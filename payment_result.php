<?php
// payment_result.php
// 這個頁面接收歐買尬金流的前端通知並顯示結果

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'payment_errors.log');

// 載入配置
$config = [
    'db' => [
        'host' => '220.135.250.156', 
        'port' => '3306',
        'name' => 'l2jmobius_fafurion',
        'user' => 'payment',
        'pass' => 'x4ci3i7q',
        'charset' => 'utf8mb4'
    ],
    'funpoint' => [
        'HashKey' => 'cUHKRU04BaDCprxJ',
        'HashIV' => 'tpYEKUQ8D57JyDo0'
    ]
];

// 記錄開始
$logId = uniqid('RESULT_');
$logData = "[" . date('Y-m-d H:i:s') . "][$logId] 接收到歐買尬金流前端回調\n";
$logData .= "POST數據: " . print_r($_POST, true) . "\n";
$logData .= "GET數據: " . print_r($_GET, true) . "\n";
file_put_contents('funpoint_payment_return.log', $logData, FILE_APPEND);

// 接收回傳的資料
$receivedData = $_POST;

// 如果有資料，驗證檢查碼
if (!empty($receivedData) && isset($receivedData['CheckMacValue'])) {
    $receivedCheckMacValue = $receivedData['CheckMacValue'];
    $tmpData = $receivedData;
    unset($tmpData['CheckMacValue']);

    // 排序參數並計算檢查碼
    ksort($tmpData);
    $checkStr = "HashKey=" . $config['funpoint']['HashKey'];
    foreach ($tmpData as $key => $value) {
        $checkStr .= "&" . $key . "=" . $value;
    }
    $checkStr .= "&HashIV=" . $config['funpoint']['HashIV'];
    $checkStr = urlencode($checkStr);
    $checkStr = strtolower($checkStr);

    // 取代特殊字元
    $checkStr = str_replace('%2d', '-', $checkStr);
    $checkStr = str_replace('%5f', '_', $checkStr);
    $checkStr = str_replace('%2e', '.', $checkStr);
    $checkStr = str_replace('%21', '!', $checkStr);
    $checkStr = str_replace('%2a', '*', $checkStr);
    $checkStr = str_replace('%28', '(', $checkStr);
    $checkStr = str_replace('%29', ')', $checkStr);

    // 計算檢查碼
    $calculatedCheckMacValue = strtoupper(hash('sha256', $checkStr));

    // 驗證檢查碼
    $isValidCheckMac = ($calculatedCheckMacValue === $receivedCheckMacValue);
} else {
    $isValidCheckMac = false;
}

// 獲取交易結果
$isSuccess = isset($receivedData['RtnCode']) && $receivedData['RtnCode'] === '1' && $isValidCheckMac;
$amount = isset($receivedData['TradeAmt']) ? intval($receivedData['TradeAmt']) : 0;
$tradeNo = $receivedData['MerchantTradeNo'] ?? '';

// 從CustomField1或URL參數獲取用戶帳號
$account = $receivedData['CustomField1'] ?? '';
if (empty($account) && isset($_GET['account'])) {
    $account = $_GET['account'];
}

// 如果帳號為空，提早結束
if (empty($account)) {
    $isSuccess = false;
    error_log("[$logId] 賬號為空");
}

// 備註欄位處理
$remark = "歐買尬金流付款 - " . date('Y-m-d H:i:s');

// 如果交易成功，更新資料庫
if ($isSuccess && !empty($account) && $amount > 0) {
    try {
        // 創建數據庫連接
        $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
        
        // 使用事務確保數據一致性
        $pdo->beginTransaction();
        
        // 首先檢查該交易是否已經處理過
        $stmt = $pdo->prepare("SELECT BillID FROM autodonater WHERE TradeNo = :tradeNo");
        $stmt->execute(['tradeNo' => $tradeNo]);
        
        if ($stmt->rowCount() > 0) {
            // 交易已存在，避免重複處理
            error_log("[$logId] 交易已存在: {$tradeNo}");
        } else {
            // 驗證賬號是否存在
            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE login = :account");
            $stmt->execute(['account' => $account]);
            
            if ($stmt->rowCount() == 0) {
                // 賬號不存在
                error_log("[$logId] 賬號不存在: {$account}");
                $isSuccess = false;
            } else {
                // 插入交易記錄 - 與原 SmilePay 回調使用相同的表結構
                $stmt = $pdo->prepare("
                    INSERT INTO autodonater (money, accountID, isSent, Note, TradeNo) 
                    VALUES (:money, :accountID, 1, :note, :tradeNo)
                ");
                
                $stmt->execute([
                    'money' => $amount,
                    'accountID' => $account,
                    'note' => $remark,
                    'tradeNo' => $tradeNo
                ]);
                
                // 記錄成功日誌
                error_log("[$logId] 成功處理歐買尬金流交易: {$tradeNo}, 賬號: {$account}, 金額: {$amount}");
            }
        }
        
        // 提交事務
        $pdo->commit();
        
    } catch (PDOException $e) {
        // 回滾事務
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // 記錄錯誤
        error_log("[$logId] 數據庫錯誤: " . $e->getMessage());
        $isSuccess = false; // 數據庫錯誤時，將交易視為失敗
    }
}

// 記錄結果
error_log("[$logId] 處理結果: " . ($isSuccess ? '成功' : '失敗'));
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>霸權天堂II 贊助結果</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-container {
            text-align: center;
        }
        .countdown {
            margin-top: 10px;
            font-size: 14px;
            color: rgba(158, 255, 250, 0.7);
        }
    </style>
</head>
<body>
<div class="form-container result-container">
    <h2>霸權天堂II 贊助結果</h2>
    
    <?php if ($isSuccess): ?>
    <div class="success-message">
        <div style="font-size: 24px; font-weight: bold; color: #0f0; margin-bottom: 15px;">贊助成功！</div>
        <div class="info-row">帳號: <strong><?php echo htmlspecialchars($account); ?></strong></div>
        <div class="info-row">金額: <strong><?php echo htmlspecialchars($amount); ?> 元</strong></div>
        <div class="info-row">交易編號: <strong><?php echo htmlspecialchars($tradeNo); ?></strong></div>
        <div style="margin-top: 15px;">點數已加入您的帳戶，感謝您的支持！</div>
        <div class="countdown">將在 <span id="counter">5</span> 秒後自動返回首頁</div>
    </div>
    <?php else: ?>
    <div class="error-message">
        <div style="font-size: 24px; font-weight: bold; color: #f00; margin-bottom: 15px;">贊助處理中或發生錯誤</div>
        <?php if (!empty($account)): ?>
        <div class="info-row">帳號: <strong><?php echo htmlspecialchars($account); ?></strong></div>
        <?php endif; ?>
        <div style="margin-top: 15px;">
            您的交易可能正在處理中，或是發生了錯誤。<br>
            請稍後查看您的帳戶點數，如有問題請聯絡客服。
        </div>
    </div>
    <?php endif; ?>
    
    <button class="btn-epic" onclick="window.location.href='index.html'">返回贊助頁面</button>
</div>

<script>
// 自動倒數計時返回
<?php if ($isSuccess): ?>
let countdown = 5;
const counterElement = document.getElementById('counter');

function updateCounter() {
    countdown--;
    if (counterElement) counterElement.textContent = countdown;
    
    if (countdown <= 0) {
        window.location.href = 'index.html';
    } else {
        setTimeout(updateCounter, 1000);
    }
}

// 開始倒數
setTimeout(updateCounter, 1000);
<?php endif; ?>
</script>
    <script>
    // 創建滑鼠跟隨光標
    function createCursor() {
        const cursor = document.createElement('div');
        cursor.classList.add('cursor');
        document.body.appendChild(cursor);

        const follower = document.createElement('div');
        follower.classList.add('cursor-follower');
        document.body.appendChild(follower);

        // 創建光影效果層
        const lightEffect = document.createElement('div');
        lightEffect.classList.add('light-effect');
        document.body.appendChild(lightEffect);

        // 設定滑鼠移動事件
        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;
        let followerX = 0, followerY = 0;

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;

            // 更新光影效果位置
            lightEffect.style.setProperty('--x', mouseX + 'px');
            lightEffect.style.setProperty('--y', mouseY + 'px');

            // 為表單元素添加懸停效果
            const hoveredElement = document.elementFromPoint(mouseX, mouseY);
            if (hoveredElement && (hoveredElement.tagName === 'INPUT' ||
                                  hoveredElement.tagName === 'SELECT' ||
                                  hoveredElement.tagName === 'BUTTON' ||
                                  hoveredElement.classList.contains('btn-epic'))) {
                cursor.style.width = '30px';
                cursor.style.height = '30px';
                cursor.style.backgroundColor = 'rgba(58, 210, 208, 0.4)';

                follower.style.width = '80px';
                follower.style.height = '80px';
            } else {
                cursor.style.width = '20px';
                cursor.style.height = '20px';
                cursor.style.backgroundColor = 'rgba(58, 210, 208, 0.7)';

                follower.style.width = '60px';
                follower.style.height = '60px';
            }
        });

        // 滑鼠點擊效果
        document.addEventListener('mousedown', () => {
            cursor.style.transform = 'translate(-50%, -50%) scale(0.7)';
            follower.style.transform = 'translate(-50%, -50%) scale(0.7)';
        });

        document.addEventListener('mouseup', () => {
            cursor.style.transform = 'translate(-50%, -50%) scale(1)';
            follower.style.transform = 'translate(-50%, -50%) scale(1)';
        });

        // 平滑跟隨效果
        function updateCursorPosition() {
            cursorX += (mouseX - cursorX) * 0.2;
            cursorY += (mouseY - cursorY) * 0.2;
            cursor.style.left = cursorX + 'px';
            cursor.style.top = cursorY + 'px';

            followerX += (mouseX - followerX) * 0.1;
            followerY += (mouseY - followerY) * 0.1;
            follower.style.left = followerX + 'px';
            follower.style.top = followerY + 'px';

            requestAnimationFrame(updateCursorPosition);
        }
        updateCursorPosition();
    }

    // 創建粒子效果
    function createParticles() {
        const particlesContainer = document.createElement('div');
        particlesContainer.classList.add('particles');
        document.body.appendChild(particlesContainer);

        // 創建粒子
        const particlesCount = 30;
        const particles = [];

        for (let i = 0; i < particlesCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');

            // 隨機位置、大小和透明度
            const size = Math.random() * 4 + 1;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.opacity = Math.random() * 0.5 + 0.1;

            // 初始位置
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;
            particle.style.left = x + 'px';
            particle.style.top = y + 'px';

            // 速度和方向
            const speedX = (Math.random() - 0.5) * 0.5;
            const speedY = (Math.random() - 0.5) * 0.5;

            particles.push({
                element: particle,
                x,
                y,
                speedX,
                speedY
            });

            particlesContainer.appendChild(particle);
        }

        // 更新粒子位置
        function updateParticles() {
            particles.forEach(p => {
                // 更新位置
                p.x += p.speedX;
                p.y += p.speedY;

                // 邊界檢查
                if (p.x < 0) p.x = window.innerWidth;
                if (p.x > window.innerWidth) p.x = 0;
                if (p.y < 0) p.y = window.innerHeight;
                if (p.y > window.innerHeight) p.y = 0;

                // 更新DOM
                p.element.style.left = p.x + 'px';
                p.element.style.top = p.y + 'px';
            });

            requestAnimationFrame(updateParticles);
        }

        updateParticles();
    }

    // 為表單輸入框添加光暈效果
    function enhanceFormElements() {
        const inputs = document.querySelectorAll('input, select');
        inputs.forEach(input => {
            const wrapper = document.createElement('div');
            wrapper.classList.add('input-glow');
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            // 添加懸停效果
            wrapper.classList.add('hover-effect');
        });

        // 為按鈕添加懸停效果
        const buttons = document.querySelectorAll('.btn-epic');
        buttons.forEach(button => {
            button.classList.add('hover-effect');
        });

        // 為提示框添加效果
        const tipBoxes = document.querySelectorAll('.tip-box');
        tipBoxes.forEach(box => {
            box.classList.add('hover-effect');
        });
    }

    // 頁面載入時初始化所有效果
    document.addEventListener('DOMContentLoaded', () => {
        createCursor();
        createParticles();
        enhanceFormElements();
    });
    </script>

</body>
</html>