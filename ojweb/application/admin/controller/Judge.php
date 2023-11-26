<?php
namespace app\admin\controller;
use think\Controller;
use Alchemy\Zippy\Zippy;
class Judge extends Filebase
{
    var $tmpFileBaseName;
    public function _initialize()
    {
        $this->OJMode();
        $this->AdminInit();
        $this->FilebaseInit();

        $this->filenameRe = "/^(spj.cc|tpj.cc|([0-9a-zA-Z-_\\. \\(\\)]+\\.(zip|in|out)))$/i";
        $this->filenameReMsg = "";
        $this->maxFileSize = config('CsgojConfig.OJ_UPLOAD_TESTDATA_MAXSIZE');
        $this->assign('maxfilesize', $this->maxFileSize);
        $this->maxFileNum = config('CsgojConfig.OJ_UPLOAD_MAXNUM');
        $this->validateRule = ['size' => $this->maxFileSize];


        if(!input('?item') || trim(input('item') != 'problem'))
            $this->error('This page is only for problem judging ralated things.');
        $this->GetInput();
        $this->FileAuthentication();
        $this->GetPath();
    }
    public function judgedata_manager() {
        $this->assign([
            'pagetitle'         => 'Judge Data ' . $this->inputInfo['id'],
            'inputinfo'         => $this->inputInfo,
            'iteminfo'          => $this->itemInfo,
            'file_url'          => '/admin/judge/judgedata_manager_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
            'delete_url'        => '/admin/judge/file_delete_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
            'rename_url'        => '/admin/judge/file_rename_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
            'upload_url'        => '/admin/judge/upload_ajax',
            'method_button'     => 'File Type',
            'attach_notify'     => "<code>.in</code>, <code>.out</code> and <code>spj.cc/tpj.cc</code>. <br/>
            OJ原生special judge上传<code>spj.cc</code>，<strong class='text-danger'>若基于 testlib 请上传<code>tpj.cc</code></strong><br/>
            上传zip将自动解压，不合法文件会自动删除。<br/>
            Uploaded zip file will be automatically decompressed.<br/>If invalid files exists in the zip, they'll be automatically deleted without notification.<br/><strong>System will only recursively search folder with the same name of the zip file, e.g. a \"test\" folder in a \"test.zip\" file.</strong>"
        ]);
        return $this->fetch();
    }
    public function GetPath() {
        $this->inputInfo['path'] =  $this->ojPath['testdata'] . '/' . $this->inputInfo['id'];
        if(!MakeDirs($this->inputInfo['path']))
        {
            $this->error('Folder permission denied.');
        }
        return $this->inputInfo;
    }
    public function judgedata_manager_ajax() {
        $filelist = $this->GetDir();
        $ret['total'] = count($filelist);
        $ret['rows'] = $filelist;
        return $ret['rows'];
    }

    public function file_rename_ajax()
    {

        if(!preg_match($this->filenameRe, $this->inputInfo['rename']))
        {
            $this->error("Please enter a valid filename".$this->filenameReMsg);
        }
        if(!rename($this->inputInfo['path'] . '/' . $this->inputInfo['filename'], $this->inputInfo['path'] . '/' . $this->inputInfo['rename']))
        {
            $this->error('Failed.');
        }
        $this->ojPath = config('OjPath');
        $this->success(
            'Renamed to ' . $this->inputInfo['rename'],
            '',
            ['rename'  => "<a href='/admin/judge/downloaddata?id=".$this->inputInfo['id']."&filename=". $this->inputInfo['rename']."' filename='".$this->inputInfo['rename']."'>" .  $this->inputInfo['rename'] . "</a>"]
        );
    }

    public function upload_ajax()
    {
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
                $infolist .= "<br/>" . $filename . ": 文件名不合法(Name not valid)";
                continue;
            }
            //$fileExt处理类似 spj 这样没有扩展名的文件。ThinkPHP的move这里会给没后缀名文件自动加一个“.”。
            $fileExt = strtolower(GetExtension($filename));
            if(strlen($fileExt) == 0)
                $info = $file->validate($this->validateRule)->move($this->inputInfo['path'], $filename . ".FILE_NO_EXT");
            else
                $info = $file->validate($this->validateRule)->move($this->inputInfo['path'], '');
            if (!$info) {
                $infolist .= "<br/>File \"".$filename."\": ".($file->getError());
            }
            else {
                $atLeastOneFile++;
                if(strlen($fileExt) == 0)
                {
                    rename($this->inputInfo['path'] . "/" . $filename . ".FILE_NO_EXT", $this->inputInfo['path'] . "/" . $filename);
                    if($filename == 'spj' || $filename == 'tpj') {
                        exec("chmod +x " . $this->inputInfo['path'] . "/" . $filename);
                    }
                }
                else if($fileExt == 'zip')
                {
                    //有时候压缩数据时候直接对文件夹打包，为方便这个操作，允许与zip同名的文件夹，递归一次。此处暂存zip不带扩展名的文件名。
                    $this->tmpFileBaseName = basename($filename, '.zip');
                    $this->DecompressZipData($this->inputInfo['path'] . "/" . $filename);
                    unlink($this->inputInfo['path'] . "/" . $filename);
                    if(is_file($this->inputInfo['path'] . "/spj")) {
                        //不加上传判断了，直接看一下有没有spj，有就给chmod。
                        exec("chmod +x " . $this->inputInfo['path'] . "/spj");
                    }
                    if(is_file($this->inputInfo['path'] . "/tpj")) {
                        exec("chmod +x " . $this->inputInfo['path'] . "/tpj");
                    }
                }
            }
        }
        if($infolist == '')
            $this->success('OK');
        else
            $this->error('存在文件上传失败(Some files upload failed)' . $infolist);
    }
    function DecompressZipData($zipFilePath)
    {
        $date = date('Y-m-d-H-i-s');
        //在建立新的临时文件夹之前，删除旧的因为程序崩溃导致的未删除的临时文件夹。
        DelTimeExpireFolders($this->ojPath['import_problem_temp'], $this->ojPath['export_temp_keep_time']);
        //导入题目过程中文件中转的临时文件夹
        $importTempPath = $this->ojPath['import_problem_temp'] . '/' . $date . '-' . session('user_id');
        if(!MakeDirs($importTempPath))
            $this->error("Folder [" . $this->ojPath['import_problem_temp'] . "] permission denied, you may need 'chmod'.");
        $zippy = Zippy::load();
        $archive = $zippy->open($zipFilePath);
        $archive->extract($importTempPath);
        if(!$this->MoveUnzipFile($importTempPath))
        {
            DelDirs($importTempPath);
            $this->error('Some file in the zip is not valid, delete failed.');
        }
        DelDirs($importTempPath);
        return true;
    }

    // 验证目标文件夹文件名合法性
    public function MoveUnzipFile($path, $depth=0, $deleteFile = true)
    {
        // 如果允许删除非法文件，则都删除后返回true，删除失败返回false。如果不允许删除，直接返回false
        if (is_dir($path) && ($handle = opendir($path)))
        {
            while (($file = readdir($handle)) !== false)
            {
                if ($file!="." && $file!="..")
                {
                    if(is_dir($path . '/' . $file))
                    {
                        //有时候压缩数据时候直接对文件夹打包，为方便这个操作，允许与zip同名的文件夹，递归一次。
                        if($file == $this->tmpFileBaseName && $depth < 3)
                        {
                            //$depth 控制一下递归深度。虽然又是个基本不会发生的情况，应该不会套很多层文件夹吧。不过还是防一下。
                            $this->MoveUnzipFile($path . '/' . $file, $depth + 1);
                        }
                        //文件夹直接删除。problem附带文件不该有子文件夹
                        if($deleteFile)
                        {
                            if(!DelDirs($path . '/' . $file))
                                return false;
                        }
                        else
                            return false;
                    }
                    else if(!preg_match($this->filenameRe, $file))
                    {
                        if($deleteFile)
                        {
                            if(!unlink($path . '/' . $file))
                                return false;
                        }
                        else
                            return false;
                    }
                    else
                    {
                        //mv时文件路径加引号，不然带括号的文件会有问题。
                        //PHP bug?，rename文件夹跨“驱动器”会提示copy()xxx，反正有问题
                        exec("mv \"" . $path . '/' . $file . "\"  \"" . $this->inputInfo['path'] . "/" . $file . "\"");
                    }

                }
            }
            //关闭句柄
            closedir ( $handle );
        }
        else
        {
            return false;
        }
        return true;
    }
    //获取目标文件夹文件列表
    public function GetDir()
    {
        $filelist = [];
        if ($handle  = opendir($this->inputInfo['path']))
        {
            $i = 1;
            while (($file = readdir($handle)) !== false)
            {
                if ($file!="." && $file!="..")
                {
                    $filelist[] = [
                        'file_lastmodify'   => date("Y-m-d h:i:s", filemtime($this->inputInfo['path'] . '/' . $file)),
                        'file_name'         => $file,
                        'file_size'         => round(filesize($this->inputInfo['path'] . '/' . $file) / 1024, 2),
                        'file_type'         => mime_content_type($this->inputInfo['path'] . '/' . $file),
                        'file_url'          => "/admin/judge/downloaddata?item=".$this->inputInfo['item']."&id=".$this->inputInfo['id']."&filename=".$file
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
}
