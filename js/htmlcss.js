// ============================================
// Echo Payment System - Visual Effects
// 滑鼠跟隨光標、粒子效果、表單增強
// ============================================

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
                              hoveredElement.classList.contains('btn-epic') ||
                              hoveredElement.classList.contains('preset-btn'))) {
            cursor.style.width = '30px';
            cursor.style.height = '30px';
            cursor.style.backgroundColor = 'rgba(0, 212, 255, 0.4)';
            cursor.style.boxShadow = '0 0 30px 8px rgba(0, 212, 255, 0.6), 0 0 60px 15px rgba(0, 102, 255, 0.4)';
            
            follower.style.width = '80px';
            follower.style.height = '80px';
        } else {
            cursor.style.width = '20px';
            cursor.style.height = '20px';
            cursor.style.backgroundColor = 'rgba(0, 212, 255, 0.8)';
            cursor.style.boxShadow = '0 0 20px 5px rgba(0, 212, 255, 0.5), 0 0 40px 10px rgba(0, 102, 255, 0.3)';
            
            follower.style.width = '60px';
            follower.style.height = '60px';
        }
    });
    
    // 滑鼠點擊效果
    document.addEventListener('mousedown', () => {
        cursor.style.transform = 'translate(-50%, -50%) scale(0.7)';
        follower.style.transform = 'translate(-50%, -50%) scale(0.7)';
        
        // 創建點擊波紋效果
        createRipple(mouseX, mouseY);
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

// 創建點擊波紋效果
function createRipple(x, y) {
    const ripple = document.createElement('div');
    ripple.style.cssText = `
        position: fixed;
        left: ${x}px;
        top: ${y}px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid rgba(0, 212, 255, 0.8);
        transform: translate(-50%, -50%);
        pointer-events: none;
        z-index: 9997;
        animation: rippleExpand 0.6s ease-out forwards;
    `;
    document.body.appendChild(ripple);
    
    setTimeout(() => ripple.remove(), 600);
}

// 添加波紋動畫
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes rippleExpand {
        0% {
            width: 20px;
            height: 20px;
            opacity: 1;
        }
        100% {
            width: 100px;
            height: 100px;
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

// 創建粒子效果
function createParticles() {
    const particlesContainer = document.createElement('div');
    particlesContainer.classList.add('particles');
    document.body.appendChild(particlesContainer);
    
    // 創建粒子
    const particlesCount = 40;
    const particles = [];
    
    for (let i = 0; i < particlesCount; i++) {
        const particle = document.createElement('div');
        particle.classList.add('particle');
        
        // 隨機位置、大小和透明度
        const size = Math.random() * 4 + 1;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.opacity = Math.random() * 0.5 + 0.1;
        
        // 隨機顏色（藍色系）
        const hue = 190 + Math.random() * 30; // 藍色到青色
        particle.style.background = `hsl(${hue}, 100%, 60%)`;
        particle.style.boxShadow = `0 0 ${size * 2}px hsl(${hue}, 100%, 60%)`;
        
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
            speedY,
            size
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
        // 檢查是否已經有包裝器
        if (input.parentNode.classList.contains('input-glow')) return;
        
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

// 創建科技感裝飾線條
function createDecoLines() {
    const container = document.querySelector('.form-container');
    if (!container) return;
    
    // 創建角落裝飾
    const corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
    corners.forEach(corner => {
        const deco = document.createElement('div');
        deco.className = `corner-deco ${corner}`;
        deco.style.cssText = `
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: rgba(0, 212, 255, 0.5);
            border-style: solid;
            border-width: 0;
            pointer-events: none;
        `;
        
        switch(corner) {
            case 'top-left':
                deco.style.top = '10px';
                deco.style.left = '10px';
                deco.style.borderTopWidth = '2px';
                deco.style.borderLeftWidth = '2px';
                break;
            case 'top-right':
                deco.style.top = '10px';
                deco.style.right = '10px';
                deco.style.borderTopWidth = '2px';
                deco.style.borderRightWidth = '2px';
                break;
            case 'bottom-left':
                deco.style.bottom = '10px';
                deco.style.left = '10px';
                deco.style.borderBottomWidth = '2px';
                deco.style.borderLeftWidth = '2px';
                break;
            case 'bottom-right':
                deco.style.bottom = '10px';
                deco.style.right = '10px';
                deco.style.borderBottomWidth = '2px';
                deco.style.borderRightWidth = '2px';
                break;
        }
        
        container.appendChild(deco);
    });
}

// 頁面載入時初始化所有效果
document.addEventListener('DOMContentLoaded', () => {
    // 檢測是否為移動設備
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (!isMobile) {
        createCursor();
    }
    
    createParticles();
    enhanceFormElements();
    createDecoLines();
});

// 視窗大小改變時重新創建粒子
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        const existingParticles = document.querySelector('.particles');
        if (existingParticles) {
            existingParticles.remove();
            createParticles();
        }
    }, 300);
});
