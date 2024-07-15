<hr id="hidden_user_panel">
{if $OJ_MODE=='online' || IsAdmin('administrator') }
<li {if ($module == 'index') } class="active" {/if} ><a href="__HOME__/index">主页</a></li>
{if $OJ_OPEN_ARCHIVE }
<li {if ($controller == 'problemarchive')} class="active" {/if} ><a href="__OJ__/problemarchive">赛事题目归档</a></li>
{/if}
<li {if ($controller == 'problemset')} class="active" {/if} ><a href="__OJ__/problemset">开放题目集</a></li>
<li {if ($controller == 'status')} class="active" {/if} ><a href="__OJ__/status">提交状态</a></li>
<li {if ($controller == 'userrank')} class="active" {/if} ><a href="__OJ__/userrank">用户总表</a></li>
<li {if ($module == 'csgoj' && $controller == 'contest')} class="active" {/if} ><a href="__OJ__/contest">比赛</a></li>
{/if}
{if $OJ_STATUS == 'cpc'}
<li  {if $module == 'cpcsys' && $controller == 'contest'} class="active" {/if} ><a href="__CPC__/contest">XCPC标准比赛</a></li>
{/if}
<li {if ($controller == 'faqs')} class="active" {/if} ><a href="{if $module == 'cpcsys'}__CPC__{else}__OJ__{/if}/faqs">常见疑问</a></li>

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
    <li {if ($module == 'admin')} class="active" {/if}><a href="__ADMIN__/index">管理后台</a></li>
<?php } ?>

<hr/>
{if $OJ_MODE=='online' || IsAdmin() }
<li><a href="__OJTOOL__">工具集</a></li>
{/if}

{$OJ_ADDITION_LINK }