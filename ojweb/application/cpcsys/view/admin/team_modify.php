<!-- 修改队伍信息 Modal -->
<div class="modal fade" id="teamModifyModal" tabindex="-1" aria-labelledby="teamModifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title bilingual-inline" id="teamModifyModalLabel">
                    <span class="cn-text"><i class="bi bi-pencil-square me-2"></i>
                    修改队伍信息
                    </span><span class="en-text">Update Team Information</span>
                    <span class="ms-2 text-muted" id="team_modify_header_team_id" style="font-size: 0.85em; font-weight: normal;"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="team_modify_form" method="post" action="/cpcsys/admin/team_modify_ajax">
                    <div class="row g-3">
                        <!-- 队伍ID (隐藏) -->
                        <input type="hidden" id="team_modify_team_id" name="team_id" value="">
                        <input type="hidden" id="team_modify_cid" name="cid" value="">
                        
                        <!-- 队伍名称 -->
                        <div class="col-md-6">
                            <label for="team_modify_name" class="form-label bilingual-label">
                                队伍名称：<span class="en-text">Team Name</span>
                            </label>
                            <input type="text" id="team_modify_name" class="form-control teaminfo_input" name="name" 
                                   placeholder="最多100个字符" value="">
                        </div>
                        
                        <!-- 队伍名称(副语言) -->
                        <div class="col-md-6">
                            <label for="team_modify_name_en" class="form-label bilingual-label">
                                队伍名称(副语言)：<span class="en-text">Team Name (Secondary Language)</span>
                            </label>
                            <input type="text" id="team_modify_name_en" class="form-control teaminfo_input" name="name_en" 
                                   placeholder="最多120个字符" value="">
                        </div>
                        
                        <!-- 学校/组织 -->
                        <div class="col-md-6">
                            <label for="team_modify_school" class="form-label bilingual-label">
                                学校/组织：<span class="en-text">School/Organization</span>
                            </label>
                            <input type="text" id="team_modify_school" class="form-control teaminfo_input" name="school" 
                                   placeholder="学校或组织名称" value="">
                        </div>
                        
                        <!-- 国家/地区 -->
                        <div class="col-md-6">
                            <label for="team_modify_region" class="form-label bilingual-label">
                                国家/地区：<span class="en-text">Country/Region</span>
                            </label>
                            <div class="csg-select">
                                <select class="csg-select-input teaminfo_input" name="region" id="team_modify_region"
                                        data-csg-searchable="true" 
                                        data-csg-allow-custom="true"
                                        data-csg-placeholder="请选择国家/地区"
                                        data-csg-placeholder-en="Please select country/region"
                                        data-csg-custom-text="自定义输入"
                                        data-csg-custom-text-en="Custom input">
                                    <option value="">请选择国家/地区</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 队员 -->
                        <div class="col-12">
                            <label for="team_modify_tmember" class="form-label bilingual-label">
                                队员：<span class="en-text">Members</span>
                            </label>
                            <input type="text" id="team_modify_tmember" class="form-control teaminfo_input" name="tmember" 
                                   placeholder="队员姓名，用逗号分隔" value="">
                        </div>
                        
                        <!-- 教练 -->
                        <div class="col-12">
                            <label for="team_modify_coach" class="form-label bilingual-label">
                                教练：<span class="en-text">Coach</span>
                            </label>
                            <input type="text" id="team_modify_coach" class="form-control teaminfo_input" name="coach" 
                                   placeholder="教练姓名" value="">
                        </div>
                        
                        <!-- 机房 -->
                        <div class="col-md-6">
                            <label for="team_modify_room" class="form-label bilingual-label">
                                房间/区域：<span class="en-text">Room</span>
                            </label>
                            <input type="text" id="team_modify_room" class="form-control teaminfo_input" name="room" 
                                   placeholder="机房编号" value="">
                        </div>
                        
                        <!-- 队伍类型 -->
                        <div class="col-md-6">
                            <label for="team_modify_tkind" class="form-label bilingual-label">
                                队伍类型：<span class="en-text">Team Type</span>
                            </label>
                            <select id="team_modify_tkind" class="form-select teaminfo_input" name="tkind">
                                <option value="0">正式队伍 (Regular Team)</option>
                                <option value="1">女队 (Girls Team)</option>
                                <option value="2">打星队伍 (Star Team)</option>
                            </select>
                        </div>
                        
                        <!-- 新密码 -->
                        <div class="col-12">
                            <label for="team_modify_password" class="form-label bilingual-label">
                                新密码：<span class="en-text">New Password</span>
                            </label>
                            <input type="text" id="team_modify_password" class="form-control teaminfo_input" 
                                   placeholder="留空表示不修改密码，至少6个字符" name="password">
                            <div class="form-text">
                                <span class="bilingual-inline">
                                    <span>留空表示不修改密码</span>
                                    <span class="en-text">Leave blank to keep current password</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <span class="cn-text"><i class="bi bi-x-circle me-1"></i>
                    取消</span><span class="en-text">Cancel</span>
                </button>
                <button type="submit" class="btn btn-primary bilingual-button" id="team_modify_submit_button">
                    <span class="cn-text"><i class="bi bi-check-circle me-2"></i>
                    提交修改</span><span class="en-text">Submit Changes</span>
                </button>
            </div>
        </div>
    </div>
</div>

{include file="../../csgoj/view/public/base_select" /}