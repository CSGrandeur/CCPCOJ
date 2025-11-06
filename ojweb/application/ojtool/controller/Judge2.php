<?php
/**
 * CSGOJ 评测机接口
 * CSGrandeur @ 2025-10-28
 */

namespace app\ojtool\controller;

class Judge2 extends Ojtoolbase
{
    var $judge_user;
    var $auto_rejudge_sec = 900;
    
    public function _initialize(){
        $this->OJMode();
        $this->JudgeInit();
    }
    protected function AuthFail() {
        // judger 独立鉴权，不使用全局方案，确保评测权限控制的及时性
        if(IsAdmin('super_admin')) {
            return 0;    // 超管允许访问judge接口
        }
        if(!$this->IsLogin()) {
            return '未登录 / Need Login';
        }
        $user_id = $this->GetLoginJudger();
        $judger_privilege = db('privilege')->where(['user_id' => $user_id, 'rightstr' => 'judger'])->cache(30)->find();
        if(!$judger_privilege) {
            return '无权限 / No Permission';
        }
        $judger_defunct = db('users')->where('user_id', $user_id)->field(['user_id', 'defunct'])->cache(30)->find();
        if($judger_defunct['defunct'] == 1) {
            return '已停用 / Disabled';
        }
        return 0;
    }
    public function index() {
        return $this->response(true, "OK", null, 'OK');
    }
    protected function JudgeInit(){
        if($this->action == 'judge_login') {
            return;
        }
        // 同步Judge.php的鉴权逻辑
        $auth_fail_msg = $this->AuthFail();
        if($auth_fail_msg) {
            print_r(IsAdmin('super_admin') ? 1 : 2);
            return $this->response(false, $auth_fail_msg, null, 'AUTH_FAILED');
        }
        $this->judge_user = $this->GetLoginJudger();
    }
    
    /**
     * 获取JSON请求体数据
     * ThinkPHP5.0最佳实践：使用input()方法自动解析JSON请求体
     * 支持调试工具发送的json_data参数
     */
    private function getJsonData()
    {
        // 优先检查调试工具发送的json_data参数
        $jsonData = $this->request->param('json_data');
        if (!empty($jsonData)) {
            $data = json_decode($jsonData, true);
            if ($data !== null) {
                // 调试信息（仅在开发环境）
                if (config('app_debug')) {
                    \think\Log::info('调试工具JSON数据: ' . $jsonData);
                }
                return $data;
            }
        }
        
        // 检查Content-Type是否为application/json
        $contentType = $this->request->header('content-type');
        if (strpos($contentType, 'application/json') === false) {
            // 如果不是JSON请求，返回空数组
            return [];
        }
        
        // 直接读取原始请求体并解析JSON
        $rawData = $this->request->getContent();
        $data = json_decode($rawData, true);
        
        // 如果JSON解析失败，尝试使用ThinkPHP的input方法
        if ($data === null) {
            $data = input('param.');
        }
        
        // 调试信息（仅在开发环境）
        if (config('app_debug')) {
            \think\Log::info('JSON请求数据: ' . json_encode($data));
            \think\Log::info('原始请求体: ' . $rawData);
        }
        
        return $data ?: [];
    }
    
    /**
     * 语言编号转评测机支持的名称
     */
    private function getLanguageName($language_id){
        $lang_map = config('CsgojConfig.OJ_LANGUAGE_NORMALIZED');
        return $lang_map[$language_id] ?? 'unknown';
    }
    
    /**
     * 评测结果名称转编号
     */
    private function getResultId($result_name){
        $results = config('CsgojConfig.OJ_RESULTS');
        $result_map = array_flip($results);
        return $result_map[$result_name] ?? 90;  // 默认返回JF (90) - 评测失败
    }
    
    
    /**
     * 评测机登录接口
     */
    public function judge_login(){
        if($this->IsLogin()) {
            return $this->response(true, "已登录", ['user_id' => $this->GetLoginJudger()]);
        }
        
        // 从JSON请求体获取参数
        $data = $this->getJsonData();
        $user_id = trim($data['user_id'] ?? '');
        $password = trim($data['password'] ?? '');
        
        if (empty($user_id)) {
            return $this->response(false, "用户名不能为空", null, 'INVALID_PARAMS');
        }
        
        // 检查用户是否存在
        $userinfo = db('users')->where('user_id', $user_id)->find();
        if (!$userinfo) {
            return $this->response(false, "用户不存在", null, 'USER_NOT_FOUND');
        }
        
        // 验证密码
        if (!CkPasswd($password, $userinfo['password'],  True)) {
            AddLoginlog($user_id, 0);
            return $this->response(false, "密码错误", null, 'PASSWORD_ERROR');
        }
        
        // 执行登录操作（同步Judge.php的LoginOper方法）
        $this->LoginOper($userinfo);
        AddLoginlog($user_id, 1);
        
        return $this->response(true, "登录成功", ['user_id' => $user_id]);
    }
    
    protected function IsLogin() {
        return session('?judger_user_id');
    }
    protected function GetLoginJudger() {
        return session('judger_user_id');
    }
    /**
     * 登录操作（同步Judge.php的LoginOper方法）
     */
    protected function LoginOper($userinfo){
        // 设置登录后的session
        session('judger_user_id', $userinfo['user_id']);
        // 简化登录，权限将实时检测
    }
    
    /**
     * 获取待评测任务
     */
    public function getpending() {
        $max_tasks = input('max_tasks/d', 1);
        $flg_checkout = input('flg_checkout/d', 1);  // 默认值为1，表示需要checkout，即获取的pending会变更为编译中，不再被其它评测机获取

        $max_tasks = min(5, $max_tasks); // 至多一次check 5 个
        $OJ_LANGUAGE = config('CsgojConfig.OJ_LANGUAGE');
        $OJ_LANGUAGE_ID_LIST = array_keys($OJ_LANGUAGE);

        $judger_id = $this->GetLoginJudger();
        // **********
        // 评测机特殊限制
        $judger = db('users')->where('user_id', $judger_id)->field([
            'email as pro_list',
            'volume as flg_white',
            'defunct',
            'language as langmask',
            'accesstime',
            'ip'
        ])->cache('getpending_' . $judger_id, 10)->find();
        $map_judge_limit = [];

        if($judger['pro_list']) {
            $map_judge_limit['problem_id'] = [$judger['flg_white'] == 0 ? 'in' : 'not in', explode(',', $judger['pro_list'])];
        }
        if($judger['langmask']) {
            $map_judge_limit['language'] = ['in', array_intersect($OJ_LANGUAGE_ID_LIST, LangMask2LangList($judger['langmask'], 'id'))];
        } else {
            $map_judge_limit['language'] = ['in', $OJ_LANGUAGE_ID_LIST];
        }
        // **********
        // 查询
        $solutions = db('solution')
            ->where(function ($query) {
                $query->where('result', '<', 2)  // 待评测或编译中
                    ->whereOr(function ($query2) {  // 超过 $this->auto_rejudge_sec 秒未判完的题目
                        $query2->where('result', '<', 4)->whereTime('judgetime', '<', time() - $this->auto_rejudge_sec);
                    });
            })
            ->where($map_judge_limit)
            ->order(['result' => 'asc', 'solution_id' => 'asc'])
            ->limit($max_tasks)
            ->select();
        
        $tasks = [];

        foreach ($solutions as $solution) {
            // 根据flg_checkout参数决定是否更新状态为编译中
            if ($flg_checkout) {
                // 参考Judge.php的checkout逻辑：更新状态为编译中
                db('solution')->where('solution_id', $solution['solution_id'])->update([
                    'result'    => 2,  // 编译中
                    'time'      => 0,
                    'memory'    => 0,
                    'judgetime' => date('Y-m-d H:i:s'),
                    'judger'    => $this->GetLoginJudger()
                ]);
            }
            
            $tasks[] = [
                'solution_id'   => $solution['solution_id'],
                'problem_id'    => $solution['problem_id'],
                'user_id'       => $solution['user_id'],
                'language'      => $this->getLanguageName($solution['language']),  // 返回语言名称
                'contest_id'    => $solution['contest_id']
            ];
        }
        
        return $this->response(true, $flg_checkout ? "checkout" : 'get', $tasks);
    }
    
    /**
     * 获取 solution 的信息
     */
    public function getsolutioninfo(){
        $solution_id = input('sid/d');
        $flg_with_result = input('flg_with_result/d', 0);  // 默认值为0，表示不返回 result，只有调试时候才用得上
        
        if ($solution_id <= 0) {
            return $this->response(false, " solution ID无效");
        }
        
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        if (!$solution) {
            return $this->response(false, " solution 不存在");
        }
        
        // 读取评测机配置（只需实际配置数据）
        // 返回格式示例：
        // {
        //   code: 1,
        //   data: {
        //     solution_id: 1001,
        //     problem_id: 1000,
        //     user_id: "tester",
        //     language: "cpp",
        //     contest_id: 0,
        //     // 评测机参数（供评测端使用）：
        //     judge_common: { max_time_limit: 10000, max_memory_limit: 2048, max_output_limit: 256, ... },
        //     judge_lang_cfg:   { cpp_std: "-std=c++17", cpp_opt: "-O2" }  // 与 language 对应分组
        //   }
        // }
        $judgeConfigPack = GetJudgerConfig(false);
        $judgeConfig = $judgeConfigPack['config'];
        $judgeCommon = $judgeConfig['common'];
        
        // 将语言ID映射为配置分组键名
        $langName = $this->getLanguageName($solution['language']);
        $langKey = strtolower($langName);
        if ($langKey === 'c++') $langKey = 'cpp';
        if ($langKey === 'golang') $langKey = 'go';
        if (!isset($judgeConfig[$langKey]) || !is_array($judgeConfig[$langKey])) {
            return $this->response(false, "评测语言配置不存在或无效: {$langKey}");
        }
        $langCfg = $judgeConfig[$langKey];
        
        $solution_info = [
            'solution_id' => $solution['solution_id'],
            'problem_id' => $solution['problem_id'],
            'user_id' => $solution['user_id'],
            'language' => $this->getLanguageName($solution['language']),  // 返回语言名称
            'contest_id' => $solution['contest_id'],
            'judge_common' => $judgeCommon,
            'judge_lang_cfg' => $langCfg
        ];
        if($flg_with_result) {
            $solution_info['result'] = $solution['result'];
        }
        
        return $this->response(true, "获取 solution 信息成功", $solution_info);
    }
    
    /**
     * 获取 solution 代码
     */
    public function getsolution()
    {
        $solution_id = input('sid/d');
        
        if ($solution_id <= 0) {
            return $this->response(false, " solution ID无效");
        }
        
        $solution = db('solution')->where('solution_id', $solution_id)->find();
        if (!$solution) {
            return $this->response(false, " solution 不存在");
        }
        
        // 获取源代码
        $source = db('source_code')->where('solution_id', $solution_id)->find();
        if (!$source) {
            return $this->response(false, "源代码不存在");
        }
        
        return $this->response(true, "获取源代码成功", $source['source']);
    }
    
    /**
     * 获取问题信息
     */
    public function getprobleminfo()
    {
        $problem_id = input('pid/d');
        
        if ($problem_id <= 0) {
            return $this->response(false, "问题ID无效");
        }
        
        $problem = db('problem')->where('problem_id', $problem_id)->find();
        if (!$problem) {
            return $this->response(false, "问题不存在");
        }
        
        $problem_info = [
            'time_limit' => $problem['time_limit'],
            'memory_limit' => $problem['memory_limit'],
            'spj' => $problem['spj']
        ];
        
        return $this->response(true, "获取问题信息成功", $problem_info);
    }
    
    /**
     * 获取评测数据
     */
    public function getdata(){
        $problem_id = input('problem_id/d');
        
        if ($problem_id <= 0) {
            return $this->response(false, "问题ID无效");
        }
        
        // 参考Judge.php的配置获取方式
        $path_judge_data = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $problem_id;
        if (!is_dir($path_judge_data)) {
            return $this->response(false, "评测数据目录不存在");
        }
        
        // 创建临时压缩文件
        $temp_file = tempnam(sys_get_temp_dir(), 'judge_data_');
        $tar_file = $temp_file . '.tar.gz';
        
        // 使用tar指令直接筛选文件：只打包 .in、.out、tpj.cc 三种文件
        $command = "cd " . dirname($path_judge_data) . " && tar -czf {$tar_file} --include='*.in' --include='*.out' --include='tpj.cc' " . basename($path_judge_data);
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            return $this->response(false, "打包评测数据失败");
        }
        
        // 检查是否生成了有效文件
        if (!file_exists($tar_file) || filesize($tar_file) === 0) {
            return $this->response(false, "没有找到符合条件的评测数据文件");
        }
        
        // 输出压缩文件
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="data.tar.gz"');
        readfile($tar_file);
        
        // 清理临时文件
        unlink($tar_file);
        unlink($temp_file);
    }
    
    /**
     * 更新任务状态
     */
    public function updatesolution()
    {
        // 从JSON请求体获取参数
        $data = $this->getJsonData();
        $solution_id = $data['solution_id'] ?? 0;
        $task_status = $data['task_status'] ?? '';
        $judge_result_data = $data['judge_result_data'] ?? [];
        
        if ($solution_id <= 0 || empty($task_status)) {
            return $this->response(false, "参数无效");
        }
        
        // 参考Judge.php的字段名称和逻辑
        $update_data = [
            'judger' => $this->judge_user        // 评测机用户名
        ];
        
        // 支持结果名称和编号两种方式
        $result_code = null;
        if (isset($judge_result_data['judge_result'])) {
            if (is_numeric($judge_result_data['judge_result'])) {
                // 如果是数字，直接使用
                $result_code = intval($judge_result_data['judge_result']);
            } else {
                // 如果是字符串，转换为编号
                $result_code = $this->getResultId($judge_result_data['judge_result']);
            }
        }
        
        // 根据评测机执行状态设置结果（基于新版评测机逻辑）
        switch ($task_status) {
            case 'running':
                // 评测机正在运行
                $update_data['result'] = 3;  // RJ - Running&Judging
                break;
            case 'completed':
                // 评测机任务完成，使用传入的评测结果
                $update_data['result'] = $result_code ?? 90;  // 使用传入的结果或默认JF（评测失败）
                $update_data['time'] = intval($judge_result_data['time'] ?? 0);
                $update_data['memory'] = intval($judge_result_data['memory'] ?? 0);
                break;
            case 'error':
                // 评测机执行出错，检查是否是评测机问题
                $result_name = $judge_result_data['judge_result'] ?? '';
                if (in_array($result_name, ['AC', 'WA', 'TLE', 'MLE', 'RE', 'CE', 'PE', 'OLE'])) {
                    // 这是正常的评测结果，使用传入的结果
                    $update_data['result'] = $result_code ?? 90;  // 使用传入的结果或默认JF（评测失败）
                    $update_data['time'] = intval($judge_result_data['time'] ?? 0);
                    $update_data['memory'] = intval($judge_result_data['memory'] ?? 0);
                } else {
                    // 这是评测机问题，设为评测失败
                    $update_data['result'] = 90;  // JF - Judge Failed
                }
                break;
            default:
                // 未知状态，设为评测失败
                $update_data['result'] = 90;  // JF - Judge Failed
                break;
        }
        
        $updated = db('solution')->where('solution_id', $solution_id)->update($update_data);
        
        return $this->response(true, $updated ? "任务状态更新成功" : "任务状态没有变化", $update_data);
        
    }
    
    /**
     * 添加编译错误信息
     */
    public function addceinfo()
    {
        return $this->addErrorInfo('ceinfo', 'compileinfo', '编译错误信息');
    }
    
    /**
     * 添加运行时错误信息
     */
    public function addreinfo()
    {
        return $this->addErrorInfo('reinfo', 'runtimeinfo', '运行时错误信息');
    }
    
    /**
     * 通用错误信息添加方法
     * 
     * @param string $data_key JSON数据中的键名
     * @param string $table_name 数据库表名
     * @param string $error_type_name 错误类型名称（用于日志）
     * @return array 响应结果
     */
    private function addErrorInfo($data_key, $table_name, $error_type_name)
    {
        // 从JSON请求体获取参数
        $data = $this->getJsonData();
        $solution_id = $data['sid'] ?? 0;
        $error_data = $data[$data_key] ?? [];
        
        if ($solution_id <= 0) {
            return $this->response(false, " solution ID无效");
        }
        
        // 处理结构化错误信息
        $error_text = $this->processStructuredErrorInfo($error_data);
        
        // 数据库操作：查找现有记录
        $item = db($table_name)->where('solution_id', $solution_id)->find();
        if($item == null) {
            // 插入新记录
            $inserted = db($table_name)->insert(['solution_id' => $solution_id, 'error' => $error_text]);
        } else {
            // 更新现有记录
            $item['error'] = $error_text;
            $inserted = db($table_name)->update($item);
        }
        
        if ($inserted !== false) {
            return $this->response(true, "{$error_type_name}添加成功");
        } else {
            return $this->response(false, "{$error_type_name}添加失败");
        }
    }
    
    /**
     * 处理结构化错误信息
     * 将结构化的错误信息转换为JSON字符串存储到数据库
     */
    private function processStructuredErrorInfo($error_data)
    {
        // 直接转换为格式化的JSON字符串
        return json_encode($error_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
        
    /**
     * 更新问题统计信息
     */
    public function updateproblem()
    {
        // 从JSON请求体获取参数
        $data = $this->getJsonData();
        $problem_id = $data['problem_id'] ?? 0;
        
        if ($problem_id <= 0) {
            return $this->response(false, "问题ID无效");
        }
        
        // 重新计算问题统计信息
        $this->recalculateProblemStats($problem_id);
        
        return $this->response(true, "问题统计信息更新成功", ['problem_id' => $problem_id]);
    }
    
    /**
     * rejudge 统计信息
     */
    private function recalculateProblemStats($problem_id)
    {
        // 参考Judge.php的实现，区分contest和非contest状态
        $cid = input('cid/d');
        if($cid != null && $cid > 0) {
            // contest题目不需要更新统计
            return true;
        }
        
        $accepted = db('solution')->where('problem_id', $problem_id)->where('result', 4)
            ->where(function ($query) {
                $query->whereNull('contest_id')
                ->whereOr('contest_id', 0);
            })->count();
        $submit = db('solution')->where('problem_id', $problem_id)
            ->where(function ($query) {
                $query->whereNull('contest_id')
                    ->whereOr('contest_id', 0);
            })
            ->count();
        
        $ret = db('problem')->where('problem_id', $problem_id)->update(['accepted' => $accepted, 'submit' => $submit]);
        return $ret;
    }
        
    // **************************************************
    // 全量数据同步相关接口
    /**
     * 获取问题列表
     */
    public function getproblemlist()
    {
        // 获取所有问题ID
        $problem_ids = db('problem')->column('problem_id');
        // $problem_ids = array_column($problems, 'problem_id');
        
        return $this->response(true, "获取问题列表成功", $problem_ids);
    }
    
    /**
     * 获取所有问题的数据信息
     */
    public function getallproblemsinfo()
    {
        // 从JSON请求体获取参数
        $data = $this->getJsonData();
        $problem_ids = $data['problem_ids'] ?? [];
        
        if (empty($problem_ids)) {
            return $this->response(false, "问题ID列表不能为空");
        }
        
        $problems_info = [];
        
        foreach ($problem_ids as $problem_id) {
            // 计算评测数据目录路径
            $data_dir = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $problem_id;
            
            // 只计算同步必需的信息
            $size = 0;
            $hash = '';
            
            if (is_dir($data_dir)) {
                // 计算目录大小
                $size = $this->calculateDirectorySize($data_dir);
                
                // 计算目录哈希
                $hash = $this->calculateDirectoryHash($data_dir);
            }
            
            $problems_info[$problem_id] = [
                'problem_id' => $problem_id,
                'size' => $size,
                'hash' => $hash,
                'has_data' => is_dir($data_dir) && count(scandir($data_dir)) > 2
            ];
        }
        
        return $this->response(true, "获取问题数据信息成功", $problems_info);
    }
    
    /**
     * 获取数据哈希
     */
    public function getdatahash()
    {
        $problem_id = input('problem_id/d');
        
        if ($problem_id <= 0) {
            return $this->response(false, "问题ID无效");
        }
        
        // 参考Judge.php的配置获取方式
        $path_judge_data = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $problem_id;
        if (!is_dir($path_judge_data)) {
            return $this->response(false, "评测数据目录不存在");
        }
        
        // 计算目录哈希
        $hash = $this->calculateDirectoryHash($path_judge_data);
        
        return $this->response(true, "获取数据哈希成功", $hash);
    }
    
    /**
     * 获取题目文件列表和哈希
     */
    public function get_datafile_list()
    {
        $problem_id = input('problem_id/d');
        
        if ($problem_id <= 0) {
            return $this->response(false, "问题ID无效");
        }
        
        // 参考Judge.php的配置获取方式
        $path_judge_data = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $problem_id;
        if (!is_dir($path_judge_data)) {
            return $this->response(false, "评测数据目录不存在");
        }
        
        $files_info = [];
        
        // 扫描目录，只收集评测相关文件
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path_judge_data));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // 只包含评测相关文件：.in, .out, .tpj.cc
                if ($extension === 'in' || $extension === 'out' || $filename === 'tpj.cc') {
                    $rel_path = str_replace($path_judge_data . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $file_hash = hash_file('sha256', $file->getPathname());
                    $file_size = $file->getSize();
                    $file_mtime = $file->getMTime();  // 获取文件修改时间
                    
                    $files_info[] = [
                        'path' => $rel_path,
                        'hash' => $file_hash,
                        'size' => $file_size,
                        'mtime' => $file_mtime
                    ];
                }
            }
        }
        
        return $this->response(true, "获取文件列表成功", $files_info);
    }
    
    /**
     * 获取单个文件内容
     */
    public function getdatafile()
    {
        $problem_id = input('problem_id/d');
        $file_path = input('file_path/s');
        
        if ($problem_id <= 0 || empty($file_path)) {
            return $this->response(false, "参数无效");
        }
        
        // 参考Judge.php的配置获取方式
        $path_judge_data = config('OjPath.testdata') . DIRECTORY_SEPARATOR . $problem_id;
        $full_file_path = $path_judge_data . DIRECTORY_SEPARATOR . $file_path;
        
        if (!file_exists($full_file_path)) {
            return $this->response(false, "文件不存在");
        }
        
        // 安全检查：确保文件在题目目录内
        $real_path = realpath($full_file_path);
        $real_data_dir = realpath($path_judge_data);
        if (strpos($real_path, $real_data_dir) !== 0) {
            return $this->response(false, "文件路径不安全");
        }
        
        // 输出文件内容
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['in', 'out', 'c', 'cc', 'cpp', 'md', 'txt'])) {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            // 其他类型使用二进制流
            header('Content-Type: application/octet-stream');
        }
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        readfile($full_file_path);
    }
    
    /**
     * 计算评测数据大小（只包含评测相关文件）
     */
    private function calculateDirectorySize($dir)
    {
        $size = 0;
        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    // 只计算评测相关文件的大小：.in, .out, .tpj.cc
                    if ($extension === 'in' || $extension === 'out' || $filename === 'tpj.cc') {
                        $size += $file->getSize();
                    }
                }
            }
        }
        return $size;
    }
    
    /**
     * 计算评测数据哈希（只包含评测相关文件）
     */
    private function calculateDirectoryHash($dir)
    {
        $hash = hash_init('sha256');
        
        if (is_dir($dir)) {
            // 只处理评测相关的文件
            $judge_files = [];
            
            // 扫描目录，只收集评测相关文件
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    // 只包含评测相关文件：.in, .out, .tpj.cc
                    if ($extension === 'in' || $extension === 'out' || $filename === 'tpj.cc') {
                        $judge_files[] = $file->getPathname();
                    }
                }
            }
            
            // 按文件名排序确保一致性
            sort($judge_files);
            
            foreach ($judge_files as $file) {
                $rel_path = str_replace($dir . DIRECTORY_SEPARATOR, '', $file);
                hash_update($hash, $rel_path);
                
                if (is_readable($file)) {
                    hash_update_file($hash, $file);
                }
            }
        }
        
        return hash_final($hash);
    }
    
}
