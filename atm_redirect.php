<?php
/**
 * ATM Client端回傳付款相關資訊處理檔案
 * 當 ATM 訂單建立完成後，歐買尬金流會以 Client POST 方式回傳付款資訊並將頁面轉到此頁面
 */
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM 虛擬帳號 - 霸權天堂II</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .atm-info-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .atm-title {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .atm-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .atm-info h3 {
            color: #dc3545;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .info-value {
            color: #dc3545;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }
        
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        
        .back-button {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: transform 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="atm-info-container">
        <h1 class="atm-title">🏧 ATM 虛擬帳號轉帳</h1>
        
        <?php
        // 接收並顯示 ATM 相關資訊
        $merchantTradeNo = $_POST['MerchantTradeNo'] ?? '';
        $paymentType = $_POST['PaymentType'] ?? '';
        $tradeAmt = $_POST['TradeAmt'] ?? '';
        $bankCode = $_POST['BankCode'] ?? '';
        $vAccount = $_POST['vAccount'] ?? '';
        $expireDate = $_POST['ExpireDate'] ?? '';
        $customField1 = $_POST['CustomField1'] ?? '';
        
        // 銀行代碼對應表
        $bankNames = [
            '004' => '臺灣銀行',
            '005' => '土地銀行',
            '006' => '合作金庫',
            '007' => '第一銀行',
            '008' => '華南銀行',
            '009' => '彰化銀行',
            '011' => '上海銀行',
            '012' => '台北富邦',
            '013' => '國泰世華',
            '017' => '兆豐銀行',
            '822' => '中國信託'
        ];
        
        $bankName = $bankNames[$bankCode] ?? "銀行代碼: {$bankCode}";
        
        if ($merchantTradeNo && $vAccount) {
        ?>
            <div class="atm-info">
                <h3>💳 請使用以下資訊進行轉帳</h3>
                
                <div class="info-row">
                    <span class="info-label">轉入銀行：</span>
                    <span class="info-value"><?php echo htmlspecialchars($bankName); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">虛擬帳號：</span>
                    <span class="info-value"><?php echo htmlspecialchars($vAccount); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">轉帳金額：</span>
                    <span class="info-value">NT$ <?php echo number_format($tradeAmt); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">繳費期限：</span>
                    <span class="info-value"><?php echo htmlspecialchars($expireDate); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">訂單編號：</span>
                    <span class="info-value"><?php echo htmlspecialchars($merchantTradeNo); ?></span>
                </div>
                
                <?php if ($customField1): ?>
                <div class="info-row">
                    <span class="info-label">遊戲帳號：</span>
                    <span class="info-value"><?php echo htmlspecialchars($customField1); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="important-note">
                <strong>⚠️ 重要提醒：</strong>
                <ul style="text-align: left; margin: 10px 0;">
                    <li>請在期限內完成轉帳，逾期將無法處理</li>
                    <li>轉帳金額必須完全正確，多轉或少轉都無法自動入帳</li>
                    <li>完成轉帳後，系統會自動處理，請稍待片刻</li>
                    <li>如有問題請聯繫客服並提供訂單編號</li>
                </ul>
            </div>
            
        <?php
        } else {
        ?>
            <div class="atm-info">
                <h3 style="color: #dc3545;">❌ 資料接收錯誤</h3>
                <p>無法取得虛擬帳號資訊，請重新操作或聯繫客服。</p>
            </div>
        <?php
        }
        ?>
        
        <a href="index.html" class="back-button">返回首頁</a>
        
        <script>
            // 自動複製虛擬帳號到剪貼簿（如果瀏覽器支援）
            function copyToClipboard(text) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        console.log('虛擬帳號已複製到剪貼簿');
                    });
                }
            }
            
            // 點擊虛擬帳號可複製
            document.addEventListener('DOMContentLoaded', function() {
                const vAccountElement = document.querySelector('.info-value');
                if (vAccountElement && vAccountElement.textContent.length > 10) {
                    vAccountElement.style.cursor = 'pointer';
                    vAccountElement.title = '點擊複製虛擬帳號';
                    vAccountElement.addEventListener('click', function() {
                        copyToClipboard(this.textContent);
                        this.style.background = '#d4edda';
                        setTimeout(() => {
                            this.style.background = '';
                        }, 1000);
                    });
                }
            });
        </script>
    </div>
</body>
</html>