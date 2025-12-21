# hyperf-excel

[![php](https://img.shields.io/badge/php-%3E=8.1-brightgreen.svg?maxAge=2592000)](https://github.com/php/php-src)
[![Latest Stable Version](https://img.shields.io/packagist/v/vartruexuan/hyperf-excel)](https://packagist.org/packages/vartruexuan/hyperf-excel)
[![License](https://img.shields.io/packagist/l/vartruexuan/hyperf-excel)](https://github.com/vartruexuan/hyperf-excel)

## 📌 概述

Excel 同步/异步智能配置导入导出组件，为 Hyperf 框架提供强大的 Excel 处理能力。

## 📑 目录

- [组件能力](#-组件能力)
- [安装](#-安装)
- [使用指南](#-使用指南)
- [配置类配置](#️-配置类配置)
  - [ExportConfig 导出配置](#exportconfig-导出配置)
  - [单元格类型](#单元格类型)
  - [ImportConfig 导入配置](#importconfig-导入配置)
- [组件配置](#组件配置)
  - [驱动配置](#驱动配置)
- [命令行](#命令行)
- [DI 配置](#di)
- [监听器](#监听器)

## ✨ 组件能力


- ✅ **异步处理** - 支持异步导入导出
- 🧩 **复杂表头** - 支持`无限极`、`跨行`、`跨列`的复杂表头设计
- 🎨 **样式定制** - 可配置`页码样式`、`表头样式`、`列样式`
- 📊 **进度追踪** - 实时获取处理进度信息
- 💬 **消息系统** - 支持构建查询消息
- 📄 **格式支持** - 支持 `xlsx` 格式
- ⚙️ **驱动支持** - 基于 `xlswriter` 和 `PhpSpreadsheet` 驱动
- 🔤 **单元格类型** - 支持文本、链接、公式、日期、图片等多种单元格类型

## 🚀 安装

### 前置准备

#### 驱动选择

组件支持两种驱动，可根据需求选择：

1. **xlswriter 驱动**（推荐，高性能）
   - 需要安装 [xlswriter](https://xlswriter-docs.viest.me/zh-cn/an-zhuang) 扩展
   ```bash
   pecl install xlswriter
   ```
   - 性能优异，适合大数据量导出
   - 支持文本、链接、公式、日期、图片类型

2. **PhpSpreadsheet 驱动**（功能丰富）
   - 需要安装 `phpoffice/phpspreadsheet` 包
   ```bash
   composer require phpoffice/phpspreadsheet
   ```
   - 功能更丰富，支持更多 Excel 特性
   - 支持文本、链接、公式、日期、图片类型

#### 依赖组件包

以下组件需要在项目中安装并构建配置：
- [hyperf/filesystem](https://hyperf.wiki/3.1/#/zh-cn/filesystem?id=%e5%ae%89%e8%a3%85)
- [hyperf/async-queue](https://hyperf.wiki/3.1/#/zh-cn/async-queue?id=%e5%bc%82%e6%ad%a5%e9%98%9f%e5%88%97)
- [hyperf/logger](https://hyperf.wiki/3.1/#/zh-cn/logger?id=%e6%97%a5%e5%bf%97)
- [hyperf/redis](https://hyperf.wiki/3.1/#/zh-cn/redis?id=redis)
### 安装组件

```shell
composer require vartruexuan/hyperf-excel
```

### 构建配置

```shell
php bin/hyperf.php vendor:publish vartruexuan/hyperf-excel
```

## 🛠 使用指南

- excel对象

```php
$excel = ApplicationContext::getContainer()->get(\Vartruexuan\HyperfExcel\ExcelInterface::class);
```

- 导出

```php
/**
 * @var \Vartruexuan\HyperfExcel\ExcelInterface $excel 
 */
$exportData = $excel->export(new DemoExportConfig([
    // 额外参数
    'params'=> $request->all(),
]));
```

- 导入

```php
/**
 * @var \Vartruexuan\HyperfExcel\ExcelInterface $excel 
 * @
 */
$exportData = $excel->import(new DemoImportConfig()->setPath('/d/xxx.xlsx'));
```

- 获取进度

```php
/**
 * @var \Vartruexuan\HyperfExcel\ExcelInterface $excel 
 * @var \Vartruexuan\HyperfExcel\Progress\ProgressRecord $progressRecord
 */
$progressRecord = $excel->getProgressRecord($token);

// $progressRecord->status - 状态值：
//   1.待处理 2.正在处理 3.处理完成 4.处理失败 5.输出中 6.完成
// $progressRecord->progress - 总进度信息（包含 total, progress, success, fail 等）
// $progressRecord->sheetProgress - 页码进度信息（数组）
```

- 获取输出消息

```php
/**
 * @var \Vartruexuan\HyperfExcel\ExcelInterface $excel 
 * @var \Vartruexuan\HyperfExcel\Progress\ProgressRecord $progressRecord
 */
$isEnd = false; // 是否结束
$progressRecord = $excel->popMessageAndIsEnd($token, 50, $isEnd);
```

## ⚙️ 配置类配置

### ExportConfig 导出配置

`ExportConfig` 是导出配置的基类，继承它来创建自定义的导出配置。

#### 主要属性说明

- `$serviceName` - 服务名称，用于标识不同的导出服务
- `$driverName` - 驱动名称（可选），可指定 `'xlswriter'` 或 `'spreadsheet'`，未指定则使用全局配置的默认驱动
- `$isAsync` - 是否异步处理，`true` 为异步，`false` 为同步
- `$outPutType` - 输出类型：
  - `OUT_PUT_TYPE_UPLOAD` - 导出并上传到文件系统
  - `OUT_PUT_TYPE_OUT` - 直接同步输出到浏览器
- `$params` - 额外参数数组，可在数据回调中使用
- `$sheets` - 工作表配置数组

#### 完整配置示例

```php
<?php

namespace App\Excel\Export;

use Vartruexuan\HyperfExcel\Data\Export\ExportConfig;

use Vartruexuan\HyperfExcel\Data\Export\Column;
use Vartruexuan\HyperfExcel\Data\Export\ExportCallbackParam;
use Vartruexuan\HyperfExcel\Data\Export\Sheet;
use Vartruexuan\HyperfExcel\Data\Export\SheetStyle;

class DemoExportConfig extends ExportConfig
{
    public string $serviceName = 'demo';

    // 驱动名称（可选，未指定则使用配置中的默认驱动）
    // 'xlswriter' - 使用 xlswriter 驱动（高性能，推荐）
    // 'spreadsheet' - 使用 PhpSpreadsheet 驱动（功能丰富）
    public string $driverName = ''; // 空字符串表示使用默认驱动

    // 是否异步
    public bool $isAsync = true;

    // 输出类型  
    // OUT_PUT_TYPE_UPLOAD  导出并上传
    // OUT_PUT_TYPE_OUT     直接同步输出
    public string $outPutType = self::OUT_PUT_TYPE_UPLOAD;

    // 页码配置
    public function getSheets(): array
    {
        $this->setSheets([
            new Sheet([
                'name' => 'sheet1',
                'columns' => [
                    new Column([
                        'title' => '用户名',
                        'field' => 'username',
                        // 子列
                        'children' => []
                    ]),
                    new Column([
                        'title' => '姓名',
                        'field' => 'name',
                    ]),
                    new Column([
                        'title' => '年龄',
                        'field' => 'age',
                    ]),
                    // ...
                ],
                'count' => $this->getDataCount(), // 数据数量
                'data' => [$this, 'getData'], // 数据
                'pageSize' => 500, // 每页导出数量<分批导出>
                'style'=> new SheetStyle(), // 页码样式
            ])
        ]);
        return $this->sheets;
    }

    /**
     * 获取数据数量
     *
     * @return int
     */
    public function getDataCount(): int
    {
        // 测试数据 <实际业务可能是查询数据库>
        return 1000;
    }

    /**
     * 获取数据
     *
     * @param ExportCallbackParam $exportCallbackParam
     * @return array
     */
    public function getData(ExportCallbackParam $exportCallbackParam): array
    {
      // $exportCallbackParam->page; // 当前页码
      // $exportCallbackParam->pageSize;// 页码数量
      
      msleep(500);
      var_dump($this->params);
      // 测试数据 <实际业务可能是查询数据库>
      for ($i = 0; $i < $exportCallbackParam->pageSize; $i++) {
          $d[] = [
              'username' => '哈哈',
              'name' => '测试'
              'age' => 11,
          ];
      }
      
      // 输出信息
      $progress= ApplicationContext::getContainer()->get(ProgressInterface::class);
      $progress->pushMessage($this->token,"页码:".$exportCallbackParam->page .",数量：". $exportCallbackParam->pageSize);
      return $d ?? [];
    }
}

```

#### Sheet 工作表配置

```php
 new Sheet([
       // 页码名
      'name' => 'sheet1',
      // 列配置
      'columns' => [ 
         new \Vartruexuan\HyperfExcel\Data\Export\Column([]),
      ],
      // 数据数量
      'count' => 0, 
      // 数据(array|callback)
      'data' => function(\Vartruexuan\HyperfExcel\Data\Export\ExportCallbackParam $callbackParam){
            return [];
      }, 
      // 分批导出数
      'pageSize' => 1, 
      // 页码样式
      'style'=> new  \Vartruexuan\HyperfExcel\Data\Export\SheetStyle([]);
]),
```

#### Column 列配置

```php
 new Column([
      // 列名
      'title' => "一级列", 
       // 宽度
      //'width' => 32,
      // 高度
      'height' => 58,
      // 单元格类型（字符串或类型对象）
      // 支持的类型：'text'（默认）、'url'、'formula'、'date'、'image'
      // 或使用类型对象：new TextType(), new UrlType(), new DateType(), new ImageType() 等
      'type' => 'text', // 或 Column::TYPE_TEXT
      // header 单元样式
      'headerStyle' => new Style([
          'wrap' => true,
          'fontColor' => 0x2972F4,
          'font' => '等线',
          'align' => [Style::FORMAT_ALIGN_LEFT, Style::FORMAT_ALIGN_VERTICAL_CENTER],
          'fontSize' => 10,
      ]),
      // 子列 <自动跨列>
      'children' => [
          new Column([
              'title' => '二级列1',
              'field' => 'key1', // 数据字段名
              'width' => 32, // 宽度
              // 头部单元格样式
              'headerStyle' => new Style([
                  'align' => [Style::FORMAT_ALIGN_CENTER],
                  'bold' => true,
              ]),
          ]),
          // ...
      ],
]),
```

### 单元格类型

组件支持多种单元格类型，可以通过 `Column` 的 `type` 属性配置。`type` 可以是字符串类型名或类型对象，构造函数会自动转换。

#### 支持的类型

1. **文本类型 (text)** - 默认类型
```php
new Column([
    'title' => '用户名',
    'field' => 'username',
    'type' => 'text', // 或 Column::TYPE_TEXT
    // 或使用类型对象
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\TextType([
        'format' => null, // 格式化字符串（xlswriter 驱动支持）
    ]),
])
```

2. **链接类型 (url)**
```php
new Column([
    'title' => '网站',
    'field' => 'website',
    'type' => 'url', // 或 Column::TYPE_URL
    // 或使用类型对象
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\UrlType([
        'text' => '点击访问', // 链接显示文字（为空则使用 URL 本身）
        'tooltip' => '提示信息', // 链接提示（xlswriter 驱动支持）
    ]),
])
```

3. **公式类型 (formula)**
```php
new Column([
    'title' => '合计',
    'field' => 'total',
    'type' => 'formula', // 或 Column::TYPE_FORMULA
    // 或使用类型对象
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\FormulaType(),
])
// 数据值应为公式字符串，如：'SUM(A1:A10)'
```

4. **日期类型 (date)**
```php
new Column([
    'title' => '创建时间',
    'field' => 'created_at',
    'type' => 'date', // 或 Column::TYPE_DATE
    // 或使用类型对象
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\DateType([
        'dateFormat' => 'yyyy-mm-dd', // 日期格式（SpreadSheet 驱动支持）
    ]),
])
// 数据值可以是时间戳或日期字符串
```

5. **图片类型 (image)**
```php
new Column([
    'title' => '头像',
    'field' => 'avatar',
    'type' => 'image', // 或 Column::TYPE_IMAGE
    // 或使用类型对象
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\ImageType([
        'width' => 100, // 目标宽度（像素）
        'height' => 100, // 目标高度（像素）
        // 或使用缩放比例
        'widthScale' => 0.5, // 宽度缩放比例
        'heightScale' => 0.5, // 高度缩放比例
    ]),
])
// 数据值应为图片路径（本地路径或 HTTP/HTTPS URL）
// 支持自动下载远程图片并缓存
// 如果图片不存在或下载失败，会自动降级为文本显示
// 优先级：宽高 > 缩放比例，如果只设置一个维度，会保持宽高比
```

#### 类型常量

```php
use Vartruexuan\HyperfExcel\Data\Export\Column;

Column::TYPE_TEXT    // 'text'
Column::TYPE_URL     // 'url'
Column::TYPE_FORMULA // 'formula'
Column::TYPE_DATE    // 'date'
Column::TYPE_IMAGE   // 'image'
```

#### 类型对象命名空间

所有类型对象位于 `Vartruexuan\HyperfExcel\Data\Export\Type\` 命名空间下：

- `TextType` - 文本类型
- `UrlType` - 链接类型
- `FormulaType` - 公式类型
- `DateType` - 日期类型
- `ImageType` - 图片类型
- `BaseType` - 类型基类

#### 类型自动转换

`Column` 构造函数会自动处理类型转换：

- 如果 `type` 未设置，默认使用 `TextType`
- 如果 `type` 是字符串（如 `'text'`, `'url'`），会自动转换为对应的类型对象
- 如果 `type` 已经是类型对象，直接使用

因此，以下两种写法是等价的：

```php
// 使用字符串
new Column([
    'title' => '网站',
    'field' => 'website',
    'type' => 'url',
])

// 使用类型对象
new Column([
    'title' => '网站',
    'field' => 'website',
    'type' => new \Vartruexuan\HyperfExcel\Data\Export\Type\UrlType(),
])
```

#### SheetStyle 工作表样式

```php
new  \Vartruexuan\HyperfExcel\Data\Export\SheetStyle([
   // 网格线
   'gridline'=> \Vartruexuan\HyperfExcel\Data\Export\SheetStyle::GRIDLINES_HIDE_ALL,
   // 缩放 (10 <= $scale <= 400)
   'zoom'=> 50,  
   // 隐藏当前页码 
   'hide' => false, 
   // 选中当前页码
   'isFirst' => true,
])
```

#### Style 单元格样式

```php
new Style([
  'wrap' => true,
  'fontColor' => 0x2972F4,
  'font' => '等线',
  'align' => [Style::FORMAT_ALIGN_LEFT, Style::FORMAT_ALIGN_VERTICAL_CENTER],
  'fontSize' => 10,
])
```

### ImportConfig 导入配置

`ImportConfig` 是导入配置的基类，继承它来创建自定义的导入配置。

#### 主要属性说明

- `$serviceName` - 服务名称，用于标识不同的导入服务
- `$driverName` - 驱动名称（可选），可指定 `'xlswriter'` 或 `'spreadsheet'`，未指定则使用全局配置的默认驱动
- `$isAsync` - 是否异步处理，`true` 为异步，`false` 为同步
- `$path` - Excel 文件路径（本地路径或 URL）
- `$sheets` - 工作表配置数组

#### 完整配置示例

```php
<?php

namespace App\Excel\Import;

use Vartruexuan\HyperfExcel\Data\Import\ImportConfig;
use App\Exception\BusinessException;
use Hyperf\Collection\Arr;
use Vartruexuan\HyperfExcel\Data\Import\ImportRowCallbackParam;
use Vartruexuan\HyperfExcel\Data\Import\Sheet;
use Vartruexuan\HyperfExcel\Data\Import\Column;

class DemoImportConfig extends AbstractImportConfig
{
    public string $serviceName = 'demo';

    // 是否异步 <默认 async-queue>
    public bool $isAsync = true;
    
    public function getSheets(): array
    {
        $this->setSheets([
            new Sheet([
                'name' => 'sheet1',
                'headerIndex' => 1, // 列头下标<0则无列头>
                'columns' => [
                      new Column([
                          'title' => '用户名', // excel中列头
                          'field' => 'username', // 映射字段名
                          'type' => Column::TYPE_STRING, // 数据类型(默认 string)
                      ]),
                      new Column([
                          'title' => '年龄',
                          'field' => 'age',
                          'type' => Column::TYPE_INT,
                      ]),
                      new Column([
                          'title' => '身高',
                          'field' => 'height',
                          'type' => Column::TYPE_INT,
                      ]),
                ],
                // 数据回调
                'callback' => [$this, 'rowCallback']
            ])
        ]);
        return parent::getSheets();
    }

    public function rowCallback(ImportRowCallbackParam $importRowCallbackParam)
    {
       // $importRowCallbackParam->row; // 行数据
       // $importRowCallbackParam->rowIndex; // 行下标
       // $importRowCallbackParam->sheet;// 当前页码
        try {
             // 参数校验
             // 业务操作
             var_dump($importRowCallbackParam->row);
        } catch (\Throwable $throwable) {
            // 异常信息将会推入进度消息中 | 自动归为失败数
            throw new BusinessException(ResultCode::FAIL, '第' . $param->rowIndex . '行:' . $throwable->getMessage());
        }
    }
}
```

#### Sheet 工作表配置

```php
new Sheet([
    // 页码名
    'name' => 'sheet1',
    // 列头下标<0则无列头>
    'headerIndex' => 1, 
    // 列配置
    'columns' => [
          new Column([
              'title' => '用户名', // excel中列头
              'field' => 'username', // 映射字段名
              'type' => Column::TYPE_STRING, // 数据类型(默认 string)
          ]),
    ],
    // 数据回调
    'callback' => function(\Vartruexuan\HyperfExcel\Data\Import\ImportRowCallbackParam $callbackParam){}
])

```

#### Column 列配置

```php
new Column([
    // 列头
    'title' => '身高',
    // 映射字段名
    'field' => 'height',
    // 读取类型
    'type' => Column::TYPE_INT,
]),
```

## 组件配置

### 驱动配置

组件支持两种驱动，可在全局配置中设置默认驱动，也可在具体的配置类中指定驱动。

#### 全局驱动配置

在 `config/autoload/excel.php` 中配置：

```php
<?php

declare(strict_types=1);

return [
    // 默认驱动：'xlswriter' 或 'spreadsheet'
    'default' => 'xlswriter',
    'drivers' => [
        // xlswriter 驱动（高性能，需要安装 xlswriter 扩展）
        'xlswriter' => [
            'driver' => \Vartruexuan\HyperfExcel\Driver\XlsWriterDriver::class,
            // 固定内存模式配置（可选）
            'const_memory' => [
                'enable' => false, // 是否启用固定内存模式（默认关闭）
                'enable_zip64' => true, // 是否启用 ZIP64（默认开启，WPS 需要关闭）
            ],
        ],
        // PhpSpreadsheet 驱动（功能丰富，需要安装 phpoffice/phpspreadsheet 包）
        'spreadsheet' => [
            'driver' => \Vartruexuan\HyperfExcel\Driver\SpreadSheetDriver::class,
        ],
    ],
```

#### 配置类中指定驱动

在 `ExportConfig` 或 `ImportConfig` 子类中，可以通过 `driverName` 属性指定使用的驱动：

```php
class DemoExportConfig extends ExportConfig
{
    // 指定使用 xlswriter 驱动
    public string $driverName = 'xlswriter';
    
    // 或指定使用 PhpSpreadsheet 驱动
    // public string $driverName = 'spreadsheet';
    
    // 如果不指定（空字符串），则使用全局配置中的默认驱动
    // public string $driverName = '';
}
```

#### 驱动特性对比

| 特性 | xlswriter 驱动 | PhpSpreadsheet 驱动 |
|------|---------------|-------------------|
| **性能** | ⚡ 高性能，适合大数据量 | 🐌 性能一般 |
| **内存占用** | 💚 低内存占用 | 💛 较高内存占用 |
| **安装要求** | 需要安装 xlswriter 扩展 | 需要安装 phpoffice/phpspreadsheet 包 |
| **单元格类型支持** | ✅ 全部支持 | ✅ 全部支持 |
| **样式支持** | ✅ 基础样式 | ✅ 丰富样式 |
| **图片处理** | ✅ 支持 | ✅ 支持 |
| **公式支持** | ✅ 支持 | ✅ 支持 |
| **日期格式** | ⚠️ 基础支持 | ✅ 完整支持 |
| **链接提示** | ✅ 支持 tooltip | ❌ 不支持 tooltip |
| **推荐场景** | 大数据量导出、生产环境 | 复杂样式需求、开发调试 |

> **提示**：建议在生产环境使用 `xlswriter` 驱动以获得更好的性能，在需要复杂样式或调试时使用 `PhpSpreadsheet` 驱动。

#### xlswriter 固定内存模式

xlswriter 驱动支持固定内存模式，适用于大数据量导出场景。固定内存模式下，最大内存使用量 = 最大一行的数据占用量，可以显著降低内存占用。

**配置方式：**

```php
'xlswriter' => [
    'driver' => \Vartruexuan\HyperfExcel\Driver\XlsWriterDriver::class,
    'const_memory' => [
        'enable' => true, // 启用固定内存模式
        'enable_zip64' => true, // 是否启用 ZIP64（默认开启，WPS 需要关闭）
    ],
],
```

**注意事项：**

1. **内存优势**：固定内存模式下，内存使用量固定，不会随数据量增长而增长
2. **功能限制**：
   - 单元格按行落盘，如果当前操作的行已落盘则无法进行任何修改
   - 只支持简单的单行表头，不支持复杂的合并单元格和多级表头
   - 不支持单元格方式插入数据（非 text 类型），只能使用 data 方式批量写入
3. **WPS 兼容性**：WPS 需要关闭 ZIP64（`enable_zip64 => false`），否则打开文件可能报文件损坏
4. **适用场景**：适合大数据量、简单表头结构的导出场景

**参考文档**：[xlswriter 固定内存模式文档](https://xlswriter-docs.viest.me/zh-cn/nei-cun/gu-ding-nei-cun-mo-shi)

#### 完整配置示例

```php
<?php

declare(strict_types=1);

return [
    // 默认驱动：'xlswriter' 或 'spreadsheet'
    'default' => 'xlswriter',
    'drivers' => [
        // xlswriter 驱动（高性能，需要安装 xlswriter 扩展）
        'xlswriter' => [
            'driver' => \Vartruexuan\HyperfExcel\Driver\XlsWriterDriver::class,
            // 固定内存模式配置（可选）
            'const_memory' => [
                'enable' => false, // 是否启用固定内存模式（默认关闭）
                'enable_zip64' => true, // 是否启用 ZIP64（默认开启，WPS 需要关闭）
            ],
        ],
        // PhpSpreadsheet 驱动（功能丰富，需要安装 phpoffice/phpspreadsheet 包）
        'spreadsheet' => [
            'driver' => \Vartruexuan\HyperfExcel\Driver\SpreadSheetDriver::class,
        ],
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
```

## 📜命令行

- 导出

```bash
php bin/hyperf.php  excel:export "\App\Excel\DemoExportConfig"
```

- 导入

```bash
# 本地文件
php bin/hyperf.php  excel:import "\App\Excel\DemoImportConfig" "/d/xxx.xlsx"
# 远程文件
php bin/hyperf.php  excel:import "\App\Excel\DemoImportConfig" "https://xxx.com/xxx.xlsx"
```

- 进度查询

```bash
php bin/hyperf.php  excel:progress  424ee1bd6db248e09b514231edea5f04
```

- 获取输出消息

```bash
php bin/hyperf.php  excel:message  424ee1bd6db248e09b514231edea5f04
```

## 🔧 DI 配置

### Token 生成策略

默认使用 UUID4 策略，可在 `config/autoload/dependencies.php` 中自定义：

```php
<?php

return [
    // Token 生成策略（默认 uuid4）
    \Vartruexuan\HyperfExcel\Strategy\Token\TokenStrategyInterface::class => 
        \Vartruexuan\HyperfExcel\Strategy\Token\UuidStrategy::class,
];
```

### 导出文件名策略

默认使用日期时间策略，可在 `config/autoload/dependencies.php` 中自定义：

```php
<?php

return [
    // 导出文件名策略（默认日期时间）
    \Vartruexuan\HyperfExcel\Strategy\Path\ExportPathStrategyInterface::class => 
        \Vartruexuan\HyperfExcel\Strategy\Path\DateTimeExportPathStrategy::class,
];
```

### 队列配置

默认使用 async-queue，可在 `config/autoload/dependencies.php` 中自定义：

```php
<?php

return [
    // 队列（默认 async-queue）
    \Vartruexuan\HyperfExcel\Queue\ExcelQueueInterface::class => 
        \Vartruexuan\HyperfExcel\Queue\AsyncQueue\ExcelQueue::class,
];
```

## 监听器

### 日志监听器

```php
// config/autoload/listeners.php
return [
    Vartruexuan\HyperfExcel\Listener\ExcelLogListener::class,
];
```

### db日志监听器

```php
// config/autoload/listeners.php
return [
    Vartruexuan\HyperfExcel\Listener\ExcelLogDbListener::class,
];
```

- 构建数据库表

```bash
php bin/hyperf.php migrate  --path=./vendor/vartruexuan/hyperf-excel/src/migrations
```

或

```sql
#
直接执行sql
CREATE TABLE `excel_log`
(
    `id`             int unsigned NOT NULL AUTO_INCREMENT,
    `token`          varchar(64)  NOT NULL DEFAULT '',
    `type`           enum('export','import') NOT NULL DEFAULT 'export' COMMENT '类型:export导出import导入',
    `config_class`   varchar(250) NOT NULL DEFAULT '',
    `config`         json                  DEFAULT NULL COMMENT 'config信息',
    `service_name`   varchar(20)  NOT NULL DEFAULT '' COMMENT '服务名',
    `sheet_progress` json                  DEFAULT NULL COMMENT '页码进度',
    `progress`       json                  DEFAULT NULL COMMENT '总进度信息',
    `status`         tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态:1.待处理2.正在处理3.处理完成4.处理失败5.输出中6.完成',
    `data`           json         NOT NULL COMMENT '数据信息',
    `remark`         varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
    `url`            varchar(300) NOT NULL DEFAULT '' COMMENT 'url地址',
    `created_at`     timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at`     timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_token` (`token`)
) ENGINE=InnoDB  COMMENT='导入导出日志';

```

### 自定义监听器

- 继承`Vartruexuan\HyperfExcel\Listener\BaseListener`

## License

MIT
