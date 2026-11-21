<?php
class qa_leaderboard_widget {
    public function allow_template($template) {
        return true; // show on all pages
    }

    public function allow_region($region) {
        return in_array($region, ['side', 'full']); // sidebar or full
    }

    public function output_widget($region, $place, $themeobject, $template, $request, $qa_content) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $maxRank = (int)qa_opt('leaderboard_widget_count');
        if ($maxRank < 1) $maxRank = 3; // fallback

        // Yesterday's counts are final, so rank once per day
        $cacheKey = $yesterday . ':' . $maxRank;
        $cached = json_decode((string)qa_opt('leaderboard_widget_cache'), true);
        if (is_array($cached) && ($cached['key'] ?? '') === $cacheKey) {
            $rowsToShow = $cached['rows'];
        } else {
            $rowsToShow = $this->top_readers($yesterday, $maxRank);
            qa_opt('leaderboard_widget_cache', json_encode(['key' => $cacheKey, 'rows' => $rowsToShow]));
        }

        if (empty($rowsToShow)) {
            $themeobject->output('<div class="qa-leaderboard-widget">' . qa_lang_html('featured_lang/no_results') . '</div>');
            return;
        }

        // --- Output ---
        $themeobject->output('<div class="qa-leaderboard-widget">');
        $themeobject->output('<h3>' . qa_lang_html('featured_lang/yesterday_top_readers') . '</h3>');

        foreach ($rowsToShow as $row) {
            $medal = $row['_rank']==1 ? '🥇 ' : ($row['_rank']==2 ? '🥈 ' : ($row['_rank']==3 ? '🥉 ' : ''));
            $themeobject->output(
                '<div class="lb-row">'.$medal.
                '<strong>#'.(int)$row['_rank'].'</strong> '.
                '<a href="'.qa_path_html('user/'.$row['handle']).'">'.qa_html($row['handle']).'</a> — '.
                (int)$row['read_count'].' '.qa_lang_html('featured_lang/reads').'</div>'
            );
        }

        $themeobject->output('<div style="margin-top:6px;"><a href="'.qa_path_html('read-leaderboard').'">➡ '.qa_lang_html('featured_lang/full_leaderboard').'</a></div>');

        $themeobject->output('<style>
            .qa-leaderboard-widget {
                background: #f9f9f9;
                border: 1px solid #ddd;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 6px;
                font-size: 14px;
            }
            .qa-leaderboard-widget h3 {
                margin-top: 0;
                font-size: 16px;
                color: #0073e6;
            }
            .qa-leaderboard-widget .lb-row {
                margin: 4px 0;
            }
            html:not([data-theme="light"]) .qa-leaderboard-widget {
                background: rgb(255 255 255 / 6%);
                border-color: rgb(255 255 255 / 12%);
                color: inherit;
            }
            html:not([data-theme="light"]) .qa-leaderboard-widget h3 {
                color: #64b5f6;
            }
            html:not([data-theme="light"]) .qa-leaderboard-widget a {
                color: #6cb4ff;
            }
        </style></div>');
    }

    // Readers with competition ranks (1,2,2,4,…) up to $maxRank
    private function top_readers($date, $maxRank) {
        $rows = qa_db_read_all_assoc(qa_db_query_sub(
            'SELECT ur.userid, COUNT(*) AS read_count
             FROM ^userread_events ur
             WHERE ur.read_date = $
             GROUP BY ur.userid
             ORDER BY read_count DESC
             LIMIT #',
            $date, $maxRank
        ));
        if (empty($rows)) {
            return [];
        }
        $cutoff = (int)end($rows)['read_count'];

        $rows = qa_db_read_all_assoc(qa_db_query_sub(
            'SELECT u.userid, u.handle, x.read_count
             FROM (SELECT userid, COUNT(*) AS read_count FROM ^userread_events WHERE read_date = $ GROUP BY userid HAVING read_count >= #) x
             JOIN ^users u ON u.userid = x.userid
             ORDER BY x.read_count DESC, u.handle ASC',
            $date, $cutoff
        ));

        $out = [];
        $prevCount = null;
        $rank = 0;
        foreach ($rows as $pos => $row) {
            $count = (int)$row['read_count'];
            if ($count !== $prevCount) {
                $rank = $pos + 1;
            }
            if ($rank > $maxRank) {
                break;
            }
            $prevCount = $count;
            $out[] = ['handle' => $row['handle'], 'read_count' => $count, '_rank' => $rank];
        }
        return $out;
    }
}
