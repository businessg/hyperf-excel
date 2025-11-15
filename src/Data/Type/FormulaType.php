<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Type;

/**
 * 公式类型
 */
class FormulaType extends BaseType
{
    /**
     * 获取类型名称
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'formula';
    }
}

