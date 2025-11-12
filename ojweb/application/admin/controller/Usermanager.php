<?php
/**
 * Created by Vscode.
 * User: CSGrandeur
 * Date: 2022/2/11
 * Time: 21:40
 */
namespace app\admin\controller;
use think\Db;
use think\Validate;
class Usermanager extends Adminbase
{

	//***************************************************************//
	// User Manager
	//***************************************************************//
	public function _initialize()
	{
		$this->OJMode();
		$this->AdminInit();
		if(!IsAdmin('password_setter'))
			$this->error("You have no privilege to reset password");
	}
	public function index()
	{
		return $this->fetch();
	}
    // **************************************************
    // User List
	public function password_reset_ajax()
	{
		$user_id = trim(input('passwordreset_user_id'));//用带前缀的input name是避免与登录框name相同，导致前端自动填表功能干扰填写
		if(!isset($user_id) || strlen($user_id) == 0)
			$this->error('Pleas input User ID');
		$password = trim(input('passwordreset_password'));
		if(!isset($password) || strlen($password) < 6 || strlen($password) > 255)
			$this->error('Length of password should between 6 and 255');
		$Users = db('users');
		$userinfo = $Users->where('user_id', $user_id)->find();
		if(!$userinfo)
			$this->error('No such user');
		if($userinfo['user_id'] == session('user_id'))
			$this->error('You can only reset the password of yourself in the user modify page.');

		$userprivilege = db('privilege')->where(['user_id' => $user_id, 'rightstr' => ['in', array_keys($this->ojAdminList)]])->select();

		// administrator可以改其他管理员密码，super_admin可以改administrator密码
		if(count($userprivilege))
		{
			foreach($userprivilege as $privilege)
			{
				if(
					//password_setter不可改任何有权限的帐号密码
					!IsAdmin('administrator') ||
					//非super_admin不可改administrator密码
					($privilege['rightstr'] == 'administrator' && !IsAdmin('super_admin')) ||
					//谁都不可以在这里改super_admin密码
					$privilege['rightstr'] == 'super_admin'
				)
				{
					$this->error('You cannot reset the password of an administrator.');
					return;
				}
			}
		}
		$Users->where('user_id', $user_id)->update(['password'=>MkPasswd($password)]);
		$this->success('Reset password of "'.$user_id.'" successfully.');
	}

    public function user_list_ajax()
    {
        $offset        = intval(input('offset'));
        $limit        = intval(input('limit'));
        $search        = trim(input('search/s'));

        $map = [];
        if(strlen($search) > 0)
            $map['user_id|nick'] =  ['like', "%$search%"];

        // 只查询user_id长度不少于5的用户
		$map[] = ['exp', Db::raw('CHAR_LENGTH(user_id) >= 5')];
        $ret = [];
        $Users = db('users');
        $userlist = $Users
            ->field('user_id, nick, school, email, solved, submit, reg_time')
            ->where($map)
            ->order('reg_time', 'desc')
            ->limit("$offset, $limit")
            ->select();
        $retList = [];
        $i = $offset + 1;
        foreach($userlist as $user)
        {
            $row = [
                'rank'      => $i,
                'user_id'   => $user['user_id'],
                'nick'      => htmlspecialchars($user['nick']),
                'school'    => htmlspecialchars($user['school']),
                'email'     => htmlspecialchars($user['email']),
                'reg_time'  => $user['reg_time'],
                'solved'  => $user['solved'],
                'submit'    => $user['submit'],
                'ratio'     => $user['submit'] == 0 ? '-' : (strval(sprintf("%.3f", floatval($user['solved']) / floatval($user['submit']) * 100)) . "%"),
            ];
            $retList[] = $row;
            $i ++;
        }
        $ret['total'] = $Users->where($map)->count();
        $ret['order'] = 'desc';
        $ret['rows'] = $retList;
        return $ret;
    }
	// **************************************************
	// User Del
	public function user_del_ajax() {
		if(!IsAdmin()) {
			$this->error("No permission");
		}
		$user_id = input('user_id/s');
		if(db('solution')->where('user_id', $user_id)->find()) {
			$this->error("User " . $user_id . "<br/>already submitted some problem.<br/>Could not be deleted.");
		}
		if(db('privilege')->where('user_id', $user_id)->find()) {
			$this->error("User " . $user_id . " has some privileges.<br/>Could not be deleted.");
		}
		db('users')->where('user_id', $user_id)->delete();
		$this->success("User " . $user_id . " deleted");
	}
    // **************************************************
    // User Add
	public function usergen() {
		if(!IsAdmin()) {
			$this->error("No permission");
		}
		return $this->fetch();
	}
	public function usergen_ajax() {
		if(!IsAdmin("super_admin") && $this->OJ_STATUS!='exp') {
			$this->error("User generation is not allowd in this OJ mode.");
		}
		if(!IsAdmin()) {
			$this->error("No permission");
		}
		
		// 获取POST数据
		$userList = input('user_list');
		
		if (!$userList) {
			$this->error('No user data provided');
		}
		
		// 解析JSON数据
		$users = json_decode($userList, true);
		if (!$users || !is_array($users)) {
			$this->error('Invalid user data format');
		}
		
		if(count($users) == 0) {
			$this->error('No users to generate');
		}
		if(count($users) > 100) {
			$this->error('No more than 100 user allowed to generate at once.');
		}
		
		$userToInsert = [];
		$userToShow = [];
		$validate = new Validate(config('CpcSysConfig.userinfo_rule'), config('CpcSysConfig.userinfo_msg'));
		$validateNotList = '';
		
		// 检查user_id重复
		$userIds = [];
		foreach($users as $userData) {
			$userId = $userData['user_id'] ?? '';
			if($userId != '') {
				if(in_array($userId, $userIds)) {
					$this->error("Duplicate user_id found: " . $userId);
				}
				$userIds[] = $userId;
			}
		}
		
		// 检查已存在的用户
		$solutionUserQuery = db('solution')->group('user_id')->field('user_id')->select();
		$solutionUsers = [];
		foreach($solutionUserQuery as $val) {
			$solutionUsers[strtolower($val['user_id'])] = true;
		}
		$privilegeUserQuery = db('privilege')->group('user_id')->field('user_id')->select();
		$privilegeUsers = [];
		foreach($privilegeUserQuery as $val) {
			$privilegeUsers[strtolower($val['user_id'])] = true;
		}
		
		$userIdsInsert = [];
		$userNotUpdateInfo = "";
		$idxNotupdate = 0;
		
		foreach($users as $userData) {
			$nowUser = [];
			
			// 处理user_id（必须提供，不允许为空）
			$userId = $userData['user_id'] ?? '';
			if($userId == '') {
				$this->error('User ID is required for all users');
			}
			$nowUser['user_id'] = $userId;
			
			// 处理其他字段
			$nowUser['nick'] = $userData['nick'] ?? '';
			$nowUser['school'] = $userData['school'] ?? '';
			$nowUser['email'] = $userData['email'] ?? '';
			$nowUser['ip'] = 'localhost';
			
			// 处理密码
			$password = $userData['password'] ?? '';
			if($password == '') {
				$password = RandPass();
			}
			$nowUser['password'] = $password;
			
			$nowUser['reg_time'] = date('Y-m-d H:i:s');
			
			// 验证数据
			if(!$validate->check($nowUser)) {
				$validateNotList .= "<br/>" . $nowUser['user_id'] . ': ' . $validate->getError();
			}
			
			if(strlen($validateNotList) == 0 && 
				!array_key_exists(strtolower($nowUser['user_id']), $solutionUsers) &&
				!array_key_exists(strtolower($nowUser['user_id']), $privilegeUsers)
			) {
				$userToShow[] = $nowUser;
				$nowUser['password'] = MkPasswd($nowUser['password']);
				$userToInsert[] = $nowUser;
				$userIdsInsert[] = $nowUser['user_id'];
			} else {
				$idxNotupdate++;
				$userNotUpdateInfo .= "<br/>" . $idxNotupdate . ". " . $nowUser['user_id'];
			}
		}
		
		if(strlen($validateNotList) > 0) {
			$addInfo = '<br/>Some user information is not valid. Please check.' . $validateNotList;
			$this->error('User generation failed.' . $addInfo);
		}
		
		if(strlen($userNotUpdateInfo) > 0) {
			$userNotUpdateInfo = "<br/>User with submission or privilege not updated:" . $userNotUpdateInfo;
		}
		
		$Users = db('users');
		$success_num = $Users->insertAll($userToInsert, true);
		if(!$success_num) {
			$retInfo = 'No user generated. Users already exist or data input is invalid.';
			if(strlen($userNotUpdateInfo) > 0) {
				$retInfo .= $userNotUpdateInfo;
			}
			$this->error($retInfo);
		}
		$this->success('User successfully generated/updated. See the table below.' . $userNotUpdateInfo, null, ['rows' => $userToShow, 'type' => 'usergen', 'success_num'=> $success_num]);
	}
}