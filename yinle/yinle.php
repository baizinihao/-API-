<?php
/**
 * 白子音乐播放器 PHP 版本
 * 基于原HTML版本改造，保留所有功能
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>白子</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 隐藏滚动条但保留功能 */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* 移动端高度适配 */
        .h-safe-screen {
            height: 100vh;
            height: 100dvh;
        }

        /* 歌词滚动动画 */
        .lyric-item {
            transition: all 0.3s ease;
            opacity: 0.6;
            filter: blur(0.5px);
            transform: scale(0.95);
        }
        .lyric-active {
            opacity: 1;
            font-weight: bold;
            color: #10b981; /* Emerald-500 */
            font-size: 1.25rem;
            filter: blur(0);
            transform: scale(1.05);
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
        }
        
        /* 进度条与音量条样式重置 */
        input[type=range] {
            -webkit-appearance: none; 
            background: transparent; 
            cursor: pointer;
        }
        /* 滑块圆形按钮 */
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background: #ffffff;
            margin-top: -4px; /* 轨道高度4px，(4-12)/2 = -4 */
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
            border: none;
            transition: transform 0.1s;
        }
        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
        /* 滑块轨道 */
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
        }
        
        /* 旋转动画优化 */
        .spin-slow {
            animation: spin 10s linear infinite;
            will-change: transform;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* 心跳动画 */
        .heartbeat {
            animation: beat 1.5s infinite;
        }
        @keyframes beat {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
        }
        
        /* 音量条容器热区优化 */
        .volume-hover-bridge {
            padding-bottom: 20px; /* 增加底部填充，连接图标和滑块 */
            width: 60px; /* 增加宽度，防止鼠标左右滑出 */
        }
    </style>
</head>
<body class="bg-gray-900 text-white h-safe-screen overflow-hidden font-sans select-none">

    <!-- 背景模糊层 -->
    <div id="bg-layer" class="fixed inset-0 bg-cover bg-center transition-all duration-1000 opacity-30 blur-3xl scale-110 z-0" style="background-image: url('https://y.gtimg.cn/mediastyle/global/img/album_300.png');"></div>
    <div class="fixed inset-0 bg-black/60 z-0"></div>

    <!-- 主界面容器 -->
    <div class="relative z-10 flex flex-col h-full max-w-4xl mx-auto p-4">
        
        <!-- 顶部搜索栏 -->
        <div class="flex items-center space-x-2 mb-4 bg-gray-800/50 p-2 rounded-xl backdrop-blur-md border border-white/10 shadow-lg shrink-0">
            <i class="fas fa-search text-gray-400 pl-2"></i>
            <input type="text" id="search-input" class="bg-transparent border-none outline-none flex-1 text-white placeholder-gray-400 px-2 min-w-0" placeholder="搜索歌曲、歌手..." onkeydown="if(event.keyCode===13) searchMusic()">
            <button onclick="searchMusic()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-lg transition text-sm font-medium whitespace-nowrap">搜索</button>
        </div>

        <!-- 中间内容区域 (歌曲列表) -->
        <div class="flex-1 overflow-y-auto no-scrollbar relative flex flex-col" id="main-content">
            <!-- 加载状态 -->
            <div id="loading" class="hidden absolute inset-0 flex items-center justify-center z-20">
                <i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i>
            </div>

            <!-- 空状态/欢迎页 -->
            <div id="welcome" class="flex flex-col items-center justify-center flex-1 text-gray-500 space-y-4">
                <i class="fas fa-music text-6xl opacity-20"></i>
                <p>输入关键词开始探索音乐</p>
                <div class="text-xs text-emerald-500/50 border border-emerald-500/20 px-3 py-1 rounded-full mt-8">
                    <i class="fas fa-bolt mr-1"></i> 由 白子 强力驱动
                </div>
            </div>

            <!-- 歌曲列表 -->
            <div id="song-list" class="space-y-2 pb-6">
                <!-- 列表项模板 (JS生成) -->
            </div>
            
            <!-- 分页控制器 -->
            <div id="pagination" class="hidden flex justify-center items-center space-x-6 py-6 pb-44 text-sm font-medium text-gray-400 select-none">
                <button onclick="changePage(currentPage - 1)" class="hover:text-white disabled:opacity-30 disabled:hover:text-gray-400 transition" id="prev-btn">
                    <i class="fas fa-chevron-left mr-1"></i> 上一页
                </button>
                <span class="bg-white/10 px-3 py-1 rounded-full text-white text-xs">
                    <span id="page-num">1</span> / <span id="total-pages">3</span>
                </span>
                <button onclick="changePage(currentPage + 1)" class="hover:text-white disabled:opacity-30 disabled:hover:text-gray-400 transition" id="next-btn">
                    下一页 <i class="fas fa-chevron-right ml-1"></i>
                </button>
            </div>
        </div>

        <!-- 底部播放控制条 -->
        <div class="absolute bottom-6 sm:bottom-4 left-4 right-4 bg-gray-800/90 backdrop-blur-xl rounded-2xl border border-white/10 shadow-2xl flex flex-col p-3 sm:p-4 z-50 transition-all duration-300 transform translate-y-0" id="player-bar">
            
            <!-- 默认单行歌词显示区 -->
            <div class="text-center mb-2 sm:mb-3 h-5 sm:h-6 overflow-hidden relative group cursor-pointer" onclick="toggleFullLyric()">
                <p id="mini-lyric" class="text-emerald-400 text-xs sm:text-base font-medium transition-all truncate px-4">
                    暂无播放
                </p>
                <div class="absolute inset-0 flex items-center justify-center bg-gray-900/80 opacity-0 group-hover:opacity-100 transition-opacity text-xs text-white rounded">
                    点击展开歌词
                </div>
            </div>

            <div class="flex items-center justify-between">
                <!-- 左侧：歌曲信息 -->
                <div class="flex items-center w-3/12 min-w-0">
                    <div class="relative shrink-0">
                        <img id="cover-img" src="https://y.gtimg.cn/mediastyle/global/img/album_300.png" class="w-10 h-10 sm:w-14 sm:h-14 rounded-full object-cover ring-2 ring-white/10 shadow-lg transition-transform duration-700 block" alt="Cover">
                        <div class="absolute inset-0 m-auto w-2 h-2 sm:w-3 sm:h-3 bg-gray-900 rounded-full border border-gray-700/50"></div>
                    </div>
                    <div class="ml-2 sm:ml-3 overflow-hidden flex flex-col justify-center">
                        <div id="song-title" class="text-xs sm:text-sm font-bold text-white truncate leading-tight">未播放</div>
                        <div id="singer-name" class="text-[10px] sm:text-xs text-gray-400 truncate leading-tight mt-0.5">--</div>
                    </div>
                </div>

                <!-- 中间：控制按钮 -->
                <div class="flex flex-col items-center w-6/12 px-2">
                    <div class="flex items-center space-x-4 sm:space-x-8 mb-1">
                        <button class="text-gray-400 hover:text-white transition p-1" onclick="playPrev()"><i class="fas fa-step-backward text-sm sm:text-lg"></i></button>
                        <button id="play-btn" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-white text-black flex items-center justify-center hover:scale-110 transition shadow-lg shadow-white/10" onclick="togglePlay()">
                            <i class="fas fa-play ml-0.5 text-xs sm:text-sm"></i>
                        </button>
                        <button class="text-gray-400 hover:text-white transition p-1" onclick="playNext()"><i class="fas fa-step-forward text-sm sm:text-lg"></i></button>
                    </div>
                    <!-- 进度条 -->
                    <div class="w-full flex items-center space-x-1 sm:space-x-2 text-[10px] sm:text-xs text-gray-400">
                        <span id="current-time" class="min-w-[30px] text-right">00:00</span>
                        <input type="range" id="progress-bar" value="0" min="0" max="100" step="0.1" class="flex-1 h-1 bg-gray-600 rounded-lg appearance-none cursor-pointer" oninput="seekAudio()">
                        <span id="duration" class="min-w-[30px]">00:00</span>
                    </div>
                </div>

                <!-- 右侧：功能按钮 -->
                <div class="flex items-center justify-end w-3/12 gap-1 sm:gap-3 text-gray-400 pl-1">
                    <!-- 播放模式切换按钮 -->
                    <button class="hover:text-emerald-400 transition p-1.5" onclick="changePlaybackMode()" id="mode-btn" title="顺序播放">
                        <i class="fas fa-list text-sm sm:text-base"></i>
                    </button>
                    
                    <!-- 音量控制 (仅桌面端显示) -->
                    <div class="group relative items-center hidden sm:flex justify-center w-8 h-8">
                        <i class="fas fa-volume-up hover:text-white cursor-pointer p-2"></i>
                        
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 hidden group-hover:flex flex-col items-center volume-hover-bridge z-50">
                            <div class="bg-gray-800 rounded-lg shadow-xl border border-white/10 w-8 h-32 flex items-center justify-center relative">
                                <input type="range" min="0" max="1" step="0.1" value="1" 
                                    class="w-24 h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer absolute" 
                                    style="transform: rotate(-90deg); transform-origin: center;"
                                    oninput="setVolume(this.value)">
                            </div>
                        </div>
                    </div>
                    
                    <a id="download-btn" href="#" target="_blank" class="hover:text-emerald-400 transition p-1.5" title="下载/外链">
                        <i class="fas fa-external-link-alt text-sm sm:text-base"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 全屏歌词遮罩层 -->
    <div id="full-lyric-modal" class="fixed inset-0 z-40 bg-gray-900/95 backdrop-blur-xl transform translate-y-full transition-transform duration-500 flex flex-col">
        <!-- 顶部导航 -->
        <div class="flex justify-between items-center p-6 border-b border-white/5 shrink-0">
            <div class="text-lg font-bold">
                <span id="full-title">歌曲名</span>
                <span class="text-gray-500 text-sm ml-2" id="full-singer">歌手</span>
            </div>
            <button onclick="toggleFullLyric()" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                <i class="fas fa-chevron-down text-white"></i>
            </button>
        </div>

        <!-- 歌词滚动区 -->
        <div class="flex-1 overflow-y-auto no-scrollbar relative p-8 text-center" id="lyric-container">
            <div class="h-[40vh]"></div> <!-- 顶部占位 -->
            <div id="lyric-content" class="space-y-6 text-gray-400 font-medium text-lg leading-loose">
                <p>暂无歌词</p>
            </div>
            <div class="h-[40vh]"></div> <!-- 底部占位 -->
            
            <!-- 底部 API 标识 - 位于滚动容器底部 -->
            <div class="py-8 text-center text-xs text-gray-600 select-none">
                 <span class="inline-flex items-center space-x-1">
                    <i class="fas fa-bolt text-emerald-500/50"></i>
                    <span>Powered by 白子</span>
                 </span>
            </div>
        </div>
    </div>

    <audio id="audio-player" preload="auto">

    <script>
        // ================= 配置 =================
        const API_BASE = 'https://api.xunhuisi.store/API/QQMusic/Song.php';
        
        // ================= 状态 =================
        let allSearchResults = [];
        let currentPage = 1;
        const itemsPerPage = 10;
        const maxPages = 3;

        let currentIndex = -1;
        let isPlaying = false;
        let lyrics = [];
        let lyricIndex = 0;
        let isSeeking = false;
        
        // 触摸滑动检测 (防止列表误触)
        let isTouchMove = false;
        document.addEventListener('touchstart', () => { isTouchMove = false; }, {passive: true});
        document.addEventListener('touchmove', () => { isTouchMove = true; }, {passive: true});

        // 播放模式
        let playbackMode = 0; 
        const modes = [
            { name: '顺序播放', icon: 'fa-list' },
            { name: '单曲循环', icon: 'fa-repeat' },
            { name: '随机播放', icon: 'fa-shuffle' }
        ];

        // ================= DOM =================
        const audio = document.getElementById('audio-player');
        const playBtn = document.getElementById('play-btn');
        const coverImg = document.getElementById('cover-img');
        const progressBar = document.getElementById('progress-bar');
        const miniLyric = document.getElementById('mini-lyric');
        const fullLyricModal = document.getElementById('full-lyric-modal');
        const lyricContent = document.getElementById('lyric-content');
        const paginationEl = document.getElementById('pagination');
        const modeBtn = document.getElementById('mode-btn');

        // ================= 初始化 =================
        audio.volume = 1;

        audio.addEventListener('timeupdate', updateProgress);
        audio.addEventListener('ended', handleSongEnd);
        audio.addEventListener('play', () => updatePlayState(true));
        audio.addEventListener('pause', () => updatePlayState(false));
        audio.addEventListener('error', () => {
            miniLyric.innerText = "播放出错，尝试下一首...";
            setTimeout(() => playNext(true), 2000);
        });

        // ================= 核心逻辑 =================

        function changePlaybackMode() {
            playbackMode = (playbackMode + 1) % modes.length;
            const mode = modes[playbackMode];
            
            modeBtn.innerHTML = `<i class="fas ${mode.icon} text-sm sm:text-base"></i>`;
            modeBtn.title = mode.name;
            
            if (playbackMode === 1) {
                modeBtn.classList.add('text-emerald-400');
            } else {
                modeBtn.classList.remove('text-emerald-400');
            }

            const originalText = miniLyric.innerText;
            miniLyric.innerText = `模式切换：${mode.name}`;
            setTimeout(() => {
                if (miniLyric.innerText.includes('模式切换')) {
                    miniLyric.innerText = originalText;
                }
            }, 1500);
        }

        function handleSongEnd() {
            if (playbackMode === 1) { 
                audio.currentTime = 0;
                audio.play();
            } else {
                playNext(true);
            }
        }

        function playNext(isAuto = false) {
            if (allSearchResults.length === 0) return;
            let nextIndex = currentIndex;
            if (playbackMode === 2) { 
                if (allSearchResults.length > 1) {
                    do {
                        nextIndex = Math.floor(Math.random() * allSearchResults.length);
                    } while (nextIndex === currentIndex);
                } else {
                    nextIndex = 0;
                }
            } else {
                nextIndex = currentIndex + 1;
                if (nextIndex >= allSearchResults.length) nextIndex = 0;
            }
            playMusic(nextIndex);
        }

        function playPrev() {
            if (allSearchResults.length === 0) return;
            let prevIndex = currentIndex;
            if (playbackMode === 2) { 
                 if (allSearchResults.length > 1) {
                    do {
                        prevIndex = Math.floor(Math.random() * allSearchResults.length);
                    } while (prevIndex === currentIndex);
                }
            } else {
                prevIndex = currentIndex - 1;
                if (prevIndex < 0) prevIndex = allSearchResults.length - 1;
            }
            playMusic(prevIndex);
        }

        async function searchMusic() {
            const keyword = document.getElementById('search-input').value.trim();
            if (!keyword) return;

            showLoading(true);
            document.getElementById('welcome').classList.add('hidden');
            paginationEl.classList.add('hidden');
            document.getElementById('song-list').innerHTML = '';

            try {
                const res = await fetch(`${API_BASE}?name=${encodeURIComponent(keyword)}&type=json&list=30`);
                const data = await res.json();

                if (data.code === 200 && data.data && data.data.length > 0) {
                    allSearchResults = data.data;
                    changePage(1);
                } else {
                    document.getElementById('song-list').innerHTML = `<div class="text-center text-gray-500 py-10">未找到相关歌曲</div>`;
                }
            } catch (e) {
                console.error(e);
                document.getElementById('song-list').innerHTML = `<div class="text-center text-red-400 py-10">网络错误，请检查 API 配置</div>`;
            } finally {
                showLoading(false);
            }
        }

        function changePage(page) {
            if (page < 1) page = 1;
            const totalItems = allSearchResults.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            if (page > totalPages) page = totalPages;

            currentPage = page;

            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = allSearchResults.slice(start, end);

            renderList(pageData, start);
            updatePaginationUI(totalPages);
        }

        function updatePaginationUI(totalPages) {
            if (totalPages <= 1) {
                paginationEl.classList.add('hidden');
                return;
            }
            paginationEl.classList.remove('hidden');
            document.getElementById('page-num').innerText = currentPage;
            document.getElementById('total-pages').innerText = totalPages;
            document.getElementById('prev-btn').disabled = currentPage === 1;
            document.getElementById('next-btn').disabled = currentPage === totalPages;
        }

        function renderList(list, startIndex) {
            const html = list.map((song, i) => {
                const globalIndex = startIndex + i;
                const isActive = globalIndex === currentIndex;
                
                // 动态计算样式：高亮当前播放歌曲，移除触摸 active 样式
                const containerClass = isActive 
                    ? "group flex items-center p-3 rounded-lg bg-white/10 cursor-pointer transition border-b border-white/5 last:border-0" // 高亮背景
                    : "group flex items-center p-3 rounded-lg sm:hover:bg-white/10 cursor-pointer transition border-b border-white/5 last:border-0"; // 普通状态
                
                const numClass = isActive
                    ? "w-8 text-center text-emerald-400 font-mono text-sm" // 高亮数字
                    : "w-8 text-center text-gray-500 font-mono text-sm group-hover:text-emerald-400";
                    
                const titleClass = isActive
                    ? "text-emerald-400 font-medium truncate transition" // 高亮标题
                    : "text-white font-medium truncate group-hover:text-emerald-400 transition";
                
                const iconBtnClass = isActive
                    ? "w-8 h-8 rounded-full border border-emerald-500 bg-emerald-500/20 text-emerald-500 flex items-center justify-center transition"
                    : "w-8 h-8 rounded-full border border-gray-600 flex items-center justify-center group-hover:border-emerald-500 group-hover:bg-emerald-500/20 text-gray-400 group-hover:text-emerald-500 transition";

                // 高亮时显示波形图标
                const iconHtml = isActive
                    ? '<i class="fas fa-wave-square text-xs"></i>'
                    : '<i class="fas fa-play text-xs pl-0.5"></i>';

                return `
                <div onclick="playMusic(${globalIndex})" class="${containerClass}">
                    <div class="${numClass}">
                        ${isActive ? '<i class="fas fa-volume-high text-xs"></i>' : globalIndex + 1}
                    </div>
                    <div class="flex-1 ml-3 overflow-hidden">
                        <div class="${titleClass}">${song.name}</div>
                        <div class="text-xs text-gray-400 truncate">${song.singer}</div>
                    </div>
                    <button class="${iconBtnClass}">
                       ${iconHtml}
                    </button>
                </div>
            `}).join('');
            document.getElementById('song-list').innerHTML = html;
        }

        async function playMusic(index) {
            if (isTouchMove) return;

            if (index < 0 || index >= allSearchResults.length) return;
            
            // 更新当前索引
            currentIndex = index;
            
            // 立即刷新列表高亮状态（“光标”移动）
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            // 只有当播放歌曲在当前页时才需要重新渲染，或者你可以简单地直接重新渲染当前页
            const pageData = allSearchResults.slice(start, end);
            renderList(pageData, start);

            const song = allSearchResults[index];

            updateMetadata(song.name, song.singer, null);
            miniLyric.innerText = "正在解析...";
            
            try {
                const res = await fetch(`${API_BASE}?mid=${song.mid}&type=json`);
                const data = await res.json();

                if (data.code === 200) {
                    audio.src = data.music_url;
                    audio.play();

                    updateMetadata(data.title, data.singer, data.cover);
                    document.getElementById('download-btn').href = data.music_url;
                    document.getElementById('bg-layer').style.backgroundImage = `url('${data.cover}')`;

                    parseAndRenderLyrics(data.lyric, data.trans);

                } else {
                    miniLyric.innerText = data.msg || "无法播放";
                }
            } catch (e) {
                console.error(e);
                miniLyric.innerText = "加载失败";
            }
        }

        function togglePlay() {
            if (!audio.src) return;
            if (audio.paused) {
                audio.play();
            } else {
                audio.pause();
            }
        }

        function updatePlayState(playing) {
            isPlaying = playing;
            playBtn.innerHTML = playing ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play ml-0.5"></i>';
            if (playing) {
                coverImg.classList.add('spin-slow');
                coverImg.style.animationPlayState = 'running';
            } else {
                coverImg.style.animationPlayState = 'paused';
            }
        }

        // ================= 歌词逻辑 =================

        function parseAndRenderLyrics(lrcStr, transStr) {
            lyrics = [];
            if (!lrcStr) {
                renderLyricsUI();
                return;
            }
            const lines = lrcStr.split('\n');
            const timeReg = /\[(\d{2}):(\d{2})\.(\d{2,3})\]/;
            lines.forEach(line => {
                const match = timeReg.exec(line);
                if (match) {
                    const min = parseInt(match[1]);
                    const sec = parseInt(match[2]);
                    const ms = parseInt(match[3].padEnd(3, '0').substring(0, 3));
                    const time = min * 60 + sec + ms / 1000;
                    const text = line.replace(timeReg, '').trim();
                    if (text) { lyrics.push({ time, text }); }
                }
            });
            renderLyricsUI();
        }

        function renderLyricsUI() {
            if (lyrics.length === 0) {
                lyricContent.innerHTML = '<p>纯音乐 / 暂无歌词</p>';
                miniLyric.innerText = '纯音乐 / 暂无歌词';
                return;
            }
            const html = lyrics.map((line, i) => `
                <p class="lyric-item cursor-pointer hover:text-white" id="line-${i}" onclick="seekToLyric(${line.time})">
                    ${line.text}
                </p>
            `).join('');
            lyricContent.innerHTML = html;
        }

        function updateProgress() {
            if (!isSeeking) {
                const percent = (audio.currentTime / audio.duration) * 100 || 0;
                progressBar.value = percent;
                document.getElementById('current-time').innerText = formatTime(audio.currentTime);
                document.getElementById('duration').innerText = formatTime(audio.duration || 0);
                progressBar.style.background = `linear-gradient(to right, #10b981 ${percent}%, rgba(255,255,255,0.2) ${percent}%)`;
            }
            syncLyric();
        }

        function syncLyric() {
            if (lyrics.length === 0) return;
            const time = audio.currentTime;
            let index = lyrics.findIndex(l => l.time > time) - 1;
            if (index < 0) index = 0;
            if (index === lyricIndex) return;
            lyricIndex = index;
            const currentLine = lyrics[index];
            if (currentLine) {
                miniLyric.innerText = currentLine.text;
                const items = document.querySelectorAll('.lyric-item');
                items.forEach(el => el.classList.remove('lyric-active'));
                const activeEl = document.getElementById(`line-${index}`);
                if (activeEl) {
                    activeEl.classList.add('lyric-active');
                    activeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }

        function seekAudio() {
            isSeeking = true;
            const time = (progressBar.value / 100) * audio.duration;
            audio.currentTime = time;
            progressBar.style.background = `linear-gradient(to right, #10b981 ${progressBar.value}%, rgba(255,255,255,0.2) ${progressBar.value}%)`;
            setTimeout(() => isSeeking = false, 200);
        }

        function seekToLyric(time) {
            audio.currentTime = time;
            togglePlay();
            if (audio.paused) audio.play();
        }

        function setVolume(val) {
            audio.volume = val;
            const slider = document.querySelector('input[type=range][style*="rotate"]');
            if(slider) {
                 const percent = val * 100;
                 slider.style.background = `linear-gradient(to right, #10b981 ${percent}%, rgba(255,255,255,0.2) ${percent}%)`;
            }
        }

        function toggleFullLyric() {
            fullLyricModal.classList.toggle('translate-y-full');
            if (!fullLyricModal.classList.contains('translate-y-full')) {
                document.getElementById('full-title').innerText = document.getElementById('song-title').innerText;
                document.getElementById('full-singer').innerText = document.getElementById('singer-name').innerText;
                const activeEl = document.querySelector('.lyric-active');
                if (activeEl) activeEl.scrollIntoView({ block: 'center' });
            }
        }

        function updateMetadata(title, singer, cover) {
            document.getElementById('song-title').innerText = title;
            document.getElementById('singer-name').innerText = singer;
            if (cover) document.getElementById('cover-img').src = cover;
        }

        function formatTime(seconds) {
            const min = Math.floor(seconds / 60);
            const sec = Math.floor(seconds % 60);
            return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
        }

        function showLoading(show) {
            document.getElementById('loading').classList.toggle('hidden', !show);
        }
    </script>
</body>
</html>