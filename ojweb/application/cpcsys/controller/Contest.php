<?php
namespace app\cpcsys\controller;
use think\Db;
use think\Validate;
use app\csgoj\controller\Contest as Contestbase;
use phpDocumentor\Reflection\Types\Null_;

class Contest extends Contestbase
{
    var $teamSessionName;       // team 的 session 字段名
    var $watcherUser;
    var $balloonManager;
    var $balloonSender;
    var $printManager;
    var $isReviewer;
    public function _initialize()
    {
        $this->OJMode();
        $this->ContestInit();
    }
    
    public function ContestInit()
    {
        $this->assign('pagetitle', 'Standard Contest');
        $this->outsideContestAction = ['index', 'contest_list_ajax', 'contest_collect_team_ajax'];
        $this->allowPublicVisitAction = ['contest_login', 'rank', 'ranklist_ajax', 'scorerank', 'scorerank_ajax', 'schoolrank', 'schoolrank_ajax', 'contest', 'contest_auth_ajax', 'contest_auth_passwordless_ajax', 'team_auth_type_ajax', 'contest_data_ajax'];
        $this->ojLang = config('CsgojConfig.OJ_LANGUAGE');
        $this->ojResults = config('CsgojConfig.OJ_RESULTS');
        $this->ojResultsHtml = config('CsgojConfig.OJ_RESULTS_HTML');
        $this->allowLanguage = $this->ojLang;
        $this->running = false;
        $this->canJoin = false;
        $this->allowResults = [4, 5, 6, 7, 8, 9, 10, 11];
        $this->needAuth = false;
        $this->isAdmin = IsAdmin();
        $this->isContestAdmin = false;
        if($this->controller == 'contest' && !in_array($this->request->action(), $this->outsideContestAction) || $this->controller == 'admin' || $this->controller == 'contestadmin') {
            $this->GetContestInfo();
            $this->teamSessionName = '#cpcteam' . $this->contest['contest_id']; // 先获取contest，后组合teamSessionName，最后计算rankUseCache
            $this->rankUseCache = !$this->IsContestAdmin('admin') && !$this->IsContestAdmin('balloon_manager') && !$this->IsContestAdmin('balloon_sender') && !$this->IsContestAdmin('admin') && !$this->IsContestAdmin('watcher') ? 1 : 0;
            $this->isContestAdmin = $this->IsContestAdmin('admin');
            $this->GetVars();
            $this->ContestAuthentication();
            $this->SetAssign();
            
            // 为contest_login.php模板准备变量
            $clientIp = GetRealIp();
            $this->assign('client_ip', $clientIp);
            
            // 查询该IP对应的client的team_id_bind
            $team_id_bind = null;
            if (!empty($clientIp)) {
                $client = db('cpc_client')->where([
                    'contest_id' => $this->contest['contest_id'],
                    'ip_bind' => $clientIp
                ])->find();
                
                if ($client && !empty($client['team_id_bind'])) {
                    $team_id_bind = $client['team_id_bind'];
                }
            }
            $this->assign('team_id_bind', $team_id_bind);
            
            // 计算收集模式相关变量
            $addition = [];
            if (!empty($this->contest['addition'])) {
                $addition = Json2Array($this->contest['addition']);
                if (!$addition || !is_array($addition)) {
                    $addition = [];
                }
            }
            $flg_collect_team_id = isset($addition['flg_collect_team_id']) ? intval($addition['flg_collect_team_id']) : 0;
            
            // 判断是否在比赛前10分钟
            $now = time();
            $startTime = strtotime($this->contest['start_time']);
            $timeDiff = $startTime - $now;
            $isBefore10Min = $timeDiff >= 600; // 600秒 = 10分钟
            
            // 判断是否是收集模式
            $isCollectMode = ($flg_collect_team_id == 1 && $isBefore10Min);
            
            $this->assign('flg_collect_team_id', $flg_collect_team_id);
            $this->assign('isCollectMode', $isCollectMode);
        }
        $this->assign('contest_controller', 'contest');
    }
    public function SetAssignUser(){
        if($this->GetSession('?')){
            $this->contest_user = $this->GetSession('team_id');
            $this->assign('contest_user', $this->contest_user);
            $this->assign('login_teaminfo', $this->GetSession());
        } else {
            $this->contest_user = null;
            $this->assign('contest_user', null);
            $this->assign('login_teaminfo', null);
        }
        $this->proctorAdmin     =   $this->IsContestAdmin('admin');
        $this->watcherUser      =   $this->IsContestAdmin('watcher');
        $this->balloonManager   =   $this->IsContestAdmin('balloon_manager');
        $this->balloonSender    =   $this->IsContestAdmin('balloon_sender');
        $this->printManager     =   $this->IsContestAdmin('printer');
        $this->isReviewer       =   $this->IsContestAdmin('reviewer');
        $this->isContestStaff   =   $this->proctorAdmin || $this->balloonManager || $this->balloonSender || 
                                    $this->printManager || $this->isReviewer || $this->watcherUser;
        $this->isContestWorker  =   $this->balloonSender || $this->printManager;
        $this->assign('proctorAdmin',       $this->proctorAdmin);
        $this->assign('watcherUser',        $this->watcherUser);
        $this->assign('balloonManager',     $this->balloonManager);
        $this->assign('balloonSender',      $this->balloonSender);
        $this->assign('printManager',       $this->printManager);
        $this->assign('isReviewer',         $this->isReviewer);
        $this->assign('isContestStaff',     $this->isContestStaff);
        $this->assign('isContestWorker',    $this->isContestWorker);
    }
    public function GetSession($sessionStr=null) {
        if(!isset($this->teamSessionName) || $this->teamSessionName == '' || $this->teamSessionName == null)
            return null;
        if($sessionStr == '?') {
            return session('?' . $this->teamSessionName);
        }
        if($sessionStr === null) {
            $sessionStr = $this->teamSessionName;
        } else {
            $sessionStr = $this->teamSessionName . '.' . $sessionStr;
        }
        if(!session('?' . $sessionStr)) {
            return null;
        }
        return session($sessionStr);
    }
    public function CanJoin()
    {
        //是否有参加比赛权限，private设置的参赛权或public比赛无密码，输入正确的密码的public比赛也会获得参赛session
        if(!$this->GetSession('?')){
            $this->needAuth = true;
            return false;
        }
        return true;
    }
    public function IsContestAdmin($privilegeName=null)
    {
        $isAdmin = IsAdmin('contest', $this->contest['contest_id']);
        if($privilegeName === null) {
            return $isAdmin;
        }
        else {
            return $this->GetSession('privilege') === $privilegeName || $isAdmin;
        }
    }
    protected function contest_login_oper($teamInfo)
    {
        session($this->teamSessionName, [
            'team_id'   => $teamInfo['team_id'],
            'name'      => $teamInfo['name'],
            'name_en'   => $teamInfo['name_en'],
            'tmember'   => $teamInfo['tmember'],
            'coach'     => $teamInfo['coach'],
            'school'    => $teamInfo['school'],
            'region'    => $teamInfo['region'],
            'room'      => $teamInfo['room'],
            'privilege' => $teamInfo['privilege'],
        ]);
    }
    protected function contest_loginlog($team_id, $success)
    {
        $ip = GetRealIp();
        $time = date("Y-m-d H:i:s");
        db('loginlog')->insert(
            [
                'user_id'=>'#cpc' . $this->contest['contest_id'] . '_' . $team_id,
                //暂时用password字段作是否登录成功标记用。因为password计算依赖原密码salt，这里存储没有意义
                'password' => '',
                'ip' => $ip,
                'time'=> $time,
                'success'=> $success,
            ]);
    }
    
    /**
     * 根据用户权限获取登录后的重定向URL
     * @return string 重定向URL
     */
    protected function getContestLoginRedirectUrl()
    {
        $redirect_action = "problemset";
        if($this->IsContestAdmin('admin')) {
            $redirect_action = "ranklist";
        } else if($this->IsContestAdmin('balloon_manager')) {
            $redirect_action = "balloon_queue";
        } else if($this->IsContestAdmin('balloon_sender')) {
            $redirect_action = "balloon_queue";
        } else if($this->IsContestAdmin('printer')) {
            $redirect_action = "print_status";
        }
        return "/" . $this->module . "/contest/" . $redirect_action . "?cid=" . $this->contest['contest_id'];
    }
    public function contest_logout_ajax()
    {
        if(!$this->GetSession('?')){
            $this->error('User already logged out.');
        }
        session($this->teamSessionName, null);
        $this->success('Logout Contest ' . $this->contest['contest_id'] . ' Successful!<br/>Reloading data.');
    }
    public function contest_auth_ajax(){
        // 比赛账号（非OJ账号）登录验证
        if($this->GetSession('?')){
            $this->error('Already logged in. Try refreshing the page.');
        }
        $team_id = trim(input('team_id/s'));
        $password = trim(input('password/s'));
        if($team_id == null || strlen($team_id) == 0) {
            $this->error('Query Data Invalid!');
        }
        $Team = db('cpc_team');
        $map = array(
            'contest_id' => $this->contest['contest_id'],
            'team_id' => $team_id,
        );
        $teamInfo = $Team->where($map)->find();
        // 如果比赛已结束，则不允许选手账号再登录
        if($this->contestStatus == 2 && ($teamInfo == null || $teamInfo['privilege'] == null || strlen(trim($teamInfo['privilege'])) == 0)) {
            $this->error('比赛结束 / Ended!');
        }
        if($teamInfo == null) {
            $this->error('No such team');
        }
        if(CkPasswd($password, $teamInfo['password'], True))
        {
            $this->contest_login_oper($teamInfo);
            $data['team_id'] = $teamInfo['team_id'];
            $data['name'] = $teamInfo['name'];
            $this->contest_loginlog($teamInfo['team_id'], 1);
        }
        else
        {
            $this->contest_loginlog($teamInfo['team_id'], 0);
            $this->error('Password Error!');
        }
        $this->success('ok', null, ['redirect_url' => $this->getContestLoginRedirectUrl()]);
    }
    
    /**
     * 免密登录接口（通过IP查询team_id_bind）
     */
    public function contest_auth_passwordless_ajax() {
        // 比赛账号免密登录验证（通过IP）
        if($this->GetSession('?')){
            $this->error('Already logged in. Try refreshing the page.');
        }
        
        $ip = GetRealIp();
        if (empty($ip)) {
            $this->error('无法获取IP地址 / Unable to get IP address');
        }
        
        // 查询该IP对应的client
        $client = db('cpc_client')->where([
            'contest_id' => $this->contest['contest_id'],
            'ip_bind' => $ip
        ])->find();
        
        if (!$client || empty($client['team_id_bind'])) {
            $this->error('该IP未绑定队伍，请联系管理员 / This IP is not bound to a team, please contact administrator');
        }
        
        $team_id = $client['team_id_bind'];
        
        // 查询team是否存在
        $Team = db('cpc_team');
        $map = array(
            'contest_id' => $this->contest['contest_id'],
            'team_id' => $team_id,
        );
        $teamInfo = $Team->where($map)->find();
        
        if($teamInfo == null) {
            $this->error('队伍不存在，请联系管理员 / Team not found, please contact administrator');
        }
        
        // 如果比赛已结束，则不允许选手账号再登录
        if($this->contestStatus == 2 && ($teamInfo['privilege'] == null || strlen(trim($teamInfo['privilege'])) == 0)) {
            $this->error('比赛结束 / Ended!');
        }
        
        // 执行登录
        $this->contest_login_oper($teamInfo);
        $this->contest_loginlog($teamInfo['team_id'], 1);
        
        $this->success('ok', null, ['redirect_url' => $this->getContestLoginRedirectUrl()]);
    }
    
    /**
     * 收集账号接口
     */
    public function contest_collect_team_ajax() {
        // 判断是否是收集模式
        $contest = db('contest')->where('contest_id', input('cid/d'))->find();
        $addition = [];
        if (!empty($contest['addition'])) {
            $addition = Json2Array($contest['addition']);
            if (!$addition || !is_array($addition)) {
                $addition = [];
            }
        }
        
        $flg_collect_team_id = isset($addition['flg_collect_team_id']) ? intval($addition['flg_collect_team_id']) : 0;
        if ($flg_collect_team_id != 1) {
            $this->error('当前不是收集模式 / Not in collect mode');
        }
        
        // 判断是否在比赛前10分钟
        $now = time();
        $startTime = strtotime($contest['start_time']);
        $timeDiff = $startTime - $now;
        if ($timeDiff < 600) { // 600秒 = 10分钟
            $this->error('收集模式已自动关闭（比赛前10分钟） / Collect mode automatically disabled (10 minutes before contest)');
        }
        
        $team_id = trim(input('team_id/s'));
        if (empty($team_id)) {
            $this->error('队伍ID不能为空 / Team ID cannot be empty');
        }
        
        // 验证team_id合法性（参考teamgen的验证逻辑）
        $validate = new Validate(config('CpcSysConfig.teaminfo_rule'), config('CpcSysConfig.teaminfo_msg'));
        $teamData = [
            'team_id' => $team_id,
            'contest_id' => $contest['contest_id']
        ];
        
        if (!$validate->check($teamData)) {
            $this->error('队伍ID格式不正确：' . $validate->getError() . ' / Invalid Team ID format: ' . $validate->getError());
        }
        
        // 验证team_id是否存在
        $teamInfo = db('cpc_team')->where([
            'contest_id' => $contest['contest_id'],
            'team_id' => $team_id
        ])->find();
                
        $ip = GetRealIp();
        if (empty($ip)) {
            $this->error('无法获取IP地址 / Unable to get IP address');
        }
        
        // 查询该IP是否已有client记录
        $client = db('cpc_client')->where([
            'contest_id' => $contest['contest_id'],
            'ip_bind' => $ip
        ])->find();
        
        if ($client) {
            // 更新现有的client记录
            db('cpc_client')->where('client_id', $client['client_id'])->update([
                'team_id_bind' => $team_id
            ]);
        } else {
            // 新建client记录
            db('cpc_client')->insert([
                'contest_id' => $contest['contest_id'],
                'team_id_bind' => $team_id,
                'ip_bind' => $ip,
                'ssh_config' => null
            ]);
        }
        $rep_data = [
            'team_id' => $team_id,
            'ip' => $ip
        ];
        if (!$teamInfo) {
            $rep_data['team_warning_cn'] = '队伍 ' . $team_id . ' 尚未录入比赛，但仍然已完成绑定，如不是预期行为，请在客户端管理处处理';
            $rep_data['team_warning_en'] = 'Team ' . $team_id . ' has not been entered into the contest, but has already been bound. If this is not the expected behavior, please handle it in the client management';
        }
        $this->success('账号收集成功 / Account collected successfully', null, $rep_data);
    }
    
    /**
     * 比赛首页
     */
    public function contest() {
        // 获取客户端IP
        $clientIp = GetRealIp();
        $this->assign('client_ip', $clientIp);
        
        // 查询该IP对应的client的team_id_bind
        $team_id_bind = null;
        if (!empty($clientIp)) {
            $client = db('cpc_client')->where([
                'contest_id' => $this->contest['contest_id'],
                'ip_bind' => $clientIp
            ])->find();
            
            if ($client && !empty($client['team_id_bind'])) {
                $team_id_bind = $client['team_id_bind'];
            }
        }
        
        $this->assign('team_id_bind', $team_id_bind);
        
        return $this->fetch();
    }
    
    protected function SolutionUser($user_id, $appearprefix=null){
        // 针对 cpcsys 的 solution 用户名前缀处理
        if($appearprefix === null) {
            if($user_id != '' && $user_id[0] == '#') $user_id = substr(strrchr($user_id, "_"), 1);
            else $user_id = '#cpc' . $this->contest['contest_id'] . '_' . $user_id;
        }
        else if($appearprefix === true) {
            if($user_id != '' && $user_id[0] != '#') $user_id = '#cpc' . $this->contest['contest_id'] . '_' . $user_id;
        }
        else {
            if($user_id != '' && $user_id[0] == '#') $user_id = substr(strrchr($user_id, "_"), 1);
        }
        return $user_id;
    }
    public function RankUserList($map, $with_star=true)
    {
        $cmap = ['contest_id' => $this->contest['contest_id']];
        if(!$with_star) {
            $cmap['tkind'] = ['neq', 2];
        }
        return db('cpc_team')->where($cmap)
        ->field([
            'team_id user_id',
            'name nick',
            'school',
            'tmember',
            'tkind',
            'coach',
            'room'
        ])
        ->select();
    }
	public function UserInfoUrl($user_id, $contest_id=0, $prefix=false)
	{
        if($prefix)
            return '/' . $this->module . '/' . $this->controller . '/teaminfo?cid=' . $contest_id . '&team_id=';
        else
		    return '/' . $this->module . '/' . $this->controller . '/teaminfo?cid=' . $contest_id . '&team_id=' . $user_id;
	}
	public function teaminfo()
	{
        // 用户信息页
        $team_id = trim(input('team_id'));
        if($team_id == null || strlen($team_id) == 0)
            $team_id = $this->GetSession('team_id');
        if($team_id == null || strlen($team_id) == 0) {
            $this->error('You find a 404 ^_^');
        }
		$teaminfo = db('cpc_team')->where(['contest_id' => $this->contest['contest_id'], 'team_id' => $team_id])->find();
		if($teaminfo == null)
			$this->error('No such team.');
		$this->assign(['teaminfo' => $teaminfo]);
		return $this->fetch();
	}
    /**************************************************/
    //Printcode
    /**************************************************/
    public function PrintCodeAuth($watch_status=false)
    {
        if($this->contestStatus == -1 && !$this->IsContestAdmin('printer'))
            $this->error('Not started.');
        if($this->contestStatus == 2 && !$this->IsContestAdmin('printer') && !$watch_status)
            $this->error('Contest Ended.', null, '', 1);
        if(!$this->contest_user && !$this->IsContestAdmin('printer'))
            $this->error('Please login before print code', '/', '', 1);
    }
    public function print_code()
    {
        $this->PrintCodeAuth();
        $this->assign([
            'cid'         => $this->contest['contest_id'],
            'pagetitle' => 'Print Code',
            'team_id'    => $this->contest_user
        ]);
        return $this->fetch();
    }
    public function print_code_ajax()
    {
        $this->PrintCodeAuth();
        $source = trim(input('source'));
        $code_length = strlen($source);
        if($code_length < 6)
            $this->error('Code too short');
        else if($code_length > 16384)
            $this->error('Code too long');

        $teaminfo = db('cpc_team')->where(['team_id' => $this->contest_user, 'contest_id' => $this->contest['contest_id']])->field(['team_id', 'room'])->find();

        if(!$teaminfo)
            $this->error('No such team. Are you deleted by administrator?');
        db('contest_print')->insert([
            'contest_id'    => $this->contest['contest_id'],
            'team_id'       => $this->SolutionUser($teaminfo['team_id'], true),
            'source'        => $source,
            'in_date'       => date('Y-m-d H:i:s'),
            'ip'            => $this->request->ip(),
            'code_length'   => $code_length,
            'room'          => $teaminfo['room']
        ]);
        $this->success('Print request submitted', null, ['contest_id'=> $this->contest['contest_id'],'team_id'=> $this->contest_user, 'team_info' => $teaminfo]);
    }
    public function GetPrintCode($type='show')
    {
        $print_id = trim(input('print_id'));
        $printinfo = db('contest_print')->where('print_id', $print_id)->find();

        if(!$this->if_can_see_print($printinfo))
            $this->error('Permission denied to see this code.');

        if($type == 'show')
            $printinfo['source'] = htmlentities(str_replace("\r\n","\n",$printinfo['source']),ENT_QUOTES,"utf-8");
        $printinfo['auth'] =
            "\n/**********************************************************************".
            "\n\tContest: " . $this->contest['contest_id'] . '-' . $this->contest['title'].
            "\n\tTeam: " . $this->SolutionUser($printinfo['team_id'], false) . "\n".
            "**********************************************************************/\n";
        $printinfo['contest_title'] = $this->contest['title'];
        $printinfo['team_id'] = $this->SolutionUser($printinfo['team_id'], false);
        return $printinfo;
    }
    public function print_code_show_ajax()
    {
        //用于网页显示
        $printinfo = $this->GetPrintCode();
        $this->success('', null, $printinfo);
    }
    public function print_code_plain_content_ajax()
    {
        //用于打印
        $printinfo = $this->GetPrintCode('print');
        $this->success('', null, $printinfo);
    }
    public function if_can_see_print($printReq)
    {
        if(!isset($printReq['contest_id']) || $printReq['contest_id'] != $this->contest['contest_id'])
            return false;
        if($this->IsContestAdmin('printer'))
            return true;
        if(!$this->contest_user)
            return false;
        if($this->contest_user == $this->SolutionUser($printReq['team_id'], false))
            return true;
        return false;
    }

    public function GetPrintStatusShow($printReq)
    {
        $oj_print_status_html = config('CpcSysConfig.PRINT_STATUS_HTML');
        $ret = '';
        if(array_key_exists($printReq['print_status'], $oj_print_status_html))
        {
            $ret = $oj_print_status_html[$printReq['print_status']][1];
        }
        else
            $ret = 'Unknown';
        return $ret;
    }
    public function print_status() {
        $this->PrintCodeAuth(true);
        $this->assign([
            'cid'               => $this->contest['contest_id'],
            'pagetitle'         => 'Print Code Status',
            'search_team_id'    => input('team_id', ''),
            'team_id'           => $this->contest_user,
            'printStatus'       => config('CpcSysConfig.PRINT_STATUS'),
            'show_code_url'     => 'print_code_show_ajax',
            'room_ids'          => cookie('room_ids_c' . $this->contest['contest_id']),
        ]);
        return $this->fetch();
    }
    public function print_status_ajax()
    {
        $this->PrintCodeAuth(true);
        $offset     = intval(input('offset'));
        $limit      = intval(input('limit'));
        $sort       = trim(input('sort'));
        $order      = input('order');
        $search     = trim(input('search/s'));
        $room_ids   = trim(input('room_ids/s'));

        //为了打开页面时即过滤room_ids，目前得在server端设置cookie，因为前端幺蛾子多
        if($room_ids === '') {
            cookie('room_ids_c' . $this->contest['contest_id'], null);
        } else {
            cookie('room_ids_c' . $this->contest['contest_id'], $room_ids);
        }

        $team_id        = trim(input('team_id'));
        $print_status     = input('print_status');
        $map = [];

        if($team_id != null && strlen($team_id) > 0) {
            $map['team_id'] = $this->SolutionUser($team_id, true);
        }
        // else if(!$this->IsContestAdmin('printer'))
        //     $map['team_id'] = $this->SolutionUser($this->contest_user, true);
        if($print_status != null && $print_status != -1) {
            $map['print_status'] = $print_status;
        }
        if(strlen($room_ids) > 0)
        {
            $roomIdList = explode(",", $room_ids);
            foreach($roomIdList as &$room) {
                $room = trim($room);
            }
            $map['room'] = ['in', $roomIdList];
        }
        $map['contest_id'] = $this->contest['contest_id'];
        $ret = [];
        $ordertype = [];
        if (strlen($sort) > 0) {
            if($sort == 'print_status_show')
                $sort = 'print_status';
            $ordertype = [
                $sort => $order,
            ];
            //如果按其他排序，则print_id顺序排列，优先打印较早提交的。
            if($sort != 'print_id')
                $ordertype['print_id'] = 'asc';
        }
        $Print = db('contest_print');
        $printList = $Print
            ->where($map)
            ->order($ordertype)
            ->limit($offset, $limit)
            ->select();
        foreach($printList as &$printReq)
        {
            $printReq['team_id'] = $this->SolutionUser($printReq['team_id'], false);
            if($this->contest_user != $printReq['team_id'] && !$this->IsContestAdmin('admin') && !$this->IsContestAdmin('printer') && $this->contestStatus != 2)
            {
                //不是该用户，不是管理员，且比赛没结束，不可以查看别人的code length
                $printReq['code_length'] = '-';
            }

            // 只返回数据，不返回HTML
            $printReq['print_status_show'] = $this->GetPrintStatusShow($printReq);
            $printReq['user_info_url'] = $this->UserInfoUrl($printReq['team_id'], $this->contest['contest_id']);
            $printReq['flg_can_print'] = ($this->IsContestAdmin('admin') || $this->IsContestAdmin('printer')) ? 1 : 0;
            $printReq['flg_can_deny'] = (($this->IsContestAdmin('admin') || $this->IsContestAdmin('printer')) && $printReq['print_status'] == 0) ? 1 : 0;
            $printReq['flg_showcode'] = $this->if_can_see_print($printReq) ? 1 : 0;
        }
        $ret['total'] = $Print->where($map)->count();
        $ret['order'] = $order;
        $ret['rows'] = $printList;
        return $ret;
    }
    public function print_deny_ajax()
    {
        $this->PrintCodeAuth();
        if(!$this->IsContestAdmin('printer')) {
            $this->error("No permission to deny print task.");
        }
        $ContestPrint = db('contest_print');
        $print_id = trim(input('print_id'));
        $printinfo = $ContestPrint->where('print_id', $print_id)->find();
        if(!$printinfo)
            $this->error('No such print request, maybe you need refresh this page.');
        $printinfo['print_status'] = 2;
        if(strtotime($printinfo['in_date']) < 0)
            $printinfo['in_date'] = date('Y-m-d H:i:s');
        $ContestPrint->update($printinfo);
        $this->success('Print request ' . $print_id . ' is denied');
    }
    public function print_do_ajax()
    {
        $this->PrintCodeAuth(true);
        if(!$this->IsContestAdmin('printer')) {
            $this->error("No permission to do print task.");
        }
        $ContestPrint = db('contest_print');
        $print_id = trim(input('print_id'));
        $printinfo = $ContestPrint->where('print_id', $print_id)->find();
        if(!$printinfo)
            $this->error('No such print request, maybe you need refresh this page.');
        if(strtotime($printinfo['in_date']) < 0)
            $printinfo['in_date'] = date('Y-m-d H:i:s');

        $printinfo['print_status'] = 1;
        $ContestPrint->update($printinfo);
        $this->success('Print request ' . $print_id . ' is started');
    }

    /**************************************************/
    //Balloon
    /**************************************************/
    protected function BalloonAuth() {
        if(!$this->balloonManager && !$this->balloonSender && !$this->isContestAdmin) {
            $this->error('Permission denied to manage balloon', '/', '', 1);
        }
    }
    public function balloon_manager() {
        $this->BalloonAuth();
        return $this->fetch();
    }
    public function balloon_queue() {
        $this->BalloonAuth();
        // 获取当前用户信息（如果是balloonSender）
        if($this->balloonSender && $this->contest_user) {
            $teaminfo = db('cpc_team')->where(['contest_id' => $this->contest['contest_id'], 'team_id' => $this->contest_user])->find();
            $this->assign('teaminfo', $teaminfo ? $teaminfo : null);
        }
        return $this->fetch();
    }
    public function balloon_data_ajax() {
        $this->BalloonAuth();
        $contest_data = $this->GetContestData4Rank([
            'info_need' => null,    // 表示所有信息都需要
            'solution_result' => 4, // 只查询 AC 的题目
        ]);
        $this->success("ok", null, $contest_data);
    }
    public function balloon_change_status_ajax() {
        $this->BalloonAuth();
        // 将气球分配给 balloon sender
        $balloon_sender = trim(input('balloon_sender/s'));
        $contest_id = $this->contest['contest_id']; // 由 get 参数的 cid 提供，controller 会自动赋值
        $solution_id = trim(input('solution_id/d'));
        $balloon_sender = $this->SolutionUser($balloon_sender, false);
        $op = trim(input('op/s')); // set_sender / grab， 区分 管理员设置 和 配送员抢任务
        $pst = trim(input('pst/d'));
        $bst = trim(input('bst/d'));
        if($pst === null) {
            $this->error('首答状态不能为空\nFirst blood status cannot be empty.');
        }
        if(!in_array($pst, [0, 10, 20])) {
            $this->error('不存在的首答状态\nNo such first blood status.');
        }
        if($bst === null) {
            $this->error('气球状态不能为空\nBalloon status cannot be empty.');
        }
        if(!in_array($bst, [0, 10, 20, 30])) {
            $this->error('不存在的气球状态\nNo such balloon status.');
        }
        // 处理 solution 信息
        $solution = db('solution')->where(['solution_id' => $solution_id])->find();
        if(!$solution) {
            $this->error('不存在的提交\nNo such solution.');
        }
        if($solution['contest_id'] != $contest_id) {
            $this->error('提交不属于当前比赛\nSolution does not belong to current contest.');
        }
        if($solution['result'] != 4 && $bst !== 0) { // 没AC且行为不是退回
            $this->error('提交不是 AC\nSolution is not AC.');
        }
        $team_id = $this->SolutionUser($solution['user_id'], false);
        $problem_id = $solution['problem_id'];

        if($op == 'grab') {
            if(!$this->balloonSender && !$this->balloonManager) {
                $this->error('没有权限抢任务\nNo permission to grab task.');
            }
            $balloon_sender = $this->SolutionUser($this->contest_user, false);
            $bst = 20; // grab 模式只能设为"已分配"
        } else if($op == 'set_sender') {
            // set_sender 模式：只有管理员将状态设为20时才是set_sender
            if(!$this->IsContestAdmin('balloon_manager')) {
                $this->error('没有权限设置配送员\nNo permission to set sender.');
            }
            if($bst != 20) {
                $this->error('set_sender 模式只能将状态设为20（已分配）\nset_sender mode can only set status to 20 (Assigned).');
            }
            if(!$balloon_sender) {
                $this->error('配送员不能为空\nBalloon sender cannot be empty.');
            }
        }
        $new_contest_balloon = [
            'contest_id' => $contest_id,
            'problem_id' => $problem_id,
            'team_id' => $team_id,
            'room' => '',
            'ac_time' => strtotime($solution['in_date']),
            'pst' => $pst,
            'bst' => $bst,
        ];
        if($balloon_sender) {
            // 确认 balloon_sender 身份合法性
            $sender = db('cpc_team')->where([
                    'contest_id' => $contest_id, 
                    'team_id' => $balloon_sender, 
                    'privilege' => ['in', ['balloon_sender', 'balloon_manager']]])
                ->find();
            if(!$sender) {
                $this->error($balloon_sender . ' 不是气球配送员\n"' . $balloon_sender . '" is not a balloon sender.');
            }
            $new_contest_balloon['balloon_sender'] = $balloon_sender;
        }
        $contest_balloon = db('contest_balloon')->where(['contest_id' => $contest_id, 'problem_id' => $problem_id, 'team_id' => $team_id])->find();
        if($contest_balloon) {
            // 已存在的 balloon 记录
            if(!$this->IsContestAdmin('balloon_manager')) {
                // 非气球管理员情况
                if($contest_balloon['balloon_sender'] != $this->contest_user) {
                    $this->error('没有权限处理此气球.\nNo permission to handle this balloon.');
                }
                if($new_contest_balloon['bst'] == 10) {
                    $this->error('不能将气球状态设为“已通知”.\nCannot set balloon status to “Printed/Issued”.');
                }
            }
            db('contest_balloon')->where([
                    'contest_id' => $contest_balloon['contest_id'],
                    'problem_id' => $contest_balloon['problem_id'],
                    'team_id' => $contest_balloon['team_id']
                ])->update($new_contest_balloon);
        } else {
            db('contest_balloon')->insert($new_contest_balloon);
        }
        return $this->success('ok', null, $new_contest_balloon);
    }
    
}
