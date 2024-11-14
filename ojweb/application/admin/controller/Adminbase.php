<?php
namespace app\admin\controller;
use think\Controller;
use \Globalbasecontroller;
class Adminbase extends Globalbasecontroller
{
    var $privilegeName;            // 该controller对应的权限名称字符串，problem_editor
    var $privilegePrefix;        // 该controller对应的权限前缀，pro_
    var $privilegeStr;            // controller名字和对应权限名字一致
    var $ojAdminList;            // problem_editor 映射为网页显示的 Problem Editor 的映射表
    var $ojPreAdmin;            // 前缀 pro_ 映射为 problem_editor 的映射表
    var $ojItemPri;                // problem 映射为前缀 pro_  的映射表
    public function _initialize()
    {
        $this->OJMode();
        $this->AdminInit();
        $this->InitController();
    }
    public function AdminInit()
    {
        $this->ojAdminList    = $this->OJ_ADMIN['OJ_ADMIN_LIST'];
        $this->ojItemPri     = $this->OJ_ADMIN['OJ_ITEM_PRI'];
        $this->ojPreAdmin     = $this->OJ_ADMIN['OJ_PRE_ADMIN'];

        $this->assign('pagetitle', 'Admin ' . ucfirst($this->request->controller()));
        $this->assign('controller', strtolower($this->request->controller()));
        $this->assign('ojAdminList', $this->ojAdminList);

        if(!session('?user_id'))
            $this->error('Please loggin first.', '/', null, 1);

        // controller名字和对应权限名字一致，以后要修改admin代码结构时，这里要注意！！！
        $this->privilegeStr = strtolower($this->request->controller());

        //这个是基类，直接调用BaseAuthentication可能导致有些类进不去，加if条件再执行验证
        if(array_key_exists($this->privilegeStr, $this->ojItemPri))
            $this->BaseAuthentication($this->privilegeStr);
//        else if(!IsAdmin('administrator') && strtolower($this->request->action()) != 'index')
//            $this->error('You are not admin', '/');
    }
    public function BaseAuthentication($privilegeStr)
    {
        if(array_key_exists($privilegeStr, $this->ojItemPri))
        {
            $this->privilegePrefix = $this->ojItemPri[$privilegeStr];     //比如 pro_
            $this->privilegeName = $this->ojPreAdmin[$this->privilegePrefix];    //比如 problem_editor
            //先判断此模块权限
            if(!IsAdmin($this->privilegeName))
                $this->error("You don't have this privilege", '/'.$this->request->module(), '', 1);
        }
        else
        {
            //无此模块(指 problem、contest、news这样的item
            $this->error('No such work like "' . $this->inputInfo['item'] . '"');
        }
    }
    public function AddPrivilege($user_id, $item, $id)
    {
        if($user_id == session('user_id') && IsAdmin('administrator')){
            //全局管理员不需要添加单个条目权限
            return;
        }
        //contest、problem、news等模块添加完新内容，要添加对应id的权限
        if(!array_key_exists($item, $this->ojItemPri))
            $this->error("No such kind of admin");

        $priItemPre = $this->ojItemPri[$item];
        if(!IsAdmin($this->ojPreAdmin[$priItemPre]))
            $this->error("You don't have privilege of " . $item);

        $rightstr = $this->ojItemPri[$item] . $id;

        $Privilege = db('privilege');
        $map = ['user_id' => $user_id, 'rightstr' => $rightstr];
        $privilege = $Privilege->where($map)->find();
        if($privilege == null)
            $Privilege->insert($map);
        session('login_user_info')['privilege'][$rightstr] = true;
    }
    public function AttachFolderCalculation($randStr = '')
    {
        //为了题目导入导出不影响附件路径，用固定文件夹名存数据库
        $day = date('Y-m-d');
        $suffix = time() . $randStr . rand();
        $suffixStr = strtoupper(substr(md5($suffix), 0, 16));
        return $day . '_' . $suffixStr;
    }
    public function GetCooperator($item_id)
    {
        //获取 item 的合作者列表
        if(!IsAdmin($this->privilegeStr, $item_id)) {
            $this->error('Permission denied to get cooperator');
        }
        return db('privilege')->where('rightstr', $this->privilegePrefix . $item_id)->column('user_id');
    }
    public function SaveCooperator($cooperator, $item_id)
    {
        //获取 item 的合作者列表
        if(!IsAdmin($this->privilegeStr, $item_id)) {
            $this->error('Permission denied to save cooperator');
        }
        $Privilege = db('privilege');
        $userIdList = [];
        $insertList = [];
        $retInfo = '';
        $num = 0;
        $dictRecord = [];
        if(!IsAdmin('administrator')) {
            // 如果不是全局管理员，则默认要保留自己对该条目的权限
            $userIdList[] = session('user_id');
        }
        if(count($cooperator) > 1 || count($cooperator) == 1 && trim($cooperator[0]) != '') {
            foreach($cooperator as $user_id) {
                if($num >= 6) {
                    $retInfo .= "At most first 6 valid co-editors allowed. Others ignored.";
                    break;
                }
                $user_id = trim(strtolower($user_id));
                if(array_key_exists($user_id, $dictRecord)) {
                    continue;
                }
                $dictRecord[$user_id] = true;
                $userIdList[] = $user_id;
                $num ++;
            }
        }
        $readyUserIdList = db('users')->where('user_id', 'in', $userIdList)->column('user_id');
        foreach($readyUserIdList as $user_id) {
            $insertList[] = [
                'user_id'     => $user_id,
                'rightstr'    => $this->privilegePrefix . $item_id
            ];
        }
        $Privilege->where('rightstr', $this->privilegePrefix . $item_id)->delete();
        $Privilege->insertAll($insertList);
        if(count($readyUserIdList) != count($userIdList)) {
            $retInfo .= "<br/>Users not exists are ignored.<br/>Success user list: <br/>" . implode('\n', $readyUserIdList);
        }
        return $retInfo;
    }
}