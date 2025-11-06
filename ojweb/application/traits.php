<?php
namespace app;
use think\Validate;
use think\Db;
trait ContestAdminTrait {
    var $TINFO_NAME_MAX = 100;
    var $TINFO_NAME_EN_MAX = 120;
    public function _initialize()
    {
        $this->OJMode();
        $this->ContestInit();
        $this->AdminInit();
    }
    public function AdminInit() {
        if(!$this->IsContestAdmin() && !$this->IsContestAdmin('admin')) {
            $this->error("You are not admin.", '/' . $this->module . '/contest/contest?cid=' . $this->contest['contest_id'], '', 1);
        }
    }
    public function index()
    {
        $this->redirect("/$this->module/$this->controller/contest_edit?cid=" . input('get.cid'));
    }
    public function rank_roll() {
        return $this->fetch();
    }
    // **************************************************
    // Contest Edit
    public function contest_edit() {
        $contest = $this->contest;
        $contest_md = db('contest_md')->where('contest_id', $contest['contest_id'])->find();
        if($contest_md != null) {
            $contest = array_replace($this->contest, $contest_md);
        }
        $start = strtotime($contest['start_time']);
        $end = strtotime($contest['end_time']);
        $award_ratio = $contest['award_ratio'];
        $ratio_gold = $award_ratio % 1000; $award_ratio /= 1000;
        $ratio_silver = $award_ratio % 1000; $award_ratio /= 1000;
        $ratio_bronze = $award_ratio % 1000; $award_ratio /= 1000;
        
        $this->assign([
            'start_year'    => date('Y', $start),
            'start_month'   => date('m', $start),
            'start_day'     => date('d', $start),
            'start_hour'    => date('H', $start),
            'start_minute'  => date('i', $start),
            'end_year'      => date('Y', $end),
            'end_month'     => date('m', $end),
            'end_day'       => date('d', $end),
            'end_hour'      => date('H', $end),
            'end_minute'    => date('i', $end),
            'topteam'       => $contest['topteam'],
            'ratio_gold'    => $ratio_gold,
            'ratio_silver'  => $ratio_silver,
            'ratio_bronze'  => $ratio_bronze,
            'frozen_minute' => $contest['frozen_minute'],
            'frozen_after'  => $contest['frozen_after'],
            'contest'       => $contest
        ]);
        return $this->fetch();
    }
    public function CalLangMask($languages)
    {
        $ret = LangList2LangMask($languages);
        if($ret == -1) $this->error('Please select at least 1 language.');
        if($ret == -2) $this->error('Some languages are not allowed for this OJ.');
        return $ret;
    }
	public function contest_addedit_ajax_process()
	{
        $postData = input('post.');
        $contest_info = [
            'start_time'    =>
                trim($postData['start_year']).'-'.
                trim($postData['start_month']).'-'.
                trim($postData['start_day']).' '.
                trim($postData['start_hour']).':'.
                trim($postData['start_minute']).':'.
                '0',
            'end_time'    =>
                trim($postData['end_year']).'-'.
                trim($postData['end_month']).'-'.
                trim($postData['end_day']).' '.
                trim($postData['end_hour']).':'.
                trim($postData['end_minute']).':'.
                '0',
            'langmask'    => $this->CalLangMask(isset($postData['language']) ? $postData['language'] : []), 
            'description' => trim($postData['description']),
            'frozen_minute' => intval($postData['frozen_minute']),
            'frozen_after' => intval($postData['frozen_after'])
        ];
        if($contest_info['frozen_minute'] > 2592000 || $contest_info['frozen_after'] > 2592000)
            $this->error('Frozen time too long.');
        $contest_md_info = [
            'description' => $contest_info['description'],
        ];
        //插入contest表，描述字段为md编译的html
        $contest_info['description']    = ParseMarkdown($contest_md_info['description']);
        if(!strtotime($contest_info['start_time']) || !strtotime($contest_info['end_time']))
            $this->error('Time syntax error.');
        $starttime = strtotime($contest_info['start_time']);
        $endtime = strtotime($contest_info['end_time']);
        if($starttime >= $endtime) {
            $this->error('Start Time should before End Time.');
        }
        $contest_info['topteam'] = intval($postData['topteam']);
        if($contest_info['topteam'] > 20) {
            $contest_info['topteam'] = 20;
        }
        if($contest_info['topteam'] < 1){
            $contest_info['topteam'] = 1;
        }
		$ratio_gold = intval($postData['ratio_gold']);
		$ratio_silver = intval($postData['ratio_silver']);
		$ratio_bronze = intval($postData['ratio_bronze']);
		if(
			$ratio_gold >= 0 && $ratio_gold <= 100 && 
			$ratio_silver >= 0 && $ratio_silver <= 100 && 
			$ratio_bronze >= 0 && $ratio_bronze <= 100 &&
			$ratio_gold + $ratio_silver + $ratio_bronze > 100
		) {
			$this->error('Total award ratio should not exceed 100.');
        }
		$contest_info['award_ratio'] = $ratio_bronze * 1000000 + $ratio_silver * 1000 + $ratio_gold;
        //过滤时间格式，否则插入数据库可能出错
        $contest_info['start_time'] = date('Y-m-d H:i:s', $starttime);
        $contest_info['end_time'] = date('Y-m-d H:i:s', $endtime);
		return [$contest_info, $contest_md_info];

	}
    public function contest_edit_ajax()
    {
        if(!$this->IsContestAdmin()) {
            $this->error('Powerless');
        }
		
		$ret = $this->contest_addedit_ajax_process();
		$contest_edit = $ret[0];
		$contest_md_edit = $ret[1];
		
        $contestinfo = array_replace($this->contest, $contest_edit);

        db('contest')->update($contestinfo);
        // contest已更新，下面处理contest_md
        $Contest_md = db('contest_md');
        $contest_md = $Contest_md->where('contest_id', $this->contest['contest_id'])->find();
        if(!$contest_md) {
            $contest_md_edit['contest_id'] = $this->contest['contest_id'];
            $Contest_md->insert($contest_md_edit);
        }
        else
        {
			$contest_md_edit['contest_id'] = $this->contest['contest_id'];
            $Contest_md->update($contest_md_edit);
        }
        $this->success('Contest successfully modified.');
    }
    // **************************************************
    // Contest Edit
    public function contest_rejudge() {
        if(!$this->IsContestAdmin()) {
            $this->error("No privilege to rejudge");
        }
        $this->assign([
            'rejudge_type' => 'contest', 
            'cid' => $this->contest['contest_id'], 
            'submit_url' => '/' . $this->module . '/' . $this->controller . '/contest_rejudge_ajax?cid=' . $this->contest['contest_id']
        ]);
        return $this->fetch();
    }
    public function contest_rejudge_ajax() {
        // 比赛内题目重判
        if(!$this->IsContestAdmin()) {
            $this->error("No privilege to rejudge");
        }
        $solution_id = trim(input('solution_id'));
        $problem_alphabet_id = trim(input('problem_id'));
        $rejudge_res_check = input('rejudge_res_check/a');
        $map = ['contest_id' => $this->contest['contest_id']];
        if($rejudge_res_check === null) {
            $rejudge_res_check = [];
        }
        if(!in_array('any', $rejudge_res_check)) {
            $map['result'] = ['in', $rejudge_res_check];
        }   
        $addUrl = '';
        $Solution = db('solution');
        $contestKind = $this->contest['private'] % 10;
        if($solution_id != '') {
            $solutionIdList = explode(',', $solution_id);
            $map['solution_id'] = ['in', $solutionIdList];
            $addUrl = '#solution_id=' . $solutionIdList[0];
        }
        else if($problem_alphabet_id != null && strlen($problem_alphabet_id) > 0) {
            $problemAlphabetIdList = explode(',', $problem_alphabet_id);
            $problemNumIdList = [];
            if($contestKind == 5) {
                // exam
                $problemNumIdList = $problemAlphabetIdList;
            } else {
                // tradition contest
                foreach($problemAlphabetIdList as $pAID) {
                    $problemNumIdList[] = Alphabet2Num($pAID);
                }
            }
            $problemList = db('contest_problem')->where(['contest_id' => $this->contest['contest_id'], 'num' => ['in', $problemNumIdList]])->field('problem_id')->select();
            $problemIdList = [];
            
            foreach($problemList as $prob) {
                $problemIdList[] = $prob['problem_id'];
            }
            if($contestKind == 5) {
                // exam
                $questionList = db('ex_question')->where('ex_question_id', 'in', $problemIdList)->field('description')->select();
                $problemIdList = [];
                foreach($questionList as $ques) {
                    $problemIdList[] = $ques['description'];
                }
            }
            $map['problem_id'] = ['in', $problemIdList];
            $addUrl = '#problem_id=' . $problemAlphabetIdList[0];
        } else {
            // 避免误操作或网络请求问题导致重判整个比赛的严重后果
            $this->error("请提供提交号或题目号<br/>Please provide solution_id or problem_id.");
        }
        $Solution->where($map)->update([
            'result'    => 1,
            'memory'    => 0,
            'time'      => 0,
            'pass_rate' => 0
        ]);
        $jumpurl = '/' . ($this->OJ_MODE == 'online' ? 'csgoj' : 'cpcsys') . '/contest/status?cid=' . $this->contest['contest_id'] . $addUrl;
        $this->success('Rejudge started', '', $jumpurl);
    }
    // **************************************************
    // Award Related
    public function award() {
        if(!$this->IsContestAdmin()) {
            $this->error('Permission denied to see award', '/', '', 1);
        }
        $award_ratio = $this->GetAwardRatio();
        //设置school筛选表数据
        $this->assign([
            'contest'       => $this->contest,
            // 'user_id'       => $this->contest_user,
            'ratio_gold'    => $award_ratio[0],
            'ratio_silver'  => $award_ratio[1],
            'ratio_bronze'  => $award_ratio[2],
        ]);
        return $this->fetch();
    }
    // **************************************************
    // Contest Message
    // 弹窗消息
    // **************************************************
    protected function MsgAuth() {
        if(!$this->IsContestAdmin('admin')) {
            $this->error("No Privilege to Send Message");
        }
    }
    public function msg() {
        $this->MsgAuth();
        return $this->fetch();
    }
    public function msg_ajax() {
        $this->MsgAuth();
        return db('contest_msg')->where(['contest_id' => $this->contest['contest_id']])->select();
    }
    public function msg_add_edit_ajax() {
        $this->MsgAuth();
        $msg_id = input('msg_id/d');
        $content = input('content/s');
        if (strlen($content) > 255) {
            $this->error("消息过长<br/>Message too long");
        }
        $ContestMsg = db('contest_msg');
        if(!$msg_id) {
            $ContestMsg->insert([
                'content'       => $content,
                'contest_id'    => $this->contest['contest_id'],
                'in_date'       => date('Y-m-d H:i:s'),
                'defunct'       => 1,
                'team_id'       => $this->contest_user ? $this->SolutionUser($this->contest_user, true) : session('user_id')
            ]);
        } else {
            $msg = $ContestMsg->where('msg_id', $msg_id)->find();
            if(!$msg || $msg['contest_id'] != $this->contest['contest_id']) {
                $this->error("no such message");
            }
            $msg['content'] = $content;
            $msg['team_id'] = $this->contest_user ? $this->SolutionUser($this->contest_user, true) : session('user_id');
            $msg['in_date'] = date('Y-m-d H:i:s');
            $ContestMsg->update($msg);
        }
        $this->success('ok');
    }
    public function msg_status_change_ajax() {
        $this->MsgAuth();
        $msg_id = input('msg_id/d');
        $msg = db('contest_msg')->where(['msg_id' => $msg_id,  'contest_id' => $this->contest['contest_id']])->find();
        if(!$msg) {
            $this->error("no such message");
        }
        $msg['defunct'] = input('defunct/d');
        if($msg['defunct'] == 0) {
            $msg['in_date'] = date('Y-m-d H:i:s');
        }
        db('contest_msg')->update($msg);
        $this->success('Status to ' . ($msg['defunct'] == 1 ? 'Prepared' : 'Sent'), null, $msg);
    }

    // **************************************************
    // 外榜
    public function outrank() {
        return $this->fetch();
    }
}