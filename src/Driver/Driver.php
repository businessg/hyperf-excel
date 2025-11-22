<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\Filesystem;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Vartruexuan\HyperfExcel\Data\Export\ExportCallbackParam;
use Vartruexuan\HyperfExcel\Data\Export\ExportConfig;
use Vartruexuan\HyperfExcel\Data\Export\ExportData;
use Vartruexuan\HyperfExcel\Data\Import\ImportConfig;
use Vartruexuan\HyperfExcel\Data\Import\ImportData;
use Vartruexuan\HyperfExcel\Data\Import\ImportRowCallbackParam;
use Vartruexuan\HyperfExcel\Event\AfterExportData;
use Vartruexuan\HyperfExcel\Event\AfterExportOutput;
use Vartruexuan\HyperfExcel\Event\AfterImportData;
use Vartruexuan\HyperfExcel\Event\BeforeExportData;
use Vartruexuan\HyperfExcel\Event\BeforeExportOutput;
use Vartruexuan\HyperfExcel\Event\BeforeImportData;
use Vartruexuan\HyperfExcel\Event\Error;
use Vartruexuan\HyperfExcel\Exception\ExcelException;
use Vartruexuan\HyperfExcel\Helper\Helper;
use Vartruexuan\HyperfExcel\Data\Import\Sheet as ImportSheet;
use Vartruexuan\HyperfExcel\Data\Export\Sheet as ExportSheet;
use Vartruexuan\HyperfExcel\Strategy\Path\ExportPathStrategyInterface;
use Hyperf\Coroutine\Coroutine;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Hyperf\Coroutine\Parallel;
use Vartruexuan\HyperfExcel\Data\Export\Column;
use Vartruexuan\HyperfExcel\Data\Type\ImageType;
use Vartruexuan\HyperfExcel\Data\Type\TextType;

abstract class Driver implements DriverInterface
{
    public EventDispatcherInterface $event;
    public Filesystem $filesystem;

    public function __construct(protected ContainerInterface $container, protected array $config, protected string $name)
    {
        $this->event = $container->get(EventDispatcherInterface::class);
        $this->filesystem = $this->container->get(FilesystemFactory::class)->get($this->config['filesystem']['storage'] ?? 'local');
    }

    public function export(ExportConfig $config): ExportData
    {
        try {
            $exportData = new ExportData(['token' => $config->getToken()]);

            $filePath = $this->getTempFileName();

            $path = $this->exportExcel($config, $filePath);

            $this->event->dispatch(new BeforeExportOutput($config, $this));

            $exportData->response = $this->exportOutPut($config, $path);

            $this->event->dispatch(new AfterExportOutput($config, $this, $exportData));

            return $exportData;
        } catch (ExcelException $exception) {
            $this->event->dispatch(new Error($config, $this, $exception));
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->event->dispatch(new Error($config, $this, $throwable));
            throw $throwable;
        }
    }

    public function import(ImportConfig $config): importData
    {
        try {

            $importData = new ImportData(['token' => $config->getToken()]);

            $config->setTempPath($this->fileToTemp($config->getPath()));

            $importData->sheetData = $this->importExcel($config);

            $this->cleanCache($config->getTempPath(), $config->getToken());

        } catch (ExcelException $exception) {

            $this->event->dispatch(new Error($config, $this, $exception));
            throw $exception;
        } catch (\Throwable $throwable) {

            $this->event->dispatch(new Error($config, $this, $throwable));
            throw $throwable;
        }

        return $importData;
    }

    /**
     * 文件to临时文件
     *
     * @param $path
     * @return string
     * @throws ExcelException
     */
    protected function fileToTemp($path)
    {
        $filePath = $this->getTempFileName();

        if (!Helper::isUrl($path)) {
            if (!is_file($path)) {
                throw new ExcelException(sprintf('File not exists[%s]', $path));
            }
            if (!copy($path, $filePath)) {
                throw new ExcelException('File copy error');
            }
        } else {
            if (!Helper::downloadFile($path, $filePath)) {
                throw new ExcelException('File download error');
            }
        }
        return $filePath;
    }

    /**
     * 获取临时文件
     *
     * @return string
     * @throws ExcelException
     */
    public function getTempFileName(): string
    {
        if (!$filePath = Helper::getTempFileName($this->getTempDir(), 'ex_')) {
            throw new ExcelException('Failed to build temporary file');
        }
        return $filePath;
    }

    /**
     * 获取临时目录
     *
     * @return string
     * @throws ExcelException
     */
    public function getTempDir(): string
    {
        $dir = Helper::getTempDir() . DIRECTORY_SEPARATOR . 'hyperf-excel';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new ExcelException('Failed to build temporary directory');
            }
        }
        return $dir;
    }

    /**
     * 导出数据回调
     *
     * @param callable $callback 回调
     * @param ExportConfig $config
     * @param ExportSheet $sheet
     * @param int $page 页码
     * @param int $pageSize 限制每页数量
     * @param int|null $totalCount
     * @return mixed
     */
    protected function exportDataCallback(callable $callback, ExportConfig $config, ExportSheet $sheet, int $page, int $pageSize, ?int $totalCount)
    {
        $exportCallbackParam = new ExportCallbackParam([
            'driver' => $this,
            'config' => $config,
            'sheet' => $sheet,

            'page' => $page,
            'pageSize' => $pageSize,
            'totalCount' => $totalCount,
        ]);

        $this->event->dispatch(new BeforeExportData($config, $this, $exportCallbackParam));

        $result = call_user_func($callback, $exportCallbackParam);

        $this->event->dispatch(new AfterExportData($config, $this, $exportCallbackParam, $result ?? []));

        return $result;
    }

    protected function exportSheetData(callable $writeDataFun, ExportSheet $sheet, ExportConfig $config, array $columns)
    {
        $totalCount = $sheet->getCount();
        $pageSize = $sheet->getPageSize();
        $data = $sheet->getData();

        $isCallback = is_callable($data);

        $page = 1;
        $pageNum = ceil($totalCount / $pageSize);

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
                $writeDataFun($sheet->formatList($list, $columns));
            }

            $isEnd = !$isCallback || $totalCount <= 0 || $totalCount <= $pageSize || ($listCount < $pageSize || $pageNum <= $page);

            $page++;
        } while (!$isEnd);

    }

    /**
     * 导入行回调
     *
     * @param callable $callback
     * @param ImportConfig $config
     * @param ImportSheet $sheet
     * @param array $row
     * @param int $rowIndex
     * @return mixed|null
     */
    protected function importRowCallback(callable $callback, ImportConfig $config, ImportSheet $sheet, array $row, int $rowIndex)
    {
        $importRowCallbackParam = new ImportRowCallbackParam([
            'excel' => $this,
            'sheet' => $sheet,
            'config' => $config,
            'row' => $row,
            'rowIndex' => $rowIndex,
        ]);

        $this->event->dispatch(new BeforeImportData($config, $this, $importRowCallbackParam));
        try {
            $result = call_user_func($callback, $importRowCallbackParam);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }
        $this->event->dispatch(new AfterImportData($config, $this, $importRowCallbackParam, $exception ?? null));

        return $result ?? null;
    }

    /**
     * 导出文件输出
     *
     * @param ExportConfig $config
     * @param string $filePath
     * @return string|Psr\Http\Message\ResponseInterface
     * @throws ExcelException
     */
    protected function exportOutPut(ExportConfig $config, string $filePath): string|\Psr\Http\Message\ResponseInterface
    {
        $path = $this->buildExportPath($config);
        $fileName = basename($path);
        switch ($config->outPutType) {
            case ExportConfig::OUT_PUT_TYPE_UPLOAD:
                try {
                    $this->filesystem->writeStream($path, fopen($filePath, 'r+'));
                    $this->cleanCache($filePath, $config->getToken());
                } catch (\Throwable $throwable) {
                    throw new ExcelException('File upload failed:' . $throwable->getMessage() . ',' . get_class($throwable));
                }
                if (!$this->filesystem->fileExists($path)) {
                    throw new ExcelException('File upload failed');
                }

                return $path;
                break;
            case ExportConfig::OUT_PUT_TYPE_OUT:
                $response = $this->container->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
                $resp = $response->download($filePath, $fileName);
                $resp->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $resp->setHeader('Content-Disposition', 'attachment;filename="' . rawurlencode($fileName) . '"');
                $resp->setHeader('Content-Length', filesize($filePath));
                $resp->setHeader('Content-Transfer-Encoding', 'binary');
                $resp->setHeader('Cache-Control', 'must-revalidate');
                $resp->setHeader('Cache-Control', 'max-age=0');
                $resp->setHeader('Pragma', 'public');
                $this->cleanCache($filePath, $config->getToken());
                return $resp;
                break;
            default:
                throw new ExcelException('outPutType error');
        }
    }

    protected function deleteFile(string $filePath)
    {
        $callback = function () use ($filePath) {
            if (file_exists($filePath)) {
                Helper::deleteFile($filePath);
            }
        };
        if (Coroutine::inCoroutine()) {
            Coroutine::defer($callback);
        } else {
            $callback();
        }
    }

    /**
     * 清理缓存（文件 + 图片）
     *
     * @param string $filePath
     * @param string $token
     * @return void
     * @throws ExcelException
     */
    protected function cleanCache(string $filePath, string $token): void
    {
        $this->deleteFile($filePath);
        $this->cleanupImageCache($token);
    }

    /**
     * 获取图片目录路径
     *
     * @param string $token
     * @return string
     * @throws ExcelException
     */
    protected function getImageDir(string $token): string
    {
        $tempDir = $this->getTempDir();
        return $tempDir . DIRECTORY_SEPARATOR . $token . DIRECTORY_SEPARATOR . 'images';
    }

    /**
     * 获取图片文件路径
     *
     * @param string $token
     * @param string $url 图片URL
     * @return string
     * @throws ExcelException
     */
    protected function getImageFilePath(string $token, string $url): string
    {
        $imageDir = $this->getImageDir($token);
        return $imageDir . DIRECTORY_SEPARATOR . md5($url);
    }

    /**
     * 获取单元格图片目录路径
     *
     * @param string $token
     * @return string
     * @throws ExcelException
     */
    protected function getCellImageDir(string $token): string
    {
        $imageDir = $this->getImageDir($token);
        return $imageDir . DIRECTORY_SEPARATOR . 'cells';
    }

    /**
     * 根据图片地址获取实际文件路径（如果存在）
     *
     * @param string $imagePath 图片地址（可能是 URL 或本地路径）
     * @param ExportConfig|null $config 导出配置
     * @return string|null 返回实际文件路径，如果文件不存在则返回 null
     * @throws ExcelException
     */
    protected function getActualImagePath(string $imagePath, ?ExportConfig $config = null): ?string
    {
        if (str_starts_with($imagePath, 'http')) {
            $token = $config ? $config->getToken() : null;
            if ($token === null) {
                return null;
            }
            $filePath = $this->getImageFilePath($token, $imagePath);
            if (file_exists($filePath)) {
                return $filePath;
            }
            return null;
        }

        if (file_exists($imagePath)) {
            return $imagePath;
        }

        return null;
    }

    /**
     * 清理缓存的图片
     *
     * @param string $token
     * @return void
     * @throws ExcelException
     */
    protected function cleanupImageCache(string $token): void
    {
        $tempDir = $this->getTempDir();
        $tokenDir = $tempDir . DIRECTORY_SEPARATOR . $token;
        if (is_dir($tokenDir)) {
            $this->deleteDirectory($tokenDir);
        }
    }

    /**
     * 递归删除目录及其所有内容
     *
     * @param string $dir
     * @return bool
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * 构建导出地址
     *
     * @param ExportConfig $config
     * @return string
     */
    protected function buildExportPath(ExportConfig $config)
    {
        $strategy = $this->container->get(ExportPathStrategyInterface::class);
        return implode(DIRECTORY_SEPARATOR, array_filter([
            $this->config['export']['rootDir'] ?? null,
            $strategy->getPath($config),
        ]));
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 批量下载图片（协程并发下载）
     *
     * @param Column[] $columns
     * @param array $list
     * @param ExportConfig $config
     * @return void
     * @throws ExcelException
     */
    protected function batchDownloadImages(array $columns, array $list, ExportConfig $config)
    {
        $imageUrls = [];
        $token = $config->getToken();
        
        foreach ($list as $row) {
            foreach ($columns as $column) {
                $type = $column->type ?? new TextType();
                if ($type instanceof ImageType || $type->name === 'image') {
                    $value = $row[$column->field] ?? '';
                    if (!empty($value) && str_starts_with((string)$value, 'http')) {
                        $url = (string)$value;
                        $filePath = $this->getImageFilePath($token, $url);
                        if (!file_exists($filePath) && !in_array($url, $imageUrls)) {
                            $imageUrls[] = $url;
                        }
                    }
                }
            }
        }

        if (empty($imageUrls)) {
            return;
        }

        $this->downloadImagesConcurrently($imageUrls, $config);
    }

    /**
     * 协程并发下载图片
     *
     * @param array $urls
     * @param ExportConfig $config
     * @return void
     * @throws ExcelException
     */
    protected function downloadImagesConcurrently(array $urls, ExportConfig $config)
    {
        $token = $config->getToken();
        $imageDir = $this->getImageDir($token);
        
        if (!is_dir($imageDir) && !mkdir($imageDir, 0777, true)) {
            throw new ExcelException('Failed to build image directory');
        }

        $needDownloadUrls = [];
        foreach ($urls as $url) {
            $filePath = $this->getImageFilePath($token, $url);
            if (!file_exists($filePath)) {
                $needDownloadUrls[] = $url;
            }
        }

        if (empty($needDownloadUrls)) {
            return;
        }

        $batchThreshold = $this->config['image_batch_threshold'] ?? 10;
        $batchThreshold = max(1, (int)$batchThreshold);
        $batches = array_chunk($needDownloadUrls, $batchThreshold);

        foreach ($batches as $batch) {
            if (Coroutine::inCoroutine()) {
                $this->downloadImagesWithParallel($batch, $token);
            } else {
                $this->downloadImagesWithGuzzle($batch, $token);
            }
        }
    }

    /**
     * 使用 Hyperf Parallel 并发下载图片（协程环境）
     *
     * @param array $urls
     * @param string $token
     * @return void
     * @throws ExcelException
     */
    protected function downloadImagesWithParallel(array $urls, string $token)
    {
        $parallel = new Parallel();

        foreach ($urls as $url) {
            $parallel->add(function () use ($url, $token) {
                $filePath = $this->getImageFilePath($token, $url);
                
                if (file_exists($filePath)) {
                    return;
                }

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

                    if ($content) {
                        @file_put_contents($filePath, $content);
                    }
                } catch (\Throwable $e) {
                }
            });
        }

        try {
            $parallel->wait();
        } catch (\Throwable $e) {
        }
    }

    /**
     * 使用 Guzzle 异步请求下载图片（非协程环境）
     *
     * @param array $urls
     * @param string $token
     * @return void
     * @throws ExcelException
     */
    protected function downloadImagesWithGuzzle(array $urls, string $token)
    {
        $client = new Client([
            'timeout' => 30,
            'verify' => false,
            'http_errors' => false,
        ]);

        $promises = [];
        foreach ($urls as $url) {
            $filePath = $this->getImageFilePath($token, $url);
            if (file_exists($filePath)) {
                continue;
            }
            $promises[$url] = $client->requestAsync('GET', $url);
        }

        if (empty($promises)) {
            return;
        }

        try {
            $responses = Utils::unwrap($promises);

            foreach ($responses as $url => $response) {
                $filePath = $this->getImageFilePath($token, $url);

                if ($response->getStatusCode() === 200) {
                    $content = $response->getBody()->getContents();
                    if ($content) {
                        @file_put_contents($filePath, $content);
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    }

    abstract function exportExcel(ExportConfig $config, string $filePath): string;

    abstract function importExcel(ImportConfig $config): array|null;
}