# businessg/hyperf-excel

Hyperf 框架的 Excel 同步/异步导入导出组件，基于 [businessg/base-excel](https://github.com/businessg/base-excel) 核心库。

支持：同步/异步导出、同步/异步导入、进度追踪、消息推送、数据库日志、临时文件自动清理、HTTP 接口自动注册、CLI 命令。

---

## 目录

- [环境要求](#环境要求)
- [安装](#安装)
- [发布配置](#发布配置)
- [数据库建表](#数据库建表)
- [配置说明](#配置说明)
  - [excel.php — 组件核心配置](#excelphp--组件核心配置)
  - [excel_business.php — 业务导入导出配置](#excel_businessphp--业务导入导出配置)
- [快速开始（零代码模式）](#快速开始零代码模式)
- [HTTP 接口说明](#http-接口说明)
- [CLI 命令](#cli-命令)
- [自定义导出配置](#自定义导出配置)
- [自定义导入配置](#自定义导入配置)
- [异步队列配置](#异步队列配置)
- [自定义响应格式](#自定义响应格式)
- [异常处理](#异常处理)
- [内置 Demo 配置说明](#内置-demo-配置说明)

---

## 环境要求

| 依赖 | 版本 |
|---|---|
| PHP | >= 8.1 |
| Hyperf | ~3.1 |
| ext-xlswriter | * (pecl install xlswriter) |
| ext-redis | * |
| Swoole / Swow | Hyperf 运行时环境 |
| Redis | 服务运行中 |

## 安装

```bash
composer require businessg/hyperf-excel
```

> 包已配置 Hyperf ConfigProvider 自动发现，无需手动注册。

## 发布配置

```bash
php bin/hyperf.php vendor:publish businessg/hyperf-excel
```

将在 `config/autoload/` 目录生成两个文件：

| 文件 | 用途 |
|---|---|
| `config/autoload/excel.php` | 组件核心配置（驱动、队列、进度、日志、HTTP 等） |
| `config/autoload/excel_business.php` | 业务导入导出配置（注册各业务的 ExportConfig / ImportConfig） |

## 数据库建表

启用 `dbLog` 时，需手动执行建表 SQL。SQL 文件位于组件包内 `src/migrations/excel_log.sql`：

```sql
CREATE TABLE `excel_log` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `token` varchar(64) NOT NULL DEFAULT '',
    `type` enum('export','import') NOT NULL DEFAULT 'export',
    `config_class` varchar(250) NOT NULL DEFAULT '',
    `config` json DEFAULT NULL,
    `service_name` varchar(20) NOT NULL DEFAULT '',
    `sheet_progress` json DEFAULT NULL,
    `progress` json DEFAULT NULL,
    `status` tinyint unsigned NOT NULL DEFAULT '1',
    `data` json NOT NULL,
    `remark` varchar(500) NOT NULL DEFAULT '',
    `url` varchar(300) NOT NULL DEFAULT '',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_token` (`token`)
) ENGINE=InnoDB COMMENT='导入导出日志';
```

如不需要数据库日志，将 `dbLog.enabled` 设为 `false` 即可跳过。

---

## 配置说明

### excel.php — 组件核心配置

```php
return [
    // 默认驱动（目前仅 xlswriter）
    'default' => 'xlswriter',

    // 驱动配置
    'drivers' => [
        'xlswriter' => [
            'class' => \BusinessG\BaseExcel\Driver\XlsWriterDriver::class,
            'disk' => 'local',         // 对应 config/autoload/file.php 中 storage 的 key
            'exportDir' => 'export',   // 导出文件存放目录（相对于 disk 根路径）
            'tempDir' => null,         // 临时文件目录，null 则使用 sys_get_temp_dir()
        ],
    ],

    // 日志通道，对应 config/autoload/logger.php 中的 channel name
    'logging' => [
        'channel' => 'hyperf-excel',
    ],

    // 异步队列（isAsync=true 时使用）
    'queue' => [
        'connection' => 'default',     // 对应 config/autoload/async_queue.php 中的 key
        'channel' => 'default',
    ],

    // Redis 进度追踪
    'progress' => [
        'enabled' => true,
        'prefix' => 'HyperfExcel',     // Redis key 前缀
        'ttl' => 3600,                 // 进度数据过期时间（秒）
        'connection' => 'default',     // 对应 config/autoload/redis.php 中的 pool name
    ],

    // 数据库日志
    'dbLog' => [
        'enabled' => true,
        'model' => \BusinessG\HyperfExcel\Db\Model\ExcelLog::class,
    ],

    // 临时文件自动清理（CleanFileProcess 进程）
    'cleanup' => [
        'enabled' => true,
        'maxAge' => 1800,              // 文件最大存活时间（秒）
        'interval' => 3600,            // 清理间隔（秒）
    ],

    // HTTP 配置（路由自动注册 + 响应格式 + 域名）
    'http' => [
        'enabled' => false,            // ← 设为 true 启用自动路由注册
        'prefix' => '',                // 路由前缀，如 '/api' 则接口为 /api/excel/export
        'middleware' => [],             // 中间件（完整类名数组）
        'domain' => \Hyperf\Support\env('APP_URL', 'http://localhost:9501'),
        'codeField' => 'code',         // 响应 JSON 状态码字段名
        'dataField' => 'data',         // 响应 JSON 数据字段名
        'messageField' => 'message',   // 响应 JSON 消息字段名
        'successCode' => 0,            // 成功时状态码的值
    ],
];
```

> **注意**：Hyperf 中需使用 `\Hyperf\Support\env()` 代替全局 `env()` 函数。

### excel_business.php — 业务导入导出配置

```php
return [
    'export' => [
        // key 为 business_id
        'myExport' => [
            'config' => \App\Excel\MyExportConfig::class,
        ],
    ],
    'import' => [
        'myImport' => [
            'config' => \App\Excel\MyImportConfig::class,
            'info' => [
                // 方式一：动态模板（配置导出 business_id，info 接口自动拼接 URL）
                'templateBusinessId' => 'myImportTemplate',
                // 方式二：静态模板（直接配置完整 URL）
                // 'templateUrl' => 'https://cdn.example.com/templates/order.xlsx',
            ],
        ],
    ],
];
```

---

## 快速开始（零代码模式）

组件内置了完整的 Demo 配置，只需两步即可验证功能：

### 第 1 步：启用 HTTP 路由

编辑 `config/autoload/excel.php`，将 `http.enabled` 设为 `true`：

```php
'http' => [
    'enabled' => true,
    'prefix' => '',
    'middleware' => [],
    'domain' => \Hyperf\Support\env('APP_URL', 'http://localhost:9501'),
    // ...
],
```

### 第 2 步：配置异常处理器顺序

编辑 `config/autoload/exceptions.php`，确保 `ExcelExceptionHandler` 在通用处理器之前：

```php
return [
    'handler' => [
        'http' => [
            BusinessG\HyperfExcel\Exception\Handler\ExcelExceptionHandler::class,
            // ... 其他处理器
            App\Exception\Handler\AppExceptionHandler::class,
        ],
    ],
];
```

> **重要**：Hyperf 异常处理器按顺序匹配，`ExcelExceptionHandler` 必须放在通用处理器前面，否则 `ExcelException` 会被通用处理器捕获。

### 第 3 步：启动服务并测试

```bash
php bin/hyperf.php start
```

**同步导出（返回文件路径）：**

```bash
curl -X POST http://localhost:9501/excel/export \
  -H "Content-Type: application/json" \
  -d '{"business_id": "demoExport"}'
```

**同步导出（浏览器直接下载）：**

```
GET http://localhost:9501/excel/export?business_id=demoExportOut
```

**异步导出（大数据量）：**

```bash
# 1. 创建导出任务
curl -X POST http://localhost:9501/excel/export \
  -H "Content-Type: application/json" \
  -d '{"business_id": "demoAsyncExport"}'
# 返回 {"code":0,"data":{"token":"xxx"}}

# 2. 轮询进度
curl "http://localhost:9501/excel/progress?token=xxx"

# 3. 轮询消息
curl "http://localhost:9501/excel/message?token=xxx"
```

**导入流程：**

```bash
# 1. 获取导入信息（含模板下载地址）
curl "http://localhost:9501/excel/info?business_id=demoImport"

# 2. 上传文件
curl -X POST http://localhost:9501/excel/upload \
  -F "file=@test.xlsx"
# 返回 {"code":0,"data":{"path":"/full/path/to/file.xlsx","url":"/full/path/to/file.xlsx"}}

# 3. 执行导入
curl -X POST http://localhost:9501/excel/import \
  -H "Content-Type: application/json" \
  -d '{"business_id": "demoImport", "url": "/full/path/to/file.xlsx"}'
```

---

## HTTP 接口说明

启用 `http.enabled = true` 后，组件通过 `RegisterRouteListener` 在 `BootApplication` 事件中自动注册以下路由：

| 方法 | 路径 | 参数 | 说明 |
|---|---|---|---|
| GET/POST | `{prefix}/excel/export` | `business_id`, `param`(可选) | 触发导出。同步+OUT 模式直接返回文件流；其他返回 token |
| POST | `{prefix}/excel/import` | `business_id`, `url` | 触发导入，返回 token |
| GET | `{prefix}/excel/progress` | `token` | 查询进度 |
| GET | `{prefix}/excel/message` | `token` | 获取消息（消费式，取后即删） |
| GET | `{prefix}/excel/info` | `business_id` | 获取导入业务附加信息（如模板地址） |
| POST | `{prefix}/excel/upload` | `file` (multipart) | 上传 Excel 文件，返回本地路径 |

### 响应格式

所有接口统一返回 JSON，字段名由 `http` 配置决定。默认格式：

```json
// 成功
{"code": 0, "data": {...}, "message": ""}

// 失败
{"code": 500, "data": null, "message": "错误信息"}
```

---

## CLI 命令

### 导出

```bash
# 参数为 ExportConfig 类的完整类名
php bin/hyperf.php excel:export "BusinessG\BaseExcel\Demo\DemoExportConfig"

# 不显示进度条
php bin/hyperf.php excel:export "BusinessG\BaseExcel\Demo\DemoExportConfig" --no-progress
```

### 导入

```bash
# 参数：ImportConfig 类名 + 文件路径
php bin/hyperf.php excel:import "BusinessG\BaseExcel\Demo\DemoImportConfig" "/path/to/file.xlsx"
```

### 查询进度

```bash
php bin/hyperf.php excel:progress {token}
```

### 查询消息

```bash
php bin/hyperf.php excel:message {token}
```

---

## 自定义导出配置

创建一个类继承 `ExportConfig`：

```php
<?php

namespace App\Excel;

use BusinessG\BaseExcel\Data\Export\Column;
use BusinessG\BaseExcel\Data\Export\ExportCallbackParam;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Data\Export\Sheet;

class OrderExportConfig extends ExportConfig
{
    public string $serviceName = '订单导出';

    // 同步/异步：true 则推送到队列异步执行
    public bool $isAsync = false;

    // 输出方式：
    //   OUT_PUT_TYPE_OUT    — 直接输出文件流（浏览器下载）
    //   OUT_PUT_TYPE_UPLOAD — 上传到 filesystem，返回文件路径
    public string $outPutType = self::OUT_PUT_TYPE_UPLOAD;

    public function getSheets(): array
    {
        $this->setSheets([
            new Sheet([
                'name' => '订单列表',
                'columns' => [
                    new Column(['title' => '订单号', 'field' => 'order_no']),
                    new Column(['title' => '金额',   'field' => 'amount']),
                    new Column(['title' => '状态',   'field' => 'status']),
                ],
                'count' => $this->getDataCount(),
                'data' => [$this, 'getData'],
                'pageSize' => 1000,
            ]),
        ]);
        return $this->sheets;
    }

    public function getDataCount(): int
    {
        return Order::count();
    }

    public function getData(ExportCallbackParam $param): array
    {
        return Order::query()
            ->offset(($param->page - 1) * $param->pageSize)
            ->limit($param->pageSize)
            ->get(['order_no', 'amount', 'status'])
            ->toArray();
    }
}
```

然后在 `config/autoload/excel_business.php` 注册：

```php
'export' => [
    'orderExport' => [
        'config' => \App\Excel\OrderExportConfig::class,
    ],
],
```

调用：`POST /excel/export  {"business_id": "orderExport"}`

---

## 自定义导入配置

创建一个类继承 `ImportConfig`：

```php
<?php

namespace App\Excel;

use BusinessG\BaseExcel\Data\Import\Column;
use BusinessG\BaseExcel\Data\Import\ImportConfig;
use BusinessG\BaseExcel\Data\Import\ImportRowCallbackParam;
use BusinessG\BaseExcel\Data\Import\Sheet;
use BusinessG\BaseExcel\Exception\ExcelException;

class OrderImportConfig extends ImportConfig
{
    public string $serviceName = '订单导入';
    public bool $isAsync = false;

    public function getSheets(): array
    {
        $this->setSheets([
            new Sheet([
                'name' => 'sheet1',
                'headerIndex' => 1,    // 第几行为表头（1 = 第一行）
                'columns' => [
                    new Column(['title' => '订单号', 'field' => 'order_no']),
                    new Column(['title' => '金额',   'field' => 'amount']),
                ],
                'callback' => [$this, 'rowCallback'],
            ]),
        ]);
        return $this->sheets;
    }

    public function rowCallback(ImportRowCallbackParam $param): void
    {
        if (empty($param->row)) {
            return;
        }

        $orderNo = $param->row['order_no'] ?? '';
        if (empty($orderNo)) {
            throw new ExcelException('第' . ($param->rowIndex + 2) . '行: 订单号不能为空');
        }

        Order::create([
            'order_no' => $orderNo,
            'amount' => $param->row['amount'] ?? 0,
        ]);
    }
}
```

注册并调用：

```php
// config/autoload/excel_business.php
'import' => [
    'orderImport' => [
        'config' => \App\Excel\OrderImportConfig::class,
        'info' => [
            'templateBusinessId' => 'orderImportTemplate',
        ],
    ],
],
```

---

## 异步队列配置

Hyperf 使用 `hyperf/async-queue` 组件处理异步任务。

1. 确保 `config/autoload/async_queue.php` 已正确配置：

```php
return [
    'default' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'redis' => ['pool' => 'default'],
        'channel' => 'queue',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 60,
        'processes' => 1,
    ],
];
```

2. 在 `config/autoload/excel.php` 中设置：

```php
'queue' => [
    'connection' => 'default',
    'channel' => 'default',
],
```

3. Hyperf 启动时会自动启动 AsyncQueue 进程（确保 `config/autoload/processes.php` 包含 AsyncQueue 的 ConsumerProcess）

4. 在 ExportConfig / ImportConfig 中设置 `public bool $isAsync = true;`

---

## 自定义响应格式

如果项目的 API 响应格式不是默认的 `code/data/message`，可通过 `http` 配置自定义：

```php
// config/autoload/excel.php
'http' => [
    // ...
    'codeField' => 'status',
    'dataField' => 'result',
    'messageField' => 'msg',
    'successCode' => 200,
],
```

则响应变为：

```json
{"status": 200, "result": {...}, "msg": ""}
```

---

## 异常处理

组件通过 `ConfigProvider` 注册了 `ExcelExceptionHandler`，捕获 `ExcelException` 并返回 JSON 错误响应。

**关键**：需确保 `config/autoload/exceptions.php` 中 `ExcelExceptionHandler` 在通用处理器之前：

```php
return [
    'handler' => [
        'http' => [
            \BusinessG\HyperfExcel\Exception\Handler\ExcelExceptionHandler::class,
            // 其他处理器...
            \App\Exception\Handler\AppExceptionHandler::class,
        ],
    ],
];
```

如需完全自定义异常处理，可移除上述配置并在自己的 ExceptionHandler 中处理 `BusinessG\BaseExcel\Exception\ExcelException`。

---

## 内置 Demo 配置说明

| business_id | 类 | 同步/异步 | 输出方式 | 说明 |
|---|---|---|---|---|
| `demoExport` | DemoExportConfig | 同步 | UPLOAD | 100 条数据，返回文件路径 |
| `demoExportOut` | DemoExportOutConfig | 同步 | OUT | 20 条数据，浏览器直接下载 |
| `demoAsyncExport` | DemoAsyncExportConfig | 异步 | UPLOAD | 5 万条数据，带进度消息 |
| `demoExportForImport` | DemoExportForImportConfig | 同步 | UPLOAD | 5 条测试数据，供导入测试 |
| `demoImportTemplate` | DemoImportTemplateExportConfig | 同步 | OUT | 带样式的导入模板 |
| `demoImport` | DemoImportConfig | 同步 | — | 逐行校验 + 消息推送 |

---

## License

MIT
