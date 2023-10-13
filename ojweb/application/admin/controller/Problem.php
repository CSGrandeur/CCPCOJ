<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 20:25
 */
namespace app\admin\controller;
use think\Db;
class Problem extends Adminbase
{
    //***************************************************************//
    //Problem
    //***************************************************************//
    public function index()
    {
        return $this->fetch();
    }
    public function problem_list_ajax()
    {
        $columns = ['problem_id', 'title', 'in_date', 'source', 'author', 'defunct', 'spj'];
        $offset		= intval(input('offset'));
        $limit		= intval(input('limit'));
        $sort		= trim(input('sort'));
        $sort = validate_item_range($sort, $columns);
        $order		= input('order');
        $search		= trim(input('search/s'));

        $map = [
            'problem_id' => ['between', [1000 + $offset, 1000 + $offset + $limit - 1]]
        ];
        if(strlen($search) > 0)
            $map = [
                'problem_id|title|source|author' =>  ['like', "%$search%"]
            ];
        $ret = [];
        $ordertype = [];
        if(strlen($sort) > 0)
        {
            $ordertype = [
                $sort => $order
            ];
        }
        $Problem = db('problem');
        $problemList = $Problem
            ->field(implode(",", $columns))
            ->where($map)
            ->order($ordertype)
            ->select();
        foreach($problemList as &$problem) {
            $problem['title'] = "<a href='/csgoj/problemset/problem?pid=" . $problem['problem_id'] . "' " .
                ($problem['spj'] == 1 ? " class='red-link' " : "") .">" . $problem['title'] . "</a>";
            if(IsAdmin($this->privilegeStr, $problem['problem_id'])) {
                $problem['defunct'] =
                    "<button type='button' field='defunct' itemid='".$problem['problem_id']."' class='change_status btn ".
                    ($problem['defunct'] == '0' ? "btn-success' status='0' >Available" : "btn-warning' status='1' >Reserved").
                    "</button>";
                $problem['edit'] = 1;
                $problem['testdata'] = 1;
            }
            else {
                $problem['defunct'] = $problem['defunct'] == '0' ? "<span class='text-success'>Available</span>" : "<span class='text-warning'>Reserved</span>";
                $problem['edit'] = 0;
                $problem['testdata'] = 0;
            }
            // $problem['source'] = htmlspecialchars($problem['source']);
            // $problem['author'] = htmlspecialchars($problem['author']);
        }
        $total_map = [];
        if(strlen($search) > 0) {
            $total_map['problem_id|title|source|author'] = ['like', "%$search%"];
            $ret['total'] = $Problem->where($total_map)->count();
        }
        else{
            // 如果正常查询，“total”应该返回最大ID与1000的差值+1，这样前端table才能正常分页。
            // 比如如果没有题号为1000的题，1001~1100是100个，前端则只显示 1 页，没法打开第2页
            $maxProId = $Problem->field("max(problem_id) as max_pro_id")->select();
            $ret['total'] = $maxProId[0]['max_pro_id'] - 999;
        }
        $ret['total'] = $Problem->where($total_map)->count();
        $ret['order'] = $order;
        $ret['rows'] = $problemList;
        return $ret;
    }
    public function problem_edit($copy_mode=false) {
        $problem_id = trim(input('id'));
        if(!IsAdmin($this->privilegeStr, $problem_id))
        {
            $this->error("You don't own this problem");
        }
        $problem = db('problem')->where('problem_id', $problem_id)->find();
        if($problem == null)
        {
            $this->error('No such problem.');
        }
        $problem_md = db('problem_md')->where('problem_id', $problem_id)->find();
        if($problem_md != null)
        {
            //目前方案，另外一个problem_md表只存几个题目描述字段的markdown版本
            //这里用markdown对应字段替换原数据，用于前端显示
            $problem = array_replace($problem, $problem_md);
        }
        $cooperator = $this->GetCooperator($problem['problem_id']);
        $this->assign([
            'problem' 		=> $problem,
            'cooperator'	=> implode(",", $cooperator),
            'item_priv'     => IsAdmin($this->privilegeStr, $problem_id),
            'copy_mode'     => $copy_mode,
        ]);
        return $this->fetch('problem_edit');
    }
    
    public function problem_copy() {
        return $this->problem_edit(true);
    }
    public function problem_edit_ajax()
    {
        $problem_id = input('problem_id/s');
        $problem_copy_id = input('problem_copy_id/s');
        $Problem = db('problem');
        $Problem_md = db('problem_md');
        $problem_item = null;
        $problem_md_item = null;
        if($problem_id !== null) {
            $problem_id = trim($problem_id);
            if(!IsAdmin($this->privilegeStr, $problem_id)) {
                $this->error('You cannot edit this problem');
            }
            $problem_item = $Problem->where('problem_id', $problem_id)->find();
            if($problem_item == null) {
                $this->error('No such problem.');
            }
            $problem_md_item = $Problem_md->where('problem_id', $problem_id)->find();
        }
        $problem_update = input('post.');
        unset($problem_update['cooperator']); //'post.'方便点，但是cooperator要额外处理，就unset了。
        if($problem_copy_id != null) {
            unset($problem_update['problem_copy_id']);
        }        
        $problem_update['spj'] = (array_key_exists('spj', $problem_update) && $problem_update['spj'] == 'true') ? 1 : 0;
        $this->ProValid($problem_update);
        $problem_md_update = [
            'description'   =>     $problem_update['description'],
            'input'         =>     $problem_update['input'],
            'output'        =>     $problem_update['output'],
            'hint'          =>     $problem_update['hint'],
            'source'        =>     $problem_update['source'],
            'author'        =>     $problem_update['author'],
        ];
        $problem_update['description']  = ParseMarkdown($problem_md_update['description']);
        $problem_update['input']        = ParseMarkdown($problem_md_update['input']);
        $problem_update['output']       = ParseMarkdown($problem_md_update['output']);
        $problem_update['hint']         = ParseMarkdown($problem_md_update['hint']);
        $problem_update['source']       = ParseMarkdown($problem_md_update['source']);
        $problem_update['author']       = ParseMarkdown($problem_md_update['author']);
        if($problem_id === null) {
            // 插入数据
            $problem_update['attach'] = $this->AttachFolderCalculation(session('user_id'));
            $problem_id = db('problem')->insertGetId($problem_update);
            if(!$problem_id) {
                $this->error('Add problem failed, SQL error.');
            }
        } else {
            // 更新数据
            if(!$Problem->where('problem_id', $problem_id)->update($problem_update)) {
                $this->error("Update data are the same.");
            }
        }
        $problem_md_update['problem_id'] = $problem_id;
        if($problem_md_item == null) {
            $Problem_md->insert($problem_md_update);
        } else {
            $Problem_md->where('problem_id', $problem_id)->update($problem_md_update);
        }
        //处理cooperator
        $cooperator = input('cooperator/s');
        $alert = false;
        $additionMsg = '';
        if($cooperator != null) {
            $cooperatorList = explode(",", $cooperator);
            $additionMsg = $this->SaveCooperator($cooperatorList, $problem_id);
            if(strlen($additionMsg) > 0) {
                $alert = true;
            }
        }
        if($problem_copy_id != null) {
            $ojPath = config('OjPath');
            $testDataCopyPath = $ojPath['testdata'] . '/' . $problem_copy_id;
            $testDataPath = $ojPath['testdata'] . '/' . $problem_id;
            if(!MakeDirs($testDataPath)) {
                $additionMsg .= "<br/>Cannot create problem data dir";
            } else {
                exec("cp $testDataCopyPath/*.in $testDataPath/");
                exec("cp $testDataCopyPath/*.out $testDataPath/");
                exec("cp $testDataCopyPath/*.cc $testDataPath/");
            }
            

        }
        $this->success('Successful<br/>' . $additionMsg, '', ['problem_id' => $problem_id, 'alert' => $alert]);
    }
    public function problem_add()
    {
        return $this->fetch('problem_edit');
    }
    protected function ProValid($pro_data) {
        if(!array_key_exists('sample_input', $pro_data) || !array_key_exists('sample_output', $pro_data)) {
            $this->error("Sample needed.");
        }
        if(strlen($pro_data['sample_input']) > 16384 || strlen($pro_data['sample_output']) > 16384) {
            $this->error("Sample too long.");
        }
    }
    public function problem_add_ajax()
    {
        $problem_add = input('post.');
        $problem_add['spj'] = (array_key_exists('spj', $problem_add) && $problem_add['spj'] == 'true') ? 1 : 0;
        $problem_add['defunct'] = '1'; //默认隐藏防泄漏咯~
        $problem_add['in_date'] = date('Y-m-d H:i:s');
        $problem_md_add = [
            'description'	=> 	$problem_add['description'],
            'input'			=> 	$problem_add['input'],
            'output'		=> 	$problem_add['output'],
            'hint'			=> 	$problem_add['hint'],
            'source'		=> 	$problem_add['source'],
            'author'		=> 	$problem_add['author'],
        ];
        //插入problem表，描述字段为md编译的html
        $problem_add['description']	= ParseMarkdown($problem_md_add['description']);
        $problem_add['input']		= ParseMarkdown($problem_md_add['input']);
        $problem_add['output']		= ParseMarkdown($problem_md_add['output']);
        $problem_add['hint']		= ParseMarkdown($problem_md_add['hint']);
        $problem_add['source']		= ParseMarkdown($problem_md_add['source']);
        $problem_add['author']		= ParseMarkdown($problem_md_add['author']);
        $problem_add['attach']		= $this->AttachFolderCalculation(session('user_id')); // 计算附件文件夹名称，固定后导入导出题目不会有路径变化问题
        $this->ProValid($problem_add);
        
        $problem_id = null;
        if(!($problem_id = db('problem')->insertGetId($problem_add)))
        {
            $this->error('Add problem failed, SQL error.');
            return;
        }
        // problem已插入，下面处理problem_md
        $Problem_md = db('problem_md');
        $problem_md = $Problem_md->where('problem_id', $problem_id)->find();
        $problem_md_add['problem_id'] = $problem_id; //注意problem_md表要设置problem_id以和problem表对应。
        //虽然新插数据基本不会发生problem_md已有此problem_id的情况，但以防万一problem表被删除过并修改过auto_increacement
        if($problem_md == null)
        {
            $Problem_md->insert($problem_md_add);
        }
        else
        {
            $Problem_md->update($problem_md_add);
        }
        //由于该用户添加的，给该用户管理该题目的权限（用于不同题目分权）
        $this->AddPrivilege(session('user_id'), 'problem', $problem_id);
        $ojPath = config('OjPath');
        // // 不再自动生成 sample 的测试文件
        // if(!$this->mkdata($problem_id, 'sample.in', $problem_add['sample_input'], $ojPath['testdata'])
        //     || !$this->mkdata($problem_id, 'sample.out', $problem_add['sample_output'], $ojPath['testdata']))
        // {
        //     $this->success('Problem successfully added<br/>But sample file add failed', '', ['problem_id' => $problem_id]);
        // }
        $this->success('Problem successfully added.', '', ['id' => $problem_id]);
    }
    private function mkdata($pid,$filename,$input,$OJ_DATA)
    {

        $basedir = "$OJ_DATA/$pid";
        if(!MakeDirs($basedir))//在common里的自定义的函数，递归建立文件夹
            return false;
        $fp = @fopen ( $basedir . "/$filename", "w" );
        if($fp)
        {
            fputs ( $fp, preg_replace ( "(\r\n)", "\n", $input ) );
            fclose ( $fp );
            return true;
        }
        return false;
    }
    public function problem_rejudge()
    {
        $this->assign([
            'rejudge_type' => 'problem',
            'submit_url' => '/' . $this->module . '/' . $this->controller . '/problem_rejudge_ajax'
        ]);
        return $this->fetch();
    }
    private function Rejudge($item)
    {
        if(input('?'.$item) && strlen(trim(input($item))) > 0)
        {
            $id = intval(trim(input($item)));
            if($id <= 0)
                return false;
            $Solution = db('solution');
            if($item == 'solution_id')
            {
                $solution = $Solution->where($item, $id)->find();
                if(!IsAdmin($this->privilegeStr,  $solution['problem_id']))
                    $this->error('You cannot rejudge solution of problem ' . $solution['problem_id']);
            }
            else if($item == 'problem_id')
            {
                if(!IsAdmin($this->privilegeStr, $id))
                    $this->error('You cannot rejudge problem ' . $id);
            }
            $map = [$item => $id];
            if(input('post.all_rejudge_check') != 'on') {
                $map['result'] = ['neq', 4];
            }
            db('solution')
                ->where($map)
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->setField('result', 1);
            return $id;
        }
        return false;
    }
    public function problem_rejudge_ajax()
    {
        $jumpurl = '/csgoj';
        $item = "";
        if($id = $this->Rejudge('solution_id')) {
            $jumpurl .= '/status?solution_id=' . $id;
            $item = 'solution';
        }
        else if($id = $this->Rejudge('problem_id')) {
            $jumpurl .= '/status?problem_id=' . $id;
            $item = 'problem';
        }
        else
            $this->error('ID not valid');
        $this->success('Rejudge ' . $item . ' id=' . $id . ' started', '', $jumpurl);
    }


}