<ul class="nav nav-tabs">
    <?php $controller = strtolower(request()->controller()); ?>
    {if $isContestAdmin }
    <li class="{if $action == 'contest_edit' } active {/if}">
        <a href="__CPC__/admin/contest_edit?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
            Modify
        </a>
    </li>
    <li class="{if $action == 'contest_rejudge' } active {/if}">
        <a href="__CPC__/admin/contest_rejudge?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
            Rejudge
        </a>
    </li>
    {/if}
    <li class="{if $action == 'msg' } active {/if}">
        <a href="__CPC__/admin/msg?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
            Message
        </a>
    </li>
    {if $module == 'cpcsys'}
        {if $isContestAdmin }
        <li class="{if $action == 'contest_teamgen' } active {/if}">
            <a href="__CPC__/admin/contest_teamgen?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
                TeamGen
            </a>
        </li>
        <li class="{if $action == 'contest_staffgen' } active {/if}">
            <a href="__CPC__/admin/contest_staffgen?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
                StaffGen
            </a>
        </li>
        {/if}
    <li class="{if $action == 'team_modify' } active {/if}">
        <a href="__CPC__/admin/team_modify?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false">
            Team Modify
        </a>
    </li>
    <li {if $action == 'ipcheck'} class="active" {/if}><a href="/{$module}/admin/ipcheck?cid={$contest['contest_id']}">IPCheck</a></li>
    {/if}
    {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin}
    <li><a href="/ojtool/rankroll/rankroll?cid={$contest['contest_id']}" target="_blank">RankRoll</a></li>
    {/if}
    {if $isContestAdmin }
    <li id="contest_export" cid="{$contest['contest_id']}"><a href="#">ExportRecord</a></li>
    <li id="contest_printp" ><a href="/ojtool/tool/contest2print?cid={$contest['contest_id']}&module={$module}" target="_blank">PrintProblem</a></li>
    <li class="{if $action == 'award' } active {/if}" id="contest_award" cid="{$contest['contest_id']}"><a href="/{$module}/admin/award?cid={$contest['contest_id']}">Award</a></li>
    {/if}

</ul>