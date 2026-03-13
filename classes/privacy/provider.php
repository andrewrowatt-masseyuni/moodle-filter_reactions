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

namespace filter_reactions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for filter_reactions.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the types of data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('filter_reactions', [
            'contextid' => 'privacy:metadata:filter_reactions:contextid',
            'itemid' => 'privacy:metadata:filter_reactions:itemid',
            'type' => 'privacy:metadata:filter_reactions:type',
            'userid' => 'privacy:metadata:filter_reactions:userid',
            'response' => 'privacy:metadata:filter_reactions:response',
            'timecreated' => 'privacy:metadata:filter_reactions:timecreated',
            'timemodified' => 'privacy:metadata:filter_reactions:timemodified',
        ], 'privacy:metadata:filter_reactions');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT DISTINCT contextid FROM {filter_reactions} WHERE userid = :userid",
            ['userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $userlist->add_from_sql(
            'userid',
            '{filter_reactions}',
            'contextid = :contextid',
            ['contextid' => $context->id]
        );
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $records = $DB->get_records('filter_reactions', [
                'contextid' => $context->id,
                'userid' => $userid,
            ]);

            foreach ($records as $record) {
                $data = (object) [
                    'type' => $record->type,
                    'itemid' => $record->itemid,
                    'response' => $record->response,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];

                writer::with_context($context)->export_data(
                    [get_string('filtername', 'filter_reactions'), $record->itemid],
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records('filter_reactions', ['contextid' => $context->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('filter_reactions', [
                'contextid' => $context->id,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users' data for a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $inparams['contextid'] = $context->id;

        $DB->delete_records_select(
            'filter_reactions',
            "contextid = :contextid AND userid $insql",
            $inparams
        );
    }
}
