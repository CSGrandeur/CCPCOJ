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
            'tmember'   => input('tmember'),
            'coach'     => input('coach'),
            'school'    => input('school'),
            'password'  => input('password'),
            'room'      => input('room'),
            'tkind'     => intval(input('tkind')),
        ];
        if(strlen($teamUpdate['name']) > 90) {
            $this->error("team name too long");
        }
        if(trim($teamUpdate['password']) == '') 
            unset($teamUpdate['password']);
        else
            $teamUpdate['password'] = MkPasswd($teamUpdate['password'], True);
        if($teamUpdate['tkind'] > 2 || $teamUpdate['tkind'] < 0) {
            $teamUpdate['tkind'] = 0;
        }
        $teamInfo = array_replace($teamInfo, $teamUpdate);
        db('cpc_team')->where(['team_id' => $team_id, 'contest_id' => $this->contest['contest_id']])->update($teamInfo);
        $this->success("<a href='/$this->module/contest/teaminfo?cid=" . $this->contest['contest_id'] . "&team_id=$team_id'>$team_id</a> Updated");
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
        return $this->fetch();
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
        $flagAddStaff = input('staff/d') === 1;
        $reset_team = input('reset_team', false) == 'on';
        if($reset_team) {
            $this->ClearTeam();
        }
        $CpcTeam = db('cpc_team');
        $teamPrefix = "team";   // 如果提供了个性化team编号（带字母），则不再带这个前缀
        $teamDescription = trim(input('team_description'), "\n\r");
        if(strlen(trim($teamDescription)) == 0) {
            $teamList = [];
        }
        else {
            $teamList = explode("\n", $teamDescription);
        }
        if(count($teamList) == 1) {
            $line = preg_split("/[#\\t ]/", $teamList[0]);
            if(count($line) <= 2) {
                $gen_num = null;
                $gen_seed = null;
                if(is_numeric($line[0])) {
                    $gen_num = min(intval($line[0]), 4096);
                }
                if(count($line) == 2 && is_numeric($line[1])) {
                    $gen_seed = intval($line[1]);
                    srand($gen_seed);
                }
                if($gen_num != null) {
                    // 只输入了1~2个数字，则生成$gen_num个空队伍
                    $pad_num = strlen(strval($gen_num + 20));
                    $teamToShow = [];
                    $teamToInsert = [];
                    for($i = 0; $i < $gen_num; $i ++) {
                        $nowTeam = [
                            'team_id'   => $teamPrefix . str_pad($i + 1, $pad_num, '0', STR_PAD_LEFT),
                            'password'  => RandPass(),
                            'contest_id'=> $this->contest['contest_id']
                        ];
                        $teamToShow[] = $nowTeam;
                        $nowTeam['password'] = MkPasswd($nowTeam['password'], true);
                        $teamToInsert[] = $nowTeam;
                    }
                    $success_num = $CpcTeam->insertAll($teamToInsert, true);
                    if(!$success_num) {
                        $this->error('Team generation failed. Please check the data input.');
                    }
                    $this->success('Team successfully generated. <br/>See the table below', null, ['rows' => $teamToShow, 'type' => 'teamgen', 'success_num'=> $success_num]);
                }
            }
        }
        $teamListLen = count($teamList);    //teamDescription的行数
        if($teamListLen == 0)
            $this->error('At least fill in one form of Team description or Team number');
        if($teamListLen > 5000)
            $this->error('Too many teams');
        $teamToInsert = [];
        $teamToShow = [];
        // email是member，emailSuf是coach
        $fieldList = ['team_id', 'name', 'school', 'tmember', 'coach', 'room', 'tkind', 'password', 'contest_id', 'privilege'];
        $fieldNum = count($fieldList);
        $validate = new Validate(config('CpcSysConfig.teaminfo_rule'), config('CpcSysConfig.teaminfo_msg'));
        $validateNotList = '';

        $i = $CpcTeam->where(['team_id' => ['like', 'team%'], 'contest_id' => $this->contest['contest_id']])->count() + 1;
        
        foreach($teamList as $teamStr) {
            $teamInput = preg_split("/[#\\t]/", $teamStr);
            $nowTeam = [];
            for($j = 0; $j < $fieldNum; $j ++) {
                if(!array_key_exists($j, $teamInput)) {
                    $teamInput[$j] = '';
                }
                // $field = trim($teamInput[$j]);
                $field = mb_convert_encoding(trim($teamInput[$j]), 'UTF-8', 'UTF-8');
                switch($fieldList[$j])
                {
                    case 'team_id':
                        $tmpTeamPrefix = $teamPrefix;
                        if($field == '') {
                            $field = str_pad($i, 4, '0', STR_PAD_LEFT);
                        } else if(!$flagAddStaff) {
                            if(strlen($field) > 16) {
                                $validateNotList .= "<br/>[$teamStr] team_id too long.";
                            } else if(!is_numeric($field)) {
                                $tmpTeamPrefix = '';
                            }
                        }
                        else if(!$flagAddStaff && !is_numeric($field) || strlen($field) > 16) {
                            $validateNotList .= "<br/>[$teamStr] team_id not valid.";
                        }
                        $field = $flagAddStaff ? $field : ($teamPrefix . $field);
                        $field = $flagAddStaff ? $field : ($tmpTeamPrefix . $field);
                        $nowTeam['team_id'] = $field;
                        break;
                    case 'name':
                        if(strlen($field) > 90) {
                            $validateNotList .= "<br/>[$teamStr] team_name too long.";
                        }
                        $nowTeam['name'] = $field;
                        break;
                    case 'password':
                        if($field == '')
                            $field = RandPass();
                        $nowTeam['password'] = $field;
                        break;
                    case 'tkind':
                        $nowTeam['tkind'] = intval($field);
                        if($nowTeam['tkind'] > 2 || $nowTeam['tkind'] < 0) {
                            $nowTeam['tkind'] = 0;
                        }
                        break;
                    case 'contest_id':
                        $nowTeam['contest_id'] = $this->contest['contest_id'];
                        break;
                    case 'privilege':
                        $nowTeam['privilege'] = in_array($field, ['admin', 'printer', 'balloon_manager', 'balloon_sender']) ? $field : null;
                        if($nowTeam['privilege'] != null && strpos($nowTeam['team_id'], 'team') === 0) {
                            $validateNotList .= "<br/>[$teamStr] special account should not start with \"team\".";
                        }
                        break;
                    default:
                        $nowTeam[$fieldList[$j]] = $field;
                }
            }
            if(!$validate->check($nowTeam)) {
                $validateNotList .= "<br/>" . $nowTeam['team_id'] . ': ' . $validate->getError();
            }
            if(strlen($validateNotList) == 0) {
                $teamToShow[] = $nowTeam;
                $nowTeam['password'] = MkPasswd($nowTeam['password'], True);
                $teamToInsert[] = $nowTeam;
            }
            $i ++;
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
    // public function contest_teamgenbyseatdraw_ajax() {
    //     if($this->contest['private'] % 10 != 2) {
    //         $this->error("该比赛不支持生成队伍.");
    //     }
    //     if(!IsAdmin('contest', $this->contest['contest_id'])) {
    //         $this->error("没有该比赛管理权限.");
    //     }
    //     $team_list = input('team_list/a');
  
    //     $team_id_num_len = input('team_id_num_len/d');
    //     $team_already = db('cpc_team')->where('contest_id', $this->contest['contest_id'])->select();
    //     $team_already_map = [];
    //     if($team_already != null) {
    //         foreach($team_already as &$val) {
    //             $team_already_map[intval(str_replace("team", "", $val['team_id']))] = $val;
    //         }
    //     }
    //     $team_list_insert = [];
    //     function Strcut($team, $key, $len) {
    //         return mb_convert_encoding(array_key_exists($key, $team) ? substr($team[$key], 0, $len) : '', 'UTF-8', 'UTF-8');
    //     }
    //     function TeamTkind($team) {
    //         $tkind = array_key_exists('tkind', $team) ? intval($team['tkind']) : 0;
    //         if($tkind < 0) $tkind = 0;
    //         if($tkind > 3) $tkind = 3;
    //         return $tkind;
    //     }
    //     foreach($team_list as &$team) {
    //         if(array_key_exists('team_num_id', $team)) {
    //             $team_num_id = intval($team['team_num_id']);
    //             if($team_num_id > 5000) {
    //                 $this->error("队伍编号过大");
    //             }
    //             $team_list_insert[] = [
    //                 'team_id'       =>  str_pad($team['team_num_id'], $team_id_num_len, '0', STR_PAD_LEFT),
    //                 'name'          =>  Strcut($team, 'name', 49),
    //                 'school'        =>  Strcut($team, 'school', 49),
    //                 'tmember'       =>  Strcut($team, 'tmember', 63),
    //                 'coach'         =>  Strcut($team, 'coach', 23),
    //                 'room'          =>  Strcut($team, 'room', 49),
    //                 'tkind'         =>  TeamTkind($team),
    //                 'password'      =>  array_key_exists($team_num_id, $team_already_map) ? $team_already_map[$team_num_id]['password'] : MkPasswd(RandPass(), True),
    //                 'contest_id'    =>  $this->contest['contest_id'],
    //                 'privilege'     => ''
    //             ];
    //         }
    //     }
    //     $CpcTeam = db('cpc_team');
    //     $CpcTeam->where(['contest_id' => $this->contest['contest_id'], 'team_id' => ['like', 'team%']])->delete();
    //     $num = $CpcTeam->insertAll($team_list_insert);
    //     $this->success("ok", null, ['success_num'=>$num]);

    // }
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
}