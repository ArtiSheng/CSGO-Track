/**
 * 响应式布局增强脚本
 * 主要用于改善CSGO饰品价格追踪网站在移动设备上的显示效果
 */

// 等待DOM和jQuery加载完成
$(document).ready(function() {
    // 初始化响应式功能
    initResponsiveFeatures();
    
    // 当窗口大小变化时重新执行响应式布局
    $(window).on('resize', function() {
        checkScreenSize();
    });
});

/**
 * 初始化所有响应式功能
 */
function initResponsiveFeatures() {
    // 检查屏幕尺寸并应用合适的布局
    checkScreenSize();
    
    // 监听主页切换按钮的显示/隐藏列事件
    listenToColumnVisibilityChanges();
    
    // 监听主表格的渲染完成事件
    listenToTableRender();
    
    // 添加移动端按钮事件处理
    initMobileButtonHandlers();
}

/**
 * 初始化移动端按钮的点击事件处理
 */
function initMobileButtonHandlers() {
    // 监听所有按钮点击事件
    const showActiveSkins = document.getElementById('showActiveSkins');
    const showSoldSkins = document.getElementById('showSoldSkins');
    const showSeparate = document.getElementById('showSeparate');
    const showMerged = document.getElementById('showMerged');
    const sortOptions = document.querySelectorAll('.sort-option');
    const orderOptions = document.querySelectorAll('.order-option');
    
    console.log('初始化移动端按钮事件，是否找到元素：', {
        showActiveSkins: !!showActiveSkins,
        showSoldSkins: !!showSoldSkins,
        showSeparate: !!showSeparate,
        showMerged: !!showMerged,
        sortOptions: sortOptions.length,
        orderOptions: orderOptions.length
    });
    
    // 显示未售出/已售出饰品按钮
    if (showActiveSkins) {
        showActiveSkins.addEventListener('click', function(e) {
            console.log('移动端点击显示未售出按钮');
            // 使用相同的逻辑，但不触发原始按钮事件
            window.showSold = 'unsold_only';
            
            // 更新按钮状态
            showActiveSkins.classList.add('active');
            if (showSoldSkins) showSoldSkins.classList.remove('active');
            
            // 更新表头和列显示
            document.querySelectorAll('.skin-header-cell.sold-only, .skin-item-cell.sold-only').forEach(el => {
                el.classList.add('d-none');
            });
            document.querySelectorAll('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').forEach(el => {
                el.classList.remove('d-none');
            });
            
            // 更新排序菜单选项
            document.querySelectorAll('.sold-sort').forEach(el => {
                el.classList.add('d-none');
            });
            document.querySelectorAll('.unsold-sort').forEach(el => {
                el.classList.remove('d-none');
            });
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    }
    
    if (showSoldSkins) {
        showSoldSkins.addEventListener('click', function(e) {
            console.log('移动端点击显示已售出按钮');
            // 使用相同的逻辑，但不触发原始按钮事件
            window.showSold = 'sold_only';
            
            // 更新按钮状态
            showSoldSkins.classList.add('active');
            if (showActiveSkins) showActiveSkins.classList.remove('active');
            
            // 更新表头和列显示
            document.querySelectorAll('.skin-header-cell.sold-only, .skin-item-cell.sold-only').forEach(el => {
                el.classList.remove('d-none');
            });
            document.querySelectorAll('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').forEach(el => {
                el.classList.add('d-none');
            });
            
            // 更新排序菜单选项
            document.querySelectorAll('.sold-sort').forEach(el => {
                el.classList.remove('d-none');
            });
            document.querySelectorAll('.unsold-sort').forEach(el => {
                el.classList.add('d-none');
            });
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    }
    
    // 单独/合并显示按钮
    if (showSeparate) {
        showSeparate.addEventListener('click', function(e) {
            console.log('移动端点击单独显示按钮');
            // 使用相同的逻辑，但不触发原始按钮事件
            window.mergeMode = 'separate';
            
            // 更新按钮状态
            showSeparate.classList.add('active');
            if (showMerged) showMerged.classList.remove('active');
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    }
    
    if (showMerged) {
        showMerged.addEventListener('click', function(e) {
            console.log('移动端点击合并显示按钮');
            // 使用相同的逻辑，但不触发原始按钮事件
            window.mergeMode = 'merged';
            
            // 更新按钮状态
            showMerged.classList.add('active');
            if (showSeparate) showSeparate.classList.remove('active');
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    }
    
    // 排序选项
    sortOptions.forEach(function(option) {
        option.addEventListener('click', function(e) {
            console.log('移动端点击排序选项:', option.dataset.sort);
            e.preventDefault();
            e.stopPropagation();
            
            // 更新排序参数
            window.sort = option.dataset.sort;
            
            // 更新活跃状态
            sortOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            
            // 更新下拉按钮文本
            const sortDropdown = document.getElementById('sortDropdown');
            if (sortDropdown) {
                sortDropdown.textContent = this.textContent;
            }
            
            // 关闭下拉菜单
            const dropdown = document.querySelector('.dropdown-menu.show');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    });
    
    // 排序顺序按钮
    orderOptions.forEach(function(option) {
        option.addEventListener('click', function(e) {
            console.log('移动端点击排序顺序按钮:', option.dataset.order);
            
            // 更新排序顺序
            window.order = option.dataset.order;
            
            // 更新按钮状态
            orderOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            
            // 调用全局loadSkins函数
            if (typeof window.loadSkins === 'function') {
                window.loadSkins();
            } else {
                console.error('loadSkins函数不存在');
            }
        });
    });
}

/**
 * 更新列可见性状态
 */
function updateColumnVisibility(mode) {
    if (mode === 'sold_only') {
        // 显示已售出列，隐藏未售出列
        document.querySelectorAll('.skin-header-cell.sold-only, .skin-item-cell.sold-only').forEach(el => {
            el.classList.remove('d-none');
        });
        document.querySelectorAll('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').forEach(el => {
            el.classList.add('d-none');
        });
        
        // 更新排序菜单选项
        document.querySelectorAll('.sold-sort').forEach(el => {
            el.classList.remove('d-none');
        });
        document.querySelectorAll('.unsold-sort').forEach(el => {
            el.classList.add('d-none');
        });
    } else {
        // 显示未售出列，隐藏已售出列
        document.querySelectorAll('.skin-header-cell.sold-only, .skin-item-cell.sold-only').forEach(el => {
            el.classList.add('d-none');
        });
        document.querySelectorAll('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').forEach(el => {
            el.classList.remove('d-none');
        });
        
        // 更新排序菜单选项
        document.querySelectorAll('.sold-sort').forEach(el => {
            el.classList.add('d-none');
        });
        document.querySelectorAll('.unsold-sort').forEach(el => {
            el.classList.remove('d-none');
        });
    }
}

/**
 * 重写loadSkins函数，使其在加载后应用移动端优化
 */
function overrideLoadSkinsFunction() {
    // 确保jQuery已加载且loadSkins函数存在
    if (typeof window.jQuery !== 'undefined' && typeof window.loadSkins === 'function') {
        // 保存原始函数引用
        const originalLoadSkinsFunc = window.loadSkins;
        
        // 重写loadSkins函数
        window.loadSkins = function() {
            // 调用原始函数
            originalLoadSkinsFunc.apply(this, arguments);
            
            // 延迟执行以确保DOM已更新
            setTimeout(function() {
                addMobileDataAttributes();
                // 检查当前视图模式
                adaptSoldUnsoldToggle();
            }, 300);
        };
    } else {
        // 如果函数不存在，设置一个延迟检查
        console.log('loadSkins函数未找到，将在页面完全加载后重试');
        setTimeout(overrideLoadSkinsFunction, 500);
    }
}

/**
 * 根据屏幕宽度应用不同的CSS类
 */
function checkScreenSize() {
    const body = document.body;
    const skinListWrapper = document.querySelector('.skin-list-wrapper');
    const mobileControls = document.querySelector('.mobile-controls');
    
    // 获取当前窗口宽度
    const windowWidth = window.innerWidth;
    
    // 记录当前是否为移动视图
    const wasMobileView = body.classList.contains('is-mobile-view');
    
    if (windowWidth <= 480) {
        // 超小屏幕设备 - 启用卡片视图
        body.classList.add('is-mobile-view');
        if (skinListWrapper) {
            skinListWrapper.classList.add('mobile-card-view');
        }
        
        // 确保控制面板保持水平布局
        if (mobileControls) {
            // 防止添加垂直堆叠的类
            mobileControls.classList.remove('d-flex', 'flex-column');
            mobileControls.classList.add('mobile-horizontal-layout');
            
            // 确保包含按钮的容器是水平的
            const buttonContainers = mobileControls.querySelectorAll('.btn-group, .dropdown');
            buttonContainers.forEach(container => {
                container.style.width = 'auto';
                container.style.marginBottom = '4px';
                container.style.marginRight = '4px';
            });
        }
        
        // 适配"已售出"和"未售出"视图
        adaptSoldUnsoldToggle();
        // 为表格添加数据标签
        addMobileDataAttributes();
        
        // 如果之前不是移动视图，现在是移动视图，重新初始化移动端事件处理
        if (!wasMobileView) {
            console.log('从桌面视图切换到移动视图，重新初始化按钮事件');
            initMobileButtonHandlers();
        }
    } else if (windowWidth <= 768) {
        // 小屏幕设备 - 启用移动友好布局
        body.classList.add('is-tablet-view');
        body.classList.remove('is-mobile-view');
        if (skinListWrapper) {
            skinListWrapper.classList.remove('mobile-card-view');
        }
        
        // 平板也保持水平布局
        if (mobileControls) {
            mobileControls.classList.remove('d-flex', 'flex-column');
            mobileControls.classList.add('mobile-horizontal-layout');
        }
        
        // 如果之前是移动视图，现在不是移动视图，重新绑定桌面端事件
        if (wasMobileView) {
            console.log('从移动视图切换到平板视图，刷新页面以确保事件正确绑定');
            // 可选：刷新页面以确保所有事件绑定正确
            // location.reload();
        }
    } else {
        // 桌面设备 - 使用默认布局
        body.classList.remove('is-mobile-view', 'is-tablet-view');
        if (skinListWrapper) {
            skinListWrapper.classList.remove('mobile-card-view');
        }
        
        // 如果之前是移动视图，现在不是移动视图，重新绑定桌面端事件
        if (wasMobileView) {
            console.log('从移动视图切换到桌面视图，刷新页面以确保事件正确绑定');
            // 可选：刷新页面以确保所有事件绑定正确
            // location.reload();
        }
    }
}

/**
 * 监听表格渲染完成事件
 */
function listenToTableRender() {
    // 创建一个MutationObserver来监听DOM变化
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.target.id === 'skin-list') {
                // 表格内容已更新
                // 延迟执行以确保DOM完全更新
                setTimeout(function() {
                    // 检查并应用响应式布局
                    checkScreenSize();
                }, 100);
            }
        });
    });
    
    // 监听skin-list元素的变化
    const skinList = document.getElementById('skin-list');
    if (skinList) {
        observer.observe(skinList, { childList: true });
    }
}

/**
 * 为移动端卡片视图添加数据标签
 */
function addMobileDataAttributes() {
    // 只有在移动视图中才执行
    if (!document.body.classList.contains('is-mobile-view')) {
        return;
    }
    
    console.log("应用移动视图数据属性和样式");
    
    // 获取所有的饰品行
    const skinItems = document.querySelectorAll('.skin-item');
    
    skinItems.forEach(function(item) {
        // 为每个单元格添加数据标签
        const cells = item.querySelectorAll('.skin-item-cell');
        
        cells.forEach(function(cell) {
            // 根据类名确定标签内容
            if (cell.classList.contains('skin-item-price')) {
                cell.setAttribute('data-label', '购入价格');
            } else if (cell.classList.contains('skin-item-soldprice')) {
                cell.setAttribute('data-label', '售出价格');
            } else if (cell.classList.contains('skin-item-netprice')) {
                cell.setAttribute('data-label', '到手价格');
            } else if (cell.classList.contains('skin-item-fee')) {
                cell.setAttribute('data-label', '手续费');
            } else if (cell.classList.contains('skin-item-market')) {
                cell.setAttribute('data-label', '市场价格');
            } else if (cell.classList.contains('skin-item-change')) {
                cell.setAttribute('data-label', '涨跌幅');
            } else if (cell.classList.contains('skin-item-profitrate')) {
                cell.setAttribute('data-label', '盈亏率');
            } else if (cell.classList.contains('skin-item-profit')) {
                cell.setAttribute('data-label', '盈亏');
            } else if (cell.classList.contains('skin-item-days')) {
                cell.setAttribute('data-label', '持有天数');
            } else if (cell.classList.contains('skin-item-date')) {
                cell.setAttribute('data-label', '购入日期');
            } else if (cell.classList.contains('skin-item-solddate')) {
                cell.setAttribute('data-label', '售出日期');
            }
        });
        
        // 特殊处理饰品名称，确保样式统一
        const nameCell = item.querySelector('.skin-item-name');
        if (nameCell) {
            // 确保名称单元格正确显示
            nameCell.style.display = 'block';
            nameCell.style.width = '100%';
            nameCell.style.textAlign = 'left';
            nameCell.style.justifyContent = 'flex-start'; 
            
            // 确保其中的span元素正确对齐
            const nameSpans = nameCell.querySelectorAll('span');
            nameSpans.forEach(span => {
                if (!span.classList.contains('skin-quantity')) {
                    span.style.textAlign = 'left';
                    span.style.display = 'inline-block';
                    span.style.float = 'none';
                }
            });
        }
        
        // 优化操作按钮，使其在移动端更易点击
        const actionCell = item.querySelector('.skin-item-actions');
        if (actionCell) {
            // 移动端水平排列按钮
            if (window.innerWidth <= 480) {
                actionCell.style.display = 'flex';
                actionCell.style.flexDirection = 'row';
                actionCell.style.flexWrap = 'wrap';
                actionCell.style.gap = '4px';
                actionCell.style.justifyContent = 'center';
                
                // 优化按钮样式 - 更小的按钮
                const buttons = actionCell.querySelectorAll('.btn-action');
                buttons.forEach(function(btn) {
                    btn.style.width = 'auto';
                    btn.style.minHeight = '28px';
                    btn.style.minWidth = '45px';
                    btn.style.display = 'flex';
                    btn.style.justifyContent = 'center';
                    btn.style.alignItems = 'center';
                    btn.style.padding = '4px 6px';
                    btn.style.margin = '0 2px';
                    btn.style.fontSize = '12px';
                    btn.style.lineHeight = '1';
                });
            }
        }
    });
}

/**
 * 监听显示/隐藏列的切换按钮
 */
function listenToColumnVisibilityChanges() {
    // 监听"已售出"和"未售出"按钮点击事件
    const showActiveSkins = document.getElementById('showActiveSkins');
    const showSoldSkins = document.getElementById('showSoldSkins');
    
    if (showActiveSkins && showSoldSkins) {
        $(showActiveSkins).on('click', function() {
            // 延迟以确保其他处理完成
            setTimeout(adaptSoldUnsoldToggle, 100);
        });
        
        $(showSoldSkins).on('click', function() {
            // 延迟以确保其他处理完成
            setTimeout(adaptSoldUnsoldToggle, 100);
        });
    }
}

/**
 * 适配"已售出"和"未售出"视图切换
 */
function adaptSoldUnsoldToggle() {
    // 检查是否处于"已售出"视图
    const isSoldView = document.getElementById('showSoldSkins') && 
                      document.getElementById('showSoldSkins').classList.contains('active');
    
    // 在DOM中添加或移除类以适配当前视图
    document.body.classList.toggle('sold-view', isSoldView);
    document.body.classList.toggle('unsold-view', !isSoldView);
    
    // 仅在移动视图中添加数据标签
    if (document.body.classList.contains('is-mobile-view')) {
        // 延迟处理添加数据标签
        setTimeout(addMobileDataAttributes, 100);
    }
} 