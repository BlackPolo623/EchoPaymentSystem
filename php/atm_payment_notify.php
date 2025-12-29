<?php
// ============================================
// Echo Payment System - ATM 付款完成通知處理
// atm_payment_notify.php
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 確保資料目錄存在
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

ini_set('error_log', $dataDir . '/atm_payment_errors.log');

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ],
    'dataFile' => $dataDir . '/transactions.json'
];

// 開始記錄交易
$transactionId = generateTransactionId();
logTransaction($transactionId, 'START', '接收到 ATM 付款完成通知');

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

// 驗證交易狀態
if (!isset($receivedData['RtnCode']) || $receivedData['RtnCode'] !== '1') {
    logTransaction($transactionId, 'INFO', "ATM 交易未成功: RtnCode=" . ($receivedData['RtnCode'] ?? 'missing'));
    echo '1|OK';
    exit;
}

// 獲取交易資料
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$tradeNo = $receivedData['TradeNo'] ?? '';
$paymentDate = $receivedData['PaymentDate'] ?? date('Y-m-d H:i:s');
$customField1 = $receivedData['CustomField1'] ?? '';

// 驗證交易數據
if (empty($merchantTradeNo) || $amount <= 0) {
    logTransaction($transactionId, 'ERROR', "無效的交易數據: MerchantTradeNo={$merchantTradeNo}, amount={$amount}");
    echo '0|無效的交易數據';
    exit;
}

// 記錄交易到 JSON 檔案
try {
    $transaction = [
        'id' => $transactionId,
        'merchantTradeNo' => $merchantTradeNo,
        'tradeNo' => $tradeNo,
        'amount' => $amount,
        'paymentType' => 'ATM',
        'paymentDate' => $paymentDate,
        'customField1' => $customField1,
        'status' => 'SUCCESS',
        'rtnCode' => $receivedData['RtnCode'] ?? '',
        'rtnMsg' => $receivedData['RtnMsg'] ?? '',
        'createdAt' => date('Y-m-d H:i:s'),
        'rawData' => $receivedData
    ];
    
    // 讀取現有交易記錄
    $transactions = [];
    if (file_exists($config['dataFile'])) {
        $content = file_get_contents($config['dataFile']);
        $transactions = json_decode($content, true) ?: [];
    }
    
    // 檢查是否重複交易
    $isDuplicate = false;
    foreach ($transactions as $t) {
        if ($t['merchantTradeNo'] === $merchantTradeNo) {
            $isDuplicate = true;
            break;
        }
    }
    
    if (!$isDuplicate) {
        array_unshift($transactions, $transaction);
        file_put_contents($config['dataFile'], json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pendingFile = $dataDir . '/pending_atm.json';
        if (file_exists($pendingFile)) {
            $pendingOrders = json_decode(file_get_contents($pendingFile), true) ?: [];
            $pendingOrders = array_filter($pendingOrders, function($order) use ($merchantTradeNo) {
                return $order['merchantTradeNo'] !== $merchantTradeNo;
            });
            file_put_contents($pendingFile, json_encode(array_values($pendingOrders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        logTransaction($transactionId, 'SUCCESS', "成功處理 ATM 交易: {$merchantTradeNo}, 金額: {$amount}");
    } else {
        logTransaction($transactionId, 'INFO', "交易已存在: {$merchantTradeNo}");
    }
    
} catch (Exception $e) {
    logTransaction($transactionId, 'ERROR', "保存交易失敗: " . $e->getMessage());
    echo '0|保存錯誤';
    exit;
}

// 回應歐買尬金流
echo 'OK';

/**
 * 產生唯一交易ID
 */
function generateTransactionId() {
    return 'ATM_PAY_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * 記錄交易日誌
 */
function logTransaction($transactionId, $status, $data) {
    global $dataDir;
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents($dataDir . '/atm_payment_notify.log', $logMessage, FILE_APPEND);
}
?>
