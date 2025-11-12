<?php
namespace app\csgoj\controller;
use think\Controller;
use think\Db;
class Problemset extends Csgojbase
{

    public function _initialize()
    {
        $this->OJMode();
        $this->assign(['pagetitle' => 'Problem Set']);
    }
    public function index()
    {
        $this->assign([
            'prolist_mode' => 'frontend',
            'search_spj' => input('spj', -1),
            'table_prefix' => 'problemset',
            'table_id' => 'problemset_table',
            'ajax_url' => '/csgoj/problemset/problemset_ajax',
            'page_title' => '开放题目集',
            'page_title_en' => 'Public Problems',
            'search_placeholder' => '题号/标题/来源',
            'page_size' => 25,
            'cookie_expire' => '1m',
            'cookie_suffix' => session('?user_id') ? '-'.session('user_id') : '',
            'filter_selectors' => ['spj'],
            'custom_handlers' => 'initUrlSearch'
        ]);
        return $this->fetch();
    }
    public function problemset_ajax()
    {
        //暂时用get，不知道是bug还是设置不对，前端表格用post的ajax，后台获取不到数据
        $columns = ['problem_id', 'title', 'source', 'accepted', 'submit', 'spj'];
        $offset = intval(input('offset'));
        $limit  = intval(input('limit'));
        $sort   = trim(input('sort', ''));
        $sort   = validate_item_range($sort, ['problem_id', 'accepted', 'submit']);
        $order  = input('order');
        $search = trim(input('search/s'));
        
        // 新增筛选参数
        $spj_filter = input('spj', -1);

        $map = [];
        // 多个联合成一个条目的 or 关系('problem_id|title|source')与 and 关系('defunct')连用时候会自带括号，不需要加->query()
        if(strlen($search) > 0)
            $map = [
                'problem_id'   => $search,
                'title|source' => ['like', "%$search%"]
            ];
        $defunctmap = [];
        // // 管理员可以在后台看problem，没必要前台给特权，这里 if 注释掉
        // if(!IsAdmin())
        $defunctmap['defunct'] = '0';
        
        // 添加spj筛选
        if($spj_filter != -1) {
            $defunctmap['spj'] = $spj_filter;
        }
        $ret = [];
        $ordertype = [];
        if(strlen($sort) > 0)
        {
            $ordertype = [
                $sort => $order
            ];
        }
        $Problem = db('problem');

        if(strlen($search) > 0) {
            $problemList = $Problem
                ->field(implode(",", $columns))
                ->where(function($query)use ($map) {
                    $query->whereOr($map);
                })
                ->where($defunctmap)
                ->limit($offset, $limit)
                ->order($ordertype)
                ->select();
        }
        else {
            $problemList = $Problem
                ->field(implode(",", $columns))
                ->where($map)
                ->where($defunctmap)
                ->limit($offset, $limit)
                ->order($ordertype)
                ->select();
        }
        if(session('?user_id')) {
            $user_id = session('user_id');
            $solutionStatus = [];
            $Solution = db('solution');
            $solutionNormal = $Solution
                ->where(['user_id' => $user_id])
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->field('problem_id')
                ->group('problem_id')
                ->select();
            $solutionAc = $Solution
                ->where(['user_id' => $user_id, 'result' => 4])
                ->where(function ($query) {
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->field('problem_id')
                ->group('problem_id')
                ->select();
            foreach($solutionNormal as $res) {
                $solutionStatus[$res['problem_id']] = '0';
            }
            foreach($solutionAc as $res) {
                $solutionStatus[$res['problem_id']] = '1';
            }

            foreach($problemList as $key=>$problem) {
                if(array_key_exists($problem['problem_id'], $solutionStatus))
                    $problemList[$key]['ac'] = $solutionStatus[$problem['problem_id']] == '0' ? 0 : 1;
            }
        }
        if(strlen($search) > 0) {
            $ret['total'] = $Problem
                ->where(function($query)use ($map) {
                    $query->whereOr($map);
                })
                ->where($defunctmap)
                ->count();
        }
        else {
            $ret['total'] = $Problem
                ->where($defunctmap)
                ->count();
        }
        $ret['rows'] = $problemList;
        return $ret;
    }
    private function GetProblem()
    {
        $Problem = db('problem');
        $pid = intval(input('get.pid'));
        $map = ['problem_id' => $pid];
        $problem = $Problem->where($map)->find();
        if($problem == null)
        {
            $this->error('No such problem.', null, '', 1);
        }
        if($problem['defunct'] == '1' && !IsAdmin('problem', $pid))
            $this->error('You cannot open problem '. $pid, null, '', 1);
        return $problem;
    }
    public function problem()
    {
        $problem = $this->GetProblem();
        if(input('?ajaxuser') && input('?ajaxtoken'))
        {
            $ajaxuser = trim(input('ajaxuser'));
            $ajaxtoken = trim(input('ajaxtoken'));
            $spiderToken = config('CsgojConfig.SPIDER_TOKEN');
            if(array_key_exists($ajaxuser, $spiderToken) && $ajaxtoken == $spiderToken[$ajaxuser])
                return json($problem);
        }
        $problem['problem_id_show'] = $problem['problem_id'];
        $this->assign([
            'problem' => $problem,
            'pagetitle'=> $problem['problem_id'] .':'. $problem['title']
        ]);
        return $this->fetch();
    }
    public function submit()
    {
        if(!session('?user_id'))
            $this->error('Please login before submit problem solution!', null, '', 1);
        $problem = $this->GetProblem();
        $problem['problem_id_show'] = $problem['problem_id'];
        $pid = intval(input('get.pid'));
        $this->assign([
            'problem' => $problem,
            'pagetitle' => 'Submit Problem ' . $problem['problem_id'] .':'. $problem['title'],
            'user_id' => session('user_id'),
            'allowLanguage' => config('CsgojConfig.OJ_LANGUAGE'),
        ]);
        return $this->fetch();
    }
    public function submit_ajax() {
        if(session('?lastsubmit')) {
            $now = time();
            $submitWaitTime = config('CsgojConfig.OJ_SUBMIT_WAIT_TIME');
            if($now - session('lastsubmit') < $submitWaitTime)
                $this->error("You should not submit more than twice in ".$submitWaitTime." seconds...");
        }
        $pid = trim(input('pid'));
        $ojLang = config('CsgojConfig.OJ_LANGUAGE');
        $language = intval(input('language'));
        if(!array_key_exists($language, $ojLang)) {
            $this->error('The submitted language is not allowed for this OnlineJudge.');
        }
        $source = input('source');
        if(!session('?user_id'))
        {
            $this->error('Please Login First!');
            return;
        }
        $problem = db('problem')->where(['problem_id'=>$pid])->find();
        if($problem == null)
        {
            //题目不存在
            $this->error('Problem not exist!');
            return;
        }
        if($problem['defunct'] != '0' && !IsAdmin('problem_editor') && !IsAdmin('contest_editor'))
        {
            $this->error('Permission denied to submit this problem.');
            return;
        }
        $user_id = session('user_id');
        $code_length = strlen($source);
        if($code_length < 6)
        {
            $this->error('Code too short.');
            return;
        }
        else if($code_length > 65536)
        {
            $this->error('Code too long.');
            return;
        }
        $solution_id = db('solution')->insertGetId([
            'problem_id' => $pid,
            'user_id'    => $user_id,
            'in_date'    => Date('Y-m-d H:i:s'),
            'language'   => $language,
            'ip'         => request()->ip(),
            'code_length'=> $code_length
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
        $this->success(
            'Submit successful! <br/>Redirecting to Status.',
            '',
            ['solution_id' => $solution_id, 'user_id' => $user_id]
        );
    }

    public function summary()
    {
        $problem = $this->GetProblem();
        $problem['problem_id_show'] = $problem['problem_id'];
        if($problem == null)
            $this->error('No such problem.', null, 1);
        $Solution = db('solution');
        $map = [
            'problem_id' => $problem['problem_id'],
        ];

        $ojResultsHtml = config('CsgojConfig.OJ_RESULTS_HTML');
        $statistic = [
            'total_submissions'    =>
                $Solution->where($map)->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })->count(),
            'users_submitted'    =>
                $Solution
                    ->where($map)
                    ->where(function($query){
                        $query->whereNull('contest_id')
                            ->whereOr('contest_id', 0);
                    })
                    ->count('DISTINCT user_id'),
            'users_solved'        =>
                $Solution
                    ->where($map)
                    ->where(function($query){
                        $query->whereNull('contest_id')
                            ->whereOr('contest_id', 0);
                    })
                    ->where('result', 4)
                    ->count('DISTINCT user_id'),
        ];

        foreach($ojResultsHtml as $key=>$value)
        {
            if($key == 13)
                break;
            $statistic[$key] = $Solution
                ->where($map)
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->where('result', $key)
                ->count();
        }
        $this->assign([
            'problem'         => $problem,
            'statistic'        => $statistic,
            'pagetitle'     => $problem['problem_id'] .':'. $problem['title'],
            'ojResultsHtml' => $ojResultsHtml,
            'allowLanguage'    => config('CsgojConfig.OJ_LANGUAGE'),
        ]);
        return $this->fetch();
    }
    private function GetSolutionScoreStr($oderType)
    {
        $scoreConf = [
            'time'             => '000000',
            'memory'         => '00000000',
            'code_length'     => '00000',
            'solution_id'     => '00000',
        ];
        $retQuery = 'CONCAT(';
        $firstFlag = true;
        foreach($oderType as $key=>$value)
        {
            if(!$firstFlag)
                $retQuery .= ", ";
            $retQuery .= "RIGHT(CONCAT('" . $scoreConf[$key] . "', " . ($value == 'asc' ? "" : "1" . $scoreConf[$key] . " - 1 - ") . $key . "), " . strlen($scoreConf[$key]) .")";
            $firstFlag = false;
        }
        $retQuery .= ")";
        return $retQuery;
    }
    public function summary_ajax()
    {
        $pid = input('pid', -1);
        $offset = intval(input('offset'));
        $limit = 20; //intval(input('limit'));
        $sort = input('sort', 'time');
        $orderConfig = ['time', 'memory', 'code_length', 'solution_id'];
        $sort = validate_item_range($sort, $orderConfig);
        $order = input('order', 'asc');
        $language = input('language');

        //在前台设置的排序列优先之后，剩下的内容按优先顺序做次级排序
        $orderType = [$sort => $order];
        foreach($orderConfig as $oc)
        {
            if($oc != $sort)
                $orderType[$oc] = 'asc';
        }
        $scoreQuery = $this->GetSolutionScoreStr($orderType);
        $map = ['problem_id' => $pid, 'result' => 4];
        if($language != null && $language != -1) {
            $map['language'] = $language;
        }
        $Solution = db('solution');
        $userDistinct = $Solution
            ->where($map)
            ->where(function($query){
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->field(['user_id', 'count(*) acnum', 'MIN(' . $scoreQuery . ') score'])
            ->group('user_id')
            ->order('score')
            ->limit($offset, $limit)
            ->buildSql();
        $solutionInfo = $Solution
            ->field(['solution_id', 'user_id', 'memory', 'time', 'language', 'code_length', 'in_date', $scoreQuery . ' score'])
            ->where($map)
            ->where(function($query){
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->buildSql();
        $solutionList = $Solution->table([$userDistinct => 'ud'])
            ->join([$solutionInfo => 's'], 'ud.user_id = s.user_id AND ud.score = s.score', 'left')
            ->field([
                's.solution_id solution_id',
                's.user_id user_id',
                's.memory memory',
                's.time time',
                's.language language',
                's.code_length code_length',
                's.in_date in_date',
                'ud.acnum acnum'
            ])
            ->select();

        $allowLanguage = config('CsgojConfig.OJ_LANGUAGE');
        $i = 1;
        foreach($solutionList as &$solution)
        {
            $solution['rank'] = $offset + $i;
            $i ++;
            if(array_key_exists($solution['language'], $allowLanguage))
                $solution['language'] = $allowLanguage[$solution['language']];
            else
                $solution['language'] = 'unknown';
            $solution['solution_id'] = "<a href='/" . $this->request->module() . "/status?problem_id=" . $pid . "&result=4&user_id=" . $solution['user_id'] . "'>" . $solution['solution_id'] ."(" . $solution['acnum'] . ")</a>";
            $solution['user_id'] = "<a href='/" . $this->request->module() . "/user/userinfo?user_id=" . $solution['user_id'] . "'>" . $solution['user_id'] . "</a>";
        }
        return [
            'rows' => $solutionList,
            'total' => $Solution
                ->where($map)
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->count('DISTINCT user_id')
        ];
    }
}
