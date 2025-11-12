<?php

/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/3
 * Time: 20:58
 */
return [
    'STATIC_PAGE'    => [
        // 固定页面的news_id，小于1000
        'about_us'  => 10,        //关于我们
        'oj_faq'    => 20,        //F.A.Qs
        'cr_faq'    => 21,        //报名系统F.A.Qs
        'carousel'  => 30,        //首页三张滚动大图
    ],
    'MAX_TAG'       => 5,    //最多几个tag
    'TAG_LENGTH'    => 32,    //每个tag最长是多长

    'CAROUSEL'      => [
        'carouselItem'  => ['href', 'src', 'header', 'content'],
        'srcDefault'    => [
            '/static/image/carousel_default/carousel0.png',
            '/static/image/carousel_default/carousel1.png',
            '/static/image/carousel_default/carousel2.png',
        ]
    ],
];
