<?php
namespace app\ojtool\controller;

class Userinit extends Ojtoolbase
{
    /**************************************************/
    //Init User (System Initialization)
    /**************************************************/
    public function index()
    {
        // 检查 users 表是否为空
        $userCount = db('users')->count();
        if($userCount > 0) {
            $this->error('系统已初始化，无法重复初始化。<span class="en-text">System already initialized.</span>', '/');
        }
        return $this->fetch();
    }

    public function init_user_ajax()
    {
        // 检查 users 表是否为空
        $userCount = db('users')->count();
        if($userCount > 0) {
            $this->error('系统已初始化，无法重复初始化。<span class="en-text">System already initialized.</span>');
        }

        $user_id = trim(input('user_id'));
        $password = trim(input('password'));
        $confirm_password = trim(input('confirm_password'));

        if($user_id == null || strlen($user_id) == 0) {
            $this->error('用户名不能为空<span class="en-text">User ID required</span>');
        }

        if(strlen($user_id) < 5 || strlen($user_id) > 20) {
            $this->error('用户名长度应为5-20个字符<span class="en-text">User ID should be 5-20 characters</span>');
        }

        if($password == null || strlen($password) == 0) {
            $this->error('密码不能为空<span class="en-text">Password required</span>');
        }

        if(strlen($password) < 6 || strlen($password) > 255) {
            $this->error('密码长度应为6-255个字符<span class="en-text">Password should be 6-255 characters</span>');
        }

        if($password != $confirm_password) {
            $this->error('两次输入的密码不一致<span class="en-text">Password confirmation mismatch</span>');
        }

        $Users = db('users');
        $userinfo = $Users->where('user_id', $user_id)->find();
        if($userinfo != null) {
            $this->error('用户名已存在<span class="en-text">User ID already exists</span>');
        }

        // 构建新用户信息，字符串字段默认 "admin"，其他字段使用合适的默认值
        $userinfo = [
            'user_id'     => $user_id,
            'email'       => 'admin',
            'submit'      => 0,
            'solved'      => 0,
            'defunct'     => 'N',
            'ip'          => '127.0.0.1',
            'accesstime'  => date('Y-m-d H:i:s'),
            'volume'      => 1,
            'language'    => 1,
            'password'    => MkPasswd($password),
            'reg_time'    => date('Y-m-d H:i:s'),
            'nick'        => 'admin',
            'school'      => 'admin',
        ];

        // 插入用户
        $Users->insert($userinfo);

        // 清空 privilege 表（删除所有记录）
        db('privilege')->where('privilege_id', '>', 0)->delete();

        // 添加 super_admin 权限
        db('privilege')->insert([
            'user_id'  => $user_id,
            'rightstr' => 'super_admin',
            'defunct'  => 'N'
        ]);

        // 自动登录（使用全局函数）
        LoginOper($userinfo);
        AddLoginlog($userinfo['user_id'], 1);

        $this->success('系统初始化成功！<br/>请配置评测机以开始使用系统。<span class="en-text">System initialized successfully!<br/>Please configure the judger to start using the system.</span>', '/admin/judger/index', ['user_id' => $userinfo['user_id']]);
    }
}

