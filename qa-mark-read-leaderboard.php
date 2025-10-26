<?php
class mark_read_leaderboard {
    public function match_request($request) {
        return $request == 'read-leaderboard';
    }

    public function process_request($request) {
        // Yesterday by default
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $from = qa_get('from') ?: $yesterday;
        $to   = qa_get('to')   ?: $yesterday;

        $min_reads = (int)qa_opt('leaderboard_min_reads');
        $maxRank   = (int)qa_opt('leaderboard_max_users');

        // Load categories
        $categories = qa_db_read_all_assoc(qa_db_query_sub(
            'SELECT categoryid, title FROM ^categories ORDER BY title ASC'
        ));

        // Load all user reads in range (with category)
        $rows = qa_db_read_all_assoc(qa_db_query_sub(
            'SELECT u.userid, u.handle, u.flags, u.email, u.avatarblobid, 
                    p.categoryid, COUNT(ur.postid) as read_count
             FROM ^userread_events ur
             JOIN ^users u ON u.userid = ur.userid
             JOIN ^posts p ON p.postid = ur.postid
             WHERE ur.read_date BETWEEN $ AND $
             GROUP BY u.userid, u.handle, u.flags, u.email, u.avatarblobid, p.categoryid',
             $from, $to
        ));

        // Transform into structured array per user
        $userData = [];
        foreach ($rows as $r) {
            $uid = $r['userid'];
            if (!isset($userData[$uid])) {
                $userData[$uid] = [
                    'userid' => $uid,
                    'handle' => $r['handle'],
                    'flags' => $r['flags'],
                    'email' => $r['email'],
                    'avatarblobid' => $r['avatarblobid'],
                    'categories' => [],
                ];
            }
            $userData[$uid]['categories'][$r['categoryid']] = (int)$r['read_count'];
        }

        $content = qa_content_prepare();
        $content['title'] = qa_lang('featured_lang/leaderboard_title');

        // Filter controls
		$infoBox = '
		<div class="qa-info-box">
			<p>Note: <ol>
				<li> ' . qa_lang_html('featured_lang/info_leaderboard_intro') . '</li>
				<li> ' . qa_lang_html('featured_lang/info_read_stats') . '<a href="' . qa_path_html('read-stats') . '"> ' . qa_lang_html('featured_lang/go_to_stats') . '</a></li>
				<li> ' . qa_lang_html('featured_lang/info_read_questions'). '<a href="' . qa_path_html('read') . '"> ' . qa_lang_html('featured_lang/go_to_read_list') . '</a></li>				
				</ol>			
			</p>
		</div>';

        $html = $infoBox.'<div style="margin-bottom:15px;">
                    <label>' . qa_lang('featured_lang/from') . ': 
                        <input type="date" id="fromDate" value="' . qa_html($from) . '"></label>
                    <label>' . qa_lang('featured_lang/to') . ': 
                        <input type="date" id="toDate" value="' . qa_html($to) . '"></label>
                    <label>' . qa_lang('featured_lang/category') . ':
                        <select id="categorySelect" multiple size="6" style="vertical-align: middle; min-width:220px;">
                            <option value="__all__" selected>(All Categories)</option>';
        foreach ($categories as $c) {
            $html .= '<option value="' . (int)$c['categoryid'] . '">' . qa_html($c['title']) . '</option>';
        }
        $html .= '   </select>
                    </label>
                    <button id="applyFilter" class="qa-form-tall-button">' . qa_lang('featured_lang/filter') . '</button>
					<span style="opacity:.75;">' . qa_lang('featured_lang/min_reads') . ': ' . (int)$min_reads .
                     ' &nbsp;|&nbsp; ' . strtr(qa_lang('featured_lang/showing_top'), ['^' => $maxRank]) . '</span>
                 </div>';

        // Leaderboard table container
        $html .= '<h3 id="leaderboardTitle"></h3>';
        $html .= '<div id="leaderboardTable"></div>';
		
		$html .= '<style>
		.qa-info-box {
			background: #f0f7ff;
			border: 1px solid #bcd;
			padding: 10px 15px;
			margin-bottom: 15px;
			border-radius: 6px;
			font-size: 14px;
		}
		.qa-info-box a {
			font-weight: bold;
			color: #0073e6;
			text-decoration: none;
		}
		.qa-info-box a:hover {
			text-decoration: underline;
		}

		</style>';

        // Pass data to JS
        $html .= '<script>
            const allUsers = ' . json_encode(array_values($userData)) . ';
            const allCategories = ' . json_encode($categories) . ';
            const minReads = ' . $min_reads . ';
            const maxRank = ' . $maxRank . ';

            // --- Render Leaderboard ---
            function renderLeaderboard(filteredUsers, selectedCats) {
                let totals = filteredUsers.map(u => {
                    let total = 0;
                    for (const cid in u.categories) total += u.categories[cid];
                    return { ...u, total };
                }).filter(u => u.total >= minReads);

                totals.sort((a,b) => b.total - a.total || a.handle.localeCompare(b.handle));

                let prevTotal=null, prevRank=0, pos=0;
                totals.forEach(u => {
                    pos++;
                    if (u.total === prevTotal) u.rank = prevRank;
                    else u.rank = pos;
                    prevTotal = u.total;
                    prevRank = u.rank;
                });

                totals = totals.filter(u => u.rank <= maxRank);

                

                // --- Build HTML ---
                let html = "<table class=\'qa-form-tall-table leaderboard-table\'>";
                html += "<thead><tr><th>Rank</th><th>User</th><th>Total Reads</th>";

                // Category headers ONLY if specific categories selected
                let showCategoryCols = !(selectedCats.includes("__all__") || selectedCats.length===0 || selectedCats.length===1);
                let catsToShow = [];
                if (showCategoryCols) {
                    catsToShow = allCategories.filter(c => selectedCats.includes(c.categoryid.toString()));
                    catsToShow.forEach(c => {
                        html += "<th>"+c.title+"</th>";
                    });
                }
                html += "</tr></thead><tbody>";

                if (totals.length === 0) {
                    let colspan = showCategoryCols ? 3+catsToShow.length : 3;
                    html += "<tr><td colspan=\'"+colspan+"\' style=\'text-align:center;\'>No results</td></tr>";
                } else {
                    totals.forEach(u => {
                        let medal = (u.rank==1?"🥇":(u.rank==2?"🥈":(u.rank==3?"🥉":"")));
                        html += "<tr><td>"+medal+u.rank+"</td>";
                        html += "<td><a href=\'index.php?qa=user/"+u.handle+"\'>"+u.handle+"</a></td>";
                        html += "<td style=\'text-align:center;\'>"+u.total+"</td>";
                        if (showCategoryCols) {
                            catsToShow.forEach(c => {
                                let val = u.categories[c.categoryid] ? u.categories[c.categoryid] : 0;
                                html += "<td style=\'text-align:center;\'>"+val+"</td>";
                            });
                        }
                        html += "</tr>";
                    });
                }
                html += "</tbody></table>";
                document.getElementById("leaderboardTable").innerHTML = html;
            }

            // --- Apply Filter ---
            document.getElementById("applyFilter").addEventListener("click", () => {
                const cats = Array.from(document.getElementById("categorySelect").selectedOptions).map(o => o.value);
                let filtered = allUsers.map(u => {
                    let filteredCats = {};
                    for (const cid in u.categories) {
                        if (cats.includes("__all__") || cats.length===0 || cats.includes(cid.toString())) {
                            filteredCats[cid] = u.categories[cid];
                        }
                    }
                    return { ...u, categories: filteredCats };
                });
                renderLeaderboard(filtered, cats);
            });

            // --- Reload when dates change ---
            document.getElementById("fromDate").addEventListener("change", reloadWithDates);
            document.getElementById("toDate").addEventListener("change", reloadWithDates);
            function reloadWithDates(){
                const from = document.getElementById("fromDate").value;
                const to = document.getElementById("toDate").value;
                const url = new URL(window.location.href);
                url.searchParams.set("from", from);
                url.searchParams.set("to", to);
                window.location.href = url.toString();
            }

            // --- Initial Render ---
            renderLeaderboard(allUsers, ["__all__"]);
        </script>';

        // Styling
        $html .= '<style>
            .leaderboard-table { width:100%; border-collapse:collapse; margin-top:10px; }
            .leaderboard-table th, .leaderboard-table td { border:1px solid #ddd; padding:8px; }
            .leaderboard-table th { background:#0073e6; color:#fff; }
            .leaderboard-table tr:nth-child(even) { background:#fafafa; }
            .leaderboard-table tr:hover { background:#f1f7ff; }
        </style>';

        $content['custom'] = $html;
        return $content;
    }
}
