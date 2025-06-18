<?php
// payment_return.php
// 這個檔案接收 SmilePay 的回調通知

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
    'smilepay' => [
        'verify_key' => "A5218E74588E75C82BCFFBF05CFC8F00",
        'dcvc' => "16761",
        'verify_code' => "0623"
    ]
];

// 生成唯一的日誌ID
$logId = uniqid('SMILEPAY_');

// 記錄傳入的數據
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到 SmilePay 回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
file_put_contents('payment_return.log', $logData, FILE_APPEND);

// 檢查必要參數
if (!isset($_POST['Data_id']) || !isset($_POST['Amount']) || !isset($_POST['Email']) || !isset($_POST['Mid_smilepay'])) {
    logError($logId, "缺少必要參數");
    echo "<Roturlstatus>ERROR Missing required parameters </Roturlstatus>";
    exit;
}

// 獲取交易數據
$data_id = $_POST['Data_id']; // 訂單號碼
$amount = $_POST['Amount']; // 實際成交金額
$account = $_POST['Email']; // 使用者賬號
$mid_smilepay = $_POST['Mid_smilepay']; // SmilePay 驗證碼
$response_id = isset($_POST['Response_id']) ? $_POST['Response_id'] : '0'; // 授權成功為1，失敗為0
$remark = isset($_POST['Remark']) ? $_POST['Remark'] : ''; // 備註

// 驗證參數
if (empty($data_id) || empty($amount) || empty($account) || empty($mid_smilepay)) {
    logError($logId, "參數值無效: data_id={$data_id}, amount={$amount}, account={$account}");
    echo "<Roturlstatus>ERROR Invalid parameter value: data_id={$data_id}, amount={$amount}, account={$account}</Roturlstatus>";
    exit;
}

// 計算自己的驗證碼
$str_to_hash = $data_id . $amount . $config['smilepay']['verify_key'] . $config['smilepay']['verify_code'];
$calculated_mid = strtoupper(md5($str_to_hash));

// 驗證 SmilePay 發送的驗證碼
if ($calculated_mid !== $mid_smilepay) {
    logError($logId, "驗證碼不匹配: 計算值={$calculated_mid}, 接收值={$mid_smilepay}");
    echo "<Roturlstatus>ERROR Verification code does not match: calculated value = {$calculated_mid}, received value = {$mid_smilepay}</Roturlstatus>";
    exit;
}

// 驗證交易成功
if ($response_id != '1' || $amount <= 0) {
    logInfo($logId, "交易未成功: response_id={$response_id}, amount={$amount}");
    echo "<Roturlstatus>OK</Roturlstatus>"; // 仍然回復OK，表示已收到數據
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
    
    // 使用事務確保數據一致性
    $pdo->beginTransaction();
    
    // 首先檢查該交易是否已經處理過
    $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo LIMIT 1");
    $stmt->execute(['tradeNo' => $data_id]);
    
    if ($stmt->rowCount() > 0) {
        // 交易已存在，避免重複處理
        logInfo($logId, "交易已存在: {$data_id}");
        $pdo->commit();
        echo "<Roturlstatus>OK</Roturlstatus>";
        exit;
    }
    
    // 驗證賬號是否存在
    $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
    $stmt->execute(['account' => $account]);
    
    if ($stmt->rowCount() == 0) {
        // 賬號不存在
        logError($logId, "賬號不存在: {$account}");
        $pdo->commit(); // 還是提交事務，因為這不是資料庫錯誤
        echo "<Roturlstatus>OK</Roturlstatus>"; // 仍然回復OK，表示已收到數據
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
        'note' => $remark ?: "SmilePay付款 - " . date('Y-m-d H:i:s'),
        'tradeNo' => $data_id
    ]);
    
    // 提交事務
    $pdo->commit();
    
    // 記錄成功日誌
    logSuccess($logId, "成功處理交易: {$data_id}, 賬號: {$account}, 金額: {$amount}");
    
    // 返回成功狀態
    echo "<Roturlstatus>OK</Roturlstatus>";
    
} catch (PDOException $e) {
    // 回滾事務
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 記錄錯誤
    logError($logId, "數據庫錯誤: " . $e->getMessage());
    echo "<Roturlstatus>ERROR Database error</Roturlstatus>";
}

/**
 * 記錄錯誤日誌
 */
function logError($id, $message) {
    error_log("[" . date('Y-m-d H:i:s') . "][$id][ERROR] $message");
}

/**
 * 記錄信息日誌
 */
function logInfo($id, $message) {
    error_log("[" . date('Y-m-d H:i:s') . "][$id][INFO] $message");
}

/**
 * 記錄成功日誌
 */
function logSuccess($id, $message) {
    error_log("[" . date('Y-m-d H:i:s') . "][$id][SUCCESS] $message");
}
?>