<?php
// atm_payment_info.php
// ATM 取號完成通知處理 (PaymentInfoURL)

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'atm_payment_info_errors.log');

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
    echo '1|OK'; // 仍然返回成功，讓金流平台知道我們已收到通知
    exit;
}

// 取得必要資料
$account = $receivedData['CustomField1'] ?? '';
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$bankCode = $receivedData['BankCode'] ?? '';
$vAccount = $receivedData['vAccount'] ?? '';
$expireDate = $receivedData['ExpireDate'] ?? '';

// 驗證資料
if (empty($account) || empty($merchantTradeNo) || $amount <= 0) {
    logTransaction($transactionId, 'ERROR', "資料不完整: account={$account}, merchantTradeNo={$merchantTradeNo}, amount={$amount}");
    echo '0|資料不完整';
    exit;
}

try {
    // 創建數據庫連接
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
    $pdo->beginTransaction();

    // 檢查是否已記錄過此取號
    $checkStmt = $pdo->prepare("SELECT id FROM atm_virtual_accounts WHERE MerchantTradeNo = :tradeNo LIMIT 1");
    $checkStmt->execute(['tradeNo' => $merchantTradeNo]);

    if ($checkStmt->rowCount() > 0) {
        logTransaction($transactionId, 'INFO', "ATM 取號記錄已存在: {$merchantTradeNo}");
        $pdo->commit();
        echo '1|OK';
        exit;
    }

    // 驗證賬號是否存在
    $accountStmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
    $accountStmt->execute(['account' => $account]);

    if ($accountStmt->rowCount() == 0) {
        logTransaction($transactionId, 'ERROR', "賬號不存在: {$account}");
        $pdo->rollBack();
        echo '0|賬號不存在';
        exit;
    }

    // 創建 ATM 虛擬帳號記錄表（如果不存在）
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS atm_virtual_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        MerchantTradeNo VARCHAR(50) NOT NULL UNIQUE,
        accountID VARCHAR(50) NOT NULL,
        amount INT NOT NULL,
        BankCode VARCHAR(10),
        vAccount VARCHAR(20),
        ExpireDate VARCHAR(30),
        status ENUM('pending', 'paid', 'expired') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_at TIMESTAMP NULL,
        INDEX idx_trade_no (MerchantTradeNo),
        INDEX idx_account (accountID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($createTableSQL);

    // 記錄 ATM 虛擬帳號資訊
    $insertStmt = $pdo->prepare("
        INSERT INTO atm_virtual_accounts
        (MerchantTradeNo, accountID, amount, BankCode, vAccount, ExpireDate, status)
        VALUES (:tradeNo, :accountID, :amount, :bankCode, :vAccount, :expireDate, 'pending')
    ");

    $insertStmt->execute([
        'tradeNo' => $merchantTradeNo,
        'accountID' => $account,
        'amount' => $amount,
        'bankCode' => $bankCode,
        'vAccount' => $vAccount,
        'expireDate' => $expireDate
    ]);

    $pdo->commit();

    logTransaction($transactionId, 'SUCCESS', "成功記錄 ATM 取號: {$merchantTradeNo}, 賬號: {$account}, 金額: {$amount}, 虛擬帳號: {$vAccount}");

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logTransaction($transactionId, 'ERROR', "資料庫錯誤: " . $e->getMessage());
    echo '0|資料庫錯誤';
    exit;
}

// 回應金流平台
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