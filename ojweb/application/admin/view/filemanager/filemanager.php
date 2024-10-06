{__NOLAYOUT__}
<!DOCTYPE html>
<html>
{include file="../../csgoj/view/public/global_head" /}
<body>
<div class="container">
<h2>{$inputinfo['item']|ucwords}: {$inputinfo['id']} | {$iteminfo['attach']}</h2>
<script type="text/javascript">
	//table related
	var re_checkfile = /^[0-9a-zA-Z-_\.\(\)-]+\.(jpg|png|gif|bmp|svg|ico|rar|zip|7z|tar|pdf|doc|docx|xls|xlsx|ppt|pptx|txt)$/;
</script>
{include file="filemanager/js_upload" /}
</div>
</body>
</html>