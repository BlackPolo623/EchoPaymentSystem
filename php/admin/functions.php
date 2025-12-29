<?php
// ============================================
// 迴響電競訂單後台 - 共用函數
// php/admin/functions.php
// ============================================

/**
 * 檢查是否已登入
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * 讀取交易記錄
 */
function getTransactions() {
    if (!file_exists(TRANSACTIONS_FILE)) {
        return [];
    }
    $content = file_get_contents(TRANSACTIONS_FILE);
    return json_decode($content, true) ?: [];
}

/**
 * 讀取待處理訂單
 */
function getPendingOrders() {
    if (!file_exists(PENDING_FILE)) {
        return [];
    }
    $content = file_get_contents(PENDING_FILE);
    return json_decode($content, true) ?: [];
}

/**
 * 儲存交易記錄
 */
function saveTransactions($transactions) {
    return file_put_contents(
        TRANSACTIONS_FILE, 
        json_encode(array_values($transactions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

/**
 * 儲存待處理訂單
 */
function savePendingOrders($orders) {
    return file_put_contents(
        PENDING_FILE, 
        json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

/**
 * 計算統計數據
 */
function getStatistics($transactions) {
    $stats = [
        'totalCount' => count($transactions),
        'totalAmount' => 0,
        'todayCount' => 0,
        'todayAmount' => 0
    ];
    
    $today = date('Y-m-d');
    
    foreach ($transactions as $t) {
        $stats['totalAmount'] += $t['amount'] ?? 0;
        
        $date = $t['createdAt'] ?? $t['paymentDate'] ?? '';
        if (strpos($date, $today) === 0) {
            $stats['todayAmount'] += $t['amount'] ?? 0;
            $stats['todayCount']++;
        }
    }
    
    return $stats;
}

/**
 * 刪除訂單
 */
function deleteOrder($orderNo) {
    $transactions = getTransactions();
    $deleted = false;
    $deletedOrder = null;
    
    foreach ($transactions as $key => $t) {
        if ($t['merchantTradeNo'] === $orderNo) {
            $deletedOrder = $t;
            $deletedOrder['deletedAt'] = date('Y-m-d H:i:s');
            unset($transactions[$key]);
            $deleted = true;
            break;
        }
    }
    
    if ($deleted) {
        // 儲存更新後的交易記錄
        saveTransactions($transactions);
        
        // 備份已刪除的訂單
        $deletedOrders = [];
        if (file_exists(DELETED_FILE)) {
            $deletedOrders = json_decode(file_get_contents(DELETED_FILE), true) ?: [];
        }
        $deletedOrders[] = $deletedOrder;
        file_put_contents(
            DELETED_FILE, 
            json_encode($deletedOrders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
    
    return $deleted;
}

/**
 * 清空待處理訂單
 */
function clearPendingOrders() {
    $pendingOrders = getPendingOrders();
    $count = count($pendingOrders);
    
    if ($count > 0) {
        savePendingOrders([]);
    }
    
    return $count;
}

/**
 * 匯出訂單資料
 */
function exportOrders($range = 'all') {
    $transactions = getTransactions();
    $exportData = [];
    
    foreach ($transactions as $t) {
        $date = $t['createdAt'] ?? $t['paymentDate'] ?? '';
        $include = false;
        
        switch ($range) {
            case 'today':
                $include = (strpos($date, date('Y-m-d')) === 0);
                break;
            case 'month':
                $include = (strpos($date, date('Y-m')) === 0);
                break;
            default:
                $include = true;
        }
        
        if ($include) {
            $exportData[] = [
                '訂單編號' => $t['merchantTradeNo'] ?? '',
                '金流編號' => $t['tradeNo'] ?? '',
                '金額' => $t['amount'] ?? 0,
                '付款方式' => $t['paymentType'] ?? '',
                '狀態' => $t['status'] ?? '',
                '付款時間' => $t['paymentDate'] ?? $t['createdAt'] ?? ''
            ];
        }
    }
    
    return $exportData;
}

/**
 * 產生 CSV 檔案內容
 */
function generateCSV($data) {
    if (empty($data)) {
        return '';
    }
    
    $headers = array_keys($data[0]);
    $csv = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    $csv .= implode(',', $headers) . "\n";
    
    foreach ($data as $row) {
        $values = [];
        foreach ($headers as $header) {
            $values[] = '"' . str_replace('"', '""', $row[$header]) . '"';
        }
        $csv .= implode(',', $values) . "\n";
    }
    
    return $csv;
}

/**
 * 分頁處理
 */
function paginate($items, $page = 1, $perPage = PER_PAGE) {
    $totalPages = ceil(count($items) / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'items' => array_slice($items, $offset, $perPage),
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => count($items)
    ];
}
?>
