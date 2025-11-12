<?php

namespace app\outrank\controller;

class Rank extends Outrankbase {
    function index() {
        $outrank_uuid = input('outrank_uuid/s', '');
        
        if (empty($outrank_uuid)) {
            $this->error('Missing outrank_uuid parameter');
        }
        
        // 查询 outrank 信息
        $outrank = db('outrank')->where('outrank_uuid', $outrank_uuid)->find();
        
        if (!$outrank) {
            $this->error('Outrank not found');
        }
        
        // assign 变量
        $this->assign('outrank_uuid', $outrank_uuid);
        $this->assign('outrank', $outrank);
        $this->assign('timeStamp', microtime(true)); // 用于计算时间差
        
        return $this->fetch();
    }
}