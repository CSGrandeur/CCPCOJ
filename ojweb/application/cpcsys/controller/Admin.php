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
        
        // 获取区分参数：staff=1 或 ttype=1 表示工作人员生成，否则为队伍生成
        $isStaff = input('staff', 0) == 1 || input('ttype', 0) == 1;
        
        if (!$teamList) {
            $this->error('No team data provided');
        }
        
        // 解析JSON数据
        $teams = json_decode($teamList, true);
        if (!$teams || !is_array($teams)) {
            $this->error('Invalid team data format');
        }
        
        // 根据区分参数决定清空哪种类型的账号
        if($reset_team === 1 || $reset_team === true || $reset_team === 'on' || $reset_team === 'true') {
            // isStaff=true 时清空工作人员账号（privilege不为null），否则清空普通队伍（privilege为null）
            $this->ClearTeam($isStaff);
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
            // 处理privilege字段（工作人员权限）
            $nowTeam['privilege'] = $teamData['privilege'] ?? null;
            
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
    
    /**************************************************/
    // Client Management
    /**************************************************/
    
    public function client_manage() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied to manage clients', '/', '', 1);
        }
        if(!in_array($this->contest['private'] % 10, [2, 5])) {
            $this->error("This contest does not support client management.");
        }
        
        // 计算收集模式状态
        $addition = [];
        if (!empty($this->contest['addition'])) {
            $addition = Json2Array($this->contest['addition']);
            if (!$addition || !is_array($addition)) {
                $addition = [];
            }
        }
        $flg_collect_team_id = isset($addition['flg_collect_team_id']) ? intval($addition['flg_collect_team_id']) : 0;
        
        // 判断收集模式是否已无效化（比赛前10分钟内）
        $isCollectModeInvalid = false;
        if ($flg_collect_team_id == 1) {
            $now = time();
            $startTime = strtotime($this->contest['start_time']);
            $timeDiff = $startTime - $now;
            $isCollectModeInvalid = $timeDiff < 600; // 600秒 = 10分钟
        }
        
        $this->assign('flg_collect_team_id', $flg_collect_team_id);
        $this->assign('is_collect_mode_invalid', $isCollectModeInvalid);
        
        return $this->fetch();
    }
    
    /**
     * 读取 Python 记录文件
     * @param int $contest_id 比赛ID
     * @return array 客户端记录数据
     */
    private function readClientRecord($contest_id) {
        $ojPath = config('OjPath');
        $recordPath = $ojPath['cpc_client_record'] . '/' . $contest_id . '/record.json';
        
        if (!file_exists($recordPath)) {
            return [];
        }
        
        $content = file_get_contents($recordPath);
        if ($content === false) {
            return [];
        }
        
        $data = Json2Array($content);
        return $data ? $data : [];
    }
    
    /**
     * 获取比赛客户端列表
     */
    public function contest_client_list_ajax() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied');
        }
        
        $clientList = db('cpc_client')
            ->where('contest_id', $this->contest['contest_id'])
            ->order('client_id', 'asc')
            ->select();
        
        // 读取 Python 记录文件
        $recordData = $this->readClientRecord($this->contest['contest_id']);
        
        // 将记录数据按 ip_bind 索引
        // record.json 格式：对象 {ip_bind: {last_connect_time: '...', connect_status: '...', lock_status: '...', lock_time: '...'}, ...}
        $recordMap = [];
        if (is_array($recordData)) {
            foreach ($recordData as $ipBind => $record) {
                if (is_string($ipBind) && is_array($record)) {
                    // 对象格式：key 是 ip_bind
                    $recordMap[$ipBind] = $record;
                } elseif (is_array($record) && isset($record['ip_bind'])) {
                    // 数组格式：每个元素包含 ip_bind 字段
                    $recordMap[$record['ip_bind']] = $record;
                }
            }
        }
        
        // 合并数据
        foreach ($clientList as &$client) {
            // 解析 ssh_config
            $sshConfig = [];
            if (!empty($client['ssh_config'])) {
                $sshConfig = Json2Array($client['ssh_config']);
                if (!$sshConfig) {
                    $sshConfig = [];
                }
            }
            
            // 设置 SSH 字段
            $client['ssh_user'] = $sshConfig['user'] ?? '';
            $client['ssh_pass'] = $sshConfig['pass'] ?? '';
            $client['ssh_rsa'] = $sshConfig['rsa'] ?? '';
            $client['ssh_port'] = $sshConfig['port'] ?? '22';
            
            // 合并记录数据
            if (isset($client['ip_bind']) && isset($recordMap[$client['ip_bind']])) {
                $record = $recordMap[$client['ip_bind']];
                $client['last_connect_time'] = $record['last_connect_time'] ?? null;
                $client['connect_status'] = $record['connect_status'] ?? 'unknown';
                $client['lock_status'] = $record['lock_status'] ?? 'unlock';
                $client['lock_time'] = $record['lock_time'] ?? null;
            } else {
                $client['last_connect_time'] = null;
                $client['connect_status'] = 'unknown';
                $client['lock_status'] = 'unlock';
                $client['lock_time'] = null;
            }
        }
        
        return $clientList;
    }
    
    /**
     * 验证IP地址格式（简单验证）
     */
    private function isValidIpAddress($ip) {
        if (empty($ip) || !is_string($ip)) {
            return false;
        }
        // 简单的IP地址格式验证（IPv4）
        return preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $ip) === 1;
    }
    
    /**
     * 验证 SSH 配置
     * SSH相关信息允许全为空
     * 仅当提供了SSH账号（用户名）的时候，才验证密码、密钥方面是否存在
     */
    private function validateSshConfig($sshUser, $sshPass, $sshRsa, $sshPort) {
        // 如果SSH用户名为空，则通过验证（允许SSH信息全为空）
        if (empty($sshUser)) {
            return ['valid' => true];
        }
        
        // ssh_user 不应该是IP地址格式（防止字段混淆）
        if ($this->isValidIpAddress($sshUser)) {
            return ['valid' => false, 'error' => 'SSH user cannot be an IP address format, please check if the column order is correct / SSH用户名不能是IP地址格式，请检查数据列顺序是否正确'];
        }
        
        // 如果提供了SSH用户名，则需要验证port必填
        if (empty($sshPort)) {
            return ['valid' => false, 'error' => 'SSH port is required when SSH user is provided / 提供SSH用户名时，SSH端口为必填项'];
        }
        
        // 如果提供了SSH用户名，pass 和 rsa 至少需要有一个
        if (empty($sshPass) && empty($sshRsa)) {
            return ['valid' => false, 'error' => 'SSH password or RSA key is required when SSH user is provided / 提供SSH用户名时，SSH密码或RSA密钥至少需要提供一个'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 保存或更新客户端
     */
    public function contest_client_save_ajax() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied');
        }
        
        $clientList = input('client_list');
        if (!$clientList) {
            $this->error('No client data provided');
        }
        
        $clients = json_decode($clientList, true);
        if (!$clients || !is_array($clients)) {
            $this->error('Invalid client data format');
        }
        
        $CpcClient = db('cpc_client');
        
        // 一次性查询该 contest_id 的所有 client
        $existingClients = $CpcClient->where('contest_id', $this->contest['contest_id'])->select();
        
        // 创建基于 team_id_bind 的 map（忽略大小写，key 使用小写）
        $teamIdMap = [];
        foreach ($existingClients as $client) {
            $teamIdBind = trim($client['team_id_bind'] ?? '');
            if (!empty($teamIdBind)) {
                $teamIdKey = strtolower($teamIdBind);
                $teamIdMap[$teamIdKey] = $client;
            }
        }
        
        // 创建基于 ip_bind 的 map
        $ipMap = [];
        foreach ($existingClients as $client) {
            $ipBind = trim($client['ip_bind'] ?? '');
            if (!empty($ipBind)) {
                $ipMap[$ipBind] = $client;
            }
        }
        
        $successCount = 0;
        $errorList = [];
        $insertList = [];
        $updateList = [];
        
        foreach ($clients as $idx => $clientData) {
            // 处理 SSH 配置
            $sshUser = trim($clientData['ssh_user'] ?? '');
            $sshPass = trim($clientData['ssh_pass'] ?? '');
            $sshRsa = trim($clientData['ssh_rsa'] ?? '');
            $sshPort = trim($clientData['ssh_port'] ?? '22');
            
            // 验证 SSH 配置
            $sshValidation = $this->validateSshConfig($sshUser, $sshPass, $sshRsa, $sshPort);
            if (!$sshValidation['valid']) {
                $errorList[] = "Row " . ($idx + 1) . ": " . $sshValidation['error'];
                continue;
            }
            
            // 构建 SSH 配置 JSON
            // 仅当提供了SSH用户名时才构建SSH配置
            $sshConfig = [];
            if (!empty($sshUser)) {
                $sshConfig['user'] = $sshUser;
                $sshConfig['port'] = $sshPort ?: '22';
                if (!empty($sshPass)) {
                    $sshConfig['pass'] = $sshPass;
                }
                if (!empty($sshRsa)) {
                    $sshConfig['rsa'] = $sshRsa;
                }
            }
            
            // 获取数据
            $teamIdBind = trim($clientData['team_id_bind'] ?? '');
            $ipBind = trim($clientData['ip_bind'] ?? '');
            
            // team_id_bind 不应该是IP地址格式（防止字段混淆）
            if (!empty($teamIdBind) && $this->isValidIpAddress($teamIdBind)) {
                $errorList[] = "Row " . ($idx + 1) . ": team_id_bind cannot be an IP address format, please check if the column order is correct / 队伍号不能是IP地址格式，请检查数据列顺序是否正确";
                continue;
            }
            
            // 判断是更新还是插入
            $existingClient = null;
            $isUpdate = false;
            
            // 情况1：如果ip不为空且数据库已存在这个contest_id下的这个ip，则做更新操作
            if (!empty($ipBind) && isset($ipMap[$ipBind])) {
                $existingClient = $ipMap[$ipBind];
                $isUpdate = true;
            }
            
            // 情况2：如果ip为空且队伍号不为空，且数据库存在这个contest_id下的这个队伍账号，则也做更新操作
            if (!$isUpdate && empty($ipBind) && !empty($teamIdBind)) {
                $teamIdKey = strtolower($teamIdBind);
                if (isset($teamIdMap[$teamIdKey])) {
                    $existingClient = $teamIdMap[$teamIdKey];
                    $isUpdate = true;
                }
            }
            
            if ($isUpdate) {
                // 准备更新数据
                $updateData = [
                    'client_id' => $existingClient['client_id'],
                    'contest_id' => $this->contest['contest_id']
                ];
                
                if (!empty($teamIdBind)) {
                    $updateData['team_id_bind'] = $teamIdBind;
                } else {
                    // 保持原有值
                    $updateData['team_id_bind'] = $existingClient['team_id_bind'] ?? '';
                }
                
                if (!empty($ipBind)) {
                    $updateData['ip_bind'] = $ipBind;
                } else {
                    // 保持原有值
                    $updateData['ip_bind'] = $existingClient['ip_bind'] ?? '';
                }
                
                // 如果SSH用户名为空，则清空SSH配置；如果提供了SSH用户名，则更新SSH配置
                if (isset($clientData['ssh_user'])) {
                    $updateData['ssh_config'] = !empty($sshConfig) ? json_encode($sshConfig, JSON_UNESCAPED_UNICODE) : null;
                } else {
                    // 保持原有值
                    $updateData['ssh_config'] = $existingClient['ssh_config'] ?? null;
                }
                
                $updateList[] = $updateData;
            } else {
                // 插入操作：验证必填字段
                if (empty($teamIdBind)) {
                    $errorList[] = "Row " . ($idx + 1) . ": team_id_bind is required for new client / 队伍号不能为空";
                    continue;
                }
                
                if (empty($ipBind)) {
                    $errorList[] = "Row " . ($idx + 1) . ": ip_bind is required for new client / IP地址不能为空";
                    continue;
                }
                
                $clientInfo = [
                    'contest_id' => $this->contest['contest_id'],
                    'team_id_bind' => $teamIdBind,
                    'ip_bind' => $ipBind,
                    'ssh_config' => !empty($sshConfig) ? json_encode($sshConfig, JSON_UNESCAPED_UNICODE) : null
                ];
                
                $insertList[] = $clientInfo;
            }
        }
        
        // 批量插入新数据
        if (!empty($insertList)) {
            $insertResult = $CpcClient->insertAll($insertList, true);
            if ($insertResult !== false) {
                $successCount += count($insertList);
            } else {
                $errorList[] = "Batch insert failed / 批量插入失败";
            }
        }
        
        // 批量更新现有数据（使用 REPLACE INTO）
        if (!empty($updateList)) {
            $updateResult = $CpcClient->insertAll($updateList, true);
            if ($updateResult !== false) {
                $successCount += count($updateList);
            } else {
                $errorList[] = "Batch update failed / 批量更新失败";
            }
        }
        
        if (!empty($errorList)) {
            $this->error('Some clients failed to save: ' . implode('; ', $errorList));
        }
        
        $this->success('Clients saved successfully', null, ['success_count' => $successCount]);
    }
    
    /**
     * 删除客户端
     */
    public function contest_client_del_ajax() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied');
        }
        
        $clientId = input('client_id/d');
        if (!$clientId) {
            $this->error('client_id is required');
        }
        
        $result = db('cpc_client')->where([
            'client_id' => $clientId,
            'contest_id' => $this->contest['contest_id']
        ])->delete();
        
        if ($result) {
            $this->success('Client deleted successfully');
        } else {
            $this->error('Failed to delete client');
        }
    }
    
    // /**
    //  * SSH 操作接口
    //  * @param string $action 操作类型: check_connect, lock, unlock
    //  */
    // public function contest_client_ssh_ajax() {
    //     if(!$this->isContestAdmin) {
    //         $this->error('Permission denied');
    //     }
        
    //     $action = input('action/s');
    //     $clientIds = input('client_ids/a');
    //     $ipBinds = input('ip_binds/a');
        
    //     if (empty($action)) {
    //         $this->error('Action is required');
    //     }
        
    //     if (!in_array($action, ['check_connect', 'lock', 'unlock'])) {
    //         $this->error('Invalid action');
    //     }
        
    //     // 如果提供了 client_ids，则查询对应的 ip_bind
    //     if (!empty($clientIds) && empty($ipBinds)) {
    //         $clients = db('cpc_client')
    //             ->where('client_id', 'in', $clientIds)
    //             ->where('contest_id', $this->contest['contest_id'])
    //             ->field('ip_bind')
    //             ->select();
            
    //         $ipBinds = [];
    //         foreach ($clients as $client) {
    //             if (!empty($client['ip_bind'])) {
    //                 $ipBinds[] = $client['ip_bind'];
    //             }
    //         }
    //     }
        
    //     if (empty($ipBinds)) {
    //         $this->error('No IP addresses to process');
    //     }
        
    //     // TODO: 调用 Python 脚本执行 SSH 操作
    //     // 这里先返回成功，后续实现 Python 调用
    //     // 预期格式：
    //     // - check_connect: 检查连接状态，返回连接时间和状态
    //     // - lock: 锁屏操作
    //     // - unlock: 解锁操作
        
    //     $this->success('SSH operation submitted', null, [
    //         'action' => $action,
    //         'ip_count' => count($ipBinds),
    //         'ip_binds' => $ipBinds
    //     ]);
    // }
    
    /**
     * 更新 contest 表的 addition JSON 字段
     * @param array $updateFields 要更新的字段数组，如 ['flg_collect_team_id' => 1]
     * @param bool $flg_update 是否只更新提供的字段（true）还是直接覆盖整个JSON（false），默认true
     * @return bool 更新是否成功
     */
    protected function updateContestAddition($updateFields, $flg_update = true) {
        $contest = db('contest')->where('contest_id', $this->contest['contest_id'])->find();
        if (!$contest) {
            $this->error('Contest not found');
        }
        
        // 获取现有的 addition 数据
        $addition = [];
        if (!empty($contest['addition'])) {
            $addition = Json2Array($contest['addition']);
            if (!$addition || !is_array($addition)) {
                $addition = [];
            }
        }
        
        if ($flg_update) {
            // 只更新提供的字段
            $addition = array_merge($addition, $updateFields);
        } else {
            // 直接覆盖
            $addition = $updateFields;
        }
        
        // 更新数据库
        $result = db('contest')->where('contest_id', $this->contest['contest_id'])->update([
            'addition' => json_encode($addition, JSON_UNESCAPED_UNICODE)
        ]);
        
        return $result !== false;
    }
    
    /**
     * 切换账号收集模式状态
     */
    public function contest_collect_mode_toggle_ajax() {
        if(!$this->isContestAdmin) {
            $this->error('Permission denied');
        }
        
        // 获取当前状态
        $addition = [];
        if (!empty($this->contest['addition'])) {
            $addition = Json2Array($this->contest['addition']);
            if (!$addition || !is_array($addition)) {
                $addition = [];
            }
        }
        
        $currentStatus = isset($addition['flg_collect_team_id']) ? intval($addition['flg_collect_team_id']) : 0;
        $newStatus = $currentStatus == 1 ? 0 : 1;
        
        // 更新状态
        if ($this->updateContestAddition(['flg_collect_team_id' => $newStatus], true)) {
            $statusText = $newStatus == 1 ? '已开启 / Enabled' : '已关闭 / Disabled';
            $this->success('账号收集模式' . $statusText, null, [
                'flg_collect_team_id' => $newStatus,
                'status_str' => $statusText
            ]);
        } else {
            $this->error('更新失败 / Update failed');
        }
    }
}