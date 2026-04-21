<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Config;

use BusinessG\HyperfExcel\Listener\HyperfExcelLogDbListener;
use BusinessG\HyperfExcel\Listener\HyperfProgressListener;
use BusinessG\HyperfExcel\Listener\RegisterRouteListener;

/**
 * Hyperf excel 配置中的 `listeners`：注册到 Hyperf Event 的监听器类名列表。
 *
 * 与 {@see \BusinessG\BaseExcel\Config\ListenersConfig} 区分：此处为 Hyperf 适配器类，
 * 而非 BaseExcel 的 {@see \BusinessG\BaseExcel\Listener\AbstractBaseListener} 子类。
 */
final class HyperfListenersConfig
{
    /**
     * @param array<int, class-string> $classNames
     */
    public function __construct(
        public readonly array $classNames = [
            HyperfProgressListener::class,
            HyperfExcelLogDbListener::class,
            RegisterRouteListener::class,
        ],
    ) {
    }

    /**
     * @param array<string, mixed> $excel 合并后的 excel 配置（publish 与 config/autoload 等）
     */
    public static function fromExcelArray(array $excel): self
    {
        $configured = $excel['listeners'] ?? null;
        if (is_array($configured) && $configured !== []) {
            $classes = array_values(array_filter(
                $configured,
                static fn (mixed $c): bool => is_string($c) && $c !== ''
            ));

            return new self(classNames: $classes);
        }

        return new self();
    }
}
