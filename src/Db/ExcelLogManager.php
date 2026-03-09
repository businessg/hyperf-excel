<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Db;

use BusinessG\BaseExcel\Db\AbstractExcelLogManager;
use Hyperf\Contract\ConfigInterface;
use Vartruexuan\HyperfExcel\Db\Model\ExcelLog as ExcelLogModel;

class ExcelLogManager extends AbstractExcelLogManager
{
    protected function resolveConfig(): array
    {
        return $this->container->get(ConfigInterface::class)->get('excel.dbLog', [
            'enable' => true,
            'model' => ExcelLogModel::class,
        ]);
    }

    protected function getDefaultModelClass(): string
    {
        return ExcelLogModel::class;
    }
}
