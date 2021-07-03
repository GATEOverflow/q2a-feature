<?php

class qa_html_theme_layer extends qa_html_theme_base {


	function doctype(){
		global $qa_request;
		$request = qa_request_parts();
		$request = $request[0];
		$categoryslugs = qa_request_parts(1);
		qa_html_theme_base::doctype();
		if((strcmp($request,'questions') == 0) || (strcmp($request,'unanswered') == 0)) {
			//$request='questions';
			if (isset($categoryslugs))
				foreach ($categoryslugs as $slug)
					$request.='/'.$slug;
			if(qa_get('sort') === 'featured')
			{
				if($request === "unanswered")
				{
					$this->content['navigation']['sub']['by-answers']['selected'] = false;
				}
				else
				{
					$this->content['navigation']['sub']['recent']['selected'] = false;
				}
			}
			$this->content['navigation']['sub']['featured']= array(
				'label' => qa_lang_html('featured_lang/featured'),
				'url' => qa_path_html($request, array('sort' => 'featured')),
				'selected' => (qa_get('sort') === 'featured')

			);
		}

	}
	public function head_css()
	{
		qa_html_theme_base::head_css();
		if(qa_opt("qa_featured_enable_user_reads")){
			$this->output('<style type="text/css">'.qa_opt('qa_featured_css').' </style>');
		}

	}

	public function q_item_title($q_item)
	{
		if(qa_opt("qa_featured_enable_user_reads") &&( ($this->template == 'questions') || ($this->template == 'unanswered') || ($this->template == 'question') || ($this->template == 'activity'))  ){
			$this->output(
				'<div class="qa-q-item-title');
			if(isset($q_item['raw']['readid']))
				$this->output(' qa-q-read');

			$this->output('">',
				'<a href="'.$q_item['url'].'">'.$q_item['title'].'</a>',
				// add closed note in title
				empty($q_item['closed']['state']) ? '' : ' ['.$q_item['closed']['state'].']',
				'</div>'
			);
		}
		else 
			qa_html_theme_base::q_item_title($q_item);
	}



	public function q_view_buttons($q_view)
	{
		if (($this->template == 'question') && (!empty($q_view['form']))) {
			if(qa_is_logged_in())// && isset($q_view['raw']))
			{
				$postid=$q_view['raw']['postid'];
				$q_view['form']['fields']['postid'] = array("tags" => "name='postid' value='$postid' type='hidden'"); 
				if(qa_opt("qa_featured_enable_user_reads")){
					$query = "select postid from ^userreads where userid = # and postid = #";
					$result = qa_db_query_sub($query, qa_get_logged_in_userid(), $postid);
					$id = qa_db_read_one_value($result, true);
					if(!$id)
						//if(qa_db_postmeta_get($postid, "featured") == null)
					{
						$q_view['form']['buttons']['read'] = array("tags" => "name='read-button'", "popup" => qa_lang_html('featured_lang/read_pop'), "label" => qa_lang_html('featured_lang/read')); 
					}
					else{
						$q_view['form']['buttons']['unread'] = array("tags" => "name='unread-button'", "popup" => qa_lang_html('featured_lang/unread_pop'), "label" => qa_lang_html('featured_lang/unread')); 
					}
				}
				$user_level = qa_get_logged_in_level();
				if($user_level >=  qa_opt('qa_featured_questions_level') )
				{
					require_once QA_INCLUDE_DIR.'db/metas.php';
					if(qa_db_postmeta_get($postid, "featured") == null)
					{
						$q_view['form']['buttons'][] = array("tags" => "name='feature-button'",  "popup" => qa_lang_html('featured_lang/feature_pop'), "label" => qa_lang_html('featured_lang/feature')); 
					}
					else{
						$q_view['form']['buttons'][] = array("tags" => "name='unfeature-button'", "popup" => qa_lang_html('featured_lang/unfeature_pop'), "label" => qa_lang_html('featured_lang/unfeature')); 
					}
				}
			}

		}
		qa_html_theme_base::q_view_buttons($q_view);
	}



}

