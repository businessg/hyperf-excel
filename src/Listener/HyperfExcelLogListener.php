<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ExcelLogListener;

class HyperfExcelLogListener extends HyperfListenerAdapter
{
    public function __construct(ExcelLogListener $listener)
    {
        parent::__construct($listener);
    }
}
