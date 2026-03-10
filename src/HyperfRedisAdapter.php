<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel;

use BusinessG\BaseExcel\Contract\ConfigResolverInterface;
use BusinessG\BaseExcel\Contract\RedisAdapterInterface;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class HyperfRedisAdapter implements RedisAdapterInterface
{
    private object $redis;

    public function __construct(ContainerInterface $container, ConfigResolverInterface $configResolver)
    {
        $progressConfig = $configResolver->get('excel.progress', []);
        $connection = $progressConfig['redis']['connection'] ?? $progressConfig['redis']['pool'] ?? 'default';
        $this->redis = $container->get(RedisFactory::class)->get($connection);
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value !== false ? (string) $value : null;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->redis->setex($key, $ttl, $value);
    }

    public function eval(string $script, array $keys, array $args): mixed
    {
        return $this->redis->eval($script, array_merge($keys, $args), count($keys));
    }

    public function rpop(string $key): ?string
    {
        $value = $this->redis->rPop($key);
        return $value !== false ? (string) $value : null;
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $result = $this->redis->lRange($key, $start, $stop);
        return $result !== false ? array_map('strval', $result) : [];
    }
}
