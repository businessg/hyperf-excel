<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Db;

use BusinessG\BaseExcel\Data\BaseConfig;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Progress\ProgressData;
use BusinessG\BaseExcel\Progress\ProgressInterface;
use BusinessG\BaseExcel\Progress\ProgressRecord;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Vartruexuan\HyperfExcel\Db\Model\ExcelLog as ExcelLogModel;

class ExcelLogManager implements ExcelLogInterface
{
    public string $model;
    public const TYPE_EXPORT = 'export';
    public const TYPE_IMPORT = 'import';

    protected array $config;

    public function __construct(protected ContainerInterface $container, protected ProgressInterface $progress)
    {
        $config = $this->container->get(ConfigInterface::class);
        $this->config = $config->get('excel.dbLog', [
            'enable' => true,
            'model' => ExcelLogModel::class,
        ]);
        $this->model = $this->config['model'] ?? ExcelLogModel::class;
    }

    /**
     * 保存记录信息
     *
     * @param BaseConfig $config
     * @param array $saveParam
     * @return int
     */
    public function saveLog(BaseConfig $config, array $saveParam = []): int
    {
        $token = $config->getToken();

        $type = $config instanceof ExportConfig ? static::TYPE_EXPORT : static::TYPE_IMPORT;

        $progressRecord = $this->getProgressByToken($token);

        $saveParam = array_merge($saveParam, [
            'token' => $token,
            'config_class' => get_class($config),
            'config' => json_encode($config->__serialize()),
            'type' => $type,
            'service_name' => $config->serviceName,
            'progress' => json_encode($progressRecord?->progress), // 进度信息
            'sheet_progress' => json_encode($progressRecord?->sheetListProgress), // 页码进度信息
            'status' => $progressRecord?->progress->status ?: ProgressData::PROGRESS_STATUS_AWAIT,// 状态
            'data' => json_encode($progressRecord?->data ?: []),
        ]);
        if ($type == static::TYPE_EXPORT) {
            $saveParam['url'] = $progressRecord?->data?->response ?? "";
        } else {
            $saveParam['url'] = $config->getPath();
        }
        return $this->model::query()->upsert([$saveParam], ['token']);
    }


    /**
     * 获取进度
     *
     * @param string $token
     * @return ProgressRecord|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getProgressByToken(string $token): ?ProgressRecord
    {
        return $this->progress->getRecordByToken($token);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

}