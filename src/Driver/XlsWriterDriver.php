<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Driver\XlsWriterDriver as BaseXlsWriterDriver;
use BusinessG\BaseExcel\Helper\Helper;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class XlsWriterDriver extends BaseXlsWriterDriver
{
    public function __construct(ContainerInterface $container, array $config, string $name)
    {
        $event = $container->get(EventDispatcherInterface::class);
        /** @var FilesystemOperator $filesystem */
        $filesystem = $container->get(FilesystemFactory::class)->get($config['filesystem']['storage'] ?? 'local');

        parent::__construct($container, $config, $name, $event, $filesystem);
    }

    protected function exportOutPutStream(ExportConfig $config, string $filePath, string $path): \Psr\Http\Message\ResponseInterface
    {
        $fileName = basename($path);
        $response = $this->container->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
        $resp = $response->download($filePath, $fileName);
        foreach (Helper::getExportResponseHeaders($fileName, $filePath) as $name => $value) {
            $resp = $resp->withHeader($name, $value);
        }
        $this->deleteFile($filePath);
        return $resp;
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

    protected function getTempDirSuffix(): string
    {
        return 'hyperf-excel';
    }
}
