<?php
namespace app\cpcsys\controller;
use think\Db;
use think\Validate;
use app\cpcsys\controller\Contest as Contestbase;
require_once(__DIR__ . "../../../traits.php");
use app\ContestAdminTrait as AT;
class Admin extends Contestbase
{
    use AT;
    
    public function teamgen_list_ajax() {
        $privilegeMap = ['privilege' => ['exp', Db::raw(input('ttype/d') ? 'is not null' : 'is null')]];
        $teamList = db('cpc_team')->where(['contest_id' => $this->contest['contest_id']])
        ->where(function($query){
            $query->whereNull('privilege')->whereOr('privilege', 'neq', 'reviewer');
        })
        ->where($privilegeMap)
        ->order('team_id', 'asc')->select();
        foreach($teamList as $key=>&$val) {
            $val['password'] = RecoverPasswd($val['password']);
        }
        return $teamList;
    }
    
    /**
     * 获取气球配送员列表（staff，privilege为balloon_sender）
     */
    public function team_list_ajax() {
        // 检查权限（需要balloonManager或isContestAdmin）
        if (!$this->balloonManager && !$this->isContestAdmin) {
            $this->error('Permission denied');
        }
        
        $ttype = input('ttype/d', 0);
        $cid = input('cid/d');
        
        if (!$cid) {
            $this->error('Missing contest ID');
        }
        
        // 如果ttype=1，筛选privilege为balloon_sender的staff
        if ($ttype == 1) {
            $teamList = db('cpc_team')->where([
                'contest_id' => $cid,
                'privilege' => 'balloon_sender'
            ])->field(['team_id', 'name', 'room'])->order('team_id', 'asc')->select();
        } else {
            $teamList = [];
        }
        
        $this->success('', null, ['team_list' => $teamList]);
    }
    public function team_modify() {
        return $this->fetch();
    }
    public function teaminfo_ajax() {
        $team_id = input('team_id');
        $teamInfo = db('cpc_team')->where(['team_id' => $team_id, 'contest_id' => $this->contest['contest_id']])->find();
        if(!$teamInfo) {
            $this->error("No such team.");
        }
        $teamInfo['password'] = '';
        $this->success('', null, ['teaminfo' => $teamInfo]);
    }
    public function team_modify_ajax() {
        
        $team_id = input('team_id');
        $teamInfo = db('cpc_team')->where(['team_id' => $team_id, 'contest_id' => $this->contest['contest_id']])->find();
        if(!$teamInfo) {
            $this->error("No such team.");
        }
        if($teamInfo['privilege'] == 'admin' && !IsAdmin('contest', $this->contest['contest_id'])) {
            $this->error("Information of administrator could not be modified.");
        }
        $teamUpdate = [
            'name'      => input('name'),
            'name_en'   => input('name_en'),
            'tmember'   => input('tmember'),
            'coach'     => input('coach'),
            'school'    => input('school'),
            'region'    => input('region'),
            'password'  => input('password'),
            'room'      => input('room'),
            'tkind'     => intval(input('tkind')),
        ];
        if(strlen($teamUpdate['name']) > $this->TINFO_NAME_MAX) {
            $this->error("team name too long");
        }
        if(strlen($teamUpdate['name_en']) > $this->TINFO_NAME_EN_MAX) {
            $this->error("team name_en too long");
        }
        if(trim($teamUpdate['password']) == '') {
            unset($teamUpdate['password']);
        }
        else {
            $teamUpdate['password'] = MkPasswd($teamUpdate['password'], True);
        }
        if($teamUpdate['tkind'] > 2 || $teamUpdate['tkind'] < 0) {
            $teamUpdate['tkind'] = 0;
        }
        $teamInfo = array_replace($teamInfo, $teamUpdate);
        db('cpc_team')->where(['team_id' => $team_id, 'contest_id' => $this->contest['contest_id']])->update($teamInfo);
        $teamInfo['password'] = RecoverPasswd($teamInfo['password']);
        $this->success("ok", null, $teamInfo);
    }
    
    /**************************************************/
    // IPCheck
    /**************************************************/

    public function IpCheckAuth()
    {
        if(!$this->isContestAdmin && !$this->proctorAdmin)
            $this->error('Permission denied to see ipcheck', '/', '', 1);
    }
    public function ipcheck()
    {
        $this->IpCheckAuth();
        $this->assign('ipcheck');
        return $this->fetch();
    }
    public function ipcheck_ajax()
    {
        $this->IpCheckAuth();
        $lgCheckStart = date("Y-m-d H:i:s", strtotime("-1 hour", strtotime($this->contest['start_time'])));
        $lgCheckEnd = date("Y-m-d H:i:s", strtotime("+10 minute", strtotime($this->contest['end_time'])));
        $cid = $this->contest['contest_id'];
        $uidPrefix = '#cpc' . $cid . '_';
        $contestUserLog = db('loginlog')->alias('lg')
            ->join('cpc_team', 'CONCAT("' . $uidPrefix . '",cpc_team.team_id) = lg.user_id')
            ->where([
                'cpc_team.contest_id' => $this->contest['contest_id'],
                'lg.time' => ['between', [$lgCheckStart, $lgCheckEnd]]
            ])
            ->group('lg.user_id, lg.ip, cpc_team.name')
            ->field([
                'lg.user_id team_id',
                'lg.ip ip',
                'Max(lg.time) time',
                'cpc_team.name name',
            ])
            ->order('time', 'asc')
            ->select();
        $userIps = [];
        $ipUsers = [];
        foreach($contestUserLog as $userLog)
        {
            $userLog['team_id'] = $this->SolutionUser($userLog['team_id'], false);
            if(!array_key_exists($userLog['team_id'], $userIps))
                $userIps[$userLog['team_id']] = [
                    'name' => $userLog['name'],
                    'ips' => []
                ];
            $userIps[$userLog['team_id']]['ips'][] = [
                'ip' => $userLog['ip'],
                'time' => $userLog['time']
            ];
            if(!array_key_exists($userLog['ip'], $ipUsers))
                $ipUsers[$userLog['ip']] = [];
            $ipUsers[$userLog['ip']][] = [
                'team_id' => $userLog['team_id'],
                'name' => $userLog['name'],
                'time' => $userLog['time']
            ];
        }
        foreach($userIps as $k=>$v) {
            if(count($v['ips']) <= 1)
                unset($userIps[$k]);
        }
        foreach($ipUsers as $k=>$v) {
            if(count($v) <= 1)
                unset($ipUsers[$k]);
        }
        $this->success("Successful", null, ['userIps' => $userIps, 'ipUsers'=>$ipUsers]);
    }
    
    // **************************************************
    // Contest Team Generator
    public function contest_teamgen() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied to gen teams', '/', '', 1);
        }
        if(!in_array($this->contest['private'] % 10, [2, 5])) {
            // 2是standard，5是exam
            $this->error("This contest could not generate teams.");
        }
        if(!IsAdmin('contest', $this->contest['contest_id'])) {
            $this->error("You are not system administrator.");
        }
        if($this->contestStatus == 2 && !IsAdmin('super_admin')) {
            $this->error("You'd better not modify teams after contest ended.");
        }
        $this->assign('action', 'contest_teamgen');
        return $this->fetch();
    }
    public function contest_staffgen() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied to gen staffs', '/', '', 1);
        }
        if(!in_array($this->contest['private'] % 10, [2, 5])) {
            // 2是standard，5是exam
            $this->error("This contest could not generate staffs.");
        }
        if(!IsAdmin('contest', $this->contest['contest_id'])) {
            $this->error("You are not system administrator.");
        }
        if($this->contestStatus == 2 && !IsAdmin('super_admin')) {
            $this->error("You'd better not modify staffs after contest ended.");
        }
        return $this->fetch('contest_teamgen');
    }
    
    public function contest_teamgen_ajax() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied to gen teams', '/', '', 1);
        }
        if($this->contest['private'] % 10 != 2) {
            $this->error("This contest could not generate teams.");
        }
        if(!IsAdmin('contest', $this->contest['contest_id'])) {
            $this->error("You are not system administrator.");
        }
        if($this->contestStatus == 2 && !IsAdmin('super_admin')) {
            $this->error("You'd better not modify teams after contest ended.");
        }
        
        // 获取POST数据
        $teamList = input('team_list');
        $reset_team = input('reset_team', false);
        $password_seed = input('password_seed', 0);
        
        if (!$teamList) {
            $this->error('No team data provided');
        }
        
        // 解析JSON数据
        $teams = json_decode($teamList, true);
        if (!$teams || !is_array($teams)) {
            $this->error('Invalid team data format');
        }
        if($reset_team) {
            $this->ClearTeam();
        }
        
        $CpcTeam = db('cpc_team');
        $teamPrefix = "team";
        
        if(count($teams) == 0) {
            $this->error('No teams to generate');
        }
        if(count($teams) > 5000) {
            $this->error('Too many teams');
        }
        
        $teamToInsert = [];
        $teamToShow = [];
        $validate = new Validate(config('CpcSysConfig.teaminfo_rule'), config('CpcSysConfig.teaminfo_msg'));
        $validateNotList = '';
        
        // 检查team_id重复
        $teamIds = [];
        foreach($teams as $teamData) {
            $teamId = $teamData['team_id'] ?? '';
            if($teamId != '') {
                if(in_array($teamId, $teamIds)) {
                    $this->error("Duplicate team_id found: " . $teamId);
                }
                $teamIds[] = $teamId;
            }
        }

        $i = $CpcTeam->where(['team_id' => ['like', 'team%'], 'contest_id' => $this->contest['contest_id']])->count() + 1;
        
        foreach($teams as $teamData) {
            $nowTeam = [];
            
            // 处理team_id（必须提供，不允许为空）
            $teamId = $teamData['team_id'] ?? '';
            if($teamId == '') {
                $this->error('Team ID is required for all teams');
            }
            $nowTeam['team_id'] = $teamId;
            
            // 处理其他字段
            $nowTeam['name'] = $teamData['name'] ?? '';
            $nowTeam['name_en'] = $teamData['name_en'] ?? '';
            $nowTeam['school'] = $teamData['school'] ?? '';
            $nowTeam['region'] = $teamData['region'] ?? '';
            $nowTeam['tmember'] = $teamData['tmember'] ?? '';
            $nowTeam['coach'] = $teamData['coach'] ?? '';
            $nowTeam['room'] = $teamData['room'] ?? '';
            $nowTeam['tkind'] = intval($teamData['tkind'] ?? 0);
            if($nowTeam['tkind'] > 2 || $nowTeam['tkind'] < 0) {
                $nowTeam['tkind'] = 0;
            }
            
            // 处理密码
            $password = $teamData['password'] ?? '';
            if($password == '') {
                $password = $this->generateSeededPassword($teamId, $password_seed);
            }
            $nowTeam['password'] = $password;
            
            $nowTeam['contest_id'] = $this->contest['contest_id'];
            $nowTeam['privilege'] = null;
            
            // 验证数据
            if(!$validate->check($nowTeam)) {
                $validateNotList .= "<br/>" . $nowTeam['team_id'] . ': ' . $validate->getError();
            }
            
            if(strlen($validateNotList) == 0) {
                $teamToShow[] = $nowTeam;
                $nowTeam['password'] = MkPasswd($nowTeam['password'], true);
                $teamToInsert[] = $nowTeam;
            }
            $i++;
        }
        
        if(strlen($validateNotList) > 0) {
            $addInfo = '<br/>Some team information is not valid. Please check.' . $validateNotList;
            $this->error('Team generation failed.' . $addInfo);
        }
        
        $success_num = $CpcTeam->insertAll($teamToInsert, true);
        if(!$success_num) {
            $this->error('Team generation failed. Please check the data input.');
        }
        $this->success('Team successfully generated. <br/>See the table below', null, ['rows' => $teamToShow, 'type' => 'teamgen', 'success_num'=> $success_num]);
    }
    
    // 基于种子的确定性密码生成
    private function generateSeededPassword($teamId, $seed) {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        
        // 如果没有种子，使用随机种子
        if ($seed == 0) {
            $seed = mt_rand(1, 999999);
        }
        
        // 使用种子和team_id生成确定性随机数
        $combinedSeed = $seed + strlen($teamId) + ord($teamId[0] ?? 'A');
        for ($i = 0; $i < strlen($teamId); $i++) {
            $combinedSeed = ($combinedSeed * 31 + ord($teamId[$i])) % 2147483647;
        }
        
        // 简单的线性同余生成器
        for ($i = 0; $i < 8; $i++) {
            $combinedSeed = ($combinedSeed * 16807) % 2147483647;
            $random = $combinedSeed / 2147483647;
            $password .= $chars[floor($random * strlen($chars))];
        }
        
        return $password;
    }
    public function ClearTeam($helperAccounts=false) {
        $CpcTeam = db('cpc_team');
        $map = [
            'contest_id' => $this->contest['contest_id']
        ];
        $CpcTeam->where($map)->where(function($query) {
            $query->whereNull('privilege')->whereOr('privilege', '');
        })->delete();
        // 当字段为 null 时，=和<>都会返回null
        if($helperAccounts) {
            $CpcTeam->where($map)->where('privilege', 'exp', Db::raw('is not null'))->where('privilege', 'neq', 'reviewer')->delete();
        }
    }
    public function TeamDel($teamStr)
    {
        if(!isset($teamStr) || strlen($teamStr) == 0)
            $this->error("No team prefix set");
        $Users = db('users');
        $Solution = db('solution');
        $teamList = $Users->where(['user_id' => ['like', $teamStr . '%']])->select();
        $teamToShow = [];
        foreach($teamList as $team)
        {
            if(!$Solution->where('user_id', $team['user_id'])->find())
                $Users->where('user_id', $team['user_id'])->delete();
            else {
                $team['password'] = "__UNKNOWN__";
                $teamToShow[] = $team;
            }
        }
        $this->success('Team names [' . $teamStr . '%] without submissions deleted.', null, ['rows' => $teamToShow, 'type' => 'teamdel']);
    }
    public function team_del_ajax() {
        if(!$this->isContestAdmin) {
            $this->error("No privilege");
        }
        $team_id = input('team_id/s');
        db('cpc_team')->where([
                'team_id'       => $team_id,
                'contest_id'    => $this->contest['contest_id']
            ])->delete();
        $this->success("deleted");
    }
    // **************************************************
    // 比赛滚榜用队伍照片管理
    
    public function rank_team_image() {
        $this->assign('contest', $this->contest);
        return $this->fetch();
    }
    public function team_image_list_ajax() {
        $ojPath = config('ojPath');
        $team_photo_path = $ojPath['PUBLIC'] . $ojPath['contest_ATTACH'] . '/' . $this->contest['attach'] . '/team_photo';
        if(!MakeDirs($team_photo_path)) {
			$this->error('队伍图片列表读取失败.');
        }
        return $this->success('ok', null, GetDir($team_photo_path));
    }
    public function team_image_upload_ajax() { 
        if(!IsAdmin('contest', $this->contest['contest_id']) && !$this->IsContestAdmin('admin')) {
            $this->error("仅管理员有权上传", '/ojtool', null, 1);
        }
        $team_id = input('team_id');
        $team = null;
        if(in_array($this->contest['private'], [2, 12])) {
            $team = db('cpc_team')->where(['contest_id' => $this->contest['contest_id'], 'team_id' => $team_id])->find();
        } else {
            $team = db('users')->join('solution', 'users.user_id=solution.user_id')->where(['solution.contest_id' => $this->contest['contest_id'], 'users.user_id' => $team_id])->field('users.user_id team_id')->find();
        }
        if($team == null) {
            $this->error("没有这个队伍");
        }
        
        $dataURL = input("team_photo/s");
        if($dataURL) {
            $ojPath = config('ojPath');
            $filename = $team['team_id'] . '.jpg';
            $file_url = $ojPath['contest_ATTACH']. '/' . $this->contest['attach'] . '/team_photo/' . $filename;
            $file_folder = $ojPath['PUBLIC'] . $ojPath['contest_ATTACH'] . '/' . $this->contest['attach'] . '/team_photo/';
            MakeDirs($file_folder);
            
            list($type, $data) = explode(';', $dataURL);
            list(, $type) = explode(':', $type);
            list(, $data) = explode(',', $data);
            $data = base64_decode($data);
            if(strlen($data) > 524288) {
                $this->error("图片过大");
            }
            file_put_contents($file_folder . '/' . $filename, $data);
            $this->success('OK', null, [
                'file_url' => $file_url
            ]);
        }
        $this->error('未获取到文件');;
    }
    public function team_image_del_ajax() {
        if(!IsAdmin('contest', $this->contest['contest_id']) && !$this->IsContestAdmin('admin')) {
            $this->error("仅管理员有权删除", '/ojtool', null, 1);
        }
        $ojPath = config('ojPath');
        $team_id = input('team_id/s');
        if($team_id === null || trim($team_id) === '' || ($team_id = preg_replace('/[^A-Za-z0-9_]/', '', $team_id)) === '') {
            $this->error("team_id not valid");
        }
        $file_path = $ojPath['PUBLIC'] . $ojPath['contest_ATTACH'] . '/' . $this->contest['attach'] . '/team_photo/' . $team_id . '.jpg';
        DelWhatever($file_path);
        $this->success('ok');
    }
}