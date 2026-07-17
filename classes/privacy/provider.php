<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy provider for enrol_gapplya.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_gapplya\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider class for the enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('enrol_gapplya', [
            'userid'       => 'privacy:metadata:enrol_gapplya:userid',
            'applytext'    => 'privacy:metadata:enrol_gapplya:applytext',
            'json_data'    => 'privacy:metadata:enrol_gapplya:json_data',
            'status'       => 'privacy:metadata:enrol_gapplya:status',
            'timecreated'  => 'privacy:metadata:enrol_gapplya:timecreated',
            'timemodified' => 'privacy:metadata:enrol_gapplya:timemodified',
        ], 'privacy:metadata:enrol_gapplya');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course} co ON co.id = c.instanceid AND c.contextlevel = :contextcourse
                  JOIN {enrol} e ON e.courseid = co.id AND e.enrol = 'gapplya'
                  JOIN {enrol_gapplya} ega ON ega.instance = e.id
                 WHERE ega.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextcourse' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT ega.userid
                  FROM {enrol_gapplya} ega
                  JOIN {enrol} e ON e.id = ega.instance
                 WHERE e.courseid = :courseid AND e.enrol = 'gapplya'";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT ega.*, c.id as contextid
                  FROM {enrol_gapplya} ega
                  JOIN {enrol} e ON e.id = ega.instance
                  JOIN {context} c ON c.instanceid = e.courseid AND c.contextlevel = :contextcourse
                 WHERE ega.userid = :userid AND c.id $contextsql";

        $params = array_merge(['userid' => $userid, 'contextcourse' => CONTEXT_COURSE], $contextparams);
        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $context = \context::instance_by_id($record->contextid);
            $data = (object)[
                'status' => $record->status,
                'applytext' => format_text($record->applytext, FORMAT_HTML),
                'json_data' => json_decode($record->json_data),
                'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'enrol_gapplya')], $data);

            // Export attached files.
            $itemid = (int) ($record->instance . $userid);
            writer::with_context($context)->export_area_files(
                $context->id,
                'enrol_gapplya',
                'applyfile',
                $itemid
            );
        }
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $instances = $DB->get_records('enrol', ['courseid' => $context->instanceid, 'enrol' => 'gapplya'], '', 'id');
        if (empty($instances)) {
            return;
        }
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($instances));

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile');

        $DB->delete_records_select('enrol_gapplya', "instance $insql", $inparams);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $instances = $DB->get_records('enrol', ['courseid' => $context->instanceid, 'enrol' => 'gapplya'], '', 'id');
            if (empty($instances)) {
                continue;
            }
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($instances));
            $params = array_merge($inparams, [$userid]);

            $records = $DB->get_records_select('enrol_gapplya', "instance $insql AND userid = ?", $params);
            $fs = get_file_storage();
            foreach ($records as $record) {
                $itemid = (int) ($record->instance . $userid);
                $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile', $itemid);
            }

            $DB->delete_records_select('enrol_gapplya', "instance $insql AND userid = ?", $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        $instances = $DB->get_records('enrol', ['courseid' => $context->instanceid, 'enrol' => 'gapplya'], '', 'id');
        if (empty($instances)) {
            return;
        }
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($instances));
        list($usersql, $userparams) = $DB->get_in_or_equal($userids);

        $params = array_merge($inparams, $userparams);

        $records = $DB->get_records_select('enrol_gapplya', "instance $insql AND userid $usersql", $params);
        $fs = get_file_storage();
        foreach ($records as $record) {
            $itemid = (int) ($record->instance . $record->userid);
            $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile', $itemid);
        }

        $DB->delete_records_select('enrol_gapplya', "instance $insql AND userid $usersql", $params);
    }
}
