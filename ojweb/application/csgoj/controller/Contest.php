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
    var $frozenEndTime;                 // 封榜结束时间戳（int）
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
    var $contest_user_dbfull;           // 比赛内用户在 solution/topic 等表中的实际字符串
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
        $this->outsideContestAction = ['index', 'contest_list_ajax'];
        $this->allowPublicVisitAction = ['rank', 'ranklist_ajax', 'scorerank', 'scorerank_ajax', 'schoolrank', 'schoolrank_ajax', 'contest', 'contest_auth_ajax', 'team_auth_type_ajax', 'contest_data_ajax'];
        $this->ojLang = config('CsgojConfig.OJ_LANGUAGE');
        $this->ojResults = config('CsgojConfig.OJ_RESULTS');
        $this->ojResultsHtml = config('CsgojConfig.OJ_RESULTS_HTML');
        $this->allowLanguage = $this->ojLang;
        $this->running = false;
        $this->canJoin = false;
        $this->allowResults = [4, 5, 6, 7, 8, 9, 10, 11];
        $this->needAuth = false;
        $this->contest_user = null;
        $this->contest_user_dbfull = null;
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
            $this->contest_user_dbfull = $this->SolutionUser($this->contest_user, true);
            $this->assign('contest_user', $this->contest_user);
            $this->assign('login_teaminfo', session('login_user_info'));
        } else {
            $this->contest_user = null;
            $this->contest_user_dbfull = null;
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
        $this->closeRankTime = $end_time - ($contest['frozen_minute'] > 0 ? $contest['frozen_minute'] : 0) * 60;
        $this->frozenEndTime = $end_time + ($contest['frozen_after'] > 0 ? $contest['frozen_after'] : 0) * 60;
        $ret = -1;
        $this->rankFrozen = false;
        if ($now < $start_time)
            $ret = -1;
        else if ($now < $end_time)
            $ret = 1;
        else
            $ret = 2;
        if ($now > $this->closeRankTime && $now < $this->frozenEndTime) {  // 封榜时间段
            $this->rankFrozen = true;
        }
        if ($this->IsContestAdmin() || $this->IsContestAdmin('balloon_manager') || 
        $this->IsContestAdmin('balloon_sender') || $this->IsContestAdmin('admin') ) { //管理员、气球管理员、气球配送员 不封榜
            $this->rankFrozen = false;
        }
        // PS: 直播员 watcher 需要封榜
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
                'p.spj spj',
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
                'p.spj spj',
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
    protected function SolutionUser($user_id, $appearprefix = null)
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
        if (!$this->ALLOW_TEST_DOWNLOAD && !IsAdmin()) {
            $this->error("No permission to see test data.");
        }
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
            $code[$i]['source'] = htmlentities(str_replace("\r\n", "\n", $code[$i]['source']), ENT_QUOTES, "utf-8");
        }
        $this->assign('code', $code);
        $this->assign('userInfoUrl', $this->UserInfoUrl('', $this->contest['contest_id'], true));
        return $this->fetch();
    }
    public function resdetail_ajax(){
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        $flg_can_see_special = IsAdmin('source_browser') || $this->IsContestAdmin() || $this->ALLOW_WA_INFO;
        if ($this->if_can_see_info($solution)) {
            if($solution['result'] == 11) {
                // Compile Error
                $table_name = 'compileinfo';
            }
            else if(in_array($solution['result'], [5, 6, 7, 8, 9, 10, 90]) && $flg_can_see_special) {
                // PE || WA || TLE || MLE || OLE || RE 暂时只允许管理员查看
                $table_name = 'runtimeinfo';
            }
            else {
                $this->error('No infomation.');
            }
            
            $info_item = db($table_name)->where('solution_id', $solution_id)->find();
            $info = $info_item ? $info_item['error'] : '';
            
            $data = Json2Array($info);
            if(!$data) {
                $data = ['data_type' => 'text', 'data' => $info];
            }
            $this->success('', null, $data);
            
        } else {
            $this->error('Permission denied to see this infomation.');
        }
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
        $data['source'] = htmlentities(str_replace("\r\n", "\n", $source['source']), ENT_QUOTES, "utf-8");
        $data['result'] = array_key_exists($solution['result'], $oj_results) ? $oj_results[$solution['result']] : 'Unknown';
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
        $oj_results_short = config('CsgojConfig.OJ_RESULTS_SHORT');
        // if_can_see_info 的前提下，【10 RE 或 11 CE】或者【5~9的结果且(为管理员或允许查看错误信息)】
        $solution['res_show'] = $this->if_can_see_info($solution) && ($solution['result'] == 11 || (in_array($solution['result'], [5, 6, 7, 8, 9, 10, 90])  && ($this->IsContestAdmin() || $this->ALLOW_WA_INFO)));
        $result_style = array_key_exists($solution['result'], $oj_results_html) ? $solution['result'] : 100;
        $solution['res_color'] = $oj_results_html[$result_style][0];
        $solution['res_text'] = $oj_results_html[$result_style][1];
        $solution['res_short'] = array_key_exists($solution['result'], $oj_results_short) ? $oj_results_short[$solution['result']] : 'Unknown';
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
    public function rank() {
        return $this->fetch();
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
                    $solution = db('solution')->where(['contest_id' => $contest['contest_id'], 'result' => ['egt', 4]])->group('user_id')->field('user_id')->select();
                }
                foreach ($solution as $sol) {
                    // [
                    //     [
                    //         8230,                   // [0]solution_id
                    //         1002,                   // [1]contest_id
                    //         1001,                   // [2]problem_id
                    //         admin,                  // [3]user_id
                    //         0,                      // [4]tkind
                    //         "2025-10-30 22:53:06    // [5]in_date
                    //     ]
                    // ]
                    $cuser[] = $sol[3];
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
    protected function GetContestData4Rank($param=[]) {
        
        $info_need = $param['info_need'] ?? [];
        $min_solution_id = $param['min_solution_id'] ?? 0;
        $solution_result = $param['solution_result'] ?? -1;  // 查询特定结果类型

        if(!is_array($info_need) || count($info_need) == 0 || $info_need[0] == 'all') {
            $info_need = [
                'contest',
                'problem',
                'team', 
                'solution', 
                'contest_balloon'
            ];
        }
        sort($info_need);
        $query_param = implode('_', $info_need) . '_' . 
            ($min_solution_id == null ? '0' : $min_solution_id) . '_' .
            ($solution_result == null ? '0' : $solution_result) . '_' .
            $this->contest['contest_id'];

        $cache_option = config('CsgojConfig.OJ_RANKDYNAMIC_CACHE_OPTION');
        $cache_key = $this->OJ_MODE . '_drk_' . $query_param;
        $flg_use_cache = !$this->isContestAdmin && !$this->IsContestAdmin('watcher') && 
            !$this->IsContestAdmin('balloon_manager');
        if($flg_use_cache) {
            //非管理员则使用cache
            $contest_data = cache($cache_key, '', $cache_option);
            if($contest_data) {
                $contest_data['flg_cache'] = true;
                return $contest_data;
            }
        }
        // 参数验证和过滤
        $contest_id = intval($this->contest['contest_id']);
        $min_solution_id = $min_solution_id ? intval($min_solution_id) : 0;
        
        $contest_data = [];
        // ********************
        // solution
        if(in_array('solution', $info_need)) {
            $sol_map = [
                'contest_id' => $contest_id,
            ];
            if($solution_result >= 0) {
                $sol_map['result'] = $solution_result;
            }
            if ($min_solution_id > 0) {
                $sol_map['solution_id'] = ['egt', $min_solution_id];
            }
            $field = ['solution_id', 'contest_id', 'problem_id', 'user_id', 'result', 'in_date'];
            // 正常查询获取数据
            $solution_raw = db('solution')->where($sol_map)->field($field)->order('solution_id', 'asc')->select();
            
            // 转换为 list 格式
            // 字段顺序：[0]solution_id, [1]contest_id, [2]problem_id, [3]team_id（数据库是user_id）, [4]result, [5]in_date
            $solution = [];
            if ($solution_raw && is_array($solution_raw)) {
                foreach ($solution_raw as $item) {
                    $solution[] = [
                        $item['solution_id'],
                        $item['contest_id'],
                        $item['problem_id'],
                        $item['user_id'],
                        $item['result'],
                        $item['in_date']
                    ];
                }
            }
            if ($this->rankFrozen) {
                $closeRankTimeStr = date('Y-m-d H:i:s', $this->closeRankTime);
                foreach ($solution as &$s) {
                    if ($s[5] > $closeRankTimeStr) {
                        $s[4] = -1;
                    }
                }
            }
            $contest_data['solution'] = $solution;
        }
        // ********************
        // team
        if(in_array('team', $info_need)) {
            // 获取 team 信息
            $team_data = $this->GetContestTeam([$this->contest], $solution ?? []);
            
            // 将 team dict 处理为 list 格式
            // 字段顺序：[0]contest_id, [1]team_id, [2]name, [3]name_en, [4]coach, [5]tmember, [6]school, [7]region, [8]tkind, [9]room, [10]privilege, [11]team_global_code
            $team_list = [];
            foreach ($team_data as $team) {
                $team_list[] = [
                    $team['contest_id'] ?? '',
                    $team['team_id'] ?? '',
                    $team['name'] ?? '',
                    $team['name_en'] ?? '',
                    $team['coach'] ?? '',
                    $team['tmember'] ?? '',
                    $team['school'] ?? '',
                    $team['region'] ?? '',
                    $team['tkind'] ?? '',
                    $team['room'] ?? '',
                    $team['privilege'] ?? '',
                    $team['team_global_code'] ?? ''
                ];
            }
            $contest_data['team'] = $team_list;
        }
        // ********************
        // problem
        if(in_array('problem', $info_need)) {
            $problem_data = db('contest_problem')->where('contest_id', $this->contest['contest_id'])->field([
                "problem_id",
                "contest_id",
                "title as color",   // 使用 color 字段名
                "num",
            ])->select();
            $contest_data['problem'] = $problem_data;
        }
        // ********************
        // contest
        if(in_array('contest', $info_need)) {
            $contest_data['contest'] = $this->contest;
        }
        // ********************
        // contest_balloon
        if(in_array('contest_balloon', $info_need)) {
            $contest_balloon = db('contest_balloon')->where(['contest_id' => $contest_id])->select();
            $contest_data['contest_balloon'] = [];
            foreach($contest_balloon as $item) {
                $contest_data['contest_balloon'][] = [
                    $item['contest_id'],
                    $item['problem_id'],
                    $item['team_id'],
                    $item['ac_time'],   // int时间戳
                    $item['pst'],       // int 0普通 10 正式队一血 20 全局一血
                    $item['bst'],       // int 0未发 10已通知 20已分配 30已发放
                    $item['balloon_sender'], // string 气球配送员
                ];
            }
        }
        if($flg_use_cache) {
            cache($cache_key, $contest_data, $cache_option);
        }
        return $contest_data;

    }
    public function contest_data_ajax(){
        $info_need = input('info_need/a');
        $min_solution_id = input('min_solution_id/d');
        $solution_result = input('solution_result/d');  // 查询特定结果类型
        $contest_data = $this->GetContestData4Rank([
            'info_need' => $info_need,            
            'min_solution_id' => $min_solution_id,
            'solution_result' => $solution_result,
        ]);
        $this->success("ok", null, $contest_data);
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
    public function sec2str($sec)
    {
        // 训练类比赛可能超过100小时，多于二位数了。
        if ($sec < 360000)
            $sec = sprintf("%02d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        else
            $sec = sprintf("%d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        return $sec;
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
        if (!$this->contest_user && !$this->IsContestAdmin('admin'))
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
        
        // 使用统一的problem_id处理逻辑
        $topic = $this->ProcessTopicProblemId($topic);
        
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
                $this->error("回复过于频繁，请稍候。<br/>Reply too frequent, please wait.<br/>" . ($submitWaitTime - ($now - session('last_topic_submit'))) . " s");
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
        $this->success("Topic " . $topic['topic_id'] . " status changed", null, ['status' => $topic['public_show']]);
    }
    public function topic_list()
    {
        $this->TopicAuth();
        $this->assign('abc2id', $this->problemIdMap['abc2id']);
        $this->assign('action', strtolower($this->request->action()));
        return $this->fetch();
    }
    public function topic_num_ajax() {
        $this->TopicAuth();
        
        // 构建查询器
        $query = db('contest_topic')
            ->where([
                'contest_id' => $this->contest['contest_id'],
                'reply' => ['elt', 0]
            ])
            ->where($this->topicDefaultMap);
        
        // 如果不是管理员，添加额外的权限限制
        if (!$this->IsContestAdmin('admin')) {
            $query->where(function($query) {
                $query->where('user_id', '=', $this->contest_user_dbfull)
                      ->whereOr('public_show', '=', 1);
            });
        }
        
        $result = $query->field('COUNT(*) as count, SUM(reply) as reply_sum')->find();
        
        $result['reply_sum'] = $result['reply_sum'] ?? 0;
        return $this->success('ok', null, $result);
    }
    public function topic_list_ajax()
    {
        $this->TopicAuth();
        $sort       = trim(input('sort', 'topic_id'));
        $fields     = ['topic_id', 'user_id', 'title', 'public_show', 'contest_id', 'in_date', 'problem_id', 'reply'];
        $sort       = validate_item_range($sort, $fields);
        $order      = input('order', 'desc');

        $map = ['reply' => ['elt', 0]];
        $map['contest_id'] = $this->contest['contest_id'];

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
                ->select();
        } else {
            $list = $Topic
                ->where($map)
                ->field($fields)
                ->order($ordertype)
                ->select();
        }

        foreach ($list as &$item) {
            $item['user_id'] = $this->SolutionUser($item['user_id'], false);
            
            // 使用统一的problem_id处理逻辑
            $item = $this->ProcessTopicProblemId($item);
            
            $item['reply'] = -$item['reply']; // 负数表示被回复次数
            // 添加管理员标识，供前端formatter使用
            $item['is_admin'] = $this->IsContestAdmin('admin');
        }
        return $list;
    }
    /**
     * 处理topic的problem_id显示逻辑
     * @param array $topic 包含problem_id的topic数组
     * @return array 返回处理后的topic数组，包含problem_id和pid_abc字段
     */
    public function ProcessTopicProblemId($topic)
    {
        $realPid = isset($topic['problem_id']) ? $topic['problem_id'] : null;
        
        if ($realPid == null || $realPid == -1) {
            $topic['problem_id'] = ''; // 无权限情况下设为空字符串
            $topic['pid_abc'] = 'All';
        } else {
            // 根据权限决定是否显示真实ID
            if ($this->IsContestAdmin() && $realPid != -1) {
                $topic['problem_id'] = strval($realPid); // 保持真实ID
            } else {
                $topic['problem_id'] = ''; // 无权限情况下设为空字符串
            }
            
            // 设置字母ID
            if (array_key_exists($realPid, $this->problemIdMap['id2abc'])) {
                $topic['pid_abc'] = $this->problemIdMap['id2abc'][$realPid];
            } else {
                $topic['pid_abc'] = strval($realPid); // 如果题目被移出比赛，显示原ID
            }
        }
        
        return $topic;
    }

    /**
     * 兼容旧版本的DisplayTopicPid函数
     * @deprecated 请使用ProcessTopicProblemId替代
     */
    public function DisplayTopicPid($topicPid)
    {
        $topic = ['problem_id' => $topicPid];
        $processed = $this->ProcessTopicProblemId($topic);
        
        // 构建显示文本：如果有problem_id则显示括号，否则只显示字母ID
        $displayText = $processed['pid_abc'];
        if ($processed['problem_id'] && $processed['problem_id'] !== '') {
            $displayText = $processed['pid_abc'] . '(' . $processed['problem_id'] . ')';
        }
        
        return $displayText;
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
    
    public function contest2print() {
        if (!$this->isContestAdmin) {
            $this->error("You are not administrator!");
        }
        
        // 禁用布局（打印页面不需要布局）
        $this->view->engine->layout(false);
        
        // 获取题目列表（参考 problem() 方法和 contest_problem_description_export()）
        $problem_list_export = [];
        foreach ($this->problemIdMap['id2abc'] as $key => $val) {
            $problem_list_export[] = intval($key);
        }
        $problem_list_export = array_unique($problem_list_export);
        if (count($problem_list_export) == 0) {
            $this->error("Cannot find problems for contest " . $this->contest['contest_id']);
        }
        
        $whereMap = [
            "problem_id" => ['in', $problem_list_export]
        ];
        $orderMap = new Expression("field(problem_id," . implode(",", $problem_list_export) . ")");
        
        $Problem = db('problem');
        $problem_list = $Problem->where($whereMap)
            ->order($orderMap)
            ->select();
        
        // 处理题目数据，添加题号（A, B, C...）
        foreach ($problem_list as &$problem) {
            $problem_id = $problem['problem_id'];
            if (isset($this->problemIdMap['id2abc'][$problem_id])) {
                $problem['apid'] = $this->problemIdMap['id2abc'][$problem_id];
            }            
        }
        
        $this->assign([
            "pagetitle" => "Contest Problem Print",
            "contest" => $this->contest,
            "problem_list" => $problem_list
        ]);
        return $this->fetch();
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
    }
    public function msg() {
        return $this->fetch();
    }
    public function msg_list_ajax() {
        return db('contest_msg')->where(['contest_id' => $this->contest['contest_id'], 'defunct' => 0])->cache(10)->select();
    }
}
