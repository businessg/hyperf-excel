<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Db\Model;

use Hyperf\Database\Model\Model;

class ExcelLog extends Model
{
    public ?string $table = 'excel_log';
}