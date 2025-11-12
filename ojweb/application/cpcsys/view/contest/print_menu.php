{if $running && $contest_user && !$isContestStaff}
<li class="nav-item dropdown{if strpos($action, 'print') === 0} active{/if}" title="代码打印">
    <a class="nav-link dropdown-toggle{if strpos($action, 'print') === 0} active{/if}" href="#" id="printDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="cn-text"><i class="bi bi-printer"></i>印</span><span class="en-text">Print</span>
    </a>
    <ul class="dropdown-menu" aria-labelledby="printDropdown">
        <li>
            <a class="dropdown-item" href="/{$module}/contest/print_code?cid={$contest['contest_id']}">
                <span class="cn-text"><i class="bi bi-send"></i> 发送打印 </span><span class="en-text">Send Print Request</span>
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="/{$module}/contest/print_status?cid={$contest['contest_id']}{if !$printManager }#team_id={$contest_user}{/if}">
                <span class="cn-text"><i class="bi bi-list-check"></i> 打印任务 </span><span class="en-text">Print Status</span>
            </a>
        </li>
    </ul>
</li>
{else /}
    <li class="nav-item{if strpos($action, 'print') === 0} active{/if}">
        <a class="nav-link{if $action == 'print_status'} active{/if}" href="/{$module}/contest/print_status?cid={$contest['contest_id']}{if !$printManager }#team_id={$contest_user}{/if}" title="代码打印">
            <span class="cn-text"><i class="bi bi-printer"></i>印 </span><span class="en-text">Print Status</span>
        </a>
    </li>
{/if}