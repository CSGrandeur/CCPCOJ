<?php
namespace app\csgoj\controller;
use think\Controller;
use think\Db;
class Status extends Csgojbase
{
    public function index()
    {
        $this->assign([
            'pagetitle' => 'Status',
            'user_id' => session('user_id'),
            'allowLanguage'    => config('CsgojConfig.OJ_LANGUAGE'),
            'ojResults'    => config('CsgojConfig.OJ_RESULTS'),
            'ojResultsHtml' => config('CsgojConfig.OJ_RESULTS_HTML'),
            'resdetail_authority' => IsAdmin('source_browser'),
            'search_problem_id' => input('problem_id'),
            'search_user_id' => input('user_id'),
            'search_solution_id' => input('solution_id'),
            'search_result' => intval(input('result', -1)),
            'single_status_url'     => '/' . $this->request->module() . '/status/single_status_ajax',
            'show_code_url'         => '/' . $this->request->module() . '/status/showcode_ajax',
            'show_res_url'             => '/' . $this->request->module() . '/status/resdetail_ajax',
        ]);
        return $this->fetch();
    }
    public function status_ajax() {
        $columns = ['solution_id', 'user_id', 'problem_id', 'contest_id', 'result', 'memory', 'time', 'language', 'code_length', 'pass_rate', 'in_date'];
        $offset = intval(input('offset'));
        $limit = 20; //intval(input('limit'));
        $sort = 'solution_id';
        $order = 'desc';
        $solution_id_list = input('solution_id_list/a');   // 用于局部刷新status

        $problem_id = trim(input('problem_id'));
        $user_id = trim(input('user_id'));
        $solution_id = trim(input('solution_id'));
        $language = input('language');
        $result = input('result');
        $map = [];
        if($problem_id != null && strlen($problem_id) > 0) {
            $map['problem_id'] = $problem_id;
        }
        if($user_id != null && strlen($user_id) > 0) {
            $map['user_id'] = $user_id;
        }
        if($solution_id != null && strlen($solution_id) > 0) {
            $map['solution_id'] = $solution_id;
        } else if($solution_id_list != null){            
            $map['solution_id'] = ['in', array_slice($solution_id_list, 0, 25)];
        }
        if($language != null && $language != -1) {
            $map['language'] = $language;
        }
        if($result != null && $result != -1) {
            $map['result'] = $result;
        }
        $ret = [];
        $ordertype = [];
        if (strlen($sort) > 0) {
            $ordertype = [
                $sort => $order
            ];
        }
        $Solution = db('solution');
        if(IsAdmin('source_browser')) {
            $columns[] = 'judger';
        }
        $solution_fetch_pre = $Solution
            ->field($columns)
            ->where($map);
        if(!IsAdmin())//对于非管理员，外部status不显示contest里的提交。
        {
            $solution_fetch_pre = $solution_fetch_pre->where(function ($query) {
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            });
        }
        $solutionlist = $solution_fetch_pre
            ->order($ordertype)
            ->limit($offset, $limit)
            ->select();
        $oj_language = config('CsgojConfig.OJ_LANGUAGE');
        foreach($solutionlist as &$solution)
        {
            if(array_key_exists($solution['language'], $oj_language))
                $solution['language'] = $oj_language[$solution['language']];
            else
                $solution['language'] = "Unknown";
            $solution['code_show'] = $this->if_can_see_info($solution);

            $this->GetResultShow($solution);
        }

        if(IsAdmin())//对于非管理员，外部status不显示contest里的提交。
        {
            $ret['total'] = $Solution->where($map)->count();
        }
        else
        {
            $ret['total'] = $Solution->where($map)->where(function ($query) {
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })->count();
        }
//        $ret['recordsFiltered'] = 1;
        $ret['order'] = $order;
        $ret['rows'] = $solutionlist;
        return $ret;
    }
    public function single_status_ajax()
    {
        $solution_id = trim(input('solution_id'));
        $map = ['solution_id' => $solution_id];
        $solution_fetch_pre = db('solution')->where($map);
        if(!IsAdmin())//对于非管理员，外部status不显示contest里的提交。
        {
            $solution_fetch_pre = $solution_fetch_pre->where(function ($query) {
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            });
        }
        $solution = $solution_fetch_pre->field(['solution_id', 'user_id', 'memory', 'time', 'result', 'contest_id', 'pass_rate'])->find();
        if($solution == null)
        {
            $this->error('No such solution.');
        }
        if($solution['contest_id'] != null && $solution['contest_id'] > 0)
        {
            if(!IsAdmin('contest', $solution['contest_id']))
                $this->error("You cannot see a contest submission outside the contest");
        }

        $this->GetResultShow($solution);
        //上面已通过field只获取'memory', 'time', 'result'，不会泄漏其他信息
        $this->success('ok', null, $solution);
        return;
    }
    public function GetResultShow(&$solution) {
        $solution['res_show'] = false;
        $oj_results_html = config('CsgojConfig.OJ_RESULTS_HTML');
        $oj_results_short = config('CsgojConfig.OJ_RESULTS_SHORT');
        // if_can_see_info 的前提下，【10 RE 或 11 CE】或者【5~9的结果且(为管理员或允许查看错误信息)】
        $solution['res_show'] = $this->if_can_see_info($solution) && (($solution['result'] == 10 || $solution['result'] == 11) || 
            (in_array($solution['result'], [5, 6, 7, 8, 9, 10, 90]) && (IsAdmin('source_browser') || $this->ALLOW_WA_INFO)));
        $result_style = array_key_exists($solution['result'], $oj_results_html) ? $solution['result'] : 100;
        $solution['res_color'] = $oj_results_html[$result_style][0];
        $solution['res_text'] = $oj_results_html[$result_style][1];
        $solution['res_short'] = array_key_exists($solution['result'], $oj_results_short) ? $oj_results_short[$solution['result']] : 'Unknown';
    }
    private function if_can_see_info($solution)
    {
        if(!session('?user_id'))
            return false;
        if(session('user_id') == $solution['user_id'])
            return true;
        if(IsAdmin('source_browser'))
            return true;
        if($solution['contest_id'] != null && $solution['contest_id'] > 0)
        {
            if(!IsAdmin('contest', $solution['contest_id']))
                return false;
        }
        return false;
    }
    public function resdetail_ajax(){
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        $solution_related_admin = IsAdmin('source_browser') || $solution['contest_id'] != null && $solution['contest_id'] > 0 && IsAdmin('contest', $solution['contest_id']);
        $flg_can_see_special = $solution_related_admin || $this->ALLOW_WA_INFO;
        if($this->if_can_see_info($solution)) {
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
        }
        else {
            $this->error('Permission denied to see this infomation.');
        }
    }
    public function showcode_ajax()
    {
        $data = [];
        $solution_id = trim(input('solution_id'));
        $solution = db('solution')->where('solution_id', $solution_id)->find();

        $oj_language = config('CsgojConfig.OJ_LANGUAGE');
        $oj_results = config('CsgojConfig.OJ_RESULTS');
        if($this->if_can_see_info($solution))
        {
            if(array_key_exists($solution['language'], $oj_language))
                $language = $oj_language[$solution['language']];
            else
                $language = "Unknown";
            $source = db('source_code_user')->where('solution_id', $solution_id)->find();
            
            // 返回转义后的代码，确保安全性
            $data['source'] = htmlentities(str_replace("\r\n","\n",$source['source']), ENT_QUOTES, "utf-8");
            $data['language'] = $language;
            $data['problem_id'] = $solution['problem_id'];
            $data['user_id'] = $solution['user_id'];
            $data['result'] = $oj_results[$solution['result']];
            $data['submit_time'] = $solution['in_date'];
            $data['code_length'] = $solution['code_length'];
            
            // 如果AC，添加时间和内存信息
            if ($solution['result']==4) {
                $data['time'] = $solution['time'];
                $data['memory'] = $solution['memory'];
            }
        }
        else
        {
            $this->error('Permission denied to see this code.');
            return;
        }
        $this->success('', null, $data);
    }
}
