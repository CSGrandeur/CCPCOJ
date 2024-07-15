<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 21:15
 */
namespace app\admin\controller;
class Itemstatuschange extends Adminbase
{
	//用于修改defunct、private等标记的Controller
	var $itemInfo;
	var $changeInfo;
	var $allowField;
	public function _initialize()
	{
		$this->OJMode();
		$this->allowField = [
			'defunct'	=> [
				[
					'status_str'	=> 'Available',
					'status_class'	=> 'success',
				],
				[
					'status_str'	=> 'Reserved',
					'status_class'	=> 'warning',
				],
			],
			'private'	=> [
				[
					'status_str'	=> 'Public',
					'status_class'	=> 'success',
				],
				[
					'status_str'	=> 'Private',
					'status_class'	=> 'primary',
				],
			],
			'archived' 	=> [
				[
					'status_str'	=> 'UnArchive',
					'status_class'	=> 'default',
				],
				[
					'status_str'	=> 'Archived',
					'status_class'	=> 'info',
				],
			]
		]; //以后扩展功能再改此配置
		$this->AdminInit();
		$this->ItemStatusAuthentication();
	}
	private function ItemStatusAuthentication()
	{
		//$status_item指 defunct 或 private(contest里的)
		//Admin ChangeDefunct管理的通用验证，$item = news、problem、contest
		$this->changeInfo = [
			'item' 		=> trim(input('item')),
			'field'		=> trim(input('field')),
			'id' 		=> trim(input('id')),
			'status'	=> trim(input('status'))
		];

		if(!array_key_exists($this->changeInfo['field'], $this->allowField))
		{
			//只能修改允许的字段。比如defunct、private
			$this->error("You can't change this field");
		}
		$this->privilegeStr = $this->changeInfo['item']; //比如 problem
		if(array_key_exists($this->privilegeStr, $this->ojItemPri))
		{
			$this->privilegePrefix = $this->ojItemPri[$this->privilegeStr]; 	//比如 pro_
			$this->privilegeName = $this->ojPreAdmin[$this->privilegePrefix];	//比如 problem_editor
			//先判断此模块权限
			if(!IsAdmin($this->privilegeName))
				$this->error("You don't have this privilege", '/'.$this->request->module(), '', 1);
		}
		else
		{
			//无此模块
			$this->error('No such work like "' . $this->changeInfo['item'] . '"');
		}
		if (!IsAdmin($this->changeInfo['item'], $this->changeInfo['id'])) {
			//判断有无管理此模块下对应id的专有权限
			$this->error("You don't own this item.");
		}
		$this->itemInfo = db($this->changeInfo['item'])
			->where($this->changeInfo['item'] . '_id', $this->changeInfo['id'])
			->field($this->changeInfo['field'])
			->find();
		if (!$this->itemInfo) {
			//无此条目
			$this->error('No such ' . $this->changeInfo['item'] . '.');
		}
	}

	public function change_status_ajax()
	{
		$this->itemInfo[$this->changeInfo['field']] = $this->changeInfo['status'];
		if (!db($this->changeInfo['item'])->where($this->changeInfo['item'] . '_id', $this->changeInfo['id'])->update($this->itemInfo)) {
			$this->error('Change '.$this->changeInfo['field'].' failed.');
		}

		$this->success(
			'Succesfully Changed.',
			null,
			[
				'status'=>$this->changeInfo['status'],
				'status_str'=>$this->allowField[$this->changeInfo['field']][$this->changeInfo['status']]['status_str'],
				'status_class_rmv'=>$this->allowField[$this->changeInfo['field']][!$this->changeInfo['status']]['status_class'],
				'status_class'=>$this->allowField[$this->changeInfo['field']][$this->changeInfo['status']]['status_class'],
			]
		);
	}
}