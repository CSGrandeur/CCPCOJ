/**
 * CSG Animation Library
 * 高性能动画库，支持智能排序动画
 * 
 * 约定：
 * 1. 元素必须有 data-row-id 属性作为唯一标识
 * 2. 排序后的order数组包含teamId，按目标顺序排列
 * 3. 容器元素作为动画的父容器
 */
if(typeof CSGAnim == 'undefined') {

    class CSGAnim {
        constructor() {
            this.supportsWebAnimations = typeof Element.prototype.animate === 'function';
            // 动画状态管理
            this.activeAnimations = new WeakMap(); // element -> Animation对象
            this.animationQueues = new Map(); // container -> 队列
            this.isAnimating = new Map(); // container -> 是否正在动画
            this.risingElements = new WeakSet(); // 标记正在上升的元素
            // z-index 自增计数器（从100开始，表头使用100000）
            this.risingZIndexCounter = 100;
        }
    
        /**
         * 基于速度计算动画时长
         */
        calculateDurationBySpeed(distance, speed = 300, minDuration = 200, maxDuration = 3000, speedMultiplier = 1.0) {
            const absDistance = Math.abs(distance);
            const baseDuration = (absDistance / speed) * 1000; // 转为毫秒
            const adjustedDuration = baseDuration / speedMultiplier;
            
            return Math.max(minDuration, Math.min(maxDuration, adjustedDuration));
        }

        /**
         * 读取元素当前的transform状态（考虑正在进行的动画）
         */
        getCurrentTransform(element) {
            const computedStyle = window.getComputedStyle(element);
            const transform = computedStyle.transform;
            
            if (!transform || transform === 'none') {
                return { x: 0, y: 0 };
            }
            
            // 解析 matrix 或 matrix3d
            const matrix = transform.match(/matrix(?:3d)?\(([^)]+)\)/);
            if (matrix) {
                const values = matrix[1].split(',').map(v => parseFloat(v.trim()));
                // matrix: m11, m12, m21, m22, tx, ty
                // matrix3d: 16个值，最后4个是 tx, ty, tz, w
                if (values.length === 6) {
                    return { x: values[4] || 0, y: values[5] || 0 };
                } else if (values.length === 16) {
                    return { x: values[12] || 0, y: values[13] || 0 };
                }
            }
            
            return { x: 0, y: 0 };
        }

        /**
         * 取消动画（保护正在上升的队伍）
         */
        cancelAnimations(container, risingTeamIds = []) {
            const elements = container.querySelectorAll('[data-row-id]');
            elements.forEach(element => {
                const teamId = element.getAttribute('data-row-id');
                const isRising = risingTeamIds.includes(teamId);
                const wasRising = this.risingElements.has(element);
                
                // 如果正在上升（新的或之前的），保护它（不取消）
                if (isRising || wasRising) {
                    // 关键修复：对于正在上升的队伍，我们需要读取当前动画的中间状态
                    // 但在取消动画时，我们需要先获取当前的transform值，然后保存到style中
                    // 这样即使动画被取消，元素也会保持在当前的视觉位置
                    const activeAnim = this.activeAnimations.get(element);
                    if (activeAnim && activeAnim.playState === 'running') {
                        // 动画正在运行，读取当前的transform值并保存到style中
                        const currentTransform = this.getCurrentTransform(element);
                        // 强制同步布局，确保读取到的是当前动画的中间状态
                        element.offsetHeight;
                        // 将当前动画的transform值保存到style中，这样取消动画后元素会保持当前位置
                        element.style.transform = `translate3d(${currentTransform.x.toFixed(2)}px, ${currentTransform.y.toFixed(2)}px, 0)`;
                        // 然后取消动画
                        try {
                            activeAnim.cancel();
                        } catch (e) {
                            // 忽略取消错误
                        }
                        // 注意：不删除activeAnim，因为我们需要继续保护这个元素
                        
                    }
                    return; // 不取消上升队伍的动画（或已经保存了当前状态）
                }
                
                // 取消其他队伍的动画
                const activeAnim = this.activeAnimations.get(element);
                if (activeAnim) {
                    try {
                        activeAnim.cancel();
                    } catch (e) {
                        // 忽略取消错误
                    }
                    this.activeAnimations.delete(element);
                }
                
                // 立即清理transform，避免回弹（除非是正在上升的队伍）
                element.style.transform = '';
                element.style.transition = '';
                element.style.willChange = 'auto';
            });
        }

        /**
         * 处理队列中的下一个动画
         */
        async processQueue(container) {
            const queue = this.animationQueues.get(container);
            if (!queue || queue.length === 0) {
                return;
            }

            const next = queue.shift();
            if (next) {
                try {
                    await this.sortAnimateInternal(next.container, next.order, next.options);
                    if (next.resolve) next.resolve();
                } catch (error) {
                    if (next.resolve) next.resolve();
                }
            }
        }

        /**
         * 智能排序动画 - 主要接口（带队列管理）
         */
        async sortAnimate(container, order, options = {}) {
            const {
                queue = true, // 是否使用队列
                cancelPrevious = true, // 是否取消之前的动画
                risingTeamIds = [] // 上升队伍ID列表（用于滚榜保护）
            } = options;

            // 队列管理：如果启用队列且已有动画在进行
            if (queue && this.isAnimating.get(container)) {
                // 如果允许取消之前的动画，清空队列并取消当前动画，立即开始新动画
                if (cancelPrevious) {
                    // 取消正在进行的动画（保护上升队伍，如果有）
                    this.cancelAnimations(container, risingTeamIds);
                    // 清空队列（丢弃所有等待中的动画）
                    this.animationQueues.set(container, []);
                    // 重置动画状态，立即开始新动画
                    this.isAnimating.set(container, false);
                    // 直接开始新动画，实现快速响应
                    return this.sortAnimateInternal(container, order, options);
                } else {
                    // 如果不取消，保持队列行为：排队等待
                    return new Promise((resolve) => {
                        const queue = this.animationQueues.get(container) || [];
                        queue.push({ 
                            container, 
                            order, 
                            options: { ...options, queue: false }, 
                            resolve 
                        });
                        this.animationQueues.set(container, queue);
                    });
                }
            }

            return this.sortAnimateInternal(container, order, options);
        }

        /**
         * 内部排序动画实现
         */
        async sortAnimateInternal(container, order, options = {}) {
            const {
                duration = 600,
                speedMultiplier = 1.0,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null,
                useFlip = true,
                // 队列相关
                cancelPrevious = true,
                // 滚榜专用选项
                useSpeedBasedDuration = false,
                speed = 300,
                risingTeamIds = [], // 上升队伍ID列表
                mergeAnimations = false
            } = options;
    
            // 如果启用取消且已有动画，取消之前的动画（但保护正在上升的队伍）
            // 关键修复：对于mergeAnimations模式，我们不应该在这里取消动画
            // 而是应该在记录初始位置后、DOM更新前，在同一帧内保存状态并取消
            // 这样可以避免视觉闪烁
            if (cancelPrevious && !mergeAnimations) {
                this.cancelAnimations(container, risingTeamIds);
            } else if (cancelPrevious && mergeAnimations) {
                // mergeAnimations模式：收集所有正在上升的队伍ID
                // 但不在此时取消动画，而是在记录初始位置时处理
                const allRisingTeamIds = [...risingTeamIds];
                if (container) {
                    const allElements = container.querySelectorAll('[data-row-id]');
                    allElements.forEach(element => {
                        if (this.risingElements.has(element)) {
                            const teamId = element.getAttribute('data-row-id');
                            if (teamId && !allRisingTeamIds.includes(teamId)) {
                                allRisingTeamIds.push(teamId);
                                
                            }
                        }
                    });
                }
                // 不在这里取消，而是在记录初始位置时保存状态
            }

            // 标记开始动画
            this.isAnimating.set(container, true);

            try {
            const adjustedDuration = Math.max(100, Math.min(3000, duration / speedMultiplier));
            const currentElements = Array.from(container.querySelectorAll('[data-row-id]'));
            
            if (currentElements.length === 0) {
                    this.isAnimating.set(container, false);
                    this.processQueue(container);
                    if (onComplete) onComplete();
                return Promise.resolve();
            }
    
            const sortedElements = this.reorderElements(currentElements, order);
            const needsAnimation = this.checkIfNeedsAnimation(currentElements, sortedElements);
            
            if (!needsAnimation) {
                    this.isAnimating.set(container, false);
                    this.processQueue(container);
                if (onComplete) onComplete();
                return Promise.resolve();
            }
            
                let animationPromise;
            if (useFlip) {
                    animationPromise = this.flipSortAnimate(sortedElements, {
                    ...options,
                        duration: adjustedDuration,
                        risingTeamIds: risingTeamIds
                });
            } else {
                    animationPromise = this.simpleSortAnimate(currentElements, sortedElements, {
                    ...options,
                    duration: adjustedDuration
                });
                }

                // 不要存储container -> Promise，因为动画引用已经在flipSortAnimate中按element存储了

                await animationPromise;

                // 清理
                // 不再需要删除container的引用，因为动画引用是按element存储的
                this.isAnimating.set(container, false);
                
                // 处理队列
                this.processQueue(container);

            } catch (error) {
                // 清理container状态
                this.isAnimating.set(container, false);
                this.processQueue(container);
                throw error;
            }
        }
    
        /**
         * FLIP技术排序动画（增强版：区分上升和下降队伍）
         */
        async flipSortAnimate(sortedElements, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null,
                // 新增选项
                useSpeedBasedDuration = false,
                speed = 300,
                minDuration = 200,
                maxDuration = 3000,
                speedMultiplier = 1.0,
                mergeAnimations = false,
                risingTeamIds = [], // 上升队伍ID列表
                risingEasing = 'cubic-bezier(0.25, 0.1, 0.25, 1)', // ease-out，优雅减速
                fallingDuration = 400, // 下降队伍固定duration，快速落位
                fallingEasing = 'cubic-bezier(0.4, 0, 1, 1)' // ease-in，快速开始
            } = options;
    
            if (sortedElements.length === 0) {
                return Promise.resolve();
            }
    
            // 检查Web Animations API支持
            if (!this.supportsWebAnimations) {
                console.warn('Web Animations API not supported, falling back to CSS animation');
                return this.flipSortAnimateCSS(sortedElements, options);
            }
    
            try {
                // 准备：只有滚榜模式（mergeAnimations=true）才需要保护上升队伍
                const risingElements = new Set();
                if (mergeAnimations) {
                    sortedElements.forEach(element => {
                        const teamId = element.getAttribute('data-row-id');
                        const isRising = risingTeamIds.includes(teamId);
                        const wasRising = this.risingElements.has(element);
                        const shouldProtect = isRising || wasRising;
                        
                        if (shouldProtect) {
                            risingElements.add(element);
                            this.risingElements.add(element);
                        } else {
                            // 不是上升队伍，取消动画并清理transform
                            const activeAnim = this.activeAnimations.get(element);
                            if (activeAnim) {
                                try {
                                    activeAnim.cancel();
                                } catch (e) {}
                                this.activeAnimations.delete(element);
                            }
                            element.style.transform = '';
                            element.style.willChange = 'auto';
                            this.risingElements.delete(element);
                        }
                    });
                } else {
                    // 标准榜单模式：取消所有动画
                    sortedElements.forEach(element => {
                        const activeAnim = this.activeAnimations.get(element);
                        if (activeAnim) {
                            try {
                                activeAnim.cancel();
                            } catch (e) {}
                            this.activeAnimations.delete(element);
                        }
                        element.style.transform = '';
                        element.style.willChange = 'auto';
                        this.risingElements.delete(element);
                    });
                }
                
                // 同步布局，确保transform清理生效
                sortedElements[0]?.offsetHeight;
                await new Promise(resolve => requestAnimationFrame(() => {
                    requestAnimationFrame(resolve);
                }));
                
                // 记录初始位置
                const initialPositions = new Map();
                sortedElements.forEach((element, index) => {
                    const teamId = element.getAttribute('data-row-id');
                    const isRising = risingElements.has(element);
                    const shouldReadTransform = mergeAnimations && (isRising || this.risingElements.has(element));
                    
                    let currentTransform = { x: 0, y: 0 };
                    
                    if (shouldReadTransform) {
                        const activeAnim = this.activeAnimations.get(element);
                        if (activeAnim && activeAnim.playState === 'running') {
                            // 读取并保存当前动画状态
                            element.offsetHeight;
                            currentTransform = this.getCurrentTransform(element);
                            element.style.transform = `translate3d(${currentTransform.x.toFixed(2)}px, ${currentTransform.y.toFixed(2)}px, 0)`;
                            element.offsetHeight;
                            try {
                                activeAnim.cancel();
                            } catch (e) {}
                            element.offsetHeight;
                        } else {
                            // 读取已保存的transform
                            currentTransform = this.getCurrentTransform(element);
                        }
                    }
                    
                    // 获取元素的当前rect（已经考虑了transform的影响）
                    // 注意：getBoundingClientRect() 返回的是元素的视觉位置（已包含transform）
                    // 对于正在运行的动画，如果已经保存了transform到style，这会反映保存后的位置
                    const rect = element.getBoundingClientRect();
                    
                    initialPositions.set(index, {
                        top: rect.top,
                        left: rect.left,
                        currentX: currentTransform.x,
                        currentY: currentTransform.y,
                        element: element,
                        isRising: isRising,
                        teamId: teamId,
                        // 关键：只有mergeAnimations=true且确实有非零transform时，才标记为hasExistingTransform
                        hasExistingTransform: mergeAnimations && shouldReadTransform && (Math.abs(currentTransform.x) > 0.01 || Math.abs(currentTransform.y) > 0.01)
                    });
                });
    

                // FLIP技术：Last + Invert
                // 关键修复：在同一帧内完成DOM更新、应用反向transform和启动新动画，确保无缝衔接
                let animationsRef = null; // 用于保存动画引用，以便后续等待完成
                await new Promise(resolve => {
                    requestAnimationFrame(() => {
                        // 在同一帧内完成以下操作，避免视觉闪烁：
                        // 1. 更新DOM到最终状态
                        // 2. 强制同步布局
                        // 3. 立即计算并应用反向transform
                        // 4. 立即启动新动画（不要等到下一个requestAnimationFrame）
                        // 这样新旧动画可以无缝衔接，完全消除卡顿
                        this.updateDOMToFinalState(sortedElements);
                        
                        // 强制同步布局，确保DOM更新生效
                        sortedElements[0]?.offsetHeight;
                        
                        // 计算每个元素的移动距离和duration
                        const animationData = [];
                        sortedElements.forEach((element, index) => {
                            const rect = element.getBoundingClientRect();
                            const initial = initialPositions.get(index);
                            if (!initial) return;
                            
                            // 计算移动距离（FLIP技术）
                            // 关键修复：getBoundingClientRect() 返回的是元素的视觉位置（已包含transform）
                            // - initial.top/left: 记录时的视觉位置（已包含transform）
                            // - rect.top/left: DOM更新后的最终位置
                            // - deltaY/deltaX: 反向transform的偏移量（让元素看起来还在初始视觉位置）
                            // 
                            // FLIP原理：
                            // 1. 元素当前视觉位置 = initial.top（已包含transform）
                            // 2. DOM更新后，元素在最终位置 = rect.top
                            // 3. 应用反向transform，让元素看起来还在initial.top
                            // 4. 反向transform = initial.top - rect.top
                            // 5. 动画时，逐渐移除transform，元素平滑移动到rect.top
                            //
                            // 对于有transform的元素：
                            // - 当前有transform: translate3d(currentX, currentY, 0)
                            // - 当前视觉位置 = DOM位置 + transform = initial.top
                            // - 最终位置 = rect.top
                            // - 反向transform应该让元素从rect.top移动到initial.top
                            // - 反向transform = initial.top - rect.top
                            // - 叠加当前transform：新transform = 当前transform + 反向transform
                            
                            let deltaX, deltaY;
                            
                            if (initial.hasExistingTransform && mergeAnimations) {
                                // 有transform的情况：
                                // - 当前视觉位置 = initial.top/left（已包含transform）
                                // - 最终位置 = rect.top/left
                                // - 反向transform = 当前视觉位置 - 最终位置
                                // - 新transform = 当前transform + 反向transform
                                // 
                                // 注意：delta就是反向transform，不是实际DOM位置的差值
                                deltaX = initial.left - rect.left;
                                deltaY = initial.top - rect.top;
                                
                                
                            } else {
                                // 没有transform：反向transform = 初始位置 - 最终位置
                                deltaX = initial.left - rect.left;
                                deltaY = initial.top - rect.top;
                            }
                            
                            // 如果距离很小，跳过动画，直接清理transform
                            if (Math.abs(deltaY) < 1 && Math.abs(deltaX) < 1) {
                                element.style.transform = '';
                                element.style.willChange = 'auto';
                                return;
                            }

                            const isRising = initial.isRising;
                            let distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
                            // 关键修复：FLIP技术中，deltaY = initial.top - rect.top
                            // - 如果队伍上升（从下到上）：initial.top > rect.top，所以 deltaY > 0
                            // - 如果队伍下降（从上到下）：initial.top < rect.top，所以 deltaY < 0
                            // 所以上升应该是 deltaY > 0（正数）
                            const isActuallyRising = deltaY > 0;
                            
                            // 根据选项计算duration和easing
                            let elementDuration, elementEasing;
                            
                            // 关键修复：上升队伍（在risingTeamIds中且向上移动）使用基于速度的计算（慢）
                            // 其他所有队伍（下降或不变）使用固定的duration（快）
                            if (isRising && isActuallyRising && useSpeedBasedDuration) {
                                // 上升队伍：基于速度计算，确保缓慢优雅
                                // 关键优化：如果上升距离超出视口，限制动画距离和duration让它到达视口外就结束
                                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                                const risingDistance = Math.abs(deltaY); // 上升的绝对距离
                                
                                // 如果上升距离超过视口高度，限制动画距离到视口外
                                let targetDuration;
                                
                                // if (risingDistance > viewportHeight) {
                                //     // 计算到达视口外90%位置的距离（按比例缩放deltaX和deltaY）
                                //     const distanceToViewportEdge = viewportHeight * 0.9;
                                //     const scaleFactor = distanceToViewportEdge / risingDistance;
                                //     // 按比例缩放deltaX和deltaY，保持方向不变
                                //     deltaX = deltaX * scaleFactor;
                                //     deltaY = deltaY * scaleFactor;
                                    
                                //     // 重新计算distance（基于限制后的deltaX和deltaY）
                                //     distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
                                    
                                //     // 基于实际动画距离（限制后的distance）计算duration
                                //     targetDuration = this.calculateDurationBySpeed(
                                //         distance, speed, minDuration, maxDuration, 1.0
                                //     );
                                // } else {
                                    // 正常计算，完整距离
                                    targetDuration = this.calculateDurationBySpeed(
                                        distance, speed, minDuration, maxDuration, 1.0  // 上升队伍不使用speedMultiplier加速
                                    );
                                // }
                                
                                // 保存实际动画距离（可能被限制）
                                elementDuration = targetDuration;
                                // 使用线性缓动，无加速减速
                                elementEasing = 'linear';
                            } else {
                                // 所有其他队伍（下降或水平移动）：使用固定的duration，快速落位
                                // 下降队伍使用fallingDuration，其他队伍使用duration
                                if (deltaY < 0) {
                                    // 明确下降的队伍（deltaY < 0）：使用fallingDuration
                                    elementDuration = fallingDuration / speedMultiplier;
                                    elementEasing = fallingEasing;
                                } else {
                                    // 水平移动或deltaY=0的队伍：使用普通duration
                                    elementDuration = duration / speedMultiplier;
                                    elementEasing = easing;
                                }
                                
                            }

                            animationData.push({
                                element: element,
                                deltaX: deltaX,
                                deltaY: deltaY,
                                duration: elementDuration,
                                easing: elementEasing,
                                isRising: isRising
                            });
                        });

                        // 应用反向transform
                        animationData.forEach(data => {
                            const elementIndex = sortedElements.indexOf(data.element);
                            const initial = initialPositions.get(elementIndex);
                            
                            // 计算反向transform的起点值
                            let startX, startY;
                            
                            // 关键修复：只有mergeAnimations=true且initial明确标记hasExistingTransform=true时，才叠加
                            // 否则直接使用delta（从头开始完整动画）
                            if (mergeAnimations && initial && initial.hasExistingTransform) {
                                // 有transform：叠加新delta（从当前位置继续）
                                startX = initial.currentX + data.deltaX;
                                startY = initial.currentY + data.deltaY;
                            } else {
                                // 没有transform：直接使用delta（完整动画，从头开始）
                                startX = data.deltaX;
                                startY = data.deltaY;
                            }
                            
                            // 应用反向transform（强制设置，确保起点正确）
                            data.element.style.transform = `translate3d(${startX.toFixed(2)}px, ${startY.toFixed(2)}px, 0)`;
                            data.element.offsetHeight;
                            
                            // 保存起点值
                            data.startX = startX;
                            data.startY = startY;
                            
                            data.element.style.willChange = 'transform';
                            if (data.isRising) {
                                // 使用自增z-index，确保每个上升队伍都有唯一的z-index
                                data.element.style.zIndex = String(this.risingZIndexCounter++);
                            }
                        });
                        
                        // 强制同步布局，确保所有transform都已应用
                        animationData.forEach(data => data.element.offsetHeight);

                        // 启动新动画
                        const animations = [];
                        sortedElements.forEach((element) => {
                            const data = animationData.find(d => d.element === element);
                            if (!data) return;
                            
                            const startX = data.startX;
                            const startY = data.startY;
                            const actualDistance = Math.sqrt(startX * startX + startY * startY);
                            
                            // 距离太小，跳过动画
                            if (actualDistance < 0.5) {
                                element.style.transform = '';
                                element.style.willChange = 'auto';
                                // 不移除z-index，让上升队伍的z-index自然保留
                                return;
                            }
                            
                            // 调整duration：小距离按比例缩短，但至少50ms
                            let adjustedDuration = data.duration;
                            if (actualDistance < 3 && data.duration > 100) {
                                adjustedDuration = Math.max(50, data.duration * (actualDistance / 3));
                            }
                            
                            // 创建动画
                            const anim = element.animate([
                                { transform: `translate3d(${startX.toFixed(2)}px, ${startY.toFixed(2)}px, 0)` },
                                { transform: 'translate3d(0, 0, 0)' }
                            ], {
                                duration: adjustedDuration,
                                easing: data.easing,
                                fill: 'forwards'
                            });
                            
                            try {
                                anim.play();
                                this.activeAnimations.set(element, anim);
                                anim._csgElement = element;
                                animations.push(anim);
                            } catch (e) {
                                // 动画创建失败，清理样式
                                element.style.transform = '';
                                element.style.willChange = 'auto';
                                // 不移除z-index，让上升队伍的z-index自然保留
                            }
                        });
                        
                        animationsRef = animations;
                        
                        resolve();
                    });
                });

                if (onStart) onStart();

                // 等待所有动画完成（使用Promise.allSettled避免一个失败全部失败）
                const animations = animationsRef || [];
                await Promise.allSettled(animations.map(anim => anim.finished));

                // 清理：立即清理transform和样式，避免回弹
                animations.forEach(anim => {
                    try {
                        anim.cancel(); // 确保动画停止
                    } catch (e) {
                        // 忽略错误
                    }
                    
                    const element = anim._csgElement || (anim.effect && anim.effect.target);
                    if (element) {
                        // 立即清理transform，避免回弹
                    element.style.transform = '';
                        element.style.willChange = 'auto';
                        // 不移除z-index，让上升队伍的z-index自然保留
                        
                        // 清理引用
                        this.activeAnimations.delete(element);
                        this.risingElements.delete(element);
                        delete anim._csgElement;
                    }
                });

                if (onComplete) onComplete();
    
            } catch (error) {
                // 发生错误时也要清理transform
                sortedElements.forEach(element => {
                    element.style.transform = '';
                    element.style.willChange = 'auto';
                    // 不移除z-index，让上升队伍的z-index自然保留
                    this.activeAnimations.delete(element);
                    this.risingElements.delete(element);
                });
                console.warn('Web Animations API failed, falling back to CSS animation:', error);
                return this.flipSortAnimateCSS(sortedElements, options);
            }
        }
    
        /**
         * 简单排序动画（非FLIP）
         * @param {Array} currentElements - 当前元素数组
         * @param {Array} sortedElements - 排序后元素数组
         * @param {Object} options - 动画选项
         * @returns {Promise} 动画完成的Promise
         */
        async simpleSortAnimate(currentElements, sortedElements, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null
            } = options;
    
            // 计算需要移动的元素
            const animationData = this.calculateMovements(currentElements, sortedElements);
            
            if (animationData.length === 0) {
                return Promise.resolve();
            }
    
            // 检查Web Animations API支持
            if (!this.supportsWebAnimations) {
                return this.simpleSortAnimateCSS(animationData, options);
            }
    
            try {
                // 回调：动画开始
                if (onStart) onStart();
    
                // 创建所有动画
                const animations = animationData.map(data => {
                    return data.element.animate([
                        { transform: 'translate3d(0, 0, 0)' },
                        { transform: `translate3d(0, ${data.deltaY}px, 0)` }
                    ], {
                        duration: duration,
                        easing: easing,
                        fill: 'forwards'
                    });
                });
    
                // 一次性启动所有动画
                animations.forEach(anim => anim.play());
    
                // 等待所有动画完成（使用Promise.allSettled避免一个失败全部失败）
                await Promise.allSettled(animations.map(anim => anim.finished));
    
                // 更新DOM到最终状态
                this.updateDOMToFinalState(sortedElements);
    
                // 清理动画和transform
                animations.forEach(anim => {
                    try {
                        anim.cancel();
                    } catch (e) {
                        // 忽略错误
                    }
                    const element = anim.effect && anim.effect.target;
                    if (element) {
                        element.style.transform = '';
                        element.style.willChange = 'auto';
                    }
                });
    
                // 回调：动画完成
                if (onComplete) onComplete();
    
            } catch (error) {
                // 发生错误时也要清理transform
                animationData.forEach(data => {
                    data.element.style.transform = '';
                    data.element.style.willChange = 'auto';
                });
                console.warn('Web Animations API failed, falling back to CSS animation:', error);
                return this.simpleSortAnimateCSS(animationData, options);
            }
        }
    
        /**
         * 重新排序元素
         * @param {Array} currentElements - 当前元素数组
         * @param {Array} order - 目标顺序的itemKey数组
         * @returns {Array} 按目标顺序排列的元素数组
         */
        reorderElements(currentElements, order) {
            const elementMap = new Map();
            currentElements.forEach(element => {
                const itemKey = element.getAttribute('data-row-id');
                if (itemKey) {
                    elementMap.set(itemKey, element);
                }
            });
    
            return order.map(itemKey => elementMap.get(itemKey)).filter(Boolean);
        }
    
        /**
         * 检查是否需要动画
         * @param {Array} currentElements - 当前元素数组
         * @param {Array} sortedElements - 排序后元素数组
         * @returns {boolean} 是否需要动画
         */
        checkIfNeedsAnimation(currentElements, sortedElements) {
            
            // 过滤掉undefined值
            const validSortedElements = sortedElements.filter(Boolean);
            
            
            if (currentElements.length !== validSortedElements.length) {
                
                return true;
            }
    
            // 比较每个位置的元素是否相同
            for (let i = 0; i < currentElements.length; i++) {
                if (currentElements[i] !== validSortedElements[i]) {
                    
                    
                    
                    
                    
                    return true;
                }
            }
            
            // 如果所有元素都相同，显示前几个元素的ID用于验证
            
            
            
            // 检查是否有任何元素位置发生变化
            let hasChanges = false;
            for (let i = 0; i < currentElements.length; i++) {
                const currentId = currentElements[i]?.getAttribute('data-row-id');
                const sortedId = validSortedElements[i]?.getAttribute('data-row-id');
                if (currentId !== sortedId) {
                    
                    hasChanges = true;
                }
            }
            
            if (!hasChanges) {
                
                return false;
            } else {
                
                return true;
            }
        }
    
        /**
         * 计算移动数据
         * @param {Array} currentElements - 当前元素数组
         * @param {Array} sortedElements - 排序后元素数组
         * @returns {Array} 动画数据数组
         */
        calculateMovements(currentElements, sortedElements) {
            const movements = [];
            const currentPositions = new Map();
            const sortedPositions = new Map();
    
            // 记录当前位置
            currentElements.forEach((element, index) => {
                const itemKey = element.getAttribute('data-row-id');
                if (itemKey) {
                    currentPositions.set(itemKey, { element, index });
                }
            });
    
            // 记录目标位置
            sortedElements.forEach((element, index) => {
                const itemKey = element.getAttribute('data-row-id');
                if (itemKey) {
                    sortedPositions.set(itemKey, { element, index });
                }
            });
    
            // 计算需要移动的元素
            for (const [itemKey, current] of currentPositions) {
                const sorted = sortedPositions.get(itemKey);
                if (sorted && current.index !== sorted.index) {
                    const currentRect = current.element.getBoundingClientRect();
                    const targetTop = sorted.index * currentRect.height;
                    const deltaY = targetTop - currentRect.top;
    
                    movements.push({
                        element: current.element,
                        deltaY: deltaY,
                        isUp: deltaY < 0,
                        itemKey: itemKey
                    });
                }
            }
    
            return movements;
        }
    
        /**
         * FLIP技术CSS降级方案
         * @param {Array} sortedElements - 排序后元素数组
         * @param {Object} options - 动画选项
         * @returns {Promise} 动画完成的Promise
         */
        async flipSortAnimateCSS(sortedElements, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null,
                risingTeamIds = []
            } = options;
    
            return new Promise(async resolve => {
                // 强制清除所有正在进行的动画和transform，确保DOM稳定
                sortedElements.forEach(element => {
                    element.style.transform = '';
                    element.style.transition = '';
                    element.style.willChange = 'auto';
                });
                
                // 强制同步布局，确保所有样式计算完成
                sortedElements[0]?.offsetHeight;
                // 使用 requestAnimationFrame 确保浏览器完成渲染
                await new Promise(r => requestAnimationFrame(() => {
                    requestAnimationFrame(r);
                }));
                
                // 记录初始位置
                const initialPositions = sortedElements.map(element => {
                    const rect = element.getBoundingClientRect();
                    return {
                        top: rect.top,
                        left: rect.left,
                        element: element
                    };
                });
    
                // FLIP技术：Last + Invert - 在同一帧内完成DOM更新和反向transform应用
                await new Promise(r => {
                    requestAnimationFrame(() => {
                        // 在同一帧内：更新DOM + 立即应用反向transform
                        // 这样浏览器不会渲染中间状态
                        
                        // 更新DOM到最终状态
                        this.updateDOMToFinalState(sortedElements);
                        
                        // 强制同步布局，确保DOM更新完成
                        sortedElements[0]?.offsetHeight;
                        
                        // 立即计算并应用反向transform（同步执行）
                        sortedElements.forEach((element, index) => {
                            const rect = element.getBoundingClientRect();
                            const initial = initialPositions[index];
                            const deltaX = initial.left - rect.left;
                            const deltaY = initial.top - rect.top;
                            
                            // 检查是否是上升队伍
                            const teamId = element.getAttribute('data-row-id');
                            const isRising = risingTeamIds.includes(teamId);
    
                            // 立即应用反向transform和transition，避免用户看到最终位置
                            element.style.transform = `translate3d(${deltaX}px, ${deltaY}px, 0)`;
                            element.style.transition = `transform ${duration}ms ${easing}`;
                            // 上升队伍设置自增的 z-index，避免被其他队伍遮挡
                            if (isRising) {
                                element.style.zIndex = String(this.risingZIndexCounter++);
                            }
                        });
                        
                        // 强制重排确保transform生效
                        sortedElements[0]?.offsetHeight;
                        
                        r();
                    });
                });
    
                // 回调：动画开始
                if (onStart) onStart();
    
                // 动画到最终位置
                sortedElements.forEach(element => {
                    element.style.transform = 'translate3d(0, 0, 0)';
                });
    
                // 等待动画完成
                setTimeout(() => {
                    sortedElements.forEach(element => {
                        element.style.transform = '';
                        element.style.transition = '';
                        element.style.willChange = 'auto';
                        // 不移除z-index，让上升队伍的z-index自然保留
                    });
                    if (onComplete) onComplete();
                    resolve();
                }, duration + 50);
            });
        }
    
        /**
         * 简单排序动画CSS降级方案
         * @param {Array} animationData - 动画数据数组
         * @param {Object} options - 动画选项
         * @returns {Promise} 动画完成的Promise
         */
        async simpleSortAnimateCSS(animationData, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null
            } = options;
    
            return new Promise(resolve => {
                // 回调：动画开始
                if (onStart) onStart();
    
                // 设置初始状态
                animationData.forEach(data => {
                    data.element.style.transform = 'translate3d(0, 0, 0)';
                    data.element.style.transition = `transform ${duration}ms ${easing}`;
                });
    
                // 强制重排
                animationData[0]?.element.offsetHeight;
    
                // 动画到目标位置
                animationData.forEach(data => {
                    data.element.style.transform = `translate3d(0, ${data.deltaY}px, 0)`;
                });
    
                // 等待动画完成
                setTimeout(() => {
                    animationData.forEach(data => {
                        data.element.style.transform = '';
                        data.element.style.transition = '';
                    });
                    if (onComplete) onComplete();
                    resolve();
                }, duration + 50);
            });
        }
    
        /**
         * 更新DOM到最终状态
         * @param {Array} elements - 按最终顺序排列的元素数组
         */
        updateDOMToFinalState(elements) {
            // 找到第一个元素的父容器
            const parent = elements[0]?.parentNode;
            if (!parent) return;
    
            // 使用DocumentFragment批量操作
            const fragment = document.createDocumentFragment();
            elements.forEach(element => {
                if (element && element.parentNode) {
                    fragment.appendChild(element);
                }
            });
            parent.appendChild(fragment);
    
            // 强制重排，确保DOM更新完成
            parent.offsetHeight;
        }
    
        /**
         * 简单的批量动画（非FLIP）
         * @param {Array} animationData - 动画数据数组
         * @param {Object} options - 动画选项
         * @returns {Promise} 动画完成的Promise
         */
        async batchAnimate(animationData, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null
            } = options;
    
            if (animationData.length === 0) {
                return Promise.resolve();
            }
    
            // 检查Web Animations API支持
            if (!this.supportsWebAnimations) {
                return this.batchAnimateCSS(animationData, options);
            }
    
            try {
                // 回调：动画开始
                if (onStart) onStart();
    
                // 创建所有动画
                const animations = animationData.map(data => {
                    return data.element.animate([
                        { transform: 'translate3d(0, 0, 0)' },
                        { transform: `translate3d(0, ${data.deltaY}px, 0)` }
                    ], {
                        duration: duration,
                        easing: easing,
                        fill: 'forwards'
                    });
                });
    
                // 一次性启动所有动画
                animations.forEach(anim => anim.play());
    
                // 等待所有动画完成
                await Promise.all(animations.map(anim => anim.finished));
    
                // 清理动画
                animations.forEach(anim => anim.cancel());
    
                // 回调：动画完成
                if (onComplete) onComplete();
    
            } catch (error) {
                console.warn('Web Animations API failed, falling back to CSS animation:', error);
                return this.batchAnimateCSS(animationData, options);
            }
        }
    
        /**
         * CSS降级方案（简单批量动画）
         * @param {Array} animationData - 动画数据数组
         * @param {Object} options - 动画选项
         * @returns {Promise} 动画完成的Promise
         */
        async batchAnimateCSS(animationData, options = {}) {
            const {
                duration = 600,
                easing = 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onStart = null,
                onComplete = null
            } = options;
    
            return new Promise(resolve => {
                // 回调：动画开始
                if (onStart) onStart();
    
                // 设置初始状态
                animationData.forEach(data => {
                    data.element.style.transform = 'translate3d(0, 0, 0)';
                    data.element.style.transition = `transform ${duration}ms ${easing}`;
                });
    
                // 强制重排
                animationData[0]?.element.offsetHeight;
    
                // 动画到目标位置
                animationData.forEach(data => {
                    data.element.style.transform = `translate3d(0, ${data.deltaY}px, 0)`;
                });
    
                // 等待动画完成
                setTimeout(() => {
                    animationData.forEach(data => {
                        data.element.style.transform = '';
                        data.element.style.transition = '';
                    });
                    if (onComplete) onComplete();
                    resolve();
                }, duration + 50);
            });
        }
    
        /**
         * 检查Web Animations API支持
         * @returns {boolean} 是否支持Web Animations API
         */
        isWebAnimationsSupported() {
            return this.supportsWebAnimations;
        }
    
        /**
         * 获取推荐的缓动函数
         * @param {string} type - 缓动类型
         * @returns {string} 缓动函数字符串
         */
        getEasing(type = 'smooth') {
            const easings = {
                smooth: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                bounce: 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                elastic: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
                ease: 'cubic-bezier(0.4, 0, 0.2, 1)',
                easeIn: 'cubic-bezier(0.4, 0, 1, 1)',
                easeOut: 'cubic-bezier(0, 0, 0.2, 1)',
                easeInOut: 'cubic-bezier(0.4, 0, 0.2, 1)'
            };
            return easings[type] || easings.smooth;
        }
    
        /**
         * 获取推荐的动画时长
         * @param {number} elementCount - 元素数量
         * @returns {number} 推荐的动画时长（毫秒）
         */
        getRecommendedDuration(elementCount) {
            if (elementCount <= 5) return 300;
            if (elementCount <= 20) return 500;
            if (elementCount <= 50) return 700;
            return 1000;
        }
        
        /**
         * 优雅的队伍落下动画（增强版：先上升再落下）
         * 用于f键跳过非奖区场景：所有队伍先升到窗口外，再从顶部缓缓落下，视口同时上升
         * @param {HTMLElement[]|string[]} allElementsOrIds - 所有队伍元素或ID数组
         * @param {HTMLElement[]|string[]} elementsOrIdsToFall - 要落下的元素数组或元素ID数组
         * @param {Object} options - 动画选项
         * @param {number} options.duration - 落下动画持续时间（ms），如果不提供则使用speed计算
         * @param {number} options.speed - 落下移动速度（像素/秒），默认200（优雅缓慢），用于计算duration
         * @param {number} options.riseDuration - 上升动画持续时间（ms），默认800
         * @param {boolean} options.shouldScrollFollow - 是否让窗口跟随上升，默认false
         * @param {HTMLElement|string|null} options.targetElement - 滚动目标元素或ID（用于窗口跟随）
         * @param {Function} options.onScrollTo - 滚动回调函数，接收目标元素作为参数
         * @param {number} options.fallDistance - 落下距离（像素），默认视口高度的1.5倍
         * @param {string} options.easing - 缓动函数，默认 'cubic-bezier(0.4, 0, 0.2, 1)'
         * @param {number} options.staggerDelay - 每个元素之间的延迟（ms），如果为null则基于速度计算
         * @param {string} options.fallingClass - 添加的CSS类名，默认 'rank-falling-down'
         * @param {number} options.splitIndex - 分界点索引，此索引之后的队伍可以快一点到位，默认null
         * @param {number} options.fastSpeed - 快速动画移动速度（像素/秒），默认400，用于计算fastDuration
         * @param {number} options.minDuration - 最小动画时长（ms），默认800
         * @param {number} options.maxDuration - 最大动画时长（ms），默认8000
         * @returns {Promise<void>}
         */
        async animateTeamsRiseAndFall(allElementsOrIds, elementsOrIdsToFall, options = {}) {
            const {
                duration = null, // 如果为null，则基于speed计算
                speed = 200, // 优雅缓慢的移动速度（像素/秒）
                riseDuration = null, // 如果为null，则基于riseSpeed计算
                riseSpeed = 150, // 上升移动速度（像素/秒），默认150，优雅缓慢
                shouldScrollFollow = false,
                targetElement = null,
                onScrollTo = null,
                fallDistance = null,
                easing = 'cubic-bezier(0.4, 0, 0.2, 1)',
                staggerDelay = null,
                fallingClass = 'rank-falling-down',
                splitIndex = null,
                fastSpeed = 100, // 快速动画移动速度（像素/秒）
                minDuration = 800,
                maxDuration = 80000,
                scrollSpeed = 50 // 视口滚动速度（像素/秒），与下落速度匹配
            } = options;
            
            // 解析所有元素
            const allElements = [];
            allElementsOrIds.forEach(item => {
                let element;
                if (typeof item === 'string') {
                    element = document.getElementById(item);
                } else if (item && item.nodeType === 1) {
                    element = item;
                }
                if (element) {
                    allElements.push(element);
                }
            });
            
            // 解析要落下的元素
            const elementsToFall = [];
            elementsOrIdsToFall.forEach(item => {
                let element;
                if (typeof item === 'string') {
                    element = document.getElementById(item);
                } else if (item && item.nodeType === 1) {
                    element = item;
                }
                if (element) {
                    elementsToFall.push(element);
                }
            });
            
            if (allElements.length === 0) {
                return;
            }
            
            // 计算视口高度和上升/落下距离
            const viewportHeight = window.innerHeight;
            const riseDistance = viewportHeight * 1.2; // 上升距离：视口高度的1.2倍
            const finalFallDistance = fallDistance || (viewportHeight * 1.5); // 落下距离
            
            // 计算上升动画时长（基于速度）
            const calculatedRiseDuration = riseDuration !== null
                ? riseDuration
                : this.calculateDurationBySpeed(riseDistance, riseSpeed, 600, 2000, 1.0);
            
            // 计算duration（基于速度）
            const calculatedDuration = duration !== null 
                ? duration 
                : this.calculateDurationBySpeed(finalFallDistance, speed, minDuration, maxDuration, 1.0);
            
            // 计算快速动画的duration（基于快速速度）
            // 注意：快速动画的min/max应该与主动画的min/max一致，但可以稍微快一点
            const fastMinDuration = minDuration * 0.6; // 快速动画最小时长为主动画的60%
            const fastMaxDuration = maxDuration * 0.8; // 快速动画最大时长为主动画的80%
            const calculatedFastDuration = this.calculateDurationBySpeed(finalFallDistance, fastSpeed, fastMinDuration, fastMaxDuration, 1.0);
            
            // 计算每个元素的延迟时间（基于计算出的duration）
            const finalStaggerDelay = staggerDelay !== null 
                ? staggerDelay 
                : (calculatedDuration / elementsToFall.length * 0.1);
            
            // 解析目标元素
            let targetEl = null;
            if (targetElement) {
                if (typeof targetElement === 'string') {
                    targetEl = document.getElementById(targetElement);
                } else if (targetElement && targetElement.nodeType === 1) {
                    targetEl = targetElement;
                }
            }
            
            // 第一步：所有队伍升到窗口外
            // 注意：元素可能在调用前已经被移出视口（例如在rank_roll.js中），所以需要检查当前状态
            // 如果元素已经在窗口外，可以跳过或缩短上升动画
            const risePromises = allElements.map((element, index) => {
                return new Promise(resolve => {
                    requestAnimationFrame(() => {
                        // 检查元素当前位置（通过getBoundingClientRect）
                        const rect = element.getBoundingClientRect();
                        const currentTransform = window.getComputedStyle(element).transform;
                        const isAlreadyOutside = rect.top < -window.innerHeight || 
                                                 (currentTransform !== 'none' && currentTransform.includes('translateY'));
                        
                        if (isAlreadyOutside) {
                            // 元素已经在窗口外，确保位置正确，但可以使用较短的过渡
                            element.style.transition = `transform ${Math.min(calculatedRiseDuration, 300)}ms ${easing}`;
                            element.style.transform = `translateY(-${riseDistance}px)`;
                            element.style.willChange = 'transform';
                            
                            setTimeout(() => {
                                element.style.transition = '';
                                resolve();
                            }, Math.min(calculatedRiseDuration, 300));
                        } else {
                            // 元素在视口中，执行正常上升动画（使用计算出的优雅缓慢速度）
                            element.style.transition = `transform ${calculatedRiseDuration}ms ${easing}`;
                            element.style.transform = `translateY(-${riseDistance}px)`;
                            element.style.willChange = 'transform';
                            
                            setTimeout(() => {
                                element.style.transition = '';
                                resolve();
                            }, calculatedRiseDuration);
                        }
                    });
                });
            });
            
            await Promise.all(risePromises);
            
            // 等待上升动画完成（缩短等待时间，只需要一帧即可）
            await new Promise(resolve => requestAnimationFrame(resolve));
            
            // 第二步：重新定位视口到目标位置（奖区最后一个队）
            // 注意：视口可能在第一步上升动画时被改变，需要重新定位到目标位置
            // 但此时元素已经通过 transform 移出视口，需要使用 offsetTop 计算位置
            if (shouldScrollFollow && targetEl) {
                // 使用 offsetTop 计算位置（不受 transform 影响，确保准确性）
                const viewportHeight = window.innerHeight;
                
                // 获取元素的绝对位置（相对于文档顶部）
                let elementOffsetTop = targetEl.offsetTop;
                let offsetParent = targetEl.offsetParent;
                
                // 遍历所有 offsetParent，累加它们的 offsetTop
                while (offsetParent && offsetParent !== document.body && offsetParent !== document.documentElement) {
                    elementOffsetTop += offsetParent.offsetTop;
                    offsetParent = offsetParent.offsetParent;
                }
                
                // 目标：元素顶部距离视口顶部 = 视口高度的 1/3
                const targetScrollY = elementOffsetTop - (viewportHeight / 3);
                
                // 立即定位到目标位置（不使用动画，直接定位）
                window.scrollTo({
                    top: Math.max(0, targetScrollY),
                    behavior: 'auto'
                });
                
                // 等待滚动完成并确保布局稳定
                await new Promise(resolve => requestAnimationFrame(() => {
                    requestAnimationFrame(resolve);
                }));
            }
            
            // 第三步：处理不需要落下的队伍
            // 视口已经定位到底部，不需要落下的队伍保持在窗口外（等待视口上升时自然出现）
            const elementsToFallSet = new Set(elementsToFall);
            allElements.forEach(element => {
                if (!elementsToFallSet.has(element)) {
                    // 不需要落下的队伍：保持在上升后的位置（窗口外）
                    // 它们会在视口上升时自然出现在正确位置
                    element.style.transform = `translateY(-${riseDistance}px)`;
                    element.style.opacity = '1';
                }
            });
            
            // 等待一帧，确保样式已应用
            await new Promise(resolve => requestAnimationFrame(resolve));
            
            // 第四步：不再需要提前定位视口，因为视口已经定位到底部
            // 后续会在第一个队伍落到位时启动视口上升
            
            // 第五步：开始落下动画（视口已定位到底部，第一个队伍落到位时启动视口上升）
            // 关键：使用FLIP技术确保动画结束后所有元素都在正确位置
            
            // FLIP第一步：记录最终位置（First）
            // 此时所有元素都应该在正确的DOM位置，但被移到了窗口外
            // 我们需要记录它们在正常状态下的位置
            const finalPositions = new Map();
            allElements.forEach(element => {
                // 临时移除transform，记录最终位置
                const savedTransform = element.style.transform;
                element.style.transform = '';
                const savedTransition = element.style.transition;
                element.style.transition = 'none';
                
                // 强制重排，获取元素在正常位置时的位置
                element.offsetHeight;
                const rect = element.getBoundingClientRect();
                finalPositions.set(element, {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height
                });
                
                // 恢复transform
                element.style.transform = savedTransform;
                element.style.transition = savedTransition;
            });
            
            // 等待一帧，确保位置记录完成
            await new Promise(resolve => requestAnimationFrame(resolve));
            
            // 4.1 队伍落下动画（从下到上：最后一个先落，然后是倒数第二个...）
            // 注意：elementsToFall 的顺序是从上到下，我们需要反转为从下到上
            const elementsToFallReversedForAnimation = [...elementsToFall].reverse();
            const animationPromises = [];
            
            elementsToFallReversedForAnimation.forEach((element, reversedIndex) => {
                // 计算原始索引（用于判断是否快速动画）
                const originalIndex = elementsToFall.length - 1 - reversedIndex;
                const isFast = splitIndex !== null && originalIndex >= splitIndex;
                const elementDuration = isFast ? calculatedFastDuration : calculatedDuration;
                const elementEasing = isFast ? 'cubic-bezier(0.4, 0, 1, 1)' : easing; // 快速使用ease-in
                
                                // 初始位置在窗口上方
                                element.style.transform = `translateY(-${finalFallDistance}px)`;
                                element.style.opacity = '0';
                                element.style.transition = ''; // 先清除transition，避免冲突
                                element.style.willChange = 'transform, opacity';
                                element.style.zIndex = '50';
                                element.classList.add(fallingClass);
                                
                                // 强制重排，确保初始状态已应用
                                element.offsetHeight;
                                
                                // 延迟开始：从最后一个开始，所以第一个（reversedIndex=0）立即开始
                                // 后续每个队伍延迟一点，形成从下到上的流水效果
                                const delay = reversedIndex * finalStaggerDelay;
                                
                                animationPromises.push(
                                    new Promise(resolve => {
                                        setTimeout(() => {
                                            requestAnimationFrame(() => {
                                                // 开始落下动画（使用will-change优化，避免抖动）
                                                element.style.willChange = 'transform, opacity';
                                                element.style.transition = `transform ${elementDuration}ms ${elementEasing}, opacity ${elementDuration}ms ease-out`;
                                                element.style.transform = 'translateY(0)';
                                                element.style.opacity = '1';
                                                
                                                // 关键：当第一个队伍（reversedIndex=0）落到位时，启动视口上升
                                                // 在动画进行到一定进度时（例如80%）触发视口滚动
                                                if (shouldScrollFollow && targetEl && onScrollTo && reversedIndex === 0) {
                                                    // 第一个队伍落到位时（在80%进度时），启动视口滚动到目标位置
                                                    const scrollTriggerTime = elementDuration * 0.8;
                                                    setTimeout(() => {
                                                        // 调用 onScrollTo 回调，启动视口滚动到目标元素
                                                        if (onScrollTo && targetEl) {
                                                            onScrollTo(targetEl, scrollSpeed);
                                                        }
                                                    }, scrollTriggerTime);
                                                }
                                
                                // 动画完成后清理（使用FLIP技术确保最终位置正确）
                                setTimeout(() => {
                                    // FLIP最后一步：确保元素回到正确的最终位置
                                    // 先清除transition，避免清理时触发动画
                                    element.style.transition = '';
                                    
                                    // 获取记录的最终位置
                                    const finalPos = finalPositions.get(element);
                                    if (finalPos) {
                                        // 获取元素当前实际位置（考虑transform）
                                        const currentRect = element.getBoundingClientRect();
                                        const deltaY = finalPos.top - currentRect.top;
                                        const deltaX = finalPos.left - currentRect.left;
                                        
                                        // 如果有位置差异，应用反向transform然后动画到最终位置
                                        if (Math.abs(deltaY) > 0.5 || Math.abs(deltaX) > 0.5) {
                                            // 立即应用反向transform，使元素看起来还在当前位置
                                            element.style.transition = `transform 200ms ease-out`;
                                            element.style.transform = `translate3d(${deltaX}px, ${deltaY}px, 0)`;
                                            
                                            // 强制重排
                                            element.offsetHeight;
                                            
                                            // 动画到最终位置（translate3d(0, 0, 0)）
                                            requestAnimationFrame(() => {
                                                element.style.transform = 'translate3d(0, 0, 0)';
                                                
                                                // 等待动画完成后清理
                                                setTimeout(() => {
                                                    element.style.transform = '';
                                                    element.style.transition = '';
                                                    element.style.opacity = '';
                                                    element.style.willChange = '';
                                                    element.style.zIndex = '';
                                                    element.classList.remove(fallingClass);
                                                    resolve();
                                                }, 200);
                                            });
                                        } else {
                                            // 位置已经正确，直接清理
                                            element.style.transform = '';
                                            element.style.opacity = '';
                                            element.style.willChange = '';
                                            element.style.zIndex = '';
                                            element.classList.remove(fallingClass);
                                            resolve();
                                        }
                                    } else {
                                        // 没有记录的位置，直接清理
                                        element.style.transform = '';
                                        element.style.opacity = '';
                                        element.style.willChange = '';
                                        element.style.zIndex = '';
                                        element.classList.remove(fallingClass);
                                        resolve();
                                    }
                                }, elementDuration);
                            });
                        }, delay);
                    })
                );
            });
            
            // 等待所有下落动画完成
            await Promise.all(animationPromises);
            
            // 4.2 处理不需要落下的队伍（使用FLIP技术确保它们在正确位置）
            // 当视口上升时，这些队伍会自然出现在正确位置
            // 但需要在所有下落动画完成后，使用FLIP技术确保它们在正确位置
            const nonFallingElements = allElements.filter(element => !elementsToFallSet.has(element));
            if (nonFallingElements.length > 0) {
                await new Promise(resolve => {
                    requestAnimationFrame(() => {
                        nonFallingElements.forEach(element => {
                            // 获取记录的最终位置
                            const finalPos = finalPositions.get(element);
                            if (finalPos) {
                                // 临时移除transform，检查位置
                                const savedTransform = element.style.transform;
                                element.style.transform = '';
                                const currentRect = element.getBoundingClientRect();
                                const deltaY = finalPos.top - currentRect.top;
                                const deltaX = finalPos.left - currentRect.left;
                                
                                // 恢复transform
                                element.style.transform = savedTransform;
                                
                                // 如果有位置差异，应用FLIP修正
                                if (Math.abs(deltaY) > 0.5 || Math.abs(deltaX) > 0.5) {
                                    element.style.transition = `transform 200ms ease-out`;
                                    element.style.transform = `translate3d(${deltaX}px, ${deltaY}px, 0)`;
                                    
                                    element.offsetHeight;
                                    
                                    requestAnimationFrame(() => {
                                        element.style.transform = 'translate3d(0, 0, 0)';
                                        setTimeout(() => {
                                            element.style.transform = '';
                                            element.style.transition = '';
                                        }, 200);
                                    });
                                } else {
                                    // 位置正确，直接恢复
                                    element.style.transform = '';
                                    element.style.transition = '';
                                }
                            } else {
                                // 直接恢复
                                element.style.transform = '';
                                element.style.transition = '';
                            }
                        });
                        
                        // 等待一帧后resolve
                        requestAnimationFrame(resolve);
                    });
                });
            }
            
            // FLIP最后一步：强制清理所有transform，确保所有元素都在正确位置
            await new Promise(resolve => {
                requestAnimationFrame(() => {
                    allElements.forEach(element => {
                        // 最终检查：确保元素没有残留的transform
                        const computedStyle = window.getComputedStyle(element);
                        const transform = computedStyle.transform;
                        if (transform && transform !== 'none' && transform !== 'matrix(1, 0, 0, 1, 0, 0)') {
                            // 有残留transform，清除它
                            element.style.transform = '';
                            element.style.transition = '';
                        }
                    });
                    resolve();
                });
            });
        }
        
        /**
         * 优雅的队伍落下动画
         * @param {HTMLElement[]|string[]} elementsOrIds - 要落下的元素数组或元素ID数组
         * @param {Object} options - 动画选项
         * @param {number} options.duration - 动画持续时间（ms），默认1500
         * @param {boolean} options.shouldScrollFollow - 是否让窗口跟随上升，默认false
         * @param {HTMLElement|string|null} options.targetElement - 滚动目标元素或ID（用于窗口跟随）
         * @param {Function} options.onScrollTo - 滚动回调函数，接收目标元素作为参数
         * @param {number} options.fallDistance - 落下距离（像素），默认视口高度的1.5倍
         * @param {string} options.easing - 缓动函数，默认 'cubic-bezier(0.4, 0, 0.2, 1)'
         * @param {number} options.staggerDelay - 每个元素之间的延迟（ms），默认duration的10%
         * @param {string} options.fallingClass - 添加的CSS类名，默认 'rank-falling-down'
         * @returns {Promise<void>}
         */
        async animateTeamsFallingDown(elementsOrIds, options = {}) {
            const {
                duration = 1500,
                shouldScrollFollow = false,
                targetElement = null,
                onScrollTo = null,
                fallDistance = null,
                easing = 'cubic-bezier(0.4, 0, 0.2, 1)',
                staggerDelay = null,
                fallingClass = 'rank-falling-down'
            } = options;
            
            // 解析元素或ID数组
            const elements = [];
            elementsOrIds.forEach(item => {
                let element;
                if (typeof item === 'string') {
                    element = document.getElementById(item);
                } else if (item && item.nodeType === 1) {
                    element = item;
                }
                if (element) {
                    elements.push(element);
                }
            });
            
            if (elements.length === 0) {
                return;
            }
            
            // 计算落下距离
            const viewportHeight = window.innerHeight;
            const finalFallDistance = fallDistance || (viewportHeight * 1.5);
            
            // 计算每个元素的延迟时间
            const finalStaggerDelay = staggerDelay || (duration / elements.length * 0.1);
            
            // 解析目标元素
            let targetEl = null;
            if (targetElement) {
                if (typeof targetElement === 'string') {
                    targetEl = document.getElementById(targetElement);
                } else if (targetElement && targetElement.nodeType === 1) {
                    targetEl = targetElement;
                }
            }
            
            // 记录所有要落下的元素及其初始位置
            const rowsToAnimate = [];
            elements.forEach(element => {
                const rect = element.getBoundingClientRect();
                rowsToAnimate.push({
                    element: element,
                    initialTop: rect.top,
                    initialHeight: rect.height
                });
            });
            
            // 并行执行落下动画和窗口滚动
            const animationPromises = [];
            
            // 1. 队伍落下动画
            rowsToAnimate.forEach((item, index) => {
                const row = item.element;
                
                // 添加动画类
                row.classList.add(fallingClass);
                row.style.willChange = 'transform, opacity';
                row.style.zIndex = '50';
                
                // 延迟开始，形成流水效果
                const delay = index * finalStaggerDelay;
                
                animationPromises.push(
                    new Promise(resolve => {
                        setTimeout(() => {
                            // 使用 requestAnimationFrame 确保流畅
                            requestAnimationFrame(() => {
                                row.style.transition = `transform ${duration}ms ${easing}, opacity ${duration}ms ease-out`;
                                row.style.transform = `translateY(${finalFallDistance}px)`;
                                row.style.opacity = '0';
                                
                                // 动画完成后清理
                                setTimeout(() => {
                                    row.style.transition = '';
                                    row.style.transform = '';
                                    row.style.opacity = '';
                                    row.style.willChange = '';
                                    row.style.zIndex = '';
                                    row.classList.remove(fallingClass);
                                    resolve();
                                }, duration);
                            });
                        }, delay);
                    })
                );
            });
            
            // 2. 窗口跟随上升（如果启用）
            if (shouldScrollFollow && targetEl && onScrollTo) {
                // 延迟一点开始滚动，让落下动画先开始
                const scrollDelay = duration * 0.2; // 在20%的时间点开始滚动
                animationPromises.push(
                    new Promise(resolve => {
                        setTimeout(() => {
                            onScrollTo(targetEl);
                            // 等待滚动动画完成
                            setTimeout(() => {
                                resolve();
                            }, duration - scrollDelay);
                        }, scrollDelay);
                    })
                );
            }
            
            // 等待所有动画完成
            await Promise.all(animationPromises);
        }
    }
    
    // 创建全局实例
    window.CSGAnim = new CSGAnim();    
}