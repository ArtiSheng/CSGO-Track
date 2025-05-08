const CACHE_NAME = 'csgotrack-v2';
const CACHE_ASSETS = [
  '/',
  '/index.php',
  '/css/custom.css',
  '/css/codebase.min.css',
  '/css/inspect.css',
  '/js/main.js',
  '/js/pwa.js',
  '/manifest.json',
  '/img/icons/icon-192x192.png',
  '/img/icons/icon-512x512.png',
  '/offline.html',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css',
  'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js'
];

// 安装Service Worker并缓存核心资源
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('缓存应用核心资源');
        return cache.addAll(CACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// 激活Service Worker并清理旧缓存
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(cacheName => {
          return cacheName !== CACHE_NAME;
        }).map(cacheName => {
          return caches.delete(cacheName);
        })
      );
    }).then(() => self.clients.claim())
  );
});

// 网络优先策略: 先尝试网络请求，失败时使用缓存
const networkFirst = async (request) => {
  try {
    // 对动态API请求使用网络优先策略
    const networkResponse = await fetch(request);
    const cache = await caches.open(CACHE_NAME);
    
    // 克隆响应并存入缓存
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // 网络请求失败，使用缓存
    const cachedResponse = await caches.match(request);
    return cachedResponse || caches.match('/offline.html');
  }
};

// 缓存优先策略: 先检查缓存，没有则使用网络
const cacheFirst = async (request) => {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    // 缓存中没有找到，尝试网络
    const networkResponse = await fetch(request);
    const cache = await caches.open(CACHE_NAME);
    
    // 克隆响应并存入缓存
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // 如果请求的是图片，可以返回默认图片
    if (request.destination === 'image') {
      return caches.match('/img/fallback.png');
    }
    
    // 其他请求失败
    return new Response('Network error', { status: 408 });
  }
};

// 拦截请求并根据类型应用不同的缓存策略
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  
  // 忽略浏览器扩展和其他非HTTP请求
  if (
    request.method !== 'GET' || 
    !request.url.startsWith('http') ||
    url.pathname.includes('/api/') || 
    url.pathname.includes('/get_skins.php') ||
    url.pathname.includes('/update_price.php')
  ) {
    return;
  }
  
  // 对静态资源使用缓存优先策略
  if (
    request.url.match(/\.(css|js|woff2|png|jpg|jpeg|gif|svg|ico)$/) ||
    CACHE_ASSETS.includes(request.url)
  ) {
    event.respondWith(cacheFirst(request));
  } else {
    // 对动态内容使用网络优先策略
    event.respondWith(networkFirst(request));
  }
});

// 后台同步功能
self.addEventListener('sync', event => {
  if (event.tag === 'update-prices') {
    event.waitUntil(syncPriceData());
  }
});

// 推送通知支持
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    
    const options = {
      body: data.body || '有新的价格变动',
      icon: '/img/icons/icon-192x192.png',
      badge: '/img/icons/icon-72x72.png',
      vibrate: [100, 50, 100],
      data: {
        url: data.url || '/index.php'
      }
    };
    
    event.waitUntil(
      self.registration.showNotification(
        data.title || 'CSGOTrack 更新', 
        options
      )
    );
  }
});

// 处理通知点击
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientsList => {
      // 查找已打开的窗口并导航
      for (const client of clientsList) {
        if (client.url === event.notification.data.url && 'focus' in client) {
          return client.focus();
        }
      }
      
      // 如果没有打开的窗口，则打开新窗口
      if (clients.openWindow) {
        return clients.openWindow(event.notification.data.url);
      }
    })
  );
});

// 模拟价格数据同步函数
async function syncPriceData() {
  try {
    const response = await fetch('/update_all_prices.php', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      // 避免显示网络连接恢复提示
      cache: 'no-store'
    });
    
    return response.json();
  } catch (error) {
    console.error('同步价格数据失败:', error);
  }
} 