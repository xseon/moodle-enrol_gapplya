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
 * External API for enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_gapplya\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_multiple_structure;
use context_course;
use stdClass;
use Exception;

global $CFG;
require_once($CFG->dirroot . '/enrol/gapplya/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * External API class for enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends external_api {
    /**
     * Parameters for withdraw.
     *
     * @return external_function_parameters
     */
    public static function withdraw_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The instance ID'),
        ]);
    }

    /**
     * Withdraw application.
     *
     * @param int $instanceid
     * @return string
     */
    public static function withdraw($instanceid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::withdraw_parameters(), ['instanceid' => $instanceid]);
        $instanceid = $params['instanceid'];

        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);

        $record = $DB->get_record(
            'enrol_gapplya',
            ['instance' => $instanceid, 'userid' => $USER->id],
            '*',
            MUST_EXIST
        );

        if (empty($instance->customchar3) || $record->status !== 'new') {
            throw new \moodle_exception('cannotwithdraw', 'enrol_gapplya');
        }

        $update = new stdClass();
        $update->id = $record->id;
        $update->status = 'withdrawn';
        $update->timemodified = time();
        $DB->update_record('enrol_gapplya', $update);

        $sm = get_string_manager();
        $oldstatusstr = $sm->string_exists($record->status, 'enrol_gapplya') ?
            get_string($record->status, 'enrol_gapplya') : $record->status;
        $newstatusstr = $sm->string_exists('withdrawn', 'enrol_gapplya') ?
            get_string('withdrawn', 'enrol_gapplya') : 'withdrawn';

        $a = new stdClass();
        $a->old = $oldstatusstr;
        $a->new = $newstatusstr;
        $details = get_string('log_status_changed', 'enrol_gapplya', $a);

        \enrol_gapplya\util::add_history($record->id, 'withdrawn', $USER->id, $details);

        return 'success';
    }

    /**
     * Returns for withdraw.
     *
     * @return external_value
     */
    public static function withdraw_returns() {
        return new external_value(PARAM_ALPHA, 'Status string');
    }

    /**
     * Parameters for execute_action.
     *
     * @return external_function_parameters
     */
    public static function execute_action_parameters() {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'Action to perform'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
            'ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Application ID')),
            'roleid' => new external_value(PARAM_INT, 'Role ID', VALUE_DEFAULT, 0),
            'start' => new external_value(PARAM_INT, 'Start time', VALUE_DEFAULT, 0),
            'end' => new external_value(PARAM_INT, 'End time', VALUE_DEFAULT, 0),
            'groups' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Group ID'),
                'Groups',
                VALUE_DEFAULT,
                []
            ),
            'notify' => new external_value(PARAM_INT, 'Notify user', VALUE_DEFAULT, 1),
            'messagetext' => new external_value(PARAM_CLEANHTML, 'Message text', VALUE_DEFAULT, ''),
            'messagesubject' => new external_value(PARAM_TEXT, 'Message subject', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute bulk action.
     *
     * @param string $action The action to execute.
     * @param int $instanceid The instance ID.
     * @param array $ids The list of application IDs.
     * @param int $roleid The role ID to assign.
     * @param int $start The start date.
     * @param int $end The end date.
     * @param array $groups The group IDs.
     * @param int $notify Whether to notify the user.
     * @param string $messagetext The message text.
     * @param string $messagesubject The message subject.
     * @return string
     */
    public static function execute_action($action, $instanceid, $ids, $roleid, $start, $end,
            $groups, $notify, $messagetext, $messagesubject) {
        global $DB, $USER, $SESSION;
        $params = self::validate_parameters(self::execute_action_parameters(), [
            'action' => $action,
            'instanceid' => $instanceid,
            'ids' => $ids,
            'roleid' => $roleid,
            'start' => $start,
            'end' => $end,
            'groups' => $groups,
            'notify' => $notify,
            'messagetext' => $messagetext,
            'messagesubject' => $messagesubject,
        ]);

        if (empty($params['ids'])) {
            return 'error';
        }

        $instance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);
        require_capability('enrol/gapplya:manage', $context);

        $enrol = enrol_get_plugin('gapplya');
        $course = $DB->get_record('course', ['id' => $instance->courseid]);
        $idsarr = $params['ids'];

        if ($params['action'] == 'delete') {
            $transaction = $DB->start_delegated_transaction();
            try {
                list($insql, $inparams) = $DB->get_in_or_equal($idsarr);
                $userids = $DB->get_fieldset_select('enrol_gapplya', 'userid', "id $insql", $inparams);
                $DB->delete_records_list('enrol_gapplya', 'id', $idsarr);

                $fs = get_file_storage();
                foreach ($userids as $uid) {
                    $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile', (int)($instance->id . $uid));
                }
                $transaction->allow_commit();
                return 'success';
            } catch (Exception $e) {
                $transaction->rollback($e);
                return 'error';
            }
        }

        $records = $DB->get_records_list('enrol_gapplya', 'id', $idsarr);
        $msgtype = ($params['action'] == 'approve') ? 'applicationapproved' : 'application' . $params['action'];
        $currentlang = current_language();

        $userids = array_map(function($r) {
            return $r->userid;
        }, $records);
        $users = empty($userids) ? [] : $DB->get_records_list('user', 'id', $userids);

        $transaction = $DB->start_delegated_transaction();

        try {
            foreach ($records as $record) {
                if (!isset($users[$record->userid])) {
                    continue;
                }
                $user = $users[$record->userid];
                $history = $record->history ? json_decode($record->history, true) : [];

                if ($params['action'] === 'sendmessage') {
                    if (empty(trim($params['messagetext']))) {
                        continue;
                    }
                    $defaultsubject = get_string('sendmessage', 'enrol_gapplya');
                    $finalsubject = !empty(trim($params['messagesubject'])) ?
                        trim($params['messagesubject']) : $defaultsubject;

                    $message = new stdClass();
                    $message->contexturl = new \moodle_url('/course/view.php', ['id' => $course->id]);
                    $message->subject = $finalsubject;
                    $message->text = $params['messagetext'];
                    $message->contexturlname = get_string('viewcourse', 'enrol_gapplya');

                    \enrol_gapplya\util::send_notification($user, $USER, $message);

                    $historydetails = get_string('subject', 'core') . ": " . $finalsubject . "\n\n" .
                        strip_tags($params['messagetext']);
                    $history[] = [
                        'time' => time(),
                        'userid' => $USER->id,
                        'action' => 'sendmessage',
                        'details' => $historydetails,
                    ];
                    $record->history = json_encode($history);
                    $DB->update_record('enrol_gapplya', $record);
                    continue;
                }

                if ($params['action'] == 'approve') {
                    $enrol->enrol_user(
                        $instance,
                        $record->userid,
                        $params['roleid'],
                        $params['start'],
                        $params['end']
                    );
                    if (!empty($params['groups'])) {
                        foreach ($params['groups'] as $groupid) {
                            groups_add_member($groupid, $record->userid);
                        }
                    }
                }

                if ($params['notify']) {
                    $oldlang = null;
                    if (get_config('enrol_gapplya', 'sendnotificationinrecipientlang')) {
                        $oldlang = $SESSION->lang;
                        $SESSION->lang = $user->lang;
                    }

                    $message = new stdClass();
                    $message->contexturl = new \moodle_url('/course/view.php', ['id' => $instance->courseid]);
                    $message->subject = get_string(
                        'custommsgsubject',
                        'enrol_gapplya',
                        format_text($course->fullname, FORMAT_HTML)
                    );
                    $message->text = get_string(
                        $msgtype,
                        'enrol_gapplya',
                        format_text($course->fullname, FORMAT_HTML)
                    );
                    $message->contexturlname = get_string('viewcourse', 'enrol_gapplya');

                    \enrol_gapplya\util::send_notification($user, $USER, $message);

                    if ($oldlang !== null) {
                        $SESSION->lang = $oldlang;
                    }
                }

                $histaction = 'waitlisted';
                if ($params['action'] == 'approve') {
                    $histaction = 'approved';
                } else if ($params['action'] == 'reject') {
                    $histaction = 'rejected';
                } else if ($params['action'] == 'withdraw') {
                    $histaction = 'withdrawn';
                }

                $sm = get_string_manager();
                $oldstatusstr = $sm->string_exists($record->status, 'enrol_gapplya') ?
                    get_string($record->status, 'enrol_gapplya') : $record->status;
                $newstatusstr = $sm->string_exists($histaction, 'enrol_gapplya') ?
                    get_string($histaction, 'enrol_gapplya') : $histaction;

                $a = new stdClass();
                $a->old = $oldstatusstr;
                $a->new = $newstatusstr;
                $details = get_string('log_status_changed', 'enrol_gapplya', $a);
                if ($params['notify']) {
                    $details .= "\n\n" . get_string('notified_yes', 'enrol_gapplya');
                } else {
                    $details .= "\n\n" . get_string('notified_no', 'enrol_gapplya');
                }

                \enrol_gapplya\util::add_history($record->id, $histaction, $USER->id, $details);
            }

            $SESSION->lang = $currentlang;

            if ($params['action'] !== 'sendmessage') {
                $newstatus = 'waitlisted';
                if ($params['action'] == 'approve') {
                    $newstatus = 'approved';
                } else if ($params['action'] == 'reject') {
                    $newstatus = 'rejected';
                } else if ($params['action'] == 'withdraw') {
                    $newstatus = 'withdrawn';
                }

                list($insql, $inparams) = $DB->get_in_or_equal($idsarr);
                $DB->set_field_select('enrol_gapplya', 'status', $newstatus, "id $insql", $inparams);
            }

            $transaction->allow_commit();
            return 'success';
        } catch (Exception $e) {
            $transaction->rollback($e);
            return 'error';
        }
    }

    /**
     * Returns for execute_action.
     *
     * @return external_value
     */
    public static function execute_action_returns() {
        return new external_value(PARAM_ALPHA, 'Status string');
    }

    /**
     * Parameters for save_data.
     *
     * @return external_function_parameters
     */
    public static function save_data_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
            'recordid' => new external_value(PARAM_INT, 'Record ID'),
            'adminnote' => new external_value(PARAM_TEXT, 'Admin note', VALUE_DEFAULT, ''),
            'formdata' => new external_value(PARAM_RAW, 'JSON encoded form data', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Save application data.
     *
     * @param int $instanceid
     * @param int $recordid
     * @param string $adminnote
     * @param string $formdata
     * @return string
     */
    public static function save_data($instanceid, $recordid, $adminnote, $formdata) {
        global $DB, $USER;
        $params = self::validate_parameters(self::save_data_parameters(), [
            'instanceid' => $instanceid,
            'recordid' => $recordid,
            'adminnote' => $adminnote,
            'formdata' => $formdata,
        ]);

        $instance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);
        require_capability('enrol/gapplya:manage', $context);

        $record = $DB->get_record('enrol_gapplya', ['id' => $params['recordid']], '*', MUST_EXIST);
        $oldjson = !empty($record->json_data) ? json_decode($record->json_data, true) : [];
        $oldnote = $record->adminnote;

        $record->adminnote = $params['adminnote'];
        $newjson = $oldjson;
        if (!is_array($newjson)) {
            $newjson = [];
        }

        $rawformdata = json_decode($params['formdata'], true);
        if (is_array($rawformdata)) {
            foreach ($rawformdata as $key => $val) {
                $cleankey = clean_param(str_replace('edit_', '', $key), PARAM_TEXT);
                $newjson[$cleankey] = clean_param($val, PARAM_TEXT);
            }
        }

        $schema = \enrol_gapplya\util::get_application_schema($instance);
        $record->json_data = json_encode($newjson);

        if ($DB->update_record('enrol_gapplya', $record)) {
            $changesdetails = [];
            if (trim((string)$oldnote) !== trim((string)$params['adminnote'])) {
                $a = new stdClass();
                $a->old = $oldnote === '' ? '-' : $oldnote;
                $a->new = $params['adminnote'] === '' ? '-' : $params['adminnote'];
                $changesdetails[] = get_string('log_note_changed', 'enrol_gapplya', $a);
            }

            foreach ($schema as $field) {
                if ($field['type'] == 'header') {
                    continue;
                }
                $key = $field['name'];
                $label = $field['label'];
                $oldval = isset($oldjson[$key]) ? $oldjson[$key] : (isset($oldjson[$label]) ? $oldjson[$label] : '');
                $newval = isset($newjson[$key]) ? $newjson[$key] : '';

                if (trim((string)$oldval) !== trim((string)$newval)) {
                    $displayold = $oldval;
                    $displaynew = $newval;

                    if ($field['type'] == 'checkbox') {
                        $oldch = ($oldval === 'yes' || $oldval === '1' || $oldval === 'on');
                        $displayold = $oldch ? get_string('yes', 'core') : get_string('no', 'core');
                        $newch = ($newval === 'yes' || $newval === '1' || $newval === 'on');
                        $displaynew = $newch ? get_string('yes', 'core') : get_string('no', 'core');
                    }
                    if ($field['type'] == 'select') {
                        if (isset($field['options'][$oldval])) {
                            $displayold = $field['options'][$oldval];
                        }
                        if (isset($field['options'][$newval])) {
                            $displaynew = $field['options'][$newval];
                        }
                    }

                    $a = new stdClass();
                    $a->field = $label;
                    $a->old = $displayold === '' ? '-' : $displayold;
                    $a->new = $displaynew === '' ? '-' : $displaynew;
                    $changesdetails[] = get_string('log_data_changed', 'enrol_gapplya', $a);
                }
            }

            if (!empty($changesdetails)) {
                $history = !empty($record->history) ? json_decode($record->history, true) : [];
                $history[] = [
                    'time' => time(),
                    'action' => 'edited',
                    'userid' => $USER->id,
                    'details' => implode("\n\n", $changesdetails),
                ];
                $DB->set_field('enrol_gapplya', 'history', json_encode($history), ['id' => $record->id]);
            }
            return 'success';
        }
        return 'error';
    }

    /**
     * Returns for save_data.
     *
     * @return external_value
     */
    public static function save_data_returns() {
        return new external_value(PARAM_ALPHA, 'Status string');
    }

    // Auxiliary Data endpoints.

    /**
     * Parameters for get_groups.
     *
     * @return external_function_parameters
     */
    public static function get_groups_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
        ]);
    }

    /**
     * Get groups.
     *
     * @param int $instanceid The instance ID.
     * @return string JSON encoded string.
     */
    public static function get_groups($instanceid) {
        global $DB;
        $params = self::validate_parameters(self::get_groups_parameters(), ['instanceid' => $instanceid]);
        $instance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);
        self::validate_context(context_course::instance($instance->courseid));

        $groups = groups_get_all_groups($instance->courseid, 0, 0, 'g.id, g.name');
        $groupsdata = [];
        foreach ($groups as $group) {
            $groupsdata[] = ['id' => $group->id, 'name' => format_text($group->name, FORMAT_PLAIN)];
        }
        return json_encode($groupsdata);
    }

    /**
     * Returns for get_groups.
     *
     * @return external_value
     */
    public static function get_groups_returns() {
        return new external_value(PARAM_RAW, 'JSON encoded groups');
    }

    /**
     * Parameters for get_roles_and_dates.
     *
     * @return external_function_parameters
     */
    public static function get_roles_and_dates_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Instance ID'),
        ]);
    }

    /**
     * Get roles and dates.
     *
     * @param int $instanceid The instance ID.
     * @return string JSON encoded string.
     */
    public static function get_roles_and_dates($instanceid) {
        global $DB;
        $params = self::validate_parameters(self::get_roles_and_dates_parameters(), ['instanceid' => $instanceid]);
        $instance = $DB->get_record('enrol', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);
        self::validate_context($context);

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        $data = [
            'roles' => $roles,
            'defaultrole' => $instance->roleid,
            'startdate' => $instance->enrolstartdate,
            'enddate' => $instance->enrolenddate,
        ];
        return json_encode($data);
    }

    /**
     * Returns for get_roles_and_dates.
     *
     * @return external_value
     */
    public static function get_roles_and_dates_returns() {
        return new external_value(PARAM_RAW, 'JSON encoded roles and dates');
    }
}
