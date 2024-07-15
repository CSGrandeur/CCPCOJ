<?php
namespace app\csgoj\controller;
use think\Controller;
use think\Db;
class Problemarchive extends Csgojbase {

    public function _initialize()
    {
        $this->OJMode();
        $this->assign(['pagetitle' => 'Problem Archive']);
    }
    public function index() {
        return $this->fetch();
    }
    public function problemarchive_ajax() {
        $Problem = db('problem');
        return $Problem->where('archived', 1)->field(["source", "MIN(in_date) as in_date, GROUP_CONCAT(author SEPARATOR ',') as author"])->order('in_date', 'desc')->group('source')->cache(60)->select();
    }
}
