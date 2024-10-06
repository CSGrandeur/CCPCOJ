<ul class="nav nav-tabs">
    <?php $controller = strtolower(request()->controller()); ?>

    {if(IsAdmin('news_editor')) }
    <li role="presentation" class="dropdown {if $controller == 'news' } active {/if}">
        <a href="__ADMIN__/news/index" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            Article <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/news/index">Article List</a>
            </li>
            <li>
                <a href="__ADMIN__/news/news_add">Article Add</a>
            </li>
            {if(IsAdmin('administrator'))}
            <li>
                <a href="__ADMIN__/news/carousel">Carousel</a>
            </li>
            <li>
                <a href="__ADMIN__/news/aboutus">About Us</a>
            </li>
            <li>
                <a href="__ADMIN__/news/oj_faq">OJ F.A.Qs</a>
            </li>
            <li>
                <a href="__ADMIN__/news/cr_faq">Reg F.A.Qs</a>
            </li>
            {/if}
        </ul>
    </li>
    {/if}
    {if(IsAdmin('problem_editor')) }
    <li role="presentation" class="dropdown {if $controller == 'problem' } active {/if}">
        <a href="__ADMIN__/problem/index" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            Problem <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/problem/index">Problem List</a>
            </li>
            <li>
                <a href="__ADMIN__/problem/problem_add">Problem Add</a>
            </li>
            <li>
                <a href="__ADMIN__/problem/problem_rejudge">Problem Rejudge</a>
            </li>
            {if(IsAdmin('administrator'))}
            <li>
                <a href="__ADMIN__/problem/polygon_import">Polygon Parse</a>
            </li>
            <li>
                <a href="__ADMIN__/problemexport/problem_export?item=problemexport">Problem Export</a>
            </li>
            <li>
                <a href="__ADMIN__/problemexport/problem_export_filemanager?item=problemexport" target="_blank">Problem Import</a>
            </li>
            {/if}
        </ul>
    </li>
    {/if}
    {if(IsAdmin('contest_editor')) }
    <li role="presentation" class="dropdown {if $controller == 'contest' } active {/if}">
        <a href="__ADMIN__/contest/index" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            Contest <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/contest/index">Contest List</a>
            </li>
            <li>
                <a href="__ADMIN__/contest/contest_add">Contest Add</a>
            </li>
            <li>
                <a href="__ADMIN__/contestsummary/contest_summary">Summary Export</a>
            </li>
        </ul>
    </li>
    {/if}
    {if(IsAdmin('administrator')) }
    <li class="{if $controller == 'privilege' } active {/if}">
        <a href="__ADMIN__/privilege/index" role="button" aria-haspopup="true" aria-expanded="false">
            Privilege
        </a>
    </li>
    {/if}
    {if(IsAdmin('password_setter')) }
    {if $OJ_SSO == false}
    <li role="presentation" class="dropdown {if $controller == 'usermanager' } active {/if}">
        <a href="__ADMIN__/usermanager/index" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            User <span class="caret"></span>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a href="__ADMIN__/usermanager/index">User List</a>
            </li>
            {if $OJ_STATUS=='exp' && IsAdmin('administrator') || IsAdmin('super_admin') }
            <li>
                <a href="__ADMIN__/usermanager/usergen">User Generator</a>
            </li>
            {/if}
        </ul>
    </li>
    {/if}
    {/if}
</ul>