<ul class="nav nav-tabs admin-menu-pills">
    <?php $controller = strtolower(request()->controller()); ?>
    {if $isContestAdmin }
    <li class="nav-item">
        <a class="nav-link{if $action == 'contest_edit'} active{/if}" href="__CPC__/admin/contest_edit?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false" title="修改比赛 Modify Contest">
            设置<span class="en-text">Modify</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link{if $action == 'contest_rejudge'} active{/if}" href="__CPC__/admin/contest_rejudge?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false" title="重判提交 Rejudge Submissions">
            重判<span class="en-text">Rejudge</span>
        </a>
    </li>
    {/if}
    <li class="nav-item">
        <a class="nav-link{if $action == 'msg'} active{/if}" href="__CPC__/admin/msg?cid={$contest['contest_id']}" role="button" aria-haspopup="true" aria-expanded="false" title="弹窗通知 Contest Messages">
            弹窗<span class="en-text">Message</span>
        </a>
    </li>
    {if $module == 'cpcsys'}
        {if $isContestAdmin }
        <li class="nav-item dropdown{if $action == 'contest_teamgen' || $action == 'contest_staffgen'} active{/if}" title="账号管理 Account Management">
            <a class="nav-link dropdown-toggle{if $action == 'contest_teamgen' || $action == 'contest_staffgen'} active{/if}" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                账号<span class="en-text">Account</span>
            </a>
            <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                <li><a class="dropdown-item{if $action == 'contest_teamgen'} active{/if}" href="__CPC__/admin/contest_teamgen?cid={$contest['contest_id']}" title="队伍生成 Team Generation">队伍<span class="en-text">TeamGen</span></a></li>
                <li><a class="dropdown-item{if $action == 'contest_staffgen'} active{/if}" href="__CPC__/admin/contest_staffgen?cid={$contest['contest_id']}" title="工作人员生成 Staff Generation">赛务<span class="en-text">StaffGen</span></a></li>
            </ul>
        </li>
        {/if}
        <!-- TODO
        <li class="nav-item dropdown{if $action == 'client_manage' || $action == 'ipcheck'} active{/if}" title="客户端与IP管理 Client & IP Management">
            <a class="nav-link dropdown-toggle{if $action == 'client_manage' || $action == 'ipcheck'} active{/if}" href="#" id="clientIpDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                客户端<span class="en-text">Client</span>
            </a>
            <ul class="dropdown-menu" aria-labelledby="clientIpDropdown">
                {if $isContestAdmin}
                <li><a class="dropdown-item{if $action == 'client_manage'} active{/if}" href="/{$module}/admin/client_manage?cid={$contest['contest_id']}" title="客户端管理 Client Management">客户端管理<span class="en-text">Client Manage</span></a></li>
                {/if}
                <li><a class="dropdown-item{if $action == 'ipcheck'} active{/if}" href="/{$module}/admin/ipcheck?cid={$contest['contest_id']}" title="IP检查 IP Check">IP检查<span class="en-text">IPCheck</span></a></li>
            </ul>
        </li>
        -->
    {/if}
    {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin}
        {if $module == 'cpcsys'}
        <li class="nav-item dropdown{if $action == 'rank_roll' || $action == 'rank_team_image'} active{/if}" title="滚榜 Rank Roll">
            <a class="nav-link dropdown-toggle{if $action == 'rank_roll' || $action == 'rank_team_image'} active{/if}" href="#" id="rankRollDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                滚榜<span class="en-text">RankRoll</span>
            </a>
            <ul class="dropdown-menu" aria-labelledby="rankRollDropdown">
                <li><a class="dropdown-item{if $action == 'rank_roll'} active{/if}" href="/{$module}/admin/rank_roll?cid={$contest['contest_id']}">滚榜 <span class="en-text">Rank Roll</span></a></li>
                <li><a class="dropdown-item{if $action == 'rank_team_image'} active{/if}" href="/{$module}/admin/rank_team_image?cid={$contest['contest_id']}">队伍图片管理 <span class="en-text">Team Image</span></a></li>
            </ul>
        </li>
        {else}
        <li class="nav-item">
            <a class="nav-link{if $action == 'rank_roll'} active{/if}" href="/{$module}/admin/rank_roll?cid={$contest['contest_id']}" title="滚榜 Rank Roll">
                滚榜<span class="en-text">RankRoll</span>
            </a>
        </li>
        {/if}
        <li class="nav-item">
            <a class="nav-link{if $action == 'outrank'} active{/if}" href="/{$module}/admin/outrank?cid={$contest['contest_id']}" title="外榜 Outer Rank">
                外榜<span class="en-text">OuterRank</span>
            </a>
        </li>
    {/if}
    {if $isContestAdmin }
    <li class="nav-item">
        <a class="nav-link" href="/{$module}/contest/contest2print?cid={$contest['contest_id']}&module={$module}" target="_blank" id="contest_printp" title="题目排版 Print Problems">
            排版<span class="en-text">PrintProblem</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link{if $action == 'award'} active{/if}" href="/{$module}/admin/award?cid={$contest['contest_id']}" id="contest_award" cid="{$contest['contest_id']}" title="奖项设置 Award Settings">
            奖项<span class="en-text">Award</span>
        </a>
    </li>
    {/if}
</ul>
{css href="__STATIC__/csgoj/admin/admin.css" /}