<?php
namespace app\csgoj\controller;
use think\Controller;
use think\Validate;
use think\Db;

class User extends Csgojbase
{
    public function index()
    {
        $this->assign(['pagetitle' => 'User']);
        return $this->fetch();
    }
    public function login_ajax() {
        if(session('?user_id')) {
            $this->error('用户已登录，可尝试刷新页面');
        }
        $user_id = trim(input('user_id/s'));
        $password = trim(input('password/s'));
        if($user_id == null || strlen($user_id) == 0) {
            $this->error('请求数据不合法');
        }
        $userinfo = null;
        $User = db('users');
        $map = array(
            'user_id' => $user_id
        );
        $userinfo = $User->where($map)->find();
        if($userinfo == null)
            $this->error('No such user');
        if(!CkPasswd($password, $userinfo['password'])) {
            $this->add_loginlog($userinfo['user_id'], 0);
            $this->error('Password Error!');
        }
        $userinfo['password'] = '';
        $privileges = $this->login_oper($userinfo);
        $this->add_loginlog($userinfo['user_id'], 1);
        //在登录这种相对低频操作时更新用户资料的ac数和submit数，用于显示总用户rank
        $this->update_user_solved_submit($user_id);

        $this->success('Login successful!<br/>Reloading data.', null, ['userinfo'=>$userinfo, 'privileges'=>$privileges]);
    }
    public function login_oper($userinfo) {
        // 设置登录后的session
        session('user_id', $userinfo['user_id']);
        // 用户权限
        $Privilege = db('privilege');
        $privilegelist = $Privilege->where('user_id', $userinfo['user_id'])->field(['rightstr', 'defunct'])->select();
        $ret = [];
        $privilege_session = [];
        foreach($privilegelist as $privilege) {
            $privilege_session[$privilege['rightstr']] = $privilege['defunct'];
            if(array_key_exists($privilege['rightstr'], $this->OJ_ADMIN['OJ_ADMIN_LIST'])) {
                $ret[$privilege['rightstr']] = true;
            }
        }
        // 用户信息
        session('login_user_info', [
            'team_id'   => $userinfo['user_id'],
            'name'      => $userinfo['nick'],
            'tmember'   => '',
            'coach'     => '',
            'school'    => $userinfo['school'],
            'room'      => ''
        ]);
        session('login_user_privilege', $privilege_session);
        return $ret;
    }
    public function add_loginlog($user_id, $success)
    {
        $ip = GetRealIp();
        $time = date("Y-m-d H:i:s");
        db('loginlog')->insert(
            [
                'user_id'=>$user_id,
                //暂时用password字段作是否登录成功标记用。因为password计算依赖原密码salt，这里存储没有意义
                'password' => $success,
                'ip' => $ip,
                'time'=> $time
            ]);
        db('users')
            ->where('user_id', $user_id)
            ->update(['ip'=>$ip, 'accesstime'=>$time]);
    }
    public function logout_ajax()
    {
        if(!session('?user_id')) {
            $this->error('User already logged out.');
        }
        $this->update_user_solved_submit(session('user_id'));
        
        session('login_user_privilege', null);
        session('login_user_info', null);
        session('user_id', null);
        $this->success('Logout Successful!<br/>Reloading data.');
    }
    public function userinfo()
    {
        // 用户信息页
        $user_id = trim(input('user_id'));
        if($user_id == null || strlen($user_id) == 0) {
            $user_id = session('user_id');
        }
        if($user_id == null || strlen($user_id) == 0) {
            $this->error('You find a 404 ^_^');
        }
        $userinfo = $this->get_userinfo($user_id, false);
        if(!$userinfo)
        {
            $this->error('No such user', null, '', 1);
        }
        if($this->OJ_MODE == 'cpcsys' || session('?user_id') && session('user_id') == $userinfo['baseinfo']['user_id'] || IsAdmin('administrator'))
        {
            //只有用户本人或管理员可以看登录日志
            $loginlog = db('loginlog')->where('user_id', $userinfo['baseinfo']['user_id'])->order('time', 'desc')->limit(10)->select();
            $this->assign('loginlog', $loginlog);
        }
        $this->assign($userinfo);
        return $this->fetch();
    }
    public function get_userinfo($user_id, $simple=true)
    {
        // 用户信息页要显示的用户数据
        $userinfo = [];
        $Users = db('users');
        $userinfo['baseinfo'] = $Users
            ->where('user_id', $user_id)
            ->field('user_id, email, submit, solved, ip, accesstime, reg_time, nick, school, volume')
            ->find();
        if(!$userinfo['baseinfo']) {
            return null;
        }
        if(!$simple)
        {
            // 获取更多数据
            $userinfo = array_merge($userinfo, $this->update_user_solved_submit($user_id));
            $Solution = db('solution');
            $userinfo['solvedlist'] = $Solution
                ->where([
                    'user_id' => $user_id,
                    'result'  => 4,
                    'problem_id'      => ['gt', 1],
                ])
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->field('problem_id')
                ->distinct(true)
                ->order('problem_id asc')
                ->select();
            $tmpTriedlist = $Solution
                ->where([
                    'user_id'         => $user_id,
                    'problem_id'      => ['gt', 1],
                ])
                ->where(function($query){
                    $query->whereNull('contest_id')
                        ->whereOr('contest_id', 0);
                })
                ->field('problem_id')
                ->distinct(true)
                ->order('problem_id asc')
                ->select();
            $i = 0;
            $j = 0;
            $solvedCnt = count($userinfo['solvedlist']);
            $triedCnt = count($tmpTriedlist);
            $userinfo['triedlist'] = [];
            for(; $j < $triedCnt; $j ++)
            {
                while($i < $solvedCnt && $userinfo['solvedlist'][$i]['problem_id'] < $tmpTriedlist[$j]['problem_id'])
                    $i ++;
                if($i < $solvedCnt && $userinfo['solvedlist'][$i]['problem_id'] == $tmpTriedlist[$j]['problem_id'])
                    continue;
                $userinfo['triedlist'][] = $tmpTriedlist[$j];
            }
            $userinfo['rank'] = $Users
                ->where('solved', 'gt', $userinfo['solved'])
                ->whereOr(function($query)use ($userinfo) {
                    $query->where(['solved' => $userinfo['solved'], 'submit' => ['lt', $userinfo['submit']]]);
                })  //ThinkPHP的 and or 条件也是神烦。
                ->count() + 1;
        }
        return $userinfo;

    }
    public function update_user_solved_submit($user_id)
    {
        // 更新数据库用户条目里的ac题数和提交数，这个在用户总rank里会使用，不可能每次调rank都更新全部用户。碎片化更新。
        $userinfo = [];
        $Solution = db('solution');
        $userinfo['solved'] = $Solution
            ->where([
                'user_id'     => $user_id,
                'result'      => 4,
            ])
            ->where(function($query){
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->count('DISTINCT problem_id');//这里似乎是ThinkPHP的老毛病了，目前只能把'DISTINCT'写到count里面，用->distinct加->field('problem_id')都统计不到想要的。

        $userinfo['submit'] = $Solution
            ->where([
                'user_id' => $user_id,
                'problem_id' => ['gt', 0],
            ])
            ->where(function($query){
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->field('solution_id')
            ->count();
        db('users')
            ->where('user_id', $user_id)
            ->update(['solved'=>$userinfo['solved'], 'submit'=>$userinfo['submit']]);

        return $userinfo;
    }

    public function modify()
    {
        $userinfo = $this->modify_func();
        if($userinfo['baseinfo']['user_id'] != session('user_id') && !IsAdmin('password_setter'))
            $this->error('Powerless');
        $this->assign([
            'is_self'           => $userinfo['baseinfo']['user_id'] == session('user_id'),
            'target_user_id'    => $userinfo['baseinfo']['user_id'],
            'is_password_setter'=> IsAdmin('password_setter')
        ]);
        return $this->fetch();
    }
    public function modify_func()
    {
        // 修改用户信息
        $user_id = trim(input('user_id'));
        if($user_id == null || strlen($user_id) == 0)
            $user_id = session('user_id');
        else if(!session('?user_id'))
            $this->error('Please login before modify your information');
        if($user_id == null || strlen($user_id) == 0)
        {
            $this->error('Who are you?');
        }
        $userinfo = $this->get_userinfo($user_id);
        if(!$userinfo)
        {
            $this->error('No such user');
        }
        $this->assign($userinfo);
        return $userinfo;
    }
    public function modify_ajax() {
        $this->modify_ajax_func(input('post.'));
    }
    public function modify_permission($target_user_id) {

        $userprivilege = db('privilege')->where(['user_id' => $target_user_id, 'rightstr' => ['in', array_keys($this->OJ_ADMIN['OJ_ADMIN_LIST'])]])->select();
        // administrator可以改其他管理员密码，super_admin可以改administrator密码
        if (!count($userprivilege)) {
            return true;
        }
        foreach ($userprivilege as $privilege) {
            if (
                //password_setter不可改任何有权限的帐号密码
                !IsAdmin('administrator') ||
                //非super_admin不可改administrator密码
                ($privilege['rightstr'] == 'administrator' && !IsAdmin('super_admin')) ||
                //谁都不可以在这里改super_admin密码
                $privilege['rightstr'] == 'super_admin'
            ) {
                return false;
            }
        }
    }
    public function modify_ajax_func($inputInfo)
    {
        if(!IsAdmin('password_setter')) {
            if(!captcha_check(Dget($inputInfo, 'vcode', '')))
                $this->error('Verification Code Error');
        }
        $user_id = trim(Dget($inputInfo, 'user_id', ''));
        $password = trim(Dget($inputInfo, 'password', ''));
        if(Dget($inputInfo, 'user_id') == null) {
            $this->error('User ID needed.');
        }
        else if($user_id != session('user_id')) {
            // 只有 'password_setter' 以上管理员才能修改他人信息
            if (IsAdmin('password_setter')) {
                if(!$this->modify_permission($user_id)) {
                    $this->error('You cannot change the information of an administrator.');
                }
            }
            else {
                $this->error('You have no permission to modify this user.');
            }
        }
        $Users = db('users');
        $userinfo = $Users->where('user_id', $user_id)->find();
        if($userinfo == null)
            $this->error('No such user');

        if ((!IsAdmin('password_setter') || $user_id == session('user_id')) && !CkPasswd($password, $userinfo['password'])) {
            // 如果既{不是管理员 或 修改的是自己}，又无法正确验证自己的密码，则无法修改信息
            $this->error('Verify password failed');
        }
                
        if(!$this->OJ_STATUS=='exp') {
            $userinfo['nick'] = trim(Dget($inputInfo, 'nick', $userinfo['nick']));
            $userinfo['school'] = trim(Dget($inputInfo, 'school', $userinfo['school']));
        }
        // exp模式下仅能修改email
        $userinfo['email'] = trim(Dget($inputInfo, 'email', $userinfo['email']));
        if($this->OJ_STATUS=='exp') {
            $configName = 'ExpsysConfig';
        }
        else {
            $configName = 'CsgojConfig';
        }
        $validate = new Validate(config($configName . '.userinfo_rule'), config($configName . '.userinfo_msg'));
        if(!$validate->check($userinfo))
            $this->error($validate->getError());

        $new_password = trim(Dget($inputInfo, 'new_password', ''));
        $confirm_new_password = trim(Dget($inputInfo, 'confirm_new_password', ''));
        if($new_password != null && strlen($new_password) > 0) {
            if($new_password != $confirm_new_password) {
                $this->error('New password not confirmed right.');
            }
            if(strlen($new_password) < 6 || strlen($new_password) > 255) {
                $this->error('Password should in 6~255 characters.');
            }
            $userinfo['password'] = MkPasswd($new_password);
        }
        $Users->where('user_id', $user_id)->update($userinfo);

        $this->success('Successfully updated<br/>Redirecting to User Infomation Page.', '', ['user_id' => $userinfo['user_id']]);
    }
    public function register()
    {
        if($this->OJ_MODE != 'online') {
            $this->error('Registration is prohibited.');
        }
        //用户注册
        if(session('?user_id'))
            $this->error('User already logged in.', null, '', 1);
        return $this->fetch();
    }
    public function register_ajax()
    {
        if($this->OJ_MODE != 'online') {
            $this->error('Registration is prohibited.');
        }
        if(!captcha_check(input('vcode')))
            $this->error('Verification Code Error');
        $user_id = trim(input('user_id'));
        if(session('?user_id'))
        {
            $this->error('Please logout first.');
            return;
        }
        if(input('user_id') == null)
        {
            $this->error('User ID needed.');
            return;
        }
        $Users = db('users');
        $userinfo = $Users->where('user_id', $user_id)->find();
        if($userinfo != null)
        {
            $this->error('User ID already exists.');
            return;
        }
        $userinfo = [
            'user_id'     => $user_id,
            'nick'         => trim(input('nick')),
            'school'     => trim(input('school')),
            'email'        => trim(input('email')),
            'password'    => trim(input('password')),
            'reg_time'    => date('Y-m-d H:i:s'),
            'accesstime'=> date('Y-m-d H:i:s'),
        ];
        $validate = new Validate(config('CsgojConfig.userinfo_rule'), config('CsgojConfig.userinfo_msg'));
        if(!$validate->check($userinfo))
        {
            $data['userinfo'] = $userinfo;
            $this->error($validate->getError(), null, $data);
            return;
        }
        $confirm_password = trim(input('confirm_password'));
        if($userinfo['password'] != $confirm_password)
        {
            $this->error('Two password not same.');
            return;
        }
        $userinfo['password'] = MkPasswd($userinfo['password']);
        $Users->insert($userinfo);
        $this->login_oper($userinfo);
        $this->add_loginlog($userinfo['user_id'], 1);
        $this->success("Succesfully Registered!<br/>Redirecting to User Infomation Page.", '', ['user_id' => $userinfo['user_id']]);
    }

    /**************************************************/
    //Pass Back
    /**************************************************/
    public function passback()
    {
        if(session('?user_id'))
        {
            $this->error('You are already logged in.');
        }
        return $this->fetch();
    }

    public function passback_ajax()
    {
        if(!captcha_check(input('vcode')))
            $this->error('Verification Code Error');
        if(session('?user_id'))
        {
            $this->error('You are already logged in.');
        }
        $user_id = trim(input('user_id', ''));
        if(strlen($user_id) < 5 || strlen($user_id) > 20)
            $this->error('User ID not valid');

        $userinfo = db('users')->where('user_id', $user_id)->find();
        if(!$userinfo)
            $this->error('No such user');

        cache(config('CsgojConfig.OJ_PASSBACK_CACHE_OPTION'));
        $passbackInfo = cache('_passback_' . $userinfo['user_id']);
        $now = time();
        if($passbackInfo)
        {
            $this->error('Passback email has already been sent.<br/>You can apply another passback email after ' . intval((1200 - ($now - $passbackInfo['time'])) / 60) . ' minutes');
        }

        $mailConfig = config('MailConfig');
        if(!filter_var($userinfo['email'], FILTER_VALIDATE_EMAIL))
            $this->error('Your email seems not valid.');
        $passbackInfo = [
            'time'     => $now,
            'token'    => md5($now . $userinfo['user_id'] . rand()),
        ];
        $content = "Click the link below to retrieve your password of Online Judge. Valid in 20 minutes.<br/>".
            "<a href='" . $mailConfig['passback_url'] . "?user_id=".$userinfo['user_id']."&token=".$passbackInfo['token']."'>Retrieve Password</a><br/>";

        if(!SendMail($userinfo['email'], $userinfo['user_id'], 'CPC Online Judge Password Retrieve', $content))
            $this->error('Mail send failed. You can join QQ group 1065953958 and ask in the group (don\'t send private message to any admin). The admin who is online could help you.');

        cache('_passback_' . $userinfo['user_id'], $passbackInfo);
        $this->success('Passback mail sent by <code>'. $mailConfig['account'].'</code>. Please check it in your email (<code>' .$userinfo['email'].'</code>)' );
    }
    public function passback_retrieve()
    {
        if(session('?user_id'))
        {
            $this->error('You are already logged in.');
        }
        $user_id = trim(input('user_id', ''));
        $userinfo = db('users')->where('user_id', $user_id)->find();
        if(!$userinfo)
            $this->error('No such user');

        $token = trim(input('token', ''));
        cache(config('CsgojConfig.OJ_PASSBACK_CACHE_OPTION'));
        $passbackInfo = cache('_passback_' . $userinfo['user_id']);
        $authOk = false;
        if($passbackInfo && $token == $passbackInfo['token'])
            $authOk = true;
        $this->assign('authOk', $authOk);
        $this->assign('user_id', $userinfo['user_id']);
        $this->assign('token', $passbackInfo['token']);
        return $this->fetch();
    }
    public function passback_retrieve_ajax()
    {
        if(session('?user_id'))
        {
            $this->error('You are already logged in.');
        }
        $user_id = trim(input('user_id', ''));
        $userinfo = db('users')->where('user_id', $user_id)->find();
        if(!$userinfo)
            $this->error('No such user');

        $token = trim(input('token', ''));
        cache(config('CsgojConfig.OJ_PASSBACK_CACHE_OPTION'));
        $passbackInfo = cache('_passback_' . $userinfo['user_id']);
        if(!$passbackInfo || $token != $passbackInfo['token'])
            $this->error('Token validation failed');
        $password = trim(input('password'));
        if(strlen($password) < 6)
            $this->error('Password too short');
        else if(strlen($password) > 64)
            $this->error('Password too long');
        $confirm_password = trim(input('confirm_password'));
        if($password != $confirm_password)
            $this->error('Two password not same');
        $password = MkPasswd($password);
        db('users')->where('user_id', $userinfo['user_id'])->setField('password', $password);
        cache('_passback_' . $userinfo['user_id'], NULL);
        $this->success("Password succesfully reset!");
    }

    /**************************************************/
    //Mail
    /**************************************************/
    public function MailAuth()
    {
        if($this->OJ_MODE != 'online')
        {
            $this->error('Module not allowed');
        }
        if(!session('?user_id'))
            $this->error("Please login first.", '/');
        $this->assign('user_id', session('user_id'));
        if($this->OJ_MODE != 'online')
        {
            $this->error('Module not allowed');
        }
    }
    public function mail_inbox()
    {
        $this->MailAuth();
        $this->assign('type', 'inbox');
        return $this->fetch();

    }
    public function mail_outbox()
    {
        $this->MailAuth();
        $this->assign('type', 'outbox');
        return $this->fetch();

    }
    public function mail_add()
    {
        $this->MailAuth();
        return $this->fetch();
    }
    public function mail_add_ajax()
    {
        $this->MailAuth();
        if(!captcha_check(input('vcode')))
            $this->error('Verification Code Error');
        $user_id = session('user_id');
        $to_user = trim(input('user_id', ''));
        if(strlen($to_user) > 32)
        {
            $this->error('User ID too long');
        }
        $to_userinfo = db('users')->where('user_id', $to_user)->field('user_id')->find();
//        if(strtolower($user_id) == strtolower($to_userinfo['user_id']))
//            $this->error('You cannot send mail to your self');
        if(!$to_userinfo)
            $this->error('No such user');
        $title = trim(input('title', ''));
        if(strlen($title) < 1)
            $this->error('Title needed');
        else if(strlen($title) > 64)
            $this->error('Title too long');
        $content = trim(input('content', ''));
        if(strlen($content) > 16384)
            $this->error('Content too long');
        $mail_add = [
            'from_user' => $user_id,
            'to_user'    => $to_userinfo['user_id'],
            'title'        => $title,
            'content'    => $content,
            'reply'        => -1,
            'in_date'    => date("Y-m-d H:i:s"),
            'defunct'    => '0',
            'new_mail'    => 01,
        ];
        $mail_id = db('mail')->insertGetId($mail_add);
        $this->success('Mail sent!', null, '', ['mail_id' => $mail_id]);
    }
}
