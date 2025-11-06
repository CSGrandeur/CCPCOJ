<?php
namespace app\ojtool\controller;

use think\Controller;
use think\response\Json;

/**
 * OJ API 控制器
 * 提供 OJ 所需的各种数据接口
 */
class Ojapi extends Ojtoolbase
{
    
    public function _initialize()
    {
        $this->OJMode();
        $this->OjapiInit();
    }
    protected function OjapiInit() {
        if(!IsLogin()) {
            $this->error("需要登录 (Needs Login)");
        }
    }
    /**
     * 获取语言配置（原始格式）
     * @return Json
     */
    public function getLanguageConfig(){
        $languages = config('CsgojConfig.OJ_LANGUAGE');
        if (empty($languages)) {
            return $this->response(false, '语言配置未找到', [], 'CONFIG_NOT_FOUND');
        }
        return $this->response(true, '获取语言配置成功', $languages);
    }
    
}
