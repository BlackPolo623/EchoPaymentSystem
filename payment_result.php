<?php
// payment_result.php
// 這個頁面接收歐買尬金流的前端通知並顯示結果（僅顯示，不執行資料庫操作）

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'payment_errors.log');

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 記錄開始
$logId = uniqid('RESULT_');
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到歐買尬金流前端回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
$logData .= "GET數據: " . print_r($_GET, true) . "\n";
file_put_contents('funpoint_payment_return.log', $logData, FILE_APPEND);

// 接收回傳的資料
$receivedData = $_POST;

// 如果有資料，驗證檢查碼
if (!empty($receivedData) && isset($receivedData['CheckMacValue'])) {
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    $tmpData = $receivedData;
    unset($tmpData['CheckMacValue']);

    // 排序參數並計算檢查碼
    ksort($tmpData);
    $checkStr = "HashKey=" . $config['funpoint']['HashKey'];
    foreach ($tmpData as $key => $value) {
        $checkStr .= "&" . $key . "=" . $value;
    }
    $checkStr .= "&HashIV=" . $config['funpoint']['HashIV'];
    $checkStr = urlencode($checkStr);
    $checkStr = strtolower($checkStr);

    // 取代特殊字元
    $checkStr = str_replace('%2d', '-', $checkStr);
    $checkStr = str_replace('%5f', '_', $checkStr);
    $checkStr = str_replace('%2e', '.', $checkStr);
    $checkStr = str_replace('%21', '!', $checkStr);
    $checkStr = str_replace('%2a', '*', $checkStr);
    $checkStr = str_replace('%28', '(', $checkStr);
    $checkStr = str_replace('%29', ')', $checkStr);

    // 計算檢查碼
    $calculatedCheckMacValue = strtoupper(hash('sha256', $checkStr));

    // 驗證檢查碼
    $isValidCheckMac = ($calculatedCheckMacValue === $receivedCheckMacValue);
} else {
    $isValidCheckMac = false;
}

// 獲取交易結果 - 簡化版本，主要用於顯示
$isSuccess = isset($receivedData['RtnCode']) && $receivedData['RtnCode'] === '1' && $isValidCheckMac;
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$tradeNo = $receivedData['MerchantTradeNo'] ?? '';

// 從CustomField1或URL參數獲取用戶帳號
$account = $receivedData['CustomField1'] ?? '';
if (empty($account) && isset($_GET['account'])) {
    $account = $_GET['account'];
}

// 記錄結果（僅記錄，不執行資料庫操作）
error_log("[$logId] 頁面顯示結果: " . ($isSuccess ? '成功' : '失敗') . " - 帳號: {$account}, 金額: {$amount}");
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>霸權天堂II 贊助結果</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-container {
            text-align: center;
        }
        .countdown {
            margin-top: 10px;
            font-size: 14px;
            color: rgba(158, 255, 250, 0.7);
        }
    </style>
</head>
<body>
<div class="form-container result-container">
    <h2>霸權天堂II 贊助結果</h2>

    <?php if ($isSuccess): ?>
    <div class="success-message">
        <div style="font-size: 24px; font-weight: bold; color: #0f0; margin-bottom: 15px;">贊助成功！</div>
        <div class="info-row">帳號: <strong><?php echo htmlspecialchars($account); ?></strong></div>
        <div class="info-row">金額: <strong><?php echo htmlspecialchars($amount); ?> 元</strong></div>
        <div class="info-row">交易編號: <strong><?php echo htmlspecialchars($tradeNo); ?></strong></div>
        <div style="margin-top: 15px;">點數已加入您的帳戶，感謝您的支持！</div>
        <div class="countdown">將在 <span id="counter">5</span> 秒後自動返回首頁</div>
    </div>
    <?php else: ?>
    <div class="error-message">
        <div style="font-size: 24px; font-weight: bold; color: #f00; margin-bottom: 15px;">贊助處理中或發生錯誤</div>
        <?php if (!empty($account)): ?>
        <div class="info-row">帳號: <strong><?php echo htmlspecialchars($account); ?></strong></div>
        <?php endif; ?>
        <div style="margin-top: 15px;">
            您的交易可能正在處理中，或是發生了錯誤。<br>
            請稍後查看您的帳戶點數，如有問題請聯絡客服。
        </div>
    </div>
    <?php endif; ?>

    <button class="btn-epic" onclick="window.location.href='index.html'">返回贊助頁面</button>
</div>

<script>
// 自動倒數計時返回
<?php if ($isSuccess): ?>
let countdown = 5;
const counterElement = document.getElementById('counter');

function updateCounter() {
    countdown--;
    if (counterElement) counterElement.textContent = countdown;

    if (countdown <= 0) {
        window.location.href = 'index.html';
    } else {
        setTimeout(updateCounter, 1000);
    }
}

// 開始倒數
setTimeout(updateCounter, 1000);
<?php endif; ?>
</script>
<script src="script.js"></script>
<script src="background-resize.js"></script>
<script src="htmlcss.js"></script>
</body>
</html>