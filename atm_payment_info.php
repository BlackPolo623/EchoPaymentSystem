<?php
/**
 * ATM 取號結果通知處理檔案
 * 當 ATM 訂單建立完成後（非付款完成），歐買尬金流會以 Server POST 方式回傳付款資訊
 */

// 設定回應編碼
header('Content-Type: text/html; charset=utf-8');

// 記錄接收到的資料（用於除錯）
$logFile = 'atm_payment_info_log.txt';
$logData = date('Y-m-d H:i:s') . " - ATM Payment Info Received:\n";
$logData .= print_r($_POST, true) . "\n";
$logData .= "===========================================\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

try {
    // 接收歐買尬金流回傳的參數
    $merchantTradeNo = $_POST['MerchantTradeNo'] ?? '';
    $paymentType = $_POST['PaymentType'] ?? '';
    $tradeAmt = $_POST['TradeAmt'] ?? '';
    $paymentDate = $_POST['PaymentDate'] ?? '';
    $tradeNo = $_POST['TradeNo'] ?? '';
    $checkMacValue = $_POST['CheckMacValue'] ?? '';
    
    // ATM 專用參數
    $bankCode = $_POST['BankCode'] ?? '';        // 銀行代碼
    $vAccount = $_POST['vAccount'] ?? '';        // 虛擬帳號
    $expireDate = $_POST['ExpireDate'] ?? '';    // 繳費期限
    
    // 自訂欄位（用來識別用戶帳號）
    $customField1 = $_POST['CustomField1'] ?? '';
    
    // 驗證檢查碼（請根據實際需求實作）
    // if (!verifyCheckMacValue($_POST)) {
    //     throw new Exception('檢查碼驗證失敗');
    // }
    
    // 處理 ATM 取號資訊
    if ($merchantTradeNo && $bankCode && $vAccount) {
        // 這裡可以將虛擬帳號資訊儲存到資料庫
        // 例如：更新訂單狀態、記錄虛擬帳號等
        
        $atmInfo = [
            'merchant_trade_no' => $merchantTradeNo,
            'bank_code' => $bankCode,
            'virtual_account' => $vAccount,
            'expire_date' => $expireDate,
            'amount' => $tradeAmt,
            'user_account' => $customField1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 儲存到資料庫的邏輯
        // saveATMInfo($atmInfo);
        
        // 可以選擇發送通知給用戶
        // 例如：發送虛擬帳號資訊到用戶信箱
        // sendATMInfoToUser($customField1, $atmInfo);
        
        // 記錄成功處理的訊息
        $successLog = date('Y-m-d H:i:s') . " - ATM Info Processed Successfully:\n";
        $successLog .= "Trade No: {$merchantTradeNo}\n";
        $successLog .= "Bank Code: {$bankCode}\n";
        $successLog .= "Virtual Account: {$vAccount}\n";
        $successLog .= "User Account: {$customField1}\n";
        $successLog .= "===========================================\n";
        file_put_contents($logFile, $successLog, FILE_APPEND | LOCK_EX);
    }
    
    // 回應成功給歐買尬金流
    echo "1|OK";
    
} catch (Exception $e) {
    // 記錄錯誤
    $errorLog = date('Y-m-d H:i:s') . " - ATM Payment Info Error: " . $e->getMessage() . "\n";
    $errorLog .= "===========================================\n";
    file_put_contents($logFile, $errorLog, FILE_APPEND | LOCK_EX);
    
    // 回應錯誤給歐買尬金流
    echo "0|Error: " . $e->getMessage();
}

/**
 * 驗證檢查碼（需要根據你的 HashKey 和 HashIV 實作）
 */
function verifyCheckMacValue($data) {
    // 這裡需要實作檢查碼驗證邏輯
    // 與前端 JavaScript 的 generateCheckMacValue 函數類似
    return true; // 暫時返回 true，實際使用時請實作驗證邏輯
}

/**
 * 儲存 ATM 資訊到資料庫
 */
function saveATMInfo($atmInfo) {
    // 實作資料庫儲存邏輯
    // 例如：INSERT INTO atm_orders ...
}

/**
 * 發送 ATM 資訊給用戶
 */
function sendATMInfoToUser($userAccount, $atmInfo) {
    // 實作發送邏輯
    // 例如：發送 email 或簡訊通知用戶虛擬帳號資訊
}
?>