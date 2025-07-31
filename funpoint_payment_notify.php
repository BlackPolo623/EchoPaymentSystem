<?php
/**
 * funpoint_payment_notify.php
 * 歐買尬金流付款結果通知處理檔案
 * 版本：2.0
 * 更新日期：2025-07-31
 */

// 設置錯誤日誌和編碼
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'payment_errors.log');
header('Content-Type: text/html; charset=utf-8');

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
        'MerchantID' => '1020977',
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 開始處理
$transactionId = generateTransactionId();
logMessage($transactionId, 'START', '=== 開始處理歐買尬金流通知 ===');

try {
    // 1. 接收並記錄所有回傳資料
    $receivedData = $_POST;

    // 記錄原始接收資料
    logMessage($transactionId, 'RECEIVED', '接收到的原始資料: ' . json_encode($receivedData, JSON_UNESCAPED_UNICODE));

    // 檢查是否有接收到資料
    if (empty($receivedData)) {
        throw new Exception('沒有接收到任何 POST 資料');
    }

    // 2. 驗證必要參數
    $requiredFields = ['MerchantID', 'MerchantTradeNo', 'RtnCode', 'TradeAmt', 'CheckMacValue'];
    foreach ($requiredFields as $field) {
        if (!isset($receivedData[$field]) || $receivedData[$field] === '') {
            throw new Exception("缺少必要參數: {$field}");
        }
    }

    // 3. 驗證商戶ID
    if ($receivedData['MerchantID'] !== $config['funpoint']['MerchantID']) {
        throw new Exception("商戶ID不匹配: 接收到 {$receivedData['MerchantID']}, 預期 {$config['funpoint']['MerchantID']}");
    }

    // 4. 驗證檢查碼
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    unset($receivedData['CheckMacValue']); // 移除檢查碼以進行計算

    $calculatedCheckMacValue = calculateCheckMacValue($receivedData, $config['funpoint']);

    logMessage($transactionId, 'CHECKSUM', "檢查碼驗證 - 接收: {$receivedCheckMacValue}, 計算: {$calculatedCheckMacValue}");

    if ($calculatedCheckMacValue !== $receivedCheckMacValue) {
        throw new Exception("檢查碼驗證失敗");
    }

    // 5. 檢查交易狀態
    if ($receivedData['RtnCode'] !== '1') {
        $rtnMsg = $receivedData['RtnMsg'] ?? '未知錯誤';
        logMessage($transactionId, 'PAYMENT_FAILED', "付款失敗 - RtnCode: {$receivedData['RtnCode']}, RtnMsg: {$rtnMsg}");
        echo '1|OK'; // 仍然回應成功，表示已收到通知
        exit;
    }

    // 6. 檢查是否為模擬付款
    if (isset($receivedData['SimulatePaid']) && $receivedData['SimulatePaid'] === '1') {
        logMessage($transactionId, 'SIMULATE', "這是模擬付款，不進行實際處理");
        echo '1|OK';
        exit;
    }

    // 7. 提取交易資料
    $merchantTradeNo = $receivedData['MerchantTradeNo'];
    $tradeNo = $receivedData['TradeNo'] ?? '';
    $tradeAmount = intval($receivedData['TradeAmt']);
    $paymentDate = $receivedData['PaymentDate'] ?? date('Y/m/d H:i:s');
    $paymentType = $receivedData['PaymentType'] ?? 'Unknown';
    $account = $receivedData['CustomField1'] ?? '';

    // 8. 驗證用戶帳號
    if (empty($account)) {
        throw new Exception("找不到用戶帳號 (CustomField1 為空)");
    }

    // 9. 處理資料庫交易
    $result = processPayment([
        'transactionId' => $transactionId,
        'merchantTradeNo' => $merchantTradeNo,
        'tradeNo' => $tradeNo,
        'amount' => $tradeAmount,
        'account' => $account,
        'paymentDate' => $paymentDate,
        'paymentType' => $paymentType,
        'config' => $config
    ]);

    if ($result['success']) {
        logMessage($transactionId, 'SUCCESS', $result['message']);
        echo '1|OK';
    } else {
        throw new Exception($result['message']);
    }

} catch (Exception $e) {
    logMessage($transactionId, 'ERROR', $e->getMessage());
    echo '0|' . $e->getMessage();
} finally {
    logMessage($transactionId, 'END', '=== 處理完成 ===');
}

/**
 * 計算檢查碼
 */
function calculateCheckMacValue($data, $funpointConfig) {
    // 移除空值參數
    $filteredData = [];
    foreach ($data as $key => $value) {
        if ($value !== '' && $value !== null) {
            $filteredData[$key] = $value;
        }
    }

    // 按字母順序排序
    ksort($filteredData);

    // 組合字串
    $checkString = "HashKey=" . $funpointConfig['HashKey'];
    foreach ($filteredData as $key => $value) {
        $checkString .= "&" . $key . "=" . $value;
    }
    $checkString .= "&HashIV=" . $funpointConfig['HashIV'];

    // URL encode
    $checkString = urlencode($checkString);

    // 轉小寫
    $checkString = strtolower($checkString);

    // 字元替換
    $checkString = str_replace(['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
                              ['-', '_', '.', '!', '*', '(', ')'],
                              $checkString);

    // SHA256 加密並轉大寫
    return strtoupper(hash('sha256', $checkString));
}

/**
 * 處理付款交易
 */
function processPayment($params) {
    try {
        $config = $params['config'];

        // 建立資料庫連接
        $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);

        // 開始資料庫交易
        $pdo->beginTransaction();

        // 檢查交易是否已存在 (避免重複處理)
        $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo LIMIT 1");
        $stmt->execute(['tradeNo' => $params['merchantTradeNo']]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            return [
                'success' => true,
                'message' => "交易已存在，跳過處理: {$params['merchantTradeNo']}"
            ];
        }

        // 驗證用戶帳號是否存在
        $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
        $stmt->execute(['account' => $params['account']]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => "用戶帳號不存在: {$params['account']}"
            ];
        }

        // 插入交易記錄
        $stmt = $pdo->prepare("
            INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo, created_at)
            VALUES (:money, :accountID, 1, :note, :tradeNo, NOW())
        ");

        $note = "歐買尬金流付款 - {$params['paymentType']} - {$params['paymentDate']} - TradeNo: {$params['tradeNo']}";

        $stmt->execute([
            'money' => $params['amount'],
            'accountID' => $params['account'],
            'note' => $note,
            'tradeNo' => $params['merchantTradeNo']
        ]);

        $billId = $pdo->lastInsertId();

        // 提交交易
        $pdo->commit();

        return [
            'success' => true,
            'message' => "成功處理付款 - BillID: {$billId}, 帳號: {$params['account']}, 金額: {$params['amount']}, 交易號: {$params['merchantTradeNo']}"
        ];

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'message' => "資料庫錯誤: " . $e->getMessage()
        ];
    }
}

/**
 * 產生交易ID
 */
function generateTransactionId() {
    return 'PAY_' . date('YmdHis') . '_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

/**
 * 記錄日誌
 */
function logMessage($transactionId, $level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$transactionId}] [{$level}] {$message}" . PHP_EOL;

    // 寫入主要日誌檔案
    file_put_contents('funpoint_payment_notify.log', $logEntry, FILE_APPEND | LOCK_EX);

    // 如果是錯誤，也寫入錯誤日誌
    if ($level === 'ERROR') {
        file_put_contents('payment_errors.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>