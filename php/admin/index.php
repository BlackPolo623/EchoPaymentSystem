<?php
// ============================================
// 迴響電競訂單後台 - 主頁面
// php/admin/index.php
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// 讀取資料
$transactions = getTransactions();
$pendingOrders = getPendingOrders();
$stats = getStatistics($transactions);

// 分頁處理
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pagination = paginate($transactions, $page);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>迴響電競訂單後台</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php include __DIR__ . '/styles.php'; ?>
</head>
<body>

<div class="admin-wrapper">
    <!-- 導航欄 -->
    <nav class="admin-nav">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="../../image/Logo.png" alt="迴響電競 Logo" style="height: 40px;">
            <h1 class="admin-title">迴響電競訂單後台</h1>
        </div>
        <div>
            <button class="refresh-btn" onclick="location.reload()">🔄 重新整理</button>
            <a href="?logout=1" class="logout-btn">登出</a>
        </div>
    </nav>
    
    <!-- 統計卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['totalCount']; ?></div>
            <div class="stat-label">總交易筆數</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($stats['totalAmount']); ?></div>
            <div class="stat-label">總交易金額</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['todayCount']; ?></div>
            <div class="stat-label">今日交易</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($stats['todayAmount']); ?></div>
            <div class="stat-label">今日金額</div>
        </div>
    </div>
    
    <!-- 待處理 ATM 訂單 -->
    <?php if (!empty($pendingOrders)): ?>
    <div class="section-header">
        <h2 class="section-title">待處理 ATM 訂單 (<?php echo count($pendingOrders); ?>)</h2>
        <button class="clear-pending-btn" onclick="clearPendingOrders()">🧹 一鍵清空</button>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>訂單編號</th>
                    <th>金額</th>
                    <th>銀行代碼</th>
                    <th>虛擬帳號</th>
                    <th>繳費期限</th>
                    <th>建立時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($pendingOrders, 0, 10) as $order): ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['merchantTradeNo'] ?? ''); ?></td>
                    <td class="amount-cell">$<?php echo number_format($order['amount'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($order['bankCode'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($order['vAccount'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($order['expireDate'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($order['createdAt'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- 交易記錄 -->
    <div class="section-header">
        <h2 class="section-title">交易記錄 (共 <?php echo $stats['totalCount']; ?> 筆)</h2>
        <button class="export-btn" onclick="showExportModal()">📥 匯出訂單</button>
    </div>
    <div class="table-container">
        <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>尚無交易記錄</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>訂單編號</th>
                    <th>金流編號</th>
                    <th>金額</th>
                    <th>付款方式</th>
                    <th>狀態</th>
                    <th>付款時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagination['items'] as $t): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['merchantTradeNo'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['tradeNo'] ?? ''); ?></td>
                    <td class="amount-cell">$<?php echo number_format($t['amount'] ?? 0); ?></td>
                    <td>
                        <?php 
                        $type = $t['paymentType'] ?? '';
                        $badgeClass = 'badge-credit';
                        if ($type === 'ATM') $badgeClass = 'badge-atm';
                        if ($type === 'CONVENIENCE_STORE') $badgeClass = 'badge-store';
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($type); ?></span>
                    </td>
                    <td>
                        <span class="badge badge-success"><?php echo htmlspecialchars($t['status'] ?? ''); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($t['paymentDate'] ?? $t['createdAt'] ?? ''); ?></td>
                    <td>
                        <button class="delete-btn" onclick="deleteOrder('<?php echo htmlspecialchars($t['merchantTradeNo'] ?? ''); ?>')">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- 分頁 -->
        <?php if ($pagination['totalPages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['page'] > 1): ?>
            <a href="?page=<?php echo $pagination['page'] - 1; ?>" class="page-btn">← 上一頁</a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $pagination['page'] - 2);
            $endPage = min($pagination['totalPages'], $pagination['page'] + 2);
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
            <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($pagination['page'] < $pagination['totalPages']): ?>
            <a href="?page=<?php echo $pagination['page'] + 1; ?>" class="page-btn">下一頁 →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 匯出對話框 -->
<div class="modal-overlay" id="exportModal" onclick="if(event.target===this) hideExportModal()">
    <div class="modal">
        <h3 class="modal-title">匯出訂單資料</h3>
        <div class="modal-options">
            <label>
                <input type="radio" name="export_range" value="all" checked>
                匯出所有訂單 (<?php echo $stats['totalCount']; ?> 筆)
            </label>
            <label>
                <input type="radio" name="export_range" value="today">
                匯出今日訂單 (<?php echo $stats['todayCount']; ?> 筆)
            </label>
            <label>
                <input type="radio" name="export_range" value="month">
                匯出本月訂單
            </label>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn modal-btn-primary" onclick="exportOrders()">📥 匯出 CSV</button>
            <button class="modal-btn modal-btn-cancel" onclick="hideExportModal()">取消</button>
        </div>
    </div>
</div>

<script>
// 顯示提示訊息
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// 刪除訂單
function deleteOrder(orderNo) {
    if (!confirm(`確定要刪除訂單 ${orderNo} 嗎？\n\n刪除的訂單會備份到 deleted_orders.json`)) {
        return;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_order&orderNo=${encodeURIComponent(orderNo)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('✓ 訂單已刪除', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('✗ 刪除失敗', 'error');
        }
    })
    .catch(() => showToast('✗ 操作失敗', 'error'));
}

// 清空待處理訂單
function clearPendingOrders() {
    if (!confirm('確定要清空所有待處理 ATM 訂單嗎？\n\n此操作無法復原！')) {
        return;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear_pending'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(`✓ 已清空 ${data.count} 筆待處理訂單`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('✗ 清空失敗', 'error');
        }
    })
    .catch(() => showToast('✗ 操作失敗', 'error'));
}

// 顯示匯出對話框
function showExportModal() {
    document.getElementById('exportModal').style.display = 'block';
}

// 隱藏匯出對話框
function hideExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

// 匯出訂單
function exportOrders() {
    const range = document.querySelector('input[name="export_range"]:checked').value;
    
    // 直接下載
    window.location.href = `api.php?action=download_csv&range=${range}`;
    
    showToast('✓ 開始下載...', 'success');
    setTimeout(() => hideExportModal(), 1000);
}
</script>

</body>
</html>
