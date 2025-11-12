<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/17
 * Time: 12:50
 */
//OJ模式
return [
    'OJ_MODE_ALLOW_MODULE' => [
        'online'    => [
            'cpc' => ['index', 'admin', 'cr', 'csgoj', 'cpcsys', 'user', 'tt', 'ojtool', 'outrank'],
            'exp' => ['index', 'admin', 'csgoj', 'user', 'expsys', 'ojtool', 'outrank'],
        ],
        'cpcsys'    => [
            'cpc' => ['cpcsys', 'admin', 'user', 'ojtool', 'outrank'],
            'exp' => ['expsys', 'admin', 'user', 'ojtool', 'outrank'],
        ]
    ],
];