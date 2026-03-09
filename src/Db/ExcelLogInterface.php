<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Db;

use BusinessG\BaseExcel\Data\BaseConfig;

interface ExcelLogInterface
{
    public function saveLog(BaseConfig $config, array $saveParam = []): int;

    public function getConfig(): array;
}