<?php
/**
 * Created by PhpStorm.
 * User: CSGrandeur
 * Date: 2017/3/3
 * Time: 21:15
 */
namespace app\index\controller;
use think\Controller;
use \Globalbasecontroller;
class Homebase extends Globalbasecontroller
{
    var $staticPage;
    var $maxTag;
    var $tagLength;
    var $category;
    var $pagetitle;

    public function _initialize()
    {
        $this->OJMode();
        $this->BaseInit();
    }
    public function MakePageTitle()
    {
        $this->assign('pagetitle', $this->pagetitle = $this->OJ_NAME . ' Home Page');
    }
    public function BaseInit()
    {
        $this->staticPage         = config('CsgcpcConst.STATIC_PAGE');
        $this->maxTag             = config('CsgcpcConst.MAX_TAG');
        $this->tagLength         = config('CsgcpcConst.TAG_LENGTH');
        $this->category            = strtolower($this->request->controller());
        $this->assign([
            'staticPage'     => $this->staticPage,
            'maxTag'        => $this->maxTag,
            'tagLengh'        => $this->tagLength,
            'category'        => $this->category,
        ]);
        $this->MakePageTitle();
    }
    public function index()
    {
        return $this->fetch('public/news_list');
    }
    public function get_news_info()
    {
        $news_id = trim(input('nid'));
        $map = ['news_id' => $news_id];
        if(!IsAdmin('news', $news_id))
            $map['defunct'] = '0';
        $news = db('news')->where($map)->find();
        if($news == null)
        {
            $this->error('No such news.');
        }
        return $news;
    }
    public function category_news_list_ajax()
    {
        $News = db('news');
        $newsList = $News
            ->where([
                'category' => strtolower($this->category),
                'defunct'  => '0'
            ])
            ->order('news_id', 'desc')
            ->field('content', true)
            ->select();
        return $newsList;
    }
    public function detail()
    {
        
        $nid = input('nid/d');
        $News = db('news');
        $map = [
            'news_id'         => $nid,
            'category'    => $this->category
        ];
        if(!IsAdmin('news', $nid))
            $map['defunct'] = '0';
        $news = $News->where($map)->find();
        if(!$news)
            $this->error('No such article of id ' . $nid);
        $this->assign('news', $news);
        $this->assign('pagetitle', $this->pagetitle . "-$nid-" . $news['title']);
        return $this->fetch('public/news_detail');
    }
}