<?php
// Author: CSGrandeur

return [
    'SCHOOL_RANK_TEAMNUM' => 1, //contest school rank 计算学校排名的队伍个数
    // userinfo_rule、userinfo_msg用于验证用户注册信息的预定义规则
    'userinfo_rule' => [
        'user_id'   => ['require', 'min:3', 'max:20', '/^[a-zA-Z0-9_]+$/'],
        'nick'      => 'max:32',
        'email'     => 'require|email|max:64',
        'school'    => 'max:32',
        'password'  => 'min:6|max:64'
    ],
    'userinfo_msg' => [
        'user_id.require'               => 'User ID needed.',
        'user_id.min'                   => 'User ID should have at least 3 characters.',
        'user_id.max'                   => 'User ID should not exceed 20 characters.',
        'user_id./^[a-zA-Z0-9_]+$/'     => 'Only number, letters and underlines are allowed for User ID.',
        'nick.max'                      => 'Nick should not exceed 30 characters.',
        'email.require'                 => 'Email address needed.',
        'email.email'                   => 'Please enter a valid E-mail address.',
        'email.max'                     => 'Email should not exceed 64 characters.',
        'school.max'                    => 'School name should not exceed 32 characters.',
        'password.min'                  => 'Password should have more than 6 characters.',
        'password.max'                  => 'Password should have less than 64 characters.'
    ],
    'EMAIL_VERIFY_WAIT'     => 20,
    'OJ_LANGUAGE'    =>[
        0   => 'C',
        1   => 'C++',
        3   => 'Java',
        6   => 'Python3',
        // 17  => 'Go',
        // 2    => 'Pascal'
    ],
    'OJ_LANGUAGE_NORMALIZED' => [
        // 对接评测机支持的名称，兼容旧代码
        0   => 'c',
        1   => 'cpp',
        3   => 'java',
        6   => 'python',
        17  => 'go'
    ],
    'OJ_RESULTS'    =>[
        4   =>  'AC',
        5   =>  'PE',
        6   =>  'WA',
        7   =>  'TLE',
        8   =>  'MLE',
        9   =>  'OLE',
        10  =>  'RE',
        11  =>  'CE',
        90  =>  'JF',  // Judge Failed - 评测失败
        13  =>  'Tested',
        0   =>  'PD',
        1   =>  'PR',
        2   =>  'CI',
        3   =>  'RJ',
    ],
    'OJ_RESULTS_HTML'    =>[
        4       =>  ['success', 'Accepted'],
        5       =>  ['danger' , 'Presentation Error'],
        6       =>  ['danger' , 'Wrong Answer'],
        7       =>  ['warning', 'Time Limit Exceed'],
        8       =>  ['warning', 'Memory Limit Exceed'],
        9       =>  ['warning', 'Output Limit Exceed'],
        10      =>  ['warning', 'Runtime Error'],
        11      =>  ['info', 'Compile Error'],  // CE不再罚时，故换成info颜色
        90      =>  ['info', 'Judge Failed'],  // 评测失败
        13      =>  ['default', 'Tested'],
        100     =>  ['default', 'Unknown'],
        0       =>  ['default res_running', 'Pending'],
        1       =>  ['default res_running', 'Pending Rejudging'],
        2       =>  ['default res_running', 'Compiling'],
        3       =>  ['info res_running', 'Running&Judging'],
    ],
    'OJ_RESULTS_SHORT'    =>[
        4       =>  'AC',
        5       =>  'PE',
        6       =>  'WA',
        7       =>  'TLE',
        8       =>  'MLE',
        9       =>  'OLE',
        10      =>  'RE',
        11      =>  'CE',
        90      =>  'JF',
        13      =>  'TD',
        100     =>  'UN',
        0       =>  'PD',
        1       =>  'PR',
        2       =>  'CI',
        3       =>  'RJ',
    ],
    'OJ_CONFIG' => [
        'user_id_maxlen' => 30
    ],
    'OJ_UPLOAD_ATTACH_MAXSIZE'      => 20971520,    // 一般文件上传尺寸限制，比如题目描述的插图
    'OJ_UPLOAD_TESTDATA_MAXSIZE'    => 536870912,   // 判题数据的尺寸限制
    'OJ_UPLOAD_IMPORT_MAXSIZE'      => 536870912,   // 导入题目最大尺寸
    'OJ_UPLOAD_MAXNUM'              => 20,          // 一次最多上传多少个文件

    'OJ_RANKDYNAMIC_CACHE_OPTION'   => ['type'=>'File', 'expire'=>10, 'path'=>CACHE_PATH, 'prefix'=>'csgoj'],  //新版Rank的cache配置
    'OJ_SUBMIT_WAIT_TIME'           => 5, //两次提交code时间间隔
    'OJ_TOPIC_WAIT_TIME'            => 5, //两次提交topic时间间隔
    'OJ_TEST_DOWNLOAD_WAIT_TIME'    => 720, //两次下载测试数据时间间隔

    'SPIDER_TOKEN'    => [
        // 给virtual judge 提供的抓题token验证
        'isun_voj' => 'c295ae8ba0980d2b951660a728b60171',
    ],


    'OJ_PASSBACK_CACHE_OPTION' => ['type'=>'File', 'expire'=>1200, 'path'=>CACHE_PATH, 'prefix'=>'csgoj'],  //密码找回的cache配置
];
