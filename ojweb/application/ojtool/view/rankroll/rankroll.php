<script>
    let OJ_MODE = "<?php echo $OJ_MODE; ?>";
    var cid = "<?php echo $contest['contest_id']; ?>";
    var contest_title = "<?php echo $contest['title']; ?>"
    var contest_attach = "<?php echo $contest['attach']; ?>";
    var PAGE_TYPE = 'roll';
    var FLG_PKG_MODE = false;
</script>
{js href="__STATIC__/csgoj/rank_common.js" /}
{js href="__STATIC__/ojtool/js/rank_tool.js" /}
{js href="__STATIC__/ojtool/js/rank_base.js" /}
{js href="__STATIC__/ojtool/js/newrank_rank_base.js" /}
{js href="__STATIC__/ojtool/js/newrank_rank.js" /}

<h1>滚榜：{$contest['title']}</h1>
<div class="btn-group input-group" role="group">
    <button class="btn btn-warning button_fullscreen"><i class="bi bi-arrows-fullscreen"></i>&nbsp;启动</button>
    <button class="btn btn-primary button_init_data"><i class="bi bi-save"></i>&nbsp;初始化</button>
    <button class="btn btn-info button_help"><i class="bi bi-chat-right-text-fill"></i>&nbsp;帮助</button>
    <select class="exam_info_form form-select" name="with_star_team" id="with_star_team" aria-label="open or close">
        <option selected value="0">打星不排名</option>
        <option value="1">不含打星</option>
        <option value="2">打星参与排名</option>
    </select>
    <button class="btn btn-success" href="__OJTOOL__/rankroll/team_image?cid={$contest['contest_id']}" id="team_photo_btn"><i class="bi bi-card-image"></i>&nbsp;队伍照片</button>
    <button class="btn btn-dark" id="pack_rankroll_btn"><i class="bi bi-usb-drive"></i>&nbsp;打包</button>
</div>
{include file="rankroll/pack_rankroll" /}
{include file="public/js_toolbox" /}
{include file="../../csgoj/view/public/js_identicon" /}

<div id="rankroll_div">
    <div id="rank_header_div">
        <div class="h_td h_rank">排名</div>
        <div class="h_td h_logo">图标</div>
        <div class="h_td h_team_content">队伍</div>
        <div class="h_td h_solve">题数</div>
        <div class="h_td h_time">罚时</div>
    </div>
    <div class="grid" id="rank_grid_div">

    </div>
    <div class="modal fade" id="award_modal_div" tabindex="-1" role="dialog" aria-labelledby="award_modal_div_label" aria-hidden="true">
        <div class="modal-dialog modal-award">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span id="award_modal_div_label_span">获奖信息</span> &nbsp;&nbsp;</h5>
                </div>
                <div class="modal-body" id="award_modal_div_content">
                    <div id="award_div">
                        <div class="award_img_col">
                            <img id="award_team_img" award="" src="#" onerror="TeamPhotoUriOnError(this)" />
                        </div>
                        <div class="award_info_col">
                            <div id="award_info" award="">
                                <div id="award_level" award=""></div>
                                <div id="awrad_school_logo"><img school="#" src="#" onerror="$('#awrad_school_logo').hide()" /></div>
                                <div id="award_school" class="award_info_div">
                                    <div class="award_info_title">单位：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_name" class="award_info_div">
                                    <div class="award_info_title">队伍：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_tmember" class="award_info_div">
                                    <div class="award_info_title">成员：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_coach" class="award_info_div">
                                    <div class="award_info_title">教练：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_rk" class="award_info_div">
                                    <div class="award_info_title">名次：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_fb" class="award_info_div">
                                    <div class="award_info_title">首答：</div>
                                    <div class="award_info_content"></div>
                                </div>
                                <div id="award_solved" class="award_info_div">
                                    <div class="award_info_title">题数：</div>
                                    <div class="award_info_content"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="loading_div" class='overlay'>
    <div id="loading_spinner" class="spinner-border">&nbsp;初始化中...</div>
</div>

<link href="__STATIC__/ojtool/css/rankroll.css" rel="stylesheet">


{js href="__STATIC__/ojtool/js/rank_roll.js" /}