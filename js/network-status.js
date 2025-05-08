/**
 * 网络状态检测脚本
 * 监听网络连接变化并在网络离线时提示用户
 */

(function() {
    // 网络状态检测
    function checkNetworkStatus() {
        const isOnline = navigator.onLine;
        
        if (!isOnline) {
            showOfflineMessage();
        } else {
            hideOfflineMessage();
        }
    }
    
    // 显示离线提示
    function showOfflineMessage() {
        let offlineMessage = document.getElementById('network-status-message');
        
        if (!offlineMessage) {
            offlineMessage = document.createElement('div');
            offlineMessage.id = 'network-status-message';
            offlineMessage.style.position = 'fixed';
            offlineMessage.style.top = '0';
            offlineMessage.style.left = '0';
            offlineMessage.style.right = '0';
            offlineMessage.style.zIndex = '9999';
            offlineMessage.style.background = '#dc3545';
            offlineMessage.style.color = 'white';
            offlineMessage.style.textAlign = 'center';
            offlineMessage.style.padding = '10px';
            offlineMessage.style.fontWeight = 'bold';
            offlineMessage.innerHTML = '网络连接已断开，部分功能可能无法使用。请检查您的网络连接。';
            
            document.body.appendChild(offlineMessage);
        } else {
            offlineMessage.style.display = 'block';
        }
    }
    
    // 隐藏离线提示
    function hideOfflineMessage() {
        const offlineMessage = document.getElementById('network-status-message');
        if (offlineMessage) {
            offlineMessage.style.display = 'none';
        }
    }
    
    // 尝试预加载关键资源
    function preloadCriticalResources() {
        // 使用绝对CDN路径，而不是相对路径
        const resources = [
            'https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js',
            'https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js', 
            'https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css'
        ];
        
        resources.forEach(resource => {
            try {
                const isCSS = resource.endsWith('.css');
                const link = document.createElement(isCSS ? 'link' : 'script');
                
                if (isCSS) {
                    link.rel = 'stylesheet';  // 修改为直接加载样式表而不是预加载
                    link.href = resource;
                } else {
                    link.src = resource;  // 使用src属性而不是href
                    link.async = true;
                }
                
                document.head.appendChild(link);
                console.log('加载资源:', resource);
            } catch (error) {
                console.warn('加载资源失败:', resource, error);
            }
        });
    }
    
    // 初始化网络状态监听
    function initNetworkListeners() {
        window.addEventListener('online', checkNetworkStatus);
        window.addEventListener('offline', checkNetworkStatus);
        
        // 首次检查
        checkNetworkStatus();
    }
    
    // 注册事件监听并尝试加载资源
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initNetworkListeners();
            preloadCriticalResources();
        });
    } else {
        initNetworkListeners();
        preloadCriticalResources();
    }
})(); 