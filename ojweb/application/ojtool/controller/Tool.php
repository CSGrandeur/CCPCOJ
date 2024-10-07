<?php
namespace app\ojtool\controller;
use think\Controller;
class Tool extends Ojtoolbase
{
    public function award() {
        if(!IsAdmin()) {
            $this->error("You are not admin.");
        }
        $this->assign("pagetitle", "Award");
        return $this->fetch();
    }
    public function contest2print() {
        $this->assign("pagetitle", "Contest Problem Print");
        return $this->fetch();
    }
    public function time_page_set() {
        // 监考时显示时间的小工具
        $this->assign("pagetitle", "Time Page Set");
        return $this->fetch();
    }
    public function time_page_show() {
        // 监考时显示时间的小工具
        $this->assign("pagetitle", "Time Page Show");
        return $this->fetch();
    }
    public function polygon_parser() {
        $this->assign("pagetitle", "Polygon Parser");
        return $this->fetch();
    }
}
