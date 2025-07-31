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
        'host' => '220.135.250.156',
        'port' => '3306',
        'name' => 'l2jmobius_fafurion',
        'user' => 'payment',
        'pass' => 'x4ci3i7q',
        'charset' => 'utf8mb4'
    ],
    'smilepay' => [
        'verify_code' => "0623", // 商家驗證參數
        'dcvc' => "16761"
    ]
];

// 生成唯一的日誌ID
$logId = uniqid('SMILEPAY_');

// 記錄傳入的數據
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到 SmilePay 回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
file_put_contents('payment_return.log', $logData, FILE_APPEND);

// 檢查必要參數
if (!isset($_POST['Amount']) || !isset($_POST['Email']) || !isset($_POST['Mid_smilepay']) || !isset($_POST['Smseid'])) {
    logError($logId, "缺少必要參數");
    echo "<Roturlstatus>ERROR Missing required parameters</Roturlstatus>";
    exit;
}

// 獲取交易數據
$amount = $_POST['Amount']; // 實際成交金額
$account = $_POST['Email']; // 使用者賬號
$mid_smilepay = $_POST['Mid_smilepay']; // SmilePay 驗證碼
$smseid = $_POST['Smseid']; // SmilePay 交易編號
$response_id = isset($_POST['Response_id']) ? $_POST['Response_id'] : '0'; // 授權成功為1，失敗為0
$remark = isset($_POST['Remark']) ? $_POST['Remark'] : ''; // 備註

// 驗證參數
if (empty($amount) || empty($account) || empty($mid_smilepay) || empty($smseid)) {
    logError($logId, "參數值無效: amount={$amount}, account={$account}, smseid={$smseid}");
    echo "<Roturlstatus>ERROR Invalid parameter values</Roturlstatus>";
    exit;
}

// 計算 SmilePay 驗證碼
$calculated_mid = calculateSmilePayVerifyCode($config['smilepay']['verify_code'], $amount, $smseid);

// 記錄計算過程
logInfo($logId, "驗證碼計算: 商家參數={$config['smilepay']['verify_code']}, 金額={$amount}, Smseid={$smseid}, 計算結果={$calculated_mid}, 接收值={$mid_smilepay}");

// 驗證 SmilePay 發送的驗證碼
if ($calculated_mid != $mid_smilepay) {
    logError($logId, "驗證碼不匹配: 計算值={$calculated_mid}, 接收值={$mid_smilepay}");
    echo "<Roturlstatus>ERROR Verification code does not match</Roturlstatus>";
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
    $stmt->execute(['tradeNo' => $smseid]);

    if ($stmt->rowCount() > 0) {
        // 交易已存在，避免重複處理
        logInfo($logId, "交易已存在: {$smseid}");
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
        'tradeNo' => $smseid
    ]);

    // 提交事務
    $pdo->commit();

    // 記錄成功日誌
    logSuccess($logId, "成功處理交易: {$smseid}, 賬號: {$account}, 金額: {$amount}");

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
 * 計算 SmilePay 驗證碼
 * 根據 SmilePay 官方文檔的計算方式
 */
function calculateSmilePayVerifyCode($verifyParam, $amount, $smseid) {
    // A = 商家驗證參數 (共四碼,不足四碼前面補零)
    $A = str_pad($verifyParam, 4, '0', STR_PAD_LEFT);

    // B = 收款金額 (取八碼,不足八碼前面補零)
    $B = str_pad($amount, 8, '0', STR_PAD_LEFT);

    // C = Smseid參數的後四碼,如不為數字則以 9 替代
    $lastFour = substr($smseid, -4);
    $C = '';
    for ($i = 0; $i < 4; $i++) {
        $char = isset($lastFour[$i]) ? $lastFour[$i] : '0';
        $C .= is_numeric($char) ? $char : '9';
    }

    // D = A & B & C
    $D = $A . $B . $C;

    // E = 取 D 的偶數位字數(由前面算起)相加後乘以 3
    $E = 0;
    for ($i = 1; $i < strlen($D); $i += 2) { // 偶數位 (索引 1,3,5...)
        $E += intval($D[$i]);
    }
    $E *= 3;

    // F = 取 D 的奇數位字數(由前面算起)相加後乘以 9
    $F = 0;
    for ($i = 0; $i < strlen($D); $i += 2) { // 奇數位 (索引 0,2,4...)
        $F += intval($D[$i]);
    }
    $F *= 9;

    // SmilePay驗證碼 = E + F
    return $E + $F;
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