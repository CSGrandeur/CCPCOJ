<?php
namespace app\index\controller;
use think\Controller;
class Index extends Homebase
{
    public function index()
    {
        $ncarousel = SetCarousel();
        $showCarousel = $ncarousel['news']['defunct'] == '0';

        $this->assign([
            'carousel'             => $ncarousel['carousel'],
            'showCarousel'        => $showCarousel,
        ]);
        $this->assign('now', time());
        return $this->fetch();
    }
    public function news_detail()
    {
        $news = $this->get_news_info();
        $this->assign(['news'=>$news]);
        return $this->fetch();
    }
    public function news_list()
    {
        return $this->fetch();
    }
    public function news_list_ajax()
    {
        $data     = [];
        $limit    = input('limit', 10);
        $offset    = input('offset', 0);
        $sort    = input('sort', 'news_id');
        $sort = validate_item_range($sort, ['news_id']);
        $order    = input('order', 'desc');
        $search    = input('search', '');

        $News = db('news');

        $map = [
            'title' => ['like', "%$search%"],
            'user_id' => ['like', "%$search%"],
            'news_id' => $search
        ];
        $list = $News
            // >1000 to make 1000 as faqs.
            ->where(['defunct' => '0', 'news_id' => ['gt', 1000]])
            ->where(function($query) use ($map) {
                $query->whereOr($map);
            })
            ->order([$sort=>$order])
            ->limit("$offset,$limit")
            ->select();
        for($i = count($list) - 1; $i >= 0; $i --)
        {
            $list[$i]['title'] = "<a href='/".request()->module()."/index/news_detail?id=".$list[$i]["news_id"]."' target='_blank'>".$list[$i]["title"]."</a>";
        }
        $data["total"] = $News->where('defunct', '0')->count();
        $data['recordsFiltered'] = count($list);
        $data['order'] = $order;
        $data["rows"] = $list;
        return $data;
    }
    public function test()
    {
        return $this->fetch();
    }
}
