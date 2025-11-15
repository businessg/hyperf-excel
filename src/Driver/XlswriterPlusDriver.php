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
use Vartruexuan\HyperfExcel\Data\Export\Sheet as ExportSheet;
use Vartruexuan\HyperfExcel\Data\Type\BaseType;
use Vartruexuan\HyperfExcel\Exception\ExcelException;
use Vartruexuan\HyperfExcel\Helper\Helper;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;

class XlswriterPlusDriver extends XlsWriterDriver
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
    }


    /**
     * 导出 sheet（重写以支持单元格插入）
     *
     * @param Excel $excel
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

        $this->event->dispatch(new \Vartruexuan\HyperfExcel\Event\BeforeExportSheet($config, $this, $sheet));

        if (!empty($sheet->style)) {
            $this->exportSheetStyle($excel, $sheet->style);
        }

        [$columns, $headers, $maxDepth] = Column::processColumns($sheet->getColumns());

        $this->exportSheetHeader($excel, $headers, $maxDepth);

        // 使用单元格方式插入数据
        $this->exportSheetDataByCell($excel, $sheet, $config, $columns);

        $this->event->dispatch(new \Vartruexuan\HyperfExcel\Event\AfterExportSheet($config, $this, $sheet));
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

    /**
     * 下载单个图片（保留用于兼容）
     *
     * @param string $url
     * @return string|false
     */
    protected function downloadImage(string $url)
    {
        // 如果缓存中有，直接返回
        if (isset($this->imageCache[$url])) {
            return $this->imageCache[$url];
        }

        $tempDir = $this->getTempDir();
        $filePath = $tempDir . DIRECTORY_SEPARATOR . md5($url);

        // 如果文件已存在，直接返回并加入缓存
        if (file_exists($filePath)) {
            $this->imageCache[$url] = $filePath;
            return $filePath;
        }

        // 尝试下载
        try {
            $content = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]));

            if ($content && @file_put_contents($filePath, $content)) {
                $this->imageCache[$url] = $filePath;
                return $filePath;
            }
        } catch (\Throwable $e) {
            // 下载失败
        }

        $this->imageCache[$url] = false;
        return false;
    }
}

