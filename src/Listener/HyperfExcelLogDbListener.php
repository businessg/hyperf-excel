<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ExcelLogDbListener;

class HyperfExcelLogDbListener extends HyperfListenerAdapter
{
    public function __construct(ExcelLogDbListener $listener)
    {
        parent::__construct($listener);
    }
}
