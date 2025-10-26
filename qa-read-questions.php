<?php
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

class user_reads_page
{
    private $userid;
    private $handle;

    public function suggest_requests()
    {
        $guest_handle = qa_get_logged_in_handle();
        return [
            [
                'title' => qa_lang('featured_lang/all_reads'),
                'request' => 'read/' . $guest_handle . '/',
                'nav' => 'M',
            ],
        ];
    }

    public function match_request($request)
    {
        $requestparts = qa_request_parts();
        $guest_handle = qa_get_logged_in_handle();
        $user_handle  = isset($requestparts[1]) ? $requestparts[1] : $guest_handle;

        $isMy        = ($user_handle === $guest_handle);
        $isAuthorized = qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN;

        if ($requestparts[0] === 'read' && ($isMy || $isAuthorized)) {
            $this->userid = qa_handle_to_userid($user_handle);
            $this->handle = $user_handle;
            return true;
        }
        return false;
    }

    public function process_request($request)
    {
        $userid = $this->userid;
        if (!$userid) {
            qa_redirect('login', ['to' => qa_path(qa_request())]);
        }

        require_once QA_INCLUDE_DIR . 'db/selects.php';
        require_once QA_INCLUDE_DIR . 'app/format.php';
        require_once QA_INCLUDE_DIR . 'app/q-list.php';

        $categoryslugs = qa_request_parts(2);
        $countslugs    = count($categoryslugs);
        $sort          = ($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('sort');
        $start         = qa_get_start();

        // Count total reads
        $query   = "SELECT postid FROM ^userreads WHERE userid = #";
        $result  = qa_db_query_sub($query, $userid);
        $postids = qa_db_read_all_values($result);
        $tcount  = ($postids ? count($postids) : 0);

        $selectsort = match ($sort) {
            'hot' => 'hotness',
            'votes' => 'netvotes',
            'answers' => 'acount',
            'views' => 'views',
            default => 'created',
        };

        $selectspec = $this->qa_db_qs_mod_selectspec(
            $userid,
            $selectsort,
            $start,
            $categoryslugs,
            null,
            false,
            false,
            qa_opt_if_loaded('page_size_qs')
        );

        // Build selectspec for categories based on user's notes
		$selectspec2 = $this->qa_db_category_nav_with_userreads_selectspec($categoryslugs, $userid);

		// Fetch questions with categories
		list($questions, $categories, $categoryid) = qa_db_select_with_pending(
			$selectspec,
			$selectspec2,
			$countslugs ? qa_db_slugs_to_category_id_selectspec($categoryslugs) : null
		);

		if (isset($categories[$categoryid])) {
			$total_questions = $categories[$categoryid]['questions_count'];
		} else {
			$total_questions = array_sum(array_column($categories, 'questions_count'));
		}

        $nonetitle = $countslugs
            ? qa_lang_html_sub('main/no_questions_in_x', qa_html($categories[$categoryid]['title']))
            : qa_lang_html('main/no_questions_found');

        $qa_content = qa_q_list_page_content(
            $questions,
            qa_opt('page_size_qs'),
            $start,
            $total_questions,
            qa_lang_sub('featured_lang/user_reads_page_title', $this->handle),
            $nonetitle,
            $categories,
            $categoryid ?? null,
            false,
            'read/' . $this->handle . '/',
            null,
            null,
            ['sort' => $sort],
            ['sort' => $sort]
        );

        $formcode = qa_get_form_security_code('user_read');

        // Handle "Mark Unread"
        if (qa_post_text('delete-read') && qa_check_form_security_code('user_read', qa_post_text('formcode'))) {
            $postid = (int)qa_post_text('postid');
            qa_db_query_sub("DELETE FROM ^userreads WHERE postid=# AND userid=#", $postid, $userid);
            qa_redirect(qa_request(), ['unread' => 1]);
        }

        // Attach "Mark Unread" button for each question
        foreach ($qa_content['q_list']['qs'] as $key => $q) {
            $postid = $q['raw']['postid'];

			// Ensure content key exists
			if (!isset($qa_content['q_list']['qs'][$key]['content'])) {
				$qa_content['q_list']['qs'][$key]['content'] = '';
			}

            $qa_content['q_list']['qs'][$key]['content'] .= '
                <form method="post" style="margin-top:5px;">
                    <input type="hidden" name="formcode" value="' . qa_html($formcode) . '">
                    <input type="hidden" name="postid" value="' . (int)$postid . '">
                    <input type="submit" name="delete-read" value="' . qa_lang_html('featured_lang/mark_unread') . '" class="qa-form-tall-button" onclick="return confirm(\'' . qa_lang_html('featured_lang/confirm_unread') . '\')">
                </form>';
        }

        // Sub navigation
        $qa_content['navigation']['sub'] = qa_user_sub_navigation(
            $this->handle,
            'read',
            ($this->userid == qa_get_logged_in_userid())
        );

        return $qa_content;
    }

    private function qa_db_qs_mod_selectspec($voteuserid, $sort, $start, $categoryslugs = null, $createip = null, $specialtype = false, $full = false, $count = null)
    {
        if ($specialtype == 'Q' || $specialtype == 'Q_QUEUED') {
            $type = $specialtype;
        } else {
            $type = $specialtype ? 'Q_HIDDEN' : 'Q';
        }

        $count = isset($count) ? min($count, QA_DB_RETRIEVE_QS_AS) : QA_DB_RETRIEVE_QS_AS;

        switch ($sort) {
            case 'acount':
            case 'flagcount':
            case 'netvotes':
            case 'views':
                $sortsql = 'ORDER BY ^posts.' . $sort . ' DESC, ^posts.created DESC';
                break;
            case 'created':
            case 'hotness':
                $sortsql = 'ORDER BY ^posts.' . $sort . ' DESC';
                break;
            default:
                qa_fatal_error('qa_db_qs_selectspec() called with illegal sort value');
                break;
        }

        $selectspec = qa_db_posts_basic_selectspec($voteuserid, $full);

        $query   = "SELECT postid FROM ^userreads WHERE userid=#";
        $result  = qa_db_query_sub($query, $voteuserid);
        $postids = qa_db_read_all_values($result);
        if ($postids && is_array($postids) && count($postids)) {
            $questions = implode(',', array_map('intval', $postids));
        } else {
            $questions = '0';
        }

        $selectspec['source'] .= " JOIN (SELECT postid FROM ^posts WHERE postid IN ($questions)) aby ON aby.postid=^posts.postid";
        $selectspec['source'] .=
            " JOIN (SELECT postid FROM ^posts WHERE " .
            qa_db_categoryslugs_sql_args($categoryslugs, $selectspec['arguments']) .
            (isset($createip) ? "createip=UNHEX($) AND " : "") .
            "type=$ ) y ON ^posts.postid=y.postid " . $sortsql . " LIMIT #,#";

        if (isset($createip)) {
            $selectspec['arguments'][] = bin2hex(@inet_pton($createip));
        }

        array_push($selectspec['arguments'], $type, $start, $count);
        $selectspec['sortdesc'] = $sort;

        return $selectspec;
    }

	private function qa_db_category_nav_with_userreads_selectspec($categoryslugs, $userid, $full = true)
	{
		$selectspec = qa_db_category_nav_selectspec($categoryslugs, false, false, $full);

		// Add custom column for number of questions that are actually belongs to that query
		$selectspec['columns']['questions_count'] = 'COUNT(DISTINCT userreads.postid)';

		// Inject JOINs into the source
		$selectspec['source'] = str_replace(
			'GROUP BY ^categories.categoryid',
			'JOIN ^posts ON ^posts.categoryid = ^categories.categoryid
			AND ^posts.type = \'Q\'
			 LEFT JOIN ^userreads AS userreads 
				ON userreads.postid = ^posts.postid 
			   AND userreads.userid = ' . (int)$userid . '
			 GROUP BY ^categories.categoryid',
			$selectspec['source']
		);

		return $selectspec;
	}
	
}
