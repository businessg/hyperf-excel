<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Db;

use BusinessG\BaseExcel\Config\ExcelConfig;
use BusinessG\BaseExcel\Contract\ConfigResolverInterface;
use BusinessG\BaseExcel\Db\ExcelLogRepositoryInterface;

class HyperfExcelLogRepository implements ExcelLogRepositoryInterface
{
    private ?string $modelClass;

    public function __construct(ConfigResolverInterface $configResolver)
    {
        $excelConfig = ExcelConfig::fromArray($configResolver->get('excel', []));
        $this->modelClass = $excelConfig->dbLog->model;
    }

    public function upsert(array $data): int
    {
        if (!$this->modelClass) {
            return 0;
        }
        return $this->modelClass::query()->upsert([$data], ['token']);
    }
}
