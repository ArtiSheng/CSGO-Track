# CSGO饰品价格追踪系统

这是一个用于追踪CSGO饰品价格和管理库存的网站系统，可以记录、显示和分析个人CSGO饰品的价格走势。

## 系统功能

- 饰品管理：添加、编辑、删除和标记售出饰品
- 价格追踪：自动更新市场价格，记录价格历史
- 饰品详情：支持获取磨损度、贴纸信息和检视链接
- 数据统计：投资总额、当前价值和投资回报率分析
- 图表展示：价格趋势和投资绩效可视化
- 移动适配：完整支持移动端访问和操作
- 离线功能：支持PWA，可在网络不稳定时使用
- 数据同步：自动获取并同步CSGO饰品基础信息

## 系统要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Web服务器：Apache 或 Nginx
- 现代浏览器支持

## 安装步骤

### 1. 获取源代码

通过直接下载或使用Git克隆项目：

```bash
git clone <项目地址> csgo-tracker
cd csgo-tracker
```

### 2. 创建和配置数据库

1. 创建MySQL数据库：

```sql
CREATE DATABASE csgo_skins DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'csgo_user'@'localhost' IDENTIFIED BY '你的安全密码';
GRANT ALL PRIVILEGES ON csgo_skins.* TO 'csgo_user'@'localhost';
FLUSH PRIVILEGES;
```

2. 导入数据库结构：
   - 使用 `create_tables.sql` 创建基础表结构：
   
   ```bash
   mysql -u csgo_user -p csgo_skins < create_tables.sql
   ```
   
   - 或者通过浏览器访问 `install.php` 进行安装

### 3. 获取SteamDT API密钥

本系统使用SteamDT的API接口获取CSGO饰品的基础信息和市场价格。请按照以下步骤获取API密钥：

1. 访问SteamDT官方网站：https://www.steamdt.com

2. 注册并登录您的账号

3. 在个人中心申请API密钥：

4. 审核通过后，您将获得API密钥

5. API使用限制和注意事项：
   - 请注意API的调用频率限制
   - 遵守SteamDT的服务条款和API使用规范
   - API密钥属于敏感信息，请妥善保管，不要泄露

### 4. 配置系统

1. 创建并编辑配置文件：

   创建新的 `config.php` 文件：

```php
<?php
define('DEBUG', false);  // 调试模式，生产环境设为false
define('STEAMDT_API_KEY', '你的API密钥');  // SteamDT API密钥
define('API_BASE_URL', 'https://open.steamdt.com'); // API 基础URL
define('DB_HOST', 'localhost');
define('DB_NAME', 'csgo_skins');
define('DB_USER', 'csgo_user');
define('DB_PASS', '你的安全密码');
?>
```

2. 配置Web服务器：

   **Apache配置示例：**

```apache
<VirtualHost *:80>
    ServerName csgo-tracker.example.com
    DocumentRoot /path/to/csgo-tracker
    
    <Directory /path/to/csgo-tracker>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/csgo_error.log
    CustomLog ${APACHE_LOG_DIR}/csgo_access.log combined
</VirtualHost>
```

   **Nginx配置示例：**

```nginx
server {
    listen 80;
    server_name csgo-tracker.example.com;
    root /path/to/csgo-tracker;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 5. 设置目录权限

确保Web服务器可以写入日志和缓存目录：

```bash
mkdir -p logs cache
chmod 755 logs cache
chown -R www-data:www-data logs cache  # 根据您的Web服务器用户调整
```

### 6. 更新数据库结构（如果需要）

如果是从旧版本升级，可能需要运行数据库更新脚本：

```bash
php update_database.php
php update_stats_db.php
```

### 7. 设置定时任务

添加以下cron任务来定期更新价格、统计数据和基础信息：

```bash
# 每天更新价格
0 0 * * * php /path/to/csgo-tracker/update_all_prices.php > /dev/null 2>&1

# 每天更新统计数据
0 1 * * * php /path/to/csgo-tracker/update_daily_stats.php > /dev/null 2>&1

# 每天更新饰品基础信息
0 2 * * * php /path/to/csgo-tracker/fetch_base_info.php > /dev/null 2>&1
```

## 使用说明

### 1. 基本操作

- **添加饰品**：点击首页的"添加饰品"按钮，输入饰品信息
- **编辑饰品**：点击饰品行中的"编辑"按钮修改信息
- **标记售出**：点击饰品行中的"售出"按钮，填写售出信息
- **删除饰品**：点击饰品行中的"删除"按钮

### 2. 价格管理

- **更新价格**：
  - 单个饰品：点击饰品行中的"更新价格"按钮
  - 批量更新：点击首页顶部的"更新所有价格"按钮

### 3. 过滤和排序

- **过滤功能**：
  - 可按未售出/已售出状态过滤
  - 使用搜索框进行名称搜索

- **排序功能**：
  - 可按名称、购入价格、市场价格、收益率等排序
  - 支持升序/降序切换

### 4. 移动端使用

- 响应式设计，自动适应屏幕尺寸
- 支持手机浏览器添加到主屏幕(PWA功能)

## 高级配置

### 1. 优化数据库性能

对于大量饰品数据，可以添加以下索引优化查询性能：

```sql
ALTER TABLE skins ADD INDEX idx_purchase_date (purchase_date);
ALTER TABLE skins ADD INDEX idx_is_sold (is_sold);
ALTER TABLE price_history ADD INDEX idx_recorded_at (recorded_at);
```

### 2. API接口说明

系统提供以下API接口供外部系统调用：

- `api/add_skin.php` - 添加新饰品
- `api/update_skin.php` - 更新饰品信息
- `api/delete_skin.php` - 删除饰品
- `api/sell_skin.php` - 标记饰品为已售出
- `api/update_sold_skin.php` - 更新已售出饰品信息

系统依赖以下外部API：

- `/open/cs2/v1/base` - 获取CSGO饰品基础信息和marketHashName
- 各种价格查询API - 获取饰品当前市场价格

所有API接口均接受POST请求，返回JSON格式数据。

### 3. 常见问题排查

- **无法连接数据库**：检查config.php中的数据库配置
- **价格更新失败**：确认SteamDT API密钥是否有效
- **页面加载缓慢**：可尝试清理缓存（访问clear_cache.php）
- **基础信息获取失败**：检查API访问权限和网络连接

## 安全建议

1. 更改默认数据库账号和密码
2. 不要将config.php文件公开在网站目录中
3. 定期备份数据库
4. 启用HTTPS加密连接
5. 限制服务器对外开放的端口
6. 定期更新系统和PHP版本

## 技术支持

如遇到技术问题，可以：
1. 查看源代码中的注释说明
2. 提交Issue到项目仓库
3. 联系开发者QQ3447478882获取支持

## 开源许可

本项目采用GNU通用公共许可证v3.0（GPL-3.0）开源许可：

```
Copyright (C) 2023-2024 CSGO饰品价格追踪系统

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
```

使用本软件即表示您同意遵守GPL-3.0许可证的条款。GPL-3.0是一种强制开源许可证，要求任何基于本项目的衍生作品也必须以相同的许可证开源。

完整的许可证文本请参阅项目中的LICENSE文件或访问：https://www.gnu.org/licenses/gpl-3.0.html

---

感谢使用CSGO饰品价格追踪系统！ 