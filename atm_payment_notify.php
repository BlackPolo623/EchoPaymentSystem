<?php
/**
 * 歐買尬金流付款結果通知接收處理程式
 * 用於接收歐買尬金流的 Server POST 付款結果通知
 */

// 設定檔
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

// 記錄 log 的函數
function logTransaction($tradeNo, $status, $message, $data = null) {
    $logFile = 'payment_log_' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] TradeNo: {$tradeNo} | Status: {$status} | Message: {$message}";
    if ($data) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// 驗證檢查碼的函數
function verifyCheckMacValue($params, $hashKey, $hashIV) {
    // 移除 CheckMacValue 參數
    $checkMacValue = $params['CheckMacValue'];
    unset($params['CheckMacValue']);

    // 依照英文字母排序參數
    ksort($params);

    // 組成查詢字串
    $queryString = '';
    foreach ($params as $key => $value) {
        if ($queryString !== '') {
            $queryString .= '&';
        }
        $queryString .= $key . '=' . $value;
    }

    // 加上 HashKey 和 HashIV
    $queryString = 'HashKey=' . $hashKey . '&' . $queryString . '&HashIV=' . $hashIV;

    // URL encode
    $queryString = urlencode($queryString);

    // 轉為小寫
    $queryString = strtolower($queryString);

    // PHP URL encode 字元替換 (符合 .NET 編碼規則)
    $queryString = str_replace('%2d', '-', $queryString);
    $queryString = str_replace('%5f', '_', $queryString);
    $queryString = str_replace('%2e', '.', $queryString);
    $queryString = str_replace('%21', '!', $queryString);
    $queryString = str_replace('%2a', '*', $queryString);
    $queryString = str_replace('%28', '(', $queryString);
    $queryString = str_replace('%29', ')', $queryString);

    // SHA256 加密並轉大寫
    $generatedCheckMac = strtoupper(hash('sha256', $queryString));

    return $generatedCheckMac === $checkMacValue;
}

// 從 CustomField1 取得帳號資訊
function getAccountFromCustomField($customField1) {
    // 帳號資訊存在 CustomField1 參數中
    return !empty($customField1) ? trim($customField1) : null;
}

// 驗證交易編號格式 (用於額外驗證)
function validateMerchantTradeNo($merchantTradeNo) {
    // 檢查是否符合預期格式: HE + 17位數字 或 ATM + 17位數字
    return preg_match('/^(HE|ATM)\d{17}$/', $merchantTradeNo);
}

try {
    // 建立資料庫連線
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['db']['charset']}"
    ]);

    // 記錄收到的原始資料
    $rawInput = file_get_contents('php://input');
    logTransaction('UNKNOWN', 'INFO', 'Raw input received', $rawInput);

    // 檢查是否為 POST 請求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logTransaction('UNKNOWN', 'ERROR', 'Not a POST request');
        echo '0|Invalid request method';
        exit;
    }

    // 取得 POST 參數
    $postData = $_POST;

    // 檢查必要參數是否存在
    $requiredParams = ['MerchantID', 'MerchantTradeNo', 'RtnCode', 'RtnMsg', 'TradeNo', 'TradeAmt', 'PaymentDate', 'CheckMacValue'];
    foreach ($requiredParams as $param) {
        if (!isset($postData[$param])) {
            logTransaction($postData['TradeNo'] ?? 'UNKNOWN', 'ERROR', "Missing required parameter: {$param}");
            echo '0|Missing required parameter';
            exit;
        }
    }

    $merchantId = $postData['MerchantID'];
    $merchantTradeNo = $postData['MerchantTradeNo'];
    $rtnCode = (int)$postData['RtnCode'];
    $rtnMsg = $postData['RtnMsg'];
    $tradeNo = $postData['TradeNo'];
    $tradeAmt = (int)$postData['TradeAmt'];
    $paymentDate = $postData['PaymentDate'];
    $simulatePaid = isset($postData['SimulatePaid']) ? (int)$postData['SimulatePaid'] : 0;
    $customField1 = isset($postData['CustomField1']) ? $postData['CustomField1'] : '';

    logTransaction($tradeNo, 'INFO', 'Payment notification received', $postData);

    // 驗證交易編號格式
    if (!validateMerchantTradeNo($merchantTradeNo)) {
        logTransaction($tradeNo, 'WARNING', "Unexpected MerchantTradeNo format: {$merchantTradeNo}");
    }

    // 驗證檢查碼
    if (!verifyCheckMacValue($postData, $config['funpoint']['HashKey'], $config['funpoint']['HashIV'])) {
        logTransaction($tradeNo, 'ERROR', 'CheckMacValue verification failed');
        echo '0|CheckMacValue verification failed';
        exit;
    }

    logTransaction($tradeNo, 'INFO', 'CheckMacValue verification passed');

    // 檢查是否為模擬付款
    if ($simulatePaid == 1) {
        logTransaction($tradeNo, 'WARNING', 'This is a simulated payment - do not deliver goods');
        // 對於模擬付款，依然要回應成功，但不進行實際業務處理
        echo '1|OK';
        exit;
    }

    // 檢查交易狀態
    if ($rtnCode !== 1) {
        logTransaction($tradeNo, 'ERROR', "Transaction failed with code {$rtnCode}: {$rtnMsg}");
        echo '1|OK'; // 依然要回應成功，表示已收到通知
        exit;
    }

    // 開始資料庫交易
    $pdo->beginTransaction();

    try {
        // 檢查是否已經處理過這筆交易 (避免重複處理)
        $stmt = $pdo->prepare("SELECT id FROM autodonater WHERE TradeNo = :tradeNo LIMIT 1");
        $stmt->execute(['tradeNo' => $tradeNo]);

        if ($stmt->rowCount() > 0) {
            logTransaction($tradeNo, 'WARNING', 'Transaction already processed');
            $pdo->rollBack();
            echo '1|OK'; // 已處理過，回應成功
            exit;
        }

        // 從 CustomField1 取得帳號
        $account = getAccountFromCustomField($customField1);
        if (!$account) {
            logTransaction($tradeNo, 'ERROR', "Cannot get account from CustomField1: {$customField1}");
            $pdo->rollBack();
            echo '0|Cannot get account information';
            exit;
        }

        // 驗證賬號是否存在
        $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
        $stmt->execute(['account' => $account]);

        if ($stmt->rowCount() == 0) {
            logTransaction($tradeNo, 'ERROR', "Account does not exist: {$account}");
            $pdo->rollBack();
            echo '0|Account does not exist';
            exit;
        }

        // 寫入 autodonater 表
        $paymentMethod = isset($postData['PaymentType']) ? $postData['PaymentType'] : 'Unknown';
        $paymentTypePrefix = strpos($merchantTradeNo, 'ATM') === 0 ? 'ATM' : 'Credit Card';
        $note = "Payment via OMG ({$paymentTypePrefix}) - TradeNo: {$tradeNo}, MerchantTradeNo: {$merchantTradeNo}, PaymentDate: {$paymentDate}, PaymentType: {$paymentMethod}";

        $stmt = $pdo->prepare("INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo) VALUES (:money, :accountID, 1, :note, :tradeNo)");
        $stmt->execute([
            'money' => $tradeAmt,
            'accountID' => $account,
            'note' => $note,
            'tradeNo' => $tradeNo
        ]);

        // 提交交易
        $pdo->commit();

        logTransaction($tradeNo, 'SUCCESS', "Payment processed successfully for account: {$account}, amount: {$tradeAmt}");

        // 回應成功
        echo '1|OK';

    } catch (Exception $e) {
        $pdo->rollBack();
        logTransaction($tradeNo, 'ERROR', 'Database transaction failed: ' . $e->getMessage());
        echo '0|Database error';
    }

} catch (PDOException $e) {
    logTransaction('UNKNOWN', 'ERROR', 'Database connection failed: ' . $e->getMessage());
    echo '0|Database connection error';
} catch (Exception $e) {
    logTransaction('UNKNOWN', 'ERROR', 'Unexpected error: ' . $e->getMessage());
    echo '0|Server error';
}
?>