<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Type;

/**
 * 文本类型
 */
class TextType extends BaseType
{
    /**
     * 格式化字符串
     *
     * @var string|null
     */
    public ?string $format = null;

    /**
     * 获取类型名称
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'text';
    }
}

