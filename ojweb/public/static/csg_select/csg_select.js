/**
 * CSG Select - 通用美化选择器组件
 * 业务无关，支持搜索、自定义内容、多种配置和事件
 */

(function() {
    'use strict';
    
    // 避免重复初始化
    if (typeof window.CSGSelect !== 'undefined') {
        return;
    }
    
    class CSGSelect {
        constructor() {
            this.selects = new Map();
            this.defaultOptions = {
                size: 'md',           // sm, md, lg
                theme: 'default',     // default, primary, success, warning, danger
                searchable: true,     // 是否可搜索
                allowCustom: true,    // 是否允许自定义输入
                placeholder: '请选择...', // 占位符
                placeholderEn: 'Please select...', // 英文占位符
                noResultsText: '无结果', // 无结果提示
                noResultsTextEn: 'No results', // 英文无结果提示
                customText: '自定义输入', // 自定义输入提示
                customTextEn: 'Custom input', // 英文自定义输入提示
                animate: true,        // 是否启用动画
                onChange: null,       // 值改变回调
                onInit: null,         // 初始化回调
                onSearch: null,       // 搜索回调
                onCustom: null        // 自定义输入回调
            };
            this.init();
        }
        
        init() {
            // 自动初始化页面中所有csg-select
            this.autoInit();
            
            // 监听动态添加的选择器
            this.observeChanges();
        }
        
        /**
         * 自动初始化所有选择器
         */
        autoInit() {
            const selects = document.querySelectorAll('.csg-select-input:not([data-csg-initialized])');
            selects.forEach(selectEl => {
                this.initSelect(selectEl);
            });
        }
        
        /**
         * 初始化单个选择器
         */
        initSelect(selectEl) {
            if (selectEl.dataset.csgInitialized) {
                return;
            }
            
            const options = this.parseOptions(selectEl);
            const selectId = this.generateId();
            
            // 标记为已初始化
            selectEl.dataset.csgInitialized = 'true';
            selectEl.dataset.csgSelectId = selectId;
            
            // 创建选择器实例
            const selectInstance = new CSGSelectInstance(selectEl, options, this);
            this.selects.set(selectId, selectInstance);
            
            // 初始化回调
            if (options.onInit && typeof options.onInit === 'function') {
                options.onInit(selectInstance);
            }
        }
        
        /**
         * 解析选项配置
         */
        parseOptions(selectEl) {
            const options = { ...this.defaultOptions };
            
            // 从data属性解析配置
            const dataOptions = selectEl.dataset;
            Object.keys(dataOptions).forEach(key => {
                if (key.startsWith('csg')) {
                    const optionKey = key.replace('csg', '').replace(/([A-Z])/g, '_$1').toLowerCase().substring(1);
                    let value = dataOptions[key];
                    
                    // 类型转换
                    if (value === 'true') value = true;
                    else if (value === 'false') value = false;
                    else if (!isNaN(value)) value = Number(value);
                    
                    options[optionKey] = value;
                }
            });
            
            return options;
        }
        
        /**
         * 生成唯一ID
         */
        generateId() {
            return 'csg-select-' + Math.random().toString(36).substr(2, 9);
        }
        
        /**
         * 监听DOM变化
         */
        observeChanges() {
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === 1) { // Element node
                                if (node.classList && node.classList.contains('csg-select-input')) {
                                    this.initSelect(node);
                                } else if (node.querySelectorAll) {
                                    const selects = node.querySelectorAll('.csg-select-input:not([data-csg-initialized])');
                                    selects.forEach(selectEl => {
                                        this.initSelect(selectEl);
                                    });
                                }
                            }
                        });
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }
        
        /**
         * 销毁选择器
         */
        destroy(selectId) {
            const selectInstance = this.selects.get(selectId);
            if (selectInstance) {
                selectInstance.destroy();
                this.selects.delete(selectId);
            }
        }
        
        /**
         * 获取选择器实例
         */
        getInstance(selectId) {
            return this.selects.get(selectId);
        }
    }
    
    /**
     * 单个选择器实例
     */
    class CSGSelectInstance {
        constructor(selectEl, options, parent) {
            this.selectEl = selectEl;
            this.options = options;
            this.parent = parent;
            this.isOpen = false;
            this.searchValue = '';
            this.highlightedIndex = -1;
            this.filteredOptions = [];
            
            this.createDisplay();
            this.bindEvents();
            this.loadOptions();
        }
        
        /**
         * 创建显示区域
         */
        createDisplay() {
            const container = this.selectEl.parentElement;
            if (!container.classList.contains('csg-select')) {
                container.classList.add('csg-select');
            }
            
            // 应用尺寸和主题类
            if (this.options.size !== 'md') {
                container.classList.add(`csg-select-${this.options.size}`);
            }
            if (this.options.theme !== 'default') {
                container.classList.add(`csg-select-${this.options.theme}`);
            }
            
            // 创建隐藏input用于表单提交
            this.createHiddenInput();
            
            // 创建显示区域
            this.displayEl = document.createElement('div');
            this.displayEl.className = 'csg-select-display';
            this.displayEl.tabIndex = 0;
            
            // 创建文本显示
            this.textEl = document.createElement('div');
            this.textEl.className = 'csg-select-text';
            this.textEl.textContent = this.getPlaceholder();
            
            // 创建箭头
            this.arrowEl = document.createElement('div');
            this.arrowEl.className = 'csg-select-arrow';
            
            // 创建下拉容器
            this.dropdownEl = document.createElement('div');
            this.dropdownEl.className = 'csg-select-dropdown';
            
            // 创建搜索框（如果启用）
            if (this.options.searchable) {
                this.createSearchBox();
            }
            
            // 创建选项容器
            this.optionsEl = document.createElement('div');
            this.optionsEl.className = 'csg-select-options';
            
            // 组装结构
            this.displayEl.appendChild(this.textEl);
            this.displayEl.appendChild(this.arrowEl);
            this.dropdownEl.appendChild(this.optionsEl);
            container.appendChild(this.displayEl);
            container.appendChild(this.dropdownEl);
        }
        
        /**
         * 创建隐藏input用于表单提交
         */
        createHiddenInput() {
            // 检查是否已存在隐藏input
            const existingHidden = this.selectEl.parentElement.querySelector(`input[type="hidden"][name="${this.selectEl.name}"]`);
            if (existingHidden) {
                this.hiddenInput = existingHidden;
                return;
            }
            
            // 创建隐藏input
            this.hiddenInput = document.createElement('input');
            this.hiddenInput.type = 'hidden';
            this.hiddenInput.name = this.selectEl.name;
            this.hiddenInput.id = this.selectEl.id + '_hidden';
            this.hiddenInput.value = this.selectEl.value;
            
            // 插入到select元素后面
            this.selectEl.parentElement.insertBefore(this.hiddenInput, this.selectEl.nextSibling);
        }
        
        /**
         * 创建搜索框
         */
        createSearchBox() {
            const searchEl = document.createElement('div');
            searchEl.className = 'csg-select-search';
            
            this.searchInputEl = document.createElement('input');
            this.searchInputEl.type = 'text';
            this.searchInputEl.className = 'csg-select-search-input';
            this.searchInputEl.placeholder = '搜索...';
            
            searchEl.appendChild(this.searchInputEl);
            this.dropdownEl.appendChild(searchEl);
        }
        
        /**
         * 绑定事件
         */
        bindEvents() {
            // 显示区域点击事件
            this.displayEl.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggle();
            });
            
            // 键盘事件
            this.displayEl.addEventListener('keydown', (e) => {
                this.handleKeydown(e);
            });
            
            // 搜索框事件
            if (this.searchInputEl) {
                this.searchInputEl.addEventListener('input', (e) => {
                    this.handleSearch(e.target.value);
                });
                
                this.searchInputEl.addEventListener('keydown', (e) => {
                    this.handleSearchKeydown(e);
                });
            }
            
            // 点击外部关闭（使用箭头函数以便后续移除）
            this.documentClickHandler = (e) => {
                if (!this.displayEl || !this.dropdownEl) {
                    return; // 组件已被销毁
                }
                if (!this.displayEl.contains(e.target) && !this.dropdownEl.contains(e.target)) {
                    this.close();
                }
            };
            document.addEventListener('click', this.documentClickHandler);
        }
        
        /**
         * 加载选项
         */
        loadOptions() {
            this.originalOptions = [];
            const options = this.selectEl.querySelectorAll('option');
            
            options.forEach((option, index) => {
                const optionData = {
                    value: option.value,
                    text: option.textContent,
                    textEn: option.dataset.textEn || option.textContent,
                    disabled: option.disabled,
                    selected: option.selected,
                    index: index
                };
                
                this.originalOptions.push(optionData);
                
                // 设置初始值
                if (option.selected) {
                    this.setValue(option.value, option.textContent);
                }
            });
            
            this.filteredOptions = [...this.originalOptions];
            this.renderOptions();
        }
        
        /**
         * 渲染选项
         */
        renderOptions() {
            this.optionsEl.innerHTML = '';
            
            // 添加自定义输入选项（如果启用且搜索有值）
            if (this.options.allowCustom && this.searchValue.trim()) {
                const customOption = this.createOptionElement({
                    value: this.searchValue,
                    text: `${this.getCustomText()}: "${this.searchValue}"`,
                    isCustom: true
                });
                this.optionsEl.appendChild(customOption);
            }
            
            // 渲染过滤后的选项
            this.filteredOptions.forEach((option, index) => {
                const optionEl = this.createOptionElement(option, index);
                this.optionsEl.appendChild(optionEl);
            });
            
            // 如果没有选项且没有自定义输入，显示无结果提示
            if (this.filteredOptions.length === 0 && (!this.options.allowCustom || !this.searchValue.trim())) {
                const noResultsEl = document.createElement('div');
                noResultsEl.className = 'csg-select-no-results';
                noResultsEl.textContent = this.getNoResultsText();
                this.optionsEl.appendChild(noResultsEl);
            }
        }
        
        /**
         * 创建选项元素
         */
        createOptionElement(option, index = -1) {
            const optionEl = document.createElement('div');
            optionEl.className = 'csg-select-option';
            if (option.isCustom) {
                optionEl.classList.add('custom');
            }
            
            const textEl = document.createElement('div');
            textEl.className = 'csg-select-option-text';
            textEl.textContent = option.text;
            
            optionEl.appendChild(textEl);
            
            // 添加点击事件
            optionEl.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectOption(option);
            });
            
            // 添加鼠标悬停事件
            optionEl.addEventListener('mouseenter', () => {
                this.highlightOption(index);
            });
            
            return optionEl;
        }
        
        /**
         * 选择选项
         */
        selectOption(option) {
            if (option.disabled) return;
            
            this.setValue(option.value, option.text);
            this.close();
            
            // 触发原生select的change事件
            this.selectEl.value = option.value;
            this.selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            
            // 触发自定义回调
            if (this.options.onChange && typeof this.options.onChange === 'function') {
                this.options.onChange(option.value, option.text, this);
            }
            
            // 触发自定义输入回调
            if (option.isCustom && this.options.onCustom && typeof this.options.onCustom === 'function') {
                this.options.onCustom(option.value, this);
            }
        }
        
        /**
         * 设置值
         */
        setValue(value, text) {
            this.selectEl.value = value;
            this.textEl.textContent = text;
            this.textEl.classList.add('has-value');
            
            // 同步隐藏input的值
            if (this.hiddenInput) {
                this.hiddenInput.value = value;
            }
        }
        
        /**
         * 切换显示状态
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        /**
         * 打开下拉框
         */
        open() {
            this.isOpen = true;
            this.displayEl.parentElement.classList.add('open');
            
            if (this.searchInputEl) {
                this.searchInputEl.focus();
            }
            
            this.highlightedIndex = -1;
        }
        
        /**
         * 关闭下拉框
         */
        close() {
            if (!this.displayEl || !this.displayEl.parentElement) {
                return; // 组件已被销毁
            }
            
            this.isOpen = false;
            this.displayEl.parentElement.classList.remove('open');
            
            if (this.searchInputEl) {
                this.searchInputEl.value = '';
                this.searchValue = '';
            }
            
            this.filteredOptions = [...this.originalOptions];
            this.renderOptions();
        }
        
        /**
         * 处理搜索
         */
        handleSearch(value) {
            this.searchValue = value;
            
            if (!value.trim()) {
                this.filteredOptions = [...this.originalOptions];
            } else {
                this.filteredOptions = this.originalOptions.filter(option => 
                    option.text.toLowerCase().includes(value.toLowerCase()) ||
                    option.textEn.toLowerCase().includes(value.toLowerCase())
                );
            }
            
            this.renderOptions();
            
            // 触发搜索回调
            if (this.options.onSearch && typeof this.options.onSearch === 'function') {
                this.options.onSearch(value, this.filteredOptions, this);
            }
        }
        
        /**
         * 处理键盘事件
         */
        handleKeydown(e) {
            if (!this.isOpen) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.open();
                }
                return;
            }
            
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.highlightNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.highlightPrevious();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectHighlighted();
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;
            }
        }
        
        /**
         * 处理搜索框键盘事件
         */
        handleSearchKeydown(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.highlightNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.highlightPrevious();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectHighlighted();
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    break;
            }
        }
        
        /**
         * 高亮下一个选项
         */
        highlightNext() {
            const options = this.optionsEl.querySelectorAll('.csg-select-option');
            if (options.length === 0) return;
            
            this.highlightedIndex = Math.min(this.highlightedIndex + 1, options.length - 1);
            this.updateHighlight();
        }
        
        /**
         * 高亮上一个选项
         */
        highlightPrevious() {
            const options = this.optionsEl.querySelectorAll('.csg-select-option');
            if (options.length === 0) return;
            
            this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
            this.updateHighlight();
        }
        
        /**
         * 更新高亮状态
         */
        updateHighlight() {
            const options = this.optionsEl.querySelectorAll('.csg-select-option');
            options.forEach((option, index) => {
                option.classList.toggle('highlighted', index === this.highlightedIndex);
            });
        }
        
        /**
         * 高亮指定选项
         */
        highlightOption(index) {
            this.highlightedIndex = index;
            this.updateHighlight();
        }
        
        /**
         * 选择高亮的选项
         */
        selectHighlighted() {
            const options = this.optionsEl.querySelectorAll('.csg-select-option');
            if (this.highlightedIndex >= 0 && this.highlightedIndex < options.length) {
                const option = options[this.highlightedIndex];
                option.click();
            }
        }
        
        /**
         * 获取占位符文本
         */
        getPlaceholder() {
            // 这里可以根据语言设置返回不同的占位符
            return this.options.placeholder;
        }
        
        /**
         * 获取无结果文本
         */
        getNoResultsText() {
            return this.options.noResultsText;
        }
        
        /**
         * 获取自定义输入文本
         */
        getCustomText() {
            return this.options.customText;
        }
        
        /**
         * 销毁选择器
         */
        destroy() {
            // 移除文档点击事件监听器
            if (this.documentClickHandler) {
                document.removeEventListener('click', this.documentClickHandler);
                this.documentClickHandler = null;
            }
            
            // 移除事件监听器
            if (this.displayEl) {
                this.displayEl.removeEventListener('click', this.toggle);
                this.displayEl.removeEventListener('keydown', this.handleKeydown);
            }
            
            if (this.searchInputEl) {
                this.searchInputEl.removeEventListener('input', this.handleSearch);
                this.searchInputEl.removeEventListener('keydown', this.handleSearchKeydown);
            }
            
            // 移除DOM元素
            if (this.displayEl && this.displayEl.parentNode) {
                this.displayEl.parentNode.removeChild(this.displayEl);
                this.displayEl = null;
            }
            if (this.dropdownEl && this.dropdownEl.parentNode) {
                this.dropdownEl.parentNode.removeChild(this.dropdownEl);
                this.dropdownEl = null;
            }
        }
    }
    
    // 创建全局实例
    window.CSGSelect = new CSGSelect();
    
    // 页面加载完成后自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.CSGSelect.autoInit();
        });
    } else {
        window.CSGSelect.autoInit();
    }
    
})();
