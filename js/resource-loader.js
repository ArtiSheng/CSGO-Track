/**
 * 资源加载检测和错误处理
 * 该脚本检查关键资源是否正确加载，如果没有，则显示用户友好的错误提示并尝试加载备用资源
 */

(function() {
    // 关键资源列表及其直接加载状态
    const criticalResources = [
        { 
            name: 'jQuery', 
            check: () => typeof window.jQuery !== 'undefined',
            url: 'https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js',
            loaded: false
        },
        { 
            name: 'Bootstrap', 
            check: () => typeof window.bootstrap !== 'undefined',
            url: 'https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js',
            loaded: false
        },
        { 
            name: 'FontAwesome', 
            check: () => document.querySelector('.fa') !== null,
            url: 'https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            loaded: false
        }
    ];
    
    // 从备用CDN直接加载关键资源
    function loadCriticalResources() {
        criticalResources.forEach(resource => {
            if (!resource.check()) {
                console.log(`直接加载关键资源: ${resource.name}`);
                loadResource(resource);
            } else {
                console.log(`资源已加载: ${resource.name}`);
                resource.loaded = true;
            }
        });
        
        // 5秒后检查资源加载情况
        setTimeout(checkResourcesLoaded, 5000);
    }
    
    // 加载单个资源
    function loadResource(resource) {
        const url = resource.url;
        const isCSS = url.endsWith('.css');
        
        let element;
        if (isCSS) {
            element = document.createElement('link');
            element.rel = 'stylesheet';
            element.href = url;
        } else {
            element = document.createElement('script');
            element.src = url;
            element.async = true; // 异步加载不阻塞页面
        }
        
        // 资源加载成功
        element.onload = () => {
            console.log(`成功加载资源: ${resource.name} (${url})`);
            resource.loaded = true;
            
            // Bootstrap加载后初始化UI组件
            if (resource.name === 'Bootstrap' && typeof window.bootstrap !== 'undefined') {
                initUIComponents();
            }
        };
        
        // 资源加载失败
        element.onerror = () => {
            console.error(`资源加载失败: ${resource.name} (${url})`);
            // 如果是关键资源，显示错误提示
            if (['jQuery', 'Bootstrap'].includes(resource.name)) {
                showResourceError([resource.name]);
            }
        };
        
        document.head.appendChild(element);
    }
    
    // 检查资源是否全部加载完成
    function checkResourcesLoaded() {
        const unloadedResources = criticalResources.filter(resource => !resource.loaded && !resource.check());
        
        if (unloadedResources.length > 0) {
            console.warn('部分资源加载失败:', unloadedResources.map(r => r.name).join(', '));
            showResourceError(unloadedResources.map(r => r.name));
            
            // 添加备用功能
            if (unloadedResources.some(r => r.name === 'FontAwesome')) {
                addIconFallbacks();
            }
        }
    }
    
    // 显示资源错误提示
    function showResourceError(resources) {
        // 如果已经显示了错误提示，不再重复显示
        if (document.querySelector('.resource-error')) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'resource-error';
        errorDiv.style.position = 'fixed';
        errorDiv.style.bottom = '10px';
        errorDiv.style.left = '10px';
        errorDiv.style.right = '10px';
        errorDiv.style.background = 'rgba(220, 53, 69, 0.9)';
        errorDiv.style.color = 'white';
        errorDiv.style.padding = '12px 20px';
        errorDiv.style.borderRadius = '4px';
        errorDiv.style.fontSize = '14px';
        errorDiv.style.zIndex = '9999';
        errorDiv.style.display = 'flex';
        errorDiv.style.justifyContent = 'space-between';
        errorDiv.style.alignItems = 'center';
        
        errorDiv.innerHTML = `
            <div>
                <strong>资源加载失败:</strong> 
                ${resources.join(', ')} 未能正确加载，部分功能可能受限。
            </div>
            <button style="background: transparent; border: 1px solid white; color: white; padding: 4px 10px; border-radius: 4px; cursor: pointer;">关闭</button>
        `;
        
        document.body.appendChild(errorDiv);
        
        // 点击关闭按钮隐藏错误提示
        errorDiv.querySelector('button').addEventListener('click', function() {
            errorDiv.style.display = 'none';
        });
        
        // 10秒后自动隐藏
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.style.display = 'none';
            }
        }, 10000);
    }
    
    // 添加备用图标
    function addIconFallbacks() {
        // 替换所有.fa和.fas图标
        document.querySelectorAll('.fa, .fas, .fab, .far').forEach(icon => {
            // 检查是否已经是备用图标
            if (icon.classList.contains('icon-fallback')) return;
            
            // 创建备用图标
            const fallback = document.createElement('span');
            fallback.style.display = 'inline-block';
            fallback.style.width = '16px';
            fallback.style.height = '16px';
            fallback.style.marginRight = '5px';
            fallback.style.textAlign = 'center';
            
            // 根据类名设置不同的内容
            if (icon.classList.contains('fa-plus')) {
                fallback.textContent = '+';
            } else if (icon.classList.contains('fa-sync-alt')) {
                fallback.textContent = '↻';
            } else if (icon.classList.contains('fa-check')) {
                fallback.textContent = '✓';
            } else if (icon.classList.contains('fa-save')) {
                fallback.textContent = '💾';
            } else {
                fallback.textContent = '•';
            }
            
            // 替换图标
            icon.parentNode.insertBefore(fallback, icon);
            icon.style.display = 'none';
        });
    }
    
    // 初始化UI组件
    function initUIComponents() {
        if (typeof window.bootstrap !== 'undefined') {
            console.log('初始化Bootstrap UI组件');
            
            try {
                // 初始化下拉菜单
                document.querySelectorAll('.dropdown-toggle').forEach(element => {
                    new bootstrap.Dropdown(element);
                });
                
                // 初始化模态框
                document.querySelectorAll('.modal').forEach(element => {
                    new bootstrap.Modal(element);
                });
                
                console.log('Bootstrap UI组件初始化完成');
            } catch (error) {
                console.error('初始化Bootstrap UI组件失败:', error);
            }
        }
    }
    
    // 页面加载后执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // 加载关键资源
            loadCriticalResources();
        });
    } else {
        // 页面已加载完成
        loadCriticalResources();
    }
})(); 