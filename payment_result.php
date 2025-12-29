<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>繳費資訊 - Echo Payment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1a365d 50%, #0d2137 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #0891b2;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .status-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .status-icon.success svg {
            color: #10b981;
        }
        
        .status-icon.error svg {
            color: #ef4444;
        }
        
        .info-card {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        .info-card h2 {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        .info-value.large {
            font-size: 22px;
        }
        
        .copy-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
            transition: background 0.3s;
        }
        
        .copy-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .order-details {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .order-details h3 {
            color: #334155;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #64748b;
        }
        
        .detail-row span:last-child {
            color: #334155;
            font-weight: 500;
        }
        
        .notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .notice h4 {
            color: #b45309;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .notice ul {
            color: #92400e;
            font-size: 13px;
            padding-left: 20px;
        }
        
        .notice li {
            margin-bottom: 5px;
        }
        
        .error-box {
            background: #fef2f2;
            border: 1px solid #ef4444;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .error-box h3 {
            color: #dc2626;
            margin-bottom: 10px;
        }
        
        .error-box p {
            color: #991b1b;
            font-size: 14px;
        }
        
        .back-btn {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(8, 145, 178, 0.4);
        }
        
        .cvs-info {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 25px;
            }
            
            .info-value {
                font-size: 16px;
            }
            
            .info-value.large {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // 接收 POST/GET 資料
        $data = !empty($_POST) ? $_POST : $_GET;
        
        // 記錄 log
        $logFile = __DIR__ . '/php/logs/client_redirect_' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Client Redirect:\n" . print_r($data, true) . "\n", FILE_APPEND);
        
        // 取得參數
        $merchantTradeNo = isset($data['MerchantTradeNo']) ? htmlspecialchars($data['MerchantTradeNo']) : '';
        $rtnCode = isset($data['RtnCode']) ? $data['RtnCode'] : '';
        $rtnMsg = isset($data['RtnMsg']) ? htmlspecialchars($data['RtnMsg']) : '';
        $tradeNo = isset($data['TradeNo']) ? htmlspecialchars($data['TradeNo']) : '';
        $tradeAmt = isset($data['TradeAmt']) ? number_format($data['TradeAmt']) : '';
        $paymentType = isset($data['PaymentType']) ? htmlspecialchars($data['PaymentType']) : '';
        $tradeDate = isset($data['TradeDate']) ? htmlspecialchars($data['TradeDate']) : '';
        $expireDate = isset($data['ExpireDate']) ? htmlspecialchars($data['ExpireDate']) : '';
        
        // ATM 參數
        $bankCode = isset($data['BankCode']) ? htmlspecialchars($data['BankCode']) : '';
        $vAccount = isset($data['vAccount']) ? htmlspecialchars($data['vAccount']) : '';
        
        // CVS 參數
        $paymentNo = isset($data['PaymentNo']) ? htmlspecialchars($data['PaymentNo']) : '';
        
        // 判斷是否成功 (ATM: 2, CVS: 10100073)
        $isSuccess = ($rtnCode == 2 || $rtnCode == 10100073);
        
        // 判斷付款方式
        $isATM = !empty($bankCode);
        $isCVS = !empty($paymentNo);
        
        // 銀行代碼對照
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
            '021' => '花旗銀行',
            '050' => '臺灣企銀',
            '052' => '渣打銀行',
            '053' => '台中銀行',
            '054' => '京城銀行',
            '081' => '匯豐銀行',
            '102' => '華泰銀行',
            '103' => '臺灣新光',
            '108' => '陽信銀行',
            '118' => '板信銀行',
            '147' => '三信銀行',
            '700' => '中華郵政',
            '803' => '聯邦銀行',
            '805' => '遠東銀行',
            '806' => '元大銀行',
            '807' => '永豐銀行',
            '808' => '玉山銀行',
            '809' => '凱基銀行',
            '810' => '星展銀行',
            '812' => '台新銀行',
            '816' => '安泰銀行',
            '822' => '中國信託',
        ];
        
        $bankName = isset($bankNames[$bankCode]) ? $bankNames[$bankCode] : $bankCode;
        ?>
        
        <div class="header">
            <h1>Echo Payment</h1>
            <p>繳費資訊</p>
        </div>
        
        <?php if ($isSuccess): ?>
            <div class="status-icon success">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
            
            <?php if ($isATM): ?>
                <!-- ATM 繳費資訊 -->
                <div class="info-card">
                    <h2>ATM 轉帳資訊</h2>
                    <div class="info-row">
                        <span class="info-label">銀行代碼</span>
                        <span>
                            <span class="info-value"><?php echo $bankCode; ?></span>
                            <span style="font-size:12px;opacity:0.8;">(<?php echo $bankName; ?>)</span>
                            <button class="copy-btn" onclick="copyText('<?php echo $bankCode; ?>')">複製</button>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">轉帳帳號</span>
                        <span>
                            <span class="info-value large"><?php echo $vAccount; ?></span>
                            <button class="copy-btn" onclick="copyText('<?php echo $vAccount; ?>')">複製</button>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">繳費金額</span>
                        <span class="info-value">NT$ <?php echo $tradeAmt; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">繳費期限</span>
                        <span class="info-value"><?php echo $expireDate; ?></span>
                    </div>
                </div>
                
                <div class="notice">
                    <h4>⚠️ ATM 轉帳注意事項</h4>
                    <ul>
                        <li>請於繳費期限內完成轉帳</li>
                        <li>轉帳金額必須與訂單金額完全相符</li>
                        <li>逾期或金額錯誤將無法完成付款</li>
                        <li>每筆訂單僅限繳費一次，請勿重複轉帳</li>
                    </ul>
                </div>
                
            <?php elseif ($isCVS): ?>
                <!-- CVS 繳費資訊 -->
                <div class="info-card cvs-info">
                    <h2>超商繳費資訊</h2>
                    <div class="info-row">
                        <span class="info-label">繳費代碼</span>
                        <span>
                            <span class="info-value large"><?php echo $paymentNo; ?></span>
                            <button class="copy-btn" onclick="copyText('<?php echo $paymentNo; ?>')">複製</button>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">繳費金額</span>
                        <span class="info-value">NT$ <?php echo $tradeAmt; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">繳費期限</span>
                        <span class="info-value"><?php echo $expireDate; ?></span>
                    </div>
                </div>
                
                <div class="notice">
                    <h4>⚠️ 超商繳費注意事項</h4>
                    <ul>
                        <li>請至全台 7-11、全家、萊爾富、OK 超商繳費</li>
                        <li>請於繳費期限內完成繳費</li>
                        <li>請告知店員「代碼繳費」並提供繳費代碼</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="order-details">
                <h3>訂單資訊</h3>
                <div class="detail-row">
                    <span>訂單編號</span>
                    <span><?php echo $merchantTradeNo; ?></span>
                </div>
                <div class="detail-row">
                    <span>交易編號</span>
                    <span><?php echo $tradeNo; ?></span>
                </div>
                <div class="detail-row">
                    <span>訂單時間</span>
                    <span><?php echo $tradeDate; ?></span>
                </div>
                <div class="detail-row">
                    <span>付款方式</span>
                    <span><?php echo $paymentType; ?></span>
                </div>
            </div>
            
        <?php else: ?>
            <div class="status-icon error">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            
            <div class="error-box">
                <h3>取號失敗</h3>
                <p>錯誤代碼：<?php echo $rtnCode; ?></p>
                <p><?php echo $rtnMsg; ?></p>
            </div>
        <?php endif; ?>
        
        <a href="/" class="back-btn">返回首頁</a>
    </div>
    
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('已複製到剪貼簿！');
            }).catch(function() {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('已複製到剪貼簿！');
            });
        }
    </script>
</body>
</html>