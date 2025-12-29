<?php
// ============================================
// 迴響電競訂單後台 - 配置文件
// php/admin/config.php
// ============================================

// 管理員密碼（請修改）
define('ADMIN_PASSWORD', 'echo2024admin');

// 資料目錄路徑
define('DATA_DIR', __DIR__ . '/../../data');
define('TRANSACTIONS_FILE', DATA_DIR . '/transactions.json');
define('PENDING_FILE', DATA_DIR . '/pending_atm.json');
define('DELETED_FILE', DATA_DIR . '/deleted_orders.json');

// 分頁設定
define('PER_PAGE', 20);

// 時區設定
date_default_timezone_set('Asia/Taipei');

// 確保資料目錄存在
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Session 設定
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
