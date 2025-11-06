<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/2
 * Time: 20:49
 */
// OJ 管理员相关权限设定记录
$_ADMIN_CFG = [
    'online'    =>[
        'OJ_ADMIN_LIST'    =>[
            //有Admin页面任务的特权
            'super_admin'           =>  ['cn' => '超管', 'en' => 'Super Admin'],           //可以添加删除任意管理员
            'administrator'         =>  ['cn' => '管理员', 'en' => 'Administrator'],       //可以添加删除下面的管理员，并具有下面其他所有管理员权限
            'news_editor'           =>  ['cn' => '文章管理员', 'en' => 'Article Editor'],  //可以添加[公告]，只可以修改自己添加的[公告]
            'problem_editor'        =>  ['cn' => '题目管理员', 'en' => 'Problem Editor'],  //可以添加[题目]，只可以修改自己添加的[题目]
            'contest_editor'        =>  ['cn' => '比赛管理员', 'en' => 'Contest Manager'], //可以添加[比赛]，只可以修改自己添加的[比赛]
            'password_setter'       =>  ['cn' => '用户管理员', 'en' => 'Password Setter'], //可以修改非管理员的密码
        ],
        'OJ_PRIVILEGE' =>[
            //没有Admin页面任务的特权
            'source_browser'   =>  ['cn' => '源码观察员', 'en' => 'Source Code Browser'],   //可以查看源码
            'judger'           =>  ['cn' => '评测机', 'en' => 'Judger'],                    //可以修改solution的判题结果
        ],
        'OJ_PRE_ADMIN' => [
            // 管理权限前缀 到 管理员名称 的映射
            'new_'      =>  'news_editor',              //可管理该公告
            'pro_'      =>  'problem_editor',           //可管理该题目
            'con_'      =>  'contest_editor',           //可管理该比赛（contest表里的contest）

            'c'         =>   '',                    //可参加该比赛（contest表里的contest），不是管理员权限
        ],
        'OJ_ITEM_PRI' => [
            'news'          => 'new_',
            'problem'       => 'pro_',
            'contest'       => 'con_',
        ]
    ],
];
return $_ADMIN_CFG;
