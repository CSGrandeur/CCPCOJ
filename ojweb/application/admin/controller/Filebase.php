<?php

/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 22:20
 */

namespace app\admin\controller;

class Filebase extends Adminbase
{

    var $filenameRe;
    var $filenameReMsg;
    var $maxFileSize;
    var $maxFileNum;
    var $inputInfo;            //get/post参数得到的信息
    var $itemInfo;            //根据id查数据库的信息
    var $allowField;
    var $ojPath;
    var $validateRule;
    public function _initialize()
    {
        $this->OJMode();
        $this->AdminInit();
        $this->FilebaseInit();
    }
    public function FilebaseInit()
    {
        $this->filenameRe = "/^[0-9a-zA-Z-_\\.\\(\\)]+\\.(jpg|png|gif|bmp|svg|ico)$/";
        $this->filenameReMsg = "<br/>只允许包含字母数字的文件名<br/>Only jpg|png|gif|bmp|svg|ico with <strong>alphanumeric file name</strong> allowed";
        $this->maxFileSize = config('CsgojConfig.OJ_UPLOAD_ATTACH_MAXSIZE');
        $this->assign('maxfilesize', $this->maxFileSize);
        $this->maxFileNum = config('CsgojConfig.OJ_UPLOAD_MAXNUM');
        $this->assign('maxFileNum', $this->maxFileNum);
        $this->validateRule = ['size' => $this->maxFileSize, 'ext' => 'jpg,png,gif,bmp,svg,ico'];
        $this->GetInput();
        $this->FileAuthentication();
    }
    public function filemanager_ajax()
    {
        $filelist = $this->GetDir();
        return $filelist;
    }
    public function GetInput()
    {
        $this->ojPath = config('OjPath');
        //Admin ChangeDefunct管理的通用验证，$item = news、problem、contest
        $this->inputInfo = [
            'item' => trim(input('item', '')),
            'id' => trim(input('id', '')),
            'filename' => trim(input('filename', '')),         //仅在删除、修改
            'rename' => trim(input('rename', '')),         //仅在修改文件名时有此项
            'path' => '',
        ];
        $this->privilegeStr = $this->inputInfo['item']; //比如 problem
    }
    public function FileAuthentication()
    {
        //Adminbase中的基本权限验证，即验证是否有 problem_editor 这样的item权限
        $this->BaseAuthentication($this->privilegeStr);

        if (!IsAdmin($this->inputInfo['item'], $this->inputInfo['id'])) {
            //判断有无管理此模块下对应id的专有权限
            $this->error("You don't own this item.");
        }
        $this->itemInfo = db($this->inputInfo['item'])
            ->where($this->inputInfo['item'] . '_id', $this->inputInfo['id'])
            ->field('attach,title')
            ->find();
        if (!$this->itemInfo) {
            //无此条目
            $this->error('No such ' . $this->inputInfo['item'] . '.');
        } else {
            //更新没有attach的旧数据或者因为bug没更新该字段的数据
            if (!array_key_exists('attach', $this->itemInfo) || strlen($this->itemInfo['attach']) == 0) {
                $this->itemInfo['attach'] = $this->AttachFolderCalculation(session('user_id'));
                db($this->inputInfo['item'])
                    ->where($this->inputInfo['item'] . '_id', $this->inputInfo['id'])
                    ->update($this->itemInfo);
            }
        }
    }
    protected function PostProcessUploadedFile($finalFilePath, $fileName)
    {
        // 上传成功后的处理
    }
    public function chunk_upload_ajax()
    {
        $file = request()->file("upload_file");
        $index = request()->post('index');
        $totalChunks = request()->post('totalChunks');
        $fileName = request()->post('fileName');

        if (!preg_match($this->filenameRe, $fileName)) {
            $this->error($fileName . ": 文件名不合法(Name not valid)");
        }
        $finalDir = $this->inputInfo['path'];
        $finalFilePath = $finalDir . DIRECTORY_SEPARATOR . $fileName;
        $finalHash = md5($finalFilePath . DIRECTORY_SEPARATOR . $totalChunks);

        // 临时保存分片文件的目录
        $tempDir = $this->ojPath['chunk_file_temp'] . DIRECTORY_SEPARATOR . $finalHash;
        if (!MakeDirs($tempDir)) {
            $this->error('Folder permission denied.');
        }


        // 校验分片大小
        $chunkSize = $file->getSize();
        $expectedChunkSize = 1024 * 1024; // 1MB
        if ($chunkSize > $expectedChunkSize) {
            $this->error($fileName . ": 分片大小超过限制(Chunk size exceeds limit)");
        }

        // 保存分片文件
        $info = $file->move($tempDir, 'part' . $index . '.chunk');
        if (!$info) {
            $this->error($fileName . ": " . ($file->getError()));
        }

        // 检查是否所有分片都已上传完成
        $allUploaded = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!file_exists($tempDir . DIRECTORY_SEPARATOR . 'part' . $i . '.chunk')) {
                $allUploaded = false;
                break;
            }
        }

        // 合并分片文件
        if ($allUploaded) {

            if (!MakeDirs($finalDir)) {
                $this->error('Folder permission denied.');
            }
            $finalFile = fopen($finalFilePath, 'w');

            for ($i = 0; $i < $totalChunks; $i++) {
                $partFilePath = $tempDir . DIRECTORY_SEPARATOR . 'part' . $i . '.chunk';
                $partFile = fopen($partFilePath, 'r');
                while ($buffer = fread($partFile, 1024)) {
                    fwrite($finalFile, $buffer);
                }
                fclose($partFile);
                unlink($partFilePath); // 删除分片文件
            }

            fclose($finalFile);
            $this->PostProcessUploadedFile($finalFilePath, $fileName);
            $this->success('OK');
        } else {
            $this->success('分片上传成功(Chunk uploaded successfully)', '', ['fileName' => $fileName, 'index' => $index, 'total'=> $totalChunks]);
        }
    }
    public function upload_ajax()
    {
        $files = request()->file("upload_file");
        // 移动到框架应用根目录/public/uploads/ 目录下
        if (count($files) > $this->maxFileNum) {
            $this->error('单次文件数量限制(Number of files limit once): ' . $this->maxFileNum);
        }
        $infolist = '';
        $atLeastOneFile = 0;
        foreach ($files as $file) {
            $filename = $file->getinfo('name');
            if (!preg_match($this->filenameRe, $filename)) {
                $infolist .= "<br/>" . $filename . ": 文件名不合法(Name not valid)";
                continue;
            }
            $info = $file->validate($this->validateRule)->move($this->inputInfo['path'], '');
            if (!$info) {
                $infolist .= "<br/>" . $filename . ": " . ($file->getError());
            } else {
                $atLeastOneFile++;
            }
        }
        if ($infolist == '')
            $this->success('OK');
        else
            $this->error('存在文件上传失败(Some files upload failed)' . $infolist);
    }
    public function file_delete_ajax()
    {
        if ($this->inputInfo['filename'] == '') {
            $this->error("No filename gaved."); // 以免删除整个目录
        }
        if (is_dir($this->inputInfo['path'] . '/' . $this->inputInfo['filename'])) {
            $this->error("禁止删除目录");
        } else {
            $ret = DelWhatever($this->inputInfo['path'] . '/' . $this->inputInfo['filename']);
            if ($ret === false)
                $this->error("Failed to delete " . $this->inputInfo['filename']);
            $this->success($ret . " [" . $this->inputInfo['filename'] . "] successfully deleted.");
        }
    }
    public function file_rename_ajax()
    {
        if (!preg_match($this->filenameRe, $this->inputInfo['rename'])) {
            $this->error("Please enter a valid filename<br/>" . $this->filenameReMsg);
        }
        if (!rename($this->inputInfo['path'] . '/' . $this->inputInfo['filename'], $this->inputInfo['path'] . '/' . $this->inputInfo['rename'])) {
            $this->error('Failed.');
        }
        $this->ojPath = config('OjPath');
        $file_url = $this->ojPath[$this->inputInfo['item'] . '_ATTACH'] . '/' . $this->inputInfo['id'] . '/' . $this->inputInfo['rename'];
        $this->success(
            'Renamed to ' . $this->inputInfo['rename'],
            '',
            ['rename' => "<a href='" . $file_url . "' filename='" . $this->inputInfo['rename'] . "' target='_blank'>" . $this->inputInfo['rename'] . "</a>"]
        );
    }
    //获取目标文件夹文件列表
    public function GetDir()
    {
        $path = $this->inputInfo['path'];
        $filelist = [];
        if ($handle = opendir($path)) {
            $i = 1;
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    $file_url = $this->ojPath[$this->inputInfo['item'] . '_ATTACH'] . '/' . $this->itemInfo['attach'] . '/' . $file;
                    $filelist[] = [
                        'file_lastmodify' => date("Y-m-d h:i:s", filemtime($path . '/' . $file)),
                        'file_name' => $file,
                        'file_size' => round(filesize($path . '/' . $file) / 1024, 2),
                        'file_type' => mime_content_type($path . '/' . $file),
                        'file_url' => $file_url,
                    ];
                    $i++;
                }
            }
            rsort($filelist);
            //关闭句柄
            closedir($handle);
        }
        return $filelist;
    }
    public function downloaddata()
    {

        if (!preg_match("/^[0-9a-zA-Z-_\\.]+$/i", $this->inputInfo['filename'])) {
            //这里只是防止意外传入了目录级的参数，非法读取父级目录
            $this->error("Please use a valid filename.");
        }
        $extension = pathinfo($this->inputInfo['filename'], PATHINFO_EXTENSION);
        if ($extension == 'in' || $extension == 'out') {
            downloads($this->inputInfo['path'], $this->inputInfo['filename'], null, 9);
        } else {
            downloads($this->inputInfo['path'], $this->inputInfo['filename']);
        }
    }
}
