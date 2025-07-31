<?php
// atm_payment_info.php
// ATM 取號完成通知處理 (PaymentInfoURL) - 簡化版

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'atm_payment_info_errors.log');

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 開始記錄交易
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

// 檢查取號是否成功
// 根據文件：ATM 回傳值為 2 時，交易狀態為取號成功
if (!isset($receivedData['RtnCode']) || $receivedData['RtnCode'] !== '2') {
    logTransaction($transactionId, 'INFO', "ATM 取號未成功: RtnCode=" . ($receivedData['RtnCode'] ?? 'missing'));
    echo '1|OK';
    exit;
}

// 記錄取號成功，但不存入資料庫
$account = $receivedData['CustomField1'] ?? '';
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$bankCode = $receivedData['BankCode'] ?? '';
$vAccount = $receivedData['vAccount'] ?? '';
$expireDate = $receivedData['ExpireDate'] ?? '';

logTransaction($transactionId, 'SUCCESS', "ATM 取號成功: {$merchantTradeNo}, 賬號: {$account}, 金額: {$amount}, 虛擬帳號: {$vAccount}");

// 直接回應金流平台，不做任何資料庫操作
echo '1|OK';

/**
 * 產生唯一交易ID用於日誌追蹤
 */
function generateTransactionId() {
    return 'ATM_INFO_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * 記錄交易日誌
 */
function logTransaction($transactionId, $status, $data) {
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = is_array($data) ? json_encode($data) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents('atm_payment_info.log', $logMessage, FILE_APPEND);
}
?>