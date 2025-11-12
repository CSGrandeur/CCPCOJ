<?php
namespace app\admin\controller;
use think\Controller;
use think\db\Expression;
use Alchemy\Zippy\Zippy;
use think\response\Jsonp;

class Contestsummary extends Adminbase
{
    var $ojPath;
    var $ojResults;
    var $ojLang;
    var $userDict;
    var $synScore;
    var $taskName;
    var $dirToZip;
    var $levelScore;    // 等级分差
    public function GetConfigs() {
        
        $this->ojResults = config('CsgojConfig.OJ_RESULTS');
        $this->ojLang = config('CsgojConfig.OJ_LANGUAGE');
        $this->ojPath = config('ojPath');
        $this->userDict = [];
        $this->taskName = "Summary-" . date('Y-m-d-H-i-s') . "-" . session("user_id");
        if(($task_name_prefix = input('task_name_prefix/s')) != null) {
            $this->taskName = $task_name_prefix . '-' . $this->taskName;
        }
        $this->dirToZip = [];
        $this->levelScore = 5;
    }
    public function contest_summary() {
        return $this->fetch();
    }
    public function contest_summary_ajax() {
        $cidList = explode("\n", trim(input('cid_list/s')));
        if(count($cidList) > 64) {
            $this->error("Too many contests.");
        }
        $contestList = db('contest')->where([
            'contest_id'    => ['in', $cidList],
            'private'       => ['in', [0,1,4,10,11,14]]
            ])->select();
        $this->synScore = [];
        $this->GetConfigs();
        set_time_limit(180); // 有些数据比较大，可能需要压缩久一点，php默认30秒超时，所以这里改一下
        foreach($contestList as $contest) {
            $this->SummaryOneContest($contest);
        }
        $totalSynScoreStr = $this->SummarySynScore($contestList);
        $this->WriteMd("", $totalSynScoreStr, "total_syn_score", true);
        $this->ZipContestFiles();
        $this->success("ok", "/admin/contestsummary/download?file=" . $this->taskName . ".zip");
    }
    public function WriteMd($baseFolder, $content, $filename, $addToZip=false) {
        $tmpFolder = $this->ojPath['summary_contest_temp'] . '/' . $this->taskName . '/' . $baseFolder;
        if(!MakeDirs($tmpFolder)) {
            $this->error('Folder permission denied.');
        }
        file_put_contents($tmpFolder . '/' . $filename . '.md', $content);
        // file_put_contents($tmpFolder . '/' . $filename . '.html', ParseMarkdown($content, true, 4, $filename));
        if($addToZip) {
            $this->dirToZip[$baseFolder . '/' . $filename . '.md'] = $tmpFolder . '/' . $filename . '.md';
            // $this->dirToZip[$baseFolder . '/' . $filename . '.html'] = $tmpFolder . '/' . $filename . '.html';
        }
    }
    public function SummarySynScore(&$contestList) {
        $ret_content = "# 实验综合成绩（Comprehensive Score）\n\n";
        $ret_content .= "| Idx | User ID | Nick | Syn Score |";
        foreach($contestList as $contest){
            $ret_content .= $contest['contest_id'] . " | ";
        }
        $ret_content .= "\n";
        $ret_content .= "|:----|:--------|:-----|:----------|";
        foreach($contestList as $contest){
            $ret_content .= ":-----|";
        }
        $ret_content .= "\n";
        ksort($this->userDict);
        $contestNum = count($contestList);
        $i = 1;
        foreach($this->userDict as $user_id=>$userinfo) {
            if(array_key_exists($user_id, $this->synScore)) {
                $scu = &$this->synScore[$user_id];
                $ret_content .= "| " . $i . " | " . $userinfo['user_id'] . " | " . $userinfo['nick'] . " | ";
                $scu['total_aver'] = 0;
                $scoreStr = "";
                foreach($contestList as $contest) {
                    if(array_key_exists($contest['contest_id'], $scu)) {
                        $scoreStr .= intval(round($scu[$contest['contest_id']]['aver'])) . " | ";
                        $scu['total_aver'] += $scu[$contest['contest_id']]['aver'] / $contestNum;
                    } else {
                        $scoreStr .= 0 . " | ";
                    }
                }
                $ret_content .= intval(round($scu['total_aver'])) . " | " . $scoreStr . "\n";
                $i ++;
            }
        }
        return $ret_content;
    }
    public function SummaryOneContest($contest) {
        $contestSummaryBaseFolder = $contest['contest_id'] . "-" . trim(preg_replace('/\s+|\.|\\\|\\/|\:|\*|\?|\"|\<|\>|\|/', '_', $contest['title']));
        $this->dirToZip[$contestSummaryBaseFolder] = $this->ojPath['summary_contest_temp'] . '/' . $this->taskName . '/' . $contestSummaryBaseFolder;
        // *************************
        // problems
        $problem = $this->ContestProblem($contest);
        $problem['md'] = preg_replace('/!\[.*?\]\(\/upload/', '![](upload', $problem['md']);
        $this->WriteMd($contestSummaryBaseFolder, $problem['md'], 'problemset');
        foreach($problem['info'] as $pro) {
            $problemAttachPath = $this->ojPath['PUBLIC'] . $this->ojPath['problem_ATTACH'] . '/' . $pro['attach'];
            if(is_dir($problemAttachPath)) {
                $this->dirToZip[$contestSummaryBaseFolder . $this->ojPath['problem_ATTACH'] . '/' . $pro['attach']] = $problemAttachPath;
            }
        }
        
        // *************************
        // submission
        $solution = $this->ContestSolution($contest, false);
        $solutionList = $solution['info'];
        $this->WriteMd($contestSummaryBaseFolder, $solution['md'], 'solution');

        // *************************
        // rank
        $rank = $this->GetRank($contest, $solutionList);
        $this->WriteMd($contestSummaryBaseFolder, $rank['md'], 'rank');
        
        // *************************
        // Quality Classification
        $quality = $this->QualityClassification($contest);
        foreach($quality['md'] as $key=>$qc) {
            $this->WriteMd($contestSummaryBaseFolder, $qc, 'sample_' . $key);
        }

        // *************************
        // syn score
        $this->SynScore($contest, $solutionList);
    }
    public function sec2str($sec) {
        // 训练类比赛可能超过100小时，多于二位数了。
        if($sec < 360000)
            $sec = sprintf("%02d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        else
            $sec = sprintf("%d:%02d:%02d", $sec / 3600, $sec % 3600 / 60, $sec % 60);
        return $sec;
    }
    public function ContestProblemId($ith)
    {
        //比赛题目编号计算, 0是A, 1是B，26是AA，类似Excel横轴命名规则
        $ret = '';
        $ith = intval($ith) + 1;
        while($ith > 0)
        {
            $ret = chr(($ith - 1) % 26 + ord('A')) . $ret;
            $ith = intval(($ith - 1) / 26);
        }
        return $ret;
    }
    public function ProblemIdMap($contest)
    {
        // 题号1xxx、ABCD、num的0123 题号的对应关系
        //[
        //    'abc2id'=>//ABC->10xx题号映射,
        //     'id2abc'=>//10xx->ABC题号映射,
        //     'id2num'=>//10xx->0、1、2(num)
        //]
        $problemIdList = db('contest_problem')
            ->where('contest_id', $contest['contest_id'])
            ->field([
                'problem_id',
                'num'
            ])
            ->order('num', 'asc')
            ->cache(60)
            ->select();

        $problemIdMap = [
            'abc2id' => [],
            'id2abc' => [],
            'id2num' => []
        ];
        if($problemIdList == null) {
            //这种情况一般不会发生，如果真的有，那是管理员操作不当，页面出问题也难免
            $this->error('No problem found in contest ' . $contest['contest_id'] . ' ' . $contest['title']);
        }
        foreach($problemIdList as $problemId)
        {
            $alphabetId = $this->ContestProblemId($problemId['num']);
            $problemIdMap['abc2id'][$alphabetId] = $problemId['problem_id'];
            $problemIdMap['id2abc'][$problemId['problem_id']] = $alphabetId;
            $problemIdMap['id2num'][$problemId['problem_id']] = $problemId['num'];
        }
        return $problemIdMap;
    }
    public function ContestProblem($contest) {
        // 获取一场比赛的Problem信息，返回 problem 数据与 markdown 文本
        $ret_content = "";
        $problem_list_export = [];
        $problemIdMap = $this->ProblemIdMap($contest);
        foreach($problemIdMap['id2abc'] as $key=>$val){
            $problem_list_export[] = intval($key);
        }
        $problem_list_export = array_unique($problem_list_export);
        if(count($problem_list_export) == 0)
            $this->error("Cannot find problems for contest ". $contest['contest_id']);
        $whereMap = [
            "p.problem_id" => ['in', $problem_list_export]
        ];
        $orderMap = new Expression("field(p.problem_id,". implode(",", $problem_list_export) .")");
        
        $Problem = db('problem');
        $problemList = $Problem->alias('p')
            ->join('problem_md pmd', 'p.problem_id = pmd.problem_id', 'left')
            ->where($whereMap)
            ->order($orderMap)
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.attach attach',
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
        $ret_content .= "# 题目\n\n";
        foreach($problemList as $pro){
            $ret_content .= "## " . $problemIdMap['id2abc'][$pro['problem_id']] . "(" . $pro['problem_id'] . ")：" . $pro['title'] . "\n\n";
            $ret_content .= "> Time Limit: " . $pro['time_limit'] . "s    \t Memory Limit: " . $pro['memory_limit'] . "MB    \t Special Judge: " . ($pro['spj']=='0' ? "False" : "True") . "\n\n";            
            $ret_content .= $pro['description_md'] . "\n\n";
            $ret_content .= "### Input\n\n" . $pro['input_md'] . "\n\n";
            $ret_content .= "### Output\n\n" . $pro['output_md'] . "\n\n";
            $ret_content .= "### Sample Input\n\n````txt\n" . $pro['sample_input'] . "\n````\n\n";
            $ret_content .= "### Sample Output\n\n````txt\n" . $pro['sample_output'] . "\n````\n\n";
            if(strlen(trim($pro['hint_md'])) > 0)
                $ret_content .= "### Hint\n\n" . $pro['hint_md'] . "\n\n";
            if(strlen(trim($pro['source_md'])) > 0)
                $ret_content .= "### Source\n\n" . $pro['source_md'] . "\n\n";
            if(strlen(trim($pro['author_md'])) > 0)
                $ret_content .= "### Author\n\n" . $pro['author_md'] . "\n\n";
        }
        return [
            'info'  => $problemList,
            'md'    => $ret_content
        ];
    }
    public function ContestUser($contest) {
        // 基于solution表获取一场比赛的用户信息
        // standard 格式的 contest 暂不处理
        if($contest['private'] % 10 == 0) {
            // Public Contest
            $userList = db('solution')->alias('s')
            ->join('users u', 'u.user_id = s.user_id', 'left')
            ->where('s.contest_id', $contest['contest_id'])
            ->group('s.user_id,u.nick,u.school,u.email')
            ->field([
                's.user_id user_id',
                'u.nick nick',
                'u.school school',
                'u.email tmember',
                '"" coach',
            ])
            ->cache(60)
            ->select();
        } else if($contest['private'] % 10 == 4) {
            $user_id_list = db('privilege')->where('rightstr', 'clss_' . $contest['password'])->column('user_id');
            $userList = db('users')->where('user_id', 'in', $user_id_list)->cache(60)->select();
        }
        else {
            // Private Contest
            $userList = db('privilege')->alias('pri')
            ->join('users u', 'u.user_id = pri.user_id', 'left')
            ->where('pri.rightstr', 'c' . $contest['contest_id'])
            ->group('u.user_id,u.nick,u.school,u.email')
            ->field([
                'u.user_id user_id',
                'u.nick nick',
                'u.school school',
                'u.email tmember',
                '"" coach',
            ])
            ->cache(60)
            ->select();
        }
        foreach($userList as $val) {
            if(!array_key_exists($val['user_id'], $this->userDict)) {
                $this->userDict[$val['user_id']] = $val;
            }
        }
        return $userList;
    }
    
    public function GetRankData($contest, &$solutionList) {
        $rankDataList = [];
        //把所有solution整理为以user_id为键的一条条成绩信息
        $firstBlood = [];
        
        // 先获取用户列表，Online版只需要nick，比赛里需要只计算比赛账号的rank，以免 fb 计算错误
        // 解释：对于比赛系统，生成账号交题后，重新生成账号去掉了已交题账号，避免这个交题记录被作为fb，造成rank实际用户fb无信息
        $userList = $this->ContestUser($contest);
        $problemIdMap = $this->ProblemIdMap($contest);
        // 获取solution信息计算rank数据
        foreach($solutionList as $s)
        {
            if(!array_key_exists($s['problem_id'], $problemIdMap['id2abc']))
                continue;
            if(!array_key_exists($s['user_id'], $this->userDict))
                continue;
            if(!array_key_exists($s['user_id'], $rankDataList))
                $rankDataList[$s['user_id']] = [
                    //solved和penalty放在前两个，sort的时候就很方便不需要额外写comp函数了。
                    'solved'  => 0,         // AC题数
                    'penalty' => 0,         // 罚时（分钟, minutes）
                    'pass_rate' => [],      // 各题 pass_rate，取最大值
                    'wa_num' => [],         // 在AC之前错了几次，AC之后的数据忽略
                    'ac_sec' => [],         // 第一次AC距离比赛开始时间（秒，seconds），之后的数据忽略
                    'tr_num' => [],         // 封榜后尝试次数
                ];
            $rankData = &$rankDataList[$s['user_id']];
            if(array_key_exists($s['problem_id'], $rankData['ac_sec']))
                continue;
    
            if($s['result'] == 4)
            {
                $rankData['ac_sec'][$s['problem_id']] = strtotime($s['in_date']) - strtotime($contest['start_time']);
                $rankData['solved'] ++;
                //用负数，sort的时候就很方便了。
                $rankData['penalty'] -= $rankData['ac_sec'][$s['problem_id']] + (array_key_exists($s['problem_id'], $rankData['wa_num']) ? (1200 * $rankData['wa_num'][$s['problem_id']]) : 0);

                //添加first blood标记，多个人同一秒出题则都是fb
                if(!array_key_exists($s['problem_id'], $firstBlood))
                    $firstBlood[$s['problem_id']] = [
                        'userlist' => [],
                        'time'    => $rankData['ac_sec'][$s['problem_id']]
                    ];
                if($firstBlood[$s['problem_id']]['time'] == $rankData['ac_sec'][$s['problem_id']])
                    $firstBlood[$s['problem_id']]['userlist'][] = $s['user_id'];
            }
            else
            {
                $rankData['wa_num'][$s['problem_id']] = array_key_exists($s['problem_id'], $rankData['wa_num']) ? $rankData['wa_num'][$s['problem_id']] + 1 : 1;
            }
            if(!array_key_exists($s['problem_id'], $rankData['pass_rate']) || $s['pass_rate'] > $rankData['pass_rate'][$s['problem_id']]){
                $rankData['pass_rate'][$s['problem_id']] = $s['pass_rate'];
            }
        }
        foreach($userList as $user)
        {
            foreach($user as $key=>&$value)
            {
                if ($value == null || trim($value) == '') {
                    $value = '-';
                }
            }
            $user['school'] = strtoupper($user['school']);
            // 如果比赛开始后管理员删除了题目，这里也要避免只交了删除题目的用户进入榜单数据里，否则会因为缺少预处理部分
            // 的数据（比如'solved'等）而报错
            if(!array_key_exists($user['user_id'], $rankDataList))
                continue;
            $rankDataList[$user['user_id']]['userinfo'] = $user;
        }
        // #####################排序在此处，就一行。此时各时间数据还是秒的int格式#######################
        arsort($rankDataList);
        return [$firstBlood, $rankDataList];
    }
    public function GetRankList($contest, &$solutionList)
    {
        // 计算一场比赛的 rank，以数据形式返回（而非html）
        $data = $this->GetRankData($contest, $solutionList);
        $rankDataList = &$data[1];
        $retList = [];
        $i = 0;
        $lastSolved = -1;
        $lastPenalty = -1;
        $problemIdMap = $this->ProblemIdMap($contest);
        foreach($rankDataList as $key=>&$rankData)
        {
            if(!isset($rankData['userinfo'])) {
                // 没有 userinfo，不合法数据
                continue;
            }
            $star_team = strlen($rankData['userinfo']['nick']) > 0 && $rankData['userinfo']['nick'][0] == '*';
            if(!$star_team && ($rankData['solved'] != $lastSolved || $rankData['solved'] == $lastSolved && $rankData['penalty'] != $lastPenalty))
            {
                $i ++;
                $lastPenalty = $rankData['penalty'];
                $lastSolved = $rankData['solved'];
            }
            $row = [
                'rank'        => $star_team ? "*" : $i,
                'nick'        => htmlspecialchars($rankData['userinfo']['nick']),    //要转换html标签，以防用户使用特殊标签做nick
                'solved'      => $rankData['solved'],
                'penalty'     => $this->sec2str(-$rankData['penalty']), //前面用负数方便sort，此时反过来
                'school'        => htmlspecialchars($rankData['userinfo']['school']),    //要转换html标签，以防用户使用特殊标签做nick
            ];
            $row['user_id'] = $key;
            // 每道题的显示内容
            foreach($problemIdMap['id2abc'] as $pid => $apid)
            {
                if(array_key_exists($pid, $rankData['ac_sec'])) {
                    $row[$apid] = $this->sec2str($rankData['ac_sec'][$pid]);
                } 
                if(!array_key_exists($apid, $row)) {
                    $row[$apid] = "";
                }
                $row[$apid] .= array_key_exists($pid, $rankData['wa_num']) ? ('(-' . $rankData['wa_num'][$pid].')') : '';
            }
            $retList[] = $row;
        }
        return $retList;
    }
    public function GetRank($contest, &$solutionList) {
        // 获取用于归档的 rank
        $ret_content = "# 排名（Rank List）\n\n";
        $problemIdMap = $this->ProblemIdMap($contest);
        $rank = $this->GetRankList($contest, $solutionList);
        // header
        $ret_content .= "| Rank | User ID | Nick | School | Member | Solved | Penalty | ";
        foreach($problemIdMap['id2abc'] as $key=>$val){
            $ret_content .= $val . "     | ";
        }
        $ret_content .= "\n";
        $ret_content .= "|:-----|:--------|:-----|:-------|:-------|:-------|:--------|";
        foreach($problemIdMap['id2abc'] as $key=>$val){
            $ret_content .= ":------|";
        }
        $ret_content .= "\n";
        // table body
        foreach($rank as $item) {
            // user info
            $ret_content .= "| " . $item['rank'] . " | " . $item['user_id'] . " | " . $item['nick'] . " | " . $item['school'] . " | " .
                (array_key_exists('tmember', $item) ? $item['tmember'] : "") . " | " . $item['solved'] . " | " . $item['penalty'] . " | ";
            // each problem score
            foreach($problemIdMap['id2abc'] as $key=>$val){
                $ret_content .= (array_key_exists($val, $item) && strlen(trim($item[$val])) > 0 ? $item[$val] : "") . " | ";
            }
            $ret_content .= "\n";
        }
        return [
            'info'  => $rank,
            'md'    => $ret_content
        ];
    }
    public function SolContent($sol, $ith=null) {
        $title = "## " . ($ith ? $ith . "--" : "") . $sol['solution_id'] . ": pro[" . $sol['problem_id'] . "] user[" . $sol['user_id'] . "][" . 
        (array_key_exists($sol['user_id'], $this->userDict) ? $this->userDict[$sol['user_id']]['nick'] : "") . "] result[" . $this->ojResults[$sol['result']] . "]\n\n";

        if(array_key_exists($sol['language'], $this->ojLang))
            $language = $this->ojLang[$sol['language']];
        else
            $language = "Unknown";
        $solContent = "````" . ($language == "Unknown" ? "" : $language);
        $solContent .= "\n/**********************************************************************\n".
            "\tProblem: ".$sol['problem_id']."\n\tUser: ".$sol['user_id']."\n".
            "\tLanguage: ".$language ."\n\tResult: ".$this->ojResults[$sol['result']]."\n";
        if ($sol['result']==4)
            $solContent .= "\tTime:".$sol['time']." ms\n"."\tMemory:".$sol['memory']." kb\n";
        if($this->Plagiarize($sol)) {
            // 抄袭情况录入代码信息
            $solContent .= "\tSimilar:".$sol['sim']."% to solution " . $sol['sim_s_id'] . "\n";
        }
        $solContent .= "**********************************************************************/\n\n";
        $solContent .= str_replace("\r\n","\n", $sol['source']);

        $solContent .= "\n````\n\n";
        return $title . $solContent;
    }
    public function ContestSolution($contest, $onlyAC=false) {
        // 获取一场比赛所有的提交信息
        // $userList = $this->ContestUser($contest);
        $ret_content = "# 代码归档\n\n";
        $Solution = db('solution');
        $map = ['s.contest_id' => $contest['contest_id']];
        if($onlyAC) {
            $map['s.result'] = 4;
        }
        $solutionList = $Solution->alias('s')
            ->join('source_code sc', 's.solution_id = sc.solution_id', 'left')
            ->join('sim si', 'si.s_id=s.solution_id', 'left')
            ->where($map)
            ->order(['s.in_date' => 'ASC'])
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
                's.in_date in_date',
                'sc.source source',
                'si.sim sim',
                'si.sim_s_id sim_s_id'
            ])
            ->cache(60)
            ->select();
        $problemIdMap = $this->ProblemIdMap($contest);
        foreach($solutionList as $sol) {
            if(!array_key_exists($sol['problem_id'], $problemIdMap['id2abc'])) {
                continue;
            }
            $ret_content .= $this->SolContent($sol);
        }
        return [
            'info'  => $solutionList,
            'md'    => $ret_content
        ];
    }
    public function ProScore($contest, $sol) {
        $start = strtotime($contest['start_time']);
        $solve = strtotime($sol['in_date']);
        $hours = ($solve - $start) / 3600;
        if($hours < 5) {
            return 100;
        } else if($hours < 24) {
            return 100 - $this->levelScore;
        } else if($hours < 168) {
            return 100 - $this->levelScore * 2;
        }
        return 100 - $this->levelScore * 3;
    }
    public function QualityClassification($contest) {
        // 代码质量分数，按 运行时长、内存、代码长度 排序后，计算在基准分上扣除的分数
        // 返回 优中差 各若干个
        $ordercmp = function($x, $y) {
            if($x['time'] < $y['time']) {
                return -1;
            } else if($x['time'] > $y['time']) {
                return 1;
            } else if($x['memory'] < $y['memory']) {
                return -1;
            } else if($x['memory'] > $y['memory']) {
                return 1;
            } else if($x['code_length'] < $y['code_length']) {
                return -1;
            } else if($x['code_length'] > $y['code_length']) {
                return 1;
            } else if($x['in_date'] < $y['in_date']) {
                return -1;
            } else if($x['in_date'] > $y['in_date']) {
                return 1;
            }
            return 0;
        };
        $problemIdMap = $this->ProblemIdMap($contest);
        $solution = $this->ContestSolution($contest, true); // only AC
        $solutionList = $solution['info'];
        $proSolOrder = [];
        foreach($solutionList as $sol) {
            if(!array_key_exists($sol['problem_id'], $problemIdMap['id2abc'])) {
                continue;
            }
            $abcpid = $problemIdMap['id2abc'][$sol['problem_id']];
            if(!array_key_exists($abcpid, $proSolOrder)) {
                $proSolOrder[$abcpid] = [];
            }
            $sol['time'] = intval($sol['time'] / 20) * 20;
            $sol['memory'] = intval($sol['memory'] / 200) * 200;
            $sol['code_length'] = intval($sol['code_length'] / 100) * 100;
            $proSolOrder[$abcpid][] = $sol;
        }
        $good = [];
        $medium = [];
        $bad = [];
        $good_md = "# 较好示例（Good Samples）\n\n> 评价标准： AC->运行效率->占用内存->代码长度\n\n";
        $medium_md = "# 中等示例（Medium Samples）\n\n> 评价标准： AC->运行效率->占用内存->代码长度\n\n";
        $bad_md = "# 较差示例（Bad Samples）\n\n> 评价标准： AC->运行效率->占用内存->代码长度\n\n";
        foreach($proSolOrder as $pid=>$solList) {
            uasort($solList, $ordercmp);
            $usermap = [];
            $realAcNum = 0;
            foreach($solList as $sol) {
                if(!array_key_exists($sol['user_id'], $usermap)) {
                    $usermap[$sol['user_id']] = true;
                    $realAcNum ++;
                }
            }
            $i = 1;
            $usermap = [];
            if($realAcNum == 0) $realAcNum = 1;
            $realAcNum *= 1.0;
            $classifyNum = 5;
            $good_md .= "\n## " . $pid . "\n\n";
            $medium_md .= "\n## " . $pid . "\n\n";
            $bad_md .= "\n## " . $pid . "\n\n";
            $ig = 1;
            $im = 1;
            $ib = 1;
            foreach($solList as $sol) {
                if($this->Plagiarize($sol)) {
                    // 跳过抄袭代码
                    continue;
                }
                if(!array_key_exists($sol['user_id'], $usermap)) {
                    $usermap[$sol['user_id']] = true;
                    KeyAdd($sol['user_id'], $this->synScore);
                    KeyAdd($contest['contest_id'], $this->synScore[$sol['user_id']]);
                    // $this->synScore[$sol['user_id']][$contest['contest_id']][$pid] = -intval($this->levelScore * $i / $realAcNum);    // 这一行好像没用，且会影响后续算分
                    if($i <= $classifyNum) {
                        // 若干 最优
                        KeyAdd($pid, $good);
                        $good[$pid] = $sol;
                        $good_md .= $this->SolContent($sol, $ig);
                        $ig ++;
                    } else if($realAcNum > $classifyNum * 2 && $i > $classifyNum && $realAcNum - $i >= $classifyNum && $i > $realAcNum / 3 && $i <= $realAcNum * 2 / 3) {
                        // 若干 中等
                        KeyAdd($pid, $medium);
                        $medium[$pid] = $sol;
                        $medium_md .= $this->SolContent($sol, $im);
                        $im ++;
                    }
                    else if($i > $classifyNum && $realAcNum - $i < $classifyNum) {
                        // 若干 较差
                        KeyAdd($pid, $bad);
                        $bad[$pid] = $sol;
                        $bad_md .= $this->SolContent($sol, $ib);
                        $ib ++;
                    }
                    $i ++;
                }
            }
        }
        return [
            'info'  => [
                'good'      => $good,
                'medium'    => $medium,
                'bad'       => $bad,
            ],
            'md'    => [
                'good'      => $good_md,
                'medium'    => $medium_md,
                'bad'       => $bad_md,
            ]
        ];
    }
    public function SynScore($contest, &$solutionList) {
        // 评分规则：5小时内、24小时内、7*24小时内、其它 四档，基准分递减 $this->levelScore 分
        // 分数区间内由代码 时间、内存、长度 三者排序设置高低
        // [
        //     'user_id' => [
        //         'contest_id' => [
        //             'A'     => 100,
        //             // ...,
        //             'total' => 500,
        //             'aver'  => 100
        //         ]
        //         'total' => 2000,
        //         'aver'  => 100
        //     ],
        //     // ...
        // ]
        $problemIdMap = $this->ProblemIdMap($contest);
        // $proNum = count($problemIdMap['abc2id']);
        // 去掉附加题分数统计（如有）
        $proNum = count($problemIdMap['abc2id']) - round($contest['private'] / 10);   

        foreach($solutionList as $sol) {
            if(!array_key_exists($sol['user_id'], $this->synScore)) {
                $this->synScore[$sol['user_id']] = [];
            }
            $scu = &$this->synScore[$sol['user_id']];
            KeyAdd($contest['contest_id'], $scu);
            if(!array_key_exists('aver', $scu[$contest['contest_id']])) {
                KeyAdd('aver', $scu[$contest['contest_id']], 0);
                KeyAdd('plagiarize', $scu[$contest['contest_id']], []);  // 标记抄袭
                foreach($problemIdMap['id2abc'] as $pid=>$apid) {
                    KeyAdd($apid, $scu[$contest['contest_id']], 0);
                }
            }
            if(!array_key_exists($sol['problem_id'], $problemIdMap['id2abc'])) {
                continue;
            }
            $proabc = $problemIdMap['id2abc'][$sol['problem_id']];
            // // if($sol['result'] == 4 && $scu[$contest['contest_id']][$proabc] <= 0) {   // 改为取最高分：
            if($sol['result'] == 4) {
                $tmpScore = $this->ProScore($contest, $sol);
                if($this->Plagiarize($sol)){
                    // 如果抄袭，则记录抄袭分数和被抄袭代码id
                    KeyAdd($proabc, $scu[$contest['contest_id']]['plagiarize']);
                    $scu[$contest['contest_id']]['plagiarize'][$proabc][] = [
                        'score'=> $tmpScore,
                        'sim'=> $sol['sim_s_id']
                    ];
                    // 抄袭则分数打折扣
                    $tmpScore = intval($tmpScore * $this->PLAGIARISM_SCORE);
                }
                if($problemIdMap['id2num'][$sol['problem_id']] < $proNum && $tmpScore > $scu[$contest['contest_id']][$proabc]){
                    // 不是附加题才计入总分
                    // 如果有没抄袭的提交，取最高的计算
                    $scu[$contest['contest_id']]['aver'] += $tmpScore / $proNum - $scu[$contest['contest_id']][$proabc] / $proNum;
                }
                if($tmpScore > $scu[$contest['contest_id']][$proabc]) {
                    $scu[$contest['contest_id']][$proabc] = $tmpScore;
                }
            }
        }
    }
    public function Plagiarize($sol) {
        return $sol['sim'] != null && $sol['sim'] > 85 && $sol['code_length'] > 800;
    }
    public function ZipContestFiles()
    {
        $exportTempRoot = $this->ojPath['summary_contest_temp'];
		$exportRoot = $this->ojPath['summary_contest'];
        //在建立新的临时文件夹之前，删除旧的因为程序崩溃导致的未删除的临时文件夹。
        DelTimeExpireFolders($exportTempRoot, $this->ojPath['export_temp_keep_time']);

		//临时放置打包文件的文件夹
		$date = date('Y-m-d-H-i-s');
		$exportMakeFolder = $exportTempRoot . '/' . $date . '-' . session('user_id');
        if(!MakeDirs($exportMakeFolder))
            $this->error('Folder permission denied.');
        if(!MakeDirs($exportRoot))
            $this->error('Folder permission denied.');

        $fileName = $this->taskName . ".zip";
        //这种情况基本不会有，不过为防止建立zip失败
        if(file_exists($exportMakeFolder . '/' . $fileName))
            unlink($exportMakeFolder . '/' . $fileName);
        $zippy = Zippy::load();
        $archive = $zippy->create(
            $exportMakeFolder . '/' . $fileName,
            $this->dirToZip,
            true
        );
        rename($exportMakeFolder . '/' . $fileName, $exportRoot . '/' . $fileName);
        //文件移动到正规目录后删除临时文件夹
        DelDirs($exportMakeFolder);
        return true;
    }
    public function summary_file_list_ajax() {
        $ojPath = config('ojPath');
        $dirPath = $ojPath['summary_contest'];
		DelTimeExpireFolders($dirPath, $ojPath['export_keep_time']);
		$filelist = [];
		if(is_dir($dirPath) && ($handle = opendir($dirPath)))
		{
			$i = 1;
			while (($file = readdir($handle)) !== false)
			{
				if ($file!="." && $file!="..")
				{
					$filetime = filemtime($dirPath . '/' . $file);
					$filelist[] = [
						'file_lastmodify' => date("Y-m-d h:i:s", $filetime),
						'file_name'       => $file,
						'file_size'       => round(filesize($dirPath . '/' . $file) / 1024, 2),
					];
					$i ++;
				}
			}
			rsort($filelist);
			//关闭句柄
			closedir ( $handle );
		}
		return $filelist;
    }
    public function download() {
        $ojPath = config('ojPath');
        $dirPath = $ojPath['summary_contest'];
        downloads($dirPath, input('file/s'));
    }
    public function delete() {
        $ojPath = config('ojPath');
        $dirPath = $ojPath['summary_contest'];
        $file = trim(preg_replace('/\\\|\\/|\:|\*|\?|\"|\<|\>|\|/', '', input('file/s')));
        if($file == "" || !is_file($dirPath . "/" . $file)) {
            $this->error("No such file");
        }
        if(!DelWhatever($dirPath . '/' . $file)) {
            $this->error("Failed");
        }
        $this->success("Deleted");
    }
}