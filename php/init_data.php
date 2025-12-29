<?php
// ============================================
// 初始化資料目錄和檔案
// php/init_data.php
// ============================================

$dataDir = __DIR__ . '/../data';

echo "<!DOCTYPE html>
<html lang='zh-TW'>
<head>
    <meta charset='UTF-8'>
    <title>資料初始化</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #00ff00; }
        .success { color: #00ff00; }
        .info { color: #00d4ff; }
        .error { color: #ff4466; }
        pre { background: #000; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<h2>🚀 資料目錄初始化</h2>
<pre>";

// 1. 創建 data 目錄
if (!is_dir($dataDir)) {
    if (mkdir($dataDir, 0755, true)) {
        echo "<span class='success'>✅ 創建目錄: data/</span>\n";
    } else {
        echo "<span class='error'>❌ 無法創建目錄: data/</span>\n";
    }
} else {
    echo "<span class='info'>ℹ️  目錄已存在: data/</span>\n";
}

// 2. 需要創建的檔案列表
$files = [
    // JSON 資料檔
    'pending_atm.json' => '[]',
    'transactions.json' => '[]',
    'deleted_orders.json' => '[]',
    
    // Log 檔案
    'atm_payment_info.log' => '',
    'atm_payment_info_errors.log' => '',
    'atm_payment_notify.log' => '',
    'funpoint_payment_notify.log' => '',
    'payment_errors.log' => '',
    'payment_return.log' => '',
    'funpoint_payment_return.log' => ''
];

// 3. 創建檔案
echo "\n<span class='info'>📝 創建檔案...</span>\n";
foreach ($files as $filename => $defaultContent) {
    $filepath = $dataDir . '/' . $filename;
    
    if (!file_exists($filepath)) {
        if (file_put_contents($filepath, $defaultContent) !== false) {
            echo "<span class='success'>✅ 創建: data/{$filename}</span>\n";
        } else {
            echo "<span class='error'>❌ 失敗: data/{$filename}</span>\n";
        }
    } else {
        echo "<span class='info'>ℹ️  已存在: data/{$filename}</span>\n";
    }
}

// 4. 檢查權限
echo "\n<span class='info'>🔒 權限檢查...</span>\n";
if (is_dir($dataDir)) {
    $perms = substr(sprintf('%o', fileperms($dataDir)), -4);
    echo "目錄權限: {$perms}\n";
    echo "可寫入: " . (is_writable($dataDir) ? "<span class='success'>✅ 是</span>" : "<span class='error'>❌ 否</span>") . "\n";
}

// 5. 測試寫入
echo "\n<span class='info'>🧪 測試寫入...</span>\n";
$testFile = $dataDir . '/test_write.txt';
$testContent = 'Test at ' . date('Y-m-d H:i:s');
if (@file_put_contents($testFile, $testContent) !== false) {
    $readContent = file_get_contents($testFile);
    if ($readContent === $testContent) {
        unlink($testFile);
        echo "<span class='success'>✅ 寫入測試: 成功</span>\n";
        echo "<span class='success'>✅ 讀取測試: 成功</span>\n";
    } else {
        echo "<span class='error'>❌ 讀取測試: 失敗</span>\n";
    }
} else {
    echo "<span class='error'>❌ 寫入測試: 失敗</span>\n";
    echo "<span class='error'>   可能原因: 權限不足或目錄不存在</span>\n";
}

// 6. 列出所有檔案
echo "\n<span class='info'>📂 檔案列表...</span>\n";
if (is_dir($dataDir)) {
    $files = scandir($dataDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filepath = $dataDir . '/' . $file;
            $size = filesize($filepath);
            $perms = substr(sprintf('%o', fileperms($filepath)), -4);
            echo "  {$file} ({$size} bytes, {$perms})\n";
        }
    }
}

echo "\n<span class='success'>🎉 初始化完成！</span>\n";
echo "\n<span class='info'>📌 下一步:</span>\n";
echo "1. 刪除此檔案 (php/init_data.php)\n";
echo "2. 測試 ATM 開單功能\n";
echo "3. 檢查後台待處理訂單\n";

echo "</pre>
</body>
</html>";
?>