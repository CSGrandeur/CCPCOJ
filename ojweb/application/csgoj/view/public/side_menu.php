<hr id="hidden_user_panel">
{if $OJ_MODE=='online' || IsAdmin('administrator') }
<li class="nav-item"><a class="nav-link{if ($module == 'index')} active{/if}" href="__HOME__/index">主页 <span class="en-text">Home</span></a></li>
{if $OJ_OPEN_ARCHIVE }
<li class="nav-item"><a class="nav-link{if ($controller == 'problemarchive')} active{/if}" href="__OJ__/problemarchive">赛事题目归档 <span class="en-text">Problem Archive</span></a></li>
{/if}
<li class="nav-item"><a class="nav-link{if ($controller == 'problemset')} active{/if}" href="__OJ__/problemset">开放题目集 <span class="en-text">Problem Set</span></a></li>
<li class="nav-item"><a class="nav-link{if ($controller == 'status')} active{/if}" href="__OJ__/status">评测状态 <span class="en-text">Status</span></a></li>
<li class="nav-item"><a class="nav-link{if ($controller == 'userrank')} active{/if}" href="__OJ__/userrank">刷题榜 <span class="en-text">Training Rank</span></a></li>
<li class="nav-item"><a class="nav-link{if ($module == 'csgoj' && $controller == 'contest')} active{/if}" href="__OJ__/contest">比赛 <span class="en-text">Contest</span></a></li>
{/if}
{if $OJ_STATUS == 'cpc'}
<li class="nav-item"><a class="nav-link{if $module == 'cpcsys' && $controller == 'contest'} active{/if}" href="__CPC__/contest">标准XCPC比赛 <span class="en-text">Standard XCPC Contest</span></a></li>
{/if}
<li class="nav-item"><a class="nav-link{if ($controller == 'faqs')} active{/if}" href="{if $module == 'cpcsys'}__CPC__{else}__OJ__{/if}/faqs">常见疑问 <span class="en-text">FAQs</span></a></li>

<?php
$showadmin = false;
//$ojAdminList在Globalbasecontroller中assign
foreach($ojAdminList as $adminStr => $adminName) {
    if (IsAdmin($adminStr))
    {
        $showadmin = true;
        break;
    }
}
if($showadmin)
{
    ?>
    <li class="nav-item"><a class="nav-link{if ($module == 'admin')} active{/if}" href="__ADMIN__/index">管理后台 <span class="en-text">Admin Panel</span></a></li>
<?php } ?>

<hr/>
{if $OJ_MODE=='online' || IsAdmin() }
<li class="nav-item"><a class="nav-link" href="__OJTOOL__">工具集 <span class="en-text">Tools</span></a></li>
{/if}

{$OJ_ADDITION_LINK }

<style>
    #sidebar_div > li {
        line-height: 1.2em;
    }
</style>