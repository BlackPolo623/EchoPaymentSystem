<?php
// ============================================
// BP Payment System - 通知處理
// bp_notify.php
// 只回應 1|OK，不記錄任何交易資訊
// ============================================

// 設置錯誤日誌（關閉）
ini_set('display_errors', 0);
error_reporting(0);

// 直接回應金流平台
echo '1|OK';
exit;
?>
