<div class="page-header">
    <h1>
        {if $edit_mode && !$copy_mode }
        Edit Contest <a href="__OJ__/contest/problemset?cid={$contest['contest_id']}" target="_blank">#{$contest['contest_id']}</a>
        <a href="__ADMIN__/filemanager/filemanager?item={$controller}&id={$contest['contest_id']}" target="_blank"><button class="btn btn-success" id="attachfile">Attach file manager</button></a>
        <?php $defunct = $contest['defunct']; $item_id = $contest['contest_id']; ?>
        {include file="admin/changestatus_button" /}
        {else /}
        Add Contest
        {/if}
    </h1>
</div>
{include file="../../csgoj/view/public/bootstrap_select" /}

    <form id="contest_edit_form" method='post' action="__ADMIN__/contest/{if $edit_mode && !$copy_mode}contest_edit_ajax{else /}contest_add_ajax{/if}">
        <div class="row">
            <div class="col-md-9 col-sm-9">
                <div class="form-group">
                    {include file="admin/co_editor_input" /}
                    <label for="title">Contest Title：</label>
                    <input type="text" class="form-control" id="title" placeholder="Contest Title..." name="title" {if $edit_mode && !$copy_mode}value="{$contest['title']}"{/if}>
                </div>
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
                    <label for="award_ratio" style="width:150px; color:red;">Award Ratio (%):</label>
                    <label for="ratio_gold">Gold</label>     <input type="text" class="form-control" name="ratio_gold"  style="width:80px;" value="{$ratio_gold}">
                    <label for="ratio_silver">Silver</label>   <input type="text" class="form-control" name="ratio_silver" style="width:80px;" value="{$ratio_silver}">
                    <label for="ratio_bronze">Bronze</label>       <input type="text" class="form-control" name="ratio_bronze"   style="width:80px;" value="{$ratio_bronze}">
                </div>
                <div class="form-inline">
                    <label for="frozen_minute" style="width:150px;">Frozen Minutes:</label>
                    <label for="frozen_minute">Before End</label>     <input type="text" class="form-control" name="frozen_minute"  style="width:80px;" value="{$frozen_minute}">
                    <label for="frozen_after" style="color:red;">After End</label>   <input type="text" class="form-control" name="frozen_after" style="width:80px;" value="{$frozen_after}">
                </div>
                <div class="form-inline">
                    <span style="display: inline-block;">
                        <label style="width:150px; color:red;">Contest Type:</label>
                        <div class="radio">
                            <label><input name="private" type="radio" value="0" {if !isset($private) || $private % 10 == 0} checked {/if} />Public</label>
                            <label><input name="private" type="radio" value="1" {if isset($private) && $private % 10 == 1} checked {/if} />Private</label>
                            <label><input name="private" type="radio" value="2" {if isset($private) && $private % 10 == 2} checked {/if} />Standard</label>
                        </div>
                        &nbsp;&nbsp;
                    </span>
                    <span style="display: inline-block;">
                        <label for="password">Password (Only for Public):</label>
                        <input type="text" class="form-control" name="password"  {if $edit_mode}value="{$contest['password']}"{/if}>
                    </span>
                </div>
                <div class="form-inline">
                    <label for="start_time" style="width:150px;">Top Team Number for School Rank:</label>
                    <input type="text" class="form-control" name="topteam"  style="width:80px;" value="{$topteam}">
                </div>
                <div class="form-inline">
                    <span style="display: inline-block;">
                        <label for="language" style="width:150px;">Language:</label>
                        <?php $ojLang = config('CsgojConfig.OJ_LANGUAGE'); ?>
                        <select name="language[]" class="selectpicker" multiple="multiple">
                            {foreach($ojLang as $k=>$val) }
                            <option value="{$k}" {if $edit_mode && (($contest['langmask'] >> $k) & 1)}selected="selected"{/if}>
                                {$val}
                            </option>
                            {/foreach}
                        </select>
                        &nbsp;&nbsp;
                    </span>
                </div>
                <div class="form-group">
                    <!-- <label for="problems">Problems(split problem_id with ',', add ":xx" for problem score):</label>
                    <input type="text" class="form-control" id="title" placeholder="1000,1001:15,1002:20..." name="problems" {if $edit_mode}value="{$problems}"{/if}> -->
                    <label for="problems">Problems(split problem_id with ','):</label>
                    <input type="text" class="form-control" id="problems" placeholder="1000,1001,1002..." name="problems" {if $edit_mode}value="{$problems}"{/if} >
                </div>
                <div class="form-group">
                    <label for="balloon_colors">Balloon Color(hexadecimal like 3FFFAA):</label>
                    <div id="balloon_color_div"></div>
                    <input type="text" class="form-control" id="balloon_colors" placeholder="3F3F3F,202020,AB3062..." name="balloon_colors" {if $edit_mode}value="{$balloon_colors}"{/if} >
                </div>
                <label for="description">Description/Announcement (markdown)：</label>
                <textarea id="contest_description" class="form-control" placeholder="Content..." rows="5" cols="50" name="description" >{if $edit_mode}{$contest['description']|htmlspecialchars}{/if}</textarea>
            </div>
            <div class="col-md-3 col-sm-3">
                <label for="users">Users(split user_id with '\n')：</label>
                <textarea class="form-control" placeholder="team001&#10;team002&#10;..." rows="20" name="users" >{if $edit_mode && !$copy_mode}{$users}{/if}</textarea>
            </div>
        </div>
        {if $edit_mode && !$copy_mode }
        <input type="hidden" id='id_input' value="{$contest['contest_id']}" name="contest_id">
        {/if}
        <button type="submit" id="submit_button" class="btn btn-primary">{if $edit_mode && !$copy_mode}Modify Contest{else/}Add Contest{/if}</button>
    </form>
<input type="hidden" id='page_info' edit_mode="{if $edit_mode && !$copy_mode}1{else/}0{/if}">
<style type="text/css">
.form-inline {
    padding-bottom: 10px;
}
#balloon_color_div {
    display: flex;
}
.balloon_color_block {
    margin-right: 2px;
    width: 53px;
    height:20px;
    color: white;
    text-align: center;
}
#balloon_colors {
	font-family: sans-serif, 'Simsun', 'Microsoft Yahei Mono', 'Lato', "PingFang SC", "Microsoft YaHei";
}
</style>
<script type="text/javascript">
    var page_info = $('#page_info');
    var edit_mode = page_info.attr('edit_mode');
    var submit_button = $('#submit_button');
    var submit_button_text = submit_button.text();
    let balloon_color_div = $('#balloon_color_div');
    let balloon_colors_input = $('#balloon_colors')
    let problems_input = $('#problems')
    function InitBalloonColorDiv() {
        let problem_list = problems_input.val().split(',');
        let balloon_color_list = balloon_colors_input.val().split(',');
        let balloon_color_list_new = [];
        let balloon_color_block = [];
        let map_color_record = {};
        for(let i = 0; i < problem_list.length; i ++) {
            let cl_show = '';
            let cl = -1;
            if (/^[a-z]+$/.test(balloon_color_list[i])) {
                cl_show = `${balloon_color_list[i]}`;
                balloon_color_list_new.push(balloon_color_list[i]);
            } else {

                if(i < balloon_color_list.length) {
                    cl = parseInt(balloon_color_list[i], 16);
                }
                if(cl < 0 || cl >= 16777216 || isNaN(cl) || (cl in map_color_record)) {
                    cl = Math.floor(Math.random() * 16777216);
                }
                map_color_record[cl] = true;
                cl = cl.toString(16).toUpperCase().padStart(6, '0');
                cl_show = `#${cl}`;
                balloon_color_list_new.push(cl);

            }
            balloon_color_block.push(`<div style="background-color:${cl_show};" class="balloon_color_block">${String.fromCharCode('A'.charCodeAt(0) + i)}</div>`);
        }
        balloon_color_div.html(balloon_color_block.join(''));
        balloon_colors_input.val(balloon_color_list_new.join(','));
    }
    balloon_colors_input.change(function() {
        InitBalloonColorDiv();
    });
    problems_input.change(function() {
        InitBalloonColorDiv();
    });
    $(document).ready(function()
    {
        InitBalloonColorDiv();
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