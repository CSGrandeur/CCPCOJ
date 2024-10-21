<div class="page-header">
    <h1>
        Edit Contest
    </h1>
</div>
{include file="../../csgoj/view/public/bootstrap_select" /}

    <form id="contest_edit_form" method='post' action="/{$module}/admin/{$action}_ajax?cid={$contest['contest_id']}">
        <div class="row">
            <div class="col-md-12 col-sm-12">
                <div class="form-inline">
                    <label for="start_time" style="width:150px;">Start Time:</label>
                    <label for="year">Year</label>     <input type="text" class="form-control" name="start_year"  style="width:80px;" value="{$start_year}">
                    <label for="Month">Month</label>   <input type="text" class="form-control" name="start_month" style="width:50px;" value="{$start_month}">
                    <label for="Day">Day</label>       <input type="text" class="form-control" name="start_day"   style="width:50px;" value="{$start_day}">
                    <label for="Hour">Hour</label>     <input type="text" class="form-control" name="start_hour"  style="width:50px;" value="{$start_hour}">
                    <label for="Minute">Minute</label> <input type="text" class="form-control" name="start_minute"style="width:50px;" value="{$start_minute}">
                </div>
                <div class="form-inline">
                    <label for="end_time" style="width:150px;">End Time:</label>
                    <label for="year">Year</label>     <input type="text" class="form-control" name="end_year"  style="width:80px;" value="{$end_year}">
                    <label for="Month">Month</label>   <input type="text" class="form-control" name="end_month" style="width:50px;" value="{$end_month}">
                    <label for="Day">Day</label>       <input type="text" class="form-control" name="end_day"   style="width:50px;" value="{$end_day}">
                    <label for="Hour">Hour</label>     <input type="text" class="form-control" name="end_hour"  style="width:50px;" value="{$end_hour}">
                    <label for="Minute">Minute</label> <input type="text" class="form-control" name="end_minute"style="width:50px;" value="{$end_minute}">
                </div>
                <div class="form-inline">
                    <label for="start_time" style="width:150px;">Award Ratio:</label>
                    <label for="ratio_gold">Gold</label>     <input type="text" class="form-control" name="ratio_gold"  style="width:80px;" value="{$ratio_gold}">
                    <label for="ratio_silver">Silver</label>   <input type="text" class="form-control" name="ratio_silver" style="width:80px;" value="{$ratio_silver}">
                    <label for="ratio_bronze">Bronze</label>       <input type="text" class="form-control" name="ratio_bronze"   style="width:80px;" value="{$ratio_bronze}">
                </div>
                {if $OJ_STATUS == 'cpc'}
                <div class="form-inline">
                    <label for="start_time" style="width:150px;">Frozen Minutes:</label>
                    <label for="ratio_gold">Before End</label>     <input type="text" class="form-control" name="frozen_minute"  style="width:80px;" value="{$frozen_minute}">
                    <label for="ratio_silver">After End</label>   <input type="text" class="form-control" name="frozen_after" style="width:80px;" value="{$frozen_after}">
                </div>
                <div class="form-inline">
                    <label for="start_time" style="width:150px;">Top Team Number for School Rank:</label>
                    <input type="text" class="form-control" name="topteam"  style="width:80px;" value="{$topteam}">
                </div>
                {else /}
                <div class="form-inline">
                    <input type="hidden" class="form-control" name="frozen_minute"  style="width:80px;" value="0">
                    <input type="hidden" class="form-control" name="frozen_after" style="width:80px;" value="0">
                </div>
                <div class="form-inline">
                    <input type="hidden" class="form-control" name="topteam"  style="width:80px;" value="1">
                </div>
                {/if}

                <div class="form-inline">
                    <span style="display: inline-block;">
                        <label for="language" style="width:150px;">Language:</label>
                        <?php $ojLang = config('CsgojConfig.OJ_LANGUAGE'); ?>
                        <select name="language[]" class="selectpicker" multiple="multiple">
                            {foreach($ojLang as $k=>$val) }
                            <option value="{$k}" {if (($contest['langmask'] >> $k) & 1)}selected="selected"{/if}>
                                {$val}
                            </option>
                            {/foreach}
                        </select>
                        &nbsp;&nbsp;
                    </span>
                </div>
                <label for="description">Description/Announcement (markdown)：</label>
                <textarea id="contest_description" class="form-control" placeholder="Content..." rows="5" cols="50" name="description" >{$contest['description']|htmlspecialchars}</textarea>
            </div>
        </div>
        <input type="hidden" id='id_input' value="{$contest['contest_id']}" name="cid">
        <button type="submit" id="submit_button" class="btn btn-primary">Modify Contest</button>
    </form>
<input type="hidden" id='page_info' edit_mode="1">
<style type="text/css">
.form-inline {
    padding-bottom: 10px;
}
</style>
<script type="text/javascript">
    var page_info = $('#page_info');
    var edit_mode = page_info.attr('edit_mode');
    var submit_button = $('#submit_button');
    var submit_button_text = submit_button.text();
    $(document).ready(function()
    {
        $('input[type="text"],textarea').tooltipster({
            trigger: 'custom',
            position: 'bottom',
            animation: 'grow',
            theme: 'tooltipster-noir',
            distance: -15
        });
        $('#contest_edit_form').validate({
            rules:{
                title:{
                    required: true,
                    maxlength: 200
                },
                description: {
                    maxlength: 16384
                },
                password:{
                    minlength: 6,
                    maxlength: 15
                },
                ratio_gold: {
                    number: true,
                    max: 999,
                    min: 0
                },
                ratio_silver: {
                    number: true,
                    max: 999,
                    min: 0
                },
                ratio_bronze: {
                    number: true,
                    max: 999,
                    min: 0
                },
                frozen_minute: {
                    number: true,
                    max: 2592000,
                    min: -1
                },
                frozen_after: {
                    number: true,
                    max: 2592000,
                    min: -1
                },
                topteam: {
                    number: true,
                    max: 20,
                    min: 1
                }
            },
            errorPlacement: function (error, element) {
                var ele = $(element),
                    err = $(error),
                    msg = err.text();
                if (msg != null && msg !== '') {
                    ele.tooltipster('content', msg);
                    ele.tooltipster('open');
                }
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).removeClass(errorClass).addClass(validClass).tooltipster('close');
            },
            submitHandler: function(form)
            {
                submit_button.attr('disabled', true);
                submit_button.text('Waiting...');
                $(form).ajaxSubmit({
                    success: function(ret)
                    {
                        if(ret['code'] == 1)
                        {
                            if(typeof(ret['data']['alert']) != 'undefined' && ret['data']['alert'] == true){
                                alertify.alert(ret['msg']);
                            }else{
                                alertify.success(ret['msg']);
                            }
                            button_delay(submit_button, 3, submit_button_text);
                            if(edit_mode != '1') {
                                setTimeout(function(){location.href='contest_edit?id='+ret['data']['id']}, 500);
                            }
                        }
                        else
                        {
                            alertify.alert(ret['msg']);
                            button_delay(submit_button, 3, submit_button_text);
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

</script>