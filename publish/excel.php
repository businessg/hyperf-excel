<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 默认驱动
    |--------------------------------------------------------------------------
    |
    | 指定 Excel 处理使用的默认驱动，对应下方 drivers 数组中的 key。
    | 目前支持: xlswriter (需安装 ext-xlswriter 扩展)
    |
    */
    'default' => 'xlswriter',

    /*
    |--------------------------------------------------------------------------
    | 驱动配置
    |--------------------------------------------------------------------------
    |
    | 每个驱动的具体配置项：
    |  - class:     驱动类名
    |  - disk:      文件系统磁盘名，对应 config/autoload/file.php 中 storage 的 key
    |  - exportDir: 导出文件存放目录（相对于 disk 根路径）
    |  - tempDir:   临时文件目录，null 则使用系统临时目录 sys_get_temp_dir()
    |
    */
    'drivers' => [
        'xlswriter' => [
            'class' => \BusinessG\BaseExcel\Driver\XlsWriterDriver::class,
            'disk' => 'local',
            'exportDir' => 'export',
            'tempDir' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    |
    | channel: 日志通道名称，对应 config/autoload/logger.php 中的 channel name
    |
    */
    'logging' => [
        'channel' => 'hyperf-excel',
    ],

    /*
    |--------------------------------------------------------------------------
    | 异步队列配置
    |--------------------------------------------------------------------------
    |
    | 当导入/导出配置为异步 (isAsync=true) 时，任务将推送到队列执行。
    |  - connection: 队列连接名，对应 config/autoload/async_queue.php 中的 key
    |  - channel:    队列通道名（Hyperf AsyncQueue 暂不区分 channel，设为 'default' 即可）
    |
    */
    'queue' => [
        'connection' => 'default',
        'channel' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | 进度追踪配置
    |--------------------------------------------------------------------------
    |
    | 基于 Redis 的实时进度追踪，前端可通过 token 轮询获取进度。
    |  - enabled:    是否启用进度追踪
    |  - prefix:     Redis key 前缀，最终 key 格式: {prefix}:{token}
    |  - ttl:        进度数据过期时间（秒）
    |  - connection: Redis 连接池名称，对应 config/autoload/redis.php 中的 pool name
    |
    */
    'progress' => [
        'enabled' => true,
        'prefix' => 'HyperfExcel',
        'ttl' => 3600,
        'connection' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | 数据库日志配置
    |--------------------------------------------------------------------------
    |
    | 将每次导入/导出操作记录到数据库，便于追溯和统计。
    |  - enabled: 是否启用数据库日志
    |  - model:   Hyperf Model 类名，用于日志持久化
    |             也可通过容器绑定 ExcelLogRepositoryInterface 自定义实现
    |
    */
    'dbLog' => [
        'enabled' => true,
        'model' => \BusinessG\HyperfExcel\Db\Model\ExcelLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 临时文件清理配置
    |--------------------------------------------------------------------------
    |
    | 导入/导出过程中产生的临时文件自动清理策略。
    | 通过 CleanFileProcess 进程定时执行清理。
    |  - enabled:  是否启用自动清理
    |  - maxAge:   文件最大存活时间（秒），超时未修改的文件将被删除
    |  - interval: 清理任务执行间隔（秒）
    |
    */
    'cleanup' => [
        'enabled' => true,
        'maxAge' => 1800,
        'interval' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP 配置（路由注册 + 响应格式 + 项目域名）
    |--------------------------------------------------------------------------
    |
    | 路由注册:
    |  - enabled:      是否自动注册 Excel HTTP 接口，开启后无需手写 Controller 和路由
    |                   最终路径为 {prefix}/excel/export, {prefix}/excel/import 等，/excel/* 部分固定
    |  - prefix:       路由前缀，如 '/api' 则接口为 /api/excel/export
    |  - middleware:    应用到整个路由组的中间件，支持字符串别名和完整类名
    |                   如 [\App\Middleware\AuthMiddleware::class]
    |
    | 项目域名:
    |  - domain:       项目域名（含协议），用于 info 接口拼接 templateUrl 完整地址
    |                   当 excel_business.php 中 templateUrl 为相对路径（如 /excel/export?...）时
    |                   info 接口会自动拼接此域名返回完整 URL
    |                   如果 templateUrl 已是完整地址（http/https 开头），则不拼接
    |
    | 响应格式（response 下）:
    |  - codeField:    响应 JSON 中状态码的字段名，默认 'code'
    |  - dataField:    响应 JSON 中数据的字段名，默认 'data'
    |  - messageField: 响应 JSON 中消息的字段名，默认 'message'
    |  - successCode:  成功时状态码的值，默认 0
    |
    | 上传配置（upload 接口保存路径）:
    |  - upload.disk: 文件系统磁盘名，对应 config/autoload/file.php 中 storage 的 key
    |  - upload.dir:  相对路径，导入文件存放目录（相对于 disk 根路径）
    |
    |  示例: 如果项目约定响应格式为 {"status": 200, "result": {...}, "msg": "ok"}
    |        则配置 response.codeField => 'status', response.dataField => 'result',
    |        response.messageField => 'msg', response.successCode => 200
    |
    */
    'http' => [
        'enabled' => false,
        'prefix' => '',
        'middleware' => [],
        'domain' => \Hyperf\Support\env('APP_URL', 'http://localhost:9501'),
        'response' => [
            'codeField' => 'code',
            'dataField' => 'data',
            'messageField' => 'message',
            'successCode' => 0,
        ],
        'upload' => [
            'disk' => 'local',
            'dir' => 'excel-import',
        ],
    ],
];
