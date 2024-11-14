<?php
namespace app\cpcsys\controller;
use think\Db;
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
        $this->outsideContestAction = ['index', 'contest_list_ajax', 'contest_data_joint_ajax'];
        $this->allowPublicVisitAction = ['contest_login', 'ranklist', 'ranklist_ajax', 'scorerank', 'scorerank_ajax', 'schoolrank', 'schoolrank_ajax', 'contest', 'contest_auth_ajax', 'team_auth_type_ajax', 'contest_data_ajax'];
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
            $this->rankUseCache = !$this->IsContestAdmin() && !$this->IsContestAdmin('balloon_manager') && !$this->IsContestAdmin('balloon_sender') && !$this->IsContestAdmin('admin') && !$this->IsContestAdmin('watcher') ? 1 : 0;
            $this->isContestAdmin = $this->IsContestAdmin();
            $this->GetVars();
            $this->ContestAuthentication();
            $this->SetAssign();
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
            'tmember'   => $teamInfo['tmember'],
            'coach'     => $teamInfo['coach'],
            'school'    => $teamInfo['school'],
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
                'password' => $success,
                'ip' => $ip,
                'time'=> $time
            ]);
    }
    public function contest_logout_ajax()
    {
        if(!$this->GetSession('?')){
            $this->error('User already logged out.');
        }
        session($this->teamSessionName, null);
        $this->success('Logout Contest ' . $this->contest['contest_id'] . ' Successful!<br/>Reloading data.');
    }
    public function contest_auth_ajax()
    {
        // 比赛账号（非OJ账号）登录验证
        if($this->GetSession('?')){
            $this->error('Already logged in. Try refreshing the page.');
        }
        $team_id = trim(input('team_id/s'));
        $password = trim(input('password/s'));
        if($team_id == null || strlen($team_id) == 0)
            $this->error('Query Data Invalid!');
        $Team = db('cpc_team');
        $map = array(
            'contest_id' => $this->contest['contest_id'],
            'team_id' => $team_id,
        );
        $teamInfo = $Team->where($map)->find();
        // 如果比赛已结束，则不允许选手账号再登录
        if($this->contestStatus == 2 && ($teamInfo == null || $teamInfo['privilege'] == null || strlen(trim($teamInfo['privilege'])) == 0)) {
            $this->error('Ended!');
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
        $redirect_action = "problemset";
        if($this->IsContestAdmin()) {
            $redirect_action = "ranklist";
        } else if($this->IsContestAdmin('balloon_manager')) {
            $redirect_action = "balloon";
        } else if($this->IsContestAdmin('balloon_sender')) {
            $redirect_action = "balloon_queue";
        } else if($this->IsContestAdmin('printer')) {
            $redirect_action = "print_status";
        }
        $this->success('Verification passed', null, ['redirect_url' => "/" . $this->module . "/contest/" . $redirect_action . "?cid=" . $this->contest['contest_id']]);
    }
    public function SolutionUser($user_id, $appearprefix=null)
    {
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
            $printinfo['source'] = htmlentities(str_replace("\n\r","\n",$printinfo['source']),ENT_QUOTES,"utf-8");
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
            if($this->if_can_see_print($printReq))
                $ret = "<span showcode=1 print_id='" . $printReq['print_id'] . "' id='print_status_".$printReq['print_id']."' class='print-" . $oj_print_status_html[$printReq['print_status']][1] . " btn btn-" . $oj_print_status_html[$printReq['print_status']][0] . "'>" . $oj_print_status_html[$printReq['print_status']][1] . "</span>";
            else
                $ret = "<div showres=0 class='text-" . $oj_print_status_html[$printReq['print_status']][0] . "'>" . $oj_print_status_html[$printReq['print_status']][1] . "</div>";
        }
        else
            $ret = "<div showres=0 class='text-default'>Unknown</div>";
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

        if($team_id != null && strlen($team_id) > 0)
            $map['team_id'] = $this->SolutionUser($team_id, true);
        // else if(!$this->IsContestAdmin('printer'))
        //     $map['team_id'] = $this->SolutionUser($this->contest_user, true);
        if($print_status != null && $print_status != -1)
            $map['print_status'] = $print_status;
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
            if($this->contest_user != $printReq['team_id'] && !$this->IsContestAdmin() && !$this->IsContestAdmin('printer') && $this->contestStatus != 2)
            {
                //不是该用户，不是管理员，且比赛没结束，不可以查看别人的code length
                $printReq['code_length'] = '-';
            }


            $printReq['print_status_show'] = $this->GetPrintStatusShow($printReq);

            $printReq['team_id_show'] = "<a href='" . $this->UserInfoUrl($printReq['team_id'], $this->contest['contest_id']) . "'>" . $printReq['team_id'] . "</a>";
            $printReq['do_print'] = '-';
            $printReq['do_deny'] = '-';
            if($this->IsContestAdmin() || $this->IsContestAdmin('printer'))
            {
                $printReq['do_print'] = "<span id='do_print_".$printReq['print_id']."' class='btn btn-success do_print'>Print</span>";
                if($printReq['print_status'] == 0)
                    $printReq['do_deny'] = "<span id='do_deny_".$printReq['print_id']."' class='btn btn-danger do_deny'>Deny</span>";
                else
                    $printReq['do_deny'] = "-";
            }
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
    public function balloon() {
        $this->BalloonAuth();
        return $this->fetch();
    }
    public function balloon_task_ajax() {
        $this->BalloonAuth();
        $map = ['contest_id' => $this->contest['contest_id']];
        $FILTER_KEY_LIST = ['problem_id', 'room', 'balloon_sender'];
        foreach($FILTER_KEY_LIST as $key) {
            if(($item = input($key . '/s')) !== null && ($item = trim($item)) !== '') {
                $map[$key] = ['in', explode(',', $item)];
            }
        }
        $team_start = input('team_start/s');
        if($team_start !== null && ($team_start = trim($team_start)) !== '') {
            $map['team_id'] = ['egt', $team_start];
        }
        $team_end = input('team_end/s');
        if($team_end !== null && ($team_end = trim($team_end)) !== '') {
            $map['team_id'] = ['elt', $team_end];
        }
        $ContestBalloon = db('contest_balloon');
        $res = $ContestBalloon->where($map)->select();
        $this->success('ok', null, [
            'balloon_task_list'     => $res,
            'problem_id_map'        => $this->problemIdMap,
            'contest_problem_list'  => $this->contest_problem_list
        ]);
    }
    public function balloon_sender_list_ajax() {
        $this->BalloonAuth();
        if(!$this->balloonManager && !$this->balloonSender && !$this->proctorAdmin && !$this->isContestAdmin) {
            $this->error("No permission to view balloon senders");
        }
        return db('cpc_team')->where(['contest_id' => $this->contest['contest_id'], 'privilege' => ['in', ['balloon_sender', 'balloon_manager']]])->field('password', true)->select();
    }
    public function balloon_change_status_ajax() {
        $this->BalloonAuth();
        $team_id        = trim(input('team_id/s', ''));
        $apid           = trim(input('apid/s', ''));
        if($team_id == '' || $apid == '') {
            $this->error("需要提供队伍ID与字母题号", null, 'need_teamid_apid');
        }
        $task_new = ['bst'=> input('bst/d')];
        if($task_new['bst'] === null) {
            $this->error("需要提供参数：气球状态bst", null, 'need_bst');
        }
        if(!array_key_exists($apid, $this->problemIdMap['abc2id'])) {
            $this->error('没有这个题目', null, 'pro_not_exists');
        }
        $pid = $this->problemIdMap['abc2id'][$apid];
        $ContestBalloon = db('contest_balloon');
        $map = [
            'contest_id'    => $this->contest['contest_id'],
            'problem_id'    => $pid,
            'team_id'       => $team_id
        ];
        $item = $ContestBalloon->where($map)->find();
        if(!$item) {
            // 新增任务
            $task_new['pst']                = input('pst/d');
            $task_new['room']               = input('room/s');
            $task_new['ac_time']            = input('ac_time/d');
            if($task_new['bst'] == 4) {
                $task_new['balloon_sender']     = input('balloon_sender/s');
                if(!$task_new['balloon_sender'] || trim($task_new['balloon_sender']) == '') {
                    $this->error("需要设置气球配送员balloon_sender", null, 'need_balloon_sender');
                }
            }
            if($task_new['pst'] === null || $task_new['ac_time'] === null) {
                $this->error("需要提供参数：题目状态pst，ac时间 ac_time", null, 'need_pst_actime');
            }
            $ContestBalloon->insert(array_merge($map, $task_new));
        } else {
            // 更新任务
            if($item['balloon_sender'] != $this->contest_user && !$this->IsContestAdmin('balloon_manager')) {
                $this->error("没有权限更改此任务", null, 'no_privilege');
            }
            if(trim(input('new_query/s', '')) === '1') {
                $this->error("已分配的任务", null, 'no_privilege'); // 避免其它指令因前端信息不同步而覆盖
            }
            if($this->IsContestAdmin('balloon_manager')) {
                if(input('?pst')) {
                    $task_new['pst'] = input('pst/d');
                }
                if(input('?room')) {
                    $task_new['room'] = input('room/s');
                }
                if(input('?ac_time')) {
                    $task_new['ac_time'] = input('ac_time/d');
                }
                if(input('?balloon_sender')) {
                    $task_new['balloon_sender'] = input('balloon_sender/s');
                }
            }
            if($task_new['bst'] < 4) {
                $ContestBalloon->where($map)->delete();
            } else {
                $update_ret = $ContestBalloon->where($map)->update($task_new);
                if($update_ret === 0) {
                    return $this->error("0 updated", null, 'nothing_changed');
                }
            }
        }
        $this->success('ok', null, 'ok');
    }
    public function balloon_queue() {
        return $this->fetch();
    }
    public function balloon_team_list_ajax() {
        $this->BalloonAuth();
        return db('cpc_team')->where(['contest_id' => $this->contest['contest_id'], 'privilege' => ['exp', Db::raw('is null')]])->field(['team_id', 'room'])->select();
    }
    public function balloon_queue_get_ajax() {
        $this->BalloonAuth();
        
    }
}
