<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\AbstractBaseListener;
use Hyperf\Event\Contract\ListenerInterface;

/**
 * Hyperf 监听器基类，实现 ListenerInterface 以适配 Hyperf 事件系统
 */
abstract class BaseListener extends AbstractBaseListener implements ListenerInterface
{
}
