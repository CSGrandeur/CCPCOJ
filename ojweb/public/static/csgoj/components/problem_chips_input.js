
// 题目选择器查询参数函数
function problemSelectionQueryParams(params) {
    return {
        offset: params.offset,
        limit: params.limit,
        sort: params.sort,
        order: params.order,
        search: params.search
    };
}

/* ========================================
   题号输入组件 (Problem Input Component)
   ======================================== */

// 题号输入组件管理类
class ProblemInputComponent {
           constructor(containerId, options = {}) {
               this.container = document.getElementById(containerId);
               if (!this.container) {
                   console.error(`ProblemInputComponent: Container ${containerId} not found`);
                   return;
               }
               
               this.opts = Object.assign({
                   max: 26,
                   allowDuplicates: false,
                   allowInvalid: false,
                   showCount: true,
                   showActions: true,
                   onChange: null,
                   onError: null
               }, options);
               
               this.chipsInput = null;
               this.sortState = 'none'; // none, asc, desc
               this.sortHistory = []; // 存储排序历史
               this._build();
               this._init();
           }
    
           _build() {
               // 创建组件HTML结构
               this.container.innerHTML = `
                   <div class="problem-input-component">
                       <div class="component-label">
                           <label class="form-label">
                               题目列表
                               ${this.opts.showCount ? '<span class="problem-count-badge badge bg-primary">0/' + this.opts.max + '</span>' : ''}
                               <span class="en-text text-muted d-block">Problem List</span>
                           </label>
                       </div>
                       
                       <!-- 隐藏的原始输入框 -->
                       <input type="hidden" name="problems" value="">
                       
                       <!-- 题号标签输入区域与操作按钮组合 -->
                       <div class="input-group">
                           <!-- 题号标签输入区域 -->
                           <div class="csg-prochips" 
                                data-input="input[name='problems']" 
                                data-max="${this.opts.max}"
                                data-allow-duplicates="${this.opts.allowDuplicates}"
                                data-allow-invalid="${this.opts.allowInvalid}">
                           </div>
                           
                           ${this.opts.showActions ? `
                           <!-- 操作按钮组 -->
                           <div class="btn-group" role="group">
                               <button type="button" class="btn btn-outline-primary btn-sm" 
                                       data-action="open-selector"
                                       title="题目选择器 / Problem Selector">
                                   <i class="bi bi-list-check"></i>
                               </button>
                               <button type="button" class="btn btn-outline-secondary btn-sm" 
                                       data-action="sort"
                                       title="排序 / Sort"
                                       data-sort-state="none">
                                   <i class="bi bi-sort-numeric-down"></i>
                               </button>
                               <button type="button" class="btn btn-outline-danger btn-sm" 
                                       data-action="clear"
                                       title="清空 / Clear">
                                   <i class="bi bi-trash"></i>
                               </button>
                           </div>
                           ` : ''}
                       </div>
                       
                       <div class="form-text">
                           <span class="bilingual-inline">
                               <span>输入题号后回车/逗号分隔，或使用题目选择器（最多${this.opts.max}个题目）</span>
                               <span class="en-text">Enter problem IDs separated by comma, or use problem selector (max ${this.opts.max} problems)</span>
                           </span>
                       </div>
                   </div>
               `;
           }
    
    _init() {
        // 初始化题号标签输入组件
        const chipsContainer = this.container.querySelector('.csg-prochips');
               this.chipsInput = new ProblemChipsInput(chipsContainer, {
                   hiddenInput: 'input[name="problems"]',
                   onChange: (csv, component) => {
                       this._updateCount();
                       // 确保隐藏输入框同步并触发事件
                       const hiddenInput = this.container.querySelector('input[name="problems"]');
                       if (hiddenInput) {
                           hiddenInput.value = csv;
                           // 触发事件通知其他监听器
                           hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                           hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                       }
                       if (typeof this.opts.onChange === 'function') {
                           this.opts.onChange(csv, component);
                       }
                   },
                   onError: (message) => {
                       if (typeof this.opts.onError === 'function') {
                           this.opts.onError(message);
                       } else {
                           alerty.warning(message);
                       }
                   }
               });
        
        // 绑定操作按钮事件
        this._bindEvents();
    }
    
           _bindEvents() {
               // 题目选择器按钮
               this.container.querySelector('[data-action="open-selector"]')?.addEventListener('click', () => {
                   this._openSelector();
               });
               
               // 排序按钮
               this.container.querySelector('[data-action="sort"]')?.addEventListener('click', () => {
                   this._handleSort();
               });
               
               // 清空按钮
               this.container.querySelector('[data-action="clear"]')?.addEventListener('click', () => {
                   this.chipsInput.clear();
               });
           }
    
    _openSelector() {
        if (typeof window.showProblemSelection === 'function') {
            window.showProblemSelection();
        } else {
            console.error('Problem selection modal not available');
        }
    }
    
           _updateCount() {
               const badge = this.container.querySelector('.problem-count-badge');
               if (badge) {
                   const count = this.chipsInput.getCount();
                   badge.textContent = `${count}/${this.opts.max}`;
               }
           }
           
           _handleSort() {
               const currentArray = this.chipsInput.toArray();
               if (currentArray.length === 0) {
                   alerty.warning({
                       message: '题目列表为空，无法排序',
                       message_en: 'Problem list is empty, cannot sort'
                   });
                   return;
               }
               
               const sortBtn = this.container.querySelector('[data-action="sort"]');
               const sortIcon = sortBtn.querySelector('i');
               
               // 保存当前状态到历史记录
               if (this.sortState !== 'none') {
                   this.sortHistory.push({
                       state: this.sortState,
                       array: [...currentArray]
                   });
               }
               
               let newArray = [...currentArray];
               let newState = 'none';
               let newIcon = 'bi-sort-numeric-down';
               let newTitle = '排序 / Sort';
               
               switch (this.sortState) {
                   case 'none':
                       // 正序排序
                       newArray.sort((a, b) => parseInt(a) - parseInt(b));
                       newState = 'asc';
                       newIcon = 'bi-sort-numeric-up';
                       newTitle = '倒序 / Descending';
                       break;
                   case 'asc':
                       // 倒序排序
                       newArray.sort((a, b) => parseInt(b) - parseInt(a));
                       newState = 'desc';
                       newIcon = 'bi-arrow-counterclockwise';
                       newTitle = '恢复 / Restore';
                       break;
                   case 'desc':
                       // 恢复最近一次修改
                       if (this.sortHistory.length > 0) {
                           const lastState = this.sortHistory.pop();
                           newArray = lastState.array;
                           newState = 'none';
                           newIcon = 'bi-sort-numeric-down';
                           newTitle = '排序 / Sort';
                       } else {
                           // 没有历史记录，恢复到原始顺序
                           newArray = currentArray;
                           newState = 'none';
                           newIcon = 'bi-sort-numeric-down';
                           newTitle = '排序 / Sort';
                       }
                       break;
               }
               
               // 更新状态
               this.sortState = newState;
               sortBtn.setAttribute('data-sort-state', newState);
               sortBtn.setAttribute('title', newTitle);
               sortIcon.className = `bi ${newIcon}`;
               
               // 应用新的排序
               this.chipsInput.setFromArray(newArray);
               
               // 显示操作提示
               const messages = {
                   'asc': { zh: '已按正序排序', en: 'Sorted in ascending order' },
                   'desc': { zh: '已按倒序排序', en: 'Sorted in descending order' },
                   'none': { zh: '已恢复原始顺序', en: 'Restored to original order' }
               };
               
               if (messages[newState]) {
                   alerty.success({
                       message: messages[newState].zh,
                       message_en: messages[newState].en
                   });
               }
           }
    
    // 公共API方法
    setValue(csv) {
        this.chipsInput.setFromCSV(csv);
    }
    
    getValue() {
        return this.chipsInput.toCSV();
    }
    
    getArray() {
        return this.chipsInput.toArray();
    }
    
    clear() {
        this.chipsInput.clear();
    }
    
    addProblems(problemIds) {
        this.chipsInput.addMany(problemIds.map(String));
    }
    
    disable() {
        this.chipsInput.disable();
    }
    
    enable() {
        this.chipsInput.enable();
    }
}

/* ========================================
   题目选择器模态框功能 (Problem Selection Modal) - Bootstrap Table 版本
   ======================================== */

// 题目选择器相关变量
let problemSelectionTable = null;
let selectedProblems = new Set();

// 初始化题目选择器模态框
function initProblemSelectionModal() {
    // 检查模态框元素是否存在
    const modalElement = document.getElementById('problemSelectionModal');
    if (!modalElement) {
        console.warn('Problem selection modal element not found');
        return;
    }
    
    const problemSelectionModal = new bootstrap.Modal(modalElement);
    
    // 显示模态框
    window.showProblemSelection = function() {
        if (problemSelectionModal) {
            problemSelectionModal.show();
            // 刷新表格数据
            setTimeout(() => {
                const table = $('#problemSelectionTable');
                if (table.length && typeof table.bootstrapTable === 'function') {
                    table.bootstrapTable('refresh');
                }
            }, 100);
        } else {
            console.error('Problem selection modal not initialized');
        }
    };
    
    // 延迟初始化选择器功能
    setTimeout(initProblemSelectionFeatures, 200);
}

// 题目选择器专用功能
function initProblemSelectionFeatures() {
    const table = $('#problemSelectionTable');
    
    if (!table.length) {
        console.warn('Problem selection table not found');
        return;
    }
    
    // 监听行选择变化
    table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function (e, rows) {
        updateSelectedCount();
    });
    
    // 绑定工具栏按钮事件
    $(document).on('click', '#selectAllBtn', function() {
        table.bootstrapTable('checkAll');
    });
    
    $(document).on('click', '#deselectAllBtn', function() {
        table.bootstrapTable('uncheckAll');
    });
    
    $(document).on('click', '#selectVisibleBtn', function() {
        table.bootstrapTable('checkBy', {field: 'problem_id'});
    });
    
    // 确认选择
    $(document).on('click', '#confirmSelection', function() {
        const selectedRows = table.bootstrapTable('getSelections');
        
        if (selectedRows.length === 0) {
            alerty.warning({
                message: '请至少选择一个题目',
                message_en: 'Please select at least one problem'
            });
            return;
        }
        
        // 将选中的题目ID传递给父页面
        if (window.onProblemSelectionConfirm) {
            const problemIds = selectedRows.map(row => row.problem_id).sort((a, b) => a - b);
            window.onProblemSelectionConfirm(problemIds);
        }
        
        // 关闭模态框
        const modal = bootstrap.Modal.getInstance(document.getElementById('problemSelectionModal'));
        if (modal) {
            modal.hide();
        }
    });
    
    // 更新选中计数
    function updateSelectedCount() {
        const selectedRows = table.bootstrapTable('getSelections');
        const count = selectedRows.length;
        $('#selectedCount').text(count);
        $('#selectedCountFooter').text(count);
    }
}

// 全局题号输入组件实例管理
window.ProblemInputComponents = new Map();

// 创建题号输入组件的便捷函数
window.createProblemInput = function(containerId, options = {}) {
    const component = new ProblemInputComponent(containerId, options);
    window.ProblemInputComponents.set(containerId, component);
    return component;
};

(function(global){
    'use strict';
    
    const SEP_REG = /[,\s，；;]+/g;
    const PRO_ID_REG = /^\d{4}$/; // 4位数字题号规则

    class ProblemChipsInput {
        constructor(el, options = {}) {
            this.root = typeof el === 'string' ? document.querySelector(el) : el;
            if (!this.root) throw new Error('ProblemChipsInput: root element not found');

            const ds = this.root.dataset;
            this.opts = Object.assign({
                hiddenInput: ds.input || null,     // 绑定的隐藏输入选择器
                max: parseInt(ds.max || '999', 10),
                allowDuplicates: ds.allowDuplicates === 'true',
                allowInvalid: ds.allowInvalid === 'true',
                onChange: null,
                onError: null
            }, options);

            this.hidden = this.opts.hiddenInput ? document.querySelector(this.opts.hiddenInput) : null;
            this.values = new Map(); // key -> { id, valid }
            this._build();
            this._loadFromHidden();
        }

        _loadFromHidden() {
            // 从隐藏输入框加载初始值
            if (this.hidden && this.hidden.value) {
                this.setFromCSV(this.hidden.value);
            }
        }

        _build() {
            this.root.classList.add('csg-prochips');

            // 创建输入框
            this.input = document.createElement('input');
            this.input.type = 'text';
            this.input.className = 'csg-prochips-input';
            this.input.placeholder = '输入题号后回车/逗号分隔…';
            this.root.appendChild(this.input);

            // 输入事件处理
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ',' || e.key === ' ') {
                    e.preventDefault();
                    this._commitInput();
                } else if (e.key === 'Backspace') {
                    // 如果输入框为空，删除最后一个chip
                    if (!this.input.value) {
                        const last = Array.from(this.values.keys()).pop();
                        if (last) this.remove(last);
                    }
                } else if (e.key === 'Delete') {
                    // Delete键：如果输入框为空，删除第一个chip
                    if (!this.input.value) {
                        const first = Array.from(this.values.keys())[0];
                        if (first) this.remove(first);
                    }
                } else if (e.key === 'ArrowLeft' && !this.input.value) {
                    // 左箭头：如果输入框为空，选择最后一个chip
                    const lastChip = this.root.querySelector('.chip:last-child');
                    if (lastChip) {
                        lastChip.classList.add('selected');
                        e.preventDefault();
                    }
                } else if (e.key === 'ArrowRight' && !this.input.value) {
                    // 右箭头：如果输入框为空，选择第一个chip
                    const firstChip = this.root.querySelector('.chip:first-child');
                    if (firstChip) {
                        firstChip.classList.add('selected');
                        e.preventDefault();
                    }
                } else if (e.key === 'Escape') {
                    // ESC键：取消选择
                    this.root.querySelectorAll('.chip.selected').forEach(chip => {
                        chip.classList.remove('selected');
                    });
                }
            });

            // 粘贴事件处理
            this.input.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (text && SEP_REG.test(text)) {
                    e.preventDefault();
                    this.addMany(this._parse(text));
                }
            });

            // 失焦时提交输入
            this.input.addEventListener('blur', () => this._commitInput());

            // 委托删除事件
            this.root.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-close[data-id]');
                if (!btn) return;
                const id = btn.getAttribute('data-id');
                this.remove(id);
            });
        }

        _parse(str) {
            return str.split(SEP_REG)
                      .map(s => s.trim())
                      .filter(s => s.length);
        }

        _valid(id) {
            return PRO_ID_REG.test(id);
        }

        _commitInput() {
            const val = this.input.value.trim();
            if (!val) return;
            this.input.value = '';
            const items = this._parse(val);
            if (items.length) this.addMany(items);
        }

        add(id) {
            if (this.values.size >= this.opts.max) {
                this._error(`最多只能添加 ${this.opts.max} 个题目`);
                return false;
            }
            
            const key = id;
            const valid = this._valid(id);
            
            if (!this.opts.allowInvalid && !valid) {
                this._error(`题号格式无效: ${id}`);
                return false;
            }
            
            if (!this.opts.allowDuplicates && this.values.has(key)) {
                this._error(`题号已存在: ${id}`);
                return false;
            }

            this.values.set(key, { id, valid });
            this._renderChip(id, valid);
            this._syncHidden();
            return true;
        }

        _renderChip(id, valid) {
            const chip = document.createElement('span');
            chip.className = 'chip' + (valid ? '' : ' invalid');
            chip.setAttribute('data-id', id);
            chip.setAttribute('draggable', 'true');
            chip.innerHTML = `
                <span>${id}</span>
                <button type="button" class="btn-close" aria-label="删除" data-id="${id}"></button>
            `;
            
            // 添加拖拽事件
            this._addDragEvents(chip);
            
            this.root.insertBefore(chip, this.input);
        }

        addMany(list) {
            const added = [];
            const errors = [];
            
            list.forEach(id => {
                if (this.add(id)) {
                    added.push(id);
                } else {
                    errors.push(id);
                }
            });
            
            if (errors.length > 0) {
                this._error(`部分题号添加失败: ${errors.join(', ')}`);
            }
            
            return added;
        }

        remove(id) {
            this.values.delete(id);
            const node = this.root.querySelector(`.chip[data-id="${CSS.escape(id)}"]`);
            if (node) node.remove();
            this._syncHidden();
        }

        setFromCSV(csv) {
            this.clear();
            if (csv && csv.trim()) {
                this.addMany(this._parse(csv));
            }
        }

        toCSV() {
            return Array.from(this.values.keys()).join(',');
        }

        toArray() {
            return Array.from(this.values.keys());
        }
        
        setFromArray(array) {
            this.clear();
            this.addMany(array.map(String));
        }

        clear() {
            this.values.clear();
            this.root.querySelectorAll('.chip').forEach(n => n.remove());
            this._syncHidden();
        }

        getCount() {
            return this.values.size;
        }

        getValidCount() {
            return Array.from(this.values.values()).filter(v => v.valid).length;
        }

        _syncHidden() {
            const csv = this.toCSV();
            if (this.hidden) {
                this.hidden.value = csv;
                // 触发 change 和 input 事件，确保其他监听器能收到通知
                this.hidden.dispatchEvent(new Event('input', { bubbles: true }));
                this.hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof this.opts.onChange === 'function') {
                this.opts.onChange(csv, this);
            }
        }

        _error(message) {
            if (typeof this.opts.onError === 'function') {
                this.opts.onError(message);
            } else {
                console.warn('ProblemChipsInput:', message);
            }
        }

        _addDragEvents(chip) {
            chip.addEventListener('dragstart', (e) => {
                this.draggedChip = chip;
                chip.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            chip.addEventListener('dragend', (e) => {
                chip.classList.remove('dragging');
                this.draggedChip = null;
            });

            chip.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            chip.addEventListener('drop', (e) => {
                e.preventDefault();
                if (this.draggedChip && this.draggedChip !== chip) {
                    this._moveChip(this.draggedChip, chip);
                }
            });
        }

        _moveChip(draggedChip, targetChip) {
            // 获取两个chip的ID
            const draggedId = draggedChip.getAttribute('data-id');
            const targetId = targetChip.getAttribute('data-id');
            
            // 获取当前DOM顺序（基于实际DOM位置）
            const domChips = Array.from(this.root.querySelectorAll('.chip'));
            const currentDomOrder = domChips.map(chip => chip.getAttribute('data-id'));
            
            // 找到拖拽元素和目标元素在DOM中的位置
            const draggedIndex = currentDomOrder.indexOf(draggedId);
            const targetIndex = currentDomOrder.indexOf(targetId);
            
            if (draggedIndex === -1 || targetIndex === -1) return;
            
            // 计算新的DOM顺序
            const newDomOrder = [...currentDomOrder];
            newDomOrder.splice(draggedIndex, 1);
            newDomOrder.splice(targetIndex, 0, draggedId);
            
            // 重新构建values Map，严格按照新的DOM顺序
            const newValues = new Map();
            newDomOrder.forEach(id => {
                if (this.values.has(id)) {
                    newValues.set(id, this.values.get(id));
                }
            });
            this.values = newValues;
            
            // 重新渲染DOM，确保完全同步
            this._reorderDOM(newDomOrder);
            
            // 更新隐藏输入框
            this._syncHidden();
        }

        _reorderDOM(newOrder) {
            // 获取所有chip元素
            const chips = Array.from(this.root.querySelectorAll('.chip'));
            const chipMap = new Map();
            
            // 创建chip映射
            chips.forEach(chip => {
                const id = chip.getAttribute('data-id');
                chipMap.set(id, chip);
            });
            
            // 移除所有chip
            chips.forEach(chip => chip.remove());
            
            // 按新顺序重新插入
            newOrder.forEach(id => {
                const chip = chipMap.get(id);
                if (chip) {
                    this.root.insertBefore(chip, this.input);
                }
            });
        }

        // 排序功能
        sort() {
            const ids = this.toArray().sort((a, b) => parseInt(a) - parseInt(b));
            
            // 重新构建values Map
            const newValues = new Map();
            ids.forEach(id => {
                if (this.values.has(id)) {
                    newValues.set(id, this.values.get(id));
                }
            });
            this.values = newValues;
            
            // 重新渲染DOM
            this._reorderDOM(ids);
            
            // 更新隐藏输入框
            this._syncHidden();
        }

        // 禁用/启用
        disable() {
            this.root.classList.add('disabled');
            this.input.disabled = true;
        }

        enable() {
            this.root.classList.remove('disabled');
            this.input.disabled = false;
        }
    }

    // 全局暴露
    global.ProblemChipsInput = ProblemChipsInput;

    // 自动初始化所有带有 data-auto-init 属性的组件
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-auto-init="problem-chips"]').forEach(el => {
            new ProblemChipsInput(el);
        });
    });

})(window);
