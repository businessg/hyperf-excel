<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Parallel;
use Vartruexuan\HyperfExcel\Data\Export\Column;
use Vartruexuan\HyperfExcel\Data\Export\ExportConfig;
use Vartruexuan\HyperfExcel\Data\Export\Type\ImageType;
use Vartruexuan\HyperfExcel\Data\Export\Type\TextType;
use Vartruexuan\HyperfExcel\Exception\ExcelException;

trait ImageTrait
{
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
            $token = $config?->getToken();
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
            $this->downloadImagesWithGuzzle($batch, $token);
        }
    }

    /**
     * 使用 Guzzle 下载图片（自动适配协程/非协程环境）
     * 协程环境：使用 Parallel + Guzzle 并发下载
     * 非协程环境：使用 Guzzle 异步请求
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

        if (Coroutine::inCoroutine()) {
            // 协程环境：使用 Parallel + Guzzle
            $parallel = new Parallel();

            foreach ($urls as $url) {
                $parallel->add(function () use ($client, $url, $token) {
                    $filePath = $this->getImageFilePath($token, $url);
                    
                    if (file_exists($filePath)) {
                        return;
                    }

                    try {
                        $response = $client->request('GET', $url);
                        
                        if ($response->getStatusCode() === 200) {
                            $content = $response->getBody()->getContents();
                            if ($content) {
                                @file_put_contents($filePath, $content);
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                });
            }

            try {
                $parallel->wait();
            } catch (\Throwable $e) {
            }
        } else {
            // 非协程环境：使用 Guzzle 异步请求
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
    }
}

