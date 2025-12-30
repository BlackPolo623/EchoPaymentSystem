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
    <link rel="stylesheet" href="../css/bp-style.css">
    <link rel="stylesheet" href="../css/bp-result.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
</body>
</html>
