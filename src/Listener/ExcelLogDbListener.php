<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ExcelLogDbListener as BaseExcelLogDbListener;
use Hyperf\Event\Contract\ListenerInterface;

/**
 * Hyperf DB 日志监听器，直接使用 base-excel 实现
 */
class ExcelLogDbListener extends BaseExcelLogDbListener implements ListenerInterface
{
}
