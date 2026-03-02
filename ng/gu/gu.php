<?php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>白子的个人主页</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #e3f2fd;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background-color: #bbdefb;
            color: #0d47a1;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 24px;
            color: #0d47a1;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #64b5f6;
        }
        .section p {
            font-size: 16px;
            color: #424242;
            line-height: 1.8;
        }
        .skills {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .skill-tag {
            background-color: #e3f2fd;
            color: #0d47a1;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .payment {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin-top: 20px;
        }
        .payment-item {
            text-align: center;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .payment-item img {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            object-fit: cover;
        }
        .payment-item p {
            margin-top: 10px;
            font-size: 14px;
            color: #424242;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            position: relative;
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        .modal.show .modal-content {
            transform: scale(1);
        }
        .modal-content img {
            max-width: 100%;
            max-height: 80vh;
            display: block;
            margin: 0 auto;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #0d47a1;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }
        .footer {
            background-color: #f5f5f5;
            padding: 20px 40px;
            font-size: 14px;
            color: #616161;
            line-height: 1.6;
        }
        @media (max-width: 600px) {
            .header h1 {
                font-size: 24px;
            }
            .content {
                padding: 20px;
            }
            .payment {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>白子的个人主页</h1>
            <p>16岁，热爱编程与技术</p>
        </div>
        <div class="content">
            <div class="section">
                <h2>关于我</h2>
                <p>大家好，我是白子，今年16岁，目前已经开始工作啦。我对编程和技术有着浓厚的兴趣，喜欢探索和学习新的东西。</p>
            </div>
            <div class="section">
                <h2>我的技能</h2>
                <div class="skills">
                    <span class="skill-tag">PHP</span>
                    <span class="skill-tag">Python</span>
                    <span class="skill-tag">JavaScript</span>
                    <span class="skill-tag">Node.js</span>
                    <span class="skill-tag">Linux</span>
                </div>
            </div>
            <div class="section">
                <h2>项目说明</h2>
                <p>本API管理系统是基于霄欧API管理系统二次开发的。</p>
            </div>
            <div class="section">
                <h2>赞助支持</h2>
                <p>赞助时请备注你的QQ号。</p>
                <div class="payment">
                    <div class="payment-item" onclick="openModal('/assets/images/zu/wx.png')">
                        <img src="/assets/images/zu/wx.png" alt="微信收款码">
                        <p>微信</p>
                    </div>
                    <div class="payment-item" onclick="openModal('/assets/images/zu/zfb.png')">
                        <img src="/assets/images/zu/zfb.png" alt="支付宝收款码">
                        <p>支付宝</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer">
            <p>免责声明：本系统仅供学习和研究使用，严禁用于任何商业或非法用途。使用本系统所产生的一切后果由使用者自行承担，本人不承担任何法律责任。</p>
        </div>
    </div>

    <div class="modal" id="imageModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">×</button>
            <img id="modalImage" src="" alt="收款码大图">
        </div>
    </div>

    <script>
        function openModal(imgSrc) {
            const modal = document.getElementById('imageModal');
            document.getElementById('modalImage').src = imgSrc;
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }
        function closeModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    </script>
</body>
</html>