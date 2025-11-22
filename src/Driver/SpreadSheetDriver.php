<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SharedDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

class SpreadSheetDriver extends Driver
{
    /**
     * 图片计数器（确保每个图片都有唯一索引）
     *
     * @var int
     */
    private static int $imageCounter = 0;

    public function __construct(protected ContainerInterface $container, protected array $config, protected string $name)
    {
        parent::__construct($container, $config, $name);
        $this->checkPackageInstalled();
    }

    /**
     * 检查 phpoffice/phpspreadsheet 包是否已安装
     *
     * @return void
     * @throws ExcelException
     */
    protected function checkPackageInstalled(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new ExcelException(
                'PhpSpreadsheet package is not installed. Please install it using: composer require phpoffice/phpspreadsheet'
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
        // 重置图片计数器（每次导出时重新开始计数）
        self::$imageCounter = 0;
        
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->event->dispatch(new BeforeExportExcel($config, $this));

        foreach (array_values($config->getSheets()) as $index => $sheet) {
            $this->exportSheet($spreadsheet, $sheet, $config, $index, $filePath);
        }

        // 保存文件
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();

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
        $filePath = $config->getTempPath();

        // 校验文件
        $this->checkFile($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->event->dispatch(new BeforeImportExcel($config, $this));

        /**
         * @var ImportSheet[] $sheets
         */
        $sheets = $config->getSheets();
        $sheetData = [];

        $sheets = array_map(function ($sheet) use ($sheetNames) {
            if ($sheet->readType == ImportSheet::SHEET_READ_TYPE_INDEX) {
                $sheetName = $sheetNames[$sheet->index] ?? null;
                if ($sheetName) {
                    $sheet->name = $sheetName;
                }
            }
            // 页码不存在
            if (!in_array($sheet->name, $sheetNames)) {
                throw new ExcelException("sheet {$sheet->name} not exist");
            }
            return $sheet;
        }, $sheets);

        foreach ($sheets as $sheet) {
            $sheetData[$sheet->name] = $this->importSheet($spreadsheet, $sheet, $config);
        }

        $spreadsheet->disconnectWorksheets();

        $this->event->dispatch(new AfterImportExcel($config, $this));

        return $sheetData;
    }

    /**
     * Export sheet
     *
     * @param Spreadsheet $spreadsheet
     * @param ExportSheet $sheet
     * @param ExportConfig $config
     * @param int $sheetIndex
     * @param string $filePath
     * @return void
     */
    protected function exportSheet(Spreadsheet $spreadsheet, ExportSheet $sheet, ExportConfig $config, int $sheetIndex, string $filePath)
    {
        $sheetName = $sheet->getName();
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle($sheetName);

        if ($sheetIndex === 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        $this->event->dispatch(new BeforeExportSheet($config, $this, $sheet));

        if (!empty($sheet->style)) {
            $this->exportSheetStyle($worksheet, $sheet->style);
        }

        [$columns, $headers, $maxDepth] = Column::processColumns($sheet->getColumns());

        $this->exportSheetHeader($worksheet, $headers, $maxDepth);

        // 检查是否有非 text 类型，如果有则使用单元格方式插入，否则使用批量写入
        if ($this->hasNonTextType($columns)) {
            // 使用单元格方式插入数据
            $this->exportSheetDataByCell($worksheet, $sheet, $config, $columns, $maxDepth);
        } else {
            // 使用批量写入（性能更好）
            $this->exportSheetData(function ($data) use ($worksheet, $maxDepth) {
                $this->writeDataBatch($worksheet, $data, $maxDepth);
            }, $sheet, $config, $columns);
        }

        $this->event->dispatch(new AfterExportSheet($config, $this, $sheet));
    }

    /**
     * 设置页码样式
     *
     * @param Worksheet $worksheet
     * @param SheetStyle $style
     * @return void
     */
    public function exportSheetStyle(Worksheet $worksheet, SheetStyle $style)
    {
        if ($style->gridline !== null) {
            $worksheet->setShowGridlines($style->gridline > 0);
        }

        if ($style->zoom !== null) {
            $worksheet->getSheetView()->setZoomScale($style->zoom);
        }

        if ($style->hide) {
            $worksheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        }

        if ($style->isFirst) {
            $worksheet->getParent()->setActiveSheetIndex($worksheet->getParent()->getIndex($worksheet));
        }
    }

    /**
     * 设置header
     *
     * @param Worksheet $worksheet
     * @param Column[] $columns
     * @param int $maxDepth
     * @return void
     */
    public function exportSheetHeader(Worksheet $worksheet, array $columns, int $maxDepth)
    {
        foreach ($columns as $column) {
            $colStr = Coordinate::stringFromColumnIndex($column->col + 1);
            $rowIndex = $column->row + 1;
            $endColStr = Coordinate::stringFromColumnIndex($column->col + $column->colSpan);
            $endRowIndex = $rowIndex + $column->rowSpan - 1;
            $range = "{$colStr}{$rowIndex}:{$endColStr}{$endRowIndex}";

            // 合并单元格
            if ($column->rowSpan > 1 || $column->colSpan > 1) {
                $worksheet->mergeCells($range);
            }

            // 设置header单元格值
            $worksheet->setCellValue($colStr . $rowIndex, $column->title);

            // 设置高度
            if ($column->height > 0) {
                $worksheet->getRowDimension($rowIndex)->setRowHeight($column->height);
            }

            // 设置宽度
            $defaultWidth = $column->width > 0 ? $column->width : (5 * mb_strlen($column->title, 'utf-8'));
            $worksheet->getColumnDimension($colStr)->setWidth($defaultWidth);

            // 设置header样式
            if (!empty($column->headerStyle)) {
                $this->applyStyle($worksheet, $range, $column->headerStyle);
            }

            // 设置列样式
            if (!empty($column->style)) {
                $colRange = "{$colStr}:{$colStr}";
                $this->applyStyle($worksheet, $colRange, $column->style);
            }
        }
    }

    /**
     * 批量写入数据
     *
     * @param Worksheet $worksheet
     * @param array $data
     * @param int $startRow
     * @return void
     */
    protected function writeDataBatch(Worksheet $worksheet, array $data, int $startRow)
    {
        foreach ($data as $rowIndex => $row) {
            $excelRowIndex = $startRow + $rowIndex + 1;
            $colIndex = 1;
            foreach ($row as $value) {
                $colStr = Coordinate::stringFromColumnIndex($colIndex);
                $worksheet->setCellValue($colStr . $excelRowIndex, $value);
                $colIndex++;
            }
        }
    }

    /**
     * 按单元格方式导出数据
     *
     * @param Worksheet $worksheet
     * @param ExportSheet $sheet
     * @param ExportConfig $config
     * @param Column[] $columns
     * @param int $maxDepth
     * @return void
     */
    protected function exportSheetDataByCell(Worksheet $worksheet, ExportSheet $sheet, ExportConfig $config, array $columns, int $maxDepth)
    {
        $totalCount = $sheet->getCount();
        $pageSize = $sheet->getPageSize();
        $data = $sheet->getData();

        $isCallback = is_callable($data);

        $page = 1;
        $pageNum = ceil($totalCount / $pageSize);
        $currentRow = $maxDepth + 1;

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
                $this->batchDownloadImages($columns, $formattedList, $config);
                // 按单元格方式插入数据
                $this->insertDataByCell($worksheet, $columns, $formattedList, $currentRow, $config);
                $currentRow += $listCount;
            }

            $isEnd = !$isCallback || $totalCount <= 0 || $totalCount <= $pageSize || ($listCount < $pageSize || $pageNum <= $page);

            $page++;
        } while (!$isEnd);
    }

    /**
     * 按单元格方式插入数据
     *
     * @param Worksheet $worksheet
     * @param Column[] $columns
     * @param array $list
     * @param int $startRow
     * @return void
     */
    protected function insertDataByCell(Worksheet $worksheet, array $columns, array $list, int $startRow, ExportConfig $config = null)
    {
        foreach ($list as $rowIndex => $row) {
            $excelRowIndex = $startRow + $rowIndex;
            foreach ($columns as $column) {
                $value = $row[$column->field] ?? '';
                $colIndex = $column->col + 1;
                $type = $column->type ?? new \Vartruexuan\HyperfExcel\Data\Type\TextType();

                // 根据类型插入单元格
                $this->insertCell($worksheet, $type, $excelRowIndex, $colIndex, $value, $column, $config);
            }
        }
    }

    /**
     * 插入单元格
     *
     * @param Worksheet $worksheet
     * @param BaseType $type
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param Column $column
     * @return void
     * @throws ExcelException
     */
    protected function insertCell(Worksheet $worksheet, BaseType $type, int $rowIndex, int $colIndex, $value, Column $column, ExportConfig $config = null)
    {
        $dataType = $type->name;
        $methodName = 'insert' . ucfirst($dataType);
        if (!method_exists($this, $methodName)) {
            // 如果方法不存在，默认使用文本类型
            $this->insertText($worksheet, $rowIndex, $colIndex, $value, $type, $column);
            return;
        }

        call_user_func([$this, $methodName], $worksheet, $rowIndex, $colIndex, $value, $type, $column, $config);
    }

    /**
     * 插入文本
     *
     * @param Worksheet $worksheet
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertText(Worksheet $worksheet, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $colStr = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $worksheet->getCell($colStr . $rowIndex);
        $cell->setValue((string)$value);

        // 应用样式
        $this->applyCellStyle($worksheet, $colStr . $rowIndex, $column, $type);
    }

    /**
     * 插入链接
     *
     * @param Worksheet $worksheet
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertUrl(Worksheet $worksheet, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $urlType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\UrlType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\UrlType();
        $url = (string)$value;
        $text = $urlType->text ?? $url;

        $colStr = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $worksheet->getCell($colStr . $rowIndex);
        $cell->getHyperlink()->setUrl($url);
        $cell->setValue($text);

        // 应用样式
        $this->applyCellStyle($worksheet, $colStr . $rowIndex, $column, $type);
    }

    /**
     * 插入公式
     *
     * @param Worksheet $worksheet
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertFormula(Worksheet $worksheet, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $formula = (string)$value;
        $colStr = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $worksheet->getCell($colStr . $rowIndex);
        $cell->setValue('=' . $formula);

        // 应用样式
        $this->applyCellStyle($worksheet, $colStr . $rowIndex, $column, $type);
    }

    /**
     * 插入日期
     *
     * @param Worksheet $worksheet
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertDate(Worksheet $worksheet, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column)
    {
        $dateType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\DateType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\DateType();
        $dateValue = $value;

        if (is_string($value)) {
            $timestamp = strtotime($value);
            $dateValue = $timestamp !== false ? $timestamp : time();
        } elseif (!is_numeric($value)) {
            $dateValue = time();
        }

        $colStr = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $worksheet->getCell($colStr . $rowIndex);
        $excelDate = SharedDate::PHPToExcel($dateValue);
        $cell->setValue($excelDate);

        $dateFormat = $dateType->dateFormat ?? 'yyyy-mm-dd';
        $cellStyle = $worksheet->getStyle($colStr . $rowIndex);
        $cellStyle->getNumberFormat()->setFormatCode($dateFormat);

        // 应用样式
        $this->applyCellStyle($worksheet, $colStr . $rowIndex, $column, $type);
    }

    /**
     * 插入图片
     *
     * @param Worksheet $worksheet
     * @param int $rowIndex
     * @param int $colIndex
     * @param mixed $value
     * @param BaseType $type
     * @param Column $column
     * @return void
     */
    protected function insertImage(Worksheet $worksheet, int $rowIndex, int $colIndex, $value, BaseType $type, Column $column, ExportConfig $config = null)
    {
        $imageType = $type instanceof \Vartruexuan\HyperfExcel\Data\Type\ImageType ? $type : new \Vartruexuan\HyperfExcel\Data\Type\ImageType();
        $imagePath = (string)$value;
        
        $hasCustomSize = ($imageType->width !== null || $imageType->height !== null || 
                         $imageType->widthScale !== null || $imageType->heightScale !== null);

        $actualImagePath = $this->getActualImagePath($imagePath, $config);
        if ($actualImagePath === null) {
            $this->insertText($worksheet, $rowIndex, $colIndex, $value, $type, $column);
            return;
        }
        
        $imagePath = $actualImagePath;

        $colStr = Coordinate::stringFromColumnIndex($colIndex);
        $cellCoordinate = $colStr . $rowIndex;

        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            $this->insertText($worksheet, $rowIndex, $colIndex, $value, $type, $column);
            return;
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        $scales = $imageType->calculateScale($originalWidth, $originalHeight);
        $finalWidth = $scales['width'];
        $finalHeight = $scales['height'];

        $rowDimension = $worksheet->getRowDimension($rowIndex);
        $currentRowHeight = $rowDimension->getRowHeight();
        if ($currentRowHeight === -1 || $currentRowHeight < $finalHeight / 1.33) {
            $rowDimension->setRowHeight($finalHeight / 1.33);
        }

        $colDimension = $worksheet->getColumnDimension($colStr);
        $currentColWidth = $colDimension->getWidth();
        $requiredColWidth = ($finalWidth / 7) + 1;
        if ($currentColWidth === -1 || $currentColWidth < $requiredColWidth) {
            $colDimension->setWidth($requiredColWidth);
        }

        self::$imageCounter++;
        $token = $config ? $config->getToken() : null;
        if ($token === null) {
            throw new ExcelException('Token is required for image export');
        }
        $cellImageDir = $this->getCellImageDir($token);
        if (!is_dir($cellImageDir)) {
            if (!mkdir($cellImageDir, 0777, true)) {
                throw new ExcelException('Failed to build cell image directory');
            }
        }
        
        $imageHash = md5($imagePath . $cellCoordinate . self::$imageCounter);
        $tempImagePath = $cellImageDir . DIRECTORY_SEPARATOR . 'img_' . $imageHash . '_' . basename($imagePath);
        
        if (!file_exists($tempImagePath)) {
            @copy($imagePath, $tempImagePath);
        }
        
        $actualImagePath = file_exists($tempImagePath) ? $tempImagePath : $imagePath;
        
        $drawing = new Drawing();
        $uniqueName = 'Image_' . self::$imageCounter . '_' . $rowIndex . '_' . $colIndex;
        $drawing->setName($uniqueName);
        $drawing->setDescription('Image ' . self::$imageCounter . ' at ' . $cellCoordinate);
        $drawing->setPath($actualImagePath);
        $drawing->setCoordinates($cellCoordinate);
        $drawing->setOffsetX(2);
        $drawing->setOffsetY(2);
        $drawing->setResizeProportional(!$hasCustomSize);
        $drawing->setWidth((int)$finalWidth);
        $drawing->setHeight((int)$finalHeight);
        $drawing->setWorksheet($worksheet);
    }

    /**
     * 应用单元格样式
     *
     * @param Worksheet $worksheet
     * @param string $cellCoordinate
     * @param Column $column
     * @param BaseType $type
     * @return void
     */
    protected function applyCellStyle(Worksheet $worksheet, string $cellCoordinate, Column $column, BaseType $type)
    {
        // 优先使用 type 中的 formatHandler
        $style = $type->formatHandler ?? $column->style;
        if ($style) {
            $this->applyStyle($worksheet, $cellCoordinate, $style);
        }
    }

    /**
     * 应用样式
     *
     * @param Worksheet $worksheet
     * @param string $range
     * @param Style $style
     * @return void
     */
    protected function applyStyle(Worksheet $worksheet, string $range, Style $style)
    {
        $cellStyle = $worksheet->getStyle($range);

        // 对齐
        if (!empty($style->align)) {
            $horizontal = Alignment::HORIZONTAL_GENERAL;
            $vertical = Alignment::VERTICAL_BOTTOM;

            foreach ($style->align as $align) {
                switch ($align) {
                    case Style::FORMAT_ALIGN_LEFT:
                        $horizontal = Alignment::HORIZONTAL_LEFT;
                        break;
                    case Style::FORMAT_ALIGN_CENTER:
                        $horizontal = Alignment::HORIZONTAL_CENTER;
                        break;
                    case Style::FORMAT_ALIGN_RIGHT:
                        $horizontal = Alignment::HORIZONTAL_RIGHT;
                        break;
                    case Style::FORMAT_ALIGN_VERTICAL_TOP:
                        $vertical = Alignment::VERTICAL_TOP;
                        break;
                    case Style::FORMAT_ALIGN_VERTICAL_CENTER:
                        $vertical = Alignment::VERTICAL_CENTER;
                        break;
                    case Style::FORMAT_ALIGN_VERTICAL_BOTTOM:
                        $vertical = Alignment::VERTICAL_BOTTOM;
                        break;
                }
            }

            $cellStyle->getAlignment()->setHorizontal($horizontal);
            $cellStyle->getAlignment()->setVertical($vertical);
        }

        // 字体
        $font = $cellStyle->getFont();
        if ($style->bold) {
            $font->setBold(true);
        }
        if ($style->italic) {
            $font->setItalic(true);
        }
        if ($style->fontSize > 0) {
            $font->setSize($style->fontSize);
        }
        if ($style->font) {
            $font->setName($style->font);
        }
        if ($style->fontColor) {
            $font->getColor()->setRGB(sprintf('%06X', $style->fontColor));
        }
        if ($style->underline > 0) {
            $underlineMap = [
                Style::UNDERLINE_SINGLE => Font::UNDERLINE_SINGLE,
                Style::UNDERLINE_DOUBLE => Font::UNDERLINE_DOUBLE,
            ];
            $font->setUnderline($underlineMap[$style->underline] ?? Font::UNDERLINE_SINGLE);
        }
        if ($style->strikeout) {
            $font->setStrikethrough(true);
        }

        // 填充
        if ($style->backgroundColor && $style->backgroundStyle) {
            $fill = $cellStyle->getFill();
            $fill->setFillType(Fill::FILL_SOLID);
            $fill->getStartColor()->setRGB(sprintf('%06X', $style->backgroundColor));
        }

        // 边框
        if ($style->border > 0) {
            $borders = $cellStyle->getBorders();
            $borderStyle = Border::BORDER_THIN;
            $borders->getAllBorders()->setBorderStyle($borderStyle);
        }

        // 文本换行
        if ($style->wrap) {
            $cellStyle->getAlignment()->setWrapText(true);
        }
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
     * import sheet
     *
     * @param Spreadsheet $spreadsheet
     * @param ImportSheet $sheet
     * @param ImportConfig $config
     * @return array|null
     * @throws ExcelException
     */
    protected function importSheet(Spreadsheet $spreadsheet, ImportSheet $sheet, ImportConfig $config): array|null
    {
        $sheetName = $sheet->name;

        $this->event->dispatch(new BeforeImportSheet($config, $this, $sheet));

        $worksheet = $spreadsheet->getSheetByName($sheetName);
        if (!$worksheet) {
            throw new ExcelException("sheet {$sheetName} not exist");
        }

        $header = [];
        $sheetData = [];

        // 读取 header（支持多行表头）
        if ($sheet->headerIndex > 0) {
            $header = $this->readMultiRowHeader($worksheet, $sheet->headerIndex);
            $sheet->validateHeader($header);
        }

        $columnTypes = $sheet->getColumnTypes($header ?? []);

        if ($sheet->callback || $header) {
            $startRow = $sheet->headerIndex > 0 ? $sheet->headerIndex + 1 : 1;
            $highestRow = $worksheet->getHighestRow();

            if ($config->isReturnSheetData) {
                // 返回全量数据
                $sheetData = [];
                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $rowData = [];
                    $colIndex = 0;
                    foreach ($worksheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
                        $value = $cell->getValue();
                        // 类型转换
                        if (isset($columnTypes[$colIndex])) {
                            $value = $this->convertCellValue($value, $columnTypes[$colIndex]);
                        }
                        $rowData[] = $value;
                        $colIndex++;
                    }
                    if (!empty($rowData) || !$sheet->skipEmptyRow) {
                        $sheetData[] = $rowData;
                    }
                }

                if ($sheet->isSetHeader) {
                    $sheetData = $sheet->formatSheetDataByHeader($sheetData, $header);
                }
            } else {
                // 执行回调
                $rowIndex = 0;
                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $rowData = [];
                    $colIndex = 0;
                    foreach ($worksheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
                        $value = $cell->getValue();
                        // 类型转换
                        if (isset($columnTypes[$colIndex])) {
                            $value = $this->convertCellValue($value, $columnTypes[$colIndex]);
                        }
                        $rowData[] = $value;
                        $colIndex++;
                    }

                    if (!empty($rowData) || !$sheet->skipEmptyRow) {
                        $this->rowCallback($config, $sheet, $rowData, $header, ++$rowIndex);
                    }
                }
            }
        }

        $this->event->dispatch(new AfterImportSheet($config, $this, $sheet));

        return $sheetData;
    }

    /**
     * 读取多行表头并合并
     *
     * @param Worksheet $worksheet
     * @param int $headerIndex 表头结束行（从1开始）
     * @return array
     */
    protected function readMultiRowHeader(Worksheet $worksheet, int $headerIndex): array
    {
        // 读取所有表头行（从第1行到 headerIndex 行）
        $headerRows = [];
        for ($row = 1; $row <= $headerIndex; $row++) {
            $headerRow = [];
            $rowIterator = $worksheet->getRowIterator($row, $row);
            $rowData = $rowIterator->current();
            if ($rowData) {
                foreach ($rowData->getCellIterator() as $cell) {
                    $headerRow[] = $cell->getValue();
                }
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
        
        return $mergedHeader;
    }

    /**
     * 转换单元格值类型
     *
     * @param mixed $value
     * @param string|int $type
     * @return mixed
     */
    protected function convertCellValue($value, string|int $type)
    {
        if ($value === null) {
            return null;
        }

        // 如果是 int 类型（Column 常量），转换为字符串
        if (is_int($type)) {
            $type = $this->convertColumnTypeToString($type);
        }

        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            default:
                return $value;
        }
    }

    /**
     * 将 Column 类型常量转换为字符串
     *
     * @param int $type
     * @return string
     */
    protected function convertColumnTypeToString(int $type): string
    {
        return match ($type) {
            \Vartruexuan\HyperfExcel\Data\Import\Column::TYPE_INT => 'int',
            \Vartruexuan\HyperfExcel\Data\Import\Column::TYPE_DOUBLE => 'float',
            \Vartruexuan\HyperfExcel\Data\Import\Column::TYPE_TIMESTAMP => 'int',
            \Vartruexuan\HyperfExcel\Data\Import\Column::TYPE_STRING => 'string',
            default => 'string',
        };
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

}

