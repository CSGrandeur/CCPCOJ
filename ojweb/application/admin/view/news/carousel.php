<div class="page-header">
	<h1>Edit Carousel
		<a href="__ADMIN__/filemanager/filemanager?item={$controller}&id={$staticPage['carousel']}" target="_blank"><button class="btn btn-success" id="attachfile">Attach file manager</button></a>
	</h1>
</div>
<div class="container">
	<form id="news_edit_form" method='post' action="__ADMIN__/news/carousel_ajax">
		<div class="form-group">
			<label for="show_carousel_switch">Show Carousel：</label>
			<input type="checkbox" id="show_carousel_switch" name="defunct">
		</div>
		<?php for($i = 0; $i < 3; $i ++): ?>
		<div class="form-group">
			<label for="carousel[]">Carousel {$i} :</label>
			{foreach $carouselItem as $item}
			<input type="text" class="form-control" placeholder="{$item}" name="{$item}{$i}" value="{$carousel[$item][$i]}">
			{/foreach}
		</div>
		<?php endfor ?>
		<br/>
		<input type="hidden" id='id_input' value="{$staticPage['carousel']}" name="news_id">
		<button type="submit" id="submit_button" class="btn btn-primary">Update Carousel</button>
		<button type="button" id="shift_button" class="btn btn-info" title="让三个Carousel的内容循环移位一下（用于添加新的Carousel到第一个位置）">Cyclic Shift</button>
		<button type="button" id="clear_button" class="btn btn-warning" onclick="$(':input').val('');">Clear Up</button>
	</form>
</div>
<input type="hidden" id="show_carousel_check" value="{if($news['defunct'] == '0')} true {else/}false{/if}">
<script type="text/javascript">
	var switch_box = $('#show_carousel_switch');
	var shift_button = $('#shift_button');
	$(document).ready(function()
	{
		switch_box.bootstrapSwitch();
		var defunct_checked = $.trim($('#show_carousel_check').val());
		if (defunct_checked == "true") {
			switch_box.bootstrapSwitch('state', true, true);
		}
		else {
			switch_box.bootstrapSwitch('state', false, true);
		}
		$('#news_edit_form').validate({
			rules:{
				title:{
					required: true,
					maxlength: 200
				}
			},
			submitHandler: function(form)
			{
				$(form).ajaxSubmit(
					{
						success: function(ret)
						{
							var submit_button = $('#submit_button');
							if(ret["code"] == 1)
							{
								alertify.success(ret['msg']);
								button_delay(submit_button, 3, 'Modify News');
							}
							else
							{
								alertify.error(ret['msg']);
								button_delay(submit_button, 3, 'Modify News');
							}
							return false;
						}
					});
				return false;
			}
		});

	});
	$(window).keydown(function(e) {
		if (e.keyCode == 83 && e.ctrlKey) {
			e.preventDefault();
			var a=document.createEvent("MouseEvents");
			a.initEvent("click", true, true);
			$('#submit_button')[0].dispatchEvent(a);
		}
	});
	shift_button.on('click', function(){
		var items = [
			'href',
			'src',
			'header',
			'content'
		];
		var now, nex;
		for(var i = 0; i < items.length; i ++)
		{
			for (var j = 0; j < 3; j++)
			{
				now = $("input[name='" + items[i] + j + "']").val();
				if(j > 0)
				{
					$("input[name='" + items[i] + j + "']").val(nex)
				}
				nex = now;
			}
			$("input[name='" + items[i] + 0 + "']").val(nex)
		}
	});
</script>
<style type="text/css">
	input{
		margin-top: 5px;
	}
</style>