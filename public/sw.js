/*
 * Service Worker：PWA 离线与静态资源缓存。
 * 策略：
 * - 静态资源（build 产物、图标、字体）：cache-first，命中直接返回；
 * - 页面与 Livewire 请求：network-only（业务数据不缓存，避免离线看到脏数据）；
 * - 导航请求失败时回退离线提示页。
 */

const CACHE_NAME = 'crm-static-v1';

// 预缓存离线提示页
const OFFLINE_URL = '/pwa/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 只处理 GET；Livewire 更新/POST/带查询的动态请求直接走网络
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // 跨域请求不处理
    if (url.origin !== self.location.origin) {
        return;
    }

    // 导航请求：网络优先，失败回退离线页
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // 静态资源（按扩展名判断）：cache-first
    const staticExtensions = ['.css', '.js', '.svg', '.png', '.jpg', '.woff2', '.woff', '.ico'];
    const isStatic = staticExtensions.some((ext) => url.pathname.endsWith(ext));
    if (! isStatic) {
        return; // 其余请求（API、Livewire 数据）直接走网络
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(request).then((response) => {
                // 只缓存成功响应
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            });
        })
    );
});
