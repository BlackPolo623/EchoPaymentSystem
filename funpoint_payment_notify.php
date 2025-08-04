<?php
// funpoint_payment_notify.php
// 簡化版本：收到什麼就處理什麼，完全按照官方檢查碼機制

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'payment_errors.log');

// 載入配置
$config = [
    'db' => [
        'host' => '125.228.68.9',
        'port' => '3306',
        'name' => 'l2jmobius_fafurion',
        'user' => 'payment',
        'pass' => 'x4ci3i7q',
        'charset' => 'utf8mb4'
    ],
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
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

// 簡單判斷付款方式（僅用於日誌）
$paymentType = (isset($receivedData['ATMAccBank']) && !empty($receivedData['ATMAccBank'])) ? 'ATM' : 'CREDIT';
logTransaction($transactionId, 'INFO', "Detected payment type: {$paymentType}");

// 計算檢查碼：除了CheckMacValue，其他全部參與計算
$calculatedCheckMacValue = calculateCheckMacValue($receivedData, $config['funpoint']['HashKey'], $config['funpoint']['HashIV'], $transactionId);

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

// 1. 從自訂欄位取得用戶帳號
$account = $receivedData['CustomField1'] ?? '';
if (empty($account)) {
    logTransaction($transactionId, 'ERROR', "User account not found");
    echo '0|User account not found';
    exit;
}

// 2. 獲取交易資料
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$remark = "Funpoint payment ({$paymentType}) - " . date('Y-m-d H:i:s');

// 驗證交易數據
if (empty($merchantTradeNo) || $amount <= 0) {
    logTransaction($transactionId, 'ERROR', "Invalid transaction data: MerchantTradeNo={$merchantTradeNo}, amount={$amount}");
    echo '0|Invalid transaction data';
    exit;
}

// 3. 處理交易
try {
    // 創建數據庫連接
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);

    // 開始交易
    $pdo->beginTransaction();

    // 首先檢查該交易是否已經處理過
    $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo LIMIT 1");
    $stmt->execute(['tradeNo' => $merchantTradeNo]);

    if ($stmt->rowCount() > 0) {
        // 交易已存在，避免重複處理
        logTransaction($transactionId, 'INFO', "Transaction already exists: {$merchantTradeNo}");
        $pdo->commit();
        echo '1|OK';
        exit;
    }

    // 驗證賬號是否存在
    $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
    $stmt->execute(['account' => $account]);

    if ($stmt->rowCount() == 0) {
        // 賬號不存在
        logTransaction($transactionId, 'ERROR', "Account does not exist: {$account}");
        $pdo->rollBack();
        echo '0|Account does not exist';
        exit;
    }

    // 插入交易記錄
    $stmt = $pdo->prepare("
        INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo)
        VALUES (:money, :accountID, 1, :note, :tradeNo)
    ");

    $stmt->execute([
        'money' => $amount,
        'accountID' => $account,
        'note' => $remark,
        'tradeNo' => $merchantTradeNo
    ]);

    // 提交交易
    $pdo->commit();

    // 記錄成功
    logTransaction($transactionId, 'SUCCESS', "Successfully processed funpoint transaction ({$paymentType}): {$merchantTradeNo}, Account: {$account}, Amount: {$amount}");

} catch (PDOException $e) {
    // 回滾交易
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 記錄錯誤
    logTransaction($transactionId, 'ERROR', "Database error: " . $e->getMessage());
    echo '0|Database error';
    exit;
}

// 回應歐買尬金流
echo '1|OK';

/**
 * 計算檢查碼 - 根據付款方式使用不同策略
 */
function calculateCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId) {
    // 判斷付款方式
    $isATM = (isset($receivedData['ATMAccBank']) && !empty($receivedData['ATMAccBank']));

    if ($isATM) {
        // ATM付款：歐付寶只回傳部分參數，需要重建送出時的參數結構
        logTransaction($transactionId, 'DEBUG', "ATM payment: rebuilding original sent parameters");
        return calculateATMCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId);
    } else {
        // 信用卡付款：使用回傳的所有參數
        logTransaction($transactionId, 'DEBUG', "Credit payment: using all received parameters");
        return calculateCreditCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId);
    }
}

/**
 * 信用卡檢查碼計算 - 使用所有回傳參數
 */
function calculateCreditCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId) {
    // Step 1: 移除CheckMacValue參數
    $params = $receivedData;
    unset($params['CheckMacValue']);

    logTransaction($transactionId, 'DEBUG', "Credit parameters count: " . count($params));

    // Step 2-8: 按照官方規範計算
    return calculateOfficialCheckMacValue($params, $hashKey, $hashIV, $transactionId);
}

/**
 * ATM檢查碼計算 - 嘗試使用回傳參數但過濾空值
 */
function calculateATMCheckMacValue($receivedData, $hashKey, $hashIV, $transactionId) {
    // 策略：使用回傳的參數，但只保留有值的參數
    $params = $receivedData;
    unset($params['CheckMacValue']);

    // 過濾空值參數
    $filteredParams = [];
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $filteredParams[$key] = $value;
        }
    }

    logTransaction($transactionId, 'DEBUG', "ATM non-empty parameters count: " . count($filteredParams));
    logTransaction($transactionId, 'DEBUG', "ATM filtered parameters: " . json_encode($filteredParams, JSON_UNESCAPED_UNICODE));

    // 按照官方規範計算
    return calculateOfficialCheckMacValue($filteredParams, $hashKey, $hashIV, $transactionId);
}

/**
 * 官方規範檢查碼計算
 */
function calculateOfficialCheckMacValue($params, $hashKey, $hashIV, $transactionId) {

    // Step 1: 按照英文字母順序排序
    ksort($params);

    // Step 2: 串連參數
    $paramString = '';
    foreach ($params as $key => $value) {
        if ($paramString !== '') {
            $paramString .= '&';
        }
        $paramString .= $key . '=' . $value;
    }

    logTransaction($transactionId, 'DEBUG', "Sorted parameter string: " . $paramString);

    // Step 3: 前面加HashKey，後面加HashIV
    $checkString = "HashKey=" . $hashKey . "&" . $paramString . "&HashIV=" . $hashIV;

    logTransaction($transactionId, 'DEBUG', "String with HashKey and HashIV: " . $checkString);

    // Step 4: URL encode
    $encodedString = urlencode($checkString);

    logTransaction($transactionId, 'DEBUG', "URL encoded string: " . $encodedString);

    // Step 5: 轉小寫
    $lowerString = strtolower($encodedString);

    logTransaction($transactionId, 'DEBUG', "Lowercase string: " . $lowerString);

    // Step 6: 字元替換（按照官方文件）
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

    logTransaction($transactionId, 'DEBUG', "After character replacement: " . $replacedString);

    // Step 7: SHA256加密並轉大寫
    $hash = strtoupper(hash('sha256', $replacedString));

    logTransaction($transactionId, 'DEBUG', "Final CheckMacValue: " . $hash);

    return $hash;
}

/**
 * 產生唯一交易ID用於日誌追蹤
 */
function generateTransactionId() {
    return 'NOTIFY_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * 記錄交易日誌
 */
function logTransaction($transactionId, $status, $data) {
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents('funpoint_payment_notify.log', $logMessage, FILE_APPEND);
}
?>