/**
 * CSG Switch - 通用美化开关组件
 * 业务无关，支持多种配置和事件
 */

(function() {
    'use strict';
    
    // 避免重复初始化
    if (typeof window.CSGSwitch !== 'undefined') {
        return;
    }
    
    class CSGSwitch {
        constructor() {
            this.switches = new Map();
        this.defaultOptions = {
            size: 'md',           // sm, md, lg
            theme: 'primary',     // primary, success, warning, danger, info
            animate: true,        // 是否启用动画
            storage: false,       // 是否使用localStorage
            storageKey: null,     // localStorage键名
            onChange: null,       // 状态改变回调
            onInit: null,         // 初始化回调
            textOn: null,         // 开启状态文字
            textOff: null,        // 关闭状态文字
            textOnEn: null,       // 开启状态英文文字
            textOffEn: null,      // 关闭状态英文文字
            titleOn: null,        // 开启状态提示
            titleOff: null        // 关闭状态提示
        };
            this.init();
        }
        
        init() {
            // 自动初始化页面中所有csg-switch
            this.autoInit();
            
            // 监听动态添加的开关
            this.observeChanges();
        }
        
        /**
         * 自动初始化所有开关
         */
        autoInit() {
            const switches = document.querySelectorAll('.csg-switch-input:not([data-csg-initialized])');
            switches.forEach(switchEl => {
                this.initSwitch(switchEl);
            });
        }
        
        /**
         * 初始化单个开关
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} options - 配置选项
         */
        initSwitch(switchEl, options = {}) {
            // 如果已经初始化，先销毁再重新初始化
            if (switchEl.dataset.csgInitialized) {
                this.destroy(switchEl);
            }
            
            const config = this.parseOptions(switchEl, options);
            const switchId = this.generateId();
            
            // 标记为已初始化
            switchEl.dataset.csgInitialized = 'true';
            switchEl.dataset.csgId = switchId;
            
            // 创建开关结构
            this.createSwitchStructure(switchEl, config);
            
            // 应用配置
            this.applyConfig(switchEl, config);
            
            // 绑定事件
            this.bindEvents(switchEl, config);
            
            // 恢复存储状态
            if (config.storage && config.storageKey) {
                this.restoreState(switchEl, config);
            }
            
            // 存储配置
            this.switches.set(switchId, {
                element: switchEl,
                config: config
            });
            
            // 强制同步状态 - 确保滑块位置与checked状态一致
            this.updateSwitchState(switchEl, config);
            
            // 触发初始化回调
            if (config.onInit && typeof config.onInit === 'function') {
                config.onInit(switchEl, config);
            }
        }
        
        /**
         * 解析配置选项
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} options - 配置选项
         * @returns {Object} 合并后的配置
         */
        parseOptions(switchEl, options) {
            const config = { ...this.defaultOptions };
            
            // 从data属性读取配置
            const dataSize = switchEl.dataset.csgSize;
            const dataTheme = switchEl.dataset.csgTheme;
            const dataAnimate = switchEl.dataset.csgAnimate;
            const dataStorage = switchEl.dataset.csgStorage;
            const dataStorageKey = switchEl.dataset.csgStorageKey;
            const dataTextOn = switchEl.dataset.csgTextOn;
            const dataTextOff = switchEl.dataset.csgTextOff;
            const dataTextOnEn = switchEl.dataset.csgTextOnEn;
            const dataTextOffEn = switchEl.dataset.csgTextOffEn;
            const dataTitleOn = switchEl.dataset.csgTitleOn;
            const dataTitleOff = switchEl.dataset.csgTitleOff;
            
            if (dataSize) config.size = dataSize;
            if (dataTheme) config.theme = dataTheme;
            if (dataAnimate !== undefined) config.animate = dataAnimate === 'true';
            if (dataStorage !== undefined) config.storage = dataStorage === 'true';
            if (dataStorageKey) config.storageKey = dataStorageKey;
            if (dataTextOn) config.textOn = dataTextOn;
            if (dataTextOff) config.textOff = dataTextOff;
            if (dataTextOnEn) config.textOnEn = dataTextOnEn;
            if (dataTextOffEn) config.textOffEn = dataTextOffEn;
            if (dataTitleOn) config.titleOn = dataTitleOn;
            if (dataTitleOff) config.titleOff = dataTitleOff;
            
            // 合并传入的选项
            return { ...config, ...options };
        }
        
        /**
         * 创建开关结构
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        createSwitchStructure(switchEl, config) {
            const wrapper = switchEl.closest('.csg-switch') || this.createWrapper(switchEl);
            const track = document.createElement('div');
            const content = document.createElement('div');
            const slider = document.createElement('div');
            
            track.className = 'csg-switch-track';
            content.className = 'csg-switch-content';
            slider.className = 'csg-switch-slider';
            
            // 创建文字元素
            if (config.textOn || config.textOff) {
                const textOn = document.createElement('div');
                const textOff = document.createElement('div');
                
                textOn.className = 'csg-switch-text csg-switch-text-on';
                textOff.className = 'csg-switch-text csg-switch-text-off';
                
                // 支持双语文字
                this.createBilingualText(textOn, config.textOn, config.textOnEn);
                this.createBilingualText(textOff, config.textOff, config.textOffEn);
                
                content.appendChild(textOff);
                content.appendChild(textOn);
                
                // 标记有文字
                track.classList.add('csg-switch-has-text');
            }
            
            track.appendChild(content);
            track.appendChild(slider);
            wrapper.appendChild(track);
            
            // 确保input在track之前
            if (switchEl.parentNode !== wrapper) {
                wrapper.insertBefore(switchEl, track);
            }
            
            // 计算并设置轨道宽度 - 参考bootstrap-switch实现
            if (config.textOn || config.textOff) {
                this.calculateTrackWidth(track, config);
            }
            
            // 设置title属性
            this.updateTitle(switchEl, config);
            
            // 立即同步状态，确保初始化时状态正确
            this.updateSwitchState(switchEl, config);
        }
        
        /**
         * 计算轨道宽度 - 参考bootstrap-switch实现
         * @param {HTMLElement} track - 轨道元素
         * @param {Object} config - 配置选项
         */
        calculateTrackWidth(track, config) {
            // 创建临时元素测量文字宽度
            const tempDiv = document.createElement('div');
            tempDiv.style.position = 'absolute';
            tempDiv.style.visibility = 'hidden';
            tempDiv.style.whiteSpace = 'nowrap';
            tempDiv.style.fontSize = '0.7rem';
            tempDiv.style.fontWeight = '600';
            tempDiv.style.padding = '0 0.5rem';
            document.body.appendChild(tempDiv);
            
            let maxWidth = 0;
            
            // 测量开启文字宽度
            if (config.textOn) {
                if (config.textOnEn) {
                    tempDiv.innerHTML = `
                        <div style="font-size: 0.7rem; font-weight: 600; line-height: 1.2;">${config.textOn}</div>
                        <div style="font-size: 0.7rem; font-weight: 400; line-height: 1.2; opacity: 0.8; margin-top: 2px;">${config.textOnEn}</div>
                    `;
                } else {
                    tempDiv.textContent = config.textOn;
                }
                maxWidth = Math.max(maxWidth, tempDiv.offsetWidth);
            }
            
            // 测量关闭文字宽度
            if (config.textOff) {
                if (config.textOffEn) {
                    tempDiv.innerHTML = `
                        <div style="font-size: 0.7rem; font-weight: 600; line-height: 1.2;">${config.textOff}</div>
                        <div style="font-size: 0.7rem; font-weight: 400; line-height: 1.2; opacity: 0.8; margin-top: 2px;">${config.textOffEn}</div>
                    `;
                } else {
                    tempDiv.textContent = config.textOff;
                }
                maxWidth = Math.max(maxWidth, tempDiv.offsetWidth);
            }
            
            // 清理临时元素
            document.body.removeChild(tempDiv);
            
            // 设置轨道宽度：文字最大宽度 + 滑块宽度 + 左右padding
            const sliderWidth = 1.5; // rem
            const padding = 1; // rem (左右各0.5rem)
            const totalWidth = maxWidth + (sliderWidth + padding) * 16; // 转换为px
            
            track.style.width = `${totalWidth}px`;
        }
        
        /**
         * 创建包装器
         * @param {HTMLElement} switchEl - 开关元素
         * @returns {HTMLElement} 包装器元素
         */
        createWrapper(switchEl) {
            const wrapper = document.createElement('div');
            wrapper.className = 'csg-switch';
            switchEl.parentNode.insertBefore(wrapper, switchEl);
            wrapper.appendChild(switchEl);
            return wrapper;
        }
        
        /**
         * 创建双语文字
         * @param {HTMLElement} element - 文字容器元素
         * @param {string} chinese - 中文文字
         * @param {string} english - 英文文字
         */
        createBilingualText(element, chinese, english) {
            if (!chinese) {
                element.textContent = 'ON';
                return;
            }
            
            if (english) {
                // 有英文，创建双语结构
                element.innerHTML = `
                    <div class="csg-switch-text-main">${chinese}</div>
                    <div class="csg-switch-text-en">${english}</div>
                `;
            } else {
                // 只有中文
                element.textContent = chinese;
            }
        }
        
        /**
         * 应用配置
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        applyConfig(switchEl, config) {
            const wrapper = switchEl.closest('.csg-switch');
            
            // 应用尺寸
            wrapper.classList.add(`csg-switch-${config.size}`);
            
            // 应用主题
            if (config.theme !== 'primary') {
                wrapper.classList.add(`csg-switch-${config.theme}`);
            }
            
            // 应用动画
            if (config.animate) {
                wrapper.classList.add('csg-switch-animate');
            }
            
            // 应用动画类型
            if (config.animation !== 'default') {
                wrapper.classList.add(`csg-switch-${config.animation}`);
            }
        }
        
        /**
         * 绑定事件
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        bindEvents(switchEl, config) {
            const wrapper = switchEl.closest('.csg-switch');
            
            // 移除旧的事件监听器（避免重复绑定）
            if (wrapper.dataset.csgEventsBound) {
                return;
            }
            
            // 点击事件
            wrapper.addEventListener('click', (e) => {
                if (switchEl.disabled) return;
                
                e.preventDefault();
                e.stopPropagation();
                this.toggle(switchEl);
            });
            
            // 键盘事件
            switchEl.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!switchEl.disabled) {
                        this.toggle(switchEl);
                    }
                }
            });
            
            // 状态改变事件
            switchEl.addEventListener('change', () => {
                this.handleChange(switchEl, config);
            });
            
            // 标记事件已绑定
            wrapper.dataset.csgEventsBound = 'true';
        }
        
        /**
         * 切换开关状态
         * @param {HTMLElement} switchEl - 开关元素
         */
        toggle(switchEl) {
            switchEl.checked = !switchEl.checked;
            switchEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        /**
         * 获取开关配置
         * @param {HTMLElement} switchEl - 开关元素
         * @returns {Object} 配置对象
         */
        getConfig(switchEl) {
            const switchId = switchEl.dataset.csgId;
            const switchData = this.switches.get(switchId);
            return switchData ? switchData.config : this.defaultOptions;
        }
        
        /**
         * 处理状态改变
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        handleChange(switchEl, config) {
            // 原子化更新：同时更新滑块位置和视觉状态
            this.updateSwitchState(switchEl, config);
            
            // 保存状态到localStorage
            if (config.storage && config.storageKey) {
                localStorage.setItem(config.storageKey, switchEl.checked.toString());
            }
            
            // 更新title
            this.updateTitle(switchEl, config);
            
            // 触发回调
            if (config.onChange && typeof config.onChange === 'function') {
                config.onChange(switchEl.checked, switchEl);
            }
            
            // 触发自定义事件
            switchEl.dispatchEvent(new CustomEvent('csg-switch-change', {
                detail: { checked: switchEl.checked, element: switchEl }
            }));
        }
        
        /**
         * 设置为开启状态
         * @param {HTMLElement} switchEl - 开关元素
         */
        turnOn(switchEl) {
            const track = switchEl.nextElementSibling;
            if (!track) return;
            
            const slider = track.querySelector('.csg-switch-slider');
            if (!slider) return;
            
            // 1. 更新CSS类
            track.classList.add('csg-switch-active');
            
            // 2. 设置滑块位置 - 立即设置，无动画
            const trackWidth = track.offsetWidth;
            const sliderWidth = slider.offsetWidth;
            
            // 临时禁用动画
            slider.style.transition = 'none';
            
            if (trackWidth > 0 && sliderWidth > 0) {
                const rightMargin = 8;
                const moveDistance = trackWidth - sliderWidth - rightMargin;
                slider.style.transform = `translateX(${moveDistance}px) translateY(-50%)`;
            } else {
                // 如果尺寸为0，使用CSS calc作为后备
                slider.style.transform = 'translateX(calc(100% - 1.5rem - 8px)) translateY(-50%)';
                
                // 延迟重试，确保DOM完全渲染
                setTimeout(() => {
                    const retryTrackWidth = track.offsetWidth;
                    const retrySliderWidth = slider.offsetWidth;
                    if (retryTrackWidth > 0 && retrySliderWidth > 0) {
                        const rightMargin = 8;
                        const moveDistance = retryTrackWidth - retrySliderWidth - rightMargin;
                        slider.style.transform = `translateX(${moveDistance}px) translateY(-50%)`;
                    }
                }, 50);
            }
            
            // 3. 更新文字显示
            const textOn = track.querySelector('.csg-switch-text-on');
            const textOff = track.querySelector('.csg-switch-text-off');
            if (textOn) textOn.style.opacity = '1';
            if (textOff) textOff.style.opacity = '0';
            
            // 恢复动画
            setTimeout(() => {
                slider.style.transition = '';
            }, 0);
        }
        
        /**
         * 设置为关闭状态
         * @param {HTMLElement} switchEl - 开关元素
         */
        turnOff(switchEl) {
            const track = switchEl.nextElementSibling;
            if (!track) return;
            
            const slider = track.querySelector('.csg-switch-slider');
            if (!slider) return;
            
            // 1. 更新CSS类
            track.classList.remove('csg-switch-active');
            
            // 2. 设置滑块位置 - 立即设置，无动画
            // 临时禁用动画
            slider.style.transition = 'none';
            slider.style.transform = 'translateX(0) translateY(-50%)';
            
            // 3. 更新文字显示
            const textOn = track.querySelector('.csg-switch-text-on');
            const textOff = track.querySelector('.csg-switch-text-off');
            if (textOn) textOn.style.opacity = '0';
            if (textOff) textOff.style.opacity = '1';
            
            // 恢复动画
            setTimeout(() => {
                slider.style.transition = '';
            }, 0);
        }
        
        /**
         * 根据checked状态设置开关状态
         * @param {HTMLElement} switchEl - 开关元素
         */
        updateSwitchState(switchEl, config) {
            if (switchEl.checked) {
                this.turnOn(switchEl);
            } else {
                this.turnOff(switchEl);
            }
        }
        
        /**
         * 强制同步开关状态
         * @param {HTMLElement} switchEl - 开关元素
         */
        syncSwitchState(switchEl) {
            // 如果开关未初始化，先初始化
            if (!switchEl.dataset.csgInitialized) {
                this.initSwitch(switchEl);
                return;
            }
            
            // 强制重新计算尺寸
            const track = switchEl.nextElementSibling;
            if (track) {
                // 强制重新计算尺寸
                track.offsetHeight;
                track.offsetWidth;
            }
            
            // 直接根据checked状态设置
            if (switchEl.checked) {
                this.turnOn(switchEl);
            } else {
                this.turnOff(switchEl);
            }
        }
        
        /**
         * 强制重新初始化开关（用于模态框等场景）
         * @param {HTMLElement} switchEl - 开关元素
         */
        forceReinit(switchEl) {
            // 销毁现有开关
            this.destroy(switchEl);
            
            // 等待DOM更新后重新初始化
            setTimeout(() => {
                this.initSwitch(switchEl);
                
                // 再次确保状态正确
                setTimeout(() => {
                    if (switchEl.checked) {
                        this.turnOn(switchEl);
                    } else {
                        this.turnOff(switchEl);
                    }
                }, 10);
            }, 10);
        }
        
        /**
         * 程序化设置开关状态
         * @param {HTMLElement} switchEl - 开关元素
         * @param {boolean} checked - 是否选中
         */
        setChecked(switchEl, checked) {
            switchEl.checked = checked;
            if (checked) {
                this.turnOn(switchEl);
            } else {
                this.turnOff(switchEl);
            }
        }
        
        
        /**
         * 更新title属性
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        updateTitle(switchEl, config) {
            if (config.titleOn && config.titleOff) {
                const title = switchEl.checked ? config.titleOn : config.titleOff;
                switchEl.setAttribute('title', title);
            }
        }
        
        
        /**
         * 恢复存储状态
         * @param {HTMLElement} switchEl - 开关元素
         * @param {Object} config - 配置选项
         */
        restoreState(switchEl, config) {
            const stored = localStorage.getItem(config.storageKey);
            if (stored !== null) {
                switchEl.checked = stored === 'true';
            }
        }
        
        /**
         * 监听DOM变化和属性变化
         */
        observeChanges() {
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'childList') {
                            // 监听新增的开关
                            mutation.addedNodes.forEach((node) => {
                                if (node.nodeType === Node.ELEMENT_NODE) {
                                    const switches = node.querySelectorAll ? 
                                        node.querySelectorAll('.csg-switch-input:not([data-csg-initialized])') : [];
                                    switches.forEach(switchEl => {
                                        this.initSwitch(switchEl);
                                    });
                                }
                            });
                        } else if (mutation.type === 'attributes') {
                            // 监听属性变化（特别是checked属性）
                            const target = mutation.target;
                            if (target.classList && target.classList.contains('csg-switch-input')) {
                                if (mutation.attributeName === 'checked' || mutation.attributeName === 'data-csg-initialized') {
                                    this.syncSwitchState(target);
                                }
                            }
                        }
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['checked', 'data-csg-initialized']
                });
            }
            
            // 监听全局的change事件
            document.addEventListener('change', (e) => {
                if (e.target.classList && e.target.classList.contains('csg-switch-input')) {
                    this.syncSwitchState(e.target);
                }
            });
            
            // 监听全局的input事件（处理程序化修改）
            document.addEventListener('input', (e) => {
                if (e.target.classList && e.target.classList.contains('csg-switch-input')) {
                    this.syncSwitchState(e.target);
                }
            });
        }
        
        /**
         * 生成唯一ID
         * @returns {string} 唯一ID
         */
        generateId() {
            return 'csg-switch-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }
        
        
        /**
         * 获取开关状态
         * @param {HTMLElement} switchEl - 开关元素
         * @returns {boolean} 是否选中
         */
        getChecked(switchEl) {
            return switchEl.checked;
        }
        
        /**
         * 启用/禁用开关
         * @param {HTMLElement} switchEl - 开关元素
         * @param {boolean} disabled - 是否禁用
         */
        setDisabled(switchEl, disabled) {
            switchEl.disabled = disabled;
            const wrapper = switchEl.closest('.csg-switch');
            if (disabled) {
                wrapper.classList.add('csg-switch-loading');
            } else {
                wrapper.classList.remove('csg-switch-loading');
            }
        }
        
        
        /**
         * 销毁开关
         * @param {HTMLElement} switchEl - 开关元素
         */
        destroy(switchEl) {
            const switchId = switchEl.dataset.csgId;
            if (switchId) {
                this.switches.delete(switchId);
                delete switchEl.dataset.csgInitialized;
                delete switchEl.dataset.csgId;
            }
            
            // 清理DOM结构
            const wrapper = switchEl.closest('.csg-switch');
            if (wrapper) {
                // 清理事件绑定标记
                delete wrapper.dataset.csgEventsBound;
                
                // 移除所有子元素，只保留input
                const track = wrapper.querySelector('.csg-switch-track');
                if (track) {
                    track.remove();
                }
                
                // 移除wrapper类
                wrapper.classList.remove('csg-switch', 'csg-switch-sm', 'csg-switch-md', 'csg-switch-lg', 
                                       'csg-switch-primary', 'csg-switch-success', 'csg-switch-warning', 
                                       'csg-switch-danger', 'csg-switch-info', 'csg-switch-animate');
            }
        }
    }
    
    // 创建全局实例
    const csgSwitch = new CSGSwitch();
    
    // 导出到全局
    window.CSGSwitch = CSGSwitch;
    window.csgSwitch = csgSwitch;
    
    // 自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            csgSwitch.autoInit();
        });
    } else {
        csgSwitch.autoInit();
    }
    
})();

