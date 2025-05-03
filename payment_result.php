<?php
// payment_result.php
// 這個頁面接收歐買尬金流的前端通知並顯示結果

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
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 記錄開始
$logId = uniqid('RESULT_');
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到歐買尬金流前端回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
$logData .= "GET數據: " . print_r($_GET, true) . "\n";
file_put_contents('funpoint_payment_return.log', $logData, FILE_APPEND);

// 接收回傳的資料
$receivedData = $_POST;

// 如果有資料，驗證檢查碼
if (!empty($receivedData) && isset($receivedData['CheckMacValue'])) {
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    $tmpData = $receivedData;
    unset($tmpData['CheckMacValue']);

    // 排序參數並計算檢查碼
    ksort($tmpData);
    $checkStr = "HashKey=" . $config['funpoint']['HashKey'];
    foreach ($tmpData as $key => $value) {
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
    $isValidCheckMac = ($calculatedCheckMacValue === $receivedCheckMacValue);
} else {
    $isValidCheckMac = false;
}

// 獲取交易結果
$isSuccess = isset($receivedData['RtnCode']) && $receivedData['RtnCode'] === '1' && $isValidCheckMac;
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$tradeNo = $receivedData['MerchantTradeNo'] ?? '';

// 從CustomField1或URL參數獲取用戶帳號
$account = $receivedData['CustomField1'] ?? '';
if (empty($account) && isset($_GET['account'])) {
    $account = $_GET['account'];
}

// 如果帳號為空，提早結束
if (empty($account)) {
    $isSuccess = false;
    error_log("[$logId] 賬號為空");
}

// 備註欄位處理
$remark = "歐買尬金流付款 - " . date('Y-m-d H:i:s');

// 如果交易成功，更新資料庫
if ($isSuccess && !empty($account) && $amount > 0) {
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
        $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo");
        $stmt->execute(['tradeNo' => $tradeNo]);
        
        if ($stmt->rowCount() > 0) {
            // 交易已存在，避免重複處理
            error_log("[$logId] 交易已存在: {$tradeNo}");
        } else {
            // 驗證賬號是否存在
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE login = :account");
            $stmt->execute(['account' => $account]);
            
            if ($stmt->rowCount() == 0) {
                // 賬號不存在
                error_log("[$logId] 賬號不存在: {$account}");
                $isSuccess = false;
            } else {
                // 插入交易記錄 - 與原 SmilePay 回調使用相同的表結構
                $stmt = $pdo->prepare("
                    INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo) 
                    VALUES (:money, :accountID, 1, :note, :tradeNo)
                ");
                
                $stmt->execute([
                    'money' => $amount,
                    'accountID' => $account,
                    'note' => $remark,
                    'tradeNo' => $tradeNo
                ]);
                
                // 記錄成功日誌
                error_log("[$logId] 成功處理歐買尬金流交易: {$tradeNo}, 賬號: {$account}, 金額: {$amount}");
            }
        }
        
        // 提交事務
        $pdo->commit();
        
    } catch (PDOException $e) {
        // 回滾事務
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // 記錄錯誤
        error_log("[$logId] 數據庫錯誤: " . $e->getMessage());
        $isSuccess = false; // 數據庫錯誤時，將交易視為失敗
    }
}

// 記錄結果
error_log("[$logId] 處理結果: " . ($isSuccess ? '成功' : '失敗'));

// 準備重定向參數
$redirectUrl = "paymentresult.html?success=" . ($isSuccess ? '1' : '0') .
               "&account=" . urlencode($account) .
               "&amount=" . urlencode($amount) .
               "&tradeNo=" . urlencode($tradeNo);

// 使用 JavaScript 重定向，以確保能夠攜帶參數
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?php echo $redirectUrl; ?>">
    <script>
        window.location.href = "<?php echo htmlspecialchars($redirectUrl); ?>";
    </script>
</head>
<body>
    <p>正在跳轉到結果頁面...</p>
</body>
</html>