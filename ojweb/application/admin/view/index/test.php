<div class="container mt-4">
	<div class="page-header mb-4">
		<h1 class="page-title">
			<span class="cn-text"><i class="bi bi-bug me-2"></i>测试页面</span><span class="en-text">Test Page</span>
		</h1>
	</div>
	
	<div class="card">
		<div class="card-header">
			<h5 class="card-title mb-0">
				<span class="cn-text"><i class="bi bi-code-square me-2"></i>API 测试</span><span class="en-text">API Test</span>
			</h5>
		</div>
		<div class="card-body">
			<div class="alert alert-info">
				<span class="cn-text"><i class="bi bi-info-circle me-2"></i>
				这是一个测试页面，用于验证系统功能。
				</span><span class="en-text">This is a test page for verifying system functionality.</span>
			</div>
			
			<div class="row">
				<div class="col-md-6">
					<button id="testBtn" class="btn btn-primary">
						<span class="cn-text"><i class="bi bi-play-circle me-1"></i>执行测试</span><span class="en-text">Run Test</span>
					</button>
				</div>
				<div class="col-md-6">
					<div id="testResult" class="alert alert-secondary" style="display: none;">
						<span class="cn-text"><i class="bi bi-check-circle me-2"></i>测试结果</span><span class="en-text">Test Result</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	$(document).ready(function(){
		$('#testBtn').click(function(){
			$('#testResult').hide();
			
			$.get(
				'/csgoj/problemset/problem?pid=1000&ajaxuser=isun_voj&ajaxtoken=c295ae8ba0980d2b951660a728b6071',
				{},
				function(ret){
					console.warn(ret);
					$('#testResult').html('<span class="cn-text"><i class="bi bi-check-circle me-2"></i>测试完成</span><span class="en-text">Test Completed</span> - 查看控制台<span class="en-text">Check Console</span>').show();
				}
			).fail(function(){
				$('#testResult').removeClass('alert-secondary').addClass('alert-danger').html('<span class="cn-text"><i class="bi bi-exclamation-triangle me-2"></i>测试失败</span><span class="en-text">Test Failed</span>').show();
			});
		});
	});
</script>