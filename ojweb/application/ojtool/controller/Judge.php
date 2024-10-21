<?php
namespace app\ojtool\controller;
class Judge extends Ojtoolbase {
    var $judge_user;
    var $utf_regex;
    var $auto_rejudge_sec = 900;
    public function _initialize() {
        $this->OJMode();
        $this->JudgeInit();
    }
    protected function Response($data, $is_file=False) {
        if($is_file) {

        } else {
            print_r($data);
            exit();
        }
    }
    protected function JudgeInit() {
        if($this->action == 'judge_login') {
            return;
        }
        if(!session('?user_id') || !session('?judger')) {
            $this->Response("0");
        }
        $this->judge_user = session('user_id');
        $this->utf_regex = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u'; // 用于替换judge信息中的乱码字符
    }
    protected function judge_manual() {
        // 用于“人工”修改判题结果
        $sid = input('sid/d');
        $result = input('result/d');
        $explain = input('explain/s');
        db('solution')->where('solution_id', $sid)->update(['result'=> $result]);
        if($explain != null) {
            $runtimeinfo = db('runtimeinfo')->where('solution_id', $sid)->find();
            if($runtimeinfo == null) {
                db('runtimeinfo')->insert(['solution_id' => $sid, 'error'=> $explain]);
            } else {
                $runtimeinfo['error'] = $explain;
                db('runtimeinfo')->update($runtimeinfo);
            }
        }
        $this->Response($sid);
    }
    protected function judge_update_solution() {
        // 更新判题结果
        $sid = input('sid/d');
        $result = input('result/d');
        $time = input('time/d');
        $memory = input('memory/d');
        $sim = input('sim/d');
        $simid = input('simid/d');
        $pass_rate = input('pass_rate/f');
        db('solution')->where('solution_id', $sid)->update([
            'result'    => $result,
            'time'      => $time,
            'memory'    => $memory,
            'judgetime' => date("Y-m-d H:i:s"),
            'pass_rate' => $pass_rate,
            'judger'    => $this->judge_user,
        ]);
        if($sim != null) {
            $sim_item = db('sim')->where('s_id', $sid)->find();
            if($sim_item == null) {
                db('sim')->insert(['s_id' => $sid, 'sim_s_id' => $simid, 'sim' => $sim]);
            } else {
                $sim_item['sim_s_id'] = $simid;
                $sim_item['sim'] = $sim;
                db('sim')->update($sim_item);
            }
        }
        $this->Response('update_solution ok');
    }
    protected function judge_checkout() {
        // 检出solution，标记为正在判题. 过滤条件：尚未检出或已检出但 $auto_rejudge_sec 秒未判题的题目
        $sid = input('sid/d');
        $result = input('result/d');
        $ret = db('solution')->where('solution_id', $sid)->where(function($query){
            $query->where('result', '<', 2)
                ->whereOr(function($query2) {
                    $query2->where('result', '<', 4)->whereTime('judgetime', '<', time() - $this->auto_rejudge_sec);
                });
        })->update([
            'result'    => $result,
            'time'      => 0,
            'memory'    => 0,
            'judgetime' => date("Y-m-d H:i:s")
        ]);
        // UPDATE `solution`  SET `result`=4,`time`=0,`memory`=0,`judgetime`='2023-05-23 17:04:12'  WHERE  `solution_id` = 1  AND (  `result` < 2 OR (  `result` < 4  AND `judgetime` <  '2023-05-23 17:03:12' ) );
        $this->Response($ret > 0 ? 1 : 0);
    }
    protected function judge_getpending() {
        // 获取pending的题目列表
        $max_running = input('max_running/d');
        $oj_lang_set = input('oj_lang_set/s');
        $lang_list = explode(',', $oj_lang_set);
        $ret = db('solution')->where('language', 'in', $lang_list)
            ->where(function ($query) {
                $query->where('result', '<', 2)
                    ->whereOr(function ($query) {
                        $query->where('result', '<', 4)->whereTime('judgetime', '<', time() - $this->auto_rejudge_sec);
                    });
            })
            ->order(['result' => 'asc', 'solution_id' => 'asc'])
            ->limit($max_running)
            ->column('solution_id');
        // echo Db::getLastsql();
        // SELECT `solution_id` FROM `solution` WHERE `language` IN (0,1,2,3,4,5,6,7,8,9,10,11,17) AND ( `result` < 2 OR ( `result` < 4 AND `judgetime` < '2023-05-23 19:38:48' ) ) ORDER BY `result` ASC,`solution_id` ASC LIMIT 3
        $this->Response(implode("\n", $ret) . "\n");
    }
    protected function judge_getsolutioninfo() {
        // 获取单条solution信息
        $sid = input('sid/d');
        $ret = db('solution')->where('solution_id', $sid)
            ->field('problem_id, user_id, language, contest_id')
            ->find();
        if($ret != null) {
            $this->Response(implode("\n", array_map("strval", array_values($ret))) . "\n");
        }
    }
    protected function judge_getsolution() {
        // 获取提交的代码
        $sid = input('sid/d');
        $ret = db('source_code')->where('solution_id', $sid)
            ->field('source')
            ->find();
        if($ret != null) {
            $this->Response($ret['source'] . "\n");
        }
    }
    protected function judge_getcustominput() {
        // 没啥用，只是对接judge端，或许会删除
        $sid = input('sid/d');
        $ret = db('custominput')->where('solution_id', $sid)
            ->field('input_text')
            ->find();
        if($ret != null) {
            $this->Response($ret['input_text'] . "\n");
        }
    }
    protected function judge_getprobleminfo() {
        // 获取题目的时限内存等信息
        $pid = input('pid/d');
        $ret = db('problem')->where('problem_id', $pid)
            ->field('time_limit, memory_limit, spj')
            ->find();
        if($ret != null) {
            $this->Response(implode("\n", array_map("strval", array_values($ret))) . "\n");
        }
    }
    protected function judge_addceinfo() {
        // 插入/更新CE信息
        $sid = input('sid/d');
        $ceinfo = input('ceinfo/s');
        if ($ceinfo !== null) {
            try {
                $replacement = "?";
                $ceinfo = preg_replace($this->utf_regex, $replacement, $ceinfo);
            } catch (\Exception $e) {
                $ceinfo = "Unrecognized characters exist";
            }
        }
        $item = db('compileinfo')->where('solution_id', $sid)->find();
        if($item == null) {
            db('compileinfo')->insert(['solution_id' => $sid, 'error' => $ceinfo]);
        } else {
            $item['error'] = $ceinfo;
            db('compileinfo')->update($item);
        }
        $this->Response("addceinfo ok\n");
    }
    protected function judge_addreinfo() {
        // 插入/更新RE信息
        $sid = input('sid/d');
        $reinfo = input('reinfo/s');
        if ($reinfo !== null) {
            try {
                $replacement = "?";
                $reinfo = preg_replace($this->utf_regex, $replacement, $reinfo);
            } catch (\Exception $e) {
                $reinfo = "Unrecognized characters exist";
            }
        }
        $item = db('runtimeinfo')->where('solution_id', $sid)->find();
        if($item == null) {
            db('runtimeinfo')->insert(['solution_id' => $sid, 'error' => $reinfo]);
        } else {
            $item['error'] = $reinfo;
            db('runtimeinfo')->update($item);
        }
        $this->Response("addreinfo ok\n");
    }
    protected function judge_updateuser() {
        // 更新用户的提交量与刷题量
        // 该数据没必要频繁更新，已在OJ web中低频完成，此处不执行该逻辑
        $this->Response("will update user in web");
    }
    protected function judge_updateproblem() {
        // 本接口区分contest和非contest状态对题目的ac/submit数更新
        // 由于web端对contest每道题ac数做了查询，此处没有必要为contest题目增加统计值
        $cid = input('cid/d');
        $pid = input('pid/d');
        if($cid != null && $cid > 0) {
            $this->Response("No need in contest");
        }
        $accepted = db('solution')->where('problem_id', $pid)->where('result', 4)
            ->where(function ($query) {
                $query->whereNull('contest_id')
                ->whereOr('contest_id', 0);
            })->count();
        $submit = db('solution')->where('problem_id', $pid)
            ->where(function ($query) {
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->count();
        $ret = db('problem')->where('problem_id', $pid)->update(['accepted' => $accepted, 'submit' => $submit]);
        $this->Response($ret);
    }
    protected function judge_checklogin() {
        $this->Response(1);
    }
    protected function judge_gettestdatalist() {
        $pid = input('pid/d');
        $time = input('time/d');
		$path_judge_data_p = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $pid;
        if(!is_dir($path_judge_data_p)) {
            $this->Response('');
        }
        $ret = [];
        if ($handle = opendir($path_judge_data_p)) {
            while (($file = readdir($handle)) !== false) {
                if ($file!="." && $file !=".." && $file != "ac") {
                    if($time != null) {
                        $ret[] = filemtime($path_judge_data_p . DIRECTORY_SEPARATOR . $file);
                    }
                    $ret[] = $file;
                }
            }
            closedir ( $handle );
        }
        $this->Response(implode("\n", array_map("strval", array_values($ret))) . "\n");
    }
    protected function judge_gettestdata() {
        $filename = input('filename/s');
		$path_judge_data = config('OjPath.testdata');
        $pathinfo = pathinfo(realpath($path_judge_data . DIRECTORY_SEPARATOR . $filename));

        downloads($pathinfo['dirname'], $pathinfo['basename']);
        exit();
    }
    public function judge_login() {
        if(session('?user_id')) {
            $this->Response('Success');
        }
        $user_id = trim(input('user_id'));
        $password = trim(input('password'));
        if($user_id == null || strlen($user_id) == 0) {
            $this->Response('Query Data Invalid!');
        }
        $User = db('users');
        $map = array(
            'user_id' => $user_id
        );
        $userinfo = $User->where($map)->find();
        if($userinfo == null) {
            $this->Response('No such user');
        }
        if(!CkPasswd($password, $userinfo['password'])) {
            $this->Response('Password Error!');
        }
        $this->login_oper($userinfo);
        $this->Response('Success');
    }
    
    protected function login_oper($userinfo) {
        // 设置登录后的session
        session('user_id', $userinfo['user_id']);
        // judger权限
        $Privilege = db('privilege');
        $privilege = $Privilege->where([
            'user_id'   => $userinfo['user_id'],
            'rightstr'  => 'judger'
        ])->field('rightstr')->find();
        if($privilege == null) {
            $this->Response('Not judger');
        }
        session($privilege['rightstr'], true);
    }
    
    public function judge() {
        if(input('?manual')) {
            $this->judge_manual();
        } 
        else if(input('?update_solution')) {
            $this->judge_update_solution();
        }
        else if(input('?checkout')) {
            $this->judge_checkout();
        }
        else if(input('?getpending')) {
            $this->judge_getpending();
        }
        else if(input('?getsolutioninfo')) {
            $this->judge_getsolutioninfo();
        }
        else if(input('?getsolution')) {
            $this->judge_getsolution();
        }
        else if(input('?getcustominput')) {
            $this->judge_getcustominput();
        }
        else if(input('?getprobleminfo')) {
            $this->judge_getprobleminfo();
        }
        else if(input('?addceinfo')) {
            $this->judge_addceinfo();
        }
        else if(input('?addreinfo')) {
            $this->judge_addreinfo();
        }
        else if(input('?updateuser')) {
            $this->judge_updateuser();
        }
        else if(input('?updateproblem')) {
            $this->judge_updateproblem();
        }
        else if(input('?checklogin')) {
            $this->judge_checklogin();
        }
        else if(input('?gettestdatalist')) {
            $this->judge_gettestdatalist();
        }
        else if(input('?gettestdata')) {
            $this->judge_gettestdata();
        }
    }
}
