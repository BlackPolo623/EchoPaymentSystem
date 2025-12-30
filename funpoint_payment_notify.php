<?php
// ============================================
// Echo Payment System - 金流通知處理
// funpoint_payment_notify.php
// 接收歐買尬金流回調並記錄到檔案
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/../data/payment_errors.log');

// 確保資料目錄存在
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

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
logTransaction($transactionId, 'START', 'Received funpoint payment notification');

// 接收回傳的資料
$receivedData = $_POST;
logTransaction($transactionId, 'DATA', $receivedData);

// 驗證檢查碼
if (!isset($receivedData['CheckMacValue'])) {
    logTransaction($transactionId, 'ERROR', 'Missing CheckMacValue parameter');
    echo '0|Missing CheckMacValue parameter';
    exit;
}

$receivedCheckMacValue = $receivedData['CheckMacValue'];

// 判斷付款方式
// 判斷付款方式 - 根據 PaymentType 欄位
$paymentTypeRaw = $receivedData['PaymentType'] ?? '';

if (strpos($paymentTypeRaw, 'ATM') !== false) {
    $paymentType = 'ATM';
} elseif (strpos($paymentTypeRaw, 'Credit') !== false) {
    $paymentType = 'CREDIT';
} elseif (strpos($paymentTypeRaw, 'CVS') !== false) {
    $paymentType = 'CVS';
} else {
    $paymentType = 'UNKNOWN';
}
logTransaction($transactionId, 'INFO', "Detected payment type: {$paymentType}");

$isATM = ($paymentType === 'ATM');

// 計算檢查碼
if ($isATM) {
    $calculatedCheckMacValue = calculateATMCheckMacValue($receivedData, $config['funpoint']['HashKey'], $config['funpoint']['HashIV'], $transactionId);
} else {
    $calculatedCheckMacValue = calculateCreditCheckMacValue($receivedData, $config['funpoint']['HashKey'], $config['funpoint']['HashIV'], $transactionId);
}

logTransaction($transactionId, 'DEBUG', "CheckMacValue comparison - Received: {$receivedCheckMacValue}, Calculated: {$calculatedCheckMacValue}");

// 驗證檢查碼
if ($calculatedCheckMacValue !== $receivedCheckMacValue) {
    logTransaction($transactionId, 'ERROR', "CheckMacValue verification failed ({$paymentType}): Calculated={$calculatedCheckMacValue}, Received={$receivedCheckMacValue}");
    echo '0|CheckMacValue verification failed';
    exit;
}

logTransaction($transactionId, 'SUCCESS', 'CheckMacValue verification passed');

// 驗證交易狀態
if (!isset($receivedData['RtnCode']) || $receivedData['RtnCode'] !== '1') {
    logTransaction($transactionId, 'INFO', "Transaction not successful: RtnCode=" . ($receivedData['RtnCode'] ?? 'missing'));
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
    logTransaction($transactionId, 'ERROR', "Invalid transaction data: MerchantTradeNo={$merchantTradeNo}, amount={$amount}");
    echo '0|Invalid transaction data';
    exit;
}

// 記錄交易到 JSON 檔案
try {
    $transaction = [
        'id' => $transactionId,
        'merchantTradeNo' => $merchantTradeNo,
        'tradeNo' => $tradeNo,
        'amount' => $amount,
        'paymentType' => $paymentType,
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
        // 添加新交易
        array_unshift($transactions, $transaction);

        // 保存到檔案
        file_put_contents($config['dataFile'], json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pendingFile = $dataDir . '/pending_atm.json';
        if (file_exists($pendingFile)) {
            $pendingOrders = json_decode(file_get_contents($pendingFile), true) ?: [];
            $originalCount = count($pendingOrders);

            // 過濾掉已完成的訂單
            $pendingOrders = array_filter($pendingOrders, function($order) use ($merchantTradeNo) {
                return $order['merchantTradeNo'] !== $merchantTradeNo;
            });

            $newCount = count($pendingOrders);

            // 重新索引陣列並保存
            file_put_contents($pendingFile, json_encode(array_values($pendingOrders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($originalCount > $newCount) {
                logTransaction($transactionId, 'INFO', "Removed from pending_atm.json: {$merchantTradeNo}");
            }
        }

        logTransaction($transactionId, 'SUCCESS', "Transaction saved: {$merchantTradeNo}, Amount: {$amount}, Type: {$paymentType}");
    } else {
        logTransaction($transactionId, 'INFO', "Duplicate transaction: {$merchantTradeNo}");
    }
    
} catch (Exception $e) {
    logTransaction($transactionId, 'ERROR', "Failed to save transaction: " . $e->getMessage());
    echo '0|Save error';
    exit;
}

// 回應歐買尬金流
echo '1|OK';

/**
 * ATM 檢查碼計算
 */
function calculateATMCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId) {
    $sentParams = [
        'MerchantID' => $receivedData['MerchantID'] ?? '',
        'MerchantTradeNo' => $receivedData['MerchantTradeNo'] ?? '',
        'MerchantTradeDate' => $receivedData['MerchantTradeDate'] ?? '',
        'PaymentType' => 'aio',
        'TotalAmount' => $receivedData['TradeAmt'] ?? '',
        'TradeDesc' => 'Echo Payment Service',
        'ItemName' => 'Echo Payment Service',
        'ReturnURL' => 'https://bachuan-3cdbb7d0b6e7.herokuapp.com/funpoint_payment_notify.php',
        'ChoosePayment' => 'ATM',
        'ClientBackURL' => 'https://bachuan-3cdbb7d0b6e7.herokuapp.com/index.html',
        'EncryptType' => '1',
        'CustomField1' => $receivedData['CustomField1'] ?? '',
        'ExpireDate' => '3'
    ];

    logTransaction($transactionId, 'DEBUG', "ATM sent parameters reconstructed: " . json_encode($sentParams, JSON_UNESCAPED_UNICODE));

    return calculateOfficialCheckMacValue($sentParams, $hashKey, $hashIV, $transactionId);
}

/**
 * 信用卡檢查碼計算
 */
function calculateCreditCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId) {
    $params = $receivedData;
    unset($params['CheckMacValue']);

    logTransaction($transactionId, 'DEBUG', "Credit parameters count: " . count($params));

    return calculateOfficialCheckMacValue($params, $hashKey, $hashIV, $transactionId);
}

/**
 * 官方規範檢查碼計算
 */
function calculateOfficialCheckMacValue($params, $hashKey, $hashIV, $transactionId) {
    ksort($params);

    $paramString = '';
    foreach ($params as $key => $value) {
        if ($paramString !== '') {
            $paramString .= '&';
        }
        $paramString .= $key . '=' . $value;
    }

    $checkString = "HashKey=" . $hashKey . "&" . $paramString . "&HashIV=" . $hashIV;
    $encodedString = urlencode($checkString);
    $lowerString = strtolower($encodedString);

    $replacedString = $lowerString;
    $str_replacements = [
        '%2d' => '-',
        '%5f' => '_',
        '%2e' => '.',
        '%21' => '!',
        '%2a' => '*',
        '%28' => '(',
        '%29' => ')'
    ];

    foreach ($str_replacements as $search => $replace) {
        $replacedString = str_replace($search, $replace, $replacedString);
    }

    return strtoupper(hash('sha256', $replacedString));
}

/**
 * 產生唯一交易ID
 */
function generateTransactionId() {
    return 'NOTIFY_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * 記錄交易日誌
 */
function logTransaction($transactionId, $status, $data) {
    global $dataDir;
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents($dataDir . '/funpoint_payment_notify.log', $logMessage, FILE_APPEND);
}
?>
