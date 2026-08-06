<?php

class qa_html_theme_layer extends qa_html_theme_base {

	private $userid;
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
			$this->output('<style type="text/css">');
			$this->output('
/* --- Mark Read Indicator (Light Mode) --- */
.qa-q-item-title.qa-q-read {
	position: relative;
	padding-left: 14px;
	border-left: 3px solid #9c16a3;
}
.qa-q-item-title.qa-q-read::before {
	content: "\2713";
	position: absolute;
	left: -22px;
	top: 50%;
	transform: translateY(-50%);
	width: 20px;
	height: 20px;
	background: linear-gradient(135deg, #1246f1, #22c5a7);
	border-radius: 50%;
	color: #fff;
	font-size: 11px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 2px 6px rgba(22,163,74,0.3);
	animation: qa-read-pop 0.3s ease;
}
.qa-q-item-title.qa-q-read a {
	color: #15803d;
}

/* Question View Page - Read Badge */
.qa-read-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	margin-bottom: 10px;
	padding: 4px 12px;
	background: #ecfdf5;
	border: 1px solid #a7f3d0;
	border-radius: 16px;
	font-size: 12px;
	font-weight: 600;
	color: #065f46;
	line-height: 1;
}
.qa-read-badge::before {
	content: "\2713";
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 16px;
	height: 16px;
	background: #16a34a;
	color: #fff;
	border-radius: 50%;
	font-size: 10px;
	font-weight: 700;
}

/* Question View - Green sidebar */
.qa-q-view.qa-q-view-read {
	border-left: 4px solid #16a34a !important;
}

@keyframes qa-read-pop {
	0% { transform: translateY(-50%) scale(0); opacity: 0; }
	70% { transform: translateY(-50%) scale(1.15); }
	100% { transform: translateY(-50%) scale(1); opacity: 1; }
}

/* --- Dark Mode --- */
html[data-theme="dark"] .qa-q-item-title.qa-q-read {
	border-left-color: #4ade80;
}
html[data-theme="dark"] .qa-q-item-title.qa-q-read::before {
	background: linear-gradient(135deg, #22c55e, #4ade80);
	
	box-shadow: 0 2px 8px rgba(74,222,128,0.35);
}
html[data-theme="dark"] .qa-q-item-title.qa-q-read a {
	color: #86efac;
}
html[data-theme="dark"] .qa-read-badge {
	background: #052e16;
	border-color: #166534;
	color: #86efac;
}
html[data-theme="dark"] .qa-read-badge::before {
	background: #4ade80;
	color: #052e16;
}
html[data-theme="dark"] .qa-q-view.qa-q-view-read {
	border-left-color: #4ade80 !important;
}
html[data-theme="dark"] .qa-q-read {
	background-color: transparent !important;
}
');
			// Allow admin CSS customizations to override (light mode only)
			$custom_css = qa_opt('qa_featured_css');
			if (!empty($custom_css)) {
				$this->output('html:not([data-theme="dark"]) ' . $custom_css);
			}
			$this->output('</style>');
		}

	}

	public function q_item_title($q_item)
	{
		if(qa_is_logged_in() && qa_opt("qa_featured_enable_user_reads") &&( ($this->template == 'questions') || ($this->template == 'unanswered') || ($this->template == 'question') || ($this->template == 'activity') || ($this->template === 'tag')  ||  ($this->template === 'question') || ($this->template === 'search')) ){
			
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



	public function q_view($q_view)
	{
		if (!empty($q_view) && qa_is_logged_in() && qa_opt('qa_featured_enable_user_reads') && $this->template == 'question') {
			$postid = $q_view['raw']['postid'];
			$query = "select postid from ^userreads where userid = # and postid = #";
			$result = qa_db_query_sub($query, qa_get_logged_in_userid(), $postid);
			$id = qa_db_read_one_value($result, true);
			if ($id) {
				$q_view['_is_read'] = true;
				$q_view['classes'] = (isset($q_view['classes']) ? $q_view['classes'] . ' ' : '') . 'qa-q-view-read';
			}
		}
		qa_html_theme_base::q_view($q_view);
	}

	public function q_view_main($q_view)
	{
		if (!empty($q_view['_is_read'])) {
			$this->output('<span class="qa-read-badge">Marked as Read</span>');
		}
		qa_html_theme_base::q_view_main($q_view);
	}

	public function q_view_buttons($q_view)
	{
		//For inserting a row in the userread_events table. Reading Analytics are fetching data from this table,
		
		if( qa_get_logged_in_userid() && ($this->template == 'question')){
			qa_db_query_sub(
				'INSERT IGNORE INTO ^userread_events (userid, postid, read_date) 
				 VALUES (#, #, CURRENT_DATE)',
				qa_get_logged_in_userid(),
				$q_view['raw']['postid']
			);
		//error_log(qa_get_logged_in_userid()." is the logged user");

		}
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
						$q_view['form']['buttons']['feature'] = array("tags" => "name='feature-button'",  "popup" => qa_lang_html('featured_lang/feature_pop'), "label" => qa_lang_html('featured_lang/feature')); 
					}
					else{
						$q_view['form']['buttons']['unfeature'] = array("tags" => "name='unfeature-button'", "popup" => qa_lang_html('featured_lang/unfeature_pop'), "label" => qa_lang_html('featured_lang/unfeature')); 
					}
				}
			}

		}
		qa_html_theme_base::q_view_buttons($q_view);
	}

   /* ============================================
       For Navigation in the user account
    ============================================ */	
	public function nav($navtype, $level = null)
	{
		// Only modify when the user profile sub navigation exists
		if (isset($this->content['navigation']['sub']['profile'])) {

			$guest_handle = qa_get_logged_in_handle();
			$user_handle = qa_request_part(1) ?qa_request_part(1): $guest_handle;

			// Access control: show for own profile or admin
			$isMy = ($user_handle === $guest_handle);
			$isAuthorized = (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN);

			if ($isMy || $isAuthorized) {
				// Build User Reads sub-navigation item
				$usernotes_sub_nav = [
					'user_reads' => [
						'label' => qa_lang_html('featured_lang/all_read'),
						'url'   => qa_path_html('read/' . $user_handle, null, qa_opt('site_url')),
						'selected' => (
							qa_request_part(0) === 'mark-read'
						),
					],
				];

				// Insert into sub-navigation after existing items
				qa_array_insert($this->content['navigation']['sub'], null, $usernotes_sub_nav);
			}
		}

		// Continue rendering default navigation
		qa_html_theme_base::nav($navtype, $level);
	}



}

