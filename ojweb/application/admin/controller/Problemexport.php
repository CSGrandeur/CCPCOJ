<?php

namespace app\admin\controller;

use think\Controller;
use think\db\Expression;
use Alchemy\Zippy\Zippy;
//不知道为什么带上require_once (VENDOR_PATH.'autoload.php');就会说验证码函数重复定义。。然而不带的话zippy还是正常使用了。不知道为什么一开始没这问题
//require_once (VENDOR_PATH.'autoload.php');
class Problemexport extends Filebase
{
    var $attachFileNameRe;
    public function _initialize()
    {
        $this->OJMode();
        $this->AdminInit();
        $this->FilebaseInit();

        $this->filenameRe = "/^[0-9a-zA-Z-_\\.\\(\\)]+\\.(zip)$/";
        $this->attachFileNameRe = "/^[0-9a-zA-Z-_\\.\\(\\)]+\\.(jpg|png|gif|bmp|svg|ico)$/";
        $this->filenameReMsg = "<br/>Only zip allowed";
        $this->maxFileSize = config('CsgojConfig.OJ_UPLOAD_IMPORT_MAXSIZE');
        $this->assign('maxfilesize', $this->maxFileSize);
        $this->maxFileNum = config('CsgojConfig.OJ_UPLOAD_MAXNUM');
        $this->validateRule = ['size' => $this->maxFileSize,  'ext' => 'zip'];

        $this->GetInput();

        if ($this->inputInfo['item'] != 'problemexport') {
            $this->error('This page is for problem export');
        }
        $this->FileAuthentication();
        $this->GetPath();
    }
    public function FileAuthentication()
    {
        // 对 problemexport 情况直接判断管理员，跳过搜索具体problem_id
        if (!IsAdmin('administrator'))
            $this->error("You cannot export problem according to your privilege");
    }
    public function GetPath()
    {
        $this->inputInfo['path'] =  $this->ojPath['export_problem'];
        if (!MakeDirs($this->inputInfo['path'])) {
            $this->error('Folder permission denied.');
        }
        return $this->inputInfo;
    }

    public function file_rename_ajax()
    {

        if (!preg_match($this->filenameRe, $this->inputInfo['rename'])) {
            $this->error("Please enter a valid filename" . $this->filenameReMsg);
        }
        if (!rename($this->inputInfo['path'] . '/' . $this->inputInfo['filename'], $this->inputInfo['path'] . '/' . $this->inputInfo['rename'])) {
            $this->error('Failed.');
        }
        $this->ojPath = config('OjPath');
        $this->success(
            'Renamed to ' . $this->inputInfo['rename'],
            '',
            ['rename'  => "<a href='/admin/problemexport/downloaddata?id=" . $this->inputInfo['id'] . "&filename=" . $this->inputInfo['rename'] . "' filename='" . $this->inputInfo['rename'] . "'>" .  $this->inputInfo['rename'] . "</a>"]
        );
    }
    // 验证目标文件夹文件名合法性
    public function VerificationDir($path, $deleteFile = true)
    {
        // 如果允许删除非法文件，则都删除后返回true，删除失败返回false。如果不允许删除，直接返回false
        if (is_dir($path) && ($handle = opendir($path))) {
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    if (is_dir($path . '/' . $file)) {
                        //文件夹直接删除。problem附带文件不该有子文件夹
                        if ($deleteFile) {
                            if (!DelDirs($path . '/' . $file))
                                return false;
                        } else {
                            return false;
                        }
                    } else if (!preg_match($this->attachFileNameRe, strtolower($file))) {
                        if ($deleteFile) {
                            if (!unlink($path . '/' . $file))
                                return false;
                        } else
                            return false;
                    }
                }
            }
            //关闭句柄
            closedir($handle);
        } else {
            return false;
        }
        return true;
    }
    //获取目标文件夹文件列表
    public function GetDir()
    {
        DelTimeExpireFolders($this->inputInfo['path'], $this->ojPath['export_keep_time']);
        $filelist = [];
        if (is_dir($this->inputInfo['path']) && ($handle = opendir($this->inputInfo['path']))) {
            $i = 1;
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != "..") {
                    $filetime = filemtime($this->inputInfo['path'] . '/' . $file);
                    $filelist[] = [
                        'file_lastmodify'     => date("Y-m-d h:i:s", $filetime),
                        'file_name'           => $file,
                        'file_size'           => round(filesize($this->inputInfo['path'] . '/' . $file) / 1024, 2),
                        'file_type'           => "Import",
                        'file_url'            => "/admin/problemexport/downloaddata?item=" . $this->inputInfo['item'] . "&filename=" . $file
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
    public function problem_export_filemanager()
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot export problem according to your privilege");
        $this->assign([
            'inputinfo'     => $this->inputInfo,
            'iteminfo'      => $this->itemInfo,
            'file_url'      => '/admin/problemexport/problem_export_filemanager_ajax?item=' . $this->inputInfo['item'] . '&id=' . $this->inputInfo['id'],
            'delete_url'    => '/admin/problemexport/file_delete_ajax?item=' . $this->inputInfo['item'] . '&id=' . $this->inputInfo['id'],
            'rename_url'    => '/admin/problemexport/file_rename_ajax?item=' . $this->inputInfo['item'] . '&id=' . $this->inputInfo['id'],
            'upload_url'    => '/admin/problemexport/upload_ajax',
            'method_button' => 'Do!',        //file_type 那一列的表头名字，这列当个功能button用
            'attach_notify' => 'Files will be automatically deleted after ' . $this->ojPath['export_keep_time'] . ' days. Only "zip" allowed', // 上传按钮旁的提示信息
            'fire_url'      =>    '/admin/problemexport/problem_import_ajax'    //执行题目导入的链接
        ]);
        return $this->fetch();
    }
    public function problem_export_filemanager_ajax()
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot import problem according to your privilege");

        $filelist = $this->GetDir();
        //        $ret['total'] = count($filelist);
        //        $ret['rows'] = $filelist;
        return $filelist;
    }
    public function problem_import_ajax()
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot import problem according to your privilege");
        if (!preg_match($this->filenameRe, $this->inputInfo['filename']))
            $this->error("Please select a valid file");

        $importPath = $this->ojPath['export_problem'];
        if (!file_exists($importPath . '/' . $this->inputInfo['filename']))
            $this->error("No such file");

        //写数据库前先验证文件夹是否有读写权限
        $notWritablePath = '';
        if (!MakeDirs($this->ojPath['testdata']) || !is_writable($this->ojPath['testdata']))
            $notWritablePath .= "<br/>Judge data path: " . $this->ojPath['testdata'];
        if (!MakeDirs($this->ojPath['PUBLIC'] . $this->ojPath['problem_ATTACH']) || !is_writable($this->ojPath['PUBLIC'] . $this->ojPath['problem_ATTACH']))
            $notWritablePath .= "<br/>Problem attach path: " . $this->ojPath['PUBLIC'] . $this->ojPath['problem_ATTACH'];
        if (!is_writable($this->ojPath['export_problem']))
            $notWritablePath .= "<br/>Export file path: " . $this->ojPath['export_problem'];
        if (!MakeDirs($this->ojPath['import_problem_temp']) || !is_writable($this->ojPath['import_problem_temp']))
            $notWritablePath .= "<br/>Import temporary path: " . $this->ojPath['import_problem_temp'];
        if ($notWritablePath != '' || strlen(trim($notWritablePath)) > 0)
            $this->error('These paths are not writable, you need "chmod" to modify:' . $notWritablePath);


        $filePath = $importPath . '/' . $this->inputInfo['filename']; //导入题目文件的绝对路径
        $date = date('Y-m-d-H-i-s');
        $importPath = $this->ojPath['export_problem'];

        //在建立新的临时文件夹之前，删除旧的因为程序崩溃导致的未删除的临时文件夹。
        DelTimeExpireFolders($this->ojPath['import_problem_temp'], $this->ojPath['export_temp_keep_time']);
        //导入题目过程中文件中转的临时文件夹
        $importTempPath = $this->ojPath['import_problem_temp'] . '/' . $date . '-' . session('user_id');
        //        $importTempPath = $this->ojPath['import_problem_temp'] . '/test';
        if (!MakeDirs($importTempPath) || !MakeDirs($importPath))
            $this->error("Folder permission denied");
        $zippy = Zippy::load();
        $archive = $zippy->open($filePath);
        $archive->extract($importTempPath);
        $problemListPath = $importTempPath . '/problemlist.json';
        if (!file_exists($problemListPath))
            $this->error('Problem file not exist');
        $problemList = json_decode(file_get_contents($importTempPath . '/' . 'problemlist.json'), true);
        if ($problemList == null)
            $this->error('Problem file damaged');
        $Problem = db('problem');
        $addedList = [];
        $failedList = [];
        $judgeDataFolderPermission = true; // judge data 文件夹权限
        $attachFolderPermission = true;     // 图片等题目描述文件 文件夹权限
        $attachFailedList = [];             // 图片等题目描述文件失败列表。失败原因是内部文件名不合法
        foreach ($problemList as $problem) {
            $problemInsert = [
                'title'             =>  $problem['title'],
                'description'       =>  $problem['description'],
                'input'             =>  $problem['input'],
                'output'            =>  $problem['output'],
                'sample_input'      =>  $problem['sample_input'],
                'sample_output'     =>  $problem['sample_output'],
                'spj'               =>  $problem['spj'],
                'hint'              =>  $problem['hint'],
                'source'            =>  $problem['source'],
                'author'            =>  $problem['author'], //额外加的字段
                'attach'            =>  isset($problem['attach']) ? $problem['attach'] : '', //额外加的字段
                'in_date'           =>  $problem['in_date'],
                'time_limit'        =>  $problem['time_limit'],
                'memory_limit'      =>  $problem['memory_limit'],
                'defunct'           =>  '1',
                'accepted'          =>  0,
                'submit'            =>  0,
            ];
            // **********
            // 处理题目描述，如果只提供了 md 没有提供 html，现场编译一次
            foreach (['description', 'input', 'output', 'hint', 'source', 'author'] as $key) {
                if (trim($problem[$key]) == '') {
                    $problemInsert[$key] = ParseMarkdown($problem[$key . "_md"]);
                }
            }

            if (strlen($problemInsert['attach']) == 0)
                $problemInsert['attach'] = $this->AttachFolderCalculation(session('user_id'));
            if ($Problem->where('attach', $problemInsert['attach'])->find()) {
                // 20230617添加：用 attach 字段避免重复导入题目
                $failedList[] = $problem['problem_id'] . ':' . $problem['title'] . ' # Exists';
                continue;
            }
            if (($problem_id = $Problem->insertGetId($problemInsert)) == false) {
                //********导入失败的文件列表
                $failedList[] = $problem['problem_id'] . ':' . $problem['title'];
                continue;
            }
            //********导入成功的文件列表（judge data 可能导入失败）
            $addedList[] = $problem['problem_id'] . ':' . $problem['title'];
            // 导入markdown

            $problemMdInsert = [
                'problem_id'        => $problem_id, //这里很重要，要和新插入的id一致
                'description'        => $problem['description_md'],
                'input'                => $problem['input_md'],
                'output'            => $problem['output_md'],
                'hint'                => $problem['hint_md'],
                'source'            => $problem['source_md'],
                'author'            => $problem['author_md'],
            ];
            $Problem_md = db('problem_md');
            if ($Problem_md->where('problem_id', $problem_id)->find() != null)
                $Problem_md->where('problem_id', $problem_id)->update($problemMdInsert);
            else
                $Problem_md->insert($problemMdInsert);
            // 导入judge data
            // problem_new_id 是导出时从 1 开始排序的编号，和导出的数据、attachfile文件夹名对应
            $importTestDataPath = $importTempPath . '/' . 'TEST_' . str_pad($problem['problem_new_id'], 5, '0', STR_PAD_LEFT);
            if (is_dir($importTestDataPath)) {
                $aimJudgeDataPath = $this->ojPath['testdata'] . '/' . $problem_id;

                if (DelDirs($aimJudgeDataPath) === false) // 这里要 ===，因为会返回 null
                    $judgeDataFolderPermission = false;
                //PHP bug?，rename文件夹跨“驱动器”会提示copy()xxx，反正有问题
                //                else if(!rename($importTestDataPath, $aimPath))
                //                    $judgeDataFolderPermission = false;
                exec("mv " . $importTestDataPath . ' ' . $aimJudgeDataPath);
            }
            // 导入题目附件
            $importAttachFilePath = $importTempPath . '/' . 'ATTACH_' . str_pad($problem['problem_new_id'], 5, '0', STR_PAD_LEFT);
            if (is_dir($importAttachFilePath)) {
                $aimAttachPath = $this->ojPath['PUBLIC'] . $this->ojPath['problem_ATTACH'] . '/' . $problemInsert['attach'];
                if (DelDirs($aimAttachPath) === false) { // 这里要 ===，因为会返回 null
                    $attachFolderPermission = false;
                } else if ($this->VerificationDir($importAttachFilePath)) { 
                    //因为 attach file 是暴露在OJ公共文件夹下的，所以要VerificationDir()严格验证。
                    //                    if(!rename($importAttachFilePath, $aimPath))
                    //                        $attachFolderPermission = false;
                    //PHP bug?，rename文件夹跨“驱动器”会提示copy()xxx，反正有问题
                    exec("mv " . $importAttachFilePath . ' ' . $aimAttachPath);
                } else {
                    $attachFailedList[] = $problem['problem_id'] . ':' . $problem['title'];
                }
            }
        }
        //文件移动到正规目录后删除临时文件夹
        DelDirs($importTempPath);
        $this->success("Imported", '/', [
            'addedList'                 => $addedList,
            'failedList'                => $failedList,
            'judgeDataFolderPermission' => $judgeDataFolderPermission,
            'attachFolderPermission'    => $attachFolderPermission,
            'attachFailedList'          => $attachFailedList,
        ]);
    }

    public function problem_export()
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot export problem according to your privilege");
        return $this->fetch();
    }
    public function problem_export_ajax()
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot export problem according to your privilege");
        //$ep: Export Parameter
        $test_data_check = input('test_data_check', 'false');
        $attach_file_check = input('attach_file_check', 'false');
        $ep = [
            'start_pid' => intval(input('start_pid')),
            'end_pid'     => intval(input('end_pid')),
            'pid_list'     => trim(input('pid_list')),
            'ex_cid'     => trim(input('ex_cid')),
            'test_data_check' => $test_data_check == 'on' || $test_data_check == 'true',
            'attach_file_check' => $attach_file_check == 'on' || $attach_file_check == 'true',
        ];
        if ($ep['end_pid'] == 0)
            $ep['end_pid'] = $ep['start_pid'];
        $whereMap = [];
        $orderMap = "";
        $exportedTag = "";  // 导出文件名的标识部分
        if ($ep['start_pid'] > 0) {
            // 按起止题号导出
            //这句和下面那句重复了，只是目前还是把所有大于30个题的请求都拦劫吧，一次导太多题不好。。。
            if ($ep['end_pid'] - $ep['start_pid'] > 30)
                $this->error("You'd better not export so many problems once (more than 30).");

            if ($ep['end_pid'] - $ep['start_pid'] > 30 && ($ep['test_data_check'] || $ep['attach_file_check']))
                $this->error('Together with related files, you cannot export so many problems.');
            else if ($ep['end_pid'] < $ep['start_pid'])
                $this->error("End problem ID should be bigger than start problem ID.", '', $ep);
            $whereMap = [
                "p.problem_id" => ['between', [$ep['start_pid'], $ep['end_pid']]]
            ];
            $exportedTag = $ep['start_pid'] . '-' . $ep['end_pid'];
        } else if ($ep['pid_list'] != '') {
            // 按离散题号列表导出
            $problem_list = explode(",", $ep['pid_list']);
            $problem_list_export = [];
            foreach ($problem_list as $val) {
                $problem_list_export[] = intval($val);
            }
            $problem_list_export = array_unique($problem_list_export);
            $whereMap = [
                "p.problem_id" => ['in', $problem_list_export]
            ];
            // 文件开头需要 think\db\Expression;  且 db 需要小写...
            $orderMap = new Expression("field(p.problem_id," . implode(",", $problem_list_export) . ")");
            $exportedTag = 'pidlist-' . count($problem_list_export) . '-start-' . $problem_list_export[0];
        } else if ($ep['ex_cid'] != '') {
            // 按比赛题目列表导出
            $ex_cid = intval($ep['ex_cid']);
            $ContestProblem = db('contest_problem');
            $problem_list = $ContestProblem
                ->where('contest_id', '=', $ex_cid)
                ->order('num', 'asc')
                ->field('problem_id')
                ->select();
            $problem_list_export = [];
            foreach ($problem_list as $val) {
                $problem_list_export[] = intval($val['problem_id']);
            }
            $problem_list_export = array_unique($problem_list_export);
            if (count($problem_list_export) == 0)
                $this->error("Cannot find problems for contest $ex_cid");
            $whereMap = [
                "p.problem_id" => ['in', $problem_list_export]
            ];
            $orderMap = new Expression("field(p.problem_id," . implode(",", $problem_list_export) . ")");
            $exportedTag = 'num-' . count($problem_list_export) . '-cid-' . $ep['ex_cid'];
        } else {
            $this->error("The problem ID is not valid");
        }
        $Problem = db('problem');
        $problemList = $Problem->alias('p')
            ->join('problem_md pmd', 'p.problem_id = pmd.problem_id', 'left')
            ->where($whereMap)
            ->order($orderMap)
            ->field([
                'p.problem_id problem_id',
                'p.title title',
                'p.description description',
                'p.input input',
                'p.output output',
                'p.sample_input sample_input',
                'p.sample_output sample_output',
                'p.spj spj',
                'p.hint hint',
                'p.source source',
                'p.author author',    //额外加的字段
                'p.attach attach',    //额外加的字段
                'p.in_date in_date',
                'p.time_limit time_limit',
                'p.memory_limit memory_limit',
                'p.defunct defunct',
                'p.accepted accepted',
                'p.submit submit',
                'p.solved solved',
                'pmd.description description_md',
                'pmd.input input_md',
                'pmd.output output_md',
                'pmd.hint hint_md',
                'pmd.source source_md',
                'pmd.author author_md',
            ])
            ->select();
        $outputInfo = "Some problems weren't exported. The problems you don't own:<br/>";
        $outputProblem = [];
        $i = 1;
        $noPrivilegeFlag = false;
        foreach ($problemList as $problem) {
            if (!IsAdmin('problem', $problem['problem_id'])) {
                $noPrivilegeFlag = true;
                $outputInfo .= $problem['problem_id'] . '<br/>';
                continue;
            }
            $problem['problem_new_id'] = $i;
            $i++;
            $outputProblem[] = $problem;
        }
        if (!count($outputProblem)) {
            if ($noPrivilegeFlag)
                $this->error("The range you selected is empty");
            else
                $this->error("You have no privilege of these problems");
        }

        $this->ZipProblemFiles($outputProblem, $ep, $exportedTag);

        $retMsg = "Successful exported. Open export file manager to see it.<br/>";
        if (count($outputProblem) != count($problemList))
            $retMsg .= $outputInfo;
        $this->success($retMsg, null, ['url' => '/admin/judge/problem_export_filemanager']);
    }
    public function ZipProblemFiles($outputProblem = null, $ep = [], $exportedTag = '-notag-')
    {
        if (!IsAdmin('administrator'))
            $this->error("You cannot export problem according to your privilege");
        $ojPath = config('OjPath');
        $testDataPath = $ojPath['testdata'];
        $problemAttachPath = $ojPath['PUBLIC'] . '/' . $ojPath['problem_ATTACH'];

        //在建立新的临时文件夹之前，删除旧的因为程序崩溃导致的未删除的临时文件夹。
        DelTimeExpireFolders($ojPath['export_problem_temp'], $this->ojPath['export_temp_keep_time']);
        //导出题目过程中文件中转的临时文件夹
        $exportTempRoot = $ojPath['export_problem_temp'];
        $exportRoot = $ojPath['export_problem'];

        //临时放置打包文件的文件夹
        $date = date('Y-m-d-H-i-s');
        $exportMakeFolder = $exportTempRoot . '/' . $date . '-' . session('user_id');
        //        $exportMakeFolder = $exportTempRoot . '/test';
        if (!MakeDirs($exportMakeFolder))
            $this->error('Folder permission denied.');
        if (!MakeDirs($exportRoot))
            $this->error('Folder permission denied.');
        $dirs_to_zip = [];
        foreach ($outputProblem as $problem) {
            if ($ep['test_data_check'] && is_dir($testDataPath . '/' . $problem['problem_id']))
                $dirs_to_zip['TEST_' . str_pad($problem['problem_new_id'], 5, '0', STR_PAD_LEFT)] = $testDataPath . '/' . $problem['problem_id'];
            if ($ep['attach_file_check'] && is_dir($problemAttachPath . '/' . $problem['attach'])) //这里改成attach字段了，用保存的固定路径，而不用id号
                $dirs_to_zip['ATTACH_' . str_pad($problem['problem_new_id'], 5, '0', STR_PAD_LEFT)] = $problemAttachPath . '/' . $problem['attach'];
        }
        file_put_contents($exportMakeFolder . '/problemlist.json', json_encode($outputProblem));
        $dirs_to_zip[] = $exportMakeFolder . '/problemlist.json';

        $fileName = 'CSGOJ-' . $exportedTag . '.zip';
        //这种情况基本不会有，不过为防止建立zip失败
        if (file_exists($exportMakeFolder . '/' . $fileName))
            unlink($exportMakeFolder . '/' . $fileName);
        $zippy = Zippy::load();
        set_time_limit(180); // 有些数据比较大，可能需要压缩久一点，php默认30秒超时，所以这里改一下
        $archive = $zippy->create(
            $exportMakeFolder . '/' . $fileName,
            $dirs_to_zip,
            true
        );
        rename($exportMakeFolder . '/' . $fileName, $exportRoot . '/' . session('user_id') . '-' . $date . '-' . $exportedTag . '.zip');

        //文件移动到正规目录后删除临时文件夹
        DelDirs($exportMakeFolder);
        return true;
    }
}
