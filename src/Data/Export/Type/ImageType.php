<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Export\Type;

/**
 * 图片类型
 */
class ImageType extends BaseType
{
    /**
     * 宽度缩放比例
     *
     * @var float|null
     */
    public ?float $widthScale = null;

    /**
     * 高度缩放比例
     *
     * @var float|null
     */
    public ?float $heightScale = null;

    /**
     * 目标宽度（像素）
     *
     * @var int|null
     */
    public ?int $width = null;

    /**
     * 目标高度（像素）
     *
     * @var int|null
     */
    public ?int $height = null;

    /**
     * 获取类型名称
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'image';
    }

    /**
     * 根据原图尺寸计算缩放比例和目标尺寸
     * 优先级：宽高 > 比例
     * 如果设置了比例，则按比例计算出宽高
     * 如果设置了宽高，则计算出对应比例
     * 最终保证四个字段都有值
     *
     * @param int $originalWidth 原图宽度
     * @param int $originalHeight 原图高度
     * @return array{width: int, height: int, widthScale: float, heightScale: float}
     */
    public function calculateScale(int $originalWidth, int $originalHeight): array
    {
        $width = $this->width;
        $height = $this->height;
        $widthScale = $this->widthScale;
        $heightScale = $this->heightScale;

        // 优先级：宽高 > 比例
        // 如果设置了宽高，优先使用宽高，并计算对应的比例
        if ($width !== null && $height !== null) {
            // 使用设置的宽高
            $widthScale = $width / $originalWidth;
            $heightScale = $height / $originalHeight;
        }
        // 如果只设置了宽度，根据宽高比计算高度和比例
        elseif ($width !== null) {
            $widthScale = $width / $originalWidth;
            // 如果未设置高度缩放比例，保持宽高比
            if ($heightScale === null) {
                $heightScale = $widthScale;
            }
            // 计算高度
            $height = (int)($originalHeight * $heightScale);
        }
        // 如果只设置了高度，根据宽高比计算宽度和比例
        elseif ($height !== null) {
            $heightScale = $height / $originalHeight;
            // 如果未设置宽度缩放比例，保持宽高比
            if ($widthScale === null) {
                $widthScale = $heightScale;
            }
            // 计算宽度
            $width = (int)($originalWidth * $widthScale);
        }
        // 如果设置了比例，按比例计算出宽高
        elseif ($widthScale !== null || $heightScale !== null) {
            // 如果只设置了宽度缩放比例，保持宽高比
            if ($widthScale !== null && $heightScale === null) {
                $heightScale = $widthScale;
            }
            // 如果只设置了高度缩放比例，保持宽高比
            elseif ($heightScale !== null && $widthScale === null) {
                $widthScale = $heightScale;
            }
            // 使用比例计算宽高
            $width = (int)($originalWidth * $widthScale);
            $height = (int)($originalHeight * $heightScale);
        }
        // 如果四个字段都未设置，使用原始尺寸（等比，缩放比例为 1.0）
        else {
            $widthScale = 1.0;
            $heightScale = 1.0;
            $width = $originalWidth;
            $height = $originalHeight;
        }

        // 更新对象属性，确保四个字段都有值
        $this->width = $width;
        $this->height = $height;
        $this->widthScale = $widthScale;
        $this->heightScale = $heightScale;

        return [
            'width' => $width,
            'height' => $height,
            'widthScale' => $widthScale,
            'heightScale' => $heightScale,
        ];
    }
}

