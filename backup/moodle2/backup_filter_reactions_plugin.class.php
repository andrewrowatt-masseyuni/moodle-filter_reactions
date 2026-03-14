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
require_once($CFG->dirroot . '/backup/moodle2/backup_filter_plugin.class.php');

/**
 * Backup plugin for filter_reactions.
 *
 * Backs up all reaction data (thumbs/stars) for course module contexts
 * within the course being backed up.
 *
 * @package    filter_reactions
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_filter_reactions_plugin extends backup_filter_plugin {

    /**
     * Define course plugin structure.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $reaction = new backup_nested_element('reaction', ['id'], [
            'contextid', 'cmid', 'itemid', 'type', 'userid', 'response',
            'timecreated', 'timemodified',
        ]);

        $wrapper->add_child($reaction);
        $plugin->add_child($wrapper);

        // Get all reactions for module contexts in this course.
        // CONTEXT_MODULE = 70.
        $reaction->set_source_sql(
            'SELECT fr.id, fr.contextid, cm.id AS cmid, fr.itemid, fr.type,
                    fr.userid, fr.response, fr.timecreated, fr.timemodified
               FROM {filter_reactions} fr
               JOIN {context} ctx ON ctx.id = fr.contextid AND ctx.contextlevel = 70
               JOIN {course_modules} cm ON cm.id = ctx.instanceid
              WHERE cm.course = ?',
            [backup::VAR_COURSEID]
        );

        $reaction->annotate_ids('user', 'userid');

        return $plugin;
    }
}
