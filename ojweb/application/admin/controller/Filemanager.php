<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 20:24
 */
namespace app\admin\controller;
class Filemanager extends Filebase
{
	public function _initialize()
	{
		$this->OJMode();
		$this->AdminInit();
		$this->FilebaseInit();
		// 扩展允许的文件类型：添加常见开发工具、安装包和二进制文件类型
		// 注意：为了安全，不包含脚本文件类型（.sh, .bat, .cmd, .ps1等）
		$allowStr = 'jpg,png,gif,bmp,ico,svg,rar,zip,7z,tar,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,exe,msi,deb,rpm,dmg,bin,jar,apk,appimage,run,iso,img,whl,egg,war,ear';
		$this->filenameRe = "/^[\w\-.\u4e00-\u9fa5()]+\.(jpg|png|gif|bmp|svg|ico|rar|zip|7z|tar|pdf|doc|docx|xls|xlsx|ppt|pptx|txt|exe|msi|deb|rpm|dmg|bin|jar|apk|appimage|run|iso|img|whl|egg|war|ear)$/ui";
		$this->filenameReMsg = "只允许包含字母、数字、中文的文件名<br/>Only " . $allowStr . " with <strong>alphanumeric or Chinese file name</strong> allowed";
		$this->maxFileSize = config('CsgojConfig.OJ_UPLOAD_ATTACH_MAXSIZE');
		$this->assign('maxfilesize', $this->maxFileSize);
		$this->maxFileNum = config('CsgojConfig.OJ_UPLOAD_MAXNUM');
		$this->validateRule = ['size' => $this->maxFileSize,  'ext'=>$allowStr];

		$this->GetInput();
		$this->FileAuthentication();
		$this->GetPath();
	}
	public function filemanager() {
		$this->assign([
			'inputinfo'		=> $this->inputInfo,
			'iteminfo' 		=> $this->itemInfo,
			'file_url'		=> '/admin/filemanager/filemanager_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
			'delete_url'	=> '/admin/filemanager/file_delete_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
			'rename_url'	=> '/admin/filemanager/file_rename_ajax?item='.$this->inputInfo['item'].'&id='.$this->inputInfo['id'],
			'upload_url'	=> '/admin/filemanager/upload_ajax',
			'method_button'	=> 'CopyUrl',
			'file_regex'	=> $this->filenameRe,
			'attach_notify'	=> $this->filenameReMsg, // 上传按钮旁的提示信息
		]);
		return $this->fetch();
	}
	public function GetPath()
	{
		$itemAttachPath = $this->ojPath['PUBLIC'] . $this->ojPath[$this->inputInfo['item'] . '_ATTACH'];

		$this->inputInfo['path'] =  $itemAttachPath . '/' . $this->itemInfo['attach'];
		if(!MakeDirs($this->inputInfo['path']))
		{
			$this->error('Folder permission denied.');
			return $this->inputInfo;
		}
		return $this->inputInfo;
	}
}