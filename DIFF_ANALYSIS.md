# PHP 8.2 表头写不上 - 代码差异分析

## Issue #4 提供的修复代码与原始代码的区别

### 1. **exportSheet 方法（第150-174行）** ⭐ 关键改动

#### 原始代码：
```php
protected function exportSheet(Excel $excel, ExportSheet $sheet, ExportConfig $config, int $sheetIndex, string $filePath): void
{
    $sheetName = $sheet->getName();
    if ($sheetIndex > 0) {
        $excel->addSheet($sheetName);
    } else {
        $excel->fileName(basename($filePath), $sheetName);
    }
    // ... 其他代码
}
```

#### 修复代码：
```php
protected function exportSheet(Excel $excel, ExportSheet $sheet, ExportConfig $config, int $sheetIndex, string $filePath): void
{
    $sheetName = (string) ($sheet->getName() ?? 'Sheet' . ($sheetIndex + 1));
    
    if ($sheetIndex > 0) {
        $excel->addSheet($sheetName);
    } else {
        $excel->fileName(basename($filePath), $sheetName);
    }
    // ... 其他代码
}
```

**改动原因：** 
- 强制类型转换为 `(string)`，确保 `$sheetName` 必为字符串
- 添加了 null 合并处理 `?? 'Sheet' . ($sheetIndex + 1)`，当 `getName()` 返回 null 时提供默认值
- **PHP 8.2 类型严格性提高，xlswriter 扩展对参数类型要求更严格**

---

### 2. **exportSheet 方法中的列和表头处理（第165-171行）** ⭐ 关键改动

#### 原始代码：
```php
[$columns, $headers, $maxDepth] = Column::processColumns($sheet->getColumns());

$this->exportSheetHeader($excel, $headers, $maxDepth);

$this->exportSheetData(function ($data) use ($excel) {
    $excel->data($data);
}, $sheet, $config, $columns);
```

#### 修复代码：
```php
[$columns, $headers, $maxDepth] = Column::processColumns($sheet->getColumns());

$maxDepth = max(1, (int) $maxDepth);
$headers = is_array($headers) ? $headers : [];

if (!empty($headers)) {
    $this->exportSheetHeader($excel, $headers, $maxDepth);
}

if (!empty($columns)) {
    $this->exportSheetData(function ($data) use ($excel) {
        if (is_array($data) && !empty($data)) {
            $excel->data($data);
        }
    }, $sheet, $config, $columns);
}
```

**改动原因：**
- `$maxDepth = max(1, (int) $maxDepth)` - 确保深度至少为1，并强制转换为整数
- `$headers = is_array($headers) ? $headers : []` - 验证 headers 是数组，否则使用空数组
- 增加 `if (!empty($headers))` 检查 - 只在表头非空时调用导出表头方法
- 增加 `if (!empty($columns))` 检查 - 只在列非空时导出数据
- 在数据回调中增加 `if (is_array($data) && !empty($data))` - 验证数据有效性

**问题根源：PHP 8.2 对空值和类型的处理更严格，原始代码可能在某些情况下传递空或非法类型给 xlswriter 扩展**

---

### 3. **exportSheetHeader 方法（第195-213行）** ⭐ 核心修复

#### 原始代码：
```php
protected function exportSheetHeader(Excel $excel, array $columns, int $maxDepth): void
{
    foreach ($columns as $column) {
        $colStr = Excel::stringFromColumnIndex($column->col);
        $rowIndex = $column->row + 1;
        $endStr = Excel::stringFromColumnIndex($column->col + $column->colSpan - 1);
        $endRowIndex = $rowIndex + $column->rowSpan - 1;
        $range = "{$colStr}{$rowIndex}:{$endStr}{$endRowIndex}";

        $excel->mergeCells($range, $column->title, !empty($column->headerStyle) ? $this->styleToResource($excel, $column->headerStyle) : null);

        if ($column->height > 0) {
            $excel->setRow($range, $column->height);
        }
        $defaultWidth = 5 * mb_strlen($column->title, 'utf-8');
        $excel->setColumn($range, $column->width > 0 ? $column->width : $defaultWidth, !empty($column->style) ? $this->styleToResource($excel, $column->style) : null);
    }
    $excel->setCurrentLine($maxDepth);
}
```

#### 修复代码：
```php
protected function exportSheetHeader(Excel $excel, array $columns, int $maxDepth): void
{
    foreach ($columns as $column) {
        $colStr = Excel::stringFromColumnIndex($column->col);
        $rowIndex = $column->row + 1;
        $endStr = Excel::stringFromColumnIndex($column->col + $column->colSpan - 1);
        $endRowIndex = $rowIndex + $column->rowSpan - 1;
        $range = "{$colStr}{$rowIndex}:{$endStr}{$endRowIndex}";
        
        $title = (string) ($column->title ?? '');
        $headerStyle = !empty($column->headerStyle) ? $this->styleToResource($excel, $column->headerStyle) : null;
        
        if ($headerStyle !== null) {
            $excel->mergeCells($range, $title, $headerStyle);
        } else {
            $excel->mergeCells($range, $title);
        }

        if ($column->height > 0) {
            $excel->setRow($range, $column->height);
        }
        
        $defaultWidth = 5 * mb_strlen($title, 'utf-8');
        $columnStyle = !empty($column->style) ? $this->styleToResource($excel, $column->style) : null;
        
        if ($columnStyle !== null) {
            $excel->setColumn($range, $column->width > 0 ? $column->width : $defaultWidth, $columnStyle);
        } else {
            $excel->setColumn($range, $column->width > 0 ? $column->width : $defaultWidth);
        }
    }
    $excel->setCurrentLine($maxDepth);
}
```

**改动原因（这是表头写不上的根本原因）：**
1. **标题强制转换为字符串：** `$title = (string) ($column->title ?? '')` - 确保标题必为字符串，处理 null 情况
2. **样式预处理：** 提前计算 `$headerStyle` 和 `$columnStyle`
3. **条件判断分离：** 
   - 将 `mergeCells()` 的样式参数从三元运算符改为 `if...else` 条件分支
   - 当样式为 null 时，调用不带样式参数的重载版本
   - 当样式非 null 时，才传递样式参数
4. **同样处理 setColumn()：**
   - 样式为 null 时只传2个参数
   - 样式非 null 时传3个参数

**关键原因：** 
- xlswriter 扩展在 PHP 8.2 下对参数处理更严格
- 传递 `null` 给 `mergeCells()` 和 `setColumn()` 可能导致参数类型错误
- 条件分支的方式避免向方法传递 null 值，改为直接调用适应的方法重载

---

## 总结

| 改动位置 | 改动内容 | 原因 |
|---------|--------|------|
| `exportSheet()` 第156行 | `$sheetName` 强制转换为字符串并提供默认值 | PHP 8.2 类型严格性 |
| `exportSheet()` 第165-171行 | 验证 `$maxDepth`、`$headers` 和数据有效性 | 防止传递无效数据给 xlswriter |
| `exportSheetHeader()` 第198-210行 | 提前计算样式，用条件分支替代三元运算符传递参数 | **表头写不上的根本原因**：避免向 xlswriter 方法传递 null 参数 |

**PHP 8.2 + xlswriter 扩展的兼容性问题已解决！**
