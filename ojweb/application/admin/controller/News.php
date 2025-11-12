<?php
namespace app\admin\controller;
use think\Controller;
class News extends Adminbase
{
	//***************************************************************//
	//News
	//***************************************************************//
	var $staticPage;
	var $maxTag;
	var $tagLength;
	public function _initialize()
	{
		$this->OJMode();
		$this->AdminInit();
		$this->staticPage 		= config('CsgcpcConst.STATIC_PAGE');
		$this->maxTag 			= config('CsgcpcConst.MAX_TAG');
		$this->tagLength 		= config('CsgcpcConst.TAG_LENGTH');

		$this->assign([
			'staticPage' 	=> $this->staticPage,
			'maxTag'		=> $this->maxTag,
			'tagLengh'		=> $this->tagLength,
		]);
	}
	public function index()
	{
		return $this->fetch();
	}
	public function news_list_ajax()
	{
		$columns = ['news_id', 'user_id', 'category', 'title', 'time', 'defunct'];
		$offset		= intval(input('offset'));
		$limit		= intval(input('limit'));
		$sort		= trim(input('sort'));
		$sort       = validate_item_range($sort, $columns);
		$order		= input('order');
		$search		= trim(input('search/s'));
		
		// 新增筛选参数
		$category_filter = input('category', -1);
		$defunct_filter = input('defunct', -1);

		$map = [
			'news_id' => ['egt', 1000]
		];
		if(strlen($search) > 0)
			$map['news_id|user_id|title|category'] = ['like', "%$search%"];
			
		// 添加category筛选
		if($category_filter != -1) {
			$map['category'] = $category_filter;
		}
		
		// 添加defunct筛选
		if($defunct_filter != -1) {
			$map['defunct'] = $defunct_filter;
		}
		
		$ret = [];
		$ordertype = [];
		if(strlen($sort) > 0)
		{
			$ordertype = [
				$sort => $order
			];
		}
		$News = db('news');
		$newsList = $News
			->field(implode(",", $columns))
			->where($map)
			->order($ordertype)
			->select();
		foreach($newsList as &$news) {
			if(IsAdmin($this->privilegeStr, $news['news_id'])) {
				$news['is_admin'] = true; // 有权限管理该新闻
			}
			else {
				$news['is_admin'] = false;
			}
		}
		$total_map = [];
		if(strlen($search) > 0) {
			$total_map['news_id|user_id|title|category'] = ['like', "%$search%"];
		}
		$total_map['news_id'] = ['egt', 1000];
		$ret['total'] = $News->where($total_map)->count();
		$ret['order'] = $order;
		$ret['rows'] = $newsList;
		
		// 如果是 AJAX 请求，返回 JSON 格式
		if (request()->isAjax()) {
			return json($ret);
		}
		
		return $ret;
	}
	public function news_add()
	{
		$this->assign([
			'edit_mode' => false,
			'copy_mode' => false,
		]);
		return $this->fetch('news_edit');
	}
	public function GetTagList($tags)
	{
		$tagList = explode(";", $tags);
		if(count($tagList) > $this->maxTag)
			$this->error("Too many tags");
		$ret = [];
		foreach($tagList as $tag)
		{
			$singleTag = trim($tag);
			if(strlen($singleTag) > $this->tagLength)
				$this->error('Tag '.$singleTag.' too long');
			if(strlen($singleTag) > 0)
				$ret[] = $singleTag;
		}
		return $ret;
	}
	public function InsertTagList($news_id, $tagList)
	{
		$insertTag = [];
		foreach($tagList as $tag) {
			$insertTag[] = [
				'news_id' => $news_id,
				'tag' => $tag,
			];
		}
		$NewsTag = db('news_tag');
		$NewsTag->where('news_id', $news_id)->delete();
		$NewsTag->insertAll($insertTag);
	}
	public function news_add_ajax()
	{
		$news_add = input('post.');
		$news_add['defunct'] = '1'; //默认隐藏防泄漏
		$news_add['time'] = date('Y-m-d H:i:s');
		$news_add['user_id'] = session('user_id');
		$news_add['modify_time'] = $news_add['time'];
		$news_add['modify_user_id'] = $news_add['user_id'];
		//tag
		$news_add['tags'] = trim($news_add['tags'], "; \0\x0B\r\t\n");
		$tagList = $this->GetTagList($news_add['tags']);
		// 验证分类是否有效（现在在前端配置中定义）
		$validCategories = ['news', 'notification', 'answer', 'cpcinfo'];
		if(!in_array($news_add['category'], $validCategories))
			$this->error("Not a valid category");
		$news_md_add = [
			'content'	=> 	$news_add['content'],
		];
		//插入news表，描述字段为md编译的html
		$news_add['content'] = ParseMarkdown($news_md_add['content']);
		$news_add['attach']	 = $this->AttachFolderCalculation(session('user_id')); // 计算附件文件夹名称，固定后导入导出题目不会有路径变化问题
		$news_id = null;
		unset($news_add['cooperator']);
		if(!($news_id = db('news')->insertGetId($news_add)))
		{
			$this->error('Add news failed, SQL error.');
		}
		// news已插入，下面处理news_md
		$News_md = db('news_md');
		$news_md = $News_md->where('news_id', $news_id)->find();
		$news_md_add['news_id'] = $news_id; //注意news_md表要设置news_id以和news表对应。
		//虽然新插数据基本不会发生news_md已有此news_id的情况，但以防万一news表被删除过并修改过auto_increacement
		if($news_md == null) {
			$News_md->insert($news_md_add);
		}
		else {
			$News_md->update($news_md_add);
		}
		$this->AddPrivilege(session('user_id'), 'news', $news_id);
		
        //处理cooperator
        $cooperator = input('cooperator/s');
        $cooperatorList = explode(",", $cooperator);
        $cooperatorFailList = $this->SaveCooperator($cooperatorList, $news_id);
		$this->InsertTagList($news_id, $tagList);
		$this->success('News successfully added.', '', ['id' => $news_id]);
	}
	public function news_edit()
	{
		$news_id = trim(input('id'));
		if(!IsAdmin($this->privilegeStr, $news_id))
		{
			$this->error('Powerless');
		}
		$news = db('news')->where('news_id', $news_id)->find();
		if($news == null)
		{
			$this->error('No such news.');
		}
		$news_md = db('news_md')->where('news_id', $news_id)->find();
		if($news_md != null)
		{
			$news = array_replace($news, $news_md);
		}
		$cooperator = $this->GetCooperator($news['news_id']);
		$this->assign([
			'news' => $news,
			'cooperator'	=> implode(",", $cooperator),
            'item_priv'     => IsAdmin($this->privilegeStr, $news_id),
		]);
		return $this->fetch();
	}
	public function news_edit_ajax()
	{
		$news_id = trim(input('news_id'));
		if(!IsAdmin($this->privilegeStr, $news_id))
		{
			$this->error('You cannot edit news ' . $news_id);
		}
		$News = db('news');
		$news = $News->where('news_id', $news_id)->find();
		if($news == null)
		{
			$this->error('No such news.');
		}
		$news_update = input('post.');
		unset($news_update['cooperator']);
		if(isset($news_update['tags']))
		{
			//tag
			$news_update['tags'] = trim($news_update['tags'], "; \0\x0B\r\t\n");
			$tagList = $this->GetTagList($news_update['tags']);
		}
		$News_md = db('news_md');
		$news_md = $News_md->where('news_id', $news_id)->find();
		$news_md_update = [
			'news_id'	=>	$news_update['news_id'],
			'content'	=> 	$news_update['content'],
		];
		//更新news_md。因为是新版，所以已有的题目在md表里也可能不存在。
		if($news_md == null)
		{
			$News_md->insert($news_md_update);
		}
		else
		{
			$News_md->update($news_md_update);
		}
		//更新news表，保存md编译的html
		$news_update['content']	= ParseMarkdown($news_update['content']);
		$news_update['modify_time'] = date('Y-m-d H:i:s');
		$news_update['modify_user_id'] = session('user_id');
		if(!$News->update($news_update))
		{
			$this->error('Not updated (datas are the same).');
			return;
		}
		if(isset($tagList))
		{
			$this->InsertTagList($news_id, $tagList);
		}
		//处理cooperator
		$cooperator = input('cooperator/s');
		$cooperatorList = explode(",", $cooperator);
		$cooperatorFailList = $this->SaveCooperator($cooperatorList, $news_id);
		$alert = false;
		if(strlen($cooperatorFailList) > 0)
		{
			$alert = true;
		}
		$this->success('Successfully modified.' . $cooperatorFailList, '', ['alert' => $alert]);
	}
	//***************************************************************//
	// Carousel
	//***************************************************************//
	public function carousel()
	{
		if(!IsAdmin('administrator'))
			$this->error("You cannot modify carousel");
		$ret = SetCarousel();
		$carouselConfig = config('CsgcpcConst.CAROUSEL');
		$this->assign($ret);
		$this->assign('carouselItem', $carouselConfig['carouselItem']);
		return $this->fetch();
	}
	public function carousel_ajax()
	{
		if(!IsAdmin('administrator'))
			$this->error("You cannot modify carousel");
		$ret = SetCarousel();
		$post = input('post.');
		$carousel = [
			'href' => [],
			'src' => [],
			'header' => [],
			'content' => [],
		];
		for($i = 0; $i < 3; $i ++)
		{
			$carousel['href'][] 	= $post['href' . $i];
			$carousel['src'][] 		= $post['src' . $i];
			$carousel['header'][]	= $post['header' . $i];
			$carousel['content'][] 	= $post['content' . $i];
		}
		$ret['news']['content'] = json_encode($carousel);
		$ret['news']['defunct'] = isset($post['defunct']) ? '0' : '1';

		db('news')->update($ret['news']);
		$this->success('Carousel updated');
	}
	//***************************************************************//
	// About Us
	//***************************************************************//
	public function SpecialNewInit($id, $title)
	{
		$News = db('news');
		$news = $News->where('news_id', $id)->find();
		if(!$news)
		{
			$news = [
				'news_id'			=> $id,
				'title'				=> $title,
				'category'			=> 'about',
				'content'			=> '',
				'user_id'			=> session('user_id'),
				'modify_user_id'	=> session('user_id'),
				'time'				=> date('Y-m-d H:i:s'),
				'modify_time'		=> date('Y-m-d H:i:s'),
			];
			$News->insert($news);
		}
		$news_md = db('news_md')->where('news_id', $news['news_id'])->find();
		if($news_md != null)
		{
			$news = array_replace($news, $news_md);
		}
		return $news;
	}
	public function aboutus()
	{
		if(!IsAdmin('administrator'))
			$this->error('You cannot update about us');
//        $title = 'About Us';
        $title = 'About';
		$news = $this->SpecialNewInit($this->staticPage['about_us'], $title);
		$this->assign([
			'news' 			=> $news,
			'special_page'	=> true,
			'title' 			=> $title,
			'aimurl'		=> '/index/about',
			'edit_mode' => false,
			'copy_mode' => false,
		]);
		return $this->fetch('news/news_edit');
	}
	//***************************************************************//
	// OJ F.A.Qs
	//***************************************************************//
	public function oj_faq()
	{
		if(!IsAdmin('administrator'))
			$this->error('You cannot update OJ F.A.Qs');
		$title = 'OJ F.A.Qs';
		$news = $this->SpecialNewInit($this->staticPage['oj_faq'], $title);
		$this->assign([
			'news' 			=> $news,
			'special_page'	=> true,
			'title' 			=> $title,
			'aimurl'		=> '/csgoj/faqs',
			'edit_mode' => false,
			'copy_mode' => false,
		]);
		return $this->fetch('news/news_edit');
	}
	//***************************************************************//
	// Contest Registration F.A.Qs
	//***************************************************************//
	public function cr_faq()
	{
		if(!IsAdmin('administrator'))
			$this->error('You cannot update Contest Registration F.A.Qs');
		$title = 'Contest Registration F.A.Qs';
		$news = $this->SpecialNewInit($this->staticPage['cr_faq'], $title);
		$this->assign([
			'news' 			=> $news,
			'special_page'	=> true,
			'title' 			=> $title,
			'aimurl'		=> '/cr/faqs'
		]);
		return $this->fetch('news/news_edit');
	}
}
