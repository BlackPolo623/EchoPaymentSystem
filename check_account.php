<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 設置錯誤日誌
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('error_log', 'app_errors.log');

// 執行時間追蹤
$start_time = microtime(true);

// 載入配置（理想情況下應該放在獨立的配置文件中） 更新
$config = [
    'db' => [
        'host' => '125.228.68.9',
        'port' => '3306',
        'name' => 'l2jmobius_homunculus',
        'user' => 'payment',
        'pass' => 'x4ci3i7q',
        'charset' => 'utf8mb4'
    ]
];

// 檢查是否接收到賬號參數
if (!isset($_POST['account']) || empty($_POST['account'])) {
    sendResponse(false, '賬號不能為空');
    exit;
}

$account = trim($_POST['account']);

// 簡單的輸入驗證
if (strlen($account) < 3 || strlen($account) > 50) {
    sendResponse(false, '賬號長度無效');
    exit;
}

try {
    // 創建數據庫連接
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // 使用真正的預備語句
    ];
    
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);

    // 準備並執行查詢
    $stmt = $pdo->prepare('SELECT login FROM accounts WHERE login = :account LIMIT 1');
    $stmt->execute(['account' => $account]);

    // 檢查是否找到賬號
    $exists = $stmt->rowCount() > 0;

    // 記錄執行時間（僅供參考）
    $execution_time = microtime(true) - $start_time;
    error_log("Account check for '{$account}' took {$execution_time}s");

    sendResponse($exists);
} catch (PDOException $e) {
    // 記錄錯誤
    error_log("Database error in check_account.php: " . $e->getMessage());
    
    // 返回安全的錯誤信息
    sendResponse(false, '數據庫連接錯誤，請稍後再試');
}

/**
 * 發送標準化的 JSON 回應
 *
 * @param bool $exists 賬號是否存在
 * @param string $error 錯誤信息 (可選)
 * @return void
 */
function sendResponse($exists, $error = '') {
    $response = ['exists' => $exists];
    
    if (!empty($error)) {
        $response['error'] = $error;
    }
    
    echo json_encode($response);
    exit;
}
?>