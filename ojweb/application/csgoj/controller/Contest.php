<?php

namespace app\csgoj\controller;

use think\db\Expression;
use think\Request;
use think\Controller;
use think\Db;

class Contest extends Csgojbase
{
    var $contest;
    var $contestStatus;                 // -1表示未开始，1表示进行中，2表示已结束
    var $rankFrozen;                    // false表示正常，true表示封榜

    var $closeRankTime;                 // 封榜时间戳（int）
    var $outsideContestAction;          // Contest的比赛列表页和其对应的列表数据ajax页action名称
    var $allowPublicVisitAction;        // private比赛允许公开观看的页面，目前就是ranklist和对应的数据ajax页
    var $ojLang;                        // oj的允许编程语言表
    var $allowLanguage;                 // 比赛允许的编程语言，从contest的langmask读
    var $running;                       // 比赛是否正在进行，封榜没封榜都是true
    var $problemIdMap;                  // ['abc2id'=>//ABC->10xx题号映射, 'id2abc'=>//10xx->ABC题号映射, 'id2num'=>//10xx->0、1、2(num)]
    var $ojResults;                     // oj的所有判题结果
    var $ojResultsHtml;                 // 判题结果的显示方案
    var $allowResults;                  // statistic统计的判题结果
    var $canJoin;                       // 参赛权限
    var $needAuth;                      // 比赛加了密码（目前只Public比赛密码生效）
    var $contest_user;                  // 登录这个比赛的用户
    var $contest_problem_list;          // 比赛的题目列表
    var $isAdmin;
    var $isContestAdmin;
    var $rankUseCache;
    var $topicDefaultMap = ['public_show' => ['neq', -1]];               // topic 的默认过滤逻辑
    
    var $isContestStaff=false;      // cpcsys使用，是否是包含proctor（contest内部admin）在内的工作人员
    var $isContestWorker=false;     // cpcsys使用，是否是balloon等无任何管理特权的工作人员
    var $proctorAdmin=false;        // cpcsys使用，是否是contest内部admin
    public function InitController()
    {
        if ($this->OJ_STATUS == 'exp' && $this->controller == 'contest') {
            $this->redirect('/csgoj/contestexp');
        }
        $this->ContestInit();
    }
    public function ContestInit()
    {
        $this->assign('pagetitle', 'Contest');
        $this->outsideContestAction = ['index', 'contest_list_ajax', 'contest_data_joint_ajax'];
        $this->allowPublicVisitAction = ['ranklist', 'ranklist_ajax', 'scorerank', 'scorerank_ajax', 'schoolrank', 'schoolrank_ajax', 'contest', 'contest_auth_ajax', 'team_auth_type_ajax', 'contest_data_ajax'];
        $this->ojLang = config('CsgojConfig.OJ_LANGUAGE');
        $this->ojResults = config('CsgojConfig.OJ_RESULTS');
        $this->ojResultsHtml = config('CsgojConfig.OJ_RESULTS_HTML');
        $this->allowLanguage = $this->ojLang;
        $this->running = false;
        $this->canJoin = false;
        $this->allowResults = [4, 5, 6, 7, 8, 9, 10, 11];
        $this->needAuth = false;
        $this->contest_user = null;
        $this->isAdmin = IsAdmin();
        $this->isContestAdmin = false;
        if (!in_array($this->request->action(), $this->outsideContestAction)) {
            $this->GetContestInfo();
            $this->rankUseCache = !$this->IsContestAdmin() ? 1 : 0;
            $this->isContestAdmin = $this->IsContestAdmin();
            $this->GetVars();
            $this->ContestAuthentication();
            $this->SetAssign();
        }
        if ($this->OJ_STATUS == 'exp') {
            $this->assign('contest_controller', 'contestexp');
        } else {
            $this->assign('contest_controller', 'contest');
        }
    }

    public function GetContestInfo()
    {
        $cid = input('cid/d');
        if (!$cid) {
            $this->error('How did you find this page?', null, '', 1);
        }
        $this->contest = db('contest')->where('contest_id', $cid)->find();
        if (!$this->contest) {
            $this->error("No such contest");
        }
        if ($this->controller == 'contestlive') {
            // nothing
        } else if (!in_array($this->contest['private'] % 10, [2, 5]) && $this->module != 'csgoj') {
            $this->redirect('/csgoj/' . $this->controller . '/' . $this->action . '?cid=' . $this->contest['contest_id']);
        } else if ($this->contest['private'] % 10 == 2 && $this->module != 'cpcsys') {
            $this->redirect('/cpcsys/' . $this->controller . '/' .  $this->action . '?cid=' . $this->contest['contest_id']);
        } else if ($this->contest['private'] % 10 == 5 && $this->module != 'expsys') {
            $this->redirect('/expsys/' . $this->controller . '/' . $this->action . '?cid=' . $this->contest['contest_id']);
        }
    }
    public function CanJoin()
    {
        //是否有参加比赛权限，private设置的参赛权或public比赛无密码，输入正确的密码的public比赛也会获得参赛session
        if (!ItemSession('c', $this->contest['contest_id'])) {
            if ($this->contest['private'] % 10 == 1) {
                $this->needAuth = false;
                return false;
            } else if ($this->contest['private'] % 10 == 0 && strlen(trim($this->contest['password'])) > 0) {
                $this->needAuth = true;
                return false;
            }
        }
        return true;
    }
    public function IsContestAdmin($privilegeName = null)
    {
        return IsAdmin('contest', $this->contest['contest_id']);
    }
    public function ContestAuthenticationBase()
    {
        if ($this->contest['defunct'] == '1' && !$this->IsContestAdmin()) {
            // 比赛隐藏，且又不是该比赛管理员
            $this->error('You cannot open this contest.', null, '', 1);
        }
        if ($this->CanJoin() || $this->IsContestAdmin() || $this->IsContestAdmin('admin')) {
            // 有参赛权或是该比赛管理员
            $this->canJoin = true;
        }
        if ($this->contestStatus == -1 && !$this->IsContestAdmin() && !$this->IsContestAdmin('admin')) {
            // 比赛尚未开始，且不是该比赛管理员
            $action = strtolower($this->request->action());
            // 处理printer和balloon的特殊情况，在cpcsys里有重载IsContestAdmin
            if ($action == "balloon" && $this->IsContestAdmin('balloon_manager') || $action == "print_status" && $this->IsContestAdmin('printer')) {
                $this->canJoin = true;
            } else if (!in_array($action, ['contest', 'contest_auth_ajax', 'team_auth_type_ajax', 'contest_logout_ajax'])) {
                $this->redirect("contest?cid=" . $this->contest['contest_id']);
            }
        }
    }
    public function ContestAuthentication()
    {
        $this->ContestAuthenticationBase();
        //比赛权限验证，执行此函数前需已获得$this->contest变量

        if (!in_array($this->request->action(),  $this->allowPublicVisitAction) && !$this->canJoin) {
            //如果不是ranklist，则要验证参加比赛的权限，权限不符则跳转到比赛说明页
            $this->redirect("contest?cid=" . $this->contest['contest_id']);
        }
    }
    public function GetVars()
    {
        $this->contestStatus = $this->ContestStatus();
        $this->running = $this->contestStatus == 1;
        $allowLanguage = $this->FromLangMask($this->contest['langmask']);
        if (count($allowLanguage) > 0)
            $this->allowLanguage = $allowLanguage;
        $this->ProblemIdMap();
    }
    public function SetAssign()
    {
        //******在contest的各个页面assign的通用变量
        //比赛信息
        $this->assign('contest', $this->contest);
        //当前状态（-1未开始，1进行中，2已结束）
        $this->assign('contestStatus', $this->contestStatus);
        $this->assign('rankFrozen', $this->rankFrozen);
        //用于在contet_header初始化当前时间，之后由js计算本地时间差并继续显示动态时间
        $this->assign('now', date('Y-m-d H:i:s'));
        //旧数据可能有langmask没指定语言的情况，数据合法时才设置为比赛的langmask，否则为默认的系统允许语言
        $this->assign('allowLanguage', $this->allowLanguage);

        //为方便View中一个变量判断，加个running标识符
        $this->assign('running', $this->running);

        // 题号1xxx、ABCD、num的0123 题号的对应关系
        $this->assign('problemIdMap', $this->problemIdMap);
        $this->assign('pagetitle', 'Contest ' . $this->contest['contest_id'] . ' ' . $this->request->action());
        //参赛权限
        $this->assign('canJoin', $this->canJoin);
        $this->assign('needAuth', $this->needAuth);
        //******公共配置信息
        $this->assign('ojLang', $this->ojLang);
        $this->assign('ojResults', $this->ojResults);
        $this->assign('ojResultsHtml', $this->ojResultsHtml);
        // 设置比赛用户 id，标记用户是否登录，区分 cpcsys 和 oj 的contest
        $this->SetAssignUser();
        $this->assign('isContestAdmin', $this->IsContestAdmin());
        $this->assign('isAdmin', IsAdmin());
    }
    public function SetAssignUser()
    {
        if (session('?user_id')) {
            $this->contest_user = session('user_id');
            $this->assign('contest_user', $this->contest_user);
            $this->assign('login_teaminfo', session('login_user_info'));
        } else {
            $this->contest_user = null;
            $this->assign('contest_user', $this->contest_user);
            $this->assign('login_teaminfo', null);
        }
    }
    public function ContestStatus($contest = null)
    {
        //-1未开始，1进行中，2结束
        if ($contest == null)
            $contest = $this->contest;
        $now = time();
        $start_time = strtotime($contest['start_time']);
        $end_time = strtotime($contest['end_time']);
        // $this->closeRankTime = intval(($end_time - $start_time) * 0.8 + $start_time + 0.00000001);   // 旧版本，4/5 时间封榜
        $this->closeRankTime = $end_time - ($contest['frozen_minute'] > 0 ? $contest['frozen_minute'] : 0) * 60;
        $frozen_end_time = $end_time + ($contest['frozen_after'] > 0 ? $contest['frozen_after'] : 0) * 60;
        $ret = -1;
        $this->rankFrozen = false;
        if ($now < $start_time)
            $ret = -1;
        else if ($now < $end_time)
            $ret = 1;
        else
            $ret = 2;
        if ($now > $this->closeRankTime && $now < $frozen_end_time) {  // 封榜时间段
            $this->rankFrozen = true;
        }
        if ($this->IsContestAdmin() || $this->IsContestAdmin('balloon_manager') || 
        $this->IsContestAdmin('balloon_sender') || $this->IsContestAdmin('admin') ||
        $this->IsContestAdmin('watcher')) { //管理员、气球管理员、观察员（直播员）不封榜
            $this->rankFrozen = false;
        }
        return $ret;
    }
    public function ContestProblemId($ith)
    {
        //比赛题目编号计算, 0是A, 1是B，26是AA，类似Excxcel横轴命名规则
        $ret = '';
        $ith = intval($ith) + 1;
        while ($ith > 0) {
            $ret = chr(($ith - 1) % 26 + ord('A')) . $ret;
            $ith = intval(($ith - 1) / 26);
        }
        return $ret;
    }
    public function ProblemIdMap()
    {
        // 题号1xxx、ABCD、num的0123 题号的对应关系
        //[
        //    'abc2id'=>//ABC->10xx题号映射,
        //     'id2abc'=>//10xx->ABC题号映射,
        //     'id2num'=>//10xx->0、1、2(num)
        //]
        $this->contest_problem_list = db('contest_problem')
            ->where('contest_id', $this->contest['contest_id'])
            ->field([
                'problem_id',
                'num',
                'title',
                'pscore'
            ])
            ->order('num', 'asc')
            ->cache(20)
            ->select();

        $this->problemIdMap = [
            'abc2id' => [],
            'id2abc' => [],
            'id2num' => [],
            'num2score' => [],
            'id2score' => [],
            'num2color' => [],
            'num2abc' => [],
        ];
        if ($this->contest_problem_list == null) {
            //这种情况一般不会发生，如果真的有，那是管理员操作不当，页面出问题也难免
            return;
        }
        $zeroScoreFlag = true;  // 是否所有题都没设置分数
        foreach ($this->contest_problem_list as $problemId) {
            if ($problemId['pscore'] > 0) {
                $zeroScoreFlag = false;
            }
        }
        // 如果有附加题，则计分题目个数减1
        $pnum = count($this->contest_problem_list) - (round($this->contest['private'] / 10) == 1);
        $everScore = $pnum <= 0 ? 100 : (floor(100 / $pnum * 10) / 10);
        foreach ($this->contest_problem_list as $problemId) {
            $alphabetId = $this->ContestProblemId($problemId['num']);
            $this->problemIdMap['abc2id'][$alphabetId] = $problemId['problem_id'];
            $this->problemIdMap['id2abc'][$problemId['problem_id']] = $alphabetId;
            $this->problemIdMap['id2num'][$problemId['problem_id']] = $problemId['num'];
            if ($problemId['num'] < $pnum) {
                $this->problemIdMap['num2score'][$problemId['num']] = $zeroScoreFlag ? $everScore : $problemId['pscore'];
                $this->problemIdMap['id2score'][$problemId['problem_id']] = $zeroScoreFlag ? $everScore : $problemId['pscore'];
            } else {
                $this->problemIdMap['num2score'][$problemId['num']] = $problemId['pscore'];
                $this->problemIdMap['id2score'][$problemId['problem_id']] = $problemId['pscore'];
            }
            $this->problemIdMap['num2color'][$problemId['num']] = $problemId['title'];
            $this->problemIdMap['num2abc'][$problemId['num']] = $alphabetId;
        }
    }
    public function index()
    {
        return $this->fetch();
    }
    public function contest()
    {
        // 比赛首页
        return $this->fetch();
    }
    public function contest_auth_ajax()
    {
        if (!$this->contest_user)
            $this->error('Please login first');
        $contest_pass = trim(input('contest_pass'));
        if ($contest_pass == $this->contest['password']) {
            ItemSession('c', $this->contest['contest_id'], true);
            $this->success('Verification passed', null, ['redirect_url' => "/" . $this->module . "/" . $this->controller . "/problemset?cid=" . $this->contest['contest_id']]);
        } else
            $this->error('Wrong password');
    }
    public function ContestType($contest)
    {
        // if($contest['private'] % 10 == 0)
        //     return (strlen(trim($contest['password'])) > 0 ? "<strong class='text-warning'>Encrypted</strong>" : "<strong class='text-success'>Public</strong>");
        // if($contest['private'] % 10 == 1)
        //     return "<strong class='text-danger'>Private</strong>";
        // if($contest['private'] % 10 == 2)
        //     return "<strong class='text-primary'>Standard</strong>";
        return $contest['private'] % 10;
    }
    public function contest_list_ajax()
    {
        //暂时bootstrap-table的pagination改为client side，即server直接返回所有比赛
        $columns = ["contest_id", "title", "start_time", "end_time", "defunct", "private", "langmask", "password", "topteam", "award_ratio", "frozen_minute", "frozen_after"];
        // // 前端分页
        // $offset        = input('offset/d');
        // $limit        = input('limit/d');
        $search        = trim(input('search/s'));

        $map = [];

        if ($this->module == 'ojtool') {
            // nothing
        } else if ($this->module == 'cpcsys') {
            $map['private'] = ['in', [2, 12]];
        } else {
            $map['private'] = ['in', [0, 1, 10, 11]];
        }
        if (strlen($search) > 0)
            $map['contest_id|title'] =  ['like', "%$search%"];
        //让管理员在此页面也无法查看defunct的比赛，查看可在管理后台看。所以注释掉if(!IsAdmin('contest_editor'))
        //        if(!IsAdmin('contest_editor'))
        $map['defunct'] = '0';
        $ret = [];
        $Contest = db('contest');
        $contestList = $Contest
            ->where($map)
            ->order(['contest_id' => 'desc'])
            ->field($columns)
            ->select();
        foreach ($contestList as &$contest) {
            $contest['status']  =  $this->ContestStatus($contest);
            $contest['kind']    =  $this->ContestType($contest);
            $contest['has_pass'] = strlen(trim($contest['password'])) > 0;
            $contest['password'] = "";  // 隐藏密码
        }
        return $contestList;
    }
    /**************************************************/
    //Problem
    /**************************************************/
    public function problemset()
    {

        if (isset($this->isContestStaff) && $this->isContestStaff && !$this->isContestAdmin && !$this->proctorAdmin) {
            $this->redirect('/' . $this->module . '/' . $this->controller . '/contest?cid=' . $this->contest['contest_id']);
        }
        return $this->fetch();
    }
    public function problemset_ajax()
    {
        $summary_map = ['result' => 4, 'contest_id' => $this->contest['contest_id']];
        if ($this->rankFrozen) {
            // 如果不是比赛管理员，则统计ac数要按封榜时间
            $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
            $summary_map['in_date'] = ['lt', $closeRankTimeStr];
        }
        $contestProblemSql = db('contest_problem')->alias('cp')
            ->join('problem p', 'p.problem_id = cp.problem_id', 'left')
            ->where('cp.contest_id', $this->contest['contest_id'])
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'cp.num num',
                'cp.pscore pscore',
                'cp.title color'    // 不改数据库了，暂时用这个字段
            ])
            ->buildSql();
        $acSql = db('solution')
            ->field(['problem_id', 'count(1) accepted'])
            ->where($summary_map)
            ->group('problem_id')
            ->buildSql();
        $submitSql = db('solution')
            ->field(['problem_id', 'count(1) submit'])
            ->where(['contest_id' => $this->contest['contest_id']])
            ->group('problem_id')
            ->buildSql();
        $problemList = db()->table([$contestProblemSql => 'p'])
            ->join($acSql . ' a', 'p.problem_id = a.problem_id', 'left')
            ->join($submitSql . ' s', 'p.problem_id = s.problem_id', 'left')
            ->order('p.num', 'asc')
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.num num',
                'p.color color',
                'p.pscore pscore',
                'a.accepted accepted',
                's.submit submit'
            ])
            ->cache(20)
            ->select();

        $solutionStatus = [];
        if ($this->contest_user) {
            $user_id = $this->SolutionUser($this->contest_user, true);
            $Solution = db('solution');
            //$solutionNormal指任意提交，$solutionAC只取AC了的提交，$solutionStatus标记哪些题过了哪些题没过，因为需要标记 没交、交了、AC了三种状态
            $solutionNormal = $Solution->where(['user_id' => $user_id, 'contest_id' => $this->contest['contest_id']])->field('problem_id')->group('problem_id')->select();
            $solutionAc = $Solution->where(['user_id' => $user_id, 'result' => 4, 'contest_id' => $this->contest['contest_id']])->field('problem_id')->group('problem_id')->select();
            foreach ($solutionNormal as $res) {
                $solutionStatus[$res['problem_id']] = '0';
            }
            foreach ($solutionAc as $res) {
                $solutionStatus[$res['problem_id']] = '1';
            }
        }
        $retList = [];
        foreach ($problemList as $problem) {
            if ($problem['problem_id'] == null) {
                //管理员弄错了题号，这里没有搜到这道题的情况下（多表联查，contest题目表left join problem，可能有problem不存在，虽然contest add时验证过）
                continue;
            }
            // $problem['title_show'] = "<a href='/" . $this->request->module() . "/" . $this->controller . "/problem?cid=". $this->contest['contest_id']."&pid=" . $this->problemIdMap['id2abc'][$problem['problem_id']] . "'>" . $problem['title'] . "</a>";

            if ($this->contestStatus < 2 && !$this->IsContestAdmin()) {
                $problem['problem_id_show'] = $this->problemIdMap['id2abc'][$problem['problem_id']];
            } else {
                $problem['problem_id_show'] = $this->problemIdMap['id2abc'][$problem['problem_id']] . '(' . $problem['problem_id'] . ')';
            }
            $problem['ac'] = "";
            if (array_key_exists($problem['problem_id'], $solutionStatus))
                $problem['ac'] = $solutionStatus[$problem['problem_id']] == '0' ? "<span class='text-warning'>N</span>" : "<span class='text-success'>Y</span>";
            // $problem['pscore'] = $this->problemIdMap['id2score'][$problem['problem_id']];
            if ($problem['accepted'] == null)
                $problem['accepted'] = 0;
            if ($problem['submit'] == null)
                $problem['submit'] = 0;
            $retList[] = $problem;
        }
        return $retList;
    }
    public function problem()
    {
        if (isset($this->isContestWorker) && $this->isContestWorker && !$this->isContestAdmin) {
            $this->error("No permission", '/' . $this->module . '/contest?cid=' . $this->contest['contest_id'], '', 1);
        }
        $summary_map = ['result' => 4, 'contest_id' => $this->contest['contest_id']];
        if ($this->rankFrozen) {
            // 如果不是比赛管理员，则统计ac数要按封榜时间
            $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
            $summary_map['in_date'] = ['lt', $closeRankTimeStr];
        }
        $apid = trim(input('get.pid'));
        $problem_id = $this->problemIdMap['abc2id'][$apid];

        $contestProblemSql = db('contest_problem')->alias('cp')
            ->join('problem p', 'p.problem_id = cp.problem_id', 'left')
            ->where('cp.contest_id', $this->contest['contest_id'])
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.description description',
                'p.input input',
                'p.output output',
                'p.sample_input sample_input',
                'p.sample_output sample_output',
                'p.spj spj',
                'p.hint hint',
                'p.time_limit time_limit',
                'p.memory_limit memory_limit',
                'cp.num num',
                'cp.pscore pscore'
            ])
            ->buildSql();
        $acSql = db('solution')
            ->field(['problem_id', 'count(1) accepted'])
            ->where($summary_map)
            ->group('problem_id')
            ->buildSql();
        $submitSql = db('solution')
            ->field(['problem_id', 'count(1) submit'])
            ->where(['contest_id' => $this->contest['contest_id']])
            ->group('problem_id')
            ->buildSql();
        $problem = db()->table([$contestProblemSql => 'p'])
            ->join($acSql . ' a', 'p.problem_id = a.problem_id', 'left')
            ->join($submitSql . ' s', 'p.problem_id = s.problem_id', 'left')
            ->order('p.num', 'asc')
            ->where(['p.problem_id' => $problem_id])
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.description description',
                'p.input input',
                'p.output output',
                'p.sample_input sample_input',
                'p.sample_output sample_output',
                'p.spj spj',
                'p.hint hint',
                'p.time_limit time_limit',
                'p.memory_limit memory_limit',
                'p.num num',
                'a.accepted accepted',
                's.submit submit',
                'p.pscore pscore'
            ])
            ->find();
        if ($problem == null) {
            $this->error('No such problem.', null, '', 1);
        }
        if ($problem['submit'] === null)
            $problem['submit'] = 0;
        if ($problem['accepted'] === null)
            $problem['accepted'] = 0;
        $problem['problem_id_show'] = $apid;
        if ($this->contestStatus == 2 || $this->IsContestAdmin())
            $problem['show_real_id'] = true;
        $problem['pagetitle'] = $apid . ': ' . $problem['title'];
        $this->assign(['contest' => $this->contest, 'problem' => $problem, 'apid' => $apid]);
        return $this->fetch();
    }
    public function SolutionUser($user_id, $appearprefix = null)
    {
        // 针对 cpcsys 的 solution 用户名前缀处理。常规系统里不处理
        return $user_id;
    }
    public function submit()
    {
        if (!$this->contest_user) {
            $this->error('Please login before submit problem solution!', null, '', 1);
        }
        if ($this->contestStatus == -1)
            $this->error('Not started.');
        if ($this->contestStatus == 2)
            $this->error('Contest Ended.', null, '', 1);

        $apid = trim(input('get.pid')); //这里传入的是ABCD的题号
        $this->assign([
            'cid'         => $this->contest['contest_id'],
            'apid'         => $apid,
            'pagetitle' => 'Submit Problem ' . $apid,
            'user_id'     => $this->contest_user
        ]);
        return $this->fetch();
    }
    public function submit_ajax()
    {
        if (!$this->contest_user) {
            $this->error('Please Login First!');
        }
        if (session('?lastsubmit')) {
            $now = time();
            $submitWaitTime = config('CsgojConfig.OJ_SUBMIT_WAIT_TIME');
            if ($now - session('lastsubmit') < $submitWaitTime)
                $this->error("You should not submit more than twice in " . $submitWaitTime . " seconds...");
        }
        $apid = trim(input('pid')); //ABCD
        if (!array_key_exists($apid, $this->problemIdMap['abc2id']))
            $this->error('No such problem!');
        $problem_id = $this->problemIdMap['abc2id'][$apid];
        $language = intval(input('language'));
        if (!array_key_exists($language, $this->allowLanguage))
            $this->error('The submitted language is not allowed for this contest');
        $source = input('source');
        $now = time();

        if ($this->contestStatus == -1)
            $this->error('Contest Not started');
        if ($this->contestStatus == 2)
            $this->error('Contest Ended');
        $problem = db('problem')->where(['problem_id' => $problem_id])->find();
        if (!$problem) {
            //题目不存在
            $this->error('No such problem!');
        }
        $user_id = $this->contest_user;
        $code_length = strlen($source);
        if ($code_length < 6) {
            $this->error('Code too short.');
        } else if ($code_length > 65536) {
            $this->error('Code too long.');
        }
        $solution_id = db('solution')->insertGetId([
            'problem_id' => $problem_id,
            'user_id'    => $this->SolutionUser($user_id, true),
            'in_date'    => date('Y-m-d H:i:s'),
            'language'   => $language,
            'ip'         => request()->ip(),
            'code_length' => $code_length,
            'contest_id' => $this->contest['contest_id']
        ]);
        db('source_code')->insert([
            'solution_id' => $solution_id,
            'source'      => $source
        ]);
        db('source_code_user')->insert([
            'solution_id' => $solution_id,
            'source'      => $source
        ]);
        session('lastsubmit', time());
        //更新schoolist的cache
        //        $userinfo = db('users')->where('user_id', $user_id)->field('school')->find();
        //        if($userinfo != null)
        //        {
        //            $tmpSchool = $userinfo['school'] == null ? '' : trim($userinfo['school']);
        //            if(strlen($tmpSchool) > 0 && $tmpSchool != '-')
        //            {
        //                $updateSchool = [substr(md5($tmpSchool), 0, 8) => $tmpSchool];
        //                $this->ContestSchoolListFromCache($updateSchool);
        //            }
        //        }
        $this->success(
            'Submit successful! <br/>Redirecting to Status.',
            '',
            ['solution_id' => $solution_id, 'user_id' => $user_id, 'contest_id' => $this->contest['contest_id']]
        );
    }
    public function description_md_ajax() {
        if (!$this->IsContestAdmin('admin')) {
            $this->error('Permission denied to get contest description markdown contents');
        }
        $contest_md = db('contest_md')->where('contest_id', $this->contest['contest_id'])->find();
        if ($contest_md)
            $description = $contest_md['description'];
        else
            $description = $this->contest['description'];
        $this->success('OK', null, $description);
    }
    public function description_change_ajax() {
        if (!$this->IsContestAdmin('admin')) {
            $this->error('Permission denied to change contest description markdown contents');
        }
        $description_md = input('description_md/s', '');
        if (strlen($description_md) > 16384)
            $this->error('Announcement too long');
        db('contest_md')->where('contest_id', $this->contest['contest_id'])->setField('description', $description_md);
        $description = ParseMarkdown($description_md);
        db('contest')->where('contest_id', $this->contest['contest_id'])->setField('description', $description);
        $this->success('Announcement updated', null, $description);
    }
    /**************************************************/
    // Problem Data
    /**************************************************/
    public function GetProblem($apid)
    {
        // 此处检查是否允许下载数据
        if (!$this->ALLOW_TEST_DOWNLOAD && !IsAdmin())
            $this->error("No permission to see test data.");
        $problem_id = $this->problemIdMap['abc2id'][$apid];

        $problem = db('contest_problem')->alias('cp')
            ->join('problem p', 'p.problem_id = cp.problem_id', 'left')
            ->where('cp.contest_id', $this->contest['contest_id'])
            ->where(['p.problem_id' => $problem_id])
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.spj spj',
                'p.time_limit time_limit',
                'p.memory_limit memory_limit',
                'cp.num num',
                'cp.pscore pscore'
            ])
            ->cache(10)
            ->find();
        if ($problem == null) {
            $this->error('No such problem.', null, '', 1);
        }
        $problem['problem_id_show'] = $apid;
        if ($this->contestStatus == 2 || $this->IsContestAdmin())
            $problem['show_real_id'] = true;
        $problem['pagetitle'] = $apid . ': ' . $problem['title'];
        return $problem;
    }
    public function testdata()
    {
        $apid = trim(input('get.pid'));
        $problem = $this->GetProblem($apid);
        $downloadWaitTime = config('CsgojConfig.OJ_TEST_DOWNLOAD_WAIT_TIME');
        $this->assign([
            'contest' => $this->contest,
            'problem' => $problem,
            'apid' => $problem['problem_id_show'],
            'downloadWaitTime' => $downloadWaitTime
        ]);
        return $this->fetch();
    }
    public function testdata_ajax()
    {
        $apid = trim(input('get.pid'));
        $problem = $this->GetProblem($apid);
        $ojPath = config('OjPath');
        $dataPath =  $ojPath['testdata'] . '/' . $problem['problem_id'];
        $filelist = GetDir($dataPath, ['in', 'out']);
        return $filelist;
    }
    public function testdata_download()
    {
        if ($this->OJ_STATUS != 'exp' || !$this->ALLOW_TEST_DOWNLOAD && !IsAdmin()) {
            $this->error("No permission to download data");
        }
        $downNum = session('?last_test_download_num') ? session('last_test_download_num') : 0;
        if (!IsAdmin()) {
            if (session('?last_test_download_time')) {
                $now = time();
                $downloadWaitTime = config('CsgojConfig.OJ_TEST_DOWNLOAD_WAIT_TIME');
                if ($downNum >= 2 && $now - session('last_test_download_time') < $downloadWaitTime) {
                    $this->error("Don't download test data too frequently.");
                }
                if ($now - session('last_test_download_time') > $downloadWaitTime) {
                    $downNum = 0;
                }
                $downNum++;
            }
        }
        $apid = trim(input('get.pid'));
        $problem = $this->GetProblem($apid);
        $filename = input('get.filename');

        if (!preg_match("/^[0-9a-zA-Z-_\\.]+$/i", $filename) || !in_array(pathinfo($filename, PATHINFO_EXTENSION), ['in', 'out'])) {
            // 防止传入目录级参数，非法读取父级目录
            $this->error("Not a valid filename.");
        }
        $ojPath = config('OjPath');
        $dataPath =  $ojPath['testdata'] . '/' . $problem['problem_id'];

        session('last_test_download_time', time());
        session('last_test_download_num', $downNum);
        downloads($dataPath, $filename, $problem['problem_id'] . '_' . $filename, 9);
    }
    /**************************************************/
    // Status
    /**************************************************/
    public function status()
    {
        $data = [
            'user_id'               => $this->contest_user,
            'resdetail_authority'   => $this->IsContestAdmin(),
            'search_problem_id'     => input('problem_id'),
            'search_user_id'        => input('user_id'),
            'search_solution_id'    => input('solution_id'),
            'single_status_url'     => 'single_status_ajax',
            'show_code_url'         => 'showcode_ajax',
            'show_res_url'          => 'resdetail_ajax',
            'search_result' => intval(input('result', -1)),
        ];
        $this->assign($data);
        return $this->fetch();
    }
    public function status_ajax()
    {
        $offset = intval(input('offset'));
        $limit = 20; //intval(input('limit'));
        $sort = 'solution_id';
        $order = 'desc';
        $solution_id_list = input('solution_id_list/a');   // 用于局部刷新status

        $apid = trim(input('problem_id'));
        $user_id = trim(input('user_id'));
        $solution_id = trim(input('solution_id'));
        $language = input('language/d');
        $result = input('result');
        $map = [];

        if ($apid != null && strlen($apid) > 0)
            $map['problem_id'] = array_key_exists($apid, $this->problemIdMap['abc2id']) ? $this->problemIdMap['abc2id'][$apid] : '';
        if ($user_id != null && strlen($user_id) > 0) {
            $map['user_id'] = $this->SolutionUser($user_id, true);
        }
        if ($solution_id != null && strlen($solution_id) > 0) {
            $map['solution_id'] = $solution_id;
        } else if ($solution_id_list != null) {
            $map['solution_id'] = ['in', array_slice($solution_id_list, 0, 25)];
        }

        if (array_key_exists($language, $this->allowLanguage))
            $map['language'] = $language;
        else {
            //筛选比赛允许的语言（比赛过程可能修改过语言列表）
            $map['language'] = ['in', array_keys($this->allowLanguage)];
        }
        if ($result != null && $result != -1) {
            $map['result'] = $result;
            // 如果已封榜，且搜索特定类型，则只返回封榜前内容
            if ($this->rankFrozen && ($user_id === null || $this->SolutionUser($user_id, true) != $this->contest_user)) {
                $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
                $map['in_date'] = ['lt', $closeRankTimeStr];
            }
        }
        $map['contest_id'] = $this->contest['contest_id'];
        $ret = [];
        $ordertype = [];
        if (strlen($sort) > 0) {
            $ordertype = [
                $sort => $order
            ];
        }
        $Solution = db('solution');
        if ($this->IsContestAdmin() || IsAdmin('source_browser')) {
            $similar = input('similar/d');
            if ($similar < 0)
                $similar = 0;
            else if ($similar > 100)
                $similar = 100;
            if ($similar > 0) {
                $solutionlist = $Solution->alias('sl')
                    ->join('sim si', 'si.s_id=sl.solution_id', 'left')
                    ->where('si.sim', 'not null')
                    ->where(['si.sim' => ['egt', $similar]])
                    ->where($map)
                    ->order($ordertype)
                    ->select();
            } else {
                $solutionlist = $Solution->alias('sl')
                    ->join('sim si', 'si.s_id=sl.solution_id', 'left')
                    ->where($map)
                    ->order($ordertype)
                    ->select();
            }
            // 手动处理 sim 数据，剔除自己与自己相似的情况。 纯SQL实现复杂且预估性能不佳
            $sim_id_list = [];
            foreach ($solutionlist as $val) {
                if ($val['sim'] != null) {
                    $sim_id_list[] = $val['sim_s_id'];
                }
            }
            $solB = db('solution')->where('solution_id', 'in', $sim_id_list)->field(['user_id', 'solution_id'])->select();
            $solBMap = [];
            foreach ($solB as $val) {
                $solBMap[$val['solution_id']] = $val['user_id'];
            }
            $solution_ret = [];
            $cnt = 0;
            foreach ($solutionlist as &$val) {
                if ($val['sim_s_id'] != null && array_key_exists($val['sim_s_id'], $solBMap) && $solBMap[$val['sim_s_id']] == $val['user_id']) {
                    // 自己与自己的代码相似
                    if ($similar > 0) {
                        continue;
                    } else {
                        $val['s_id'] = $val['sim_s_id'] = $val['sim'] = null;
                    }
                }
                if ($cnt >= $offset && $cnt < $offset + $limit) {
                    $solution_ret[] = $val;
                }
                $cnt++;
            }
            $ret['total'] = $cnt;
            $solutionlist = $solution_ret;
        } else {
            $solutionlist = $Solution
                ->where($map)
                ->order($ordertype)
                ->limit($offset, $limit)
                ->select();
        }
        foreach ($solutionlist as &$solution) {
            $solution['contest_type'] = $this->contest['private'] % 10;
            $solution['user_id'] = $this->SolutionUser($solution['user_id'], false);
            if ($this->contest_user != $solution['user_id'] && !$this->IsContestAdmin() && !IsAdmin('source_browser') && $this->contestStatus != 2) {
                //不是该用户，不是管理员，且比赛没结束，不可以查看别人的memory、time、code length
                $solution['memory'] = '-';
                $solution['time'] = '-';
                $solution['pass_rate'] = '-';
                $solution['code_length'] = '-';
            }
            $solution_id = $solution['solution_id'];
            if (!array_key_exists($solution['problem_id'], $this->problemIdMap['id2abc'])) {
                // 处理比赛开始后删除题目
                $solution['problem_id'] .= '(DEL)';
            } else {
                $solution['problem_id'] = $this->problemIdMap['id2abc'][$solution['problem_id']];
            }

            $solution['language'] = $this->allowLanguage[$solution['language']];
            $solution['code_show'] = $this->if_can_see_info($solution);

            if (strtotime($solution['in_date']) > $this->closeRankTime && $this->rankFrozen && $this->contest_user != $solution['user_id']) {
                //管理员的rankFrozen已经设置为false了
                $solution['result'] = '-';
                $solution['result_show'] = '-';
            } else {
                $this->GetResultShow($solution);
            }
        }
        if (!isset($ret['total'])) {
            $ret['total'] = $Solution->where($map)->count();
        }
        $ret['rows'] = $solutionlist;
        return $ret;
    }
    public function status_code_compare()
    {
        // 对比两个代码页面
        if (!$this->IsContestAdmin() && !IsAdmin('source_browser'))
            $this->error('Powerless');
        $sid = [];
        $sid[0] = input('sid0', '0');
        $sid[1] = input('sid1', '0');
        $code = [];
        $Source = db('source_code');
        for ($i = 0; $i < 2; $i++) {
            $code[$i] = $Source
                ->alias('so')
                ->join('solution sl', 'so.solution_id=sl.solution_id', 'left')
                ->where('so.solution_id', $sid[$i])
                ->field([
                    'so.solution_id solution_id',
                    'so.source source',
                    'sl.user_id user_id',
                    'sl.contest_id contest_id',
                    'sl.problem_id problem_id',
                ])
                ->find();

            if (!$code[$i]) {
                $this->error('Code ' . $sid[$i] . ' not exists');
            }
            // else{
            //     if((!isset($solution['contest_id']) || $solution['contest_id'] != $this->contest['contest_id']) && !IsAdmin('administrator') && !IsAdmin('source_browser'))
            //         $this->error('One of the codes is not in this contest ' . $sid[$i]);
            // }

            if (!isset($code[$i]['user_id']) || $code[$i]['user_id'] == null)
                $code[$i]['user_id'] = '-null-';
            $code[$i]['user_id'] = $this->SolutionUser($code[$i]['user_id']);
            $code[$i]['source'] = htmlentities(str_replace("\n\r", "\n", $code[$i]['source']), ENT_QUOTES, "utf-8");
        }
        $this->assign('code', $code);
        $this->assign('userInfoUrl', $this->UserInfoUrl('', $this->contest['contest_id'], true));
        return $this->fetch();
    }
    public function resdetail_ajax()
    {
        $data = [];
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        if (!$this->if_can_see_info($solution))
            $this->error('Permission denied to see this infomation.');
        if ($solution['result'] == 11) {
            //Compile Error
            $compileinfo = db('compileinfo')->where('solution_id', $solution_id)->find();
            $this->success(htmlentities(str_replace("\n\r", "\n", $compileinfo['error']), ENT_QUOTES, "utf-8"));
        } else if ((IsAdmin('source_browser') || $this->IsContestAdmin() || $this->ALLOW_WA_INFO) && in_array($solution['result'], [5, 6, 7, 8, 9, 10])) {
            // PE || WA || TLE || MLE || OLE 暂时只允许管理员查看
            $runtimeinfo = db('runtimeinfo')->where('solution_id', $solution_id)->find();
            $this->success(htmlentities(str_replace("\n\r", "\n", $runtimeinfo['error']), ENT_QUOTES, "utf-8"));
        } else {
            $this->error('No infomation.');
        }
        return $data;
    }
    public function showcode_ajax()
    {
        $data = [];
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        $solution['user_id'] = $this->SolutionUser($solution['user_id'], false);
        $oj_language = config('CsgojConfig.OJ_LANGUAGE');
        $oj_results = config('CsgojConfig.OJ_RESULTS');
        if (!$this->if_can_see_info($solution))
            $this->error('Permission denied to see this code.');

        $source = db('source_code_user')->where('solution_id', $solution_id)->find();
        $data['source'] = htmlentities(str_replace("\n\r", "\n", $source['source']), ENT_QUOTES, "utf-8");
        $data['auth'] = "\n/**********************************************************************\n" .
            "\tProblem: " . $solution['problem_id'] . "\n\tUser: " . $solution['user_id'] . "\n" .
            "\tLanguage: " . $oj_language[$solution['language']] . "\n\tResult: " . $oj_results[$solution['result']] . "\n";
        if ($solution['result'] == 4)
            $data['auth'] .= "\tTime:" . $solution['time'] . " ms\n" . "\tMemory:" . $solution['memory'] . " kb\n";
        $data['auth'] .= "**********************************************************************/\n\n";
        $this->success('', null, $data);
    }
    public function single_status_ajax()
    {
        //用于判题过程中通过ajax更新判题结果。
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->field(['solution_id', 'user_id', 'in_date', 'contest_id', 'result', 'memory', 'time', 'pass_rate'])->find();
        if ($solution == null) {
            $this->error('No such solution.');
            return;
        }
        if ($solution['contest_id'] == null || $solution['contest_id'] != $this->contest['contest_id'])
            $this->error('Not a submission of this contest');
        $solution['user_id'] = $this->SolutionUser($solution['user_id']);
        if (
            (!$this->contest_user || $this->contest_user != $solution['user_id']) &&
            !$this->IsContestAdmin() &&
            !IsAdmin('source_browser') &&
            $this->contestStatus != 2
        ) {
            //不是该用户，不是管理员，且比赛没结束，不可以查看别人的memory、time、code length
            $solution['memory'] = '-';
            $solution['time'] = '-';
            $solution['pass_rate'] = '-';
            $solution['code_length'] = '-';
        }
        if (strtotime($solution['in_date']) > $this->closeRankTime && $this->rankFrozen && $this->contest_user != $solution['user_id']) {
            //管理员的rankFrozen已经设置为false了
            $solution['result'] = '-';
            $solution['result_show'] = '-';
            $this->success('ok', null, $solution);
        }
        $this->GetResultShow($solution);

        $this->success('ok', null, $solution);
    }

    public function GetResultShow(&$solution)
    {
        $solution['res_show'] = false;
        $oj_results_html = config('CsgojConfig.OJ_RESULTS_HTML');
        // if_can_see_info 的前提下，【10 RE 或 11 CE】或者【5~9的结果且(为管理员或允许查看错误信息)】
        $solution['res_show'] = $this->if_can_see_info($solution) && ($solution['result'] == 11 || (in_array($solution['result'], [5, 6, 7, 8, 9, 10])  && ($this->IsContestAdmin() || $this->ALLOW_WA_INFO)));
        $result_style = array_key_exists($solution['result'], $oj_results_html) ? $solution['result'] : 100;
        $solution['res_color'] = $oj_results_html[$result_style][0];
        $solution['res_text'] = $oj_results_html[$result_style][1];
    }
    public function if_can_see_info($solution)
    {
        if (isset($solution) && $solution != null && isset($solution['user_id']))
            $solution['user_id'] = $this->SolutionUser($solution['user_id'], false);
        if (!isset($solution['contest_id']) || $solution['contest_id'] != $this->contest['contest_id'])
            return false;
        if (IsAdmin('source_browser') || $this->IsContestAdmin())
            return true;
        if (!$this->contest_user)
            return false;
        if ($this->contest_user == $solution['user_id'])
            return true;
        return false;
    }
    /**************************************************/
    //Ranklist
    /**************************************************/
    public function GetAwardRatio()
    {
        $award_ratio = $this->contest['award_ratio'];
        $ratio_gold = $award_ratio % 1000;
        $award_ratio /= 1000;
        $ratio_silver = $award_ratio % 1000;
        $award_ratio /= 1000;
        $ratio_bronze = $award_ratio % 1000;
        $award_ratio /= 1000;
        return [$ratio_gold, $ratio_silver, $ratio_bronze];
    }
    public function ranklist()
    {
        $award_ratio = $this->GetAwardRatio();
        //设置school筛选表数据
        $this->assign([
            'contest'       => $this->contest,
            'user_id'       => $this->contest_user,
            'ratio_gold'    => $award_ratio[0],
            'ratio_silver'  => $award_ratio[1],
            'ratio_bronze'  => $award_ratio[2],
            'use_cache'     => $this->rankUseCache
            //bootstrap-table默认适应屏幕，题目数量比较多时横向滚动条会在底部，而提交人数很多时，横向移动显示内容很不方便，所以通过后台给出前端强制设置的表格宽度来得到浏览器的横向滚动条
            //现已用jquery和scrollWidth解决
            //            'tablewidth'     => 420 + count($problemIdMap['problem_ids']) * 90,
        ]);
        return $this->fetch();
    }

    public function ranklist_ajax()
    {
        //使用rank的cache
        $cache_option = config('CsgojConfig.OJ_RANK_CACHE_OPTION');
        $cache_name = $this->OJ_MODE . '_rank' . $this->contest['contest_id'];
        $use_cache = $this->rankUseCache;
        if ($use_cache) {
            //非管理员则使用cache
            $ret = cache($cache_name, '', $cache_option);
            if ($ret) {
                return $ret['rows'];
            }
        }
        $data = $this->GetRankData();
        $firstBlood = &$data[0];
        $rankDataList = &$data[1];
        $retList = [];
        $rk_real = 0;
        $i = 0;
        $lastSolved = -1;
        $lastPenalty = -1;
        foreach ($rankDataList as $key => &$rankData) {
            if (!isset($rankData['userinfo'])) {
                // 没有 userinfo，不合法数据
                continue;
            }
            $star_team = $rankData['userinfo']['tkind'] == 2;
            if (!$star_team) {
                $rk_real++;
            }
            if ($rankData['solved'] != $lastSolved || $rankData['penalty'] != $lastPenalty) {
                $i = $rk_real;
            }
            if (!$star_team) {
                $lastPenalty = $rankData['penalty'];
                $lastSolved = $rankData['solved'];
            }
            $row = [
                'rank'      => $star_team ? "*" : $i,
                'nick'      => htmlspecialchars($rankData['userinfo']['nick']),    //要转换html标签，以防用户使用特殊标签做nick
                'tkind'     => intval($rankData['userinfo']['tkind']),
                'solved'    => $rankData['solved'],
                'penalty'   => $this->sec2str(-$rankData['penalty']), //前面用负数方便sort，此时反过来
                'school'    => htmlspecialchars($rankData['userinfo']['school']),    //要转换html标签，以防用户使用特殊标签做nick
                'room'      => array_key_exists('room', $rankData['userinfo']) ? $rankData['userinfo']['room'] : null
            ];
            if ($this->module == 'cpcsys') {
                $row['school'] = htmlspecialchars($rankData['userinfo']['school']);
                $row['tmember'] = htmlspecialchars($rankData['userinfo']['tmember']);
                $row['coach'] = htmlspecialchars($rankData['userinfo']['coach']);
            }
            // $row['user_id'] = "<a href='" . $this->UserInfoUrl($key, $this->contest['contest_id']) . "''>".$key."</a>";
            $row['user_id'] = $key;
            // 每道题的显示内容
            foreach ($this->problemIdMap['id2abc'] as $pid => $apid) {
                //pstatus 3为fb， 2 为ac， 1为没ac， 5为封榜后有尝试
                $problemstatus = 0;
                if (array_key_exists($pid, $firstBlood) && in_array($key, $firstBlood[$pid]['userlist'])) {
                    $problemstatus = 3;
                } else if (array_key_exists($pid, $rankData['ac_sec'])) {
                    $problemstatus = 2;
                } else if (array_key_exists($pid, $rankData['tr_num'])) {
                    $problemstatus = 5;
                } else if (array_key_exists($pid, $rankData['wa_num'])) {
                    $problemstatus = 1;
                }
                $row[$apid] = [
                    'ac'    => array_key_exists($pid, $rankData['ac_sec']) ? $rankData['ac_sec'][$pid] : null,
                    'wa'    => array_key_exists($pid, $rankData['wa_num']) ? $rankData['wa_num'][$pid] : null,
                    'tr'    => array_key_exists($pid, $rankData['tr_num']) ? $rankData['tr_num'][$pid] : null,
                    'pst'   => $problemstatus
                ];
            }
            $retList[] = $row;
        }
        $ret['rows'] = $retList;
        //设置rank的cache
        if ($use_cache) {
            cache($cache_name, $ret, $cache_option);
        }
        return $ret['rows'];
    }
    public function team_score_now_ajax() {
        // 获取队伍当前解题情况
        $team_id = input('team_id/s');
        if($team_id == null) {
            $team_id = $this->contest_user;
        }
        if($team_id == null) {
            $this->error("No team_id provided");
        }
        $rank = $this->ranklist_ajax();
        $award_ratio = $this->GetAwardRatio();
        $ret = [
            // 'problem_abc_list'  => $this->problemIdMap['num2abc'],
            'solved'        => 0,
            'tried'         => 0,
            'rank'          => '-',
            'ratio_gold'    => $award_ratio[0],
            'ratio_silver'  => $award_ratio[1],
            'ratio_bronze'  => $award_ratio[2],
            'pro_res'       => [],
            'star_team'     => false,
        ];
        $total_team_cnt = 0;
        $total_valid_cnt = 0;
        $rank_with_star = 0;
        $rank_virtual = 0;  // 无论打星还是正式队，计算一个在正式队排名中的排名
        $cnt_with_star = 0;
        $flg_find = false;
        $last = null;
        $recnt_formal_team = ['rank' => 0, 'solved' => 1000, 'penalty' => 0];
        foreach($rank as $item) {
            if($item['solved'] > 0) {
                $total_team_cnt++;
                if($item['tkind'] != 2) {
                    $total_valid_cnt++;
                }
            }
            if(!$flg_find) {
                if($last == null || $last['solved'] != $item['solved'] || $last['penalty'] != $item['penalty']) {
                    $cnt_with_star ++;
                }
                $last = $item;
                $rank_with_star = $cnt_with_star;
            }
            if($recnt_formal_team['solved'] != $item['solved'] || $recnt_formal_team['penalty'] != $item['penalty']) {
                $rank_virtual = $recnt_formal_team['rank'] + 1;
            } else {
                $rank_virtual = $recnt_formal_team['rank'];
            }
            if($item['tkind'] != 2) {
                $recnt_formal_team = $item;
            }
            if($item['user_id'] == $team_id) {
                $tried_count = 0;
                $ret['rank']     = $rank_virtual;
                if($item['tkind'] == 2) {
                    $ret['star_team'] = true;
                }
                $flg_find = true;
            }
        }
        // 获取真实的 solved、tried、pro_res
        $contestId = $this->contest['contest_id'];
        $userId = $this->SolutionUser($this->contest_user, true);
        
        // 构建查询
        $subQuery = db('solution')
            ->field('problem_id, MIN(result) as min_result')
            ->where([
                'result'     => ['egt', 4],
                'contest_id' => $contestId,
                'user_id'    => $userId
            ])
            ->group('problem_id')
            ->buildSql();
        
        $sol_pro_res = db()->table($subQuery . ' sub')
            ->field('problem_id, min_result as result')
            ->cache(30)
            ->select();
        $pro_status = [];
        foreach($sol_pro_res as $pro_res) {
            if(!array_key_exists($pro_res['problem_id'], $this->problemIdMap['id2abc'])) {
                continue;
            }
            $ret['solved'] += $pro_res['result'] == 4;
            $ret['tried'] += $pro_res['result'] != 4;
            $pro_status[$this->problemIdMap['id2abc'][$pro_res['problem_id']]] = $pro_res['result'] == 4 ? 2 : 1;
        }        
        foreach ($this->problemIdMap['num2abc'] as $pid_abc) {
            $ret['pro_res'][] = [
                'pid_abc'   =>  $pid_abc,
                'pst'       =>  array_key_exists($pid_abc, $pro_status) ? $pro_status[$pid_abc] : 0
            ];
        }
        $ret['total_team_cnt'] = $total_team_cnt;               // 总有效含打星队伍数
        $ret['total_valid_cnt'] = $total_valid_cnt;             // 总有效正式队伍数
        $ret['rank_with_star'] = $rank_with_star;               // 含打星的排名
        $ret['rank_frozen'] = $this->rankFrozen;
        $this->success("no team", null, $ret);
    }
    public function GetContestTeam($contest_list, $solution)
    {
        $team_map = array();
        foreach ($contest_list as $contest) {
            if (in_array($contest['private'], [2, 12])) {
                $team_list = db('cpc_team')->where(['contest_id' => $contest['contest_id'], 'privilege' => ['exp', Db::raw('is null')]])->field('password', true)->select();
            } else {
                $cuser = [];
                if ($solution == null) {
                    $solution = db('solution')->where(['contest_id' => $contest['contest_id'], 'result' => ['egt', 4]])->field(['solution_id', 'problem_id', 'user_id', 'in_date', 'result', 'contest_id'])->select();
                }
                foreach ($solution as $sol) {
                    $cuser[] = $sol['user_id'];
                }
                $cuser = array_unique($cuser);
                $team_list = db('users')->where('user_id', 'in', $cuser)->field(['user_id as team_id', 'school', 'nick as name', '0 as contest_id', '0 as tkind'])->select();
            }
            foreach ($team_list as $team) {
                $key = $team['team_id'] . '_' . $team['contest_id'];
                if (!isset($team_map[$key])) {
                    $team_map[$key] = $team;
                }
            }
        }
        return array_values($team_map);
    }
    public function contest_data_ajax()
    {
        // 新版rank使用
        $witout_solution = input('without_solution/d');
        $only_solution = input('only_solution/d');
        $min_solution_id = input('min_solution_id/d');
        $query_param = ($witout_solution == null ? '0' : $witout_solution) . '_' .
            ($only_solution == null ? '0' : $only_solution) . '_' .
            ($min_solution_id == null ? '0' : $min_solution_id) . '_' .
            $this->contest['contest_id'];

        $cache_option = config('CsgojConfig.OJ_RANKDYNAMIC_CACHE_OPTION');
        $cache_name = $this->OJ_MODE . '_drk_' . $query_param;
        // $use_cache = $this->rankUseCache;
        // if(!$this->isContestAdmin && !$this->IsContestAdmin('watcher')) {
        //     //非管理员则使用cache
        //     $contest_data = cache($cache_name, '', $cache_option);
        //     if($contest_data) {
        //         $this->success("ok", null, $contest_data);
        //         return;
        //     }
        // }
        $sol_map = [
            'contest_id' => $this->contest['contest_id'],
            // 'result' => ['egt', 4]
        ];
        if ($min_solution_id != null) {
            $sol_map['solution_id'] = ['gt', $min_solution_id];
        }
        $solution = $witout_solution ? null : db('solution')->where($sol_map)->field(['solution_id', 'problem_id', 'user_id', 'in_date', 'result', 'contest_id'])->order('solution_id', 'asc')->select();
        if ($only_solution) {
            $contest_data = [
                'solution'          => $solution
            ];
        } else {
            $contest_data = [
                'contest'           => $this->contest,
                'problem'           => db('contest_problem')->where('contest_id', $this->contest['contest_id'])->select(),
                'team'              => $this->GetContestTeam([$this->contest], $solution,),
                'solution'          => $solution
            ];
        }
        if ($this->rankFrozen) {
            $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
            foreach ($contest_data['solution'] as &$s) {
                if ($s['in_date'] > $closeRankTimeStr) {
                    $s['result'] = -1;
                }
            }
        }
        // if(!$this->isContestAdmin && !$this->IsContestAdmin('watcher')) {
        //     cache($cache_name, $contest_data, $cache_option);
        // }        
        $this->success("ok", null, $contest_data);
    }

    public function contest_data_joint_ajax()
    {
        // 多contest rank融合 （仅支持题目一致、起止时间一致的contest）
        $witout_solution = input('without_solution/d');
        $only_solution = input('only_solution/d');
        $min_solution_id = input('min_solution_id/d');
        $cid_list_str = input('cid_list/s');
        $cid_list = explode(',', $cid_list_str);
        if (count($cid_list) == 0) {
            $this->error("没有提供比赛ID");
        } else if (count($cid_list) > 5) {
            $this->error("比赛ID过多");
        }
        $cid_list_str = join(',', $cid_list);
        if (strlen($cid_list_str) > 50) {
            $this->error("比赛ID不合法");
        }
        $query_param = ($witout_solution == null ? '0' : $witout_solution) . '_' .
            ($only_solution == null ? '0' : $only_solution) . '_' .
            ($min_solution_id == null ? '0' : $min_solution_id) . '_' .
            $cid_list_str;

        $cache_option = config('CsgojConfig.OJ_RANKDYNAMIC_CACHE_OPTION');
        $cache_name = $this->OJ_MODE . '_drk_' . $query_param;
        if (!IsAdmin()) {
            //非管理员则使用cache
            $contest_data = cache($cache_name, '', $cache_option);
            if ($contest_data) {
                $this->success("ok", null, $contest_data);
                return;
            }
        }
        $contest_list = db('contest')->where('contest_id', 'in', $cid_list)->select();
        $contest_num = count($contest_list);
        if ($contest_num == 0) {
            $this->error("不存在的比赛");
        }
        $base_contest = $contest_list[0];
        for ($i = 1; $i < $contest_num; $i++) {
            $tmp_contest = $contest_list[$i];
            if ($base_contest['start_time'] != $tmp_contest['start_time'] || $base_contest['end_time'] != $tmp_contest['end_time']) {
                $this->error("存在时间不一致的比赛");
            }
        }
        if ($contest_list instanceof \think\Collection) {
            $contest_list = $contest_list->toArray();
        }
        if ($contest_num > 0) {
            $titles = array_map(function ($contest) {
                return $contest['title'];
            }, $contest_list);

            $commonPrefix = array_reduce($titles, function ($carry, $item) {
                return $carry === null ? $item : substr($item, 0, strspn($item ^ $carry, "\0"));
            });
            $commonPrefix = trim(preg_replace('/^[\p{P}]+|[\p{P}]+$/u', '', $commonPrefix));
            if ($commonPrefix == '') {
                $base_contest['title'] = '融合比赛：' . $cid_list_str;
            } else {
                $base_contest['title'] = $commonPrefix;
            }
        }
        $sol_map = [
            'contest_id' => ['in', $cid_list]
        ];
        if ($min_solution_id != null) {
            $sol_map['solution_id'] = ['gt', $min_solution_id];
        }
        $solution = $witout_solution ? null : db('solution')->where($sol_map)->field(['solution_id', 'problem_id', 'user_id', 'in_date', 'result', 'contest_id'])->order('solution_id', 'asc')->select();
        if ($only_solution) {
            $contest_data = [
                'solution'          => $solution
            ];
        } else {
            $contest_data = [
                'contest'           => $base_contest,
                'problem'           => db('contest_problem')->where('contest_id', $base_contest['contest_id'])->select(),
                'team'              => $this->GetContestTeam($contest_list, $solution),
                'solution'          => $solution
            ];
        }

        $now = time();
        // $start_time = strtotime($base_contest['start_time']);
        $end_time = strtotime($base_contest['end_time']);
        $closeRankTime = $end_time - ($base_contest['frozen_minute'] > 0 ? $base_contest['frozen_minute'] : 0) * 60;
        $frozen_end_time = $end_time + ($base_contest['frozen_after'] > 0 ? $base_contest['frozen_after'] : 0) * 60;
        $rankFrozen = false;
        if ($now > $closeRankTime && $now < $frozen_end_time) {  // 封榜时间段
            $rankFrozen = true;
        }
        if (IsAdmin()) {
            $rankFrozen = false;
        }

        if ($rankFrozen) {
            $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
            foreach ($contest_data['solution'] as &$s) {
                if ($s['in_date'] > $closeRankTimeStr) {
                    $s['result'] = -1;
                }
            }
        }
        if (!IsAdmin()) {
            cache($cache_name, $contest_data, $cache_option);
        }
        $this->success("ok", null, $contest_data);
    }
    /**************************************************/
    // OI mode 设置一个分数榜 scorerank ，不过并不是标准 OI 流程，只是用来教学考试打分
    public function scorerank()
    {
        //设置school筛选表数据
        $this->assign([
            'contest'           => $this->contest,
            'user_id'           => $this->contest_user,
            'problemIdMap'      => $this->problemIdMap,
            'use_cache'         => $this->rankUseCache
        ]);
        return $this->fetch();
    }
    public function scorerank_ajax()
    {
        // cache配置，除 cache_name 外其它同 ranklist,
        $cache_option = config('CsgojConfig.OJ_RANK_CACHE_OPTION');
        $cache_name = $this->OJ_MODE . '_scorerank' . $this->contest['contest_id'];
        if (!$this->IsContestAdmin()) {
            $ret = cache($cache_name, '', $cache_option);
            if ($ret) {
                return $ret['rows'];
            }
        }
        $data = $this->GetRankData();
        $firstBlood = &$data[0];
        $rankDataList = &$data[1];
        $problemCnt = count($this->problemIdMap['id2abc']);
        $realProblemCnt = round($this->contest['private'] / 10) == 0 ? $problemCnt : $problemCnt - 1;
        $retList = [];
        $i = 1;
        foreach ($rankDataList as $key => &$rankData) {
            $row = [
                'rank'        => "<span acforprize='" . $rankData['solved'] . "'>" . $i . "</span>",
                'nick'        => htmlspecialchars($rankData['userinfo']['nick']),
                'solved'      => $rankData['solved'],
                'penalty'     => $this->sec2str(-$rankData['penalty']),
                'school'        => htmlspecialchars($rankData['userinfo']['school']),
            ];
            if ($this->module == 'cpcsys') {
                $infoTitle = htmlspecialchars($rankData['userinfo']['tmember']) . ' @ ' . htmlspecialchars($rankData['userinfo']['school']);
                $row['school'] = htmlspecialchars($rankData['userinfo']['school']);
                $row['tmember'] = htmlspecialchars($rankData['userinfo']['tmember']);
            } else {
                $infoTitle = htmlspecialchars($rankData['userinfo']['nick']) . ' @ ' . htmlspecialchars($rankData['userinfo']['school']);
            }
            $row['user_id'] = "<a href='" . $this->UserInfoUrl($key, $this->contest['contest_id']) . "''>" . $key . "</a>";
            $row['score'] = 0;
            $lastProAlpha = $this->ContestProblemId($problemCnt - 1);
            foreach ($this->problemIdMap['id2abc'] as $pid => $apid) {
                if (array_key_exists($pid, $rankData['pass_score'])) {
                    $spscore = $rankData['pass_score'][$pid];
                    if ($spscore > 0.1) {
                        // 通过10%以上数据，认定 30 的逻辑分，通过比例按 70 分给分。
                        $spscore = $spscore * 0.7 + 0.3;
                    }
                    // 根据设置，如果有附加题，则最后一题作为附加题不计入总分
                    if ($apid != $lastProAlpha || round($this->contest['private'] / 10) == 0) {
                        // // $row['score'] += $spscore * 100;
                        // // 改为每道题独立算分
                        $row['score'] += $spscore * $this->problemIdMap['id2score'][$pid];
                    }
                    $row[$apid] = number_format($spscore * 100, 1);
                } else {
                    $row[$apid] = '';
                }
                $problemstatus = 0;
                if (array_key_exists($pid, $firstBlood) && in_array($key, $firstBlood[$pid]['userlist']))
                    $problemstatus = 3;
                else if (array_key_exists($pid, $rankData['ac_sec']))
                    $problemstatus = 2;
                else if (array_key_exists($pid, $rankData['wa_num']))
                    $problemstatus = 1;
                $row[$apid] = "<span pstatus=" . $problemstatus . ">" . $row[$apid] . "</span>";
            }
            $row['score'] = number_format($row['score'], 1);
            $retList[] = $row;
            $i++;
        }
        $ret['rows'] = $retList;
        if (!$this->IsContestAdmin()) {
            cache($cache_name, $ret, $cache_option);
        }
        return $ret['rows'];
    }
    /**************************************************/
    // 学校 rank ，可以设置各校前 x 名的队伍作为学校排名参考
    public function schoolrank()
    {
        $ojModeConfig = $this->OJ_MODE == 'cpcsys' ? 'CpcSysConfig' : 'CsgojConfig';
        $this->assign([
            'user_id'           => $this->contest_user,
            'schoolRankTeamNum' => $this->contest['topteam'] < 1 ? 1 : $this->contest['topteam'], // config($ojModeConfig . '.SCHOOL_RANK_TEAMNUM'),
            'use_cache'         => $this->rankUseCache
            //            'tablewidth'     => 420 + count($problemIdMap['problem_ids']) * 90,
        ]);
        return $this->fetch();
    }
    public function schoolrank_ajax()
    {
        //使用rank的cache
        $cache_option = config('CsgojConfig.OJ_RANK_CACHE_OPTION');
        $cache_name = $this->OJ_MODE . '_schoolrank' . $this->contest['contest_id'];
        if (!$this->IsContestAdmin()) {
            //非管理员则使用cache
            $retList = cache($cache_name, '', $cache_option);
            if ($retList) {
                return $retList;
            }
        }
        $schoolRankTeamNum = $this->contest['topteam'] < 1 ? 1 : $this->contest['topteam']; // config($ojModeConfig . '.SCHOOL_RANK_TEAMNUM');

        $data = $this->GetRankData(false);  // school rank 排除打星队，$with_star=false
        $firstBlood = &$data[0];
        $rankDataList = &$data[1];
        $schoolDataList = [];
        foreach ($rankDataList as $key => &$rankData) {
            if (!isset($rankData['userinfo']) || $rankData['userinfo']['nick'] != '' && $rankData['userinfo']['nick'][0] == '*') {
                // 没有 userinfo，不合法数据;
                // 队名开头是“*”，不计入学校排名
                continue;
            }
            //先去掉没有学校信息的
            if ($rankData['userinfo']['school'] == null || strlen(trim($rankData['userinfo']['school'])) == 0 || trim($rankData['userinfo']['school']) == '-') {
                continue;
                // $rankData['userinfo']['school'] = '-';
            }
            $rankData['userinfo']['school'] = strtoupper($rankData['userinfo']['school']);
            if (!array_key_exists($rankData['userinfo']['school'], $schoolDataList))
                $schoolDataList[$rankData['userinfo']['school']] = [
                    'solved'    => 0,     //AC题数
                    'penalty'   => 0,     //罚时（分钟, minutes）
                    'wa_num'    => [],     //在AC之前错了几次，AC之后的数据忽略
                    'ac_sec'    => [],     //第一次AC距离比赛开始时间（秒，seconds），之后的数据忽略
                    'tr_num'    => [],
                    'ac_num'    => [],     //AC队伍个数
                    'teamnum'   => 0,
                    'topteam'   => "<a href='" . $this->UserInfoUrl($rankData['userinfo']['user_id'], $this->contest['contest_id']) . "'>" . $rankData['userinfo']['user_id'] . "</a><br/>" . htmlspecialchars($rankData['userinfo']['nick']),
                ];
            $schoolData = &$schoolDataList[$rankData['userinfo']['school']];
            if ($schoolData['teamnum'] >= $schoolRankTeamNum)
                continue;
            foreach ($rankData['wa_num'] as $wakey => $wavalue) {
                if (!array_key_exists($wakey, $schoolData['wa_num']))
                    $schoolData['wa_num'][$wakey] = 0;
                $schoolData['wa_num'][$wakey] += $wavalue;
            }
            foreach ($rankData['ac_sec'] as $ackey => $acvalue) {
                if (!array_key_exists($ackey, $schoolData['ac_sec'])) {
                    $schoolData['ac_sec'][$ackey] = PHP_INT_MAX;
                    $schoolData['ac_num'][$ackey] = 0;
                }
                if ($acvalue < $schoolData['ac_sec'][$ackey])
                    $schoolData['ac_sec'][$ackey] = $acvalue;
                $schoolData['ac_num'][$ackey]++;
            }
            foreach ($rankData['tr_num'] as $trkey => $trvalue) {
                if (!array_key_exists($trkey, $schoolData['tr_num']))
                    $schoolData['tr_num'][$trkey] = 0;
                $schoolData['tr_num'][$trkey] += $trvalue;
            }
            $schoolData['penalty'] += $rankData['penalty'];
            $schoolData['solved'] += $rankData['solved'];
            $schoolData['teamnum']++;
        }
        arsort($schoolDataList);
        $retList = [];
        $i = 1;
        foreach ($schoolDataList as $key => &$schoolData) {
            $row = [
                'rank'        => "<span acforprize='" . $schoolData['solved'] . "'>" . $i . "</span>",
                'school'    => htmlspecialchars($key),
                'solved'    => $schoolData['solved'],
                'penalty'    => $this->sec2str(-$schoolData['penalty']), //前面用负数方便sort，此时反过来
                'topteam'    => $schoolData['topteam'],
            ];
            // 每道题的显示内容
            foreach ($this->problemIdMap['id2abc'] as $pid => $apid) {
                //pstatus 2为fb， 1 为ac， 0为没ac
                $problemstatus = 0;
                if (array_key_exists($pid, $schoolData['ac_sec']))
                    $problemstatus = 2;
                else if (array_key_exists($pid, $schoolData['tr_num']))
                    $problemstatus = 5;
                else if (array_key_exists($pid, $schoolData['wa_num']))
                    $problemstatus = 1;
                $row[$apid] = [
                    'ac'    => array_key_exists($pid, $schoolData['ac_sec']) ? $schoolData['ac_sec'][$pid] : null,
                    'acn'   => array_key_exists($pid, $schoolData['ac_num']) ? $schoolData['ac_num'][$pid] : null,
                    'wan'   => array_key_exists($pid, $schoolData['wa_num']) ? $schoolData['wa_num'][$pid] : null,
                    'trn'   => array_key_exists($pid, $schoolData['tr_num']) ? $schoolData['tr_num'][$pid] : null,
                    'pst'   => $problemstatus
                ];
            }
            $retList[] = $row;
            $i++;
        }
        //设置rank的cache
        if (!$this->IsContestAdmin()) {
            cache($cache_name, $retList, $cache_option);
        }
        return $retList;
    }
    public function RankUserList($map, $with_star = true)
    {
        return db('solution')->alias('s')
            ->join('users u', 'u.user_id = s.user_id', 'left')
            ->where($map)
            ->group('s.user_id,u.nick,u.school,u.email')
            ->field([
                's.user_id user_id',
                'u.nick nick',
                'u.school school',
                'u.email tmember',
                '"" coach',
                '0 tkind'
            ])
            ->select();
    }
    public function UserInfoUrl($user_id, $contest_id = 0, $prefix = false)
    {
        if ($prefix)
            return '/' . $this->module . '/user/userinfo?user_id=';
        else
            return '/' . $this->module . '/user/userinfo?user_id=' . $user_id;
    }
    protected function GetRankData($with_star = true)
    {
        $map = ['contest_id' => $this->contest['contest_id']];
        $Solution = db('solution');
        $solutionList = $Solution->where($map)->order('in_date', 'asc')->select();
        $rankDataList = [];
        //把所有solution整理为以user_id为键的一条条成绩信息
        // $fb_include_star = input('fb_include_star/d');  // 一血是否考虑打星队
        $fb_include_star = 0;  // 考虑到不同用户的缓存问题，暂时默认不考虑打星队，以后处理
        $firstBlood = [];

        // 先获取用户列表，Online版只需要nick，比赛里需要只计算比赛账号的rank，以免 fb 计算错误
        // 解释：对于比赛系统，生成账号交题后，重新生成账号去掉了已交题账号，避免这个交题记录被作为fb，造成rank实际用户fb无信息

        $userList = $this->RankUserList($map, $with_star);
        $userMap = [];
        foreach ($userList as $user) {
            $userMap[$user['user_id']] = $user;
        }
        // 获取solution信息计算rank数据
        $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
        $contestTotalTime = strtotime($this->contest['end_time']) - strtotime($this->contest['start_time']);
        foreach ($solutionList as $s) {
            $s['user_id'] = $this->SolutionUser($s['user_id'], false);
            if (!array_key_exists($s['problem_id'], $this->problemIdMap['id2abc']))
                continue;
            if (!array_key_exists($s['user_id'], $userMap))
                continue;
            if (!array_key_exists($s['user_id'], $rankDataList))
                $rankDataList[$s['user_id']] = [
                    //solved和penalty放在前两个，sort的时候就很方便不需要额外写comp函数了。
                    'solved'  => 0,         // AC题数
                    'penalty' => 0,         // 罚时（XCPC规则的总时长，秒）
                    'team_id' => $s['user_id'],
                    'pass_rate' => [],      // 各题 pass_rate，取最大值
                    'pass_score' => [],      // 如果不使用 pass_rate作为分数，而是增加额外算法，记录得分
                    'wa_num' => [],         // 在AC之前错了几次，AC之后的数据忽略
                    'ac_sec' => [],         // 第一次AC距离比赛开始时间（秒，seconds），之后的数据忽略
                    'tr_num' => [],         // 封榜后尝试次数
                ];
            $rankData = &$rankDataList[$s['user_id']];
            if (array_key_exists($s['problem_id'], $rankData['ac_sec']))
                continue;

            if ($s['result'] == 11) {
                // Compile Error 不罚时，计算rank时视为不存在
                continue;
            } else if ($this->rankFrozen == true && $s['in_date'] > $closeRankTimeStr || $s['result'] < 4) {    // 20241021 增加，result < 4 的正在评测的题也是 try 
                $rankData['tr_num'][$s['problem_id']] = array_key_exists($s['problem_id'], $rankData['tr_num']) ? $rankData['tr_num'][$s['problem_id']] + 1 : 1;
            } else if ($s['result'] == 4) {
                $rankData['ac_sec'][$s['problem_id']] = strtotime($s['in_date']) - strtotime($this->contest['start_time']);
                $rankData['solved']++;
                //用负数，sort的时候就很方便了。
                $rankData['penalty'] -= $rankData['ac_sec'][$s['problem_id']] + (array_key_exists($s['problem_id'], $rankData['wa_num']) ? (1200 * $rankData['wa_num'][$s['problem_id']]) : 0);

                if ($fb_include_star || !array_key_exists('tkind', $userMap[$s['user_id']]) || $userMap[$s['user_id']]['tkind'] != 2) {   // 只给正式队发一血
                    //添加first blood标记，多个人同一秒出题则都是fb
                    if (!array_key_exists($s['problem_id'], $firstBlood)) {
                        $firstBlood[$s['problem_id']] = [
                            'userlist' => [],
                            'time'    => $rankData['ac_sec'][$s['problem_id']]
                        ];
                    }
                    if ($firstBlood[$s['problem_id']]['time'] == $rankData['ac_sec'][$s['problem_id']]) {
                        $firstBlood[$s['problem_id']]['userlist'][] = $s['user_id'];
                    }
                }
            } else {
                $rankData['wa_num'][$s['problem_id']] = array_key_exists($s['problem_id'], $rankData['wa_num']) ? $rankData['wa_num'][$s['problem_id']] + 1 : 1;
            }
            if (!array_key_exists($s['problem_id'], $rankData['pass_rate']) || $s['pass_rate'] > $rankData['pass_rate'][$s['problem_id']]) {
                // 取最高的 pass_rate
                $rankData['pass_rate'][$s['problem_id']] = $s['pass_rate'];
            }
            // 用完成时间对 pass_rate 进行 decline 计算score
            $sPassTime = strtotime($s['in_date']) - strtotime($this->contest['start_time']);
            $s['pass_score'] = $s['pass_rate'] - 0.1 * $sPassTime / $contestTotalTime;
            if ($s['pass_score'] < 0) {
                $s['pass_score'] = 0;
            }
            if (!array_key_exists($s['problem_id'], $rankData['pass_score']) || $s['pass_score'] > $rankData['pass_score'][$s['problem_id']]) {
                // 取最高的 pass_score
                $rankData['pass_score'][$s['problem_id']] = $s['pass_score'];
            }
        }
        foreach ($userList as $user) {
            foreach ($user as $key => &$value) {
                if ($value == null || trim($value) == '') {
                    $value = '-';
                }
            }
            $user['school'] = strtoupper($user['school']);
            // 如果比赛开始后管理员删除了题目，这里也要避免只交了删除题目的用户进入榜单数据里，否则会因为缺少预处理部分
            // 的数据（比如'solved'等）而报错
            if (!array_key_exists($user['user_id'], $rankDataList))
                continue;
            $rankDataList[$user['user_id']]['userinfo'] = $user;
        }
        // #####################排序在此处，就一行。此时各时间数据还是秒的int格式#######################
        arsort($rankDataList);
        return [$firstBlood, $rankDataList];
    }
    public function sec2str($sec)
    {
        // 训练类比赛可能超过100小时，多于二位数了。
        if ($sec < 360000)
            $sec = sprintf("%02d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        else
            $sec = sprintf("%d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        return $sec;
    }
    public function statistics()
    {
        $this->assign([
            'ojRes'           => $this->ojResults,
            'ojLang'          => $this->ojLang,
            'useStatus'       => $this->allowResults,
            'useLanguage'     => $this->allowLanguage
        ]);
        $limitLanguage = [];
        foreach ($this->allowLanguage as $k => $val) {
            //滤除旧数据中新OJ不支持的语言
            if (array_key_exists($k, $this->ojLang))
                $limitLanguage[] = $k;
        }
        $map = [
            'contest_id' => $this->contest['contest_id'],
            'language'   => ['in', $limitLanguage]
        ];
        if ($this->rankFrozen) {
            //封榜
            $map['in_date'] = ['lt', date('Y-m-d H:i:s', $this->closeRankTime)];
        }
        $solutionList = db('solution')->where($map)->order('in_date', 'asc')->select();

        $lastLine = ['problem_id_show' => 'Total', 'total' => 0];
        foreach ($this->allowResults as $status) {
            $lastLine[$this->ojResults[$status]] = 0;
        }
        foreach ($this->allowLanguage as $language)
            $lastLine[$language] = 0;
        $problemStatistic = [];
        foreach ($this->problemIdMap['abc2id'] as $apid => $pid) {
            //初始化表格基本数据
            //$problemStatistic中，key为num的problem_id，'problem_id'=>值为ABCD的编号。不保存10xx格式的。
            $ps = &$problemStatistic[$this->problemIdMap['id2num'][$pid]];
            $ps = [
                'total' => 0,
                'problem_id_show' => $apid
            ];
            foreach ($this->allowResults as $status)
                $ps[$this->ojResults[$status]] = 0;
            foreach ($this->allowLanguage as $language)
                $ps[$language] = 0;
        }
        foreach ($solutionList as $s) {
            if (!array_key_exists($s['problem_id'], $this->problemIdMap['id2abc'])) {
                continue;
            }
            //不在考察范围内的状态和语言掠过
            if (!in_array($s['result'], $this->allowResults)) {
                continue;
            }
            if (!array_key_exists($s['language'], $this->allowLanguage)) {
                continue;
            }
            //这里的key用"num"，即contest里对problem的排序编号0123，用来正确排序ABCD...(比如题数大于26个，AA、AB的字符串排序就不一定靠谱了)
            $ps = &$problemStatistic[$this->problemIdMap['id2num'][$s['problem_id']]];
            $ps[$this->ojResults[$s['result']]]++;
            $ps[$this->ojLang[$s['language']]]++;

            $lastLine[$this->ojResults[$s['result']]]++;
            $lastLine[$this->ojLang[$s['language']]]++;

            $ps['total']++;
            $lastLine['total']++;
        }
        ksort($problemStatistic);
        $problemStatistic[] = $lastLine;
        $this->assign('problemStatistic', $problemStatistic);
        return $this->fetch();
    }

    public function FromLangMask($langmask)
    {
        $languages = [];
        foreach ($this->ojLang as $k => $la) {
            if (($langmask >> $k) & 1)
                $languages[$k] = $la;
        }
        ksort($languages);
        return $languages;
    }
    /**************************************************/
    //Clarification/Topic
    /**************************************************/
    // 只有管理员公开的topic才能所有人看到。
    // topic一旦被管理员改成public，则此topic禁止再被非管理员回复。
    // 因为学校政策禁止OJ交互式内容，所以不能直接public topic
    // reply正数表示被回复ID，负数表示被回复的次数
    public function TopicAuth() {
        if (!$this->contest_user)
            $this->error("Please login first", 'contest?cid=' . $this->contest['contest_id'], '', 1);
    }
    public function topic_detail()
    {
        $topic_id = input('topic_id/d');
        $Topic = db('contest_topic');
        $topic = $Topic->where(['topic_id' => $topic_id, 'contest_id' => $this->contest['contest_id']])
            ->where($this->topicDefaultMap)
            ->find();
        if (!$topic || $topic['reply'] > 0)
            $this->error("No such topic");
        $topic['user_id'] = $this->SolutionUser($topic['user_id'], false);
        if ($topic['public_show'] != 1 && $topic['user_id'] != $this->contest_user && !$this->IsContestAdmin('admin')) {
            $this->error("Permission denied to see this topic");
        }
        $replyList = $Topic->where(['contest_id' => $this->contest['contest_id'], 'reply' => $topic_id])
            ->where($this->topicDefaultMap)
            ->select();
        foreach ($replyList as $key => &$rep) {
            $rep['user_id'] = $this->SolutionUser($rep['user_id'], false);
        }
        $topic['problem_id'] = $this->DisplayTopicPid(isset($topic['problem_id']) ? $topic['problem_id'] : null);
        if ($topic['public_show'] == 1 && !$this->IsContestAdmin('admin')) {
            $this->assign('replyAvoid', true);
        }

        $this->assign(['topic' => $topic, 'replyList' => $replyList, 'userInfoUrlPrefix' => $this->UserInfoUrl('', $this->contest['contest_id'], true)]);

        return $this->fetch();
    }
    public function topic_reply_ajax()
    {
        $this->TopicAuth();
        if (!$this->running)
            $this->error("Contest is not running");
        $this->TopicSubmitDelay();
        $topic_id = input('topic_id/d');
        $Topic = db('contest_topic');
        $topic = $Topic->where(['topic_id' => $topic_id, 'contest_id' => $this->contest['contest_id']])
            ->where($this->topicDefaultMap)
            ->find();
        if (!$topic)
            $this->error("No such topic");
        $topicUserID = $this->SolutionUser($topic['user_id'], false);
        if ($topicUserID != $this->contest_user && !$this->IsContestAdmin('admin')) {
            $this->error("Permission denied to reply to this topic");
        }
        if ($topic['public_show'] == 1 && !$this->IsContestAdmin('admin')) {
            $this->error("This topic has been changed to public, reply is forbidden to avoid information change between teams.");
        }
        $topic_reply = [
            'content'       => trim(input('topic_content', '')),
            'user_id'       => $this->SolutionUser($this->contest_user, true),
            'reply'         => $topic_id,
            'public_show'   => 0,
            'contest_id'    => $this->contest['contest_id'],
            'in_date'       => date('Y-m-d H:i:s')
        ];
        if (strlen($topic_reply['content']) < 3)
            $this->error('Topic content too short.');
        if (strlen($topic_reply['content']) > 16384)
            $this->error('Topic content too long.');
        //        $Parsedown = new \Parsedown();
        //        $topic_reply['content'] = $Parsedown->text($topic_reply['content']);

        $topic_id = $Topic->insertGetId($topic_reply);
        $topic['reply']--; //负数表示回复数
        $Topic->update($topic);
        $this->success(
            "Topic submitted",
            null,
            [
                'contest_id'    => $this->contest['contest_id'],
                'topic_id'      => $topic_id,
                'content'       => nl2br(htmlspecialchars($topic_reply['content'])),
                'user_id'       => $this->SolutionUser($this->contest_user, false),
                'in_date'       => $topic_reply['in_date'],
                'module'        => $this->module,
            ]
        );
    }
    public function topic_del_ajax()
    {
        $this->TopicAuth();
        if (!$this->IsContestAdmin('admin')) {
            $this->error("Permission denied to delete this topic item");
        }
        $topic_id = input('topic_id/d');
        $Topic = db('contest_topic');
        $topic_reply = $Topic->where(['topic_id' => $topic_id])->find();
        if (!$topic_reply) {
            $this->error("No such topic");
        }
        // $Topic->where(['topic_id|reply' => $topic_id, 'contest_id' => $this->contest['contest_id']])->delete();
        // 改为标记 'public_show' => -1 作为删除，以便保持数据在档
        $Topic->where(['topic_id' => $topic_id, 'contest_id' => $this->contest['contest_id']])->update([
            'public_show' => -1
        ]);
        if ($topic_reply['reply'] > 0) {
            $topic = $Topic->where('topic_id', $topic_reply['reply'])->find();
            if ($topic) {
                $topic['reply']++; // 负数表示被回复个数
                $Topic->update($topic);
            }
        }
        $this->success("Topic " . $topic_id . " deleted");
    }
    public function topic_add()
    {
        $this->TopicAuth();
        if (!$this->running)
            $this->error("Contest is not running");
        $this->assign('abc2id', $this->problemIdMap['abc2id']);
        return $this->fetch();
    }
    public function TopicSubmitDelay()
    {
        if (!$this->IsContestAdmin('admin') && session('?last_topic_submit')) {
            $now = time();
            $submitWaitTime = config('CsgojConfig.OJ_TOPIC_WAIT_TIME');
            if ($now - session('last_topic_submit') < $submitWaitTime)
                $this->error("You should not submit topic more than twice in " . $submitWaitTime . " seconds. " . ($submitWaitTime - ($now - session('last_topic_submit'))) . " seconds left.");
        }
        session('last_topic_submit', time());
    }
    public function topic_add_ajax()
    {
        $this->TopicAuth();
        if (!$this->running)
            $this->error("Contest is not running");
        $this->TopicSubmitDelay();
        $topic_add = [
            'title'         => trim(input('topic_title', '')),
            'content'       => trim(input('topic_content', '')),
            'user_id'       => $this->SolutionUser($this->contest_user, true),
            'reply'         => 0,
            'public_show'   => 0,
            'contest_id'    => $this->contest['contest_id'],
            'in_date'       => date('Y-m-d H:i:s'),
            'problem_id'    => trim(input('apid')),
        ];
        if (strlen($topic_add['title']) > 72)
            $this->error('Topic title too long.');
        if (strlen($topic_add['title']) < 1)
            $this->error('Topic title too short.');
        if (strlen($topic_add['content']) > 16384)
            $this->error('Topic content too long.');
        //Markdown写原生html还是会破坏页面结构，暂时取消
        //        $Parsedown = new \Parsedown();
        //        $topic_add['content'] = $Parsedown->text($topic_add['content']);
        if (array_key_exists($topic_add['problem_id'], $this->problemIdMap['abc2id']))
            $topic_add['problem_id'] = $this->problemIdMap['abc2id'][$topic_add['problem_id']];
        else
            $topic_add['problem_id'] = -1;
        $topic_id = db('contest_topic')->insertGetId($topic_add);
        $this->success("Topic submitted", null, ['contest_id' => $this->contest['contest_id'], 'topic_id' => $topic_id]);
    }
    public function topic_change_status_ajax()
    {
        $this->TopicAuth();
        if (!$this->IsContestAdmin('admin')) {
            $this->error("Permission denied");
        }
        $topic_id = input('topic_id/d');
        $Topic = db('contest_topic');
        $topic = $Topic->where(['topic_id' => $topic_id, 'contest_id' => $this->contest['contest_id']])->find();
        if (!$topic || $topic['reply'] > 0) {
            $this->error("No such topic");
        }
        $topic['public_show'] = input('status/d') == 1 ? 1 : 0;
        $Topic->update($topic);
        $newStatus = $topic['public_show'] == 1 ? 'Public' : 'Private';
        $this->success("Topic " . $topic['topic_id'] . " status changed to " . $newStatus, null, ['status' => $topic['public_show'], 'statusstr' => $newStatus]);
    }
    public function topic_list()
    {
        $this->TopicAuth();
        $this->assign('abc2id', $this->problemIdMap['abc2id']);
        $this->assign('action', strtolower($this->request->action()));
        return $this->fetch();
    }
    public function topic_num_ajax() {
        if(!$this->IsContestAdmin('admin')) {
            $this->error("Permission denied");
        }
        $result = db('contest_topic')
            ->where([
                'contest_id' => $this->contest['contest_id'],
                'reply' => ['elt', 0]
            ])
            ->where($this->topicDefaultMap)
            ->field('COUNT(*) as count, SUM(reply) as reply_sum')
            ->find();
        
        $result['reply_sum'] = $result['reply_sum'] ?? 0;
        return $this->success('ok', null, $result);
    }
    public function topic_list_ajax()
    {
        $this->TopicAuth();
        $offset     = intval(input('offset'));
        $limit      = intval(input('limit'));
        $sort       = trim(input('sort', 'topic_id'));
        $fields     = ['topic_id', 'user_id', 'title', 'public_show', 'contest_id', 'in_date', 'problem_id', 'reply'];
        $sort       = validate_item_range($sort, $fields);
        $order      = input('order', 'desc');
        $search     = trim(input('search/s', ''));

        $map = ['reply' => ['elt', 0]];

        $apid       = trim(input('apid'));
        $user_id    = trim(input('user_id'));
        $topic_id   = trim(input('topic_id/d'));
        $title      = trim(input('title'));
        if ($apid != null && $apid != -1) {
            $map['problem_id'] = array_key_exists($apid, $this->problemIdMap['abc2id']) ? $this->problemIdMap['abc2id'][$apid] : '';
        }
        if ($user_id != null && strlen($user_id) > 0)
            $map['user_id'] = $this->SolutionUser($user_id, true);
        if ($topic_id != null && $topic_id > 0)
            $map['topic_id'] = $topic_id;
        if ($title != null && strlen($title) > 0)
            $map['title'] = ['like', '%' . $title . '%'];
        $map['contest_id'] = $this->contest['contest_id'];

        $ret = [];
        $ordertype = [];
        if (strlen($sort) > 0) {
            $ordertype[$sort] = $order;
        }
        $Topic = db('contest_topic');
        if (!$this->IsContestAdmin('admin')) {
            $orMap = [
                'user_id'         => $this->SolutionUser($this->contest_user, true),
                'public_show'     => 1,
            ];

            $list = $Topic
                ->where($map)
                ->where($this->topicDefaultMap) // 过滤删除标记的topic
                ->where(function ($query) use ($orMap) {
                    $query->whereOr($orMap);
                })
                ->field($fields)
                ->order($ordertype)
                ->limit($offset, $limit)
                ->select();
        } else {
            $list = $Topic
                ->where($map)
                ->field($fields)
                ->order($ordertype)
                ->limit($offset, $limit)
                ->select();
        }

        foreach ($list as &$item) {
            $item['user_id'] = $this->SolutionUser($item['user_id'], false);
            $item['title'] = "<a href='/" . $this->request->module() . "/contest/topic_detail?topic_id=" . $item['topic_id'] . "&cid=" . $item['contest_id'] . "'>" . $item['title'] . "</a>";
            $item['user_id'] = "<a href='" . $this->UserInfoUrl($item['user_id'], $this->contest['contest_id']) . "'>" . $item['user_id'] . "</a>";

            if ($this->IsContestAdmin('admin')) {
                $flg_public_show = $item['public_show'];
                $item['public_show'] = "<button type='button' field='public_show' topic_id='" . $item['topic_id'] . "' class='change_status btn ";
                if($flg_public_show == 0) {
                    $item['public_show'] .= "btn-warning' status='0' >Private";
                } else if($flg_public_show == -1) {
                    $item['public_show'] .= "btn-default' status='-1' >Deleted";
                } else {
                    $item['public_show'] .= "btn-success' status='1' >Public";
                }
                $item['public_show'] .= "</button>";
            }
            $item['problem_id'] = $this->DisplayTopicPid(isset($item['problem_id']) ? $item['problem_id'] : null);
            $item['reply'] = -$item['reply']; // 负数表示被回复次数
        }
        $ret['total'] = $Topic->where($map)->count();
        $ret['rows'] = $list;
        return $ret;
    }
    public function DisplayTopicPid($topicPid)
    {
        $pid = $topicPid == null ? -1 : $topicPid;
        if (array_key_exists($pid, $this->problemIdMap['id2abc']))
            $apid = $this->problemIdMap['id2abc'][$pid];
        else
            $apid = $pid == -1 ? "All" : strval($pid); // 如果题目被移出这个比赛，至少这个信息能提示管理员问题所在
        $retPid = $apid;
        if ($this->IsContestAdmin())
            $display_pid = $apid . "(" . $pid . ")";
        else
            $display_pid = $apid;
        if (array_key_exists($pid, $this->problemIdMap['id2abc']))
            $retPid = "<a href='problem?cid=" . $this->contest['contest_id'] . "&pid=" . $apid . "'>" . $display_pid . "</a>";
        return $retPid;
    }

    /**************************************************/
    // Contest All Export
    /**************************************************/
    // 完整导出 contest 相关的所有信息
    protected function AC($res)
    {
        return !isset($res) || $res == null ? ' ' : $this->sec2str($res);
    }
    protected function TR($res)
    {
        return !isset($res) || $res == null ? '' : '? ' . $res;
    }
    protected function WA($res)
    {
        return !isset($res) || $res == null ? ' ' : '(- ' . $res . ')';
    }
    protected function FormatterRankPro($value)
    {
        return $this->AC($value['ac']) . $this->TR($value['tr']) . '<br/>' . $this->WA($value['wa']) . '</span>';
    }
    public function contest_export()
    {
        if (!$this->IsContestAdmin()) {
            $this->error("You are not administrator!");
        }
        $ret_content = "# " . $this->contest['contest_id'] . ": " . $this->contest['title'] . " 数据归档\n\n";
        // ********************
        // Problem Description
        $problem_list_export = [];
        foreach ($this->problemIdMap['id2abc'] as $key => $val) {
            $problem_list_export[] = intval($key);
        }
        $problem_list_export = array_unique($problem_list_export);
        if (count($problem_list_export) == 0)
            $this->error("Cannot find problems for contest " . $this->contest['contest_id']);
        $whereMap = [
            "p.problem_id" => ['in', $problem_list_export]
        ];
        $orderMap = new Expression("field(p.problem_id," . implode(",", $problem_list_export) . ")");

        $Problem = db('problem');
        $problemList = $Problem->alias('p')
            ->join('problem_md pmd', 'p.problem_id = pmd.problem_id', 'left')
            ->where($whereMap)
            ->order($orderMap)
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.sample_input sample_input',
                'p.sample_output sample_output',
                'p.spj spj',
                'p.time_limit time_limit',
                'p.memory_limit memory_limit',
                'pmd.description description_md',
                'pmd.input input_md',
                'pmd.output output_md',
                'pmd.hint hint_md',
                'pmd.source source_md',
                'pmd.author author_md',
            ])
            ->select();
        $ret_content .= "## 题目\n\n";
        foreach ($problemList as $pro) {
            $ret_content .= "### " . $this->problemIdMap['id2abc'][$pro['problem_id']] . "(" . $pro['problem_id'] . ")：" . $pro['title'] . "\n\n";
            $ret_content .= "> Time Limit: " . $pro['time_limit'] . "s    \t Memory Limit: " . $pro['memory_limit'] . "MB    \t Special Judge: " . ($pro['spj'] == '0' ? "False" : "True") . "\n\n";
            $ret_content .= $pro['description_md'] . "\n\n";
            $ret_content .= "#### Input\n\n" . $pro['input_md'] . "\n\n";
            $ret_content .= "#### Output\n\n" . $pro['output_md'] . "\n\n";
            $ret_content .= "#### Sample Input\n\n````txt\n" . $pro['sample_input'] . "\n````\n\n";
            $ret_content .= "#### Sample Output\n\n````txt\n" . $pro['sample_output'] . "\n````\n\n";
            if (strlen(trim($pro['hint_md'])) > 0)
                $ret_content .= "#### Hint\n\n" . $pro['hint_md'] . "\n\n";
            if (strlen(trim($pro['source_md'])) > 0)
                $ret_content .= "#### Source\n\n" . $pro['source_md'] . "\n\n";
            if (strlen(trim($pro['author_md'])) > 0)
                $ret_content .= "#### Author\n\n" . $pro['author_md'] . "\n\n";
        }
        // ********************
        // Rank
        $ret_content .= "## 排名\n\n";
        $rank = $this->ranklist_ajax();
        $ret_content .= "| Rank | User ID | Nick | School | Member | Solved | Penalty | ";
        foreach ($this->problemIdMap['id2abc'] as $key => $val) {
            $ret_content .= $val . "     | ";
        }
        $ret_content .= "\n|";
        for ($i = 0; $i < 7; $i++) {
            $ret_content .= ":---------|";
        }
        foreach ($this->problemIdMap['id2abc'] as $key => $val) {
            $ret_content .= ":-----|";
        }
        $ret_content .= "\n";
        $userRec = [];
        foreach ($rank as $item) {
            $userRec[$item['user_id']] = [
                'user_id' => $item['user_id'],
                'nick' => $item['nick'],
                'tmember' => array_key_exists('tmember', $item) ? $item['tmember'] : '',
                'school' => $item['school']
            ];
            $ret_content .= "| " . $item['rank'] . " | " . $item['user_id'] . " | " . $item['nick'] . " | " . $item['school'] . " | " .
                (array_key_exists('tmember', $item) ? $item['tmember'] : "") . " | " . $item['solved'] . " | " . $item['penalty'] . " | ";

            foreach ($this->problemIdMap['id2abc'] as $key => $val) {
                $ret_content .= (array_key_exists($val, $item) ? $this->FormatterRankPro($item[$val]) : "") . " | ";
            }
            $ret_content .= "\n";
        }
        // ********************
        // Solution
        $ret_content .= "\n## 源代码\n\n";

        $Solution = db('solution');
        $solutionList = $Solution->alias('s')
            ->join('source_code sc', 's.solution_id = sc.solution_id', 'left')
            ->where(['s.contest_id' => $this->contest['contest_id']])
            ->order(['s.solution_id' => 'ASC'])
            ->field([
                's.solution_id solution_id',
                's.problem_id problem_id',
                's.user_id user_id',
                's.time time',
                's.memory memory',
                's.result result',
                's.language language',
                's.code_length code_length',
                's.pass_rate pass_rate',
                'sc.source source'
            ])
            ->select();

        foreach ($solutionList as $sol) {
            $sol['user_id'] = $this->SolutionUser($sol['user_id'], false);
            $ret_content .= "### " . $sol['solution_id'] . ": pro[" . $sol['problem_id'] . "] user[" . $sol['user_id'] . "][" .
                (array_key_exists($sol['user_id'], $userRec) ? $userRec[$sol['user_id']]['nick'] .
                    (array_key_exists('tmember', $userRec[$sol['user_id']]) && $userRec[$sol['user_id']]['tmember'] != '' ? "." . $userRec[$sol['user_id']]['tmember'] : '') : "") .
                "] result[" . $this->ojResults[$sol['result']] . "]\n\n";

            if (array_key_exists($sol['language'], $this->ojLang))
                $language = $this->ojLang[$sol['language']];
            else
                $language = "Unknown";
            $solContent = "````" . ($language == "Unknown" ? "" : $language);
            $solContent .= "\n/**********************************************************************\n" .
                "\tProblem: " . $sol['problem_id'] . "\n\tUser: " . $sol['user_id'] . "\n" .
                "\tLanguage: " . $language . "\n\tResult: " . $this->ojResults[$sol['result']] . "\n";
            if ($sol['result'] == 4)
                $solContent .= "\tTime:" . $sol['time'] . " ms\n" . "\tMemory:" . $sol['memory'] . " kb\n";
            $solContent .= "**********************************************************************/\n\n";
            $solContent .= str_replace("\n\r", "\n", $sol['source']);

            // $solContent .= htmlentities(str_replace("\n\r","\n", $sol['source']),ENT_QUOTES,"utf-8");
            $solContent .= "\n````\n\n";
            $ret_content .= $solContent;
        }
        if (Request::instance()->isGet()) {
            echo "$ret_content";
        } else {
            $this->success("ok", null, $ret_content);
        }
    }
    public function contest_problem_description_export()
    {
        if (!$this->isContestAdmin) {
            $this->error("You are not administrator!");
        }
        // ********************
        // Problem Description
        $problem_list_export = [];
        foreach ($this->problemIdMap['id2abc'] as $key => $val) {
            $problem_list_export[] = intval($key);
        }
        $problem_list_export = array_unique($problem_list_export);
        if (count($problem_list_export) == 0)
            $this->error("Cannot find problems for contest " . $this->contest['contest_id']);
        $whereMap = [
            "p.problem_id" => ['in', $problem_list_export]
        ];
        $orderMap = new Expression("field(p.problem_id," . implode(",", $problem_list_export) . ")");
        $with_author = input('with_author', 0);
        $with_source = input('with_source', 0);
        $Problem = db('problem');
        return [
            'contest'       => $this->contest,
            'problem_list'  => $Problem->alias('p')
                ->join('problem_md pmd', 'p.problem_id = pmd.problem_id', 'left')
                ->where($whereMap)
                ->order($orderMap)
                ->field([
                    'p.problem_id problem_id',
                    'p.title title',
                    'p.sample_input sample_input',
                    'p.sample_output sample_output',
                    'p.spj spj',
                    'p.time_limit time_limit',
                    'p.memory_limit memory_limit',
                    'pmd.description description_md',
                    'pmd.input input_md',
                    'pmd.output output_md',
                    'pmd.hint hint_md',
                    'pmd.source source_md',
                    'pmd.author author_md',

                    'p.description description',
                    'p.input input',
                    'p.output output',
                    'p.hint hint',
                    'p.source source',
                    'p.author author',
                ])
                ->select()
        ];
        // $pro_ret_all = [];
        // foreach($problemList as $pro){
        //     $ret_content = "# " . $this->problemIdMap['id2abc'][$pro['problem_id']] . ". " . $pro['title'] . "\n\n";
        //     $ret_content .= "> Time Limit: " . $pro['time_limit'] . "s    \t Memory Limit: " . $pro['memory_limit'] . "MB    \t Special Judge: " . ($pro['spj']=='0' ? "False" : "True") . "\n\n";            
        //     // $ret_content .= $pro['description_md'] . "\n\n";
        //     // $ret_content .= "## Input\n\n" . $pro['input_md'] . "\n\n";
        //     // $ret_content .= "## Output\n\n" . $pro['output_md'] . "\n\n";
        //     $ret_content .= $pro['description'] . "\n\n";
        //     $ret_content .= "## Input\n\n" . $pro['input'] . "\n\n";
        //     $ret_content .= "## Output\n\n" . $pro['output'] . "\n\n";

        //     $ret_content .= "## Sample Input\n\n````txt\n" . $pro['sample_input'] . "\n````\n\n";
        //     $ret_content .= "## Sample Output\n\n````txt\n" . $pro['sample_output'] . "\n````\n\n";
        //     if(strlen(trim($pro['hint'])) > 0) {
        //         // $ret_content .= "## Hint\n\n" . $pro['hint_md'] . "\n\n";
        //         $ret_content .= "## Hint\n\n" . $pro['hint'] . "\n\n";
        //     }
        //     if($with_source != 0 && strlen(trim($pro['source'])) > 0){
        //         // $ret_content .= "## Source\n\n" . $pro['source_md'] . "\n\n";   
        //         $ret_content .= "## Source\n\n" . $pro['source'] . "\n\n";
        //     }
        //     if($with_author != 0 && strlen(trim($pro['author'])) > 0) {
        //         // $ret_content .= "## Author\n\n" . $pro['author_md'] . "\n\n";
        //         $ret_content .= "## Author\n\n" . $pro['author'] . "\n\n";
        //     }
        //     $pro_ret_all[] = $ret_content;
        // }
        // if (Request::instance()->isGet()) {
        //     echo implode("\n\n", $pro_ret_all);;
        // } else {
        //     $this->success("ok", null, $pro_ret_all);
        // }
    }
    public function msg() {
        return $this->fetch();
    }
    public function msg_list_ajax() {
        return db('contest_msg')->where(['contest_id' => $this->contest['contest_id'], 'defunct' => 0])->cache(10)->select();
    }
}
