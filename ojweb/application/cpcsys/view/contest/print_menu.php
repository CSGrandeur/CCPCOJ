
{if $running && $contest_user && !$isContestStaff}
<li role="presentation" class="dropdown {if strpos($action, 'print') === 0 } active {/if}">
    <a href="/{$module}/contest/print_code?cid={$contest['contest_id']}" class="dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
    代码打印<br/>Print <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
        <li>
            <a href="/{$module}/contest/print_code?cid={$contest['contest_id']}">发送打印<br/>Send Print Request</a>
        </li>
        <li>
            <a href="/{$module}/contest/print_status?cid={$contest['contest_id']}{if !$printManager }#team_id={$contest_user}{/if}">打印任务<br/>Print Status</a>
        </li>
    </ul>
</li>
{else /}
    <li role="presentation" {if $action == 'print_status'} class="active" {/if}>
        <a href="/{$module}/contest/print_status?cid={$contest['contest_id']}{if !$printManager }#team_id={$contest_user}{/if}">打印任务<br/>Print Status</a>
    </li>
{/if}