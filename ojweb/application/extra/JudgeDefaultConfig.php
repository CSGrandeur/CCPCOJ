<?php

return [
    // 实际配置数据（用于文件读写）
    'config' => [
        // 通用配置
        'common' => [
            'flg_stop_when_not_ac' => true,     // 评测遇到不AC就停止
            'flg_use_max_time' => true,         // 按时间总和考虑时限还是评测最久的一组数据考虑时限
            'max_time_limit' => 10000,          // 最大时间限制(ms)
            'max_memory_limit' => 2048,         // 最大内存限制(MB)
            'max_output_limit' => 256,          // 最大输出限制(MB)
        ],
        
        // C语言配置
        'c' => [
            'cc_std' => '-std=c17',             // C标准
            'cc_opt' => '-O2',                  // C编译优化
        ],
        
        // C++语言配置
        'cpp' => [
            'cpp_std' => '-std=c++17',          // C++标准
            'cpp_opt' => '-O2',                 // C++编译优化
        ],
        
        // Java语言配置
        'java' => [
            'time_bonus_plus' => 2000,          // 时间奖励加法(ms)
            'time_bonus_multiply' => 1,         // 时间奖励乘法倍数
            'memory_bonus' => 512,              // 内存奖励(MB)
            'xms' => 1024,                      // Java启动参数 -Xms
            'xmx' => 1024,                      // Java启动参数 -Xmx
            'xss' => 64,                        // Java启动参数 -Xss
            'java_opt' => '-server',            // Java优化选项
        ],
        
        // Python语言配置
        'python' => [
            'time_bonus_plus' => 2000,          // 时间奖励加法(ms)
            'time_bonus_multiply' => 1,         // 时间奖励乘法倍数
            'memory_bonus' => 512,              // 内存奖励(MB)
        ],
    ],
    
    // 配置定义（用于前端渲染）
    'definitions' => [
        // 通用配置定义
        'common' => [
            'title' => ['cn' => '通用配置', 'en' => 'Common Settings'],
            'fields' => [
                'flg_stop_when_not_ac' => [
                    'type' => 'switch',
                    'label' => ['cn' => '遇到不AC就停止', 'en' => 'Stop when not AC'],
                    'description' => ['cn' => '评测过程中遇到非AC结果时立即停止', 'en' => 'Stop immediately when non-AC result is encountered'],
                ],
                'flg_use_max_time' => [
                    'type' => 'switch',
                    'label' => ['cn' => '使用最大时间', 'en' => 'Use max time'],
                    'description' => ['cn' => '开-以评测最久的一组数据考虑时限 / 关-按时间总和考虑时限', 'en' => 'Open-Consider time limit by longest test case / Close-Consider time limit by total time'],
                ],
                'max_time_limit' => [
                    'type' => 'number',
                    'label' => ['cn' => '最大时间限制', 'en' => 'Max Time Limit'],
                    'description' => ['cn' => '最大时间限制(毫秒)', 'en' => 'Maximum time limit (milliseconds)'],
                    'unit' => ['cn' => 'ms', 'en' => 'ms'],
                    'min' => 1000,
                    'max' => 60000,
                    'step' => 100,
                ],
                'max_memory_limit' => [
                    'type' => 'number',
                    'label' => ['cn' => '最大内存限制', 'en' => 'Max Memory Limit'],
                    'description' => ['cn' => '最大内存限制(兆字节)', 'en' => 'Maximum memory limit (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 64,
                    'max' => 8192,
                    'step' => 64,
                ],
                'max_output_limit' => [
                    'type' => 'number',
                    'label' => ['cn' => '最大输出限制', 'en' => 'Max Output Limit'],
                    'description' => ['cn' => '最大输出限制(兆字节)', 'en' => 'Maximum output limit (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 1,
                    'max' => 2048,
                    'step' => 1,
                ],
            ],
        ],
        
        // C语言配置定义
        'c' => [
            'title' => ['cn' => 'C语言配置', 'en' => 'C Language Settings'],
            'fields' => [
                'cc_std' => [
                    'type' => 'select',
                    'label' => ['cn' => 'C标准', 'en' => 'C Standard'],
                    'description' => ['cn' => 'C语言标准版本', 'en' => 'C language standard version'],
                    'options' => [
                        ['value' => '-std=c89', 'label' => ['cn' => 'C89', 'en' => 'C89']],
                        ['value' => '-std=c99', 'label' => ['cn' => 'C99', 'en' => 'C99']],
                        ['value' => '-std=c11', 'label' => ['cn' => 'C11', 'en' => 'C11']],
                        ['value' => '-std=c17', 'label' => ['cn' => 'C17', 'en' => 'C17']],
                        ['value' => '-std=c2x', 'label' => ['cn' => 'C2x', 'en' => 'C2x']],
                    ],
                ],
                'cc_opt' => [
                    'type' => 'select',
                    'label' => ['cn' => '编译优化', 'en' => 'Compile Optimization'],
                    'description' => ['cn' => 'C语言编译优化级别', 'en' => 'C language compile optimization level'],
                    'options' => [
                        ['value' => '-O0', 'label' => ['cn' => 'O0', 'en' => 'O0']],
                        ['value' => '-O1', 'label' => ['cn' => 'O1', 'en' => 'O1']],
                        ['value' => '-O2', 'label' => ['cn' => 'O2', 'en' => 'O2']],
                        ['value' => '-O3', 'label' => ['cn' => 'O3', 'en' => 'O3']],
                        ['value' => '-Os', 'label' => ['cn' => 'Os', 'en' => 'Os']],
                        ['value' => '-Ofast', 'label' => ['cn' => 'Ofast', 'en' => 'Ofast']],
                    ],
                ],
            ],
        ],
        
        // C++语言配置定义
        'cpp' => [
            'title' => ['cn' => 'C++语言配置', 'en' => 'C++ Language Settings'],
            'fields' => [
                'cpp_std' => [
                    'type' => 'select',
                    'label' => ['cn' => 'C++标准', 'en' => 'C++ Standard'],
                    'description' => ['cn' => 'C++语言标准版本', 'en' => 'C++ language standard version'],
                    'options' => [
                        ['value' => '-std=c++98', 'label' => ['cn' => 'C++98', 'en' => 'C++98']],
                        ['value' => '-std=c++03', 'label' => ['cn' => 'C++03', 'en' => 'C++03']],
                        ['value' => '-std=c++11', 'label' => ['cn' => 'C++11', 'en' => 'C++11']],
                        ['value' => '-std=c++14', 'label' => ['cn' => 'C++14', 'en' => 'C++14']],
                        ['value' => '-std=c++17', 'label' => ['cn' => 'C++17', 'en' => 'C++17']],
                        ['value' => '-std=c++20', 'label' => ['cn' => 'C++20', 'en' => 'C++20']],
                        ['value' => '-std=c++23', 'label' => ['cn' => 'C++23', 'en' => 'C++23']],
                    ],
                ],
                'cpp_opt' => [
                    'type' => 'select',
                    'label' => ['cn' => '编译优化', 'en' => 'Compile Optimization'],
                    'description' => ['cn' => 'C++语言编译优化级别', 'en' => 'C++ language compile optimization level'],
                    'options' => [
                        ['value' => '-O0', 'label' => ['cn' => 'O0', 'en' => 'O0']],
                        ['value' => '-O1', 'label' => ['cn' => 'O1', 'en' => 'O1']],
                        ['value' => '-O2', 'label' => ['cn' => 'O2', 'en' => 'O2']],
                        ['value' => '-O3', 'label' => ['cn' => 'O3', 'en' => 'O3']],
                        ['value' => '-Os', 'label' => ['cn' => 'Os', 'en' => 'Os']],
                        ['value' => '-Ofast', 'label' => ['cn' => 'Ofast', 'en' => 'Ofast']],
                    ],
                ],
            ],
        ],
        
        // Java语言配置定义
        'java' => [
            'title' => ['cn' => 'Java语言配置', 'en' => 'Java Language Settings'],
            'fields' => [
                'time_bonus_plus' => [
                    'type' => 'number',
                    'label' => ['cn' => '时间奖励加', 'en' => 'Time Bonus Plus'],
                    'description' => ['cn' => 'Java语言时间奖励加法(毫秒)，先乘后加', 'en' => 'Java language time bonus plus (milliseconds), multiply first then add'],
                    'unit' => ['cn' => 'ms', 'en' => 'ms'],
                    'min' => 0,
                    'max' => 10000,
                    'step' => 100,
                ],
                'time_bonus_multiply' => [
                    'type' => 'number',
                    'label' => ['cn' => '时间奖励乘', 'en' => 'Time Bonus Multiply'],
                    'description' => ['cn' => 'Java语言时间奖励乘法倍数，优先计算', 'en' => 'Java language time bonus multiply factor, calculated first'],
                    'min' => 1,
                    'max' => 10,
                    'step' => 0.1,
                ],
                'memory_bonus' => [
                    'type' => 'number',
                    'label' => ['cn' => '内存奖励', 'en' => 'Memory Bonus'],
                    'description' => ['cn' => 'Java语言内存奖励(兆字节)', 'en' => 'Java language memory bonus (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 0,
                    'max' => 2048,
                    'step' => 64,
                ],
                'xms' => [
                    'type' => 'number',
                    'label' => ['cn' => 'Xms', 'en' => 'Xms'],
                    'description' => ['cn' => 'Java启动参数 -Xms(兆字节)', 'en' => 'Java startup parameter -Xms (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 64,
                    'max' => 8192,
                    'step' => 64,
                ],
                'xmx' => [
                    'type' => 'number',
                    'label' => ['cn' => 'Xmx', 'en' => 'Xmx'],
                    'description' => ['cn' => 'Java启动参数 -Xmx(兆字节)', 'en' => 'Java startup parameter -Xmx (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 64,
                    'max' => 8192,
                    'step' => 64,
                ],
                'xss' => [
                    'type' => 'number',
                    'label' => ['cn' => 'Xss', 'en' => 'Xss'],
                    'description' => ['cn' => 'Java启动参数 -Xss(兆字节)', 'en' => 'Java startup parameter -Xss (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 1,
                    'max' => 1024,
                    'step' => 1,
                ],
            ],
        ],
        
        // Python语言配置定义
        'python' => [
            'title' => ['cn' => 'Python语言配置', 'en' => 'Python Language Settings'],
            'fields' => [
                'time_bonus_plus' => [
                    'type' => 'number',
                    'label' => ['cn' => '时间奖励加', 'en' => 'Time Bonus Plus'],
                    'description' => ['cn' => 'Python语言时间奖励加法(毫秒)，先乘后加', 'en' => 'Python language time bonus plus (milliseconds), multiply first then add'],
                    'unit' => ['cn' => 'ms', 'en' => 'ms'],
                    'min' => 0,
                    'max' => 10000,
                    'step' => 100,
                ],
                'time_bonus_multiply' => [
                    'type' => 'number',
                    'label' => ['cn' => '时间奖励乘', 'en' => 'Time Bonus Multiply'],
                    'description' => ['cn' => 'Python语言时间奖励乘法倍数，优先计算', 'en' => 'Python language time bonus multiply factor, calculated first'],
                    'min' => 1,
                    'max' => 10,
                    'step' => 0.1,
                ],
                'memory_bonus' => [
                    'type' => 'number',
                    'label' => ['cn' => '内存奖励', 'en' => 'Memory Bonus'],
                    'description' => ['cn' => 'Python语言内存奖励(兆字节)', 'en' => 'Python language memory bonus (MB)'],
                    'unit' => ['cn' => 'MB', 'en' => 'MB'],
                    'min' => 0,
                    'max' => 2048,
                    'step' => 64,
                ],
            ],
        ],
    ],
];
