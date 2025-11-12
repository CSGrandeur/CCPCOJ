<?php
/*
 * 评测机管理
 * 2025.10.26
 * CSGrandeur
*/
namespace app\admin\controller;
class Judger extends Adminbase{
    
    /**
     * 验证密码格式
     * @param string $password 密码
     * @return array 返回验证结果
     */
    private function validatePassword($password) {
        if (empty($password)) {
            return ['valid' => true, 'message' => ''];
        }
        
        // 验证长度
        if (strlen($password) < 6 || strlen($password) > 30) {
            return ['valid' => false, 'message' => '密码长度必须在6-30位之间'];
        }
        
        // 验证字符类型（只允许数字和字母）
        if (!preg_match('/^[a-zA-Z0-9]+$/', $password)) {
            return ['valid' => false, 'message' => '密码只能包含数字和字母'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 处理密码（验证并哈希）
     * @param string $password 原始密码
     * @return array 返回处理结果
     */
    private function processPassword($password) {
        if (empty($password)) {
            return ['success' => true, 'hashed_password' => null, 'raw_password' => null];
        }
        
        // 验证密码格式
        $validation = $this->validatePassword($password);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 哈希密码
        $hashedPassword = MkPasswd($password, true);
        
        return [
            'success' => true, 
            'hashed_password' => $hashedPassword, 
            'raw_password' => $password
        ];
    }
    
    public function index() {
        // 获取评测机配置
        $judgerConfig = GetJudgerConfig();
        
        // 获取编程语言配置
        $ojLanguage = config('CsgojConfig.OJ_LANGUAGE');
        
        // 传递配置到前端
        $this->assign('judgerConfig', $judgerConfig);
        $this->assign('ojLanguage', $ojLanguage);
        
        return $this->fetch();
    }
    public function update_judger_config_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        // 获取JSON字符串
        $jsonString = input('post.judger_config');
        
        if (empty($jsonString)) {
            $this->error('没有接收到配置数据');
        }
        
        // 解析JSON字符串
        $configData = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('配置数据格式错误：' . json_last_error_msg());
        }
        
        if (!is_array($configData)) {
            $this->error('配置数据必须是对象格式');
        }
        
        // 调试信息
        error_log('Judger config JSON: ' . $jsonString);
        error_log('Judger config data: ' . json_encode($configData));
        
        // 调用保存配置函数
        $result = SetJudgerConfig($configData);
        
        if ($result['success']) {
            $this->success($result['message'], '', $result['data']);
        } else {
            $this->error($result['message']);
        }
    }
    
    public function get_default_config_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        // 获取默认配置
        $defaultConfig = GetJudgerConfig(true);
        
        $this->success('获取默认配置成功', '', $defaultConfig['config']);
    }
    
    public function judger_list_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        // 两表联查：privilege表 left join users表
        $list = db('privilege')
            ->alias('p')
            ->join('users u', 'p.user_id = u.user_id', 'left')
            ->field([
                    'u.user_id',
                    'u.email as pro_list',
                    'u.volume as flg_white',
                    'u.defunct',
                    'u.language as langmask',
                    'u.accesstime',
                    'u.ip',
                    'u.password'
                ])
            ->where('p.rightstr', 'judger')
            ->order('p.user_id', 'asc')
            ->select();
        
        foreach($list as &$judger) {
            
            // 将langmask转换为langlist
            $langMask = intval($judger['langmask']);
            $judger['langlist'] = LangMask2LangList($langMask, 'origin'); // 不转小写，保持原样
            
            // 解密密码（如果存在）
            if (!empty($judger['password'])) {
                $judger['password'] = RecoverPasswd($judger['password']);
            }
            
            // 设置删除权限（管理员可以删除评测机权限）
            $judger['can_delete'] = true;
        }
        
        return $list;
    }
    
    public function judger_add_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('post.user_id');
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        
        // 验证用户ID格式：至多2位字母前缀，后跟至多2位数字
        if (!preg_match('/^[a-z]{1,2}\d{1,2}$/', $user_id)) {
            $this->error('用户ID格式不正确，应为1-2位小写字母+1-2位数字');
        }
        
        // 验证用户ID长度
        if (strlen($user_id) > 4) {
            $this->error('用户ID长度不能超过4位');
        }
        
        // 检查评测机总数限制（最多99个）
        $judgerCount = db('privilege')
            ->where('rightstr', 'judger')
            ->count();
        
        if ($judgerCount >= 99) {
            $this->error('评测机总数已达到上限（99个），无法继续添加');
        }
        
        // 获取自定义密码
        $customPassword = input('post.custom_password', '');
        
        // 处理密码
        if (!empty($customPassword)) {
            $passwordResult = $this->processPassword($customPassword);
            if (!$passwordResult['success']) {
                $this->error($passwordResult['message']);
            }
            $rawPassword = $passwordResult['raw_password'];
            $hashedPassword = $passwordResult['hashed_password'];
        } else {
            // 生成随机密码
            $rawPassword = RandPass(8);
            $hashedPassword = MkPasswd($rawPassword, true); // 使用可还原密码
        }
        
        // 获取配置数据（如果有的话）
        $pro_list = input('post.pro_list', '');
        $flg_white = input('post.flg_white', 0);
        $defunct = input('post.defunct', 0);
        $language = input('post.language', 0);
        
        // 准备用户数据
        $userData = [
            'user_id'   => $user_id,
            'email'     => $pro_list,  // 题目限制存储在email字段
            'defunct'   => $defunct,   // 启用状态
            'ip'        => 'localhost',
            'volume'    => $flg_white, // 黑白名单标志存储在volume字段
            'language'  => $language,  // 语言掩码
            'password'  => $hashedPassword,
            'reg_time'  => date('Y-m-d H:i:s'),
            'nick'      => 'judger',
            'school'    => 'CSGOJ'
        ];
        
        // 插入用户数据（使用insert方法，如果主键存在则更新）
        $userResult = db('users')->insert($userData, true); // true表示replace模式
        
        if (!$userResult) {
            $this->error('用户数据插入失败');
        }
        
        // 检查是否已有judger权限
        $existingPrivilege = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->find();
        
        if (!$existingPrivilege) {
            // 插入judger权限
            $privilegeResult = db('privilege')->insert([
                'user_id' => $user_id,
                'rightstr' => 'judger'
            ]);
            
            if (!$privilegeResult) {
                $this->error('权限数据插入失败');
            }
        }
        
        // 准备返回的用户数据（使用前端友好的字段名）
        $returnData = [
            'user_id' => $userData['user_id'],
            'pro_list' => $userData['email'],     // email -> pro_list
            'flg_white' => $userData['volume'],   // volume -> flg_white
            'defunct' => $userData['defunct'],
            'langlist' => LangMask2LangList($userData['language'], 'origin'), // 转换语言掩码为语言列表
            'accesstime' => null,
            'ip' => $userData['ip'],
            'password' => $rawPassword, // 明文密码用于返回
            'can_delete' => true
        ];
        
        $this->success('评测机添加成功', null, $returnData);
      
    }
    
    public function judger_delete_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('post.user_id');
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        // 首先检查该用户是否确实有judger权限
        $judgerPrivilege = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->find();
            
        if (!$judgerPrivilege) {
            $this->error('非评测机，无法删除');
        }
        
        // 删除评测机权限
        $result = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->delete();
        
        if ($result) {
            $this->success('评测机权限删除成功');
        } else {
            $this->error('评测机权限删除失败');
        }
    }
    
    public function judger_config_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('post.user_id');
        
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        
        // 验证用户是否有judger权限
        $judgerPrivilege = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->find();
            
        if (!$judgerPrivilege) {
            $this->error('非评测机，无法配置');
        }
        
        // 只更新提交的字段
        $updateData = [];
        
        // 检查并处理题目列表
        if (input('?post.pro_list')) {
            $pro_list = input('post.pro_list', '');
            
            // 验证题目列表格式
            if (!empty($pro_list)) {
                $proArray = explode(',', $pro_list);
                foreach ($proArray as $proId) {
                    $proId = trim($proId);
                    if (!empty($proId) && !preg_match('/^\d{4}$/', $proId)) {
                        $this->error('题目ID格式不正确，应为4位数字：' . $proId);
                    }
                }
            }
            $updateData['email'] = $pro_list;  // 题目列表存储在email字段
        }
        
        // 检查并处理黑白名单标志
        if (input('?post.flg_white')) {
            $flg_white = input('post.flg_white');
            
            // 验证黑白名单标志
            if (!in_array($flg_white, [0, 1])) {
                $this->error('黑白名单标志值无效');
            }
            $updateData['volume'] = intval($flg_white); // 黑白名单标志存储在volume字段
        }
        
        // 检查并处理启用状态标志
        if (input('?post.defunct')) {
            $defunct = input('post.defunct');
            
            // 验证启用状态标志
            if (!in_array($defunct, [0, 1])) {
                $this->error('启用状态标志值无效');
            }
            $updateData['defunct'] = intval($defunct); // 启用状态存储在defunct字段
        }
        
        // 检查并处理语言掩码
        if (input('?post.language')) {
            $language = input('post.language');
            
            // 验证语言掩码
            if (!is_numeric($language) || $language < 0) {
                $this->error('语言掩码值无效');
            }
            $updateData['language'] = intval($language); // 语言掩码存储在language字段
        }
        
        // 检查并处理密码修改
        if (input('?post.password')) {
            $password = input('post.password', '');
            
            if (!empty($password)) {
                $passwordResult = $this->processPassword($password);
                if (!$passwordResult['success']) {
                    $this->error($passwordResult['message']);
                }
                $updateData['password'] = $passwordResult['hashed_password'];
            }
        }
        
        // 如果没有要更新的字段，返回成功
        if (empty($updateData)) {
            $this->success('没有需要更新的配置');
        }
        
        // 更新用户配置
        $result = db('users')
            ->where('user_id', $user_id)
            ->update($updateData);
        
        if ($result !== false) {
            $this->success('评测机配置保存成功');
        } else {
            $this->error('评测机配置保存失败');
        }
    }
    
    public function judger_login_log_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('get.user_id');
        
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        
        // 验证用户是否有judger权限
        $judgerPrivilege = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->find();
            
        if (!$judgerPrivilege) {
            $this->error('非评测机，无法查看登录日志');
        }
        
        // 查询最近20次登录日志
        $logList = db('loginlog')
            ->where('user_id', $user_id)
            ->field(['success', 'ip', 'time'])
            ->order('time', 'desc')
            ->limit(20)
            ->select();
        
        // 格式化时间显示
        foreach ($logList as &$log) {
            if ($log['time']) {
                $log['time'] = date('Y-m-d H:i:s', strtotime($log['time']));
            }
        }
        
        $this->success('获取登录日志成功', '', $logList);
    }
    
    public function del_judger_pro_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('post.user_id');
        $problem_id = input('post.problem_id');
        
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        
        if (empty($problem_id)) {
            $this->error('题号不能为空');
        }
        
        // 验证题号格式（4位数字）
        if (!preg_match('/^\d{4}$/', $problem_id)) {
            $this->error('题号格式不正确，应为4位数字');
        }
        
        // 验证评测机并获取题目列表
        $judgerData = $this->validateJudgerAndGetProList($user_id);
        $currentProList = $judgerData['pro_list'] ?: '';
        
        if (empty($currentProList)) {
            $this->error('该评测机没有题目限制');
        }
        
        // 解析题目列表
        $problemList = $this->parseProblemList($currentProList);
        
        // 检查题号是否存在
        if (!in_array($problem_id, $problemList)) {
            $this->error('题目列表中不存在该题号');
        }
        
        // 从列表中移除指定题号
        $problemList = array_values(array_filter($problemList, function($id) use ($problem_id) {
            return $id !== $problem_id;
        }));
        
        // 更新题目列表
        $newProList = implode(',', $problemList);
        
        $result = db('users')
            ->where('user_id', $user_id)
            ->update(['email' => $newProList]);
        
        if ($result !== false) {
            $data = [
                'user_id' => $user_id,
                'problem_id' => $problem_id,
                'remaining_problems' => $problemList,
                'remaining_count' => count($problemList)
            ];
            $this->success('题目删除成功', '', $data);
        } else {
            $this->error('题目删除失败');
        }
    }
    
    /**
     * 获取评测机题目列表
     */
    public function get_judger_pro_list_ajax() {
        // 检查权限
        if (!IsAdmin('administrator')) {
            $this->error('权限不足');
        }
        
        $user_id = input('post.user_id');
        
        if (empty($user_id)) {
            $this->error('用户ID不能为空');
        }
        
        // 验证评测机并获取题目列表
        $judgerData = $this->validateJudgerAndGetProList($user_id);
        $proList = $judgerData['pro_list'] ?: '';
        
        if (empty($proList)) {
            $data = [
                'user_id' => $user_id,
                'pro_list' => [],
                'problem_titles' => [],
                'count' => 0
            ];
        } else {
            // 解析题目列表
            $problemList = $this->parseProblemList($proList);
            
            // 查询题目标题
            $problemTitles = $this->getProblemTitles($problemList);
            
            $data = [
                'user_id' => $user_id,
                'pro_list' => $problemList,
                'problem_titles' => $problemTitles,
                'count' => count($problemList)
            ];
        }
        
        $this->success('获取题目列表成功', '', $data);
    }
    
    /**
     * 验证评测机权限并获取题目列表
     */
    private function validateJudgerAndGetProList($user_id) {
        // 验证用户是否有judger权限
        $judgerPrivilege = db('privilege')
            ->where(['user_id' => $user_id, 'rightstr' => 'judger'])
            ->find();
            
        if (!$judgerPrivilege) {
            $this->error('非评测机，无法操作');
        }
        
        // 获取题目列表
        $judgerData = db('users')
            ->where('user_id', $user_id)
            ->field(['email as pro_list'])
            ->find();
            
        if (!$judgerData) {
            $this->error('评测机不存在');
        }
        
        return $judgerData;
    }
    
    /**
     * 解析题目列表字符串为数组
     */
    private function parseProblemList($proListStr) {
        $problemList = array_map('trim', explode(',', $proListStr));
        return array_filter($problemList); // 移除空值
    }
    
    /**
     * 获取题目标题
     */
    private function getProblemTitles($problemList) {
        $problemTitles = [];
        if (!empty($problemList)) {
            $validProblemIds = array_filter($problemList, function($id) {
                return preg_match('/^\d{4}$/', $id);
            });
            
            if (!empty($validProblemIds)) {
                $titles = db('problem')
                    ->where('problem_id', 'in', $validProblemIds)
                    ->field(['problem_id', 'title'])
                    ->select();
                
                foreach ($titles as $title) {
                    $problemTitles[$title['problem_id']] = $title['title'];
                }
            }
        }
        return $problemTitles;
    }
}