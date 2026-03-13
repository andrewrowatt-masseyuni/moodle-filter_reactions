<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_reactions;

/**
 * Reactions filter - renders thumbs/stars reaction widgets.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {

    /**
     * Set up the filter using setup page.
     *
     * @param \moodle_page $page
     * @param \context $context
     */
    public function setup($page, $context) {
        if ($page->requires->should_create_one_time_item_now('filter_reactions_js')) {
            $page->requires->js_call_amd('filter_reactions/main', 'init', []);
        }
    }

    /**
     * Filter text - detect {reactions:type} and {reactions:type,id} tags.
     *
     * @param string $text some HTML content to process.
     * @param array $options options passed to the filters
     * @return string the HTML content after the filtering has been applied.
     */
    public function filter($text, array $options = []) {
        if (strpos($text, '{reactions:') === false) {
            return $text;
        }

        return preg_replace_callback(
            '/\{reactions:(thumbs|stars)(?:,([a-zA-Z0-9]+))?\}/',
            function ($matches) {
                return $this->render_widget($matches[1], $matches[2] ?? null);
            },
            $text
        );
    }

    /**
     * Render a reaction widget.
     *
     * @param string $type 'thumbs' or 'stars'
     * @param string|null $itemid optional item identifier
     * @return string rendered HTML
     */
    private function render_widget(string $type, ?string $itemid): string {
        global $DB, $USER, $PAGE;

        $contextid = $this->context->id;
        $itemidprefix = '';
        
        // If the context is a book module, prefix with chapter ID for per-chapter reactions.
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $cm = $PAGE->cm;
            if ($cm && $cm->modname === 'book') {
                $chapterid = optional_param('chapterid', 0, PARAM_INT);
                if (!$chapterid) {
                    // No chapter specified — default to the first chapter in the book.
                    $firstchapter = $DB->get_records('book_chapters', ['bookid' => $cm->instance],
                        'pagenum ASC', 'id', 0, 1);
                    $firstchapter = reset($firstchapter);
                    if ($firstchapter) {
                        $chapterid = $firstchapter->id;
                    }
                }
                if ($chapterid) {
                    $itemidprefix = 'chapter' . $chapterid;
                }
            }
        }

        // Default the item ID to "defaultfor{contextid}" when none is provided.
        if (empty($itemid)) {
            $itemid = 'defaultfor' . $itemidprefix . $contextid;
        } else {
            $itemid = $itemidprefix . $itemid;
        }

        $canreact = isloggedin() && !isguestuser();

        // Get current user's response.
        $userresponse = '';
        if ($canreact && !empty($itemid)) {
            $record = $DB->get_record('filter_reactions', [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'type' => $type,
                'userid' => $USER->id,
            ]);
            if ($record) {
                $userresponse = $record->response;
            }
        }

        // Get aggregate counts.
        $counts = [];
        if (!empty($itemid)) {
            $records = $DB->get_records('filter_reactions', [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'type' => $type,
            ]);
            foreach ($records as $r) {
                if (!isset($counts[$r->response])) {
                    $counts[$r->response] = 0;
                }
                $counts[$r->response]++;
            }
        }

        if ($type === 'thumbs') {
            return $this->render_thumbs($contextid, $itemid, $userresponse, $counts, $canreact);
        } else {
            return $this->render_stars($contextid, $itemid, $userresponse, $counts, $canreact);
        }
    }

    /**
     * Render thumbs widget.
     *
     * @param int $contextid
     * @param string $itemid
     * @param string $userresponse
     * @param array $counts
     * @param bool $canreact
     * @return string
     */
    private function render_thumbs(int $contextid, string $itemid, string $userresponse,
            array $counts, bool $canreact): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('filter_reactions/thumbs', [
            'contextid' => $contextid,
            'itemid' => $itemid,
            'canreact' => $canreact,
            'thumbsupcount' => $counts['thumbsup'] ?? 0,
            'thumbsdowncount' => $counts['thumbsdown'] ?? 0,
            'thumbsupselected' => $userresponse === 'thumbsup',
            'thumbsdownselected' => $userresponse === 'thumbsdown',
        ]);
    }

    /**
     * Render stars widget.
     *
     * @param int $contextid
     * @param string $itemid
     * @param string $userresponse
     * @param array $counts
     * @param bool $canreact
     * @return string
     */
    private function render_stars(int $contextid, string $itemid, string $userresponse,
            array $counts, bool $canreact): string {
        global $OUTPUT;

        $uservalue = 0;
        if (preg_match('/^(\d)stars?$/', $userresponse, $m)) {
            $uservalue = (int) $m[1];
        }

        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $resp = $i === 1 ? '1star' : $i . 'stars';
            $stars[] = [
                'value' => $i,
                'response' => $resp,
                'filled' => $i <= $uservalue,
                'selected' => $i === $uservalue,
            ];
        }

        // Calculate average.
        $totalcount = 0;
        $totalvalue = 0;
        foreach ($counts as $resp => $count) {
            if (preg_match('/^(\d)stars?$/', $resp, $m)) {
                $val = (int) $m[1];
                $totalcount += $count;
                $totalvalue += $val * $count;
            }
        }
        $average = $totalcount > 0 ? round($totalvalue / $totalcount, 1) : 0;

        return $OUTPUT->render_from_template('filter_reactions/stars', [
            'contextid' => $contextid,
            'itemid' => $itemid,
            'canreact' => $canreact,
            'stars' => $stars,
            'average' => number_format($average, 1),
            'totalcount' => $totalcount,
            'hastotalcount' => $totalcount > 0,
        ]);
    }
}
