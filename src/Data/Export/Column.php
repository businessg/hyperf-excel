<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export;

use Vartruexuan\HyperfExcel\Data\BaseObject;
use Vartruexuan\HyperfExcel\Data\Export\Type\BaseType;
use Vartruexuan\HyperfExcel\Data\Export\Type\TextType;

/**
 * 列配置
 */
class Column extends BaseObject
{
    // 单元格类型常量（保留用于向后兼容）
    public const TYPE_TEXT = 'text';      // 文本（默认）
    public const TYPE_URL = 'url';       // 链接
    public const TYPE_FORMULA = 'formula'; // 公式
    public const TYPE_DATE = 'date';      // 日期
    public const TYPE_IMAGE = 'image';    // 图片

    /**
     * 列标识
     *
     * @var string
     */
    public string $key;

    /**
     * 列标题
     *
     * @var string
     */
    public string $title;

    /**
     * 数据类型
     * 可以是字符串（如 'text', 'url'）、数组或类型对象（如 TextType, UrlType）
     * 字符串和数组会自动转换为对应的类型对象
     *
     * @var string|array|BaseType
     */
    public string|array|BaseType $type;
    /**
     * 字段名
     *
     * @var string
     */
    public string $field;

    /**
     * 数据回调
     *
     * @var Callback
     *
     * `
     *      function($row){
     *          return $row['title'];
     *      }
     * `
     *
     */
    public $callback = null;

    /**
     * 设置列宽
     *
     * @var int
     */
    public int $width = 0;

    /**
     * 设置行高度
     *
     * @var int
     */
    public int $height = 0;


    /**
     * 当前所在列数（0开始）
     *
     * @var int
     */
    public int $col = 0;
    /**
     * 当前所在行数（0开始）
     *
     * @var int
     */
    public int $row = 0;
    /**
     * 跨行数
     *
     * @var int
     */
    public int $rowSpan = 0;
    /**
     * 跨列数
     *
     * @var int
     */
    public int $colSpan = 0;


    /**
     * 样式
     *
     * @var null|Style
     */
    public ?Style $style = null;

    /**
     * header样式
     *
     * @var Style|null
     */
    public ?Style $headerStyle = null;

    /**
     * 子列
     *
     * @var Column[]
     */
    public array $children = [];


    public bool $hasChildren = false;

    /**
     * 构造函数
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        // 确保 type 一定有值且是 BaseType 实例
        $this->type = ($this->type ?? new TextType()) instanceof BaseType 
            ? $this->type 
            : BaseType::from($this->type);
    }

    /**
     * 处理列结构并返回三个部分的数据
     *
     * @param Column[] $columns 列结构数组
     * @return array
     */
    public static function processColumns(array $columns): array
    {
        $maxDepth = static::calculateMaxDepth($columns);

        $result = static::processColumnsRecursive($columns, 0, $maxDepth - 1, 0);

        return [
            $result['leafNodes'], // 叶子节点列集合
            $result['fullStructure'], // 完整列结构数据<扁平化>
            $maxDepth,// 最大深度值
        ];
    }

    /**
     * 递归处理列结构
     *
     * @param array $columns
     * @param int $startRow
     * @param int $endRow
     * @param int $startCol
     * @return array
     */
    protected static function processColumnsRecursive(array $columns, int $startRow, int $endRow, int $startCol): array
    {
        $leafNodes = [];
        $fullStructure = [];
        $currentCol = $startCol;

        foreach ($columns as $column) {
            $hasChildren = !empty($column->children);
            $rowSpan = $hasChildren ? 1 : ($endRow - $startRow + 1);
            $colSpan = $hasChildren ? static::countLeafColumns($column->children) : 1;

            // type 会在 Column 构造函数中自动处理，确保是 BaseType 实例
            $columnInfo = new Column([
                'title' => $column->title,
                'field' => $column->field,
                'type' => $column->type ?? new TextType(),
                'callback' => $column->callback ?? null,
                'row' => $startRow,
                'width' => $column->width,
                'height' => $column->height,
                'col' => $currentCol,
                'rowSpan' => $rowSpan,
                'colSpan' => $colSpan,
                'style' => $column->style ?? null,
                'headerStyle' => $column->headerStyle ?? null,
                'hasChildren' => $hasChildren,
            ]);

            // 添加到完整结构
            $fullStructure[] = $columnInfo;

            if ($hasChildren) {
                // 处理子列
                $childResult = static::processColumnsRecursive(
                    $column->children,
                    $startRow + 1,
                    $endRow,
                    $currentCol
                );

                $leafNodes = array_merge($leafNodes, $childResult['leafNodes']);
                $fullStructure = array_merge($fullStructure, $childResult['fullStructure']);
                $currentCol += $colSpan;
            } else {
                // 叶子节点添加到叶子节点集合
                $leafNodes[] = $columnInfo;
                $currentCol += $colSpan;
            }
        }

        return [
            'leafNodes' => $leafNodes,
            'fullStructure' => $fullStructure
        ];
    }

    /**
     * 计算列结构的最大深度
     *
     * @param array $columns
     * @return int
     */
    protected static function calculateMaxDepth(array $columns): int
    {
        $maxDepth = 1;
        foreach ($columns as $column) {
            if (!empty($column->children)) {
                $childDepth = static::calculateMaxDepth($column->children) + 1;
                $maxDepth = max($maxDepth, $childDepth);
            }
        }
        return $maxDepth;
    }

    /**
     * 计算叶子列的数量
     *
     * @param array $columns
     * @return int
     */
    protected static function countLeafColumns(array $columns): int
    {
        $count = 0;
        foreach ($columns as $column) {
            if (empty($column->children)) {
                $count++;
            } else {
                $count += static::countLeafColumns($column->children);
            }
        }
        return $count;
    }

}