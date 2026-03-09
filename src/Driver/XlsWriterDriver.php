<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Driver\XlsWriterDriver as BaseXlsWriterDriver;
use BusinessG\BaseExcel\Exception\ExcelException;
use BusinessG\BaseExcel\Helper\Helper;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Hyperf 适配：继承 base-excel XlsWriterDriver，增加 Hyperf Response、Filesystem 支持
 */
class XlsWriterDriver extends BaseXlsWriterDriver
{
    public function __construct(ContainerInterface $container, array $config, string $name)
    {
        $event = $container->get(EventDispatcherInterface::class);
        /** @var FilesystemOperator $filesystem */
        $filesystem = $container->get(FilesystemFactory::class)->get($config['filesystem']['storage'] ?? 'local');

        parent::__construct($container, $config, $name, $event, $filesystem);
    }

    protected function exportOutPut(ExportConfig $config, string $filePath): string|\Psr\Http\Message\ResponseInterface
    {
        $path = $this->buildExportPath($config);
        $fileName = basename($path);

        switch ($config->outPutType) {
            case ExportConfig::OUT_PUT_TYPE_UPLOAD:
                return $this->uploadToStorage($filePath, $path);

            case ExportConfig::OUT_PUT_TYPE_OUT:
                $response = $this->container->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
                $resp = $response->download($filePath, $fileName);
                $resp->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $resp->setHeader('Content-Disposition', 'attachment;filename="' . rawurlencode($fileName) . '"');
                $resp->setHeader('Content-Length', (string) filesize($filePath));
                $resp->setHeader('Content-Transfer-Encoding', 'binary');
                $resp->setHeader('Cache-Control', 'must-revalidate');
                $resp->setHeader('Cache-Control', 'max-age=0');
                $resp->setHeader('Pragma', 'public');
                $this->deleteFile($filePath);
                return $resp;

            default:
                throw new ExcelException('outPutType error');
        }
    }

    protected function deleteFile(string $filePath): void
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

    public function getTempDir(): string
    {
        $dir = ($this->config['temp_dir'] ?? null) ?: Helper::getTempDir() . DIRECTORY_SEPARATOR . 'hyperf-excel';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new ExcelException('Failed to build temporary directory: ' . $dir);
            }
        }
        return $dir;
    }
}
