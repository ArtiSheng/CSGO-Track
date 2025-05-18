/**
 * PWA增强功能
 * 为CSGO饰品追踪系统提供PWA支持
 */

// 在页面加载完成后执行
document.addEventListener('DOMContentLoaded', () => {
    // 初始化PWA功能
    initPwa();
    
    // 处理iOS特定问题
    handleIosSpecifics();
});

/**
 * 初始化PWA功能
 */
function initPwa() {
    // 注册Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker 注册成功:', registration.scope);
            })
            .catch(error => {
                console.error('Service Worker 注册失败:', error);
            });
            
        navigator.serviceWorker.ready.then(registration => {
            console.log('Service Worker就绪');
        });
    }
}

/**
 * 处理iOS特定问题
 */
function handleIosSpecifics() {
    // 检测是否为iOS PWA模式
    if (('standalone' in window.navigator) && window.navigator.standalone) {
        // 修复iOS PWA中的链接问题
        document.addEventListener('click', event => {
            let element = event.target;
            while (element && element.tagName !== 'A') {
                element = element.parentElement;
            }
            
            if (element && element.getAttribute('target') !== '_blank' && element.href) {
                event.preventDefault();
                window.location.href = element.href;
            }
        });
        
        // 启用安全区域填充
        document.body.classList.add('has-safe-area');
        
        // 修复iOS PWA中的滚动问题
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', () => {
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.style.position = '';
                document.body.style.width = '';
            });
        });
    }
} 