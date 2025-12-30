<?php
// ============================================
// BP Payment System - ATM 虛擬帳號資訊頁面
// bp_atm_redirect.php
// 接收 ClientRedirectURL 回傳的取號資料
// ============================================

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 載入配置
$config = [
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 接收 ATM 資訊
$receivedData = $_POST;
$merchantTradeNo = $receivedData['MerchantTradeNo'] ?? '';
$tradeAmt = $receivedData['TradeAmt'] ?? '';
$bankCode = $receivedData['BankCode'] ?? '';
$vAccount = $receivedData['vAccount'] ?? '';
$expireDate = $receivedData['ExpireDate'] ?? '';
$customField1 = $receivedData['CustomField1'] ?? '';
$rtnCode = $receivedData['RtnCode'] ?? '';
$rtnMsg = $receivedData['RtnMsg'] ?? '';

// 銀行代碼對應表
$bankNames = [
    '004' => '臺灣銀行 (004)',
    '005' => '臺灣土地銀行 (005)',
    '006' => '合作金庫銀行 (006)',
    '007' => '第一銀行 (007)',
    '008' => '華南銀行 (008)',
    '009' => '彰化銀行 (009)',
    '011' => '上海銀行 (011)',
    '012' => '台北富邦 (012)',
    '013' => '國泰世華 (013)',
    '017' => '兆豐銀行 (017)',
    '050' => '臺灣中小企銀 (050)',
    '103' => '臺灣新光銀行 (103)',
    '108' => '陽信銀行 (108)',
    '147' => '三信商業銀行 (147)',
    '803' => '聯邦銀行 (803)',
    '805' => '遠東銀行 (805)',
    '806' => '元大銀行 (806)',
    '807' => '永豐銀行 (807)',
    '808' => '玉山銀行 (808)',
    '809' => '凱基銀行 (809)',
    '812' => '台新銀行 (812)',
    '822' => '中國信託 (822)',
];

$bankName = $bankNames[$bankCode] ?? "銀行代碼: {$bankCode}";

// 判斷取號是否成功 (RtnCode = 2 表示取號成功)
$isSuccess = ($rtnCode === '2');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM 虛擬帳號 - BP Payment</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body {
            cursor: auto !important;
        }
        body::before, body::after {
            opacity: 0.2;
        }
        .atm-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 212, 255, 0.1);
        }
        .atm-title {
            font-family: 'Orbitron', sans-serif;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }
        .success-icon {
            text-align: center;
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounceIn 0.6s ease;
        }
        .atm-info {
            background: rgba(0, 212, 255, 0.05);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin: 25px 0;
        }
        .atm-info h3 {
            color: var(--success-color);
            margin-bottom: 25px;
            font-size: 18px;
            text-align: center;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .info-value {
            color: var(--text-primary);
            font-family: 'Space Mono', monospace;
            font-size: 16px;
            font-weight: bold;
        }
        .copyable {
            cursor: pointer;
            padding: 8px 12px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .copyable:hover {
            background: rgba(0, 212, 255, 0.2);
            transform: translateY(-2px);
        }
        .important-note {
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid var(--warning-color);
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            color: var(--text-secondary);
        }
        .important-note strong {
            color: var(--warning-color);
            display: block;
            margin-bottom: 10px;
        }
        .important-note ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        .important-note li {
            margin: 8px 0;
            line-height: 1.6;
        }
        .back-button {
            display: inline-block;
            width: 100%;
            padding: 15px;
            margin-top: 25px;
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.3), rgba(0, 212, 255, 0.2));
            border: 1px solid var(--primary-color);
            border-radius: 10px;
            color: var(--text-primary);
            text-align: center;
            text-decoration: none;
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .back-button:hover {
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.4), rgba(0, 212, 255, 0.3));
            transform: translateY(-2px);
        }
        .copy-success {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.95), rgba(0, 200, 100, 0.95));
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            z-index: 10000;
            font-family: 'Rajdhani', sans-serif;
            display: none;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 255, 136, 0.4);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
        .error-container {
            text-align: center;
            color: var(--error-color);
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div id="copySuccess" class="copy-success">✓ 已複製到剪貼簿</div>

    <div class="atm-container">
        <?php if ($isSuccess && $merchantTradeNo && $vAccount): ?>
            <div class="success-icon">🏧</div>
            <h1 class="atm-title">ATM 虛擬帳號轉帳</h1>

            <div class="atm-info">
                <h3>💳 請使用以下資訊進行轉帳</h3>

                <div class="info-row">
                    <span class="info-label">轉入銀行</span>
                    <span class="info-value"><?php echo htmlspecialchars($bankName); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">虛擬帳號</span>
                    <span class="info-value copyable" onclick="copyToClipboard('<?php echo htmlspecialchars($vAccount); ?>')" title="點擊複製">
                        <?php echo htmlspecialchars($vAccount); ?> 📋
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">轉帳金額</span>
                    <span class="info-value" style="color: var(--success-color);">NT$ <?php echo number_format($tradeAmt); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">繳費期限</span>
                    <span class="info-value"><?php echo htmlspecialchars($expireDate); ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">訂單編號</span>
                    <span class="info-value copyable" onclick="copyToClipboard('<?php echo htmlspecialchars($merchantTradeNo); ?>')" title="點擊複製">
                        <?php echo htmlspecialchars($merchantTradeNo); ?> 📋
                    </span>
                </div>
            </div>

            <div class="important-note">
                <strong>⚠️ 重要提醒</strong>
                <ul>
                    <li>請在期限內完成轉帳，逾期將無法處理</li>
                    <li>轉帳金額必須完全正確</li>
                    <li>完成轉帳後，系統會自動處理（約需3-5分鐘）</li>
                    <li>如有問題請提供訂單編號聯絡客服</li>
                </ul>
            </div>

        <?php else: ?>
            <div class="error-container">
                <div class="error-icon">⚠</div>
                <h1 class="atm-title" style="color: var(--error-color);">資料接收錯誤</h1>
                <p style="margin-top: 20px; color: var(--text-secondary);">
                    <?php if (!empty($rtnMsg)): ?>
                        錯誤訊息: <?php echo htmlspecialchars($rtnMsg); ?>
                    <?php else: ?>
                        無法取得虛擬帳號資訊，請重新操作或聯絡客服。
                    <?php endif; ?>
                </p>
                <p style="margin-top: 10px; color: var(--text-muted); font-size: 12px;">
                    RtnCode: <?php echo htmlspecialchars($rtnCode); ?>
                </p>
            </div>
        <?php endif; ?>

        <a href="../bp.html" class="back-button">返回首頁</a>
    </div>

    <script>
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                console.error('複製失敗:', err);
            }
            document.body.removeChild(textArea);
        }

        function showCopySuccess() {
            const el = document.getElementById('copySuccess');
            el.style.display = 'block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 2000);
        }
    </script>
    <script src="../js/background-resize.js"></script>
</body>
</html>
