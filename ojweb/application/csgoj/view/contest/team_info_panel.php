{if isset($login_teaminfo) && $login_teaminfo}
<div id="cteaminfo_panel" style="min-width: {if !isset($isContestStaff) || !$isContestStaff }256{else /}160{/if}px;">
    <div id="contest_logout_div">
        <div class="cteam_line_info" title="🆔队伍(TeamID): {$login_teaminfo['team_id']}
            {if $login_teaminfo['room']}🏘️机房/区域(Zone): {$login_teaminfo['room']}{/if}">
            🆔{$login_teaminfo['team_id']}
            {if $login_teaminfo['room']} 🏘️{$login_teaminfo['room']}{/if}
        </div>
        <a href='#' id="contest_logout_button"><strong>Logout</strong></a>
    </div>

    {if !isset($isContestStaff) || !$isContestStaff }
    <div id="cteam_baseinfo">
        <div class="cteam_line_info" title="🏫学校(School): {$login_teaminfo['school']|htmlspecialchars}">
            🏫{$login_teaminfo['school']}
        </div>
        <div class="cteam_line_info" title="🏷️队名(Team Name): {$login_teaminfo['name']|htmlspecialchars}">
            🏷️{$login_teaminfo['name']}
        </div>
        <div class="cteam_line_info" title="🤝成员(Members): {$login_teaminfo['tmember']}{if $login_teaminfo['coach']} 👨‍🏫教练(Coach): {$login_teaminfo['coach']}{/if}">
            🤝{$login_teaminfo['tmember']}{if $login_teaminfo['coach']} 👨‍🏫{$login_teaminfo['coach']}{/if}
        </div>
    </div>
    <hr class="custom-hr">
    <div id="cteam_scoreinfo">
        {include file="../../csgoj/view/contest/team_score_panel" /}
    </div>

    {/if}

    <script type="text/javascript">
        $(document).ready(function() {
            $('#cpc_team_id_span').tooltipster({
                theme: 'tooltipster-noir',
                interactive: true,
                contentCloning: true
            });
            let contest_logout_button = $('#contest_logout_button');
            contest_logout_button.unbind('click').click(function() {
                $.post("__CPC__/contest/contest_logout_ajax?cid={$contest['contest_id']}", function(ret) {
                    if (ret['code'] == 1) {
                        alertify.success(ret['msg']);
                        setTimeout(function() {
                            location.reload()
                        }, 500);
                    } else {
                        alertify.alert(ret['msg']);
                        // location.href = '__OJ__';
                    }
                });
                return false;
            });
        });
    </script>
    <style>
        #cteaminfo_panel {
            display: flex;
            flex-direction: column;
            margin-left: auto;
        }
        #cteam_baseinfo > div {
            margin-bottom: 2px;
        }

        #contest_logout_div {
            display: flex;
            flex-direction: row;
        }

        #contest_logout_button {
            margin-left: auto;
        }

        .cteam_line_info {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            display: block;
        }
        .custom-hr {
            margin: 5px 0; /* 控制上下间距 */
            border: none; /* 去掉默认边框 */
            border-top: 1px solid #ccc; /* 设置顶部边框 */
        }
    </style>
</div>
{/if}