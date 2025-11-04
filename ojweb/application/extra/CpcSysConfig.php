<?php

return [
    'SCHOOL_RANK_TEAMNUM' => 4, //计算学校排名的队伍个数
    'PRINT_STATUS'    =>[
        0    =>    'Waiting',
        1    =>    'Printed',
        2    =>    'Denied',
    ],
    'PRINT_STATUS_HTML'    =>[
        0    =>    ['info', 'Waiting'],
        1    =>    ['success', 'Printed'],
        2    =>    ['danger', 'Denied'],
    ],
    // userinfo_rule、userinfo_msg用于验证用户注册信息的预定义规则
    'teaminfo_rule'    => [
        'team_id'    => ['require', 'min:3', 'max:30', '/^[a-zA-Z0-9_]+$/'],
        'name'         => 'max:64',
        'tmember'     => 'max:100',
        'school'     => 'max:64',
        'coach'     => 'max:32',
        'password'     => 'min:6|max:250',
        'fixed_ip'     => ['min:8', 'max:16', '/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/'],
    ],
    'teaminfo_msg' => [
        'team_id.require'             => 'User ID needed.',
        'team_id.min'                 => 'User ID should have more than 5 characters.',
        'team_id.max'                 => 'User ID should not exceed 30 characters.',
        'team_id./^[a-zA-Z0-9_]+$/' => 'Only number, letters and underlines are allowed for User ID.',
        'name.max'                    => 'Team name should not exceed 64 characters.',
        'tmember.max'                    => 'Members should not exceed 100 characters.',
        'school.max'                => 'School name should not exceed 64 characters.',
        'coach.max'                => 'Coach name should not exceed 32 characters.',
        'password.min'                => 'Password should have more than 6 characters.',
        'password.max'                => 'Password should have less than 64 characters.',
        'fixed_ip.min'                => 'Fixed IP should have more than 8 characters.',
        'fixed_ip.max'                => 'Fixed IP should have less than 16 characters.',
        'fixed_ip./^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/' => 'Fixed IP format is invalid.'
    ],
    'userinfo_rule'	=> [
		'user_id'	=> ['require', 'min:5', 'max:20', '/^[a-zA-Z0-9_]+$/'],
		'nick' 		=> 'max:32',
		'email' 	=> 'max:100',
		'school' 	=> 'max:64',
		'password' 	=> 'min:6|max:64'
	],
	'userinfo_msg' => [
		'user_id.require' 			=> 'User ID needed.',
		'user_id.min' 				=> 'User ID should have more than 5 characters.',
		'user_id.max' 				=> 'User ID should not exceed 20 characters.',
		'user_id./^[a-zA-Z0-9_]+$/' => 'Only number, letters and underlines are allowed for User ID.',
		'nick.max'					=> 'Team name should not exceed 30 characters.',
		'email.max'					=> 'Members should not exceed 100 characters.',
		'school.max'				=> 'School name should not exceed 64 characters.',
		'password.min'				=> 'Password should have more than 6 characters.',
		'password.max'				=> 'Password should have less than 64 characters.'
	],
];
