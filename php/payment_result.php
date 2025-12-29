<?php
// ============================================
// Echo Payment System - 付款結果頁面
// payment_result.php
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/../data';
ini_set('error_log', $dataDir . '/payment_errors.log');

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 記錄
$logId = uniqid('RESULT_');
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到金流前端回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
if (is_dir($dataDir)) {
    file_put_contents($dataDir . '/payment_return.log', $logData, FILE_APPEND);
}

// 接收回傳的資料
$receivedData = $_POST;

// 驗證檢查碼
$isValidCheckMac = false;
if (!empty($receivedData) && isset($receivedData['CheckMacValue'])) {
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    $tmpData = $receivedData;
    unset($tmpData['CheckMacValue']);

    ksort($tmpData);
    $checkStr = "HashKey=" . $config['funpoint']['HashKey'];
    foreach ($tmpData as $key => $value) {
        $checkStr .= "&" . $key . "=" . $value;
    }
    $checkStr .= "&HashIV=" . $config['funpoint']['HashIV'];
    $checkStr = urlencode($checkStr);
    $checkStr = strtolower($checkStr);

    $checkStr = str_replace('%2d', '-', $checkStr);
    $checkStr = str_replace('%5f', '_', $checkStr);
    $checkStr = str_replace('%2e', '.', $checkStr);
    $checkStr = str_replace('%21', '!', $checkStr);
    $checkStr = str_replace('%2a', '*', $checkStr);
    $checkStr = str_replace('%28', '(', $checkStr);
    $checkStr = str_replace('%29', ')', $checkStr);

    $calculatedCheckMacValue = strtoupper(hash('sha256', $checkStr));
    $isValidCheckMac = ($calculatedCheckMacValue === $receivedCheckMacValue);
}

// 獲取交易結果
$isSuccess = isset($receivedData['RtnCode']) && $receivedData['RtnCode'] === '1' && $isValidCheckMac;
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$tradeNo = $receivedData['MerchantTradeNo'] ?? '';
$paymentType = $receivedData['PaymentType'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>付款結果 - Echo Payment</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body {
            cursor: auto !important;
        }
        .result-container {
            text-align: center;
            max-width: 500px;
        }
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounceIn 0.6s ease;
        }
        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            margin-bottom: 25px;
            letter-spacing: 2px;
        }
        .success .result-title {
            color: var(--success-color);
            text-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
        }
        .error .result-title {
            color: var(--error-color);
            text-shadow: 0 0 20px rgba(255, 68, 102, 0.5);
        }
    </style>
</head>
<body>
<div class="form-container result-container <?php echo $isSuccess ? 'success' : 'error'; ?>">
    
    <?php if ($isSuccess): ?>
    <div class="status-icon">✓</div>
    <h2 class="result-title">付款成功</h2>
    
    <div class="success-message">
        <div class="info-row">
            <span>訂單編號</span>
            <strong><?php echo htmlspecialchars($tradeNo); ?></strong>
        </div>
        <div class="info-row">
            <span>付款金額</span>
            <strong>NT$ <?php echo number_format($amount); ?></strong>
        </div>
        <div class="info-row">
            <span>付款方式</span>
            <strong><?php echo htmlspecialchars($paymentType); ?></strong>
        </div>
        <div class="info-row">
            <span>交易時間</span>
            <strong><?php echo date('Y-m-d H:i:s'); ?></strong>
        </div>
    </div>
    
    <p style="color: var(--text-secondary); margin-top: 20px;">
        感謝您的付款！
    </p>
    
    <div class="countdown">
        將在 <span id="counter">5</span> 秒後自動返回首頁
    </div>
    
    <?php else: ?>
    <div class="status-icon">⚠</div>
    <h2 class="result-title">處理中</h2>
    
    <div class="error-message">
        <p style="margin-bottom: 15px;">
            您的交易可能正在處理中，或是發生了錯誤。
        </p>
        <?php if (!empty($tradeNo)): ?>
        <div class="info-row">
            <span>訂單編號</span>
            <strong><?php echo htmlspecialchars($tradeNo); ?></strong>
        </div>
        <?php endif; ?>
        <p style="margin-top: 15px; color: var(--text-muted);">
            如有問題請聯繫客服並提供訂單編號。
        </p>
    </div>
    <?php endif; ?>

    <button class="btn-epic" onclick="window.location.href='../index.html'">返回首頁</button>
</div>

<script>
<?php if ($isSuccess): ?>
let countdown = 5;
const counterElement = document.getElementById('counter');

function updateCounter() {
    countdown--;
    if (counterElement) counterElement.textContent = countdown;

    if (countdown <= 0) {
        window.location.href = '../index.html';
    } else {
        setTimeout(updateCounter, 1000);
    }
}

setTimeout(updateCounter, 1000);
<?php endif; ?>
</script>
<script src="../js/background-resize.js"></script>
</body>
</html>
