<?php
// log_sent_checkmac.php
// 記錄前端送出的CheckMacValue和參數

header('Content-Type: application/json');

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 接收前端發送的資料
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action']) || $data['action'] !== 'log_sent_checkmac') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// 記錄資料
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tradeNo' => $data['tradeNo'] ?? '',
    'sentCheckMacValue' => $data['sentCheckMacValue'] ?? '',
    'sentParams' => $data['sentParams'] ?? [],
    'clientTimestamp' => $data['timestamp'] ?? ''
];

// 寫入日誌檔案
$logMessage = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents('sent_checkmac.log', $logMessage, FILE_APPEND | LOCK_EX);

// 回應成功
echo json_encode(['success' => true]);
?>