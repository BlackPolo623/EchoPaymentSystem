// 設定檔 - 所有配置集中管理
const CONFIG = {
    minAmount: 500,
    apiEndpoints: {
        accountCheck: 'check_account.php'
    },
    // 歐買尬金流設定
    funpoint: {
        MerchantID: "1020977",
        HashKey: "cUHKRU04BaDCprxJ",
        HashIV: "tpYEKUQ8D57JyDo0",
        PaymentApiUrl: "https://payment.funpoint.com.tw/Cashier/AioCheckOut/V5",
        // 請更換為實際網站網址
        ReturnURL: "https://bachuan-3cdbb7d0b6e7.herokuapp.com/funpoint_payment_notify.php",
        ClientBackURL: "https://bachuan-3cdbb7d0b6e7.herokuapp.com/index.html",
        OrderResultURL: "https://bachuan-3cdbb7d0b6e7.herokuapp.com/payment_result.php",
        // 新增這兩行
        PaymentInfoURL: "https://bachuan-3cdbb7d0b6e7.herokuapp.com/atm_payment_info.php",
        ClientRedirectURL: "https://bachuan-3cdbb7d0b6e7.herokuapp.com/atm_redirect.php"
    },
    // SmilePay設定
    smilepay: {
        dcvc: "16761",
        paymentUrl: "https://ssl.smse.com.tw/ezpos/mtmk_utf.asp"
    }
};

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    // 設置事件監聽器
    document.getElementById('submit-btn').addEventListener('click', recordPayment);

    // 輸入驗證 - 即時反饋
    const amountInput = document.getElementById('amount');
    amountInput.addEventListener('input', () => {
        const value = parseInt(amountInput.value);
        if (isNaN(value) || value < CONFIG.minAmount) {
            amountInput.setCustomValidity(`金額不能少於${CONFIG.minAmount}元`);
        } else {
            amountInput.setCustomValidity('');
        }
    });
});

// 檢查賬號是否存在於數據庫中
async function checkAccountExists(account) {
    try {
        if (!account || account.trim() === '') {
            return false;
        }

        const formData = new FormData();
        formData.append('account', account);
        const response = await fetch(CONFIG.apiEndpoints.accountCheck, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`伺服器回應錯誤: ${response.status}`);
        }

        const data = await response.json();
        return data.exists;
    } catch (error) {
        console.error('檢查賬號時出錯:', error);
        alert(`系統錯誤: ${error.message}`);
        return false;
    }
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
    submitBtn.innerText = '處理中...';
    submitBtn.disabled = true;

    try {
        const account = document.getElementById("account").value.trim();
        if (!account) {
            throw new Error('請輸入帳號');
        }

        // 檢查賬號是否存在
        const accountExists = await checkAccountExists(account);
        if (!accountExists) {
            throw new Error('帳號不存在，請確認帳號是否正確');
        }

        const amount = get_money();
        if (!amount) {
            throw new Error('金額輸入錯誤或是低於最低限制');
        }

        const payMethod = document.getElementById('pay_zg').value;

        if (payMethod === 'credit') {
             // 使用歐買尬金流處理信用卡付款
             processFunpointPayment(account, amount);
        } else if (payMethod === 'ATM') {
             // 使用歐買尬金流處理ATM虛擬帳號轉帳
             processATMPayment(account, amount);
        } else {
            // 使用SmilePay支付方式處理
            const uniqueId = generateUniqueId();
            const today = new Date();
            const dateStr = today.getFullYear() + '-' +
                          (today.getMonth() + 1).toString().padStart(2, '0') + '-' +
                          today.getDate().toString().padStart(2, '0');
            const remark = account + '_' + dateStr;

            const params = new URLSearchParams({
                Rvg2c: 1,
                Dcvc: CONFIG.smilepay.dcvc,
                Od_sob: 'Servicebuyout',
                Amount: amount,
                Email: account,
                Pay_zg: payMethod,
                Data_id: uniqueId,
                Remark: remark
            });

            window.open(`${CONFIG.smilepay.paymentUrl}?${params.toString()}`, '_blank');
        }
    } catch (error) {
        alert(error.message);
    } finally {
        // 恢復按鈕狀態
        submitBtn.innerText = originalText;
        submitBtn.disabled = false;
    }
}

function processFunpointPayment(account, amount) {
    // 生成交易編號 (需確保不重複)
    const merchantTradeNo = "HE" + generateUniqueId();

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
            TradeDesc: "腳本開發服務",
            ItemName: "腳本開發服務",
            ReturnURL: CONFIG.funpoint.ReturnURL,
            ChoosePayment: "Credit",
            ClientBackURL: CONFIG.funpoint.ClientBackURL,
            OrderResultURL: CONFIG.funpoint.OrderResultURL,
            EncryptType: "1",
            ExpireDate: 3,
            CustomField1: account
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

function processATMPayment(account, amount) {
    // 生成交易編號 (需確保不重複)
    const merchantTradeNo = "HE" + generateUniqueId();

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
        TradeDesc: "腳本開發服務",
        ItemName: "腳本開發服務",
        ReturnURL: CONFIG.funpoint.ReturnURL,
        ChoosePayment: "ATM", // 固定為 ATM
        ClientBackURL: CONFIG.funpoint.ClientBackURL,
        OrderResultURL: CONFIG.funpoint.OrderResultURL,
        EncryptType: "1",
        CustomField1: account,
        // ATM 專用參數
        ExpireDate: 3,
        PaymentInfoURL: CONFIG.funpoint.PaymentInfoURL,
        ClientRedirectURL: CONFIG.funpoint.ClientRedirectURL,
        NeedExtraPaidInfo: "Y"
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

function generateUniqueId() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `${timestamp}${random}`;
}