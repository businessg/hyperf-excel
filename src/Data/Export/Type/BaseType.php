<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export\Type;

use Vartruexuan\HyperfExcel\Data\BaseObject;

/**
 * 单元格类型基类
 */
abstract class BaseType extends BaseObject
{
    /**
     * 类型名称
     *
     * @var string
     */
    public string $name;

    /**
     * 格式化处理器（样式）
     *
     * @var mixed
     */
    public $formatHandler = null;

    /**
     * 构造函数
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (empty($this->name)) {
            $this->name = static::getName();
        }
    }

    /**
     * 获取类型名称
     *
     * @return string
     */
    abstract public static function getName(): string;

    /**
     * 从字符串创建类型对象
     *
     * @param string|BaseType|array $type
     * @return BaseType
     */
    public static function from(string|BaseType|array $type): BaseType
    {
        if ($type instanceof BaseType) {
            return $type;
        }

        if (is_array($type)) {
            $typeName = $type['name'] ?? $type['type'] ?? 'text';
            $className = static::getTypeClass($typeName);
            return new $className($type);
        }

        if (is_string($type)) {
            $className = static::getTypeClass($type);
            return new $className();
        }

        // 默认返回文本类型
        return new TextType();
    }

    /**
     * 根据类型名称获取类型类
     *
     * @param string $typeName
     * @return string
     */
    protected static function getTypeClass(string $typeName): string
    {
        $typeMap = [
            'text' => TextType::class,
            'url' => UrlType::class,
            'formula' => FormulaType::class,
            'date' => DateType::class,
            'image' => ImageType::class,
        ];

        $typeName = strtolower($typeName);
        return $typeMap[$typeName] ?? TextType::class;
    }
}

