<?php
namespace app\ojtool\controller;
use think\Db;
class Rankroll extends Ojtoolbase {
    var $contest;
    public function InitController() {
        if(input('?cid') && !IsAdmin('contest', input('cid/d')) && !$this->IsContestAdmin('admin')) {
            $this->error("无访问权限", '/ojtool/rankroll', null, 1);
        }
        $this->contest = null;
        if(input('?cid')) {
            $cid = input('cid/d');
            $this->contest = db('contest')->where('contest_id', $cid)->find();
            if($this->contest == null || !in_array($this->contest['private'], [0, 1, 2, 10, 11, 12])) {
                $this->error("错误的比赛请求");
            }
        }
    }
    protected function IsContestAdmin($privilegeName=null){
        if(input('?cid') == null) {
            return false;
        }
        $cid = input('cid/d');
        if(IsAdmin('contest', $cid)) {
            return true;
        }
        if($privilegeName === null) {
            return false;
        }
        $sessionName = '#cpcteam' . $cid;
        $sessionStr = $sessionName . '.' . 'privilege';
        return session($sessionStr) === $privilegeName;
    }
    public function index() {
        $this->assign("pagetitle", "滚榜-比赛列表");
        return $this->fetch();
    }
    public function rankroll() {
        $this->assign('contest', $this->contest);
        $this->assign("pagetitle", "滚榜-" . $this->contest['title']);
        return $this->fetch();
    }
    public function contest_list_ajax() {
        $map = [];
        $map['private'] = ['in', [0, 1, 2, 10, 11, 12]];
        if(!IsAdmin()) {
            $map['defunct'] = '0';
        }
        $Contest = db('contest');
        $contestList = $Contest
            ->where($map)
            ->order(['contest_id' => 'desc'])
            ->select();
        return $contestList;
    }
    public function GetContestTeam($contest, $solution, $cid) {
        if(in_array($contest['private'], [2, 12])) {
            return db('cpc_team')->where(['contest_id' => $cid, 'privilege' => ['exp', Db::raw('is null')]])->field('password', true)->select();
        } else {
            $cuser = [];
            if($solution == null) {
                $solution = db('solution')->where(['contest_id' => $cid, 'result' => ['egt', 4]])->field(['solution_id', 'problem_id', 'user_id', 'in_date', 'result', 'contest_id'])->select();
            }
            foreach($solution as $sol) {
                $cuser[] = $sol['user_id'];
            }
            $cuser = array_unique($cuser);
            return db('users')->where('user_id', 'in', $cuser)->field(['user_id as team_id', 'school', 'nick as name', '0 as tkind'])->select();
        }
    }
    public function contest_data_ajax() {
        $witout_solution = input('without_solution/d');
        $only_solution = input('only_solution/d');
        $min_solution_id = input('min_solution_id/d');
        $sol_map = [
            'contest_id' => $this->contest['contest_id'], 
            'result' => ['egt', 4]
        ];
        if($min_solution_id != null) {
            $sol_map['solution_id'] = ['egt', $min_solution_id];
        }
        $solution = $witout_solution ? null : db('solution')->where($sol_map)->field(['solution_id', 'problem_id', 'user_id', 'in_date', 'result', 'contest_id'])->order('solution_id', 'asc')->select();
        if($only_solution) {
            $contest_data = [
                'solution'          => $solution
            ];
        } else {
            $contest_data = [
                'contest'           => $this->contest,
                'problem'           => db('contest_problem')->where('contest_id', $this->contest['contest_id'])->select(),
                'team'              => $this->GetContestTeam($this->contest, $solution, $this->contest['contest_id']),
                'solution'          => $solution
            ];
        }
        
        $this->success("ok", null, $contest_data);
    }
    public function team_image() {
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