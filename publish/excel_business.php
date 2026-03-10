<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 导出业务配置
    |--------------------------------------------------------------------------
    |
    | key 为 business_id，请求导出时传入此 ID 即可触发对应配置。
    | 'config' => ExportConfig 子类的完整类名
    |
    | 内置 Demo 覆盖以下场景（可按需删除或作为参考）:
    |   demoExport          — 同步 + 上传(UPLOAD)，100条数据，返回文件路径
    |   demoExportOut       — 同步 + 直接输出(OUT)，浏览器访问即下载
    |   demoAsyncExport     — 异步 + 上传(UPLOAD)，5万条大数据量，支持进度查询
    |   demoExportForImport — 同步 + 上传(UPLOAD)，5条测试数据，用于导入测试
    |   demoImportTemplate  — 同步 + 直接输出(OUT)，带样式的导入模板（仅表头无数据）
    |
    */
    'export' => [
        'demoExport' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoExportConfig::class,
        ],
        'demoExportOut' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoExportOutConfig::class,
        ],
        'demoAsyncExport' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoAsyncExportConfig::class,
        ],
        'demoExportForImport' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoExportForImportConfig::class,
        ],
        'demoImportTemplate' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoImportTemplateExportConfig::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 导入业务配置
    |--------------------------------------------------------------------------
    |
    | key 为 business_id，请求导入时传入此 ID 即可触发对应配置。
    | 'config' => ImportConfig 子类的完整类名
    | 'info'   => 可选，附加信息，通过 GET {prefix}/excel/info?business_id=xxx 获取
    |
    | info 中模板下载地址支持两种配置方式（二选一）:
    |
    |   方式一: templateBusinessId — 配置导出业务 ID，info 接口自动拼接完整 URL
    |     'templateBusinessId' => 'demoImportTemplate',
    |     → 返回: {domain}/{prefix}/excel/export?business_id=demoImportTemplate
    |     （对应的导出配置需 isAsync=false + OUT_PUT_TYPE_OUT，浏览器访问即下载模板）
    |
    |   方式二: templateUrl — 配置绝对 URL，直接返回
    |     'templateUrl' => 'https://cdn.example.com/templates/order.xlsx',
    |
    | 内置 Demo:
    |   demoImport — 同步导入，逐行校验 + 回调
    |
    */
    'import' => [
        'demoImport' => [
            'config' => \BusinessG\BaseExcel\Demo\DemoImportConfig::class,
            'info' => [
                'templateBusinessId' => 'demoImportTemplate',
            ],
        ],
    ],
];
