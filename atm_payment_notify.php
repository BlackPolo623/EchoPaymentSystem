<?php
/**
 * atm_payment_notify.php
 * ATM 專用付款結果通知處理檔案 - Heroku 優化版
 * 版本：2.0 (Heroku 優化)
 * 更新日期：2025-07-31
 */

// 清除任何之前的輸出
if (ob_get_level()) {
    ob_end_clean();
}

// 設置內容類型
header('Content-Type: text/plain; charset=utf-8');

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

try {
    // 記錄開始處理
    error_log("[$transactionId] ATM 付款通知開始處理");

    // 接收並記錄所有回傳資料
    $receivedData = $_POST;

    // 記錄接收到的資料
    error_log("[$transactionId] 接收到的資料: " . json_encode($receivedData));

    // 檢查是否有接收到資料
    if (empty($receivedData)) {
        error_log("[$transactionId] 錯誤: 沒有接收到任何 POST 資料");
        echo '0|沒有接收到任何 POST 資料';
        exit;
    }

    // 驗證必要參數
    $requiredFields = ['MerchantID', 'MerchantTradeNo', 'RtnCode', 'TradeAmt', 'CheckMacValue'];
    foreach ($requiredFields as $field) {
        if (!isset($receivedData[$field]) || $receivedData[$field] === '') {
            error_log("[$transactionId] 錯誤: 缺少必要參數 $field");
            echo "0|缺少必要參數: $field";
            exit;
        }
    }

    // 驗證商戶ID
    if ($receivedData['MerchantID'] !== $config['funpoint']['MerchantID']) {
        error_log("[$transactionId] 錯誤: 商戶ID不匹配");
        echo '0|商戶ID不匹配';
        exit;
    }

    // 驗證檢查碼
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    $dataForChecksum = $receivedData;
    unset($dataForChecksum['CheckMacValue']);

    $calculatedCheckMacValue = calculateCheckMacValue($dataForChecksum, $config['funpoint']);

    error_log("[$transactionId] 檢查碼驗證 - 接收: $receivedCheckMacValue, 計算: $calculatedCheckMacValue");
    /*
    if ($calculatedCheckMacValue !== $receivedCheckMacValue) {
        error_log("[$transactionId] 錯誤: 檢查碼驗證失敗");
        echo '0|檢查碼驗證失敗';
        exit;
    }*/

    // 檢查交易狀態
    if ($receivedData['RtnCode'] !== '1') {
        $rtnMsg = $receivedData['RtnMsg'] ?? '未知錯誤';
        error_log("[$transactionId] ATM 付款失敗 - RtnCode: {$receivedData['RtnCode']}, RtnMsg: $rtnMsg");
        echo '1|OK'; // 仍然回應成功
        exit;
    }

    // 檢查是否為模擬付款
    if (isset($receivedData['SimulatePaid']) && $receivedData['SimulatePaid'] === '1') {
        error_log("[$transactionId] 這是 ATM 模擬付款，不進行實際處理");
        echo '1|OK';
        exit;
    }

    // 提取交易資料
    $merchantTradeNo = $receivedData['MerchantTradeNo'];
    $tradeNo = $receivedData['TradeNo'] ?? '';
    $tradeAmount = intval($receivedData['TradeAmt']);
    $paymentDate = $receivedData['PaymentDate'] ?? date('Y/m/d H:i:s');
    $paymentType = $receivedData['PaymentType'] ?? 'ATM_Unknown';
    $account = $receivedData['CustomField1'] ?? '';

    // 驗證用戶帳號
    if (empty($account)) {
        error_log("[$transactionId] 錯誤: 找不到用戶帳號");
        echo '0|找不到用戶帳號';
        exit;
    }

    error_log("[$transactionId] 開始處理資料庫交易 - 帳號: $account, 金額: $tradeAmount, 交易號: $merchantTradeNo");

    // 處理資料庫交易
    $result = processATMPayment([
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
        error_log("[$transactionId] 成功: {$result['message']}");
        echo '1|OK';
    } else {
        error_log("[$transactionId] 失敗: {$result['message']}");
        echo "0|{$result['message']}";
    }

} catch (Exception $e) {
    error_log("[$transactionId] 異常: " . $e->getMessage());
    echo '0|系統異常';
} catch (Error $e) {
    error_log("[$transactionId] 錯誤: " . $e->getMessage());
    echo '0|系統錯誤';
}

exit;

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
 * 處理 ATM 付款交易
 */
function processATMPayment($params) {
    $transactionId = $params['transactionId'];

    try {
        $config = $params['config'];

        error_log("[$transactionId] 建立資料庫連接");

        // 建立資料庫連接
        $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
        error_log("[$transactionId] 資料庫連接成功");

        // 開始資料庫交易
        $pdo->beginTransaction();

        // 檢查交易是否已存在
        $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo LIMIT 1");
        $stmt->execute(['tradeNo' => $params['merchantTradeNo']]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            error_log("[$transactionId] 交易已存在，跳過處理");
            return [
                'success' => true,
                'message' => "ATM 交易已存在，跳過處理: {$params['merchantTradeNo']}"
            ];
        }

        // 驗證用戶帳號是否存在
        $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :account LIMIT 1");
        $stmt->execute(['account' => $params['account']]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            error_log("[$transactionId] 用戶帳號不存在: {$params['account']}");
            return [
                'success' => false,
                'message' => "用戶帳號不存在: {$params['account']}"
            ];
        }

        error_log("[$transactionId] 用戶帳號驗證成功: {$params['account']}");

        // 組合備註資訊
        $note = "ATM 付款 - {$params['paymentType']} - {$params['paymentDate']} - TradeNo: {$params['tradeNo']}";

        // 插入交易記錄
        $stmt = $pdo->prepare("
            INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo)
            VALUES (:money, :accountID, 1, :note, :tradeNo)
        ");

        $stmt->execute([
            'money' => $params['amount'],
            'accountID' => $params['account'],
            'note' => $note,
            'tradeNo' => $params['merchantTradeNo']
        ]);

        $billId = $pdo->lastInsertId();
        error_log("[$transactionId] 成功插入記錄，BillID: $billId");

        // 提交交易
        $pdo->commit();

        return [
            'success' => true,
            'message' => "成功處理 ATM 付款 - BillID: $billId, 帳號: {$params['account']}, 金額: {$params['amount']}, 交易號: {$params['merchantTradeNo']}"
        ];

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("[$transactionId] 資料庫錯誤: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "資料庫錯誤: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("[$transactionId] 處理錯誤: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "處理錯誤: " . $e->getMessage()
        ];
    }
}

/**
 * 產生交易ID
 */
function generateTransactionId() {
    return 'ATM_' . date('YmdHis') . '_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}
?>