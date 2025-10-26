<?php
        
                        
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
                    header('Location: ../../');
                    exit;   
    }               

    qa_register_plugin_module('module', 'qa-feature-admin.php', 'qa_feature_admin', 'Feature Questions');
    qa_register_plugin_layer('qa-feature-layer.php', 'Feature Layer');
    qa_register_plugin_overrides('qa-feature-overrides.php', 'Feature Override');
	qa_register_plugin_phrases('qa-feature-lang-*.php', 'featured_lang');    
	
	// creating page for showing all the marked read questions
	qa_register_plugin_module('page', 'qa-read-questions.php', 'user_reads_page', 'Page for listing all Marked read Questions');
	
	//Creating a page for stats for all the viewed page.
	qa_register_plugin_module('page','qa-read-stats-page.php','qa_read_stats_page','Read Statistics Page');
	qa_register_plugin_module('page','qa-read-stats-ajax.php','qa_read_stats_ajax','Read Stats AJAX');

	//Creating a page for leader board based on viewed questions.
	qa_register_plugin_module('page', 'qa-mark-read-leaderboard.php', 'mark_read_leaderboard', 'Page for showing leader board based on mark read');
	qa_register_plugin_module('widget','qa-leaderboard-widget.php','qa_leaderboard_widget', 'Read Leaderboard Widget');

/*                              
    Omit PHP closing tag to help avoid accidental output
*/                              
                          

