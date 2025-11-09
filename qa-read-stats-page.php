<?php

class qa_read_stats_page
{
    public function match_request($request)
    {
        return $request === 'read-stats';
    }

    public function process_request($request)
    {
        $userid = qa_get_logged_in_userid();
        if (!$userid) {
            $content = qa_content_prepare();
            $content['error'] = qa_lang('featured_lang/login_required');
            return $content;
        }
		
		// --- Default range (last 3 months) ---
		$today = date('Y-m-d');
		$threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

		$fromParam = qa_get('from');
		$toParam   = qa_get('to');

		$fromDate = $fromParam ?: $threeMonthsAgo;
		$toDate   = $toParam   ?: $today;
		
		$fromProvided = $fromParam ? 'true' : 'false';
		$toProvided   = $toParam   ? 'true' : 'false';

        // --- Get "compare_userids" from query string (comma separated) ---
        $compareIdsParam = qa_get('compare_userids');
        $compareUserIds = [];
        if ($compareIdsParam) {
            $compareUserIds = array_filter(array_map('intval', explode(',', $compareIdsParam)));
        }

        // --- Logged-in user ---
        $userHandle = qa_userid_to_handle($userid);

        // --- Comparison users ---
        $compareUsers = [];
        foreach ($compareUserIds as $cid) {
            $compareUsers[] = [
                'id' => $cid,
                'handle' => qa_userid_to_handle($cid),
            ];
        }

		$allowed_cats = array_filter(array_map('trim', explode(',', qa_opt('user_read_allowed_categories'))));

		if (count($allowed_cats)) {
			$placeholders = implode(',', array_fill(0, count($allowed_cats), '$'));
			$query = qa_db_query_sub(
				'SELECT categoryid, title FROM ^categories WHERE categoryid IN (' . $placeholders . ') ORDER BY title',
				...$allowed_cats
			);
		} else {
			// No restriction (empty option)
			$query = qa_db_query_sub('SELECT categoryid, title FROM ^categories ORDER BY title');
		}

		$categories = qa_db_read_all_assoc($query);


        // --- Function: fetch reads for a user ---
        $fetchUserReads = function ($uid, $fromDate, $toDate) {
            $rows = qa_db_read_all_assoc(qa_db_query_sub(
                'SELECT ur.postid, ur.read_date, p.categoryid
                 FROM ^userread_events ur
                 JOIN ^posts p ON p.postid = ur.postid
                 WHERE ur.userid = #
				 AND ur.read_date BETWEEN $ AND $
                 ORDER BY ur.read_date ASC',
                $uid, $fromDate, $toDate
            ));

            $agg = [];
            foreach ($rows as $row) {
                $date = date('Y-m-d', strtotime($row['read_date']));
                $catid = $row['categoryid'] !== null ? (int)$row['categoryid'] : 0;
                if (!isset($agg[$date])) {
                    $agg[$date] = [
                        'date' => $date,
                        'timestamp' => strtotime($date) * 1000, // JS expects ms
                        'categories' => []
                    ];
                }
                if (!isset($agg[$date]['categories'][$catid])) {
                    $agg[$date]['categories'][$catid] = 0;
                }
                $agg[$date]['categories'][$catid]++;
            }
            ksort($agg);
            return array_values($agg);
        };

        // --- Fetch main + comparison data ---
        $flatData = $fetchUserReads($userid,$fromDate, $toDate);
        $compareData = [];
        foreach ($compareUsers as $u) {
            $compareData[] = [
                'handle' => $u['handle'],
                'id' => $u['id'],
                'data' => $fetchUserReads($u['id'],$fromDate, $toDate),
            ];
        }

        // --- Categories for JS ---
        $jsCategories = [];
        foreach ($categories as $c) {
            $jsCategories[] = ['id' => (int)$c['categoryid'], 'title' => $c['title']];
        }
        $jsCategories[] = ['id' => 0, 'title' => qa_lang('featured_lang/uncategorized')];

        // --- Page setup ---
        $compareHandles = array_column($compareUsers, 'handle');
        $allHandles = array_merge([$userHandle], $compareHandles);
        $titleSuffix = implode(' vs ', $allHandles);

        $content = qa_content_prepare();
        $content['title'] = qa_lang('featured_lang/reading_analytics') . ' — ' . qa_html($titleSuffix);

		// --- Info banner ---
		$infoBox = '
		<div class="qa-info-box">
			<p>Note: <ol>
				<li> ' . qa_lang_html('featured_lang/info_stats') . '</li>
				<li> ' . qa_lang_html('featured_lang/info_leaderboard') . '<a href="' . qa_path_html('read-leaderboard') . '"> ' . qa_lang_html('featured_lang/go_to_leaderboard') . '</a></li>
				<li> ' . qa_lang_html('featured_lang/info_read_questions'). '<a href="' . qa_path_html('read') . '"> ' . qa_lang_html('featured_lang/go_to_read_list') . '</a></li>				
				</ol>			
			</p>
		</div>';

        // --- HTML & JS ---
        $html = $infoBox . '
			<div style="margin-bottom:1em;">
				<label><strong>' . qa_lang('featured_lang/add_compare_users') . ':</strong></label>
				<input type="text" placeholder="' . qa_lang('featured_lang/search_users') . '" id="compare_usersearch" autofocus>
				<div class="compare_us_progress"><div>' . qa_lang('featured_lang/loading') . '…</div></div>
				<div id="compare_ajaxsearch_results"></div>
				<div id="selectedUsers" style="margin-top:0.5em;"></div>
				<button type="button" id="compareUsersBtn" class="qa-form-tall-button" style="margin-top:5px;">' . qa_lang('featured_lang/compare_button') . '</button>
			</div>

			<form id="filterForm" onsubmit="return false;" style="margin-bottom:1em;">
				<label>' . qa_lang('featured_lang/from') . ': <input type="date" id="fromDate"></label>
				<label>' . qa_lang('featured_lang/to') . ': <input type="date" id="toDate"></label>
				<label>' . qa_lang('featured_lang/categories') . ':
					<select id="categorySelect" multiple size="6" style="vertical-align: middle; min-width:220px;">
						<option value="__all__" selected>(' . qa_lang('featured_lang/all_categories') . ')</option>';

        foreach ($jsCategories as $c) {
            $html .= '<option value="' . qa_html($c['id']) . '">' . qa_html($c['title']) . '</option>';
        }

        $html .= '
				</select>
			</label>
			<button type="button" id="applyFilter" class="qa-form-tall-button">' . qa_lang('featured_lang/filter') . '</button>
			<button type="button" id="resetFilter" class="qa-form-tall-button">' . qa_lang('featured_lang/reset') . '</button>
		</form>

		<div id="chartContainer" style="height:420px;width:100%;margin-bottom:2em;"></div>
		<div id="chartContainerByCategory" style="height:420px;width:100%;"></div>
		<style>
		/* --- User Search Styles --- */
		.compare_usersearch_resultfield {
			display: inline-block;
			margin: 10px 10px 0 0;
			width: 100px;
			text-align: center;
			vertical-align: top;
		}

		.compare_us_avatar img {
			border: 1px solid #EEE;
			border-radius: 4px;
			max-width: 80px;
			max-height: 80px;
			cursor: pointer;
		}

		.compare_us_link {
			word-wrap: break-word;
			cursor: pointer;
			color: #0073e6;
			font-weight: bold;
			text-decoration: none;
		}
		.compare_us_link:hover {
			text-decoration: underline;
		}

		/* --- Loading Spinner --- */
		@keyframes spin {
			to { transform: rotate(1turn); }
		}
		.compare_us_progress {
			position: relative;
			display: inline-block;
			width: 2em;
			height: 2em;
			margin: 0 .5em;
			text-indent: 999em;
			overflow: hidden;
			animation: spin 1s infinite steps(8);
			font-size: 3px;
		}
		.compare_us_progress:before,
		.compare_us_progress:after,
		.compare_us_progress > div:before,
		.compare_us_progress > div:after {
			content: "";
			position: absolute;
			top: 0;
			left: .9em;
			width: .2em;
			height: .6em;
			border-radius: .2em;
			background: #ccc;
			box-shadow: 0 1.4em #ccc; /* container height - part height */
			transform-origin: 50% 1em; /* container height / 2 */
		}
		.compare_us_progress:before { background: #555; }
		.compare_us_progress:after { transform: rotate(-45deg); background: #777; }
		.compare_us_progress > div:before { transform: rotate(-90deg); background: #999; }
		.compare_us_progress > div:after { transform: rotate(-135deg); background: #bbb; }
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

		</style>
		<script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>
		<script type="text/javascript">
		const fromProvided = '.$fromProvided.';
		const toProvided   = '.$toProvided.';
		$(document).ready(function(){

			// Hide loading spinner initially
			$(".compare_us_progress").hide();
			
			// ------------------------------
			// USER SELECTION + SEARCH
			// ------------------------------
			let selectedHandles = new Map();
			const userName = ' . qa_js($userHandle) . ';
			selectedHandles.set(userName, {id: ' . (int)$userid . ', handle:userName});
			const compareData = ' . json_encode($compareData, JSON_NUMERIC_CHECK) . ';
			compareData.forEach(u => selectedHandles.set(u.handle, {id:u.id, handle:u.handle}));

			function renderSelected() {
				let html = "";
				selectedHandles.forEach((v,h)=>{
					html += `<span style="background:#eef;padding:3px 6px;margin:2px;border-radius:4px;">${h}`;
					if(h!==userName) html += `<a href="#" data-h="${h}" style="color:red;text-decoration:none;">×</a>`;
					html += `</span>`;
				});
				$("#selectedUsers").html(html);
				$("#selectedUsers a").click(function(e){
					e.preventDefault();
					let h = $(this).data("h");
					selectedHandles.delete(h);
					renderSelected();
				});
			}
			renderSelected();

			// AJAX search
			$("#compare_usersearch").keyup(function(){
				let username = $(this).val().trim();
				if(username===""){ $("#compare_ajaxsearch_results").hide(); return; }
				$(".compare_us_progress").show();
				$.ajax({
					type:"POST",
					url:"' . qa_path('pread-stats') . '",
					data:{ ajax:username },
					success:function(htmldata){
						$("#compare_ajaxsearch_results").html(htmldata).show();
						$(".compare_us_progress").hide();
					},
					error:function(){ $("#compare_ajaxsearch_results").html("' . qa_lang('featured_lang/server_error') . '").show(); $(".compare_us_progress").hide(); }
				});
			});

			// Add from search results
			window.to_add_username = function(handle, userid){
				if(!selectedHandles.has(handle)){
					selectedHandles.set(handle, {id:userid, handle:handle});
					renderSelected();
				}
				$("#compare_ajaxsearch_results").hide();
				$("#compare_usersearch").val("");
			};

			// Compare button
			$("#compareUsersBtn").click(function(){
				let userIds = [];
				selectedHandles.forEach((v,h)=>{ if(h!==userName && v.id) userIds.push(v.id); });

				let fromVal = $("#fromDate").val();
				let toVal   = $("#toDate").val();

				let url = "'.qa_path_html('read-stats').'";
				let params = [];

				if(userIds.length > 0) params.push("compare_userids="+userIds.join(","));
				if(fromProvided && fromVal) params.push("from="+fromVal);
				if(toProvided && toVal)     params.push("to="+toVal);

				if(params.length > 0) url += "?" + params.join("&");

				window.location.href = url;
			});


			/* ------------------------------
			CHARTS
			------------------------------ */
			const allData = ' . json_encode($flatData, JSON_NUMERIC_CHECK) . ';
			const allCategories = ' . json_encode($jsCategories, JSON_NUMERIC_CHECK) . ';

			function getSelectedCategoryIds() {
				var sel = Array.from(document.getElementById("categorySelect").selectedOptions).map(o=>o.value);
				if(sel.length===0 || sel.includes("__all__")) return null;
				return sel.map(s=>parseInt(s,10));
			}

			function buildDataPoints(dayRows, selectedCategoryIds){
				var pts=[];
				dayRows.forEach(function(day){
					var sum=0;
					var cats=day.categories||{};
					if(!selectedCategoryIds){ for(var cid in cats) sum+=cats[cid]; }
					else selectedCategoryIds.forEach(cid=>{ if(cats[cid]) sum+=cats[cid]; });
					pts.push({x:day.timestamp, y:sum, label:day.date});
				});
				return pts;
			}

			// Chart 1: Total Reads
			var chart = new CanvasJS.Chart("chartContainer",{
				animationEnabled:true, exportEnabled:true, theme:"light1",
				title:{ text:"' . qa_lang('featured_lang/questions_read_total') . '" },
				axisY:{ title:"' . qa_lang('featured_lang/num_reads') . '", includeZero:true },
				axisX:{ title:"' . qa_lang('featured_lang/date') . '", labelAngle:-45, xValueType:"dateTime", labelFormatter:e=>CanvasJS.formatDate(e.value,"DD MMM YY") },
				toolTip:{ shared:true },
				legend:{ cursor:"pointer", itemclick:e=>{ e.dataSeries.visible=!e.dataSeries.visible; chart.render(); } },
				data:[]
			});

			// Chart 2: Reads per Category
			var chartByCat = new CanvasJS.Chart("chartContainerByCategory",{
				animationEnabled:true, exportEnabled:true, theme:"light1",
				title:{ text:"' . qa_lang('featured_lang/reads_by_category') . '" },
				axisY:{ title:"' . qa_lang('featured_lang/reads_per_category') . '", includeZero:true },
				axisX:{ title:"' . qa_lang('featured_lang/date') . '", labelAngle:-45, xValueType:"dateTime", labelFormatter:e=>CanvasJS.formatDate(e.value,"DD MMM YY") },
				toolTip:{
					shared:true,
					content:function(e){
						var str = CanvasJS.formatDate(e.entries[0].dataPoint.x, "DD MMM YYYY")+"<br/>";
						e.entries.forEach(function(entry){ str += entry.dataSeries.name+": "+entry.dataPoint.y+"<br/>"; });
						return str;
					}
				},
				legend:{ cursor:"pointer", itemclick:e=>{ e.dataSeries.visible=!e.dataSeries.visible; chartByCat.render(); } },
				data:[]
			});

			// Render both charts
			function renderFlow(mainDays, compareUsers){
				var selIds = getSelectedCategoryIds();

				// Chart 1 data
				var allSeries=[{type:"line",markerSize:6,lineThickness:3,name:userName,showInLegend:true,dataPoints:buildDataPoints(mainDays,selIds)}];
				compareUsers.forEach(u=>{ allSeries.push({type:"line",markerSize:6,lineThickness:1.5,lineDashType:"dash",name:u.handle,showInLegend:true,dataPoints:buildDataPoints(u.data,selIds)}); });
				chart.options.data=allSeries; chart.render();

				// Chart 2 data (per category)
				var catLines=[]; var catsToUse = selIds?selIds:allCategories.map(c=>c.id);
				var firstCategoryId = catsToUse.length>0?catsToUse[0]:null;
				catsToUse.forEach(cid=>{
					var cat = allCategories.find(c=>c.id===cid);
					var isVisible = (cid===firstCategoryId);
					var userPts = mainDays.map(day=>({x:day.timestamp,y:(day.categories[cid]||0)}));
					catLines.push({type:"line",markerSize:6,lineThickness:3,showInLegend:true,visible:isVisible,name:(cat?cat.title:"Unknown")+" ("+userName+")",dataPoints:userPts});
					compareUsers.forEach(u=>{
						var comparePts = u.data.map(day=>({x:day.timestamp,y:(day.categories[cid]||0)}));
						catLines.push({type:"line",markerSize:6,lineThickness:1.5,lineDashType:"dash",showInLegend:true,visible:isVisible,name:(cat?cat.title:"Unknown")+" ("+u.handle+")",dataPoints:comparePts});
					});
				});
				chartByCat.options.data=catLines; chartByCat.render();
			}
			
			(function setDefaultDates(){
				// Server values injected directly
				$("#toDate").val("'.$toDate.'");
				$("#fromDate").val("'.$fromDate.'");
			})();


			// Apply Filter
			$("#applyFilter").click(function(){
				var fromVal=$("#fromDate").val(), toVal=$("#toDate").val();

				const serverFrom = new Date("'.$fromDate.'");
				const serverTo   = new Date("'.$toDate.'");

				
				// if requested range is outside loaded window, reload
				if ((fromVal && new Date(fromVal) < serverFrom) || (toVal && new Date(toVal) > serverTo)) {
					let url = "'.qa_path_html('read-stats').'?from="+fromVal+"&to="+toVal;
					
					// preserve compare_userids
					let compareIds = Array.from(selectedHandles.values())
									 .filter(u => u.id && u.handle !== userName)
									 .map(u => u.id);
					if(compareIds.length > 0) url += "&compare_userids="+compareIds.join(",");

					window.location.href = url;
					return;
				}


	
				var fromTime=fromVal?new Date(fromVal).getTime():null, toTime=toVal?new Date(toVal).getTime()+86400000:null;
				function withinRange(day){ if(fromTime && day.timestamp<fromTime) return false; if(toTime && day.timestamp>=toTime) return false; return true; }
				var filteredMain = allData.filter(withinRange);
				var filteredCompare = Array.from(selectedHandles.entries()).filter(([h,v])=>h!==userName).map(([h,v])=>({handle:h,data:(compareData.find(u=>u.handle===h)||{data:[]}).data.filter(withinRange)}));
				renderFlow(filteredMain, filteredCompare);
			});

			// Reset Filter
			$("#resetFilter").click(function(){
				$("#fromDate").val(""); $("#toDate").val("");
				$("#categorySelect option").prop("selected",function(){ return this.value==="__all__"; });
				var filteredCompare = Array.from(selectedHandles.entries()).filter(([h,v])=>h!==userName).map(([h,v])=>({handle:h,data:(compareData.find(u=>u.handle===h)||{data:[]}).data}));
				renderFlow(allData, filteredCompare);
			});

			// Initial render
			renderFlow(allData, Array.from(selectedHandles.entries()).filter(([h,v])=>h!==userName).map(([h,v])=>({handle:h,data:(compareData.find(u=>u.handle===h)||{data:[]}).data})));

		});
		</script>';

        $content['custom'] = $html;
        return $content;
    }
}
