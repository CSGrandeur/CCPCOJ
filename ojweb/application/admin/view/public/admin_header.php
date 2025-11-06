<ul class="nav nav-tabs">
    {include file="../../admin/view/public/url_modal"}
    <?php $controller = strtolower(request()->controller()); ?>

    {if(IsAdmin('news_editor')) }
    <li class="nav-item dropdown">
        <a href="__ADMIN__/news/index" class="nav-link dropdown-toggle {if $controller == 'news' } active {/if}" data-bs-toggle="dropdown" role="button" aria-expanded="false">
            公告<span class="en-text">Article</span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/news/index" class="dropdown-item">文章列表<span class="en-text">Article List</span></a>
            </li>
            <li>
                <a href="__ADMIN__/news/news_add" class="dropdown-item">添加文章<span class="en-text">Article Add</span></a>
            </li>
            {if(IsAdmin('administrator'))}
            <li>
                <a href="__ADMIN__/news/carousel" class="dropdown-item">轮播<span class="en-text">Carousel</span></a>
            </li>
            <li>
                <a href="__ADMIN__/news/aboutus" class="dropdown-item">关于<span class="en-text">About Us</span></a>
            </li>
            <li>
                <a href="__ADMIN__/news/oj_faq" class="dropdown-item">常见疑问<span class="en-text">OJ F.A.Qs</span></a>
            </li>
            <!-- <li>
                <a href="__ADMIN__/news/cr_faq" class="dropdown-item">注册常见疑问<span class="en-text">Reg F.A.Qs</span></a>
            </li> -->
            {/if}
        </ul>
    </li>
    {/if}
    {if(IsAdmin('problem_editor')) }
    <li class="nav-item dropdown">
        <a href="__ADMIN__/problem/index" class="nav-link dropdown-toggle {if $controller == 'problem' } active {/if}" data-bs-toggle="dropdown" role="button" aria-expanded="false">
            题目<span class="en-text">Problem</span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/problem/index" class="dropdown-item">题目列表<span class="en-text">Problem List</span></a>
            </li>
            <li>
                <a href="__ADMIN__/problem/problem_add" class="dropdown-item">添加题目<span class="en-text">Problem Add</span></a>
            </li>
            <li>
                <a href="__ADMIN__/problem/problem_rejudge" class="dropdown-item">题目重判<span class="en-text">Problem Rejudge</span></a>
            </li>
            {if(IsAdmin('administrator'))}
            <li>
                <a href="__ADMIN__/problem/polygon_import" class="dropdown-item">Polygon解析<span class="en-text">Polygon Parse</span></a>
            </li>
            <li>
                <a href="__ADMIN__/problemexport/problem_export?item=problemexport" class="dropdown-item">题目导出<span class="en-text">Problem Export</span></a>
            </li>
            <li>
                <a href="__ADMIN__/problemexport/problem_export_filemanager?item=problemexport" 
                   data-modal-url="__ADMIN__/problemexport/problem_export_filemanager?item=problemexport"
                   data-modal-title="题目导入/导出文件管理 / Problem Import/Export File Manager"
                   class="dropdown-item">题目导入<span class="en-text">Problem Import</span></a>
            </li>
            {/if}
        </ul>
    </li>
    {/if}
    {if(IsAdmin('contest_editor')) }
    <li class="nav-item dropdown">
        <a href="__ADMIN__/contest/index" class="nav-link dropdown-toggle {if $controller == 'contest' } active {/if}" data-bs-toggle="dropdown" role="button" aria-expanded="false">
            比赛<span class="en-text">Contest</span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/contest/index" class="dropdown-item">比赛列表<span class="en-text">Contest List</span></a>
            </li>
            <li>
                <a href="__ADMIN__/contest/contest_add" class="dropdown-item">添加比赛<span class="en-text">Contest Add</span></a>
            </li>
            <li>
                <a href="__ADMIN__/contestsummary/contest_summary" class="dropdown-item">统计归档<span class="en-text">Summary Export</span></a>
            </li>
        </ul>
    </li>
    {/if}
    {if(IsAdmin('administrator')) }
    <li class="nav-item">
        <a href="__ADMIN__/privilege/index" class="nav-link {if $controller == 'privilege' } active {/if}">
            权限<span class="en-text">Privilege</span>
        </a>
    </li>
    {/if}
    {if(IsAdmin('password_setter')) }
        {if $OJ_SSO == false}
        <li class="nav-item dropdown">
            <a href="__ADMIN__/usermanager/index" class="nav-link dropdown-toggle {if $controller == 'usermanager' } active {/if}" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                用户<span class="en-text">User</span>
            </a>
            <ul class="dropdown-menu">
                <li>
                    <a href="__ADMIN__/usermanager/index" class="dropdown-item">用户列表<span class="en-text">User List</span></a>
                </li>
                {if $OJ_STATUS=='exp' && IsAdmin('administrator') || IsAdmin('super_admin') }
                <li>
                    <a href="__ADMIN__/usermanager/usergen" class="dropdown-item">用户生成<span class="en-text">User Generator</span></a>
                </li>
                {/if}
            </ul>
        </li>
        {/if}
    {/if}
    {if(IsAdmin('administrator')) }
    <li class="nav-item">
        <a href="__ADMIN__/judger/index" class="nav-link {if $controller == 'judger' } active {/if}">
            评测机<span class="en-text">Judger</span>
        </a>
    </li>
    {/if}
</ul>