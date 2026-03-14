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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/backup/moodle2/restore_filter_plugin.class.php');

/**
 * Restore plugin for filter_reactions.
 *
 * Restores reaction data with remapped context IDs, user IDs, and item IDs.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_filter_reactions_plugin extends restore_filter_plugin {

    /** @var array Cached reaction data for processing after restore. */
    protected $reactions = [];

    /**
     * Define course plugin structure for restore.
     *
     * @return array Array of restore_path_element.
     */
    protected function define_course_plugin_structure(): array {
        return [
            new restore_path_element('reaction', $this->get_pathfor('/reaction')),
        ];
    }

    /**
     * Process a single reaction record from backup XML.
     *
     * Caches the data for processing in after_restore_course() when
     * all course module mappings are available.
     *
     * @param array|object $data Reaction data from backup.
     */
    public function process_reaction($data): void {
        $this->reactions[] = (object) $data;
    }

    /**
     * Process cached reaction data after course restoration.
     *
     * Remaps course module IDs to new context IDs and updates item IDs
     * that contain embedded context references.
     */
    public function after_restore_course(): void {
        global $DB;

        foreach ($this->reactions as $data) {
            $oldcmid = $data->cmid;
            $oldcontextid = $data->contextid;

            // Remap course module ID.
            $newcmid = $this->get_mappingid('course_module', $oldcmid);
            if (!$newcmid) {
                continue;
            }

            // Get the new context for the restored course module.
            $newcontext = \context_module::instance($newcmid, IGNORE_MISSING);
            if (!$newcontext) {
                continue;
            }
            $newcontextid = $newcontext->id;

            // Remap user ID.
            $newuserid = $this->get_mappingid('user', $data->userid);
            if (!$newuserid) {
                continue;
            }

            // Remap item ID if it contains the old context ID.
            $itemid = $data->itemid;
            if (str_contains($itemid, (string) $oldcontextid)) {
                $itemid = str_replace((string) $oldcontextid, (string) $newcontextid, $itemid);
            }

            // Check for duplicate (same user, same item, same type in new context).
            $exists = $DB->record_exists('filter_reactions', [
                'contextid' => $newcontextid,
                'itemid' => $itemid,
                'type' => $data->type,
                'userid' => $newuserid,
            ]);
            if ($exists) {
                continue;
            }

            $record = new \stdClass();
            $record->contextid = $newcontextid;
            $record->itemid = $itemid;
            $record->type = $data->type;
            $record->userid = $newuserid;
            $record->response = $data->response;
            $record->timecreated = $data->timecreated;
            $record->timemodified = $data->timemodified;

            $DB->insert_record('filter_reactions', $record);
        }
    }
}
