<?php
// ============================================
// Echo Payment System - ATM 取號完成通知處理
// atm_payment_info.php
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 確保資料目錄存在
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

ini_set('error_log', $dataDir . '/atm_payment_info_errors.log');

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ],
    'pendingFile' => $dataDir . '/pending_atm.json'
];

// 開始記錄
$transactionId = generateTransactionId();
logTransaction($transactionId, 'START', '接收到 ATM 取號完成通知');

// 接收回傳的資料
$receivedData = $_POST;
logTransaction($transactionId, 'DATA', $receivedData);

// 驗證檢查碼
if (!isset($receivedData['CheckMacValue'])) {
    logTransaction($transactionId, 'ERROR', '缺少CheckMacValue參數');
    echo '0|缺少CheckMacValue參數';
    exit;
}

$receivedCheckMacValue = $receivedData['CheckMacValue'];
$checkData = $receivedData;
unset($checkData['CheckMacValue']);

// 排序參數並計算檢查碼
ksort($checkData);
$checkStr = "HashKey=" . $config['funpoint']['HashKey'];
foreach ($checkData as $key => $value) {
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
if ($calculatedCheckMacValue !== $receivedCheckMacValue) {
    logTransaction($transactionId, 'ERROR', "CheckMacValue 驗證失敗: 計算值={$calculatedCheckMacValue}, 接收值={$receivedCheckMacValue}");
    echo '0|CheckMacValue 驗證失敗';
    exit;
}

// 檢查取號是否成功 (ATM 回傳值為 2 時為取號成功)
if (!isset($receivedData['RtnCode']) || $receivedData['RtnCode'] !== '2') {
    logTransaction($transactionId, 'INFO', "ATM 取號未成功: RtnCode=" . ($receivedData['RtnCode'] ?? 'missing'));
    echo '1|OK';
    exit;
}

// 記錄取號成功資訊
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$bankCode = $receivedData['BankCode'] ?? '';
$vAccount = $receivedData['vAccount'] ?? '';
$expireDate = $receivedData['ExpireDate'] ?? '';
$customField1 = $receivedData['CustomField1'] ?? '';

// 保存待處理的 ATM 訂單
try {
    $pendingOrder = [
        'id' => $transactionId,
        'merchantTradeNo' => $merchantTradeNo,
        'amount' => $amount,
        'bankCode' => $bankCode,
        'vAccount' => $vAccount,
        'expireDate' => $expireDate,
        'customField1' => $customField1,
        'status' => 'PENDING',
        'createdAt' => date('Y-m-d H:i:s')
    ];
    
    $pendingOrders = [];
    if (file_exists($config['pendingFile'])) {
        $content = file_get_contents($config['pendingFile']);
        $pendingOrders = json_decode($content, true) ?: [];
    }
    
    array_unshift($pendingOrders, $pendingOrder);
    file_put_contents($config['pendingFile'], json_encode($pendingOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    logTransaction($transactionId, 'SUCCESS', "ATM 取號成功: {$merchantTradeNo}, 金額: {$amount}, 虛擬帳號: {$vAccount}");
    
} catch (Exception $e) {
    logTransaction($transactionId, 'ERROR', "保存待處理訂單失敗: " . $e->getMessage());
}

// 回應金流平台
echo '1|OK';

/**
 * 產生唯一交易ID
 */
function generateTransactionId() {
    return 'ATM_INFO_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * 記錄交易日誌
 */
function logTransaction($transactionId, $status, $data) {
    global $dataDir;
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents($dataDir . '/atm_payment_info.log', $logMessage, FILE_APPEND);
}
?>
