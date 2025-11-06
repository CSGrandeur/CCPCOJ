{if $controller != 'userinit'}
    <header>
        {include file="../../csgoj/view/public/csgoj_brand" /}
        <ul id="sidebar_div" class="nav nav-pills flex-column sidebar">
            <li id="user_panel" {if $OJ_MODE == 'cpcsys'}style="display:none;"{/if}>
                {if(session('?user_id')) }
                {include file="../../csgoj/view/user/logout_div" /}
                {else /}
                {include file="../../csgoj/view/user/login_div" /}
                {/if}
            </li>
            {include file="public/side_menu" /}
        </ul>
        
    {if $OJ_MODE == 'cpcsys'}
    <script>
    let sidebar_div = $('#sidebar_div');
    let user_panel = $('#user_panel');

    // 三击触发逻辑 - 只在空白区域触发，不影响菜单项点击
    sidebar_div.on('click', function (event) {
        // 检查点击的是否是菜单项或链接
        if ($(event.target).closest('a, .nav-link').length > 0) {
            return; // 如果点击的是菜单项，不处理三击逻辑
        }
        
        // 只在空白区域三击时触发
        if(event.detail == 3){
            event.preventDefault();
            event.stopPropagation();
            user_panel.toggle();
        }
    });

    // 点击分割线也可以切换用户面板
    $('#hidden_user_panel').click(function (event) {
        event.preventDefault();
        user_panel.toggle();
    });
    </script>
    {/if}
    <script>
    const updateZoom = () => {
        const userAgent = window.navigator.userAgent;
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent);

        if(window.innerWidth < 1900) {
            document.body.style.zoom = window.innerWidth / 1900;
        } else if(window.innerHeight < 900) {
            document.body.style.zoom = window.innerHeight / 900;
        } else {
            document.body.style.zoom = '';
        }
    }
    // updateZoom();
    </script>
    </header>
{/if}