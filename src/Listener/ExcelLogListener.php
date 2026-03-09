<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ExcelLogListener as BaseExcelLogListener;
use Hyperf\Event\Contract\ListenerInterface;

/**
 * Hyperf 日志监听器，直接使用 base-excel 实现
 */
class ExcelLogListener extends BaseExcelLogListener implements ListenerInterface
{
}
