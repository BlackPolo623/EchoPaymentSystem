// ============================================
// Echo Payment System - Main Script
// 設定檔 - 所有配置集中管理
// ============================================

const CONFIG = {
    minAmount: 100,
    // 歐買尬金流設定
    funpoint: {
        MerchantID: "1020977",
        HashKey: "cUHKRU04BaDCprxJ",
        HashIV: "tpYEKUQ8D57JyDo0",
        PaymentApiUrl: "https://payment.funpoint.com.tw/Cashier/AioCheckOut/V5",
        // 請更換為實際網站網址
        ReturnURL: "https://powerful-island-21172.herokuapp.com/php/funpoint_payment_notify.php",
        ClientBackURL: "https://powerful-island-21172.herokuapp.com/index.html",
        OrderResultURL: "https://powerful-island-21172.herokuapp.com/php/payment_result.php",
        // ATM 專用設定
        PaymentInfoURL: "https://powerful-island-21172.herokuapp.com/php/payment_info.php",
        ClientRedirectURL: "https://powerful-island-21172.herokuapp.com/payment_result.php"
    },
    // SmilePay設定
    smilepay: {
        dcvc: "16761",
        paymentUrl: "https://ssl.smse.com.tw/ezpos/mtmk_utf.asp"
    },
    // 單號前綴
    orderPrefix: "echo"
};

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    // 設置事件監聽器
    document.getElementById('submit-btn').addEventListener('click', recordPayment);

    // 輸入驗證 - 即時回饋
    const amountInput = document.getElementById('amount');
    amountInput.addEventListener('input', () => {
        const value = parseInt(amountInput.value);
        if (isNaN(value) || value < CONFIG.minAmount) {
            amountInput.setCustomValidity(`金額不能少於${CONFIG.minAmount}元`);
            amountInput.classList.add('invalid');
        } else {
            amountInput.setCustomValidity('');
            amountInput.classList.remove('invalid');
        }
    });

    // 設置金額快選按鈕（如果存在）
    setupAmountPresets();
});

// 設置金額快選按鈕
function setupAmountPresets() {
    const presetBtns = document.querySelectorAll('.preset-btn');
    const amountInput = document.getElementById('amount');
    
    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.amount;
            amountInput.value = value;
            
            // 移除其他按鈕的 active 狀態
            presetBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // 觸發 input 事件進行驗證
            amountInput.dispatchEvent(new Event('input'));
        });
    });
}

// 產生檢查碼 (SHA256)
function generateCheckMacValue(params) {
    // 移除CheckMacValue參數
    if (params.CheckMacValue) {
        delete params.CheckMacValue;
    }

    // 按照字母順序排序
    const keys = Object.keys(params).sort();

    // 組合字串
    let checkString = "HashKey=" + CONFIG.funpoint.HashKey;
    keys.forEach(key => {
        checkString += "&" + key + "=" + params[key];
    });
    checkString += "&HashIV=" + CONFIG.funpoint.HashIV;

    // 進行 URL encode
    checkString = encodeURIComponent(checkString).toLowerCase();

    // 取代特殊符號
    checkString = checkString.replace(/%20/g, '+')
                            .replace(/%2d/g, '-')
                            .replace(/%5f/g, '_')
                            .replace(/%2e/g, '.')
                            .replace(/%21/g, '!')
                            .replace(/%2a/g, '*')
                            .replace(/%28/g, '(')
                            .replace(/%29/g, ')');

    // 計算SHA256雜湊
    return CryptoJS.SHA256(checkString).toString().toUpperCase();
}

// 主處理函數
async function recordPayment() {
    // 顯示處理中狀態
    const submitBtn = document.getElementById('submit-btn');
    const originalText = submitBtn.innerText;
    submitBtn.innerHTML = '<span class="loading"></span>處理中...';
    submitBtn.disabled = true;

    try {
        const amount = get_money();
        if (!amount) {
            throw new Error(`金額輸入錯誤或是低於最低限制 ${CONFIG.minAmount} 元`);
        }

        const payMethod = document.getElementById('pay_zg').value;

        if (payMethod === 'credit') {
            // 使用歐買尬金流處理信用卡付款
            processFunpointPayment(amount);
        } else if (payMethod === 'ATM') {
            // 使用歐買尬金流處理ATM虛擬帳號轉帳
            processATMPayment(amount);
        } else {
            // 使用SmilePay支付方式處理（便利商店）
            const uniqueId = generateUniqueId();
            const today = new Date();
            const dateStr = today.getFullYear() + '-' +
                          (today.getMonth() + 1).toString().padStart(2, '0') + '-' +
                          today.getDate().toString().padStart(2, '0');
            const remark = CONFIG.orderPrefix + '_' + uniqueId + '_' + dateStr;

            const params = new URLSearchParams({
                Rvg2c: 1,
                Dcvc: CONFIG.smilepay.dcvc,
                Od_sob: 'Echo Payment',
                Amount: amount,
                Email: CONFIG.orderPrefix + uniqueId + '@echo.payment',
                Pay_zg: payMethod,
                Data_id: uniqueId,
                Remark: remark
            });

            window.open(`${CONFIG.smilepay.paymentUrl}?${params.toString()}`, '_blank');
        }
    } catch (error) {
        showErrorMessage(error.message);
    } finally {
        // 恢復按鈕狀態
        submitBtn.innerText = originalText;
        submitBtn.disabled = false;
    }
}

// 處理信用卡付款
function processFunpointPayment(amount) {
    // 生成交易編號 (使用 echo 前綴)
    const merchantTradeNo = CONFIG.orderPrefix.toUpperCase() + generateUniqueId();

    // 生成交易時間 (格式: yyyy/MM/dd HH:mm:ss)
    const now = new Date();
    const merchantTradeDate = now.getFullYear() + "/" +
                           (now.getMonth() + 1).toString().padStart(2, '0') + "/" +
                           now.getDate().toString().padStart(2, '0') + " " +
                           now.getHours().toString().padStart(2, '0') + ":" +
                           now.getMinutes().toString().padStart(2, '0') + ":" +
                           now.getSeconds().toString().padStart(2, '0');

    // 組裝訂單參數
    const params = {
        MerchantID: CONFIG.funpoint.MerchantID,
        MerchantTradeNo: merchantTradeNo,
        MerchantTradeDate: merchantTradeDate,
        PaymentType: "aio",
        TotalAmount: amount,
        TradeDesc: "Echo Payment Service",
        ItemName: "Echo Payment Service",
        ReturnURL: CONFIG.funpoint.ReturnURL,
        ChoosePayment: "Credit",
        ClientBackURL: CONFIG.funpoint.ClientBackURL,
        OrderResultURL: CONFIG.funpoint.OrderResultURL,
        EncryptType: "1",
        CustomField1: merchantTradeNo  // 改為存儲訂單編號
    };

    // 計算檢查碼
    params.CheckMacValue = generateCheckMacValue(params);

    // 設置表單值
    const form = document.getElementById('funpoint-payment-form');
    form.action = CONFIG.funpoint.PaymentApiUrl;

    for (const key in params) {
        if (form.elements[key]) {
            form.elements[key].value = params[key];
        }
    }

    // 提交表單
    form.submit();
}

// 處理ATM付款
function processATMPayment(amount) {
    // 生成交易編號 (使用 echo 前綴)
    const merchantTradeNo = CONFIG.orderPrefix.toUpperCase() + generateUniqueId();

    // 生成交易時間 (格式: yyyy/MM/dd HH:mm:ss)
    const now = new Date();
    const merchantTradeDate = now.getFullYear() + "/" +
                           (now.getMonth() + 1).toString().padStart(2, '0') + "/" +
                           now.getDate().toString().padStart(2, '0') + " " +
                           now.getHours().toString().padStart(2, '0') + ":" +
                           now.getMinutes().toString().padStart(2, '0') + ":" +
                           now.getSeconds().toString().padStart(2, '0');

    // 組裝訂單參數 - ATM 專用
    const params = {
        MerchantID: CONFIG.funpoint.MerchantID,
        MerchantTradeNo: merchantTradeNo,
        MerchantTradeDate: merchantTradeDate,
        PaymentType: "aio",
        TotalAmount: amount,
        TradeDesc: "Echo Payment Service",
        ItemName: "Echo Payment Service",
        ReturnURL: CONFIG.funpoint.ReturnURL,
        ChoosePayment: "ATM",
        ClientBackURL: CONFIG.funpoint.ClientBackURL,
        EncryptType: "1",
        CustomField1: merchantTradeNo,  // 改為存儲訂單編號
        ExpireDate: 3
    };

    // 計算檢查碼
    params.CheckMacValue = generateCheckMacValue(params);

    // 設置表單值
    const form = document.getElementById('funpoint-ATMpayment-form');
    form.action = CONFIG.funpoint.PaymentApiUrl;

    // 清空表單
    Array.from(form.elements).forEach(element => {
        if (element.type === 'hidden') {
            element.value = '';
        }
    });

    // 設置 ATM 參數
    for (const key in params) {
        if (form.elements[key]) {
            form.elements[key].value = params[key];
        }
    }

    // 提交表單
    form.submit();
}

// 取得金額
function get_money() {
    let amount = parseInt(document.getElementById("amount").value);
    if (isNaN(amount) || amount < CONFIG.minAmount) {
        return 0; // 金額無效
    }
    
    let count = 1;
    if (document.getElementById("Count")) {
        count = parseInt(document.getElementById("Count").value);
        if (isNaN(count) || count <= 0) {
            count = 1;
        }
    }
    
    return amount * count;
}

// 生成唯一ID
// 生成唯一ID (限制總長度 16 字元，配合 ECHO 前綴共 20 字元)
function generateUniqueId() {
    const now = new Date();
    // 格式: MMDDHHMMSS (10字元) + 隨機6位
    const dateStr = (now.getMonth() + 1).toString().padStart(2, '0') +
                    now.getDate().toString().padStart(2, '0') +
                    now.getHours().toString().padStart(2, '0') +
                    now.getMinutes().toString().padStart(2, '0') +
                    now.getSeconds().toString().padStart(2, '0');
    const random = Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    return `${dateStr}${random}`;
}

// 顯示錯誤訊息
function showErrorMessage(message) {
    // 檢查是否已有錯誤訊息元素
    let errorDiv = document.querySelector('.error-toast');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-toast';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(255, 68, 102, 0.95), rgba(200, 50, 80, 0.95));
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            z-index: 10000;
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px;
            box-shadow: 0 4px 20px rgba(255, 68, 102, 0.4);
            border: 1px solid rgba(255, 100, 120, 0.5);
            animation: slideDown 0.3s ease;
        `;
        document.body.appendChild(errorDiv);
    }
    
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    
    // 3秒後自動隱藏
    setTimeout(() => {
        errorDiv.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => {
            errorDiv.style.display = 'none';
            errorDiv.style.animation = 'slideDown 0.3s ease';
        }, 300);
    }, 3000);
}

// 添加動畫樣式
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { transform: translateX(-50%) translateY(-100%); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateX(-50%) translateY(0); opacity: 1; }
        to { transform: translateX(-50%) translateY(-100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
