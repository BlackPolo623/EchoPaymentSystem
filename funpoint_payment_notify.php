<?php
// funpoint_payment_notify.php
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
logTransaction($transactionId, 'START', '接收到歐買尬金流通知');

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
unset($receivedData['CheckMacValue']);

// 排序參數並計算檢查碼
ksort($receivedData);
$checkStr = "HashKey=" . $config['funpoint']['HashKey'];
foreach ($receivedData as $key => $value) {
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
    logTransaction($transactionId, 'INFO', "交易未成功: RtnCode=" . ($receivedData['RtnCode'] ?? 'missing'));
    echo '1|OK'; // 仍然返回成功，讓金流平台知道我們已收到通知
    exit;
}

// 1. 從自訂欄位取得用戶帳號
$account = $receivedData['CustomField1'] ?? '';
if (empty($account)) {
    logTransaction($transactionId, 'ERROR', "找不到用戶帳號");
    echo '0|找不到用戶帳號';
    exit;
}

// 2. 獲取交易資料
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$remark = "歐買尬金流付款 - " . date('Y-m-d H:i:s');

// 驗證交易數據
if (empty($merchantTradeNo) || $amount <= 0) {
    logTransaction($transactionId, 'ERROR', "無效的交易數據: MerchantTradeNo={$merchantTradeNo}, amount={$amount}");
    echo '0|無效的交易數據';
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
        logTransaction($transactionId, 'INFO', "交易已存在: {$merchantTradeNo}");
        $pdo->commit();
        echo '1|OK';
        exit;
    }
    
    // 驗證賬號是否存在
    $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
    $stmt->execute(['account' => $account]);
    
    if ($stmt->rowCount() == 0) {
        // 賬號不存在
        logTransaction($transactionId, 'ERROR', "賬號不存在: {$account}");
        $pdo->rollBack();
        echo '0|賬號不存在';
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
    logTransaction($transactionId, 'SUCCESS', "成功處理歐買尬金流交易: {$merchantTradeNo}, 賬號: {$account}, 金額: {$amount}");
    
} catch (PDOException $e) {
    // 回滾交易
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 記錄錯誤
    logTransaction($transactionId, 'ERROR', "數據庫錯誤: " . $e->getMessage());
    echo '0|數據庫錯誤';
    exit;
}

// 回應歐買尬金流
echo '1|OK';

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
    $dataStr = is_array($data) ? json_encode($data) : $data;
    $logMessage = "[$timestamp][$transactionId][$status] $dataStr\n";
    file_put_contents('funpoint_payment_notify.log', $logMessage, FILE_APPEND);
}
?>