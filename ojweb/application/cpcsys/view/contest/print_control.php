{if $printManager && !$isContestAdmin }
{js href="__STATIC__/lodop/LodopFuncs.js" /}
{js href="__STATIC__/csgoj/contest/print_control.js" /}
<object id="LODOP_OB" classid="clsid:2105C259-1E0C-4534-8141-A753534CB4CA" width=0 height=0>
	<embed id="LODOP_EM" type="application/x-print-lodop" width=0 height=0></embed>
</object>
<script type="text/javascript">
	// 初始化 Lodop（所有打印逻辑已移至 print_control.js）

</script>
{/if}