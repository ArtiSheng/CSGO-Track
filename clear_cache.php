<?php
// 禁用缓存
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 设置当前时间戳
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="3;url=index.php?v=<?php echo $timestamp; ?>">
    <title>清除缓存</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0d6efd;
        }
        .spinner {
            margin: 20px auto;
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>正在清除浏览器缓存</h1>
        <div class="spinner"></div>
        <p>正在刷新页面和清除缓存，请稍候...</p>
        <p>如果页面没有自动跳转，请<a href="index.php?v=<?php echo $timestamp; ?>">点击这里</a></p>
    </div>
    <script>
        // 清除localStorage
        localStorage.clear();
        
        // 清除sessionStorage
        sessionStorage.clear();
        
        // 用于清除缓存的JS
        window.onload = function() {
            // 强制刷新所有资源
            const links = document.getElementsByTagName('link');
            for (let i = 0; i < links.length; i++) {
                if (links[i].rel === 'stylesheet') {
                    links[i].href = links[i].href + '?v=' + new Date().getTime();
                }
            }
            
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                if (scripts[i].src) {
                    scripts[i].src = scripts[i].src + '?v=' + new Date().getTime();
                }
            }
        };
    </script>
</body>
</html> 