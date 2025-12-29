<?php
// ============================================
// Echo Payment System - ATM 虛擬帳號資訊頁面
// atm_redirect.php
// ============================================
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM 虛擬帳號 - Echo Payment</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body {
            cursor: auto !important;
        }
        body::before, body::after {
            display: none;
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
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
    </style>
</head>
<body>
    <div id="copySuccess" class="copy-success">✓ 已複製到剪貼簿</div>
    
    <div class="atm-info-container">
        <h1 class="atm-title">🏧 ATM 虛擬帳號轉帳</h1>
        
        <?php
        // 接收 ATM 資訊
        $merchantTradeNo = $_POST['MerchantTradeNo'] ?? '';
        $tradeAmt = $_POST['TradeAmt'] ?? '';
        $bankCode = $_POST['BankCode'] ?? '';
        $vAccount = $_POST['vAccount'] ?? '';
        $expireDate = $_POST['ExpireDate'] ?? '';
        $customField1 = $_POST['CustomField1'] ?? '';
        
        // 銀行代碼對應表
        $bankNames = [
            '007' => '第一銀行 (007)',
            '822' => '中國信託 (822)',
            '012' => '台北富邦 (012)',
            '013' => '國泰世華 (013)',
            '017' => '兆豐銀行 (017)'
        ];
        
        $bankName = $bankNames[$bankCode] ?? "銀行代碼: {$bankCode}";
        
        if ($merchantTradeNo && $vAccount) {
        ?>
            <div class="atm-info">
                <h3>💳 請使用以下資訊進行轉帳</h3>
                
                <div class="info-row">
                    <span class="info-label">轉入銀行</span>
                    <span class="info-value"><?php echo htmlspecialchars($bankName); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">虛擬帳號</span>
                    <span class="info-value copyable" onclick="copyToClipboard('<?php echo htmlspecialchars($vAccount); ?>')" title="點擊複製">
                        <?php echo htmlspecialchars($vAccount); ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">轉帳金額</span>
                    <span class="info-value">NT$ <?php echo number_format($tradeAmt); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">繳費期限</span>
                    <span class="info-value"><?php echo htmlspecialchars($expireDate); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">訂單編號</span>
                    <span class="info-value copyable" onclick="copyToClipboard('<?php echo htmlspecialchars($merchantTradeNo); ?>')" title="點擊複製">
                        <?php echo htmlspecialchars($merchantTradeNo); ?>
                    </span>
                </div>
            </div>
            
            <div class="important-note">
                <strong>⚠️ 重要提醒</strong>
                <ul>
                    <li>請在期限內完成轉帳，逾期將無法處理</li>
                    <li>轉帳金額必須完全正確</li>
                    <li>完成轉帳後，系統會自動處理</li>
                    <li>如有問題請提供訂單編號聯繫客服</li>
                </ul>
            </div>
            
        <?php
        } else {
        ?>
            <div class="atm-info">
                <h3 style="color: var(--error-color);">❌ 資料接收錯誤</h3>
                <p style="color: var(--text-secondary); margin-top: 15px;">
                    無法取得虛擬帳號資訊，請重新操作或聯繫客服。
                </p>
            </div>
        <?php
        }
        ?>
        
        <a href="../index.html" class="back-button">返回首頁</a>
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
        
        // 添加點擊提示
        document.querySelectorAll('.copyable').forEach(el => {
            el.style.cursor = 'pointer';
            el.addEventListener('mouseenter', () => {
                el.style.textDecoration = 'underline';
            });
            el.addEventListener('mouseleave', () => {
                el.style.textDecoration = 'none';
            });
        });
    </script>
</body>
</html>
