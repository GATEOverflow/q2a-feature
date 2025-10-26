<?php
class qa_read_stats_ajax
{
    public function match_request($request)
    {
        return $request === 'pread-stats';
    }

		function process_request($request)
		{
		
			// we received post data, it is the ajax call with the username
			$transferString = qa_post_text('ajax');
			if($transferString !== null) {
				
				// this is echoed via ajax success data
				$output = '';
				
				$username = $transferString;
				
				// ajax return all user events
				$potentials = qa_db_read_all_assoc(
					qa_db_query_sub('SELECT userid FROM ^users WHERE handle LIKE # LIMIT #', '%'.$username.'%',10));

			 
				foreach($potentials as $user) {
					if(isset($user['userid'])) {
						// get userdata
						$userdata = qa_db_read_one_assoc(
							qa_db_query_sub(
								'SELECT handle, avatarblobid FROM ^users WHERE userid = #',
								$user['userid']
							)
						);

						$imgsize = 100;
						$avatar = isset($userdata['avatarblobid'])
							? './?qa=image&qa_blobid='.$userdata['avatarblobid'].'&qa_size='.$imgsize
							: '';

						$handledisplay = qa_html($userdata['handle']);
						$userid = (int)$user['userid']; // Add this

						// user item HTML with data-userid attribute
						$output .= '<div class="compare_usersearch_resultfield" data-userid="'.$userid.'" data-handle="'.qa_html($userdata['handle']).'">
							<img src="'.$avatar.'" alt="'.$handledisplay.'" onclick="to_add_username('.qa_js($userdata['handle']).', '.$userid.')">
							<br />
							<p class="compare_us_link" onclick="to_add_username('.qa_js($userdata['handle']).', '.$userid.')">'.$handledisplay.'</p>
						</div>';
					}
				}		 
				header('Access-Control-Allow-Origin: '.qa_path(null));
				echo $output;
				
				exit(); 
			} // END AJAX RETURN
			else {
				echo 'Unexpected problem detected. No transfer string.';
				exit();
			}
			
			
			/* start */
			$qa_content = qa_content_prepare();

			$qa_content['title'] = ''; // page title

			// return if not admin!
			if(qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
				$qa_content['error'] = '<p>Access denied</p>';
				return $qa_content;
			}
			else {
				$qa_content['custom'] = '<p>Hi Admin, it actually makes no sense to call the Ajax URL directly.</p>';
			}

			return $qa_content;
		} // end process_request
		

}
