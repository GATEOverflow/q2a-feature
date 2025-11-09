<?php
class qa_feature_admin {

	function option_default($option) {

		switch($option) {
			case 'qa_featured_questions_level': 
				return QA_USER_LEVEL_MODERATOR;
			case 'qa_featured_css': 
				return '.qa-q-read { background-color: palegreen;}';
			 case 'leaderboard_min_reads':
				return 5;
			case 'leaderboard_max_users':
				return 20;
			case 'leaderboard_widget_count':
                return 3; // default top 3
			case 'user_read_allowed_categories':
				return '';
			default:
				return null;				
		}

	}
	function init_queries($tableslc) {
		require_once QA_INCLUDE_DIR."db/selects.php";
		$queries = array();
		if(qa_opt('qa_featured_enable_user_reads'))
		{
			$tablename=qa_db_add_table_prefix('userreads');
			$usertablename=qa_db_add_table_prefix('users');
            if(!in_array($tablename, $tableslc)) {
                $queries[] = "create table if not exists $tablename
				 (
				  `userid` int(10) unsigned NOT NULL,
				  `postid` int(10) unsigned NOT NULL,
				  PRIMARY KEY (`userid`,`postid`),
				  KEY `entitytype` (`postid`),
				   FOREIGN KEY (`userid`) REFERENCES `$usertablename` (`userid`) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8";
			}
			/* $sqlCols = 'SHOW COLUMNS FROM '.$tablename;
			$fields = qa_db_read_all_values(qa_db_query_sub($sqlCols));
			$newcolumns = array("marked_date");
			foreach ($newcolumns as $column){
				if(!in_array($column, $fields)) {
					$queries[] = 'ALTER TABLE '.$tablename.' ADD '.$column.' DATE NOT NULL DEFAULT (CURRENT_DATE);';
				}
			}
			
			//For giving points to the top users of the day based on the number of marks
			$tablename = qa_db_add_table_prefix('userpoints');
			$sqlCols = 'SHOW COLUMNS FROM '.$tablename;
			$fields = qa_db_read_all_values(qa_db_query_sub($sqlCols));
			$newcolumns = array("mark_read_points");
			foreach ($newcolumns as $column){
				if(!in_array($column, $fields)) {
					$queries [] =  'ALTER TABLE '.$tablename.' ADD '.$column. ' MEDIUMINT(9)  NOT NULL DEFAULT 0;';
				}
			} */
			
			//Log every view
			$tablename2=qa_db_add_table_prefix('userread_events');
            if(!in_array($tablename2, $tableslc)) {
                $queries[] = "CREATE TABLE if not exists $tablename2(
					id BIGINT AUTO_INCREMENT PRIMARY KEY,
					userid INT NOT NULL,
					postid INT NOT NULL,
					read_date DATE NOT NULL,
					created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					UNIQUE KEY uq_user_post_day (userid, postid, read_date),
					KEY idx_user_date (userid, read_date),
					KEY idx_post_date (postid, read_date)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8";
			}
			//created column is not useful as of now.
		}		
		return $queries;
	} 

	function allow_template($template)
	{
		return ($template!='admin');
	}       

	function admin_form(&$qa_content)
	{                       

		// Process form input

		$ok = null;

		if (qa_clicked('qa_featured_questions_save')) {
			qa_opt('qa_featured_questions_level',qa_post_text('qa_featured_questions_level'));
			qa_opt('qa_featured_enable_user_reads',(bool)qa_post_text('qa_featured_enable_user_reads'));
			qa_opt('qa_featured_css',qa_post_text('qa_featured_css'));
			qa_opt('leaderboard_min_reads', (int)qa_post_text('leaderboard_min_reads'));
			qa_opt('leaderboard_max_users', (int)qa_post_text('leaderboard_max_users'));
			qa_opt('leaderboard_widget_count', (int)qa_post_text('leaderboard_widget_count'));
			$allowed_cats = qa_post_array('allowed_cats'); // safely get array of selected checkboxes
			qa_opt('user_read_allowed_categories', implode(',', (array)$allowed_cats));
			$ok = qa_lang('admin/options_saved');
		}
		$showoptions = array(
				QA_USER_LEVEL_EXPERT => "Experts",
				QA_USER_LEVEL_EDITOR => "Editors",
				QA_USER_LEVEL_MODERATOR =>"Moderators",
				QA_USER_LEVEL_ADMIN =>  "Admins",
				QA_USER_LEVEL_SUPER =>  "Super Admins",
				);

		// --- Fetch all categories that do not have child categories ---
        $categories = qa_db_read_all_assoc(
			qa_db_query_sub(
				'SELECT c.categoryid, c.title
				 FROM ^categories AS c
				 WHERE NOT EXISTS (
					 SELECT 1 FROM ^categories AS sub
					 WHERE sub.parentid = c.categoryid
				 )
				 ORDER BY c.title'
			)
		);


        // --- Current saved categories ---
        $selected = explode(',', qa_opt('user_read_allowed_categories'));
        $selected = array_filter($selected);

        // --- Build checkbox list ---
        $checkboxes = '';
        foreach ($categories as $cat) {
            $checked = in_array($cat['categoryid'], $selected) ? 'checked' : '';
            $checkboxes .=
                '<label style="display:block;margin:2px 0;">' .
                '<input type="checkbox" name="allowed_cats[]" value="' . (int)$cat['categoryid'] . '" ' . $checked . '>' .
                ' ' . qa_html($cat['title']) .
                '</label>';
        }

		// Create the form for display

		$fields = array();
		$fields[] = array(
				'label' => qa_lang_html('featured_lang/min_featuring'),
				'tags' => 'name="qa_featured_questions_level"',
				'value' => @$showoptions[qa_opt('qa_featured_questions_level')],
				'type' => 'select',
				'options' => $showoptions,
				);
		$fields[] = array(
				'label' => qa_lang_html('featured_lang/enable_read'),
				'tags' => 'name="qa_featured_enable_user_reads"',
				'value' => qa_opt('qa_featured_enable_user_reads'),
				'type' => 'checkbox',
				);
		$fields[] = array(
				'label' => qa_lang_html('featured_lang/css_read'),
				'tags' => 'name="qa_featured_css"',
				'value' => qa_opt('qa_featured_css'),
				'type' => 'textarea',
				);
		
		$fields[] = array(
				'label' => qa_lang_html('featured_lang/min_read_qualify'),
				'type' => 'number',
				'value' => qa_html(qa_opt('leaderboard_min_reads')),
				'tags' => 'name="leaderboard_min_reads"',
				);
		$fields[] = array(
                'label' => 'Select categories users are allowed to read:',
                'type'  => 'custom',
                'html'  => $checkboxes ?: '<em>No categories defined.</em>',
				);

		$fields[] = array(
				'label' => qa_lang_html('featured_lang/max_rank_leaderboard'),
				'type' => 'number',
				'value' => qa_html(qa_opt('leaderboard_max_users')),
				'tags' => 'name="leaderboard_max_users"',
				);
		$fields[] = array(
				'label' => qa_lang_html('featured_lang/max_rank_widget'),
				'type' => 'number',
				'value' => (int)qa_opt('leaderboard_widget_count'),
				'tags'  => 'name="leaderboard_widget_count" min="1" max="20"',
                );
		return array(           
				'ok' => ($ok && !isset($error)) ? $ok : null,

				'fields' => $fields,

				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'NAME="qa_featured_questions_save"',
					),
				),
		);
	}
}

