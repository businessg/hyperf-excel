<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel;

use BusinessG\BaseExcel\Contract\FrameworkBridgeInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\RedisFactory;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class HyperfBridge implements FrameworkBridgeInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->container->get(ConfigInterface::class)->get($key, $default);
    }

    public function make(string $class, array $params = []): object
    {
        return \Hyperf\Support\make($class, $params);
    }

    public function getRedis(string $connection = 'default'): object
    {
        return $this->container->get(RedisFactory::class)->get($connection);
    }

    public function getLogger(string $channel = 'default'): LoggerInterface
    {
        return $this->container->get(LoggerFactory::class)->get($channel);
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->container->get(EventDispatcherInterface::class);
    }

    public function createDownloadResponse(string $filePath, string $fileName, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->container->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
        $resp = $response->download($filePath, $fileName);
        foreach ($headers as $name => $value) {
            $resp = $resp->withHeader($name, $value);
        }
        return $resp;
    }

    public function getFilesystem(string $disk = 'local'): FilesystemOperator
    {
        return $this->container->get(FilesystemFactory::class)->get($disk);
    }

    public function defer(callable $callback): void
    {
        if (Coroutine::inCoroutine()) {
            Coroutine::defer($callback);
        } else {
            $callback();
        }
    }
}
