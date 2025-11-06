<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 20:25
 */
namespace app\admin\controller;
use think\db\Expression;
use think\Validate;
require_once(__DIR__ . "../../../traits.php");
use app\ContestInfoTrait;
class Contest extends Adminbase
{

    //***************************************************************//
    //Contest
    //***************************************************************//
    public function index() {
        $this->assign('is_admin', true);
        if($this->OJ_STATUS == 'cpc') {
            return $this->fetch();
        } else {
            return $this->fetch('index_clss');
        }
    }
    public function contest_list_ajax() {
        $Contest = db('contest');
        $map = [];
        $defunct = input('defunct/d');
        if($defunct !== null) {
            $map['defunct'] = $defunct;
        }
        if($this->OJ_STATUS == 'cpc') {
            $map['private'] = ['in', [0, 1, 2, 10, 11, 12]];
        } else {
            $map['private'] = ['in', [4, 14]];
            $clss_id = input('clss_id/d');
            if($clss_id !== null) {
                $map['password'] = $clss_id;
            }
        }
        $column = input('column/s');
        if($column != null) {
            return $Contest->where($map)->column($column);
        } else {
            $contest_list = $Contest->where($map)->field('description', true)->select();
            foreach($contest_list as &$contest) {
                if(IsAdmin() || IsAdmin('con', $contest['contest_id'])) {
                    $contest['is_admin'] = true;
                }
            }
            return $contest_list;
        }
        
    }
	public function contest_addedit_ajax_process()
	{
        $postData = input('post.');
        $contest_info = [
            'title'         => trim($postData['title']),
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
            'private'     => intval($postData['private']),
            'langmask'    => $this->CalLangMask(isset($postData['language']) ? $postData['language'] : []), //这里用input('post.language')不行，可能是ThinkPHP当前版本bug
            'password'    => trim($postData['password']),
            'description' => trim($postData['description']),
            'frozen_minute' => intval($postData['frozen_minute']),
            'frozen_after' => intval($postData['frozen_after'])
        ];
        if(strlen($contest_info['title']) == 0) {
            $this->error("Title should not be empty.");
        }
        $attach_pro = input('?attach_pro') ? input('attach_pro/d') : 0;  // 是否有附加题（是否将最后一题计入总分），有则不计，没有则计
        // 以最高多少队总分为学校排名
        $contest_info['topteam'] = intval($postData['topteam']);
        if($contest_info['topteam'] > 20) {
            $contest_info['topteam'] = 20;
        }
        if($contest_info['topteam'] < 1){
            $contest_info['topteam'] = 1;
        }
        $contest_info['private'] = $attach_pro * 10 + $contest_info['private'];
        if($contest_info['frozen_minute'] > 2592000 || $contest_info['frozen_after'] > 2592000)
            $this->error('Frozen time too long.');
        $passLen = strlen($contest_info['password']);
        // if($passLen > 0 && $passLen < 3) {
        //     $this->error("Contest Password should more than 6 characters");
        // }
        if($passLen > 15) {
            $this->error("Contest Password should NOT more than 15 characters");
        }
        $contest_md_info = [
            'description' => $contest_info['description'],
        ];
        //插入contest表，描述字段为md编译的html
        $contest_info['description']    = ParseMarkdown($contest_md_info['description']);

        // 时间格式如果错误，直接插入mysql会抛出异常且暂时没找到方法catch异常，导致直接500而不ajax反馈错误。所以手动判断
        if(!strtotime($contest_info['start_time']) || !strtotime($contest_info['end_time']))
            $this->error('Time syntax error.');
        $starttime = strtotime($contest_info['start_time']);
        $endtime = strtotime($contest_info['end_time']);
        if($starttime >= $endtime) {
            $this->error('Start Time should before End Time.');
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
    public function contest_add()
    {
        $now = time();
        $this->assign([
            'start_year'    => date('Y', $now),
            'start_month'   => date('m', $now),
            'start_day'     => date('d', $now),
            'start_hour'    => date('H', $now),
            'start_minute'  => 0,
            'end_year'      => date('Y', $now + 18000),
            'end_month'     => date('m', $now + 18000),
            'end_day'       => date('d', $now + 18000),
            'end_hour'      => date('H', $now + 18000),
            'end_minute'    => 0,
            'private'       => $this->OJ_MODE == 'online' ? 1 : 2,
            'topteam'       => 1,   // 每个学校前topteam个正式队伍计入学校排名
            'ratio_gold'    => 10,
            'ratio_silver'  => 20,
            'ratio_bronze'  => 30,
            'frozen_minute' => 60,
            'frozen_after'  => 300,
            'edit_mode'     => false
        ]);
        return $this->fetch($this->OJ_STATUS == 'exp' ? 'contest_edit_clss' : 'contest_edit');
    }
    public function contest_add_ajax(){
		$ret = $this->contest_addedit_ajax_process();
		$contest_add = $ret[0];
		$contest_md_add = $ret[1];
        
        $contest_add['attach']     = $this->AttachFolderCalculation(session('user_id')); // 计算附件文件夹名称，固定后导入导出题目不会有路径变化问题
		$contest_add['defunct'] = '1';  // 默认隐藏防泄漏
		
        $contest_id = db('contest')->insertGetId($contest_add);
        if(!$contest_id)
            $this->error('Add contest failed, SQL error.');
			
        // contest已插入，下面处理contest_md
        $Contest_md = db('contest_md');
        $contest_md = $Contest_md->where('contest_id', $contest_id)->find();
        $contest_md_add['contest_id'] = $contest_id; //注意contest_md表要设置contest_id以和contest表对应。
        //虽然新插数据基本不会发生contest_md已有此contest_id的情况，但以防万一contest表被删除过并修改过auto_increacement
        if($contest_md == null) {
            $Contest_md->insert($contest_md_add);
        }
        else {
			$contest_md_add['contest_id'] = $contest_id;
            $Contest_md->update($contest_md_add);
        }
        $addmsg = $this->contest_outeritem_add($contest_id, $contest_add);
        //由于该用户添加的，给该用户管理该比赛的权限（用于不同比赛分权）
        $this->AddPrivilege(session('user_id'), 'contest', $contest_id);
        $successmsg = 'Contest successfully added.' . ($addmsg == '' ? '' : '<br/>'.$addmsg);
        
        //处理cooperator
        $cooperator = input('cooperator/s');
        $cooperatorList = explode(",", $cooperator);
        $cooperatorFailList = $this->SaveCooperator($cooperatorList, $contest_id);
        $alert = false;
        if(strlen($cooperatorFailList) > 0) {
            $alert = true;
        }
        $this->success($successmsg, '', ['id' => $contest_id, 'alert' => $alert]);
    }
    public function contest_edit($copy_mode=false) {
        $contest_id = trim(input('id'));
        if(!IsAdmin($this->privilegeStr, $contest_id))
        {
            $this->error('Powerless');
        }
        $contest = db('contest')->where('contest_id', $contest_id)->find();
        if($contest == null)
        {
            $this->error('No such contest.');
        }
        $contest_md = db('contest_md')->where('contest_id', $contest_id)->find();
        if($contest_md != null)
        {
            $contest = array_replace($contest, $contest_md);
        }
        $start = strtotime($contest['start_time']);
        $end = strtotime($contest['end_time']);
        // 比赛的题目列表
        $contestProblem = db('contest_problem')->where('contest_id', $contest_id)->order('num')->select();
        $problems = [];
        $balloon_colors = [];
        foreach($contestProblem as $problem) {
            $p = $problem['problem_id'];
            // if($this->OJ_OPEN_OI && $problem['pscore'] > 0) {
            //     $p .= ":" . $problem['pscore'];
            // }
            $problems[] = $p;
            $balloon_colors[] = $problem['title'];
        }
        // 比赛的有权限用户
        $contestUser = db('privilege')->where('rightstr', 'c'.$contest_id)->order('user_id')->select();
        $users = [];
        foreach($contestUser as $user)
            $users[] = $user['user_id'];
        $cooperator = $this->GetCooperator($contest['contest_id']);
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
            'private'       => $contest['private'],
            'topteam'       => $contest['topteam'],
            'ratio_gold'    => $ratio_gold,
            'ratio_silver'  => $ratio_silver,
            'ratio_bronze'  => $ratio_bronze,
            'frozen_minute' => $contest['frozen_minute'],
            'frozen_after'  => $contest['frozen_after'],
            'contest'       => $contest,
            'problems'      => implode(",", $problems),
            'balloon_colors'=> implode(",", $balloon_colors),
            'users'         => implode("\n", $users),
            'cooperator'    => implode(",", $cooperator),
            'item_priv'     => IsAdmin($this->privilegeStr, $contest['contest_id']),
            'edit_mode'     => true,
            'copy_mode'     => $copy_mode,
        ]);
        return $this->fetch($this->OJ_STATUS == 'exp' ? 'contest_edit_clss' : 'contest_edit');
    }
    public function contest_copy() {
        return $this->contest_edit(true);
    }
    public function contest_edit_ajax()
    {
        $contest_id = trim(input('contest_id'));
        if(!IsAdmin($this->privilegeStr, $contest_id))
        {
            $this->error('Powerless');
        }
        $Contest = db('contest');
        $contestinfo = $Contest->where('contest_id', $contest_id)->find();
        if(!$contestinfo)
        {
            $this->error('No such contest.');
        }
		
		$ret = $this->contest_addedit_ajax_process();
		$contest_edit = $ret[0];
		$contest_md_edit = $ret[1];

		
        $contestinfo = array_replace($contestinfo, $contest_edit);

        $Contest->update($contestinfo);
        // contest已插入，下面处理contest_md
        $Contest_md = db('contest_md');
        $contest_md = $Contest_md->where('contest_id', $contest_id)->find();
        $contest_md_edit['contest_id'] = $contest_id;
        if(!$contest_md) {
            $Contest_md->insert($contest_md_edit);
        }
        else {
			$contest_md_edit['contest_id'] = $contest_id;
            $Contest_md->update($contest_md_edit);
        }
        $addmsg = $this->contest_outeritem_add($contest_id, $contestinfo);
        $successmsg = 'Contest successfully edited.' . ($addmsg == '' ? '' : '<br/>'.$addmsg);
        //处理cooperator
        $cooperator = input('cooperator/s');
        $cooperatorList = explode(",", $cooperator);
        $cooperatorFailList = $this->SaveCooperator($cooperatorList, $contest_id);
        $alert = false;
        if(strlen($cooperatorFailList) > 0)
        {
            $alert = true;
        }
        $this->success($successmsg . $cooperatorFailList, '', ['alert' => $alert]);
    }
    private function CalLangMask($languages)
    {
        $ret = LangList2LangMask($languages);
        if($ret == -1) $this->error('Please select at least 1 language.');
        if($ret == -2) $this->error('Some languages are not allowed for this OJ.');
        return $ret;
    }
    private function contest_outeritem_add($contest_id, $contest)
    {
        /***********/
        // 处理Contest Problems
        $problemList = explode(",", trim(input('problems/s')));
        $balloonColorList = explode(",", trim(input('balloon_colors/s')));
        $ContestProblem = db('contest_problem');
        $ContestProblem->where('contest_id', $contest_id)->delete();//虽然基本不会有，还是防contest的auto_increcement被修改过
        $contest_problem_add = [];
        $num = 0;
        $Problem = db('problem');
        $now = time();
        $contest_end_time = strtotime($contest['end_time']);
        $problemSelected = [];
        $infoDuplicate = '';
        $infoNovalid = '';
        $nonZeroPscore = 0;
        if(count($problemList) != count($balloonColorList)) {
            $this->error("The number not match between balloon colors and problems");
        }
        foreach($problemList as $p)
        {
            // $p 可以为 “1000:20” 的形式，用于给每个题目分配分数
            $pinfoList = explode(":", trim($p));
            $p = intval($pinfoList[0]);
            $pscore = 0;
            if(count($pinfoList) > 1)
                $pscore = intval($pinfoList[1]);

            if($p < 1000)
                continue;
            if(array_key_exists($p, $problemSelected))
            {
                $infoDuplicate = 'Some duplicated problems are removed';
                continue;
            }
            $problem = $Problem->where('problem_id', $p)->field('defunct')->find();
            if($problem == null)
            {
                $infoNovalid = 'Some problem not exists';
                continue;
            }
            $problemSelected[$p] = true;
            if($pscore > 0) $nonZeroPscore ++;
            $contest_problem_add[] = [
                'problem_id'    => $p,
                'contest_id'    => $contest_id,
                'num'           => $num,
                'title'         => $balloonColorList[$num],
                'pscore'        => $pscore
            ];
            $num ++;
        }
        $pnum = count($contest_problem_add);
        if($pnum > 0)
        {
            $attachProblem = round($contest['private'] / 10);
            if($nonZeroPscore == 0) {   // 如果所有题都没安排分数
                $mainProNum = $pnum - $attachProblem;
                if($mainProNum > 0) {
                    $averScore = floor(100 / $mainProNum);
                    $ithMore = 100 - $averScore * ($mainProNum);
                    for($i = $mainProNum - 1; $i >= 0; $i --) {
                        $contest_problem_add[$i]['pscore'] = $averScore + ($i < $ithMore);
                    }
                }
            }
            if(!$ContestProblem->insertAll($contest_problem_add)) {
                $this->error('Contest problems insert failed.');
            }
        }
        /***********/
        // 处理Contest Users
        $contestUsers = trim(input('users'));
        if($contestUsers != '') {
            $userList = explode("\n", $contestUsers);
            $userList = array_unique($userList);//去除重复项
            $Privilege = db('privilege');
            $Privilege->where('rightstr', 'c'.$contest_id)->delete();
            $contest_user_add = [];
            foreach($userList as $u)
            {
                if(strlen($u) > 30)
                    $this->error('User name "' . $u . '" too long');
                $contest_user_add[] = [
                    'user_id'    => trim($u),
                    'rightstr'   => 'c'.$contest_id,
                    'defunct'    => '0'
                ];
            }
            if(count($contest_user_add) > 0) {
                if(!$Privilege->insertAll($contest_user_add)) {
                    $this->error('Contest users insert failed.');
                }
            }
        }
        $ret = '';
        if(strlen(trim($infoDuplicate)) > 0)
            $ret .= $infoDuplicate;
        if(strlen(trim($infoNovalid)) > 0){
            if(strlen($ret) > 0){
                $ret .= '<br/>';
            }
            $ret .= $infoNovalid;
        };
        return $ret;
    }
}