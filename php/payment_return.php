<?php
// ============================================
// Echo Payment System - SmilePay 回調處理
// payment_return.php
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

ini_set('error_log', $dataDir . '/payment_errors.log');

// 載入配置
$config = [
    'smilepay' => [
        'verify_code' => "0623",
        'dcvc' => "16761"
    ],
    'dataFile' => $dataDir . '/transactions.json'
];

// 生成日誌ID
$logId = uniqid('SMILEPAY_');

// 記錄傳入的數據
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到 SmilePay 回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
file_put_contents($dataDir . '/payment_return.log', $logData, FILE_APPEND);

// 檢查必要參數
if (!isset($_POST['Amount']) || !isset($_POST['Email']) || !isset($_POST['Mid_smilepay']) || !isset($_POST['Smseid'])) {
    logError($logId, "缺少必要參數");
    echo "<Roturlstatus>ERROR Missing required parameters</Roturlstatus>";
    exit;
}

// 獲取交易數據
$amount = $_POST['Amount'];
$email = $_POST['Email'];
$mid_smilepay = $_POST['Mid_smilepay'];
$smseid = $_POST['Smseid'];
$response_id = isset($_POST['Response_id']) ? $_POST['Response_id'] : '0';
$remark = isset($_POST['Remark']) ? $_POST['Remark'] : '';
$data_id = isset($_POST['Data_id']) ? $_POST['Data_id'] : '';

// 驗證參數
if (empty($amount) || empty($mid_smilepay) || empty($smseid)) {
    logError($logId, "參數值無效");
    echo "<Roturlstatus>ERROR Invalid parameter values</Roturlstatus>";
    exit;
}

// 計算 SmilePay 驗證碼
$calculated_mid = calculateSmilePayVerifyCode($config['smilepay']['verify_code'], $amount, $smseid);

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
    echo "<Roturlstatus>OK</Roturlstatus>";
    exit;
}

// 記錄交易到 JSON 檔案
try {
    $transaction = [
        'id' => $logId,
        'merchantTradeNo' => 'ECHO' . $data_id,
        'tradeNo' => $smseid,
        'amount' => intval($amount),
        'paymentType' => 'CONVENIENCE_STORE',
        'paymentDate' => date('Y-m-d H:i:s'),
        'customField1' => $remark,
        'status' => 'SUCCESS',
        'rtnCode' => $response_id,
        'rtnMsg' => 'SmilePay 交易成功',
        'createdAt' => date('Y-m-d H:i:s'),
        'rawData' => $_POST
    ];
    
    $transactions = [];
    if (file_exists($config['dataFile'])) {
        $content = file_get_contents($config['dataFile']);
        $transactions = json_decode($content, true) ?: [];
    }
    
    // 檢查是否重複
    $isDuplicate = false;
    foreach ($transactions as $t) {
        if (isset($t['tradeNo']) && $t['tradeNo'] === $smseid) {
            $isDuplicate = true;
            break;
        }
    }
    
    if (!$isDuplicate) {
        array_unshift($transactions, $transaction);
        file_put_contents($config['dataFile'], json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logSuccess($logId, "成功處理交易: {$smseid}, 金額: {$amount}");
    } else {
        logInfo($logId, "交易已存在: {$smseid}");
    }
    
    echo "<Roturlstatus>OK</Roturlstatus>";
    
} catch (Exception $e) {
    logError($logId, "保存交易失敗: " . $e->getMessage());
    echo "<Roturlstatus>ERROR Save failed</Roturlstatus>";
}

/**
 * 計算 SmilePay 驗證碼
 */
function calculateSmilePayVerifyCode($verifyParam, $amount, $smseid) {
    $A = str_pad($verifyParam, 4, '0', STR_PAD_LEFT);
    $B = str_pad($amount, 8, '0', STR_PAD_LEFT);
    
    $lastFour = substr($smseid, -4);
    $C = '';
    for ($i = 0; $i < 4; $i++) {
        $char = isset($lastFour[$i]) ? $lastFour[$i] : '0';
        $C .= is_numeric($char) ? $char : '9';
    }
    
    $D = $A . $B . $C;
    
    $E = 0;
    for ($i = 1; $i < strlen($D); $i += 2) {
        $E += intval($D[$i]);
    }
    $E *= 3;
    
    $F = 0;
    for ($i = 0; $i < strlen($D); $i += 2) {
        $F += intval($D[$i]);
    }
    $F *= 9;
    
    return $E + $F;
}

function logError($id, $message) {
    global $dataDir;
    file_put_contents($dataDir . '/payment_return.log', "[" . date('Y-m-d H:i:s') . "][$id][ERROR] $message\n", FILE_APPEND);
}

function logInfo($id, $message) {
    global $dataDir;
    file_put_contents($dataDir . '/payment_return.log', "[" . date('Y-m-d H:i:s') . "][$id][INFO] $message\n", FILE_APPEND);
}

function logSuccess($id, $message) {
    global $dataDir;
    file_put_contents($dataDir . '/payment_return.log', "[" . date('Y-m-d H:i:s') . "][$id][SUCCESS] $message\n", FILE_APPEND);
}
?>
