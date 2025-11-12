/**
 * 双语显示效果控制
 * 始终显示双语文本（中文为主，英文为辅）
 */

(function() {
    'use strict';
    
    // 默认配置
    var config = {
        showEnglish: true,  // 始终显示英文（作为辅助）
        animationDuration: 200  // 动画持续时间
    };
    
    /**
     * 初始化双语显示
     */
    function init() {
        // 始终显示英文辅助文本
        config.showEnglish = true;
        
        // 应用显示状态
        updateDisplay();
        
        // 优化按钮中的英文文本颜色
        optimizeButtonTextColors();
        
        // 优化品牌区域中的英文文本显示
        optimizeBrandTextColors();
        
    }
    
    /**
     * 更新显示状态
     */
    function updateDisplay() {
        var $enTexts = $('.en-text');
        
        // 始终显示英文辅助文本
        $enTexts.show();
    }
    
    
    
    /**
     * 优化按钮中的英文文本颜色
     */
    function optimizeButtonTextColors() {
        // 保持按钮内英文文本完全由 CSS 继承父元素颜色，避免首屏闪变
        // 如需特殊按钮定制，请在样式表中以选择器覆盖，而非运行时内联样式
        return;
    }
    
    /**
     * 优化品牌区域中的英文文本显示
     */
    function optimizeBrandTextColors() {
        $('.cpc-brand-subtitle .en-text').each(function() {
            var $enText = $(this);
            var $navbar = $enText.closest('.cpc-navbar');
            
            // 品牌区域使用固定的浅色文字，因为背景是深色的
            $enText.css({
                'color': 'rgba(238, 238, 238, 0.8)',
                'text-shadow': '0 1px 2px rgba(0, 0, 0, 0.3)',
                'opacity': '0.8'
            });
        });
    }
    
    /**
     * 判断背景颜色是否为深色
     * @param {string} bgColor - CSS背景颜色值
     * @returns {boolean} 是否为深色背景
     */
    function isDarkBackground(bgColor) {
        if (!bgColor || bgColor === 'transparent' || bgColor === 'rgba(0, 0, 0, 0)') {
            return false;
        }
        
        // 解析RGB值
        var rgb = bgColor.match(/\d+/g);
        if (!rgb || rgb.length < 3) {
            return false;
        }
        
        var r = parseInt(rgb[0]);
        var g = parseInt(rgb[1]);
        var b = parseInt(rgb[2]);
        
        // 计算亮度 (使用标准亮度公式)
        var brightness = (r * 299 + g * 587 + b * 114) / 1000;
        
        return brightness < 128; // 小于128认为是深色
    }
    
    /**
     * 获取DOM对象的中英双语文本
     * @param {jQuery|HTMLElement} element - DOM元素或jQuery对象
     * @returns {Object} 包含中文和英文文本的对象 {chinese: string, english: string}
     */
    function getBilingualText(element) {
        var $element = $(element);
        var $enText = $element.find('.en-text');
        
        var chinese = '';
        var english = '';
        
        if ($enText.length > 0) {
            // 有英文文本的情况
            chinese = $element.clone().children().remove().end().text().trim();
            english = $enText.text().trim();
        } else {
            // 没有英文文本的情况
            chinese = $element.text().trim();
            english = '';
        }
        
        return {
            chinese: chinese,
            english: english
        };
    }
    
    
    // 公开API
    window.Bilingual = {
        init: init,
        optimizeButtonTextColors: optimizeButtonTextColors,
        optimizeBrandTextColors: optimizeBrandTextColors,
        getBilingualText: getBilingualText,
        config: config
    };
    
    // 自动初始化
    $(document).ready(function() {
        init();
    });
    
})();