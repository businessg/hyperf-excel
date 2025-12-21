<?php

declare(strict_types=1);

return [
    'default' => 'xlswriter',
    'drivers' => [
        'xlswriter' => [
            'driver' => \Vartruexuan\HyperfExcel\Driver\XlsWriterDriver::class,
            // 固定内存模式配置
            'const_memory' => [
                'enable' => false, // 是否启用固定内存模式（默认关闭）
                'enable_zip64' => true, // 是否启用 ZIP64（默认开启，WPS 需要关闭）
            ],
        ]
    ],
    'options' => [
        // filesystem 配置
        'filesystem' => [
            'storage' => 'local', // 默认本地
        ],
        // 导出配置
        'export' => [
            'rootDir' => 'export', // 导出根目录
        ],
    ],
    // 日志
    'logger' => [
        'name' => 'hyperf-excel',
    ],
    // queue配置
    'queue' => [
        'name' => 'default',
    ],
    // 进度处理
    'progress' => [
        'enable' => true,
        'prefix' => 'HyperfExcel',
        'expire' => 3600, // 数据失效时间
    ],
    // db日志
    'dbLog' => [
        'enable' => true,
        'model' => \Vartruexuan\HyperfExcel\Db\Model\ExcelLog::class,
    ],
    // 清除临时文件
    'cleanTempFile' => [
        'enable' => true, // 是否允许
        'time' => 1800, // 文件未操作时间(秒)
        'interval' => 3600,// 间隔检查时间
    ],
];
