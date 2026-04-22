<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Config;

use BusinessG\BaseExcel\Config\ListenerClassListMerge;
use BusinessG\HyperfExcel\Listener\HyperfExcelLogDbListener;
use BusinessG\HyperfExcel\Listener\HyperfProgressListener;
use BusinessG\HyperfExcel\Listener\RegisterRouteListener;

/**
 * Hyperf Event：`config` 中 `listeners` 与 {@see self::defaultClassNames()} 经
 * {@see ListenerClassListMerge} 合并。publish 与 autoload 两处的类名**先**拼成 config 段**再**与默认
 * 合并。各层可写 `[]` 表示本层不追加。默认不在 publish 中重复写。
 */
final class HyperfListenersConfig
{
    private function __construct()
    {
    }

    /**
     * 内置默认监听器（不依赖 publish / config 文件）。
     *
     * @return array<int, class-string>
     */
    public static function defaultClassNames(): array
    {
        return [
            HyperfProgressListener::class,
            HyperfExcelLogDbListener::class,
            RegisterRouteListener::class,
        ];
    }

    /**
     * 从 publish 与 autoload 的配置数组中收集 `listeners` 里显式声明的类名（先后合并为 [publish..., app...]）。
     *
     * @param array<string, mixed> $publishExcel
     * @param array<string, mixed> $appExcel
     *
     * @return array<int, class-string>
     */
    public static function collectConfigListenerClasses(array $publishExcel, array $appExcel = []): array
    {
        return array_merge(
            ListenerClassListMerge::normalize($publishExcel['listeners'] ?? null),
            ListenerClassListMerge::normalize($appExcel['listeners'] ?? null),
        );
    }

    /**
     * 将「仅来自配置」的类名与代码默认用 {@see ListenerClassListMerge} 合并。
     *
     * @param array<int, class-string> $configOnlyClasses
     *
     * @return array<int, class-string>
     */
    public static function mergeWithDefaults(array $configOnlyClasses = []): array
    {
        return ListenerClassListMerge::merge(self::defaultClassNames(), $configOnlyClasses);
    }

    /**
     * @param array<string, mixed> $publishExcel
     * @param array<string, mixed> $appExcel
     *
     * @return array<int, class-string>
     */
    public static function resolveFromPublishAndApp(array $publishExcel, array $appExcel = []): array
    {
        $extra = self::collectConfigListenerClasses($publishExcel, $appExcel);

        return self::mergeWithDefaults($extra);
    }
}
