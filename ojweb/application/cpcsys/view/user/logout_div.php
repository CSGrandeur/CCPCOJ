<div class="logout_div">
	<label>
	欢迎 <a href="__OJ__/user/userinfo?user_id={$Think.session.user_id}" class="a_noline">{$Think.session.user_id}</a> !
	</label>
	<br/>
	<a href="__OJ__/user/modify?user_id={$Think.session.user_id}" class="a_noline">修改信息</a>
	<br/>
	<button class="btn btn-sm btn-primary" id="logout_button" type="button">登出</button>
</div>

<script type="text/javascript">
	$(document).ready(function(){
		$("#logout_button").unbind('click').click(function() {
			$.post("__OJ__/User/logout_ajax", function(ret){
				if(ret.code == 1) {
					alertify.success(ret['msg']);
					$('#logout_button').attr('disabled', true);
					setTimeout(function(){location.reload()}, 500);
				}
				else {
					alertify.alert(ret['msg']);
					location.href = '__OJ__';
				}
			});
			return false;
		});
	});
</script>