<?php
include RQ_CORE.'/include/tag.php';
include RQ_CORE.'/library/func.convert.php';
$searchfield='tag,keywords,title,excerpt';
$searchnum=0;
$page=isset($_GET['page'])?$_GET['page']:1;
$tagdb=array();
if(RQ_POST)
{
	if($action=='modredirect'||$action=='addredirect')
	{
		$old=isset($_POST['old'])?$_POST['old']:'';
		$new=isset($_POST['new'])?$_POST['new']:'';
		$status=isset($_POST['status'])?$_POST['status']:'1';
		if(!$old)  redirect('原网址不得为空', $admin_url.'?file=seo&action=addredirect');
		if(!$new)  redirect('转向地址不得为空', $admin_url.'?file=seo&action=addredirect');
	}
	switch($action)
	{
		case 'modtag':
			//修改Tag
			$newitem = $_POST['tag'];
			$olditem = $_POST['oldtag'];
			$result  = checktag($newitem);
			if($result)	{
				redirect($result);
			}
			$oldtagdata=gettagids($olditem);
			$aquery=$DB->query("Select aid,tag from ".DB_PREFIX."article where aid in ($oldtagdata)");
			while($data=$DB->fetch_array($aquery))
			{
				$articletag=$data['tag'];
				$tagarr=explode(',',$articletag);
				if($tagarr){
					foreach($tagarr as $k=>$v)
					{
					if($v==$olditem) unset($tagarr[$k]);
					if($v==$newitem) unset($tagarr[$k]);
					}
					$tagarr[]=$newitem;
					$aid=$data['aid'];
					$newarticletag=implode(',',$tagarr);
					$DB->query("update ".DB_PREFIX."article set tag='$newarticletag' where aid=$aid");
				}
			}
			
			$aidarr=explode(',',$oldtagdata);
			if($aidarr)
			{
				foreach($aidarr as $ar)
				{
					modtag($olditem,$newitem,$ar);
				}
				redirect('修改Tags成功', $admin_url.'?file=seo&action=taglist');
			}
			else
			{
				redirect('没有找到原tasg,请检查', $admin_url.'?file=seo&action=taglist');
			}
			break;
		case 'deltag':
			//批量删除Tag
			if (!isset($_POST['tag'])) {
				redirect('未选择任何Tags',$admin_url.'?file=seo&action=taglist');
			}
			$tags=is_array($_POST['tag'])?$_POST['tag']:array($_POST['tag']);
			foreach ($tags as $tag)
			{
				removealltag($tag);
			}
			redirect('成功删除所选Tags', $admin_url.'?file=seo&action=taglist');
			break;
		case 'delredirect':
			//批量删除Tag
			if (!isset($_POST['rid'])) {
				redirect('未选择任何跳转网址',$admin_url.'?file=seo&action=taglist');
			}
			$vids=implode_ids($_POST['rid']);
			$DB->query('delete from '.DB_PREFIX."redirect where  rid in ($vids)");
			redirect_recache();
			redirect('成功删除所选跳转网址', $admin_url.'?file=seo&action=redirect');
			break;
		case 'modredirect':
			//修改
			if (!isset($_POST['rid'])) {
				redirect('未指定修改的网址',$admin_url.'?file=seo&action=redirect');
			}
			$rid=intval($_POST['rid']);
			$search=$DB->fetch_first('select * from '.DB_PREFIX."redirect where old='$old' and rid!=$rid");
			if($search) redirect('原地址不能同时转向多个地址', $admin_url.'?file=seo&action=addredirect');
			$DB->query("update ".DB_PREFIX."redirect set `old`='$old',`new`='$new',`status`='$status' where rid=$rid");
			redirect_recache();
			redirect('成功修改网址跳转',$admin_url.'?file=seo&action=redirect');
			break;
		case 'addredirect';
			$search=$DB->fetch_first('select * from '.DB_PREFIX."redirect where old='$old'");
			if($search) redirect('该原转向地址已经存在了，请检查', $admin_url.'?file=seo&action=addredirect');
			$DB->query('insert into `'.DB_PREFIX."redirect` (`old`,`new`,`status`) values ('$old','$new','$status')");
			redirect_recache();
			redirect('成功添加网址跳转',$admin_url.'?file=seo&action=redirect');
		default:
			redirect('未定义操作', $admin_url.'?file=seo');
	}
}

//下边为GET方法
if(empty($action)) $action='taglist';
//标签列表
if ($action=='taglist') {
	if($page) {
		$start_limit = ($page - 1) * 30;
	} else {
		$start_limit = 0;
		$page = 1;
	}
	$query_count = $DB->fetch_first("SELECT count(*) FROM ".DB_PREFIX."tag");
	$total=$query_count['count(*)'];
	//$multipage = multi(100, $shownum, $page, 'tag.php');
	$query = $DB->query("SELECT * FROM ".DB_PREFIX."tag ORDER BY tid DESC LIMIT $start_limit,30");
	while ($tag = $DB->fetch_array($query)) {
		$tag['url'] = urlencode($tag['tag']);
		$tag['item'] = htmlspecialchars($tag['tag']);
		$usenum=explode(',',$tag['aids']);
		$tag['usenum']=count($usenum);
		$tagdb[] = $tag;
	}
	$multipage = multi($total, 30, $page, $admin_url.'?file=seo&action=taglist');
	unset($tag);
	$DB->free_result($query);
}//list
else if($action=='redirect')
{
	if($page) {
		$start_limit = ($page - 1) * 30;
	} else {
		$start_limit = 0;
		$page = 1;
	}
	$numsql = "LIMIT $start_limit, 30";
	$coutarr=$DB->fetch_first('select count(*) from '.DB_PREFIX."redirect");
	$total=$coutarr['count(*)'];
	$multipage = multi($total, 30, $page, $admin_url.'?file=tag&action=redirect');
	$rs = $DB->query("SELECT * FROM ".DB_PREFIX."redirect $numsql");
	$redirectdb = array();
    while ($tag = $DB->fetch_array($rs)) {
		$tag['status']=$tag['status']==1?301:302;
		$redirectdb[] = $tag;
	}
	unset($tag);
	$DB->free_result($rs);
}
//修改标签
else if($action == 'modtag') {
	$tag = $_GET['tag'];
	$aids=gettagids($tag);
	if ($aids) {
		$aidarr=explode(',',$aids);
		$query  = $DB->query("SELECT aid, title FROM ".DB_PREFIX."article WHERE aid IN ($aids)");
		$articledb = array();
		while ($article = $DB->fetch_array($query)) {
			$articledb[] = $article;
		}
		$usenum=count($aidarr);
		unset($article);
		$DB->free_result($query);
	}
}else if($action == 'modredirect') {
	$rid = $_GET['rid'];
	$redirectdb=$DB->fetch_first("SELECT * FROM `".DB_PREFIX."redirect` WHERE and rid=$rid");
	if(!$redirectdb) redirect('不存在的转向网址记录', $admin_url.'?file=seo');
	$selected301=$redirectdb['status']=='1'?'selected':'';
	$selected302=empty($selected301)?'selected':'';
}else if($action=='addredirect')
{
	$selected302=$selected301=$redirectdb['old']=$redirectdb['new']='';
}
else if($action=='tagrebuilt')
{
	if(isset($_GET['tagindex']))
	{
		$tagindex=intval($_GET['tagindex']);
		rebuildtag($tagindex,'?file=seo&action=tagrebuilt');
	}
	else
	{
		redirect('Tag更新全部完成', '?file=seo');
	}
}