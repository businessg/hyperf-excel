<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Data\Type;

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
     * 根据原图尺寸计算缩放比例或目标尺寸
     *
     * @param int $originalWidth 原图宽度
     * @param int $originalHeight 原图高度
     * @return array{widthScale: float, heightScale: float}
     */
    public function calculateScale(int $originalWidth, int $originalHeight): array
    {
        $widthScale = $this->widthScale;
        $heightScale = $this->heightScale;

        // 如果设置了目标宽度和高度，计算缩放比例
        if ($this->width !== null && $this->height !== null) {
            $widthScale = $this->width / $originalWidth;
            $heightScale = $this->height / $originalHeight;
        }
        // 如果只设置了目标宽度，根据宽高比计算高度和缩放比例
        elseif ($this->width !== null) {
            $widthScale = $this->width / $originalWidth;
            // 如果未设置高度缩放比例，保持宽高比
            if ($heightScale === null) {
                $heightScale = $widthScale;
            }
        }
        // 如果只设置了目标高度，根据宽高比计算宽度和缩放比例
        elseif ($this->height !== null) {
            $heightScale = $this->height / $originalHeight;
            // 如果未设置宽度缩放比例，保持宽高比
            if ($widthScale === null) {
                $widthScale = $heightScale;
            }
        }
        // 如果设置了宽度缩放比例，但未设置高度缩放比例，保持宽高比
        elseif ($widthScale !== null && $heightScale === null) {
            $heightScale = $widthScale;
        }
        // 如果设置了高度缩放比例，但未设置宽度缩放比例，保持宽高比
        elseif ($heightScale !== null && $widthScale === null) {
            $widthScale = $heightScale;
        }
        // 如果都没设置，使用默认值 1.0
        else {
            $widthScale = $widthScale ?? 1.0;
            $heightScale = $heightScale ?? 1.0;
        }

        return [
            'widthScale' => $widthScale,
            'heightScale' => $heightScale,
        ];
    }
}

