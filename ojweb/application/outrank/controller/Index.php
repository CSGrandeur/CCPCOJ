<?php

namespace app\outrank\controller;

use Alchemy\Zippy\Zippy;

class Index extends Outrankbase
{
    public function _initialize()
    {
        $this->OJMode();
    }

    /**
     * 接收榜单数据接口（支持跨域POST和ZIP压缩）
     * 接口1：接收数据
     */
    public function receive_data()
    {
        // 设置跨域头
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        // 处理OPTIONS预检请求
        if ($this->request->isOptions()) {
            return json(['status' => 'ok']);
        }

        $token = input('token/s', '');
        $outrank_uuid = input('outrank_uuid/s', '');
        $data_type = input('data_type/s', 'full'); // 'full' 全量数据, 'incremental' 增量solution
        
        if (empty($token) || empty($outrank_uuid)) {
            return json(['status' => 'error', 'message' => 'Missing token or outrank_uuid'], 400);
        }

        // 验证token和uuid
        $outrank = db('outrank')->where([
            'outrank_uuid' => $outrank_uuid,
            'token' => $token
        ])->find();

        if (!$outrank) {
            return json(['status' => 'error', 'message' => 'Invalid token or outrank_uuid'], 401);
        }

        // 检查 flg_allow：0 表示禁止推送
        if (isset($outrank['flg_allow']) && $outrank['flg_allow'] == '0') {
            return json(['status' => 'error', 'message' => '推送已被禁止 (Push is disabled)'], 403);
        }

        // 检查 flg_allow：1 时，如果比赛结束超过 3 天，则仅管理员有权推送
        if (isset($outrank['flg_allow']) && $outrank['flg_allow'] == '1') {
            if (!empty($outrank['end_time'])) {
                $endTime = strtotime($outrank['end_time']);
                $threeDaysAgo = time() - (3 * 24 * 60 * 60); // 3天前的时间戳
                
                if ($endTime < $threeDaysAgo) {
                    // 比赛结束超过3天，检查是否为管理员推送
                    $isAdminPush = IsAdmin();
                    if (!$isAdminPush) {
                        return json(['status' => 'error', 'message' => '比赛已结束超过3天，仅管理员可推送 (Contest ended more than 3 days ago, only administrators can push)'], 403);
                    }
                }
            }
        }

        // 检查是否为ZIP文件上传（通过FormData）
        $file = request()->file('data');
        $data = null;
        
        if ($file) {
            // 检查文件类型
            $fileInfo = $file->getInfo();
            $fileType = isset($fileInfo['type']) ? $fileInfo['type'] : '';
            $fileName = isset($fileInfo['name']) ? $fileInfo['name'] : '';
            
            // 判断是否为ZIP文件（通过MIME类型或文件扩展名）
            if ($fileType == 'application/zip' || 
                $fileType == 'application/x-zip-compressed' ||
                (pathinfo($fileName, PATHINFO_EXTENSION) == 'zip')) {
                // 处理ZIP文件（参考 Judge.php）
                try {
                    $data = $this->processZipFile($file, $outrank);
                } catch (\Exception $e) {
                    return json(['status' => 'error', 'message' => 'ZIP处理失败: ' . $e->getMessage() . ' (ZIP processing failed: ' . $e->getMessage() . ')'], 400);
                }
            } else {
                return json(['status' => 'error', 'message' => '不支持的文件类型，请上传ZIP文件 (Unsupported file type, please upload ZIP file)'], 400);
            }
        } else {
            // 读取请求体（JSON格式）
            $rawData = $this->request->getContent();
            
            // 解析JSON数据
            $data = json_decode($rawData, true);
            if ($data === null) {
                return json(['status' => 'error', 'message' => 'Invalid JSON data'], 400);
            }
        }

        if ($data === null) {
            return json(['status' => 'error', 'message' => 'Failed to process data'], 400);
        }

        // 保存数据到文件（固定文件名，供前端展示rank）
        $ojPath = config('OjPath');
        $attachFolder = $outrank['outrank_uuid'];
        $dataFolder = $ojPath['PUBLIC'] . $ojPath['outrank_ATTACH'] . '/' . $attachFolder;
        
        if (!is_dir($dataFolder)) {
            MakeDirs($dataFolder);
        }

        // 使用固定文件名，每次推送覆盖同一个文件
        $filename = 'rank.json';
        $filepath = $dataFolder . '/' . $filename;
        
        file_put_contents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 更新outrank的updated_at
        db('outrank')->where('outrank_id', $outrank['outrank_id'])->update([
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return json(['status' => 'success', 'message' => 'Data received', 'filename' => $filename]);
    }

    /**
     * 处理ZIP文件（参考 Judge.php 的 DecompressZipData 方法）
     */
    private function processZipFile($file, $outrank)
    {
        $ojPath = config('OjPath');
        
        // 创建临时文件夹
        $date = date('Y-m-d-H-i-s');
        $importTempPath = $ojPath['import_problem_temp'] . '/' . $date . '-' . session('user_id', 'outrank');
        
        if (!MakeDirs($importTempPath)) {
            throw new \Exception("临时文件夹创建失败 (Failed to create temp folder)");
        }

        // 保存上传的ZIP文件到临时位置
        $zipFilePath = $importTempPath . '/rank.zip';
        $info = $file->move($importTempPath, 'rank.zip');
        
        if (!$info) {
            DelDirs($importTempPath);
            throw new \Exception("ZIP文件保存失败: " . ($file->getError() ?: 'Unknown error') . " (Failed to save ZIP file)");
        }
        
        $zipFilePath = $info->getRealPath();

        try {
            // 使用 Zippy 解压ZIP文件
            $zippy = Zippy::load();
            $archive = $zippy->open($zipFilePath);
            $archive->extract($importTempPath);
            
            // 查找 rank.json 文件
            $rankJsonPath = $importTempPath . '/rank.json';
            if (!file_exists($rankJsonPath)) {
                DelDirs($importTempPath);
                throw new \Exception("ZIP文件中未找到 rank.json (rank.json not found in ZIP)");
            }
            
            // 读取并解析JSON
            $jsonContent = file_get_contents($rankJsonPath);
            $data = json_decode($jsonContent, true);
            
            if ($data === null) {
                DelDirs($importTempPath);
                throw new \Exception("rank.json 解析失败 (Failed to parse rank.json)");
            }
            
            // 清理临时文件夹
            DelDirs($importTempPath);
            
            return $data;
        } catch (\Exception $e) {
            // 清理临时文件夹
            if (is_dir($importTempPath)) {
                DelDirs($importTempPath);
            }
            throw $e;
        }
    }

    /**
     * JSONP备用接口（分包提交，每包3kb以内）
     */
    public function receive_data_jsonp()
    {
        $callback = input('callback/s', 'callback');
        // 确保回调函数名称安全（防止 XSS）
        $callback = preg_replace('/[^a-zA-Z0-9_]/', '', $callback);
        if (empty($callback)) {
            $callback = 'callback';
        }
        
        $token = input('token/s', '');
        $outrank_uuid = input('outrank_uuid/s', '');
        $data_type = input('data_type/s', 'full');
        $chunk_index = input('chunk_index/d', 0);
        $chunk_total = input('chunk_total/d', 1);
        $chunk_data = input('chunk_data/s', '');

        if (empty($token) || empty($outrank_uuid) || empty($chunk_data)) {
            $response = ['status' => 'error', 'message' => 'Missing required parameters'];
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
            exit;
        }

        // 验证token和uuid
        $outrank = db('outrank')->where([
            'outrank_uuid' => $outrank_uuid,
            'token' => $token
        ])->find();

        if (!$outrank) {
            $response = ['status' => 'error', 'message' => 'Invalid token or outrank_uuid'];
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
            exit;
        }

        // 检查 flg_allow：0 表示禁止推送
        if (isset($outrank['flg_allow']) && $outrank['flg_allow'] == '0') {
            $response = ['status' => 'error', 'message' => '推送已被禁止 (Push is disabled)'];
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
            exit;
        }

        // 检查 flg_allow：1 时，如果比赛结束超过 3 天，则仅管理员有权推送
        if (isset($outrank['flg_allow']) && $outrank['flg_allow'] == '1') {
            if (!empty($outrank['end_time'])) {
                $endTime = strtotime($outrank['end_time']);
                $threeDaysAgo = time() - (3 * 24 * 60 * 60); // 3天前的时间戳
                
                if ($endTime < $threeDaysAgo) {
                    // 比赛结束超过3天，检查是否为管理员推送
                    $isAdminPush = IsAdmin();
                    if (!$isAdminPush) {
                        $response = ['status' => 'error', 'message' => '比赛已结束超过3天，仅管理员可推送 (Contest ended more than 3 days ago, only administrators can push)'];
                        header('Content-Type: application/javascript; charset=utf-8');
                        header('Cache-Control: no-cache, no-store, must-revalidate');
                        echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
                        exit;
                    }
                }
            }
        }

        // 检查是否为ZIP压缩数据
        $isZip = input('is_zip/s', '0') === '1';
        
        // 保存分块数据
        $ojPath = config('OjPath');
        $attachFolder = $outrank['outrank_uuid'];
        $dataFolder = $ojPath['PUBLIC'] . $ojPath['outrank_ATTACH'] . '/' . $attachFolder . '/chunks';
        
        if (!is_dir($dataFolder)) {
            MakeDirs($dataFolder);
        }

        $chunkFile = $dataFolder . '/' . $data_type . '_' . $chunk_index . '.txt';
        file_put_contents($chunkFile, $chunk_data);

        // 如果是最后一块，合并所有分块
        if ($chunk_index == $chunk_total - 1) {
            $allChunks = [];
            for ($i = 0; $i < $chunk_total; $i++) {
                $chunkFile = $dataFolder . '/' . $data_type . '_' . $i . '.txt';
                if (file_exists($chunkFile)) {
                    $chunkContent = file_get_contents($chunkFile);
                    $allChunks[] = $chunkContent;
                    unlink($chunkFile); // 删除临时分块文件
                }
            }

            // 拼接所有分块
            $mergedDataStr = implode('', $allChunks);
            
            if ($isZip) {
                // ZIP 模式：base64 解码 -> ZIP 文件 -> 解压 -> 解析 JSON
                try {
                    // base64 解码为二进制
                    $zipBinary = base64_decode($mergedDataStr, true);
                    if ($zipBinary === false) {
                        throw new \Exception('Base64解码失败 (Base64 decode failed)');
                    }
                    
                    // 创建临时文件夹
                    $date = date('Y-m-d-H-i-s');
                    $importTempPath = $ojPath['import_problem_temp'] . '/' . $date . '-' . session('user_id', 'outrank') . '-jsonp';
                    
                    if (!MakeDirs($importTempPath)) {
                        throw new \Exception('临时文件夹创建失败 (Failed to create temp folder)');
                    }
                    
                    // 保存 ZIP 文件到临时位置
                    $zipFilePath = $importTempPath . '/rank.zip';
                    file_put_contents($zipFilePath, $zipBinary);
                    
                    // 使用 Zippy 解压ZIP文件
                    $zippy = Zippy::load();
                    $archive = $zippy->open($zipFilePath);
                    $archive->extract($importTempPath);
                    
                    // 查找 rank.json 文件
                    $rankJsonPath = $importTempPath . '/rank.json';
                    if (!file_exists($rankJsonPath)) {
                        DelDirs($importTempPath);
                        throw new \Exception('ZIP文件中未找到 rank.json (rank.json not found in ZIP)');
                    }
                    
                    // 读取并解析JSON
                    $jsonContent = file_get_contents($rankJsonPath);
                    $mergedData = json_decode($jsonContent, true);
                    
                    if ($mergedData === null) {
                        DelDirs($importTempPath);
                        throw new \Exception('rank.json 解析失败 (Failed to parse rank.json)');
                    }
                    
                    // 清理临时文件夹
                    DelDirs($importTempPath);
                } catch (\Exception $e) {
                    // 清理临时文件夹
                    if (isset($importTempPath) && is_dir($importTempPath)) {
                        DelDirs($importTempPath);
                    }
                    $response = ['status' => 'error', 'message' => 'ZIP处理失败: ' . $e->getMessage() . ' (ZIP processing failed: ' . $e->getMessage() . ')'];
                    header('Content-Type: application/javascript; charset=utf-8');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
                    exit;
                }
            } else {
                // 非 ZIP 模式：直接解析 JSON（向后兼容）
                $mergedData = json_decode($mergedDataStr, true);
                
                if ($mergedData === null) {
                    $response = ['status' => 'error', 'message' => 'Failed to parse merged JSON'];
                    header('Content-Type: application/javascript; charset=utf-8');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
                    exit;
                }
            }

            // 保存最终文件（固定文件名，供前端展示rank）
            $finalFile = $ojPath['PUBLIC'] . $ojPath['outrank_ATTACH'] . '/' . $attachFolder . '/rank.json';
            file_put_contents($finalFile, json_encode($mergedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // 更新outrank的updated_at
            db('outrank')->where('outrank_id', $outrank['outrank_id'])->update([
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        $response = ['status' => 'success', 'message' => 'Chunk received', 'chunk_index' => $chunk_index];
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // 直接输出，避免 ThinkPHP 的额外处理
        echo $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
        exit;
    }

    /**
     * 获取outrank列表
     * 接口2：列表接口
     */
    public function index()
    {
        return $this->fetch();
    }

    public function outrank_list_ajax()
    {
        $isAdmin = IsAdmin('administrator');

        $columns = ["outrank_id", "outrank_uuid", "title", "ckind", "description", "start_time", "end_time", "in_date", "updated_at", "defunct", "addition"];
        $map = [];
        if (!$isAdmin) {
            $map['defunct'] = '0';
        } else {
            $columns[] = 'token';
            $columns[] = 'flg_allow';
        }

        $list = db('outrank')
            ->where($map)
            ->field($columns)
            ->order('outrank_id', 'desc')
            ->select();

        // 为每条记录添加 is_admin 字段
        foreach ($list as &$item) {
            $item['is_admin'] = $isAdmin;
        }
        unset($item); // 释放引用

        return $list;
    }

    /**
     * 添加/编辑outrank页面
     * 接口3：编辑页面
     */
    public function outrank_edit()
    {
        $id = input('id/d', 0);
        $outrank = null;
        if ($id > 0) {
            $outrank = db('outrank')->where('outrank_id', $id)->find();
            if (!$outrank) {
                $this->error('Outrank not found');
            }
        }
        $this->assign('outrank', $outrank);
        $this->assign('pagetitle', $id > 0 ? 'Edit Outrank' : 'Add Outrank');
        return $this->fetch();
    }

    /**
     * 获取单个outrank数据（用于modal编辑）
     */
    public function outrank_get_ajax()
    {
        if (!IsAdmin('administrator')) {
            $this->error('Permission denied');
        }

        $id = input('id/d', 0);
        $outrank = null;
        
        if ($id > 0) {
            $outrank = db('outrank')->where('outrank_id', $id)->find();
            if (!$outrank) {
                $this->error('Outrank not found');
            }
        }

        $this->assign('outrank', $outrank);
        return $this->fetch('outrank_edit_form');
    }

    /**
     * 添加/编辑outrank的ajax处理
     */
    public function outrank_addedit_ajax()
    {
        if (!IsAdmin('administrator')) {
            $this->error('Permission denied');
        }

        $id = input('outrank_id/d', 0);
        $title = trim(input('title/s', ''));
        $ckind = trim(input('ckind/s', ''));
        $description = trim(input('description/s', ''));
        $token = trim(input('token/s', ''));
        $start_time = input('start_time/s', '');
        $end_time = input('end_time/s', '');
        $addition = input('addition/a', []);

        if (empty($title)) {
            $this->error('Title is required');
        }

        // 如果token为空，自动生成
        if (empty($token)) {
            $token = $this->generateRandomToken();
        }

        $data = [
            'title' => $title,
            'ckind' => $ckind,
            'description' => $description,
            'token' => $token,
            'start_time' => $start_time ?: null,
            'end_time' => $end_time ?: null,
            'addition' => json_encode($addition, JSON_UNESCAPED_UNICODE)
        ];

        if ($id > 0) {
            // 更新
            db('outrank')->where('outrank_id', $id)->update($data);
            $this->success('Outrank updated', null, ['outrank_id' => $id]);
        } else {
            // 新增：默认 flg_allow 为 1（允许推送）
            $data['outrank_uuid'] = $this->generateUuid4();
            $data['in_date'] = date('Y-m-d H:i:s');
            $data['flg_allow'] = 1; // 新增时默认为1（允许推送）
            // 注意：如果数据库表有 defunct 字段且有默认值，数据库会自动使用默认值
            // 如果表没有该字段，则不插入，避免报错
            $outrank_id = db('outrank')->insertGetId($data);
            $this->success('Outrank added', null, ['outrank_id' => $outrank_id]);
        }
    }

    /**
     * 切换状态（defunct）专用接口
     */
    public function outrank_toggle_status_ajax()
    {
        if (!IsAdmin('administrator')) {
            $this->error('Permission denied');
        }

        $id = input('outrank_id/d', 0);
        if ($id <= 0) {
            $this->error('Invalid outrank_id');
        }

        // 获取当前状态
        $outrank = db('outrank')->where('outrank_id', $id)->find();
        if (!$outrank) {
            $this->error('Outrank not found');
        }

        // 切换状态：0 <-> 1
        $newStatus = $outrank['defunct'] == '0' ? '1' : '0';
        db('outrank')->where('outrank_id', $id)->update(['defunct' => $newStatus]);
        
        $this->success('Status updated', null, ['defunct' => $newStatus]);
    }

    /**
     * 切换推送状态（flg_allow）专用接口
     */
    public function outrank_toggle_allow_ajax()
    {
        if (!IsAdmin('administrator')) {
            $this->error('Permission denied');
        }

        $id = input('outrank_id/d', 0);
        if ($id <= 0) {
            $this->error('Invalid outrank_id');
        }

        // 获取当前状态
        $outrank = db('outrank')->where('outrank_id', $id)->find();
        if (!$outrank) {
            $this->error('Outrank not found');
        }

        // 切换状态：0 <-> 1
        $currentAllow = isset($outrank['flg_allow']) ? $outrank['flg_allow'] : '1';
        $newAllow = $currentAllow == '1' ? '0' : '1';
        db('outrank')->where('outrank_id', $id)->update(['flg_allow' => $newAllow]);
        
        $this->success('Push status updated', null, ['flg_allow' => $newAllow]);
    }

    /**
     * 删除outrank（软删除，设置defunct）
     */
    public function outrank_delete_ajax()
    {
        if (!IsAdmin('administrator')) {
            $this->error('Permission denied');
        }

        $id = input('outrank_id/d', 0);
        if ($id <= 0) {
            $this->error('Invalid outrank_id');
        }

        db('outrank')->where('outrank_id', $id)->update(['defunct' => '1']);
        $this->success('Outrank deleted');
    }

    /**
     * 生成UUID v4
     */
    private function generateUuid4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // 设置版本为4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // 设置变体
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 生成随机Token
     */
    private function generateRandomToken($length = 32)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $token;
    }

}

