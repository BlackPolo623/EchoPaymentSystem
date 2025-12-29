<?php
// ============================================
// 迴響電競訂單後台 - API 處理
// php/admin/api.php
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 必須已登入
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 設定 JSON 回應
header('Content-Type: application/json');

// 取得動作類型
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    
    // ========== 刪除訂單 ==========
    case 'delete_order':
        $orderNo = $_POST['orderNo'] ?? '';
        
        if (empty($orderNo)) {
            echo json_encode(['success' => false, 'error' => '訂單編號不能為空']);
            exit;
        }
        
        $deleted = deleteOrder($orderNo);
        
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? '訂單已刪除並備份' : '刪除失敗'
        ]);
        break;
    
    // ========== 清空待處理訂單 ==========
    case 'clear_pending':
        $count = clearPendingOrders();
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'message' => "已清空 {$count} 筆待處理訂單"
        ]);
        break;
    
    // ========== 取得匯出資料 ==========
    case 'get_export_data':
        $range = $_POST['range'] ?? 'all';
        $data = exportOrders($range);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
        break;
    
    // ========== 下載 CSV ==========
    case 'download_csv':
        $range = $_GET['range'] ?? 'all';
        $data = exportOrders($range);
        
        if (empty($data)) {
            echo json_encode(['success' => false, 'error' => '沒有資料可匯出']);
            exit;
        }
        
        // 產生 CSV
        $csv = generateCSV($data);
        $filename = '訂單資料_' . date('Y-m-d') . '.csv';
        
        // 設定下載標頭
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo $csv;
        exit;
    
    // ========== 取得統計資料 ==========
    case 'get_stats':
        $transactions = getTransactions();
        $stats = getStatistics($transactions);
        $pendingOrders = getPendingOrders();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'pendingCount' => count($pendingOrders)
        ]);
        break;
    
    // ========== 未知動作 ==========
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
?>
