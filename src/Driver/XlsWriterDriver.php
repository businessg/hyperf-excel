<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use Psr\Container\ContainerInterface;
use Vartruexuan\HyperfExcel\Data\Export\Column;
use Vartruexuan\HyperfExcel\Data\Export\ExportConfig;
use Vartruexuan\HyperfExcel\Data\Export\SheetStyle;
use Vartruexuan\HyperfExcel\Data\Export\Style;
use Vartruexuan\HyperfExcel\Data\Import\ImportConfig;
use Vartruexuan\HyperfExcel\Data\Export\Sheet as ExportSheet;
use Vartruexuan\HyperfExcel\Data\Import\Sheet as ImportSheet;
use Vartruexuan\HyperfExcel\Data\Type\BaseType;
use Vartruexuan\HyperfExcel\Event\AfterExportExcel;
use Vartruexuan\HyperfExcel\Event\AfterExportSheet;
use Vartruexuan\HyperfExcel\Event\AfterImportExcel;
use Vartruexuan\HyperfExcel\Event\AfterImportSheet;
use Vartruexuan\HyperfExcel\Event\BeforeExportExcel;
use Vartruexuan\HyperfExcel\Event\BeforeExportSheet;
use Vartruexuan\HyperfExcel\Event\BeforeImportExcel;
use Vartruexuan\HyperfExcel\Event\BeforeImportSheet;
use Vartruexuan\HyperfExcel\Exception\ExcelException;
use Vartruexuan\HyperfExcel\Helper\Helper;
use Vtiful\Kernel\Excel;

class XlsWriterDriver extends Driver
{
    /**
     * 图片 URL 到本地路径的映射缓存（避免重复下载）
     *
     * @var array<string, string|false>
     */
    protected array $imageCache = [];

    public function __construct(protected ContainerInterface $container, protected array $config, protected string $name)
    {
        parent::__construct($container, $config, $name);
        $this->checkPackageInstalled();
    }

    /**
     * 检查 xlswriter 扩展是否已安装
     *
     * @return void
     * @throws ExcelException
     */
    protected function checkPackageInstalled(): void
    {
        if (!class_exists(\Vtiful\Kernel\Excel::class)) {
            throw new ExcelException(
                'xlswriter extension is not installed. Please install it using: pecl install xlswriter'
            );
        }
    }

    /**
     * export
     *
     * @param ExportConfig $config
     * @param string $filePath
     * @return string
     */
    public function exportExcel(ExportConfig $config, string $filePath): string
    {
        $excel = new Excel([
            'path' => dirname($filePath),
        ]);

        $this->event->dispatch(new BeforeExportExcel($config, $this));

        foreach (array_values($config->getSheets()) as $index => $sheet) {
            $this->exportSheet($excel, $sheet, $config, $index, $filePath);
        }

        $excel->output();
        $excel->close();
        $this->event->dispatch(new AfterExportExcel($config, $this));

        return $filePath;
    }

    /**
     * import
     *
     * @param ImportConfig $config
     * @return array|null
     * @throws ExcelException
     */
    public function importExcel(ImportConfig $config): array|null
    {
        $excel = new Excel([
            'path' => $this->getTempDir(),
        ]);

        $filePath = $config->getTempPath();
        $fileName = basename($filePath);

        // 校验文件
        $this->checkFile($filePath);

        /**
         * @var ImportSheet[] $sheets
         */
        $sheets = $config->getSheets();
        $excel->openFile($fileName);

        $sheetList = $excel->sheetList();

        $this->event->dispatch(new BeforeImportExcel($config, $this));

        $sheetData = [];

        $sheets = array_map(function ($sheet) use ($sheetList) {
            if ($sheet->readType == ImportSheet::SHEET_READ_TYPE_INDEX) {
                $sheetName = $sheetList[$sheet->index];
                $sheet->name = $sheetName;
            }
            // 页码不存在
            if (!in_array($sheet->name, $sheetList)) {
                throw new ExcelException("sheet {$sheet->name} not exist");
            }
            return $sheet;
        }, $sheets);

        foreach ($sheets as $sheet) {
            $sheetData[$sheet->name] = $this->importSheet($excel, $sheet, $config);
        }

        $excel->close();

        $this->event->dispatch(new AfterImportExcel($config, $this));

        return $sheetData;
    }

    /**
     * Export sheet
     *
     * @param mixed $excel Excel实例(类型由子类决定)
     * @param ExportSheet $sheet
     * @param ExportConfig $config
     * @param int $sheetIndex
     * @param string $filePath
     * @return void
     */
    protected function exportSheet(Excel $excel, ExportSheet $sheet, ExportConfig $config, int $sheetIndex, string $filePath)
    {
        $sheetName = $sheet->getName();
        if ($sheetIndex > 0) {
            $excel->addSheet($sheetName);
        } else {
            $excel->fileName(basename($filePath), $sheetName);
        }

        $this->event->dispatch(new BeforeExportSheet($config, $this, $sheet));

        if (!empty($sheet->style)) {
            $this->exportSheetStyle($excel, $sheet->style);
        }

        [$columns, $headers, $maxDepth] = Column::processColumns($sheet->getColumns());

        $this->exportSheetHeader($excel, $headers, $maxDepth);

        // 检查是否有非 text 类型，如果有则使用单元格方式插入，否则使用 data 方式批量写入
        if ($this->hasNonTextType($columns)) {
            // 使用单元格方式插入数据
            $this->exportSheetDataByCell($excel, $sheet, $config, $columns);
        } else {
            // 使用 data 方式批量写入（性能更好）
            $this->exportSheetData(function ($data) use ($excel) {
                $excel->data($data);
            }, $sheet, $config, $columns);
        }

        $this->event->dispatch(new AfterExportSheet($config, $this, $sheet));
    }

    /**
     * 设置页码样式
     *
     * @param Excel $excel
     * @param SheetStyle $style
     * @return void
     */
    public function exportSheetStyle(Excel $excel, SheetStyle $style)
    {
        if ($style->gridline !== null) {
            $excel->gridline($style->gridline);
        }

        if ($style->zoom !== null) {
            $excel->zoom($style->zoom);
        }

        if ($style->hide) {
            $excel->setCurrentSheetHide();
        }
        if ($style->isFirst) {
            $excel->setCurrentSheetIsFirst();
        }
    }

    /**
     * 设置header
     *
     * @param Excel $excel
     * @param Column[] $columns
     * @param int $maxDepth
     * @return void
     */
    public function exportSheetHeader(Excel $excel, array $columns, int $maxDepth)
    {
        foreach ($columns as $column) {
            // 设置列header
            $colStr = Excel::stringFromColumnIndex($column->col);
            $rowIndex = $column->row + 1;
            $endStr = Excel::stringFromColumnIndex($column->col + $column->colSpan - 1); // 结束列
            $endRowIndex = $rowIndex + $column->rowSpan - 1; // 结束行
            $range = "{$colStr}{$rowIndex}:{$endStr}{$endRowIndex}";

            // 合并单元格|设置header单元格
            $excel->mergeCells($range, $column->title, !empty($column->headerStyle) ? $this->styleToResource($excel, $column->headerStyle) : null);

            // 设置高度
            if ($column->height > 0) {
                $excel->setRow($range, $column->height);
            }
            // 设置宽度|列样式
            $defaultWidth = 5 * mb_strlen($column->title, 'utf-8');
            $excel->setColumn($range, $column->width > 0 ? $column->width : $defaultWidth, !empty($column->style) ? $this->styleToResource($excel, $column->style) : null);
        }
        $excel->setCurrentLine($maxDepth);
    }

    /**
     * import sheet
     *
     * @param Excel $excel
     * @param ImportSheet $sheet
     * @param ImportConfig $config
     * @return array|null
     * @throws ExcelException
     */
    protected function importSheet(Excel $excel, ImportSheet $sheet, ImportConfig $config): array|null
    {
        $sheetName = $sheet->name;

        $this->event->dispatch(new BeforeImportSheet($config, $this, $sheet));

        $excel->openSheet($sheetName);

        $header = [];
        $sheetData = [];

        if ($sheet->headerIndex > 0) {
            // 处理多行表头：从第1行到 headerIndex 行都读取，然后合并
            $header = $this->readMultiRowHeader($excel, $sheetName, $sheet->headerIndex);
            $sheet->validateHeader($header);
        }

        $columnTypes = $sheet->getColumnTypes($header ?? []);

        if ($sheet->callback || $header) {
            $rowIndex = 0;
            if ($config->isReturnSheetData) {
                $excel->setType($columnTypes);
                // 返回全量数据
                $sheetData = $excel->getSheetData();
                if ($sheet->isSetHeader) {
                    $sheetData = $sheet->formatSheetDataByHeader($sheetData, $header);
                }
            } else {
                // 执行回调
                while (null !== $row = $excel->nextRow($columnTypes)) {
                    $this->rowCallback($config, $sheet, $row, $header, ++$rowIndex);
                }
            }
        }

        $this->event->dispatch(new AfterImportSheet($config, $this, $sheet));

        return $sheetData;
    }

    /**
     * 读取多行表头并合并
     *
     * @param Excel $excel
     * @param string $sheetName sheet 名称
     * @param int $headerIndex 表头结束行（从1开始）
     * @return array
     */
    protected function readMultiRowHeader(Excel $excel, string $sheetName, int $headerIndex): array
    {
        // 读取所有表头行（从第1行到 headerIndex 行）
        $headerRows = [];
        for ($row = 1; $row <= $headerIndex; $row++) {
            // 重新打开 sheet 并跳过前面的行
            $excel->openSheet($sheetName);
            if ($row > 1) {
                $excel->setSkipRows($row - 1);
            }
            $headerRow = $excel->nextRow();
            if ($headerRow !== null) {
                $headerRows[] = $headerRow;
            }
        }
        
        if (empty($headerRows)) {
            return [];
        }
        
        // 合并多行表头：对于每一列，从最后一行向上查找，直到找到有值的为止
        $maxCols = 0;
        foreach ($headerRows as $row) {
            $maxCols = max($maxCols, count($row));
        }
        
        $mergedHeader = [];
        for ($col = 0; $col < $maxCols; $col++) {
            $value = '';
            // 从最后一行（headerIndex行）向上查找到第一行
            for ($rowIndex = count($headerRows) - 1; $rowIndex >= 0; $rowIndex--) {
                $cellValue = $headerRows[$rowIndex][$col] ?? '';
                $cellValue = trim((string)$cellValue);
                if (!empty($cellValue)) {
                    $value = $cellValue;
                    break; // 找到有值的就停止，使用该值
                }
            }
            $mergedHeader[] = $value;
        }
        
        // 重新打开 sheet 并设置跳过 headerIndex 行，准备读取数据
        $excel->openSheet($sheetName);
        if ($headerIndex > 0) {
            $excel->setSkipRows($headerIndex);
        }
        
        return $mergedHeader;
    }

    /**
     * 执行行回调
     *
     * @param ImportConfig $config
     * @param ImportSheet $sheet
     * @param $row
     * @param null $header
     * @param int $rowIndex
     * @return void
     * @throws ExcelException
     */
    protected function rowCallback(ImportConfig $config, ImportSheet $sheet, $row, $header = null, int $rowIndex = 0)
    {
        if ($header) {
            $row = $sheet->formatRowByHeader($row, $header);
        }
        // 执行回调
        if (is_callable($sheet->callback)) {
            $this->importRowCallback($sheet->callback, $config, $sheet, $row, $rowIndex);
        }
    }

    /**
     * 校验文件mimeType类型
     *
     * @param $filePath
     * @return void
     * @throws ExcelException
     */
    protected function checkFile($filePath)
    {
        // 本地地址
        if (!file_exists($filePath)) {
            throw new ExcelException('File does not exist');
        }
        // 校验mime type
        $mimeType = Helper::getMimeType($filePath);
        if (!in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ])) {
            throw new ExcelException('File mime type error');
        }
    }

    /**
     * 样式转换
     *
     * @param Excel $excel
     * @param Style $style
     * @return resource
     */
    protected function styleToResource(Excel $excel, Style $style)
    {
        $format = new \Vtiful\Kernel\Format($excel->getHandle());

        if (!empty($style->align)) {
            $format->align(...$style->align);
        }

        if ($style->bold) {
            $format->bold();
        }

        if (!empty($style->font)) {
            $format->font($style->font);
        }

        if ($style->italic) {
            $format->italic();
        }

        if ($style->wrap) {
            $format->wrap();
        }

        if ($style->underline > 0) {
            $format->underline($style->underline);
        }

        if ($style->backgroundColor && $style->backgroundStyle) {
            $format->background($style->backgroundColor, $style->backgroundStyle > 0 ? $style->backgroundStyle : Style::PATTERN_SOLID);
        }

        if ($style->fontSize > 0) {
            $format->fontSize($style->fontSize);
        }

        if ($style->fontColor) {
            $format->fontColor($style->fontColor);
        }

        if ($style->strikeout) {
            $format->strikeout();
        }

        return $format->toResource();
    }

    /**
     * 检查列中是否有非 text 类型
     *
     * @param Column[] $columns
     * @return bool
     */
    protected function hasNonTextType(array $columns): bool
    {
        foreach ($columns as $column) {
            $type = $column->type ?? new \Vartruexuan\HyperfExcel\Data\Type\TextType();
            if (is_string($type)) {
                $type = BaseType::from($type);
            }
            
            // 如果不是 text 类型，返回 true
            if ($type->name !== 'text') {
                return true;
            }
        }
        return false;
    }

    /**
     * 按单元格方式导出数据
     *
     * @param Excel $excel
     * @param ExportSheet $sheet
     * @param ExportConfig $config
     * @param Column[] $columns
     * @return void
     */
    protected function exportSheetDataByCell(Excel $excel, ExportSheet $sheet, ExportConfig $config, array $columns)
    {
        $totalCount = $sheet->getCount();
        $pageSize = $sheet->getPageSize();
        $data = $sheet->getData();

        $isCallback = is_callable($data);

        $page = 1;
        $pageNum = ceil($totalCount / $pageSize);
        $currentRow = $excel->getCurrentLine(); // 获取当前行（header 之后的第一行，exportSheetHeader 已设置）

        do {
            $list = $dataCallback = $data;

            if (!$isCallback) {
                $totalCount = 0;
                $dataCallback = function () use (&$totalCount, $list) {
                    return $list;
                };
            }

            $list = $this->exportDataCallback($dataCallback, $config, $sheet, $page, min($totalCount, $pageSize), $totalCount);

            $listCount = count($list ?? []);

            if ($list) {
                // 格式化数据
                $formattedList = $sheet->formatList($list, $columns);
                // 批量下载图片（协程并发）
                $this->batchDownloadImages($columns, $formattedList);
                // 按单元格方式插入数据
                $this->insertDataByCell($excel, $columns, $formattedList, $currentRow);
                $currentRow += $listCount;
            }

            $isEnd = !$isCallback || $totalCount <= 0 || $totalCount <= $pageSize || ($listCount < $pageSize || $pageNum <= $page);

            $page++;
        } while (!$isEnd);

        // 更新当前行位置
        $excel->setCurrentLine($currentRow - 1);
    }

    /**
     * 按单元格方式插入数据
     *
     * @param Excel $excel
     * @param Column[] $columns
     * @param array $list
     * @param int $startRow
     * @return void
     */
    protected function insertDataByCell(Excel $excel, array $columns, array $list, int $startRow)
    {
        foreach ($list as $rowIndex => $row) {
            $excelRowIndex = $startRow + $rowIndex; // Excel 行索引（从0开始）
            foreach ($columns as $column) {
                $value = $row[$column->field] ?? '';
                $colIndex = $column->col; // 列索引（从0开始）
                $type = $column->type ?? new \Vartruexuan\HyperfExcel\Data\Type\TextType();

                // 根据类型插入单元格
                $this->insertCell($excel, $type, $excelRowIndex, $colIndex, $value, $column);
            }
        }
    }

    /**
     * 插入单元格
     *
     * @param Excel $excel
     * @param BaseType $type
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param Column $column
     * @return void
     * @throws ExcelException
     */
    protected function insertCell(Excel $excel, BaseType $type, int $rowIndex, int $colIndex, $value, Column $column)
    {
        $dataType = $type->name;
        $methodName = 'insert' . ucfirst($dataType);
        if (!method_exists($this, $methodName)) {
            // 如果方法不存在，默认使用文本类型
            $this->insertText($excel, $rowIndex, $colIndex, $value, $type, $column);
            return;
        }

        call_user_func([$this, $methodName], $excel, $rowIndex, $colIndex, $value, $type, $column);
    }

    /**
     * 插入文本
     *
     * @param Excel $excel
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertText(Excel $excel, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $textType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\TextType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\TextType();
        $format = $textType->format ?? null;
        $formatResource = $this->getCellFormat($excel, $column, $type);
        $excel->insertText($rowIndex, $colIndex, (string)$value, $format, $formatResource);
    }

    /**
     * 插入链接
     *
     * @param Excel $excel
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertUrl(Excel $excel, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $urlType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\UrlType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\UrlType();
        $url = (string)$value;
        $text = $urlType->text ?? $url;
        $tooltip = $urlType->tooltip ?? null;
        $formatResource = $this->getCellFormat($excel, $column, $type);
        $excel->insertUrl($rowIndex, $colIndex, $url, $text, $tooltip, $formatResource);
    }

    /**
     * 插入公式
     *
     * @param Excel $excel
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertFormula(Excel $excel, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $formula = (string)$value;
        $formatResource = $this->getCellFormat($excel, $column, $type);
        $excel->insertFormula($rowIndex, $colIndex, $formula, $formatResource);
    }

    /**
     * 插入日期
     *
     * @param Excel $excel
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertDate(Excel $excel, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $dateType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\DateType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\DateType();
        $dateValue = $value;
        // 如果是字符串，尝试转换为时间戳
        if (is_string($value)) {
            $timestamp = strtotime($value);
            $dateValue = $timestamp !== false ? $timestamp : time();
        } elseif (!is_numeric($value)) {
            $dateValue = time();
        }

        $dateFormat = $dateType->dateFormat ?? null;
        $formatResource = $this->getCellFormat($excel, $column, $type);
        $excel->insertDate($rowIndex, $colIndex, $dateValue, $dateFormat, $formatResource);
    }

    /**
     * 插入图片
     *
     * @param Excel $excel
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertImage(Excel $excel, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $imageType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\ImageType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\ImageType();
        $imagePath = (string)$value;
        
        // 如果是 URL，从缓存中获取已下载的路径
        if (strpos($imagePath, 'http') === 0) {
            $imagePath = $this->imageCache[$imagePath] ?? false;
            if (!$imagePath) {
                // 缓存中没有或下载失败，使用文本
                $this->insertText($excel, $rowIndex, $colIndex, $value, $type, $column);
                return;
            }
        }

        // 检查文件是否存在
        if (!file_exists($imagePath)) {
            $this->insertText($excel, $rowIndex, $colIndex, $value, $type, $column);
            return;
        }

        // 获取原图尺寸
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            // 无法获取图片尺寸，使用默认缩放比例
            $widthScale = $imageType->widthScale ?? 1.0;
            $heightScale = $imageType->heightScale ?? 1.0;
        } else {
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // 根据配置计算缩放比例
            $scales = $imageType->calculateScale($originalWidth, $originalHeight);
            $widthScale = $scales['widthScale'];
            $heightScale = $scales['heightScale'];
        }

        $excel->insertImage($rowIndex, $colIndex, $imagePath, $widthScale, $heightScale);
    }

    /**
     * 获取单元格格式资源
     *
     * @param Excel $excel
     * @param Column $column
     * @param BaseType $type
     * @return resource|null
     */
    protected function getCellFormat(Excel $excel, Column $column, BaseType $type)
    {
        // 优先使用 type 中的 formatHandler
        if ($type->formatHandler) {
            return $this->styleToResource($excel, $type->formatHandler);
        }

        // 使用 column 的 style
        if ($column->style) {
            return $this->styleToResource($excel, $column->style);
        }

        return null;
    }

    /**
     * 批量下载图片（协程并发下载）
     *
     * @param Column[] $columns
     * @param array $list
     * @return void
     */
    protected function batchDownloadImages(array $columns, array $list)
    {
        // 收集所有需要下载的图片 URL（去重）
        $imageUrls = [];
        foreach ($list as $row) {
            foreach ($columns as $column) {
                $type = $column->type ?? new \Vartruexuan\HyperfExcel\Data\Type\TextType();
                if ($type instanceof \Vartruexuan\HyperfExcel\Data\Type\ImageType || $type->name === 'image') {
                    $value = $row[$column->field] ?? '';
                    if (!empty($value) && strpos((string)$value, 'http') === 0) {
                        $url = (string)$value;
                        // 如果缓存中没有，且不在待下载列表中，则加入待下载列表
                        // 注意：已下载成功或已失败的 URL 都不会再次下载（避免重复下载）
                        if (!isset($this->imageCache[$url]) && !in_array($url, $imageUrls)) {
                            $imageUrls[] = $url;
                        }
                    }
                }
            }
        }

        if (empty($imageUrls)) {
            return;
        }

        // 使用协程并发下载
        $this->downloadImagesConcurrently($imageUrls);
    }

    /**
     * 协程并发下载图片
     *
     * @param array $urls
     * @return void
     */
    protected function downloadImagesConcurrently(array $urls)
    {
        $tempDir = $this->getTempDir();
        
        // 过滤已存在的文件
        $needDownloadUrls = [];
        foreach ($urls as $url) {
            $filePath = $tempDir . DIRECTORY_SEPARATOR . md5($url);
            if (file_exists($filePath)) {
                $this->imageCache[$url] = $filePath;
            } else {
                $needDownloadUrls[] = $url;
            }
        }

        if (empty($needDownloadUrls)) {
            return;
        }

        // 获取批量下载阈值（默认 10）
        $batchThreshold = $this->config['image_batch_threshold'] ?? 10;
        $batchThreshold = max(1, (int)$batchThreshold); // 确保至少为 1

        // 按阈值切割成多个批次
        $batches = array_chunk($needDownloadUrls, $batchThreshold);

        // 逐批次下载
        foreach ($batches as $batch) {
            // 判断是否在协程环境
            if (Coroutine::inCoroutine()) {
                // 使用 Hyperf Parallel 并发下载
                $this->downloadImagesWithParallel($batch, $tempDir);
            } else {
                // 使用 Guzzle 异步请求下载
                $this->downloadImagesWithGuzzle($batch, $tempDir);
            }
        }
    }

    /**
     * 使用 Hyperf Parallel 并发下载图片（协程环境）
     *
     * @param array $urls
     * @param string $tempDir
     * @return void
     */
    protected function downloadImagesWithParallel(array $urls, string $tempDir)
    {
        $parallel = new Parallel();
        
        // 为每个 URL 创建下载任务
        foreach ($urls as $url) {
            $parallel->add(function () use ($url, $tempDir) {
                $filePath = $tempDir . DIRECTORY_SEPARATOR . md5($url);
                
                try {
                    $content = @file_get_contents($url, false, stream_context_create([
                        'http' => [
                            'timeout' => 30,
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'follow_location' => true,
                            'max_redirects' => 5,
                        ]
                    ]));

                    if ($content && @file_put_contents($filePath, $content)) {
                        return ['url' => $url, 'success' => true, 'path' => $filePath];
                    } else {
                        return ['url' => $url, 'success' => false, 'path' => null];
                    }
                } catch (\Throwable $e) {
                    return ['url' => $url, 'success' => false, 'path' => null, 'error' => $e->getMessage()];
                }
            });
        }

        // 等待所有任务完成
        try {
            $results = $parallel->wait();
            
            foreach ($results as $result) {
                if ($result['success'] && $result['path']) {
                    $this->imageCache[$result['url']] = $result['path'];
                } else {
                    $this->imageCache[$result['url']] = false;
                }
            }
        } catch (\Throwable $e) {
            // 处理异常，标记失败的 URL
            foreach ($urls as $url) {
                if (!isset($this->imageCache[$url])) {
                    $this->imageCache[$url] = false;
                }
            }
        }
    }

    /**
     * 使用 Guzzle 异步请求下载图片（非协程环境）
     *
     * @param array $urls
     * @param string $tempDir
     * @return void
     */
    protected function downloadImagesWithGuzzle(array $urls, string $tempDir)
    {
        $client = new Client([
            'timeout' => 30,
            'verify' => false,
            'http_errors' => false,
        ]);

        // 创建异步请求
        $promises = [];
        foreach ($urls as $url) {
            $promises[$url] = $client->requestAsync('GET', $url);
        }

        // 等待所有请求完成
        try {
            $responses = Utils::unwrap($promises);
            
            foreach ($responses as $url => $response) {
                $filePath = $tempDir . DIRECTORY_SEPARATOR . md5($url);
                
                if ($response->getStatusCode() === 200) {
                    $content = $response->getBody()->getContents();
                    if ($content && @file_put_contents($filePath, $content)) {
                        $this->imageCache[$url] = $filePath;
                    } else {
                        $this->imageCache[$url] = false;
                    }
                } else {
                    $this->imageCache[$url] = false;
                }
            }
        } catch (\Throwable $e) {
            // 处理异常，标记失败的 URL
            foreach ($urls as $url) {
                if (!isset($this->imageCache[$url])) {
                    $this->imageCache[$url] = false;
                }
            }
        }
    }

}