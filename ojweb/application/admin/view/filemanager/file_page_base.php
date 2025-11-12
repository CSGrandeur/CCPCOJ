<!DOCTYPE html>
<html>
{include file="../../csgoj/view/public/global_head" /}
<body>

<div class="container">
	
<div class="page-header">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">

                <h1 class="page-title bilingual-inline">
                    {$page_title}<span class="en-text">{$page_title_en}</span>
                </h1>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-primary fs-5 px-3 py-2 text-decoration-none fw-bold">
                        <i class="bi bi-hash me-1"></i>{$inputinfo['id']}
                    </span>
                    {if isset($show_title) && $show_title}
                    <div class="vr"></div>
                    <span class="text-dark fs-6 fw-medium" style="max-width: 20vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 0.5rem 1rem; background: rgba(0,123,255,0.1); border-radius: 0.5rem; " title="{$iteminfo['title']}">
                        <i class="bi bi-tag me-1"></i>{$iteminfo['title']}
                    </span>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
	//table related
	if (typeof re_checkfile === 'undefined') {
		var re_checkfile = {$file_regex};
	}
</script>
{include file="filemanager/js_upload" /}
</div>

</body>
</html>
