<?php
namespace app\ojtool\controller;
use app\csgoj\controller\User as UserBase;
class Sso extends UserBase {
    function InitController() {
        if($this->OJ_SSO == false) {
            $this->error("No sso config found.");
        }
    }
    public function sso_start() {
        if(session('?user_id')) {
            $this->error("用户已登录");
        }
        $this->redirect($this->OJ_SSO . '/sso/ssologin?sclient_id=' . $this->OJ_SCLIENT_ID);
    }
    public function sso_callback() {
        $access_token = input('access_token/s');
        $ret = RequestSend($this->OJ_SSO . '/api/token', [], ['Content-Type: application/json', "Authorization: Bearer " . $access_token], 0);
        if($ret['status_code'] != 200) {
            return $this->display('<h1>' . $ret['detail'] . '</h1><a href="/">回到首页</a>');
        } else {
            $userInfo = $ret['user'];
            $this->oauth2_login_func($userInfo);
        }
    }
    public function sso_logout() {
        if(session('?sso_login')) {
            session(null);
            $this->redirect($this->OJ_SSO . '/sso/ssologout?logout_redirect=/index&sclient_id=' . $this->OJ_SCLIENT_ID);
        } else {
            session(null);
            $this->redirect('/index');
        }
    }
    public function ProcessSchool($sc) {
        $sc = trim($sc, '/');
        $sc = explode('/', $sc);
        $sc = end($sc);
        return $sc;
    }
    public function sso_direct_ajax() {
        if(session('?user_id')) {
            $this->error('用户已登录，可尝试刷新页面');
        }
        $user_id = trim(input('user_id/s'));
        $password = trim(input('password/s'));
        if($user_id == null || strlen($user_id) == 0) {
            $this->error('请求数据不合法');
        }
        $userinfo = [];
        try {
            $User = db('users');
            $ret = RequestSend($this->OJ_SSO . '/api/token', ['user_id' => $user_id, 'password' => $password], [], 1, 1);
            if($ret['status_code'] != 200) {
                if(array_key_exists('detail', $ret)) {
                    $this->error($ret['detail']);
                } else {
                    $this->error("登录失败");
                }                
            } else {
                $user_sso = $ret['user'];
                $user_local_type = [
                    "user_id"   => $user_sso['user_id'],
                    "nick"      => $user_sso['name'] ?? '',
                    "school"    => $user_sso['school'] ?? '',
                    "email"     => $user_sso['email'] ?? '',
                    "ip"        => $user_sso['ip'] ?? GetRealIp(),
                    "password"  => MkPasswd($password)
                ];
                $user_local_type['school'] = $this->ProcessSchool($user_local_type['school']);
                $local_user = $User->where('user_id', $user_local_type['user_id'])->find();
                if($local_user == null) {
                    $User->insert($user_local_type);
                    $userinfo = $user_local_type;
                } else {
                    unset($user_local_type['password']);
                    unset($user_local_type['user_id']);
                    $local_user = array_merge($local_user, $user_local_type);
                    $User->update($local_user);
                    $userinfo = $local_user;
                }
            }
        } catch (Exception $e) {
            $this->error("请求用户平台失败");
        }

        $userinfo['password'] = '';
        $privileges = $this->login_oper($userinfo);
        session('proxy_login', true);
        $this->add_loginlog($userinfo['user_id'], 1);
        //在登录这种相对低频操作时更新用户资料的ac数和submit数，用于显示总用户rank
        $this->update_user_solved_submit($user_id);

        $this->success('Login successful!<br/>Reloading data.', null, ['userinfo'=>$userinfo, 'privileges'=>$privileges]);
    }
    public function ProcessInfo($info) {
        return $info == null ? '-' : $info;
    }
    public function oauth2_login_func($userInfo) {
        $user_id = $userInfo['user_id'];
        $User = db('users');
        $local_user = $User->where('user_id', $user_id)->find();
        $sso_user = [
            'user_id'   => $user_id,
            'email'     => $this->ProcessInfo($userInfo['email']),
            'password'  => MkPasswd($userInfo['id']),
            'nick'      => $this->ProcessInfo($userInfo['name']),
            'school'    => $this->ProcessInfo($userInfo['school']),
            'ip'        => $this->ProcessInfo($userInfo['ip']),
        ];
        $sso_user['school'] = $this->ProcessSchool($sso_user['school']);
        if($local_user == null) {
            $local_user = $sso_user;
            $User->insert($sso_user);
        } else {
            unset($sso_user['password']);
            $local_user = array_merge($local_user, $sso_user);
            $User->update($local_user);
        }
        $local_user['password'] = '';
        $this->login_oper($local_user);
        session('sso_login', true);
        $this->add_loginlog($local_user['user_id'], 1);
        //在登录这种相对低频操作时更新用户资料的ac数和submit数，用于显示总用户rank
        $this->update_user_solved_submit($user_id);
        // $this->success('Login successful!<br/>Reloading data.', '/', null, 1);
        $this->redirect('/index');
    }
}
