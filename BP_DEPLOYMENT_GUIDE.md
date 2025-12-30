# BP 收款系統 - 部署指南

## 📦 文件清單

```
根目錄/
├── bp.html                    ← 主收款頁面
├── js/
│   └── script-bp.js          ← BP 系統 JavaScript
└── php/
    ├── bp_notify.php         ← 通知處理（不記錄）
    ├── bp_result.php         ← 結果頁面
    └── bp_atm_redirect.php   ← ATM 虛擬帳號顯示
```

---

## 🚀 部署步驟

### 1. 上傳文件到 Heroku

```bash
# 1. 複製文件到專案目錄
bp.html                → 根目錄
js/script-bp.js        → js/ 目錄
php/bp_notify.php      → php/ 目錄
php/bp_result.php      → php/ 目錄
php/bp_atm_redirect.php → php/ 目錄

# 2. 提交並推送
git add bp.html js/script-bp.js php/bp_notify.php php/bp_result.php php/bp_atm_redirect.php
git commit -m "Add BP payment system (no recording)"
git push origin main
```

---

### 2. 訪問 BP 系統

```
https://bachuan-3cdbb7d0b6e7.herokuapp.com/bp.html
```

---

## ✨ BP 系統特點

### ✅ 支援功能
- 信用卡付款
- ATM 虛擬帳號轉帳
- 便利商店付款（7-11、全家）
- 付款結果顯示
- ATM 虛擬帳號顯示

### ❌ 不記錄任何資料
- ❌ 不記錄到 transactions.json
- ❌ 不記錄到 pending_atm.json
- ❌ 不寫入任何 log 文件
- ❌ 不出現在後台管理系統

### 🔐 安全特性
- 完整的 CheckMacValue 驗證
- 正常金流處理流程
- 只是不儲存交易記錄

---

## 📋 系統差異對比

| 功能 | Echo 系統 (index.html) | BP 系統 (bp.html) |
|------|------------------------|-------------------|
| 開單收款 | ✅ | ✅ |
| 信用卡 | ✅ | ✅ |
| ATM | ✅ | ✅ |
| 便利商店 | ✅ | ✅ |
| 記錄交易 | ✅ | ❌ |
| 後台顯示 | ✅ | ❌ |
| 訂單前綴 | ECHO | BP |
| Log 記錄 | ✅ | ❌ |

---

## 🔧 自訂設定

### 修改頁面標題

編輯 `bp.html` 第 22 行：
```html
<h2><span class="title-text">你的標題</span></h2>
```

### 修改訂單前綴

編輯 `js/script-bp.js` 第 21 行：
```javascript
orderPrefix: "你的前綴"
```

### 修改最低金額

編輯 `js/script-bp.js` 第 7 行：
```javascript
minAmount: 你的金額,
```

### 修改金額快選按鈕

編輯 `bp.html` 第 29-34 行：
```html
<button type="button" class="preset-btn" data-amount="100">$100</button>
<button type="button" class="preset-btn" data-amount="500">$500</button>
<!-- 自己修改金額 -->
```

---

## 🧪 測試步驟

### 1. 信用卡測試
1. 訪問 https://bachuan-3cdbb7d0b6e7.herokuapp.com/bp.html
2. 選擇「信用卡支付」
3. 輸入金額 100
4. 確認付款
5. 使用測試卡號完成付款
6. 應該跳轉到結果頁面 ✅

### 2. ATM 測試
1. 選擇「虛擬帳號轉帳」
2. 輸入金額 100
3. 確認付款
4. 應該顯示虛擬帳號資訊 ✅

### 3. 驗證不記錄
1. 檢查 Echo 後台：https://bachuan-3cdbb7d0b6e7.herokuapp.com/php/admin/
2. 「待處理 ATM 訂單」應該沒有 BP 開頭的訂單 ✅
3. 「交易記錄」也沒有 BP 開頭的訂單 ✅

---

## 🔒 安全注意事項

### BP 系統的安全性
- ✅ 仍然驗證 CheckMacValue
- ✅ 仍然檢查金流回傳
- ✅ 回應金流平台 1|OK
- ❌ 只是不儲存到你的資料庫

### 建議使用場景
- 臨時收款
- 測試環境
- 不需要記錄的收款
- 第三方代收

### 不建議使用場景
- 需要對帳的交易
- 需要退款的交易
- 需要追蹤的訂單

---

## 📞 技術支援

如有問題：
1. 檢查瀏覽器 Console (F12)
2. 查看 Funpoint 商店後台
3. 確認文件都已正確上傳

---

## 🎯 快速命令

```bash
# 一鍵部署
git add bp.html js/script-bp.js php/bp_*.php
git commit -m "Deploy BP payment system"
git push origin main

# 訪問
open https://bachuan-3cdbb7d0b6e7.herokuapp.com/bp.html
```

---

**部署完成後，你就有兩套獨立的收款系統了！** 🎉
- **Echo 系統**：完整功能 + 後台管理
- **BP 系統**：純收款 + 不記錄
