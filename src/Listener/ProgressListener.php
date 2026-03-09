<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ProgressListener as BaseProgressListener;
use Hyperf\Event\Contract\ListenerInterface;

/**
 * Hyperf 进度监听器，直接使用 base-excel 实现
 */
class ProgressListener extends BaseProgressListener implements ListenerInterface
{
}
