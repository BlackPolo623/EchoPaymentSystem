<?php
/**
 * ATM/CVS 取號結果通知接收 (Server POST - PaymentInfoURL)
 * 歐買尬金流會 POST 取號結果到這個網址
 */

// 設定錯誤處理
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 記錄接收到的資料
$logFile = __DIR__ . '/logs/payment_info_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 接收 POST 資料
$postData = $_POST;
$logMessage = date('Y-m-d H:i:s') . " - Received PaymentInfo:\n" . print_r($postData, true) . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// 檢查必要參數
if (empty($postData)) {
    echo "0|No data received";
    exit;
}

// 取得關鍵參數
$merchantTradeNo = isset($postData['MerchantTradeNo']) ? $postData['MerchantTradeNo'] : '';
$rtnCode = isset($postData['RtnCode']) ? $postData['RtnCode'] : '';
$rtnMsg = isset($postData['RtnMsg']) ? $postData['RtnMsg'] : '';
$tradeNo = isset($postData['TradeNo']) ? $postData['TradeNo'] : '';
$tradeAmt = isset($postData['TradeAmt']) ? $postData['TradeAmt'] : '';
$paymentType = isset($postData['PaymentType']) ? $postData['PaymentType'] : '';
$tradeDate = isset($postData['TradeDate']) ? $postData['TradeDate'] : '';

// ATM 專用參數
$bankCode = isset($postData['BankCode']) ? $postData['BankCode'] : '';
$vAccount = isset($postData['vAccount']) ? $postData['vAccount'] : '';

// CVS 專用參數
$paymentNo = isset($postData['PaymentNo']) ? $postData['PaymentNo'] : '';

// 共用參數
$expireDate = isset($postData['ExpireDate']) ? $postData['ExpireDate'] : '';

// 讀取現有訂單資料
$ordersFile = __DIR__ . '/data/orders.json';
$orders = [];
if (file_exists($ordersFile)) {
    $ordersJson = file_get_contents($ordersFile);
    $orders = json_decode($ordersJson, true) ?: [];
}

// 更新訂單資訊
$orderUpdated = false;
foreach ($orders as &$order) {
    if ($order['orderId'] === $merchantTradeNo) {
        // 更新訂單狀態
        $order['paymentInfo'] = [
            'rtnCode' => $rtnCode,
            'rtnMsg' => $rtnMsg,
            'tradeNo' => $tradeNo,
            'paymentType' => $paymentType,
            'expireDate' => $expireDate,
            'receivedAt' => date('Y-m-d H:i:s')
        ];
        
        // ATM 資訊
        if (!empty($bankCode)) {
            $order['paymentInfo']['bankCode'] = $bankCode;
            $order['paymentInfo']['vAccount'] = $vAccount;
        }
        
        // CVS 資訊
        if (!empty($paymentNo)) {
            $order['paymentInfo']['paymentNo'] = $paymentNo;
        }
        
        // 判斷取號是否成功
        // ATM: RtnCode = 2 為成功
        // CVS: RtnCode = 10100073 為成功
        if ($rtnCode == 2 || $rtnCode == 10100073) {
            $order['status'] = 'pending_payment'; // 待繳費
            $order['paymentInfo']['success'] = true;
        } else {
            $order['status'] = 'failed';
            $order['paymentInfo']['success'] = false;
        }
        
        $orderUpdated = true;
        break;
    }
}
unset($order);

// 儲存更新後的訂單
if ($orderUpdated) {
    file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $logMessage = date('Y-m-d H:i:s') . " - Order updated: $merchantTradeNo\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// 回應歐買尬金流 (必須回應 1|OK)
echo "1|OK";