<?php
$brand_class = $module;
if($OJ_STATUS=='exp' && $module == 'csgoj') {
    $brand_class = 'expsys';
}
if($brand_class == 'expsys') {
    $subtitle = 'Experiment System';
    $subtitle_cn = '实验系统';
}
else if($brand_class == 'cr') {
    $subtitle = 'Contest Registration';
    $subtitle_cn = '比赛报名';
}
else if($brand_class == 'index') {
    $subtitle = 'Home Page';
    $subtitle_cn = '首页';
}
else if($brand_class == 'admin') {
    $subtitle = 'Admin Panel';
    $subtitle_cn = '管理后台';
}
else if($brand_class == 'cpcsys') {
    $subtitle = 'Contest System';
    $subtitle_cn = '比赛系统';
}
else if($brand_class == 'tt') {
    $subtitle = 'Training Team';
    $subtitle_cn = '训练队';
}
else if($brand_class == 'ojtool') {
    $subtitle = 'Tools';
    $subtitle_cn = '工具集';
}
else {
    $subtitle = 'Online Judge';
    $subtitle_cn = '在线评测';
}
?>
<div class="cpc-navbar cpc-navbar-{$brand_class}">
    <!-- Logo 水印背景 -->
    <div class="brand-watermark">
        <img src="__IMG__/global/badge.png" title="badge" class="watermark-logo">
    </div>
    
    <!-- 品牌内容容器 -->
    <div class="brand-content">
        <!-- 品牌标题和副标题 -->
        <div class="brand-main">
            <a href="/" class="brand-link">
                <div class="brand-text-container">
                    <h1 class="cpc-brand" data-text="{$OJ_NAME}">{$OJ_NAME}</h1>
                    <div class="brand-subtitle-container">
                        <span class="cpc-brand-subtitle">
                            <span class="subtitle-main">{$subtitle_cn}</span>
                            <span class="en-text">{$subtitle}</span>
                        </span>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- 移动端菜单切换按钮 -->
        <button type="button" class="navbar-toggler cpc-nav-toggle" data-bs-toggle="collapse" data-bs-target=".sidebar" aria-expanded="false" aria-controls="navbar" aria-label="Toggle navigation">
            <span class="visually-hidden">Toggle navigation</span>
            <div class="hamburger">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </div>
        </button>
    </div>
</div>
