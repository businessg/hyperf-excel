<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Listener;

use BusinessG\BaseExcel\Listener\ProgressListener;

class HyperfProgressListener extends HyperfListenerAdapter
{
    public function __construct(ProgressListener $listener)
    {
        parent::__construct($listener);
    }
}
