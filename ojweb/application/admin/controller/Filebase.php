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
        $this->filenameReMsg = "<br/>Only jpg|png|gif|bmp|svg|ico with <strong>alphanumeric file name</strong> allowed";
        $this->maxFileSize = config('CsgojConfig.OJ_UPLOAD_ATTACH_MAXSIZE');
        $this->assign('maxfilesize', $this->maxFileSize);
        $this->maxFileNum = config('CsgojConfig.OJ_UPLOAD_MAXNUM');
        $this->assign('maxFileNum', $this->maxFileNum);
        $this->validateRule = ['size' => $this->maxFileSize,  'ext'=>'jpg,png,gif,bmp,svg,ico'];
        $this->GetInput();
        $this->FileAuthentication();
    }
    public function filemanager_ajax() {
        $filelist = $this->GetDir();
        return $filelist;
    }
    public function GetInput()
    {
        $this->ojPath = config('OjPath');
        //Admin ChangeDefunct管理的通用验证，$item = news、problem、contest
        $this->inputInfo = [
            'item'    	=> trim(input('item', '')),
            'id'      	=> trim(input('id', '')),
            'filename'	=> trim(input('filename', '')),         //仅在删除、修改
            'rename'  	=> trim(input('rename', '')),         //仅在修改文件名时有此项
            'path'    	=> '',
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
            ->find();
        if (!$this->itemInfo) {
            //无此条目
            $this->error('No such ' . $this->inputInfo['item'] . '.');
        }
        else
        {
            //更新没有attach的旧数据或者因为bug没更新该字段的数据
            if(!array_key_exists('attach', $this->itemInfo) || strlen($this->itemInfo['attach']) == 0)
            {
                $this->itemInfo['attach'] = $this->AttachFolderCalculation(session('user_id'));
                db($this->inputInfo['item'])
                    ->where($this->inputInfo['item'] . '_id', $this->inputInfo['id'])
                    ->update($this->itemInfo);
            }
        }
    }

    public function upload_ajax() {
        $files = request()->file("upload_file");
        // 移动到框架应用根目录/public/uploads/ 目录下
        if(count($files) > $this->maxFileNum)
            $this->error('Do not upload more than '.$this->maxFileNum.' files one time');
        $infolist = '';
        $atLeastOneFile = 0;
        foreach($files as $file)
        {
            $filename = $file->getinfo('name');
            if(!preg_match($this->filenameRe, $filename))
            {
                $infolist .= "<br/>File \"".$filename."\": Name not valid";
                continue;
            }
            $info = $file->validate($this->validateRule)->move($this->inputInfo['path'], '');
            if (!$info) {
                $infolist .= "<br/>File \"".$filename."\": ".($file->getError());
            }
            else {
                $atLeastOneFile++;
            }
        }
        if($infolist == '')
            $this->success('OK');
        else
            $this->error('Some file upload failed' . $infolist);
    }
    public function file_delete_ajax()
    {
        if($this->inputInfo['filename'] == '') {
            $this->error("No filename gaved."); // 以免删除整个目录
        }
        if(is_dir($this->inputInfo['path'] . '/' . $this->inputInfo['filename'])) {
            $this->error("禁止删除目录");
        } else {
            $ret = DelWhatever($this->inputInfo['path'] . '/' . $this->inputInfo['filename']);
            if($ret === false)
                $this->error("Failed to delete " . $this->inputInfo['filename']);
            $this->success($ret . " [" . $this->inputInfo['filename'] . "] successfully deleted.");
        }
    }
    public function file_rename_ajax()
    {
        if(!preg_match($this->filenameRe, $this->inputInfo['rename']))
        {
            $this->error("Please enter a valid filename<br/>".$this->filenameReMsg);
        }
        if(!rename($this->inputInfo['path'] . '/' . $this->inputInfo['filename'], $this->inputInfo['path'] . '/' . $this->inputInfo['rename']))
        {
            $this->error('Failed.');
        }
        $this->ojPath = config('OjPath');
        $file_url =  $this->ojPath[$this->inputInfo['item'].'_ATTACH']. '/' . $this->inputInfo['id'].'/'.$this->inputInfo['rename'];
        $this->success(
            'Renamed to ' . $this->inputInfo['rename'],
            '',
            ['rename' => "<a href='". $file_url ."' filename='".$this->inputInfo['rename']."' target='_blank'>" . $this->inputInfo['rename'] . "</a>"]
        );
    }
    //获取目标文件夹文件列表
    public function GetDir()
    {
        $path = $this->inputInfo['path'];
        $filelist = [];
        if ($handle  = opendir($path))
        {
            $i = 1;
            while (($file = readdir($handle)) !== false)
            {
                if ($file!="." && $file!="..")
                {
                    $file_url = $this->ojPath[$this->inputInfo['item'].'_ATTACH']. '/' . $this->itemInfo['attach'].'/'.$file;
                    $filelist[] = [
                        'file_lastmodify'   => date("Y-m-d h:i:s", filemtime($path . '/' . $file)),
                        'file_name'         => $file,
                        'file_size'         => round(filesize($path . '/' . $file) / 1024, 2),
                        'file_type'         => mime_content_type($path . '/' . $file),
                        'file_url'          => $file_url,
                    ];
                    $i ++;
                }
            }
            rsort($filelist);
            //关闭句柄
            closedir ( $handle );
        }
        return $filelist;
    }
    public function downloaddata()
    {

        if(!preg_match("/^[0-9a-zA-Z-_\\.]+$/i", $this->inputInfo['filename'])) {
            //这里只是防止意外传入了目录级的参数，非法读取父级目录
            $this->error("Please use a valid filename.");
        }
        $extension = pathinfo($this->inputInfo['filename'], PATHINFO_EXTENSION);
        if($extension == 'in' || $extension == 'out') {
            downloads($this->inputInfo['path'], $this->inputInfo['filename'], null, 9);
        } else {
            downloads($this->inputInfo['path'], $this->inputInfo['filename']);
        }
    }
}