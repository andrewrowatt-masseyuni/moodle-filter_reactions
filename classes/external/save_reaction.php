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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External function to save a reaction.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_reaction extends external_api {

    /** @var array Valid responses per type. */
    private const VALID_RESPONSES = [
        'thumbs' => ['thumbsup', 'thumbsdown'],
        'stars' => ['1star', '2stars', '3stars', '4stars', '5stars'],
    ];

    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context ID'),
            'itemid' => new external_value(PARAM_ALPHANUMEXT, 'Item identifier'),
            'type' => new external_value(PARAM_ALPHA, 'Reaction type: thumbs or stars'),
            'response' => new external_value(PARAM_ALPHANUMEXT, 'User response'),
        ]);
    }

    /**
     * Save or toggle a reaction and return updated state.
     *
     * @param int $contextid
     * @param string $itemid
     * @param string $type
     * @param string $response
     * @return array
     */
    public static function execute(int $contextid, string $itemid, string $type, string $response): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'itemid' => $itemid,
            'type' => $type,
            'response' => $response,
        ]);

        // Validate type and response.
        if (!isset(self::VALID_RESPONSES[$params['type']])) {
            throw new \invalid_parameter_exception('Invalid reaction type: ' . $params['type']);
        }
        if (!in_array($params['response'], self::VALID_RESPONSES[$params['type']])) {
            throw new \invalid_parameter_exception('Invalid response for type ' . $params['type']);
        }

        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);

        $now = time();
        $conditions = [
            'contextid' => $params['contextid'],
            'itemid' => $params['itemid'],
            'type' => $params['type'],
            'userid' => $USER->id,
        ];

        $existing = $DB->get_record('filter_reactions', $conditions);

        if ($existing) {
            if ($existing->response === $params['response']) {
                // Same response: toggle off (remove).
                $DB->delete_records('filter_reactions', ['id' => $existing->id]);
            } else {
                // Different response: update.
                $existing->response = $params['response'];
                $existing->timemodified = $now;
                $DB->update_record('filter_reactions', $existing);
            }
        } else {
            // New reaction.
            $record = (object) array_merge($conditions, [
                'response' => $params['response'],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record('filter_reactions', $record);
        }

        // Build updated counts.
        $allreactions = $DB->get_records('filter_reactions', [
            'contextid' => $params['contextid'],
            'itemid' => $params['itemid'],
            'type' => $params['type'],
        ]);

        $counts = [];
        foreach ($allreactions as $r) {
            if (!isset($counts[$r->response])) {
                $counts[$r->response] = 0;
            }
            $counts[$r->response]++;
        }

        $countdata = [];
        foreach ($counts as $resp => $count) {
            $countdata[] = ['response' => $resp, 'count' => $count];
        }

        // Get current user's response after the operation.
        $userrecord = $DB->get_record('filter_reactions', $conditions);
        $userresponse = $userrecord ? $userrecord->response : '';

        return [
            'userresponse' => $userresponse,
            'counts' => $countdata,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userresponse' => new external_value(PARAM_ALPHANUMEXT, 'Current user response or empty'),
            'counts' => new external_multiple_structure(
                new external_single_structure([
                    'response' => new external_value(PARAM_ALPHANUMEXT, 'Response value'),
                    'count' => new external_value(PARAM_INT, 'Number of users'),
                ])
            ),
        ]);
    }
}
