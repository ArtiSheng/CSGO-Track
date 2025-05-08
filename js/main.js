// 添加资源加载错误处理
(function() {
    // 检查jQuery是否加载成功
    if (typeof window.jQuery === 'undefined') {
        // 创建脚本元素动态加载jQuery
        var script = document.createElement('script');
        script.src = 'https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js';
        script.onload = function() {
            // 加载Bootstrap
            if (typeof bootstrap === 'undefined') {
                loadBootstrap();
            }
            
            // jQuery加载成功后修复移动端按钮显示问题
            fixMobileButtons();
        };
        script.onerror = function() {
            alert('资源加载失败，请检查网络连接或刷新页面重试');
        };
        document.head.appendChild(script);
    } else {
        // jQuery已存在，直接优化移动端按钮
        $(document).ready(function() {
            fixMobileButtons();
        });
    }
    
    // 修复移动端按钮显示
    function fixMobileButtons() {
        if (typeof $ !== 'undefined') {
            // 检测设备宽度
            const isMobile = window.innerWidth <= 576;
            
            if (isMobile) {
                // 确保按钮文字不会换行
                $('.btn').css({
                    'white-space': 'nowrap',
                    'overflow': 'hidden',
                    'text-overflow': 'ellipsis'
                });
                
                // 优化按钮间距
                $('.btn-group .btn').css('margin-right', '1px');
                
                // 确保排序下拉按钮显示正确
                $('#sortDropdown').css({
                    'width': '100%',
                    'text-align': 'center'
                });
                
                // 按钮组特殊处理
                if ($('.mobile-controls').length > 0) {
                    $('.mobile-controls > div').css({
                        'width': '100%',
                        'margin-bottom': '8px'
                    });
                }
            }
        }
    }
    
    // 加载Bootstrap
    function loadBootstrap() {
        var script = document.createElement('script');
        script.src = 'https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js';
        script.onload = function() {
            // 重新初始化UI组件
            initUIComponents();
        };
        script.onerror = function() {
            // 失败处理
        };
        document.head.appendChild(script);
    }
    
    // 初始化UI组件
    function initUIComponents() {
        if (typeof bootstrap !== 'undefined') {
            // 初始化下拉菜单
            var dropdownElements = document.querySelectorAll('.dropdown-toggle');
            for (var i = 0; i < dropdownElements.length; i++) {
                new bootstrap.Dropdown(dropdownElements[i]);
            }
            
            // 初始化模态框
            var modalElements = document.querySelectorAll('.modal');
            for (var i = 0; i < modalElements.length; i++) {
                new bootstrap.Modal(modalElements[i]);
            }
        }
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // 添加饰品表单提交处理
    const addSkinForm = document.getElementById('addSkinForm');
    if (addSkinForm) {
        addSkinForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 显示加载状态
            const submitBtn = document.querySelector('#addSkinForm button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = '处理中...';
            
            // 获取表单数据
            const formData = new FormData(this);
            
            // 发送请求
            fetch('api/add_skin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('操作失败: ' + error.message);
            })
            .finally(() => {
                // 恢复按钮状态
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // 添加可点击卡片的处理
    document.querySelectorAll('.clickable-card').forEach(function(card) {
        card.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
        
        // 添加鼠标悬停效果
        card.style.cursor = 'pointer';
        card.addEventListener('mouseover', function() {
            this.style.opacity = '0.9';
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 20px rgba(0,0,0,0.2)';
        });
        
        card.addEventListener('mouseout', function() {
            this.style.opacity = '1';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // 查看预览图
    document.querySelectorAll('.view-inspect').forEach(function(button) {
        button.addEventListener('click', function() {
            const skinId = this.getAttribute('data-id');
            loadInspectImages(skinId);
        });
    });

    // 查看价格历史 (修改为跳转到SteamDT)
    document.querySelectorAll('.view-history').forEach(function(button) {
        button.addEventListener('click', function() {
            const skinId = this.getAttribute('data-id');
            const marketHashName = this.getAttribute('data-hash-name');
            
            if (marketHashName) {
                // 直接跳转到SteamDT网站
                const steamdtUrl = `https://steamdt.com/cs2/${encodeURIComponent(marketHashName)}`;
                window.open(steamdtUrl, '_blank');
            } else {
                alert('无法获取饰品的市场Hash名称');
            }
        });
    });

    // 删除饰品
    document.querySelectorAll('.delete-skin').forEach(function(button) {
        button.addEventListener('click', function() {
            if (confirm('确定要删除这个饰品吗？此操作无法撤销。')) {
                const skinId = this.getAttribute('data-id');
                
                // 发送AJAX请求删除饰品
                fetch('api/delete_skin.php?id=' + skinId, {
                    method: 'POST'  // 使用POST而不是DELETE，以确保参数正确传递
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('删除成功');
                        location.reload();
                    } else {
                        alert('删除失败：' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });

    // 页面加载完成后更新统计面板
    updateStatistics();

    // 磨损度更新按钮点击事件
    $(document).on('click', '.update-float', function(e) {
        e.preventDefault();
        
        const skinId = $(this).data('id');
        const button = $(this);
        
        // 修改按钮状态
        button.prop('disabled', true).text('更新中...');
        
        // 记录重试次数
        const retryCount = parseInt(button.data('retry-count') || 0);
        
        // 如果重试次数超过3次，显示特殊提示
        if (retryCount >= 3) {
            showAlert('warning', '多次重试仍然失败，检视链接可能已过期，请尝试更新检视链接');
        }
        
        // 发送AJAX请求
        $.ajax({
            url: 'api/update_float.php',
            type: 'POST',
            data: { skin_id: skinId },
            dataType: 'json',
            timeout: 30000, // 增加超时时间到30秒
            success: function(response) {
                console.log('更新磨损度响应:', response);
                
                if (response.success) {
                    if (response.async) {
                        showAlert('info', '正在异步获取磨损度，请稍后刷新页面查看结果');
                        // 修改按钮状态为等待异步结果
                        setTimeout(() => {
                            button.prop('disabled', false)
                                  .text('刷新结果')
                                  .removeClass('btn-warning')
                                  .addClass('btn-info');
                            
                            // 添加刷新页面的点击处理
                            button.off('click').on('click', function() {
                                location.reload();
                                return false;
                            });
                        }, 2000);
                    } else {
                        // 更新成功，显示磨损度并移除按钮
                        button.closest('td').html(Number(response.float_value).toFixed(8));
                        showAlert('success', '磨损度更新成功');
                    }
                } else {
                    let errorMsg = response.message || '更新磨损度失败';
                    
                    // 对特定错误提供更有用的提示
                    if (errorMsg.includes('系统异常')) {
                        errorMsg = 'API服务器暂时不可用，请稍后再试';
                    } else if (errorMsg.includes('过期') || errorMsg.includes('无效')) {
                        errorMsg = '检视链接可能已过期，请更新检视链接';
                    }
                    
                    // 如果是异步响应错误，提供相应的处理方式
                    if (response.error_type === 'api_async_response') {
                        errorMsg = '当前无法立即获取磨损度。请先使用"更新检视链接"功能更新检视链接，然后重试。';
                        // 在按钮旁增加一个更新检视链接的快捷按钮
                        const skinId = button.data('id');
                        button.after(' <a href="update_inspect_url.php?id=' + skinId + '" class="btn btn-sm btn-secondary">更新检视链接</a>');
                    }
                    
                    showAlert('danger', errorMsg);
                    
                    // 记录重试次数
                    button.data('retry-count', retryCount + 1);
                    
                    // 修改按钮状态
                    button.prop('disabled', false).text('重试');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX错误:', status, error);
                
                let errorMsg = '请求失败';
                if (status === 'timeout') {
                    errorMsg = '请求超时，服务器响应时间过长';
                } else if (xhr.status === 0) {
                    errorMsg = '网络连接失败，请检查网络连接';
                } else if (xhr.status >= 500) {
                    errorMsg = '服务器错误 (' + xhr.status + ')，请稍后重试';
                } else if (xhr.status >= 400) {
                    errorMsg = '请求错误 (' + xhr.status + ')，请刷新页面后重试';
                }
                
                showAlert('danger', errorMsg);
                
                // 记录重试次数
                button.data('retry-count', retryCount + 1);
                
                // 修改按钮状态
                button.prop('disabled', false).text('重试');
            }
        });
    });
});

// 更新统计面板
function updateStatistics() {
    try {
        let totalInvestment, totalValue, totalProfit, roi;
        
        // 如果有直接从PHP传来的数据，优先使用
        if (typeof statisticsData !== 'undefined') {
            console.log('使用PHP直接传递的统计数据');
            totalInvestment = statisticsData.totalInvestment;
            totalValue = statisticsData.totalCurrentValue;
            totalProfit = statisticsData.totalProfit;
            roi = statisticsData.totalChangePercent;
        } else {
            // 否则从DOM中获取数据
            console.log('从DOM中获取统计数据');
            const totalInvestmentElement = document.querySelector('tfoot td:nth-child(3) strong');
            const totalValueElement = document.querySelector('tfoot td:nth-child(5) strong');
            const totalProfitElement = document.querySelector('tfoot td:nth-child(7) strong');
            
            if (!totalInvestmentElement || !totalValueElement || !totalProfitElement) {
                console.error('找不到统计数据元素');
                return;
            }
            
            console.log('投资总额原始文本:', totalInvestmentElement.innerText);
            console.log('当前总值原始文本:', totalValueElement.innerText);
            console.log('总盈亏原始文本:', totalProfitElement.innerText);
            
            // 处理所有货币符号和逗号
            totalInvestment = parseFloat(totalInvestmentElement.innerText.replace('¥', '').replace(/,/g, ''));
            totalValue = parseFloat(totalValueElement.innerText.replace('¥', '').replace(/,/g, ''));
            const totalProfitText = totalProfitElement.innerText;
            totalProfit = parseFloat(totalProfitText.replace('+', '').replace(/,/g, ''));
            
            // 计算ROI
            roi = totalInvestment > 0 ? ((totalValue - totalInvestment) / totalInvestment * 100) : 0;
        }
        
        console.log('统计数据:', {
            totalInvestment: totalInvestment,
            totalValue: totalValue,
            totalProfit: totalProfit,
            roi: roi
        });
        
        // 更新统计面板
        document.getElementById('total-investment').innerText = formatNumber(totalInvestment);
        document.getElementById('total-value').innerText = formatNumber(totalValue);
        document.getElementById('total-profit').innerText = (totalProfit >= 0 ? '+' : '') + formatNumber(totalProfit);
        document.getElementById('roi').innerText = (roi >= 0 ? '+' : '') + formatNumber(roi) + '%';
        
        // 设置盈亏卡片颜色
        const profitCard = document.getElementById('profit-card');
        const roiCard = document.getElementById('roi-card');
        
        if (totalProfit > 0) {
            profitCard.classList.add('text-white', 'bg-success');
            profitCard.classList.remove('bg-danger');
        } else if (totalProfit < 0) {
            profitCard.classList.add('text-white', 'bg-danger');
            profitCard.classList.remove('bg-success');
        } else {
            profitCard.classList.remove('text-white', 'bg-success', 'bg-danger');
        }
        
        if (roi > 0) {
            roiCard.classList.add('text-white', 'bg-success');
            roiCard.classList.remove('bg-danger');
        } else if (roi < 0) {
            roiCard.classList.add('text-white', 'bg-danger');
            roiCard.classList.remove('bg-success');
        } else {
            roiCard.classList.remove('text-white', 'bg-success', 'bg-danger');
        }
    } catch (error) {
        console.error('更新统计面板时出错:', error);
    }
}

// 格式化数字为两位小数的字符串
function formatNumber(number) {
    return number.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// 显示提示信息
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // 移除现有的提示
    $('.alert').remove();
    
    // 在表单上方显示新提示
    $('#addSkinForm').prepend(alertHtml);
    
    // 5秒后自动关闭提示
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
}

// ========== 动态加载饰品表格 ===========
$(document).ready(function() {
    // 当前参数 - 全局化，方便不同脚本访问
    window.showSold = 'unsold_only'; // unsold_only, sold_only
    window.mergeMode = 'separate';   // separate, merged
    window.sort = 'default';
    window.order = 'desc';
    
    // 初始化下拉菜单
    const sortDropdownButton = document.getElementById('sortDropdown');
    let sortDropdown = new bootstrap.Dropdown(sortDropdownButton);
    
    // 点击排序选项
    $(document).on('click', '.sort-option', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 获取并设置排序参数
        window.sort = $(this).data('sort');
        $('.sort-option').removeClass('active');
        $(this).addClass('active');
        
        // 更新下拉按钮文本
        $('#sortDropdown').text($(this).text());
        
        // 关闭下拉菜单并重新加载数据
        sortDropdown.hide();
        
        // 重新初始化下拉菜单，确保下次点击有效
        setTimeout(() => {
            sortDropdown = new bootstrap.Dropdown(sortDropdownButton);
        }, 100);
        
        loadSkins();
    });
    
    // 处理按钮状态，确保点击后不会消失
    $('.btn').on('mousedown', function() {
        $(this).addClass('btn-pressed');
    }).on('mouseup mouseleave', function() {
        $(this).removeClass('btn-pressed');
    });

    // 按钮事件绑定 - 仅对桌面端生效，移动端事件由responsive.js处理
    if (!document.body.classList.contains('is-mobile-view')) {
        $('#showActiveSkins').on('click', function() {
            window.showSold = 'unsold_only';
            $('#showActiveSkins').addClass('active');
            $('#showSoldSkins').removeClass('active');
            
            // 更新表头和列显示
            $('.skin-header-cell.sold-only, .skin-item-cell.sold-only').addClass('d-none');
            $('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').removeClass('d-none');
            
            // 更新排序菜单选项
            $('.sold-sort').addClass('d-none');
            $('.unsold-sort').removeClass('d-none');
            
            // 重置排序为默认
            if (window.sort === 'soldprice' || window.sort === 'netprice' || window.sort === 'profitrate' || window.sort === 'days' || window.sort === 'solddate' || window.sort === 'fee') {
                window.sort = 'default';
                $('.sort-option').removeClass('active');
                $('.sort-option[data-sort="default"]').addClass('active');
            }
            
            loadSkins();
        });
        
        $('#showSoldSkins').on('click', function() {
            window.showSold = 'sold_only';
            $('#showSoldSkins').addClass('active');
            $('#showActiveSkins').removeClass('active');
            
            // 更新表头和列显示
            $('.skin-header-cell.sold-only, .skin-item-cell.sold-only').removeClass('d-none');
            $('.skin-header-cell.unsold-only, .skin-item-cell.unsold-only').addClass('d-none');
            
            // 更新排序菜单选项
            $('.sold-sort').removeClass('d-none');
            $('.unsold-sort').addClass('d-none');
            
            // 重置排序为默认
            if (window.sort === 'market') {
                window.sort = 'default';
                $('.sort-option').removeClass('active');
                $('.sort-option[data-sort="default"]').addClass('active');
            }
            
            loadSkins();
        });
        
        $('#showSeparate').on('click', function() {
            window.mergeMode = 'separate';
            $('#showSeparate').addClass('active');
            $('#showMerged').removeClass('active');
            loadSkins();
        });
        
        $('#showMerged').on('click', function() {
            window.mergeMode = 'merged';
            $('#showMerged').addClass('active');
            $('#showSeparate').removeClass('active');
            loadSkins();
        });
        
        $('.order-option').on('click', function() {
            window.order = $(this).data('order');
            $('.order-option').removeClass('active');
            $(this).addClass('active');
            loadSkins();
        });
    }

    // 加载饰品数据
    window.loadSkins = function() {
        $('#skin-list').html('<div class="skin-loading">加载中...</div>');
        
        // 添加时间戳，确保不使用缓存
        const timestamp = new Date().getTime();
        
        $.ajax({
            url: 'get_skins.php',
            data: {
                sort: window.sort,
                order: window.order,
                show_sold: window.showSold,
                merge_mode: window.mergeMode,
                _: timestamp  // 添加时间戳防止缓存
            },
            dataType: 'json',
            cache: false,   // 禁用缓存
            success: function(data) {
                if (!data) {
                    $('#skin-list').html('<div class="skin-error">加载失败：返回数据为空</div>');
                    return;
                }
                
                // 检查是否返回了错误对象而不是数组
                if (!Array.isArray(data) && data.success === false) {
                    $('#skin-list').html(`<div class="skin-error">加载失败：${data.message}</div>`);
                    return;
                }
                
                if (Array.isArray(data)) {
                    if (data.length > 0) {
                        // 数据正常
                    } else {
                        if (window.showSold === 'sold_only') {
                            $('#skin-list').html('<div class="skin-empty">暂无已售出饰品</div>');
                        } else {
                            $('#skin-list').html('<div class="skin-empty">暂无饰品数据</div>');
                        }
                        return;
                    }
                } else {
                    $('#skin-list').html('<div class="skin-error">数据格式错误</div>');
                    return;
                }
                
                renderTable(data);
            },
            error: function(xhr, status, error) {
                // 显示更详细的错误信息
                let errorMessage = '加载失败';
                if (xhr.status === 0) {
                    errorMessage = '网络连接失败，请检查网络';
                } else if (xhr.status === 404) {
                    errorMessage = '找不到API文件 (404)';
                } else if (xhr.status === 500) {
                    errorMessage = '服务器错误 (500)';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage += ': ' + response.message;
                        }
                    } catch (e) {
                        if (xhr.responseText) {
                            errorMessage += ' - ' + xhr.responseText.substring(0, 100);
                        }
                    }
                }
                
                $('#skin-list').html(`<div class="skin-error">${errorMessage}</div>`);
            },
            // 添加timeout属性，增加请求超时时间
            timeout: 10000,
            // 添加详细的错误处理
            complete: function(xhr, status) {
                if (status === 'timeout') {
                    $('#skin-list').html(`<div class="skin-error">请求超时，请重试</div>`);
                }
            }
        });
    }

    // 渲染表格
    function renderTable(skins) {
        if (!skins || skins.length === 0) {
            $('#skin-list').html('<div class="skin-empty">暂无数据</div>');
            return;
        }
        
        let html = '';
        skins.forEach(function(skin) {
            let quantity = skin.quantity ? skin.quantity : 1;
            let name = skin.name;
            // 修改数量显示方式，使其在移动端合并模式下也能完整显示
            if (quantity > 1) {
                // 移动端合并模式下的数量显示优化
                if (window.mergeMode === 'merged' && document.body.classList.contains('is-mobile-view')) {
                    name = `<div class="d-flex justify-content-between align-items-center w-100">
                        <span class="skin-name-text">${skin.name}</span>
                        <span class="badge bg-info ms-1">x${quantity}</span>
                    </div>`;
                } else {
                    name += ` <span class='badge bg-info'>x${quantity}</span>`;
                }
            }
            
            // 移除已售出饰品的标记
            let soldBadge = '';
            
            // 格式化购入价格
            let purchasePrice = '-';
            let purchasePriceValue = 0;
            if (typeof skin.purchase_price === 'number' && !isNaN(skin.purchase_price)) {
                purchasePrice = '¥' + skin.purchase_price.toFixed(2);
                purchasePriceValue = skin.purchase_price;
            } else if (skin.purchase_price !== undefined && skin.purchase_price !== null && skin.purchase_price !== '') {
                let pp = parseFloat(skin.purchase_price);
                if (!isNaN(pp)) {
                    purchasePrice = '¥' + pp.toFixed(2);
                    purchasePriceValue = pp;
                }
            }

            // 格式化购入日期
            let purchaseDate = skin.purchase_date || '-';
            // 转换购入日期格式为xx-xx-xx
            if (purchaseDate && purchaseDate !== '-') {
                const dateObj = new Date(purchaseDate);
                if (!isNaN(dateObj.getTime())) {
                    // 格式为 yy-MM-dd
                    const year = dateObj.getFullYear().toString().substr(-2);
                    const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
                    const day = dateObj.getDate().toString().padStart(2, '0');
                    purchaseDate = `${year}-${month}-${day}`;
                }
            }
            
            // 格式化市场价格
            let marketPrice = '-';
            let marketPriceValue = 0;
            if (typeof skin.market_price === 'number' && !isNaN(skin.market_price)) {
                marketPrice = '¥' + skin.market_price.toFixed(2);
                marketPriceValue = skin.market_price;
            } else if (skin.market_price !== undefined && skin.market_price !== null && skin.market_price !== '') {
                let mp = parseFloat(skin.market_price);
                if (!isNaN(mp)) {
                    marketPrice = '¥' + mp.toFixed(2);
                    marketPriceValue = mp;
                }
            }
            
            // 初始化涨跌幅和盈亏
            let priceChange = 0;
            let changeClass = '';
            let priceChangeStr = '0%';
            
            let profit = 0;
            let profitClass = '';
            let profitStr = '¥0.00';
            
            // 初始化售出相关变量
            let soldPrice = '-';
            let soldPriceValue = 0;
            let fee = '-';
            let feeValue = 0;
            let netPrice = '-';
            let netValue = 0;
            let profitRate = 0;
            let profitRateClass = '';
            let profitRateStr = '0%';
            let daysHeld = '0天';
            let soldDate = '-';
            
            // 构建操作按钮
            let actionButtons = '';
            
            // 只有未售出饰品才显示卖出按钮
            if (skin.is_sold != 1) {
                actionButtons += `<button class="btn btn-action btn-sell" data-id="${skin.id}">卖出</button>`;
            }
            
            // 提取skin中的marketHashName字段
            let marketHashName = '';
            if (skin.marketHashName) {
                marketHashName = skin.marketHashName;
            } else if (skin.marketHashName === null || skin.marketHashName === '') {
                // 如果marketHashName为空，则使用name代替
                // 处理为适合作为hash的格式：移除特殊符号，用空格替换为下划线
                marketHashName = name.replace(/[（）\(\)]/g, '')
                                    .replace(/\s+/g, '_')
                                    .replace(/[^\w\s]/g, '');
            }
            
            // 历史按钮 - 使用处理后的marketHashName
            actionButtons += `<button class="btn btn-action btn-history" data-id="${skin.id}" data-hash-name="${marketHashName}">历史</button>`;
            
            // 在合并模式下不显示编辑按钮
            if (!(window.mergeMode === 'merged')) {
                actionButtons += `<button class="btn btn-action btn-edit" data-id="${skin.id}">编辑</button>`;
            }
            
            actionButtons += `<button class="btn btn-action btn-delete" data-id="${skin.id}">删除</button>`;

            // 使用多列表格布局
            html += `<div class="skin-item" data-id="${skin.id}">
                <div class="skin-item-cell skin-item-name">${name}${soldBadge}</div>
                <div class="skin-item-cell skin-item-price">${purchasePrice}</div>`;
                
            // 已售出饰品显示卖出价格和手续费
            if (skin.is_sold == 1) {
                // 格式化卖出价格
                if (typeof skin.sold_price === 'number' && !isNaN(skin.sold_price)) {
                    soldPrice = '¥' + skin.sold_price.toFixed(2);
                    soldPriceValue = skin.sold_price;
                } else if (skin.sold_price !== undefined && skin.sold_price !== null && skin.sold_price !== '') {
                    let sp = parseFloat(skin.sold_price);
                    if (!isNaN(sp)) {
                        soldPrice = '¥' + sp.toFixed(2);
                        soldPriceValue = sp;
                    }
                }
                
                // 格式化手续费
                if (typeof skin.fee === 'number' && !isNaN(skin.fee)) {
                    fee = '¥' + skin.fee.toFixed(2);
                    feeValue = skin.fee;
                } else if (skin.fee !== undefined && skin.fee !== null && skin.fee !== '') {
                    let f = parseFloat(skin.fee);
                    if (!isNaN(f)) {
                        fee = '¥' + f.toFixed(2);
                        feeValue = f;
                    }
                }
                
                // 计算并格式化到手价格
                if (soldPriceValue > 0) {
                    netValue = soldPriceValue - feeValue;
                    netPrice = '¥' + netValue.toFixed(2);
                }
                
                // 使用后端计算的涨跌幅数据
                if (typeof skin.price_change === 'number' && !isNaN(skin.price_change)) {
                    priceChange = skin.price_change;
                    changeClass = priceChange >= 0 ? 'text-success' : 'text-danger';
                    priceChangeStr = (priceChange >= 0 ? '+' : '') + priceChange.toFixed(2) + '%';
                }
                
                if (typeof skin.profit === 'number' && !isNaN(skin.profit)) {
                    profit = skin.profit;
                    profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                    profitStr = (profit >= 0 ? '+¥' : '-¥') + Math.abs(profit).toFixed(2);
                }
                
                // 计算盈亏率 - 使用后端提供的数据
                if (typeof skin.profit_percent === 'number' && !isNaN(skin.profit_percent)) {
                    profitRate = skin.profit_percent;
                    profitRateClass = profitRate >= 0 ? 'text-success' : 'text-danger';
                    profitRateStr = (profitRate >= 0 ? '+' : '') + profitRate.toFixed(2) + '%';
                } else if (typeof skin.actual_return === 'number' && !isNaN(skin.actual_return)) {
                    profitRate = skin.actual_return;
                    profitRateClass = profitRate >= 0 ? 'text-success' : 'text-danger';
                    profitRateStr = (profitRate >= 0 ? '+' : '') + profitRate.toFixed(2) + '%';
                }
                
                // 格式化持有天数
                if (skin.days_held) {
                    daysHeld = skin.days_held + '天';
                }
                
                // 格式化售出日期
                soldDate = skin.sold_date || '-';
                // 转换售出日期格式为xx-xx-xx
                if (soldDate && soldDate !== '-') {
                    const dateObj = new Date(soldDate);
                    if (!isNaN(dateObj.getTime())) {
                        // 格式为 yy-MM-dd
                        const year = dateObj.getFullYear().toString().substr(-2);
                        const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
                        const day = dateObj.getDate().toString().padStart(2, '0');
                        soldDate = `${year}-${month}-${day}`;
                    }
                }
                
                html += `<div class="skin-item-cell skin-item-soldprice sold-only">${soldPrice}</div>
                    <div class="skin-item-cell skin-item-netprice sold-only">${netPrice}</div>
                    <div class="skin-item-cell skin-item-fee sold-only">${fee}</div>
                    <div class="skin-item-cell skin-item-change"><span class="${changeClass}">${priceChangeStr}</span></div>
                    <div class="skin-item-cell skin-item-profitrate sold-only"><span class="${profitRateClass}">${profitRateStr}</span></div>
                    <div class="skin-item-cell skin-item-profit"><span class="${profitClass}">${profitStr}</span></div>
                    <div class="skin-item-cell skin-item-days sold-only">${daysHeld}</div>
                    <div class="skin-item-cell skin-item-date">${purchaseDate}</div>
                    <div class="skin-item-cell skin-item-solddate sold-only">${soldDate}</div>`;
            } else {
                // 处理未售出饰品的涨跌幅
                // 优先使用后端计算的price_change数据
                if (typeof skin.price_change === 'number' && !isNaN(skin.price_change)) {
                    priceChange = skin.price_change;
                } 
                // 如果后端未提供有效的price_change数据，前端自行计算
                else if (purchasePriceValue > 0 && marketPriceValue > 0) {
                    priceChange = ((marketPriceValue - purchasePriceValue) / purchasePriceValue) * 100;
                }
                
                // 设置涨跌幅的显示样式和文本
                changeClass = priceChange >= 0 ? 'text-success' : 'text-danger';
                priceChangeStr = (priceChange >= 0 ? '+' : '') + priceChange.toFixed(2) + '%';
                
                // 处理后端计算的盈亏数据
                if (typeof skin.profit === 'number' && !isNaN(skin.profit)) {
                    profit = skin.profit;
                    profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                    profitStr = (profit >= 0 ? '+¥' : '-¥') + Math.abs(profit).toFixed(2);
                } else if (purchasePriceValue > 0 && marketPriceValue > 0) {
                    // 如果后端没有提供盈亏数据，前端自行计算
                    profit = (marketPriceValue - purchasePriceValue) * quantity;
                    profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                    profitStr = (profit >= 0 ? '+¥' : '-¥') + Math.abs(profit).toFixed(2);
                }
                
                // 未售出饰品
                html += `<div class="skin-item-cell skin-item-soldprice sold-only d-none">-</div>
                    <div class="skin-item-cell skin-item-netprice sold-only d-none">-</div>
                    <div class="skin-item-cell skin-item-fee sold-only d-none">-</div>
                    <div class="skin-item-cell skin-item-market unsold-only">${marketPrice}</div>
                    <div class="skin-item-cell skin-item-change"><span class="${changeClass}">${priceChangeStr}</span></div>
                    <div class="skin-item-cell skin-item-profitrate sold-only d-none">-</div>
                    <div class="skin-item-cell skin-item-profit"><span class="${profitClass}">${profitStr}</span></div>
                    <div class="skin-item-cell skin-item-days sold-only d-none">-</div>
                    <div class="skin-item-cell skin-item-date">${purchaseDate}</div>
                    <div class="skin-item-cell skin-item-solddate sold-only d-none">-</div>`;
            }
            
            html += `<div class="skin-item-cell skin-item-actions">${actionButtons}</div>
            </div>`;
        });
        $('#skin-list').html(html);
        
        // 绑定卖出按钮事件
        $('.btn-sell').off('click').on('click', function(e) {
            e.stopPropagation();  // 防止触发行的点击事件
            const skinId = $(this).data('id');
            const skin = skins.find(s => s.id == skinId);
            
            // 打开卖出模态框
            $('#sellSkinId').val(skinId);
            $('#skinName').val(skin.name);
            $('#sellSkinModal').modal('show');
        });
        
        // 绑定历史按钮事件
        $('.btn-history').off('click').on('click', function(e) {
            e.stopPropagation();
            const skinId = $(this).data('id');
            const skin = skins.find(s => s.id == skinId);
            let hashName = $(this).data('hash-name');
            
            // 如果没有hash-name或者为空，使用饰品名称替代
            if (!hashName || hashName.trim() === '') {
                if (skin && skin.name) {
                    if (skin.marketHashName) {
                        hashName = skin.marketHashName;
                    } else {
                        // 将名称转换为适合URL的格式
                        hashName = skin.name.replace(/[（）\(\)]/g, '')
                                           .replace(/\s+/g, '_')
                                           .replace(/[^\w\s]/g, '');
                    }
                } else {
                    alert('无法获取饰品信息');
                    return;
                }
            }
            
            // 打开SteamDT
            const steamdtUrl = `https://steamdt.com/cs2/${encodeURIComponent(hashName)}`;
            window.open(steamdtUrl, '_blank');
        });
        
        // 绑定编辑按钮事件
        $('.btn-edit').off('click').on('click', function(e) {
            e.stopPropagation();
            const skinId = $(this).data('id');
            const skin = skins.find(s => s.id == skinId);
            
            if (!skin) {
                alert('无法获取饰品信息');
                return;
            }

            // 根据饰品是否已售出打开不同的编辑模态框
            if (skin.is_sold == 1) {
                // 已售出饰品，使用已售出编辑模态框
                $('#editSoldSkinId').val(skin.id);
                $('#editSoldSkinName').val(skin.name);
                $('#editSoldPurchasePrice').val(skin.purchase_price);
                $('#editSoldPurchaseDate').val(skin.purchase_date);
                $('#editSoldPrice').val(skin.sold_price);
                $('#editSoldFee').val(skin.fee || 0);
                $('#editSoldDate').val(skin.sold_date);
                
                $('#editSoldSkinModal').modal('show');
            } else {
                // 未售出饰品，使用常规编辑模态框
                $('#editSkinId').val(skin.id);
                $('#editName').val(skin.name);
                $('#editPurchasePrice').val(skin.purchase_price);
                $('#editPurchaseDate').val(skin.purchase_date);
                $('#editMarketHashName').val(skin.marketHashName || '');
                
                $('#editSkinModal').modal('show');
            }
        });
        
        // 绑定删除按钮事件
        $('.btn-delete').off('click').on('click', function(e) {
            e.stopPropagation();
            const skinId = $(this).data('id');
            const skin = skins.find(s => s.id == skinId);
            
            // 显示确认对话框
            if (confirm(`确定要删除 ${skin.name} 吗？此操作无法撤销。`)) {
                deleteSkin(skinId);
            }
        });
        
        // 行点击事件不再需要
        $('.skin-item').off('click');
    }

    // 删除饰品函数
    function deleteSkin(skinId) {
        fetch('api/delete_skin.php?id=' + skinId, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('删除成功');
                loadSkins();
            } else {
                alert('删除失败：' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // 确认卖出按钮事件
    $('#confirmSellBtn').on('click', function() {
        const skinId = $('#sellSkinId').val();
        const soldPrice = $('#soldPrice').val();
        const soldDate = $('#soldDate').val();
        const fee = $('#fee').val() || 0;
        
        if (!soldPrice) {
            alert('请输入卖出价格');
            return;
        }
        
        const formData = new FormData();
        formData.append('id', skinId);
        formData.append('sold_price', soldPrice);
        formData.append('sold_date', soldDate);
        formData.append('fee', fee);
        
        fetch('api/sell_skin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('卖出成功');
                $('#sellSkinModal').modal('hide');
                loadSkins();
            } else {
                alert('卖出失败: ' + data.message);
            }
        })
        .catch(error => {
            alert('操作失败: ' + error.message);
        });
    });

    // 确认编辑已售出饰品按钮事件
    $('#confirmEditSoldBtn').on('click', function() {
        // 显示加载状态
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> 保存中...');
        
        // 获取表单数据
        const formData = new FormData();
        
        // 获取表单值
        const skinId = $('#editSoldSkinId').val();
        const purchasePrice = $('#editSoldPurchasePrice').val();
        const purchaseDate = $('#editSoldPurchaseDate').val();
        const soldPrice = $('#editSoldPrice').val();
        const fee = $('#editSoldFee').val() || 0;
        const soldDate = $('#editSoldDate').val();
        
        // 验证日期格式
        if (!isValidDate(purchaseDate)) {
            alert('购入日期格式无效，请使用YYYY-MM-DD格式');
            btn.prop('disabled', false).html(originalText);
            return;
        }
        
        if (!isValidDate(soldDate)) {
            alert('卖出日期格式无效，请使用YYYY-MM-DD格式');
            btn.prop('disabled', false).html(originalText);
            return;
        }
        
        // 收集要提交的表单数据
        const formValues = {
            skin_id: skinId,
            purchase_price: purchasePrice,
            purchase_date: purchaseDate,
            sold_price: soldPrice,
            fee: fee,
            sold_date: soldDate
        };
        
        console.log('准备提交的已售出饰品数据:', formValues);
        
        // 添加所有表单字段
        for (const [key, value] of Object.entries(formValues)) {
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        }
        
        // 发送请求
        fetch('api/update_sold_skin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP错误! 状态: ${response.status}`);
            }
            // 先检查返回内容是否为空
            return response.text().then(text => {
                if (!text) {
                    throw new Error('服务器返回了空响应');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('解析JSON失败:', e);
                    console.error('原始响应:', text);
                    throw new Error('无法解析服务器响应: ' + e.message);
                }
            });
        })
        .then(data => {
            if (data.success) {
                alert(data.message || '更新成功');
                $('#editSoldSkinModal').modal('hide');
                loadSkins();
            } else {
                alert('编辑失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            console.error('请求错误:', error);
            alert('操作失败: ' + error.message);
        })
        .finally(() => {
            // 恢复按钮状态
            btn.prop('disabled', false).html(originalText);
        });
    });

    // 辅助函数：验证日期格式
    function isValidDate(dateString) {
        if (!dateString) return false;
        
        // 检查格式是否为YYYY-MM-DD
        const regex = /^\d{4}-\d{2}-\d{2}$/;
        if (!regex.test(dateString)) return false;
        
        // 检查日期是否有效
        const date = new Date(dateString);
        return !isNaN(date.getTime());
    }

    // 确认编辑按钮事件
    $('#confirmEditBtn').on('click', function() {
        // 显示加载状态
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> 保存中...');
        
        // 获取表单数据
        const formData = new FormData();
        
        // 获取表单值
        const skinId = $('#editSkinId').val();
        
        // 收集要提交的表单数据
        const formValues = {
            name: $('#editName').val(),
            purchase_price: $('#editPurchasePrice').val(),
            purchase_date: $('#editPurchaseDate').val(),
            marketHashName: $('#editMarketHashName').val()
        };
        
        console.log('准备提交的表单数据:', formValues);
        console.log('编辑ID:', skinId);
        
        // 添加所有表单字段
        for (const [key, value] of Object.entries(formValues)) {
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        }
        
        // 添加饰品ID
        if (skinId) {
            formData.append('skin_id', skinId);
        } else {
            console.warn('没有指定ID，无法确定要编辑的饰品');
            alert('未指定要编辑的饰品ID');
            btn.prop('disabled', false).html(originalText);
            return;
        }
        
        // 发送请求
        fetch('api/update_skin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // 检查响应是否成功
            if (!response.ok) {
                throw new Error(`HTTP错误! 状态: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                $('#editSkinModal').modal('hide');
                loadSkins();
            } else {
                alert('编辑失败: ' + data.message);
            }
        })
        .catch(error => {
            alert('操作失败: ' + error.message);
        })
        .finally(() => {
            // 恢复按钮状态
            btn.prop('disabled', false).html(originalText);
        });
    });

    // 页面首次加载
    loadSkins();
});
// ========== 动态加载饰品表格 END =========== 