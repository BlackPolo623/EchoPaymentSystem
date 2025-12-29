<?php
// ============================================
// Echo Payment System - 後台管理
// admin/index.php
// ============================================

// 簡單的密碼保護（可自行修改密碼）
$adminPassword = 'echo2024admin';

session_start();

// 處理登入
if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $loginError = '密碼錯誤';
    }
}

// 處理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 檢查是否已登入
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 讀取交易數據
$dataDir = __DIR__ . '/../../data';
$dataFile = $dataDir . '/transactions.json';
$pendingFile = $dataDir . '/pending_atm.json';

$transactions = [];
$pendingOrders = [];

if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $transactions = json_decode($content, true) ?: [];
}

if (file_exists($pendingFile)) {
    $content = file_get_contents($pendingFile);
    $pendingOrders = json_decode($content, true) ?: [];
}

// 統計數據
$totalAmount = 0;
$todayAmount = 0;
$todayCount = 0;
$today = date('Y-m-d');

foreach ($transactions as $t) {
    $totalAmount += $t['amount'] ?? 0;
    if (isset($t['createdAt']) && strpos($t['createdAt'], $today) === 0) {
        $todayAmount += $t['amount'] ?? 0;
        $todayCount++;
    }
}

// 分頁處理
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$totalPages = ceil(count($transactions) / $perPage);
$offset = ($page - 1) * $perPage;
$currentTransactions = array_slice($transactions, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>後台管理 - Echo Payment</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body {
            cursor: auto !important;
            padding: 0;
            display: block;
            min-height: 100vh;
        }
        body::before, body::after {
            opacity: 0.3;
        }
        .admin-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .admin-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            color: var(--text-primary);
            letter-spacing: 3px;
        }
        .logout-btn {
            background: rgba(255, 68, 102, 0.2);
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: rgba(255, 68, 102, 0.3);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.2);
        }
        .stat-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 36px;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-left: 15px;
            border-left: 3px solid var(--primary-color);
        }
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table th {
            background: rgba(0, 212, 255, 0.1);
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .data-table tr:hover {
            background: rgba(0, 212, 255, 0.05);
        }
        .data-table td {
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success {
            background: rgba(0, 255, 136, 0.2);
            color: var(--success-color);
        }
        .badge-pending {
            background: rgba(255, 170, 0, 0.2);
            color: var(--warning-color);
        }
        .badge-credit {
            background: rgba(0, 212, 255, 0.2);
            color: var(--primary-color);
        }
        .badge-atm {
            background: rgba(0, 255, 204, 0.2);
            color: var(--accent-color);
        }
        .badge-store {
            background: rgba(255, 170, 0, 0.2);
            color: var(--warning-color);
        }
        .amount-cell {
            color: var(--success-color) !important;
            font-weight: 600;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .page-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .page-btn:hover, .page-btn.active {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(0, 212, 255, 0.1);
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .refresh-btn {
            background: rgba(0, 212, 255, 0.2);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            transition: all 0.3s ease;
            margin-left: 15px;
        }
        .refresh-btn:hover {
            background: rgba(0, 212, 255, 0.3);
        }
        .error-alert {
            background: rgba(255, 68, 102, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        @media screen and (max-width: 768px) {
            .admin-wrapper {
                padding: 15px;
            }
            .table-container {
                overflow-x: auto;
            }
            .data-table th,
            .data-table td {
                padding: 10px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- 登入表單 -->
<div class="login-container">
    <div class="form-container">
        <h2><span class="title-text">管理員登陸</span></h2>
        
        <?php if (isset($loginError)): ?>
        <div class="error-alert"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label for="password" class="form-label">管理員密碼</label>
            <input type="password" id="password" name="password" placeholder="請輸入密碼" required autofocus>
            <button type="submit" class="btn-epic">登入</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- 後台主頁 -->
<div class="admin-wrapper">
    <nav class="admin-nav">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="../../image/Logo.png" alt="迴響電競 Logo" style="height: 40px;">
            <h1>迴響電競訂單後台</h1>
        </div>
        <div>
            <button class="refresh-btn" onclick="location.reload()">🔄 重新整理</button>
            <a href="?logout=1" class="logout-btn">登出</a>
        </div>
    </nav>
    
    <!-- 統計卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($transactions); ?></div>
            <div class="stat-label">總交易筆數</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($totalAmount); ?></div>
            <div class="stat-label">總交易金額</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $todayCount; ?></div>
            <div class="stat-label">今日交易</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($todayAmount); ?></div>
            <div class="stat-label">今日金額</div>
        </div>
    </div>
    
    <!-- 待處理 ATM 訂單 -->
    <?php if (!empty($pendingOrders)): ?>
    <h2 class="section-title">待處理 ATM 訂單</h2>
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
    <h2 class="section-title">交易記錄</h2>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentTransactions as $t): ?>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- 分頁 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="page-btn">← 上一頁</a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
            <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="page-btn">下一頁 →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
