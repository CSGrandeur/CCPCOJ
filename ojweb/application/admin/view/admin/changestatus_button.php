{if $defunct == '1'}
<button type="button" field="defunct" itemid="{$item_id}" {if isset($item_name)}item_name="{$item_name}"{/if} class="change_status btn btn-warning btn-sm" status="1" title="点击切换为公开状态 (Click to switch to Public)">
    <span class="cn-text"><i class="bi bi-lock-fill "></i>隐藏</span><span class="en-text">Hidden</span>
</button>
{else /}
<button type="button" field="defunct" itemid="{$item_id}" {if isset($item_name)}item_name="{$item_name}"{/if} class="change_status btn btn-success btn-sm" status="0" title="点击切换为隐藏状态 (Click to switch to Hidden)">
    <span class="cn-text"><i class="bi bi-unlock-fill "></i>公开</span><span class="en-text">Public</span>
</button>
{/if}
{include file="../../admin/view/admin/js_changestatus" /}