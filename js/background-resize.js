// ============================================
// Echo Payment System - Background Resize
// 背景尺寸自動調整
// ============================================

function adjustBackgroundSize() {
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    const ratio = 1920 / 970; // 原圖比例
    
    let bgWidth, bgHeight;
    
    if (windowWidth / windowHeight > ratio) {
        bgWidth = windowWidth;
        bgHeight = windowWidth / ratio;
    } else {
        bgHeight = windowHeight;
        bgWidth = windowHeight * ratio;
    }
    
    document.documentElement.style.setProperty('--bg-width', `${bgWidth}px`);
    document.documentElement.style.setProperty('--bg-height', `${bgHeight}px`);
}

// 初始調整
adjustBackgroundSize();

// 監聽窗口大小變化
window.addEventListener('resize', adjustBackgroundSize);
