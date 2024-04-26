<?php
// Author: CSGrandeur

use think\Env;
return [
    'OJ_CDN'                => Env::get('OJ_CDN', 'local'),                 //'bootcdn', 'cdnjs', 'local', in .env
    'OJ_SITE'               => Env::get('OJ_SITE', 'online'),               // online/local
    'OJ_MODE'               => Env::get('OJ_MODE', 'cpcsys'),               // online/cpcsys
    'OJ_STATUS'             => Env::get('OJ_STATUS', 'cpc'),                // cpc/exp
    'OJ_OPEN_OI'            => Env::get('OJ_OPEN_OI', false),               // add pass_rate to status
    'OJ_NAME'               => Env::get('OJ_NAME', 'CCPC'),                 // OJ name to display
    'ICP_RECORD'            => Env::get('ICP_RECORD', ''),                  // ICP RECORD
    'GA_CODE'               => Env::get('GA_CODE', false),                  // Google Analytics
    'GIT_DISCUSSION'        => Env::get('GIT_DISCUSSION', ''),              // Github Discussion Url
    'OJ_SESSION'            => Env::get('OJ_SESSION', 'CCPC'),              // SESSION prefix online/local/otherxxxx
    'OJ_SECRET'             => Env::get('OJ_SECRET', 'cpc_secret'),         // SECRET for OJ encryptions
    'OJ_BASE_URL'           => Env::get('OJ_BASE_URL', 'http://127.0.0.1/'),
    'OJ_PASSBACK_MAIL'      => Env::get('OJ_PASSBACK_MAIL', '<passbackmail>@163.com'),
    'OJ_PASSBACK_MAIL_PASS' => Env::get('OJ_PASSBACK_MAIL_PASS', '987654321'),
    'OJ_ADDITION_LINK'      => Env::get('OJ_ADDITION_LINK', ''),

    // OJ Mode Special
    // Only in expsys MODE
    'ALLOW_WA_INFO'         => Env::get('EXP_ALLOW_WA_INFO', false),                    // Show WA info to all users
    'ALLOW_TEST_DOWNLOAD'   => Env::get('EXP_ALLOW_TEST_DOWNLOAD', false),              // All users could download test data 
    'PLAGIARISM_SCORE'      => floatval(Env::get('EXP_PLAGIARISM_SCORE', 0.4)),         // Plagiarism reducted score ratio

    
    'OJ_SSO'            => Env::get('OJ_SSO', false),               // OJ SSO
    'OJ_SCLIENT_ID'     => Env::get('OJ_SCLIENT_ID', 'nothing'),    // OJ_SCLIENT_ID

];