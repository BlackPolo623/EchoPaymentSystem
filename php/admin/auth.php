<?php
// ============================================
// 迴響電競訂單後台 - 登入驗證
// php/admin/auth.php
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$loginError = null;

// 處理登入
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
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

// 如果未登入且不是登入請求，顯示登入頁面
if (!isLoggedIn() && !isset($_POST['password'])) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-TW">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>管理員登陸 - 迴響電競</title>
        <link rel="stylesheet" href="../../css/style.css">
        <style>
            body {
                cursor: auto !important;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .login-container {
                max-width: 400px;
                width: 90%;
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
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="form-container">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="../../image/Logo.png" alt="迴響電競 Logo" style="max-width: 150px; height: auto;">
                </div>
                <h2><span class="title-text">管理員登陸</span></h2>
                
                <?php if ($loginError): ?>
                <div class="error-alert"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <label for="password" class="form-label">管理員密碼</label>
                    <input type="password" id="password" name="password" placeholder="請輸入密碼" required autofocus>
                    <button type="submit" class="btn-epic">登入</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
