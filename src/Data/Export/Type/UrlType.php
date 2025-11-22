<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export\Type;

/**
 * 链接类型
 */
class UrlType extends BaseType
{
    /**
     * 链接文字（如果为空则使用 URL 本身）
     *
     * @var string|null
     */
    public ?string $text = null;

    /**
     * 链接提示
     *
     * @var string|null
     */
    public ?string $tooltip = null;

    /**
     * 获取类型名称
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'url';
    }
}

