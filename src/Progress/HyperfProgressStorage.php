<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Progress;

use BusinessG\BaseExcel\Progress\Storage\AbstractProgressStorage;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Psr\Container\ContainerInterface;

/**
 * Hyperf Redis 进度存储实现
 */
class HyperfProgressStorage extends AbstractProgressStorage
{
    protected RedisProxy $redis;

    public function __construct(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class)->get('excel.progress', []);
        $redisConfig = $config['redis'] ?? [];
        $pool = $redisConfig['pool'] ?? 'default';
        $this->redis = $container->get(RedisFactory::class)->get($pool);
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value !== false ? (string) $value : null;
    }

    public function set(string $key, string $value, int $ttl): void
    {
        $this->redis->set($key, $value, ['EX' => $ttl]);
    }

    public function lpush(string $key, string $value, int $ttl): void
    {
        $this->redis->eval(static::getLpushLuaScript(), [$key, $value, $ttl], 1);
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
