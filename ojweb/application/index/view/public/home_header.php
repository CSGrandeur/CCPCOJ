
<ul class="nav nav-tabs">
    <?php $controller = strtolower(request()->controller()); ?>

    <li class="nav-item">
        <a class="nav-link{if $controller == 'index'} active{/if}" href="__HOME__/index" role="button" aria-haspopup="true" aria-expanded="false">
            <span class="cn-text"><i class="bi bi-house me-1"></i>{$OJ_NAME}主页</span><span class="en-text">Home</span>
        </a>
    </li>
    <!-- 动态菜单项将通过 JavaScript 插入到这里 -->
    <li class="nav-item">
        <a class="nav-link{if $controller == 'about'} active{/if}" href="__HOME__/about" role="button" aria-haspopup="true" aria-expanded="false">
            <span class="cn-text"><i class="bi bi-info-circle me-1"></i>关于</span><span class="en-text">About</span>
        </a>
    </li>
</ul>

<script type="text/javascript">
    // 设置当前控制器供 JavaScript 使用
    window.currentController = '{$controller}';
</script>
<script type="text/javascript" src="__STATIC__/csgoj/news/index.js"></script>