<?php
namespace app\csgoj\controller;
use think\Controller;
class Userrank extends Csgojbase
{
    public function index()
    {
        $this->assign(['pagetitle' => 'User Rank']);
        return $this->fetch();
    }
    public function userrank_ajax()
    {
        $offset        = intval(input('offset'));
        $limit        = intval(input('limit'));
        $search        = trim(input('search/s'));

        $map = [];
        if(strlen($search) > 0)
            $map['user_id|nick|school'] =  ['like', "%$search%"];

        $ret = [];
        $Users = db('users');
        $userlist = $Users
            ->field('user_id, nick, school, volume, solved, submit')
            ->where($map)
            ->order(['solved' => 'desc', 'submit' => 'asc', 'user_id' => 'asc'])
            ->limit("$offset, $limit")
            ->cache(60)
            ->select();
        $ret['total'] = $Users->where($map)->count();
        $ret['order'] = 'desc';
        $ret['rows'] = $userlist;
        return $ret;
    }
}
