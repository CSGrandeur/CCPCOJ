{if !$edit_mode || $edit_mode && $item_priv }
<div class="mb-3">
    <label for="cooperator" class="form-label">
        协作者<span class="en-text">Co-editor</span>
        <small class="text-muted d-block">用逗号分隔，最多6个协作者<span class="en-text">Split with ','. At most 6 co-editors</span></small>
    </label>
    <input type="text" class="form-control" id="cooperator" placeholder="例如: user1,user2,user3" name="cooperator" {if $edit_mode}value="{$cooperator}"{/if}>
</div>
{/if}