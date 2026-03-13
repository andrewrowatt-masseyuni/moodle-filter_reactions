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

namespace filter_reactions\external;

/**
 * Tests for the save_reaction external function.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_reactions\external\save_reaction
 */
class save_reaction_test extends \advanced_testcase {

    /** @var \stdClass */
    private \stdClass $user;

    /** @var int */
    private int $contextid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
        $this->contextid = \context_system::instance()->id;
    }

    /**
     * Test creating a new thumbsup reaction.
     */
    public function test_save_new_thumbsup(): void {
        $result = save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        $this->assertEquals('thumbsup', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('thumbsup', $result['counts'][0]['response']);
        $this->assertEquals(1, $result['counts'][0]['count']);
    }

    /**
     * Test creating a new star rating.
     */
    public function test_save_new_star_rating(): void {
        $result = save_reaction::execute($this->contextid, 'item1', 'stars', '3stars');

        $this->assertEquals('3stars', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('3stars', $result['counts'][0]['response']);
        $this->assertEquals(1, $result['counts'][0]['count']);
    }

    /**
     * Test toggling off a reaction (same response again removes it).
     */
    public function test_toggle_off_reaction(): void {
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');
        $result = save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        $this->assertEquals('', $result['userresponse']);
        $this->assertCount(0, $result['counts']);
    }

    /**
     * Test switching reaction (e.g. thumbsup to thumbsdown).
     */
    public function test_switch_reaction(): void {
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');
        $result = save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsdown');

        $this->assertEquals('thumbsdown', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('thumbsdown', $result['counts'][0]['response']);
    }

    /**
     * Test switching star rating.
     */
    public function test_switch_star_rating(): void {
        save_reaction::execute($this->contextid, 'item1', 'stars', '2stars');
        $result = save_reaction::execute($this->contextid, 'item1', 'stars', '5stars');

        $this->assertEquals('5stars', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('5stars', $result['counts'][0]['response']);
    }

    /**
     * Test that reactions from multiple users are counted correctly.
     */
    public function test_multiple_users_counts(): void {
        $user2 = $this->getDataGenerator()->create_user();

        // User 1 thumbsup.
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        // User 2 thumbsup.
        $this->setUser($user2);
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        // User 2 also thumbsdown on different item.
        $result = save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        // User 2 toggled off their thumbsup, so only user 1 remains.
        $this->assertEquals('', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals(1, $result['counts'][0]['count']);
    }

    /**
     * Test that reactions are scoped to item IDs.
     */
    public function test_reactions_scoped_to_itemid(): void {
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');
        $result = save_reaction::execute($this->contextid, 'item2', 'thumbs', 'thumbsdown');

        // item2 should only have its own reaction.
        $this->assertEquals('thumbsdown', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('thumbsdown', $result['counts'][0]['response']);
    }

    /**
     * Test that reactions are scoped to types.
     */
    public function test_reactions_scoped_to_type(): void {
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');
        $result = save_reaction::execute($this->contextid, 'item1', 'stars', '4stars');

        // Stars result should only have star data.
        $this->assertEquals('4stars', $result['userresponse']);
        $this->assertCount(1, $result['counts']);
        $this->assertEquals('4stars', $result['counts'][0]['response']);
    }

    /**
     * Test invalid reaction type throws exception.
     */
    public function test_invalid_type(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_reaction::execute($this->contextid, 'item1', 'emoji', 'smile');
    }

    /**
     * Test invalid response for thumbs type throws exception.
     */
    public function test_invalid_response_for_thumbs(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_reaction::execute($this->contextid, 'item1', 'thumbs', '3stars');
    }

    /**
     * Test invalid response for stars type throws exception.
     */
    public function test_invalid_response_for_stars(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_reaction::execute($this->contextid, 'item1', 'stars', 'thumbsup');
    }

    /**
     * Test that database records are created correctly.
     */
    public function test_database_record_fields(): void {
        global $DB;

        $before = time();
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');
        $after = time();

        $record = $DB->get_record('filter_reactions', [
            'contextid' => $this->contextid,
            'itemid' => 'item1',
            'type' => 'thumbs',
            'userid' => $this->user->id,
        ]);

        $this->assertNotEmpty($record);
        $this->assertEquals('thumbsup', $record->response);
        $this->assertGreaterThanOrEqual($before, $record->timecreated);
        $this->assertLessThanOrEqual($after, $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);
    }

    /**
     * Test that switching reaction updates timemodified.
     */
    public function test_switch_updates_timemodified(): void {
        global $DB;

        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        $record1 = $DB->get_record('filter_reactions', [
            'contextid' => $this->contextid,
            'itemid' => 'item1',
            'userid' => $this->user->id,
        ]);

        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsdown');

        $record2 = $DB->get_record('filter_reactions', [
            'contextid' => $this->contextid,
            'itemid' => 'item1',
            'userid' => $this->user->id,
        ]);

        $this->assertEquals($record1->id, $record2->id);
        $this->assertEquals('thumbsdown', $record2->response);
        $this->assertGreaterThanOrEqual($record1->timemodified, $record2->timemodified);
    }

    /**
     * Test multiple users with mixed reactions returns correct counts.
     */
    public function test_mixed_reactions_counts(): void {
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // User 1: thumbsup.
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        // User 2: thumbsup.
        $this->setUser($user2);
        save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsup');

        // User 3: thumbsdown.
        $this->setUser($user3);
        $result = save_reaction::execute($this->contextid, 'item1', 'thumbs', 'thumbsdown');

        $this->assertEquals('thumbsdown', $result['userresponse']);

        // Should have 2 counts entries: thumbsup=2, thumbsdown=1.
        $counts = [];
        foreach ($result['counts'] as $c) {
            $counts[$c['response']] = $c['count'];
        }
        $this->assertEquals(2, $counts['thumbsup']);
        $this->assertEquals(1, $counts['thumbsdown']);
    }
}
