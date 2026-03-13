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
 * Tests for the reactions text filter.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_reactions\text_filter
 */
class text_filter_test extends \advanced_testcase {

    /** @var text_filter */
    private text_filter $filter;

    /** @var \context_system */
    private \context_system $context;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->context = \context_system::instance();
        $this->filter = new text_filter($this->context, []);
    }

    /**
     * Test that text without reaction tags is returned unchanged.
     */
    public function test_filter_no_tags(): void {
        $text = '<p>This is some normal text with no reaction tags.</p>';
        $this->assertEquals($text, $this->filter->filter($text));
    }

    /**
     * Test that text with an unrelated brace pattern is returned unchanged.
     */
    public function test_filter_unrelated_braces(): void {
        $text = '{something:else} and {reactions without colon}';
        $this->assertEquals($text, $this->filter->filter($text));
    }

    /**
     * Test that {reactions:thumbs} is replaced with rendered HTML.
     */
    public function test_filter_thumbs_tag(): void {
        $text = '<p>Rate this: {reactions:thumbs}</p>';
        $result = $this->filter->filter($text);

        $this->assertStringNotContainsString('{reactions:thumbs}', $result);
        $this->assertStringContainsString('filter-reactions', $result);
    }

    /**
     * Test that {reactions:stars} is replaced with rendered HTML.
     */
    public function test_filter_stars_tag(): void {
        $text = '<p>Rate this: {reactions:stars}</p>';
        $result = $this->filter->filter($text);

        $this->assertStringNotContainsString('{reactions:stars}', $result);
        $this->assertStringContainsString('filter-reactions', $result);
    }

    /**
     * Test that {reactions:thumbs,itemid} with a custom item ID works.
     */
    public function test_filter_thumbs_with_itemid(): void {
        $text = '{reactions:thumbs,myitem1}';
        $result = $this->filter->filter($text);

        $this->assertStringNotContainsString('{reactions:thumbs,myitem1}', $result);
        $this->assertStringContainsString('data-itemid="myitem1"', $result);
    }

    /**
     * Test that {reactions:stars,itemid} with a custom item ID works.
     */
    public function test_filter_stars_with_itemid(): void {
        $text = '{reactions:stars,rating1}';
        $result = $this->filter->filter($text);

        $this->assertStringNotContainsString('{reactions:stars,rating1}', $result);
        $this->assertStringContainsString('data-itemid="rating1"', $result);
    }

    /**
     * Test that invalid reaction types are not replaced.
     */
    public function test_filter_invalid_type(): void {
        $text = '{reactions:emoji}';
        $this->assertEquals($text, $this->filter->filter($text));
    }

    /**
     * Test multiple reaction tags in the same text.
     */
    public function test_filter_multiple_tags(): void {
        $text = '<p>{reactions:thumbs,a} and {reactions:stars,b}</p>';
        $result = $this->filter->filter($text);

        $this->assertStringNotContainsString('{reactions:', $result);
        $this->assertStringContainsString('data-itemid="a"', $result);
        $this->assertStringContainsString('data-itemid="b"', $result);
    }

    /**
     * Test that thumbs widget renders up/down buttons.
     */
    public function test_thumbs_widget_structure(): void {
        $text = '{reactions:thumbs}';
        $result = $this->filter->filter($text);

        $this->assertStringContainsString('data-type="thumbs"', $result);
        $this->assertStringContainsString('data-response="thumbsup"', $result);
        $this->assertStringContainsString('data-response="thumbsdown"', $result);
    }

    /**
     * Test that stars widget renders 5 star buttons.
     */
    public function test_stars_widget_structure(): void {
        $text = '{reactions:stars}';
        $result = $this->filter->filter($text);

        $this->assertStringContainsString('data-type="stars"', $result);
        $this->assertStringContainsString('data-response="1star"', $result);
        $this->assertStringContainsString('data-response="2stars"', $result);
        $this->assertStringContainsString('data-response="5stars"', $result);
    }

    /**
     * Test that guest users see disabled widgets.
     */
    public function test_filter_guest_user(): void {
        $this->setGuestUser();
        $text = '{reactions:thumbs}';
        $result = $this->filter->filter($text);

        $this->assertStringContainsString('disabled', $result);
    }

    /**
     * Test that logged-in users see enabled widgets.
     */
    public function test_filter_logged_in_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $text = '{reactions:thumbs}';
        $result = $this->filter->filter($text);

        // Should not have disabled attribute on react buttons.
        $this->assertStringContainsString('data-action="react"', $result);
    }

    /**
     * Test that a user's existing reaction is reflected in the rendered widget.
     */
    public function test_filter_shows_existing_user_reaction(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $contextid = $this->context->id;
        $itemid = 'defaultfor' . $contextid;

        $DB->insert_record('filter_reactions', (object) [
            'contextid' => $contextid,
            'itemid' => $itemid,
            'type' => 'thumbs',
            'userid' => $user->id,
            'response' => 'thumbsup',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $text = '{reactions:thumbs}';
        $result = $this->filter->filter($text);

        // The thumbsup button should be marked as selected/pressed.
        $this->assertMatchesRegularExpression('/thumbsup.*aria-pressed="true"/s', $result);
    }

    /**
     * Test that reaction counts are displayed in the widget.
     */
    public function test_filter_shows_reaction_counts(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $contextid = $this->context->id;
        $itemid = 'defaultfor' . $contextid;

        // Two users thumbsup.
        foreach ([$user1, $user2] as $u) {
            $DB->insert_record('filter_reactions', (object) [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'type' => 'thumbs',
                'userid' => $u->id,
                'response' => 'thumbsup',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $text = '{reactions:thumbs}';
        $result = $this->filter->filter($text);

        // Should show count of 2.
        $this->assertStringContainsString('>2<', $result);
    }
}
