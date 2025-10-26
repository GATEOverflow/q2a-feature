<?php
function qa_db_posts_basic_selectspec($voteuserid=null, $full=false, $user=true)
{
	global $qa_template;
	$res = null;
	if(($qa_template ===  'questions' || $qa_template ===  'unanswered') && (qa_get('sort') == 'featured') )
	{
		$res = qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
		$res['source'] .= " join ^postmetas gfeat on ^posts.postid = gfeat.postid and gfeat.title like 'featured'";
	}
	if(qa_is_logged_in() && qa_opt("qa_featured_enable_user_reads") && (($qa_template ===  'questions') || ($qa_template ===  'unanswered') ||  ($qa_template === 'activity')|| ($qa_template ===  'question') || ($qa_template === 'tag') || ($qa_template === 'search')))
	{
			if(!$res){
				$res = qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
			}
			$userid = qa_get_logged_in_userid();
			$res['columns'][] = "ureads.postid as readid";
			$res['source'] .= " left join ^userreads ureads on ^posts.postid = ureads.postid and ureads.userid = $userid";
	}
	if($res)
		return $res;
	else
		return  qa_db_posts_basic_selectspec_base($voteuserid, $full, $user);
}
function qa_q_list_page_content($questions, $pagesize, $start, $count, $sometitle, $nonetitle,
		$navcategories, $categoryid, $categoryqcount, $categorypathprefix, $feedpathprefix, $suggest,
		$pagelinkparams=null, $categoryparams=null, $dummy=null)
{
	$request = qa_request_parts();
	$request = $request[0];
	if(($request ===  'questions' || $request ===  'unanswered') && (qa_get('sort') == 'featured') )
	{
		$pagelinkparams= array("sort" => "featured");
		$categorytitlehtml = qa_html($navcategories[$categoryid]['title']);		 
		$sometitle = $categoryid != null ? qa_lang_html_sub('featured_lang/featured_qs_in_x', $categorytitlehtml) : qa_lang_html('featured_lang/featured_qs_title');
		if($request === 'questions')
		{
			$nonetitle = $categoryid != null ? qa_lang_html_sub('featured_lang/nofeatured_qs_in_x', $categorytitlehtml) : qa_lang_html('featured_lang/nofeatured_qs_title');
		}
		else
		{
			$nonetitle = $categoryid != null ? qa_lang_html_sub('featured_lang/nofeatured_qs_un_in_x', $categorytitlehtml) : qa_lang_html('featured_lang/nofeatured_qs_un_title');
		}
		$feedpathprefix =  null;
			$list = "featured_qcount";
			$listc = "fcount";
		if($request == "unanswered")
		{
			$list.= "_un";
			$listc .= "_in";

		}
		if(!$categoryid){
			$count=qa_opt($list);
		}
		else{
			$count = qa_db_categorymeta_get($categoryid, $listc);			
		}
	}

	return qa_q_list_page_content_base($questions, $pagesize, $start, $count, $sometitle, $nonetitle,
			$navcategories, $categoryid, $categoryqcount, $categorypathprefix, $feedpathprefix, $suggest,
			$pagelinkparams, $categoryparams, $dummy);
}
function category_path_fqcount_update($postid)
{
	$pathq = "select categoryid, catidpath1, catidpath2, catidpath3 from ^posts where postid = #";
	$result = qa_db_query_sub($pathq, $postid);
	$path = qa_db_read_one_assoc($result, true);
	if($path){
	ifcategory_fqcount_update($path['categoryid']); // requires QA_CATEGORY_DEPTH=4
	ifcategory_fqcount_update($path['catidpath1']);
	ifcategory_fqcount_update($path['catidpath2']);
	ifcategory_fqcount_update($path['catidpath3']);
	}
}

function updatefeaturedcount($postid)
{
	$query = qa_db_query_sub("select count(*) from ^postmetas where title like 'featured'");
	$count = qa_db_read_one_value($query);
	qa_opt('featured_qcount', $count);
	category_path_fqcount_update($postid);
	$query = qa_db_query_sub("select count(*) from ^postmetas a, ^posts b where a.postid=b.postid and b.acount = 0 and a.title like 'featured'");
	$count = qa_db_read_one_value($query);
	qa_opt('featured_qcount_un', $count);
}



function ifcategory_fqcount_update($categoryid)
{
	if (isset($categoryid)) {
		// This seemed like the most sensible approach which avoids explicitly calculating the category's depth in the hierarchy
		$filter = " and postid in (select postid from ^postmetas where title like 'featured')";
		$query = qa_db_query_sub(
				"select GREATEST( (SELECT COUNT(*) FROM ^posts WHERE categoryid=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath1=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath2=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath3=# AND type='Q'".$filter.") ) ",
				$categoryid, $categoryid, $categoryid, $categoryid
				); // requires QA_CATEGORY_DEPTH=4
		$count = qa_db_read_one_value($query);

		qa_db_categorymeta_set($categoryid, 'fcount', $count);
		$filter .=" and acount = 0";
		$query = qa_db_query_sub(
				"select GREATEST( (SELECT COUNT(*) FROM ^posts WHERE categoryid=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath1=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath2=# AND type='Q'".$filter."), (SELECT COUNT(*) FROM ^posts WHERE catidpath3=# AND type='Q'".$filter.") ) ",
				$categoryid, $categoryid, $categoryid, $categoryid
				); // requires QA_CATEGORY_DEPTH=4
		$count = qa_db_read_one_value($query);

		qa_db_categorymeta_set($categoryid, 'fcount_un', $count);
	}
}






function qa_check_page_clicks()
{
	global $qa_page_error_html;
	global  $qa_request;
	require_once QA_INCLUDE_DIR."db/metas.php";
	if ( qa_is_http_post() ) {
		$postid = @$_POST['postid'];	
		if(qa_get_logged_in_level()>=  qa_opt('qa_featured_questions_level'))
		{
			if(isset($_POST['feature-button'])  )
			{
				qa_db_postmeta_set($postid, "featured", "1");
				updatefeaturedcount($postid);
				qa_redirect( qa_request(), $_GET );
			}
			if(isset($_POST['unfeature-button'])  )
			{
				qa_db_postmeta_clear($postid, "featured");
				updatefeaturedcount($postid);
				qa_redirect( qa_request(), $_GET );
			}
		}
		if(qa_opt('qa_featured_enable_user_reads') && qa_is_logged_in())
		{
			if(isset($_POST['read-button'])  )
			{
				//$postid = $_POST['read-button'];	
				$query = "insert into ^userreads(userid, postid, marked_date) values (#,#,CURDATE())";
				qa_db_query_sub($query, qa_get_logged_in_userid(), $postid);
				qa_redirect( qa_request(), $_GET );
			}
			if(isset($_POST['unread-button'])  )
			{
				//$postid = $_POST['unread-button'];	
				$query = "delete from ^userreads where userid = # and postid = #";
				qa_db_query_sub($query, qa_get_logged_in_userid(), $postid);
				qa_redirect( qa_request(), $_GET );
			}
		}
	}

	qa_check_page_clicks_base();
}


?>