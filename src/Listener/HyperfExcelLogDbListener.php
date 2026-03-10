<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ExcelLogDbListener;

class HyperfExcelLogDbListener extends HyperfListenerAdapter
{
    public function __construct(ExcelLogDbListener $listener)
    {
        parent::__construct($listener);
    }
}
