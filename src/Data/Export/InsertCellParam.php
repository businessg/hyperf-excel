<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export;

use Vartruexuan\HyperfExcel\Data\BaseObject;

/**
 * 插入单元格参数
 */
class InsertCellParam extends BaseObject
{
    /**
     * 行索引
     *
     * @var int
     */
    public int $rowIndex;

    /**
     * 列索引
     *
     * @var int
     */
    public int $colIndex;

    /**
     * 单元格值
     *
     * @var mixed
     */
    public $value;

    /**
     * 列配置
     *
     * @var Column
     */
    public Column $column;

    /**
     * 导出配置（可选）
     *
     * @var ExportConfig|null
     */
    public ?ExportConfig $config = null;
}

