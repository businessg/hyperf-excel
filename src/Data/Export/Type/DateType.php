<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export\Type;

/**
 * 日期类型
 */
class DateType extends BaseType
{
    /**
     * 日期格式
     *
     * @var string|null
     */
    public ?string $dateFormat = null;

    /**
     * 获取类型名称
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'date';
    }
}

