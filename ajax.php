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
 * AJAX handler for enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/gapplya/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_TEXT);
$id = required_param('id', PARAM_INT);

// Helper: Handle underscore modes.
$mode = optional_param('mode', '', PARAM_ALPHA);
if ($action == 'getuserbyid_edit') {
    $action = 'getuserbyid';
    $mode = 'edit';
}
if ($action == 'getuserbyid_history') {
    $action = 'getuserbyid';
    $mode = 'history';
}

$instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
$courseid = $instance->courseid;
$context = context_course::instance($courseid);
$PAGE->set_context($context);

// Permissions check (Global Gatekeeper).
if (!has_capability('enrol/gapplya:manage', $context) && $action !== 'withdraw') {
    die(json_encode(['error' => 'No permissions']));
}

$enrol = enrol_get_plugin('gapplya');

// 1. Save data.
if ($action == "savedata") {
    require_sesskey();
    require_capability('enrol/gapplya:manage', $context);
    while (ob_get_level()) {
        ob_end_clean();
    }

    $recordid = required_param('recordid', PARAM_INT);
    $adminnote = optional_param('adminnote', '', PARAM_TEXT);

    $record = $DB->get_record('enrol_gapplya', ['id' => $recordid], '*', MUST_EXIST);

    // Save old data for comparison.
    $oldjson = !empty($record->json_data) ? json_decode($record->json_data, true) : [];
    $oldnote = $record->adminnote;

    $record->adminnote = $adminnote;

    $newjson = $oldjson;
    if (!is_array($newjson)) {
        $newjson = [];
    }

    // Process edited fields.
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'edit_') === 0) {
            $fieldname = substr($key, 5);
            $newjson[$fieldname] = clean_param($value, PARAM_TEXT);
        }
    }

    // Process checkboxes.
    $schema = \enrol_gapplya\util::get_application_schema($instance);
    foreach ($schema as $field) {
        if ($field['type'] == 'checkbox') {
            $newjson[$field['name']] = isset($_POST['edit_' . $field['name']]) ? 'yes' : 'no';
        }
    }
    $record->json_data = json_encode($newjson);

    if ($DB->update_record('enrol_gapplya', $record)) {
        // History Logic.
        $changesdetails = []; // Initialize the array.
        if (trim((string)$oldnote) !== trim((string)$adminnote)) {
            $a = new stdClass();
            $a->old = $oldnote === '' ? '-' : $oldnote;
            $a->new = $adminnote === '' ? '-' : $adminnote;

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

        echo "success";
    } else {
        echo "error";
    }
    die;
} else if (in_array($action, ['approve', 'waitlist', 'reject', 'withdraw', 'delete', 'sendmessage']) &&
           optional_param('ids', null, PARAM_RAW_TRIMMED)) {
    // 2. Bulk actions.
    require_capability('enrol/gapplya:manage', $context);

    $ids = required_param('ids', PARAM_RAW_TRIMMED);
    $notify = optional_param('notify', 1, PARAM_INT);
    $messagetext = optional_param('messagetext', '', PARAM_CLEANHTML);
    $messagesubject = optional_param('messagesubject', '', PARAM_TEXT);
    $idsarr = array_map('intval', explode(',', $ids));
    $idsarr = array_filter($idsarr);

    if (empty($idsarr)) {
        echo 'error';
        die;
    }

    if ($action == 'delete') {
        $transaction = $DB->start_delegated_transaction();

        try {
            list($insql, $inparams) = $DB->get_in_or_equal($idsarr);
            $userids = $DB->get_fieldset_select('enrol_gapplya', 'userid', "id $insql", $inparams);
            $DB->delete_records_list('enrol_gapplya', 'id', $idsarr);

            $fs = get_file_storage();
            foreach ($userids as $uid) {
                $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile', (int)($id . $uid));
            }

            $transaction->allow_commit();
            echo 'success';
        } catch (Exception $e) {
            $transaction->rollback($e);
            echo 'error';
        }
        die;
    }

    $records = $DB->get_records_list('enrol_gapplya', 'id', $idsarr);
    $course = $DB->get_record('course', ['id' => $courseid]);
    $msgtype = ($action == 'approve') ? 'applicationapproved' : 'application' . $action;
    $currentlang = current_language();

    $transaction = $DB->start_delegated_transaction();

    try {
        foreach ($records as $record) {
            $user = $DB->get_record('user', ['id' => $record->userid]);
            $history = $record->history ? json_decode($record->history, true) : [];

            if ($action === 'sendmessage') {
                if (empty(trim($messagetext))) {
                    continue;
                }

                $defaultsubject = get_string('sendmessage', 'enrol_gapplya');
                $finalsubject = !empty(trim($messagesubject)) ? trim($messagesubject) : $defaultsubject;

                $message = new stdClass();
                $message->contexturl = new moodle_url('/course/view.php', ['id' => $course->id]);
                $message->subject = $finalsubject;
                $message->text = $messagetext;
                $message->contexturlname = get_string('viewcourse', 'enrol_gapplya');

                \enrol_gapplya\util::send_notification($user, $USER, $message);

                $historydetails = get_string('subject', 'core') . ": " . $finalsubject . "\n\n" . strip_tags($messagetext);

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

            if ($action == 'approve') {
                $roleid = required_param('roleid', PARAM_INT);
                $start = required_param('start', PARAM_INT);
                $end = required_param('end', PARAM_INT);
                $enrol->enrol_user($instance, $record->userid, $roleid, $start, $end);

                $groups = optional_param('groups', '', PARAM_TEXT);
                if ($groups != '') {
                    foreach (explode(',', $groups) as $groupid) {
                        groups_add_member($groupid, $record->userid);
                    }
                }
            }

            $user = $DB->get_record('user', ['id' => $record->userid]);

            if ($notify) {
                $oldlang = null;
                if (get_config('enrol_gapplya', 'sendnotificationinrecipientlang')) {
                    $oldlang = $SESSION->lang;
                    $SESSION->lang = $user->lang;
                }

                $message = new stdClass();
                $message->contexturl = new moodle_url('/course/view.php', ['id' => $courseid]);
                $message->subject = get_string('custommsgsubject', 'enrol_gapplya', format_text($course->fullname, FORMAT_HTML));
                $message->text = get_string($msgtype, 'enrol_gapplya', format_text($course->fullname, FORMAT_HTML));
                $message->contexturlname = get_string('viewcourse', 'enrol_gapplya');

                \enrol_gapplya\util::send_notification($user, $USER, $message);

                if ($oldlang !== null) {
                    $SESSION->lang = $oldlang;
                }
            }

            $histaction = 'waitlisted';
            if ($action == 'approve') {
                $histaction = 'approved';
            } else if ($action == 'reject') {
                $histaction = 'rejected';
            } else if ($action == 'withdraw') {
                $histaction = 'withdrawn';
            }

            $sm = get_string_manager();
            $oldstatusstr = $record->status;
            if ($sm->string_exists($record->status, 'enrol_gapplya')) {
                $oldstatusstr = get_string($record->status, 'enrol_gapplya');
            }
            $newstatusstr = $histaction;
            if ($sm->string_exists($histaction, 'enrol_gapplya')) {
                $newstatusstr = get_string($histaction, 'enrol_gapplya');
            }

            $a = new stdClass();
            $a->old = $oldstatusstr;
            $a->new = $newstatusstr;
            $details = get_string('log_status_changed', 'enrol_gapplya', $a);
            if (isset($notify)) {
                $details .= "\n\n";
                $details .= ($notify ? get_string('notified_yes', 'enrol_gapplya') : get_string('notified_no', 'enrol_gapplya'));
            }

            \enrol_gapplya\util::add_history($record->id, $histaction, $USER->id, $details);
        }

        $SESSION->lang = $currentlang;

        if ($action !== 'sendmessage') {
            $newstatus = 'waitlisted';
            if ($action == 'approve') {
                $newstatus = 'approved';
            } else if ($action == 'reject') {
                $newstatus = 'rejected';
            } else if ($action == 'withdraw') {
                $newstatus = 'withdrawn';
            }

            list($insql, $inparams) = $DB->get_in_or_equal($idsarr);
            $DB->set_field_select('enrol_gapplya', 'status', $newstatus, "id $insql", $inparams);
        }

        $transaction->allow_commit();
        echo 'success';
    } catch (Exception $e) {
        $transaction->rollback($e);
        echo 'error';
    }
    die;
} else if ($action == "withdraw") {
    // 3. Withdraw (Single User).
    // Note: No 'manage' check here is intentional, students can withdraw themselves.
    $record = $DB->get_record('enrol_gapplya', ['instance' => $id, 'userid' => $USER->id], '*', MUST_EXIST);

    if (empty($instance->customchar3) || $record->status !== 'new') {
        echo json_encode(['error' => get_string('cannotwithdraw', 'enrol_gapplya')]);
        die;
    }

    $update = new stdClass();
    $update->id = $record->id;
    $update->status = 'withdrawn';
    $update->timemodified = time();
    $DB->update_record('enrol_gapplya', $update);

    $sm = get_string_manager();
    $oldstatusstr = $record->status;
    if ($sm->string_exists($record->status, 'enrol_gapplya')) {
        $oldstatusstr = get_string($record->status, 'enrol_gapplya');
    }

    $newstatusstr = 'withdrawn';
    if ($sm->string_exists('withdrawn', 'enrol_gapplya')) {
        $newstatusstr = get_string('withdrawn', 'enrol_gapplya');
    }

    $a = new stdClass();
    $a->old = $oldstatusstr;
    $a->new = $newstatusstr;

    $details = get_string('log_status_changed', 'enrol_gapplya', $a);

    \enrol_gapplya\util::add_history($record->id, 'withdrawn', $USER->id, $details);

    echo 'success';
    die;
} else if ($action == "getuserbyid") {
    // 4. Get user details (Modal).
    require_capability('enrol/gapplya:manage', $context);

    $useridraw = required_param('userid', PARAM_TEXT);
    if (strpos($useridraw, '_edit') !== false) {
        $userid = (int)str_replace('_edit', '', $useridraw);
        $mode = 'edit';
    } else if (strpos($useridraw, '_history') !== false) {
        $userid = (int)str_replace('_history', '', $useridraw);
        $mode = 'history';
    } else {
        $userid = (int)$useridraw;
    }

    require_once($CFG->dirroot . '/user/profile/lib.php');
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $gapplyarecord = $DB->get_record('enrol_gapplya', ['instance' => $id, 'userid' => $userid]);

    // Always load profile fields for $user object.
    profile_load_custom_fields($user);

    $statushtml = '';
    if ($gapplyarecord) {
        $statusclass = 'badge-secondary';
        switch ($gapplyarecord->status) {
            case 'approved':
                $statusclass = 'badge-success';
                break;
            case 'rejected':
                $statusclass = 'badge-danger';
                break;
            case 'waitlisted':
                $statusclass = 'badge-info';
                break;
            case 'withdrawn':
                $statusclass = 'badge-secondary';
                break;
            default:
                $statusclass = 'badge-warning';
        }

        $sm = get_string_manager();
        $statusstr = $gapplyarecord->status;
        if ($sm->string_exists($gapplyarecord->status, 'enrol_gapplya')) {
            $statusstr = get_string($gapplyarecord->status, 'enrol_gapplya');
        }
        $statushtml = '<span class="badge ' . $statusclass . ' ml-2" ';
        $statushtml .= 'style="font-size: 0.6em; vertical-align: middle;">' . $statusstr . '</span>';
    }

    $html = '';
    // Header.
    $html .= '<div class="modal-header border-bottom-0 pb-0">';
    $html .= '<h4 class="modal-title text-truncate">' . get_string('application', 'enrol_gapplya') . '</h4>';
    $html .= '<div class="d-print-none">';

    $html .= '<a href="javascript:window.print()" class="btn btn-sm btn-light text-muted mr-2" ';
    $html .= 'title="' . get_string('print', 'enrol_gapplya') . '" data-toggle="tooltip" ';
    $html .= 'style="width: 35px; height: 35px; padding: 6px 0;"><i class="fa fa-print"></i></a>';

    $html .= '<button data-dismiss="modal" class="btn btn-sm btn-light text-muted" ';
    $html .= 'title="' . get_string('close', 'enrol_gapplya') . '" data-toggle="tooltip" ';
    $html .= 'style="width: 35px; height: 35px; padding: 6px 0;"><i class="fa fa-times"></i></button>';

    $html .= '</div></div>';

    // Sub-header.
    $html .= '<div class="bg-light border-bottom shadow-sm px-4 py-3 ';
    $html .= 'd-flex justify-content-between align-items-center mb-0">';
    $html .= '<div class="d-flex align-items-center">';
    if ($user->picture > 0) {
        $html .= $OUTPUT->user_picture($user, ['size' => 40, 'class' => 'mr-3 rounded-circle']);
    }
    $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);

    $html .= '<div>';
    $html .= '<h3 class="m-0 text-dark" style="font-weight: 300;">';
    $html .= '<a href="' . $profileurl . '" target="_blank" class="text-dark" title="Profile">';
    $html .= fullname($user) . '</a>';
    $html .= $statushtml . '</h3>';

    $lastaccessstr = empty($user->lastaccess) ? get_string('never') : userdate($user->lastaccess);
    $html .= '<div class="text-muted mt-1" style="font-size: 0.85rem;">';
    $html .= get_string('lastaccess', 'enrol_gapplya') . ': ' . $lastaccessstr . '</div>';
    $html .= '</div></div>';

    if (isset($gapplyarecord->timecreated)) {
        $submissiondate = userdate($gapplyarecord->timecreated);
        $html .= '<div class="text-right"><a href="javascript:void(0);" ';
        $html .= 'class="text-primary font-weight-bold history-trigger">';
        $html .= '<small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">';
        $html .= get_string('timecreated', 'enrol_gapplya') . '</small>';
        $html .= '<i class="fa fa-clock-o mr-1"></i> ' . $submissiondate . '</a></div>';
    }
    $html .= '</div>';

    $html .= '<div class="modal-body pt-4">';

    // Main Table.
    $html .= '<table class="table table-bordered table-sm table-striped mb-4" ';
    $html .= 'style="font-size: 0.95rem;"><tbody>';

    $priorityfields = ['department', 'institution', 'city', 'phone1', 'phone2', 'email'];
    foreach ($priorityfields as $fieldkey) {
        $val = '';
        if (!empty($user->$fieldkey)) {
            $val = s($user->$fieldkey);
        }
        if ($val !== '') {
            $label = get_string($fieldkey, 'core');
            if ($label === '[[' . $fieldkey . ']]') {
                $label = ucfirst($fieldkey);
            }
            $html .= '<tr><th style="width: 35%; color: #333;">' . $label . '</th><td>' . $val . '</td></tr>';
        }
    }
    $html .= '</tbody><tbody id="dynamic-table-rows"></tbody></table>';

    if ($gapplyarecord && !empty($gapplyarecord->applytext)) {
        $html .= '<h5 class="text-primary mb-2" style="font-size: 1.1rem;">';
        $html .= get_string('applicationtext', 'enrol_gapplya') . '</h5>';
        $html .= '<div class="p-3 bg-light border rounded mb-4"><div>';
        $html .= format_text($gapplyarecord->applytext, FORMAT_HTML);
        $html .= '</div></div>';
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'enrol_gapplya', 'applyfile', $id . $userid, 'filename', false);
    if ($files) {
        $html .= '<div class="p-3 bg-light border rounded mb-4">';
        $html .= '<h6 class="font-weight-bold text-muted mb-2">';
        $html .= get_string('appattachment', 'enrol_gapplya') . ':</h6>';
        foreach ($files as $file) {
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out();

            $html .= html_writer::link(
                'javascript:void(0)',
                '<i class="fa fa-download"></i> ' . $file->get_filename(),
                [
                    'class' => 'btn btn-sm btn-white border mr-2 mb-2 attachmentlink',
                    'data-type' => $file->get_mimetype(),
                    'data-url' => $url,
                ]
            );
        }
        $html .= '</div>';
    }

    $html .= '<form id="gapplya-edit-form">';
    $html .= '<input type="hidden" name="action" value="savedata">';
    $html .= '<input type="hidden" name="id" value="' . $id . '">';
    $html .= '<input type="hidden" name="recordid" value="' . $gapplyarecord->id . '">';
    $html .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

    $schema = \enrol_gapplya\util::get_application_schema($instance);
    $viewstyle = ($mode == 'edit') ? 'style="display:none;"' : '';
    $editstyle = ($mode == 'edit') ? 'style="display:block;"' : 'style="display:none;"';

    $jseditbtn = json_encode('<i class="fa fa-pencil"></i> ' . get_string('edit', 'enrol_gapplya'));
    $jscancelbtn = json_encode('<i class="fa fa-times"></i> ' . get_string('cancel', 'enrol_gapplya'));
    $initialbtntext = ($mode == 'edit') ? json_decode($jscancelbtn) : json_decode($jseditbtn);

    if (!empty($schema)) {
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<h5 class="text-primary m-0" style="font-size: 1.1rem;">';
        $html .= get_string('additionalinfo', 'enrol_gapplya') . '</h5>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-secondary d-print-none" id="btn-toggle-edit">';
        $html .= $initialbtntext . '</button></div>';

        $jsondata = (!empty($gapplyarecord->json_data)) ? json_decode($gapplyarecord->json_data, true) : [];

        $html .= '<div id="view-mode-container" ' . $viewstyle . '>';
        $html .= '<table class="table table-bordered table-sm table-striped mb-3"><tbody>';
        foreach ($schema as $field) {
            if ($field['type'] == 'header') {
                continue;
            }
            $key = $field['name'];
            $lbl = $field['label'];
            $val = isset($jsondata[$key]) ? $jsondata[$key] : (isset($jsondata[$lbl]) ? $jsondata[$lbl] : '-');

            if ($field['type'] == 'select' && isset($field['options'][$val])) {
                $val = $field['options'][$val];
            }
            if ($field['type'] == 'checkbox') {
                $val = ($val === 'yes' || $val === '1' || $val === 'on') ? get_string('yes', 'core') : '-';
            }
            $html .= '<tr><th style="width: 35%; color: #333;">' . $lbl . '</th><td>' . nl2br(s($val)) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        $html .= '<div id="edit-mode-container" ' . $editstyle . '>';
        $html .= '<table class="table table-bordered table-sm mb-3 bg-white"><tbody>';
        foreach ($schema as $field) {
            if ($field['type'] == 'header') {
                $lbl = $field['label'];
                $rowattrs = '';
                if (isset($field['dependency']) && is_array($field['dependency'])) {
                    $dep = $field['dependency'];
                    $depel = isset($dep['element']) ? $dep['element'] : '';
                    $depcond = isset($dep['condition']) ? $dep['condition'] : 'eq';
                    $depval = isset($dep['value']) ? $dep['value'] : '';

                    $rowattrs = 'data-dep-element="' . s($depel) . '" ';
                    $rowattrs .= 'data-dep-condition="' . s($depcond) . '" ';
                    $rowattrs .= 'data-dep-value="' . s($depval) . '"';
                }
                $html .= '<tr ' . $rowattrs . '><td colspan="2" class="font-weight-bold bg-light">' . $lbl . '</td></tr>';
                continue;
            }

            $key = $field['name'];
            $lbl = $field['label'];
            $val = isset($jsondata[$key]) ? $jsondata[$key] : (isset($jsondata[$lbl]) ? $jsondata[$lbl] : '');

            if ($field['type'] == 'select') {
                $inputhtml = '<select name="edit_' . $key . '" class="custom-select custom-select-sm">';
                foreach ($field['options'] as $k => $v) {
                    $sel = ($k == $val) ? 'selected' : '';
                    $inputhtml .= '<option value="' . $k . '" ' . $sel . '>' . $v . '</option>';
                }
                $inputhtml .= '</select>';
            } else if ($field['type'] == 'checkbox') {
                $chk = ($val === 'yes' || $val === '1' || $val === 'on') ? 'checked' : '';
                $inputhtml = '<input type="checkbox" name="edit_' . $key . '" ' . $chk . '>';
            } else {
                $inputhtml = '<input type="text" name="edit_' . $key . '" ';
                $inputhtml .= 'value="' . s($val) . '" class="form-control form-control-sm">';
            }

            $rowattrs = 'data-fieldname="' . s($key) . '"';
            if (isset($field['dependency']) && is_array($field['dependency'])) {
                $dep = $field['dependency'];
                $depel = isset($dep['element']) ? $dep['element'] : '';
                $depcond = isset($dep['condition']) ? $dep['condition'] : 'eq';
                $depval = isset($dep['value']) ? $dep['value'] : '';

                $rowattrs .= ' data-dep-element="' . s($depel) . '" ';
                $rowattrs .= 'data-dep-condition="' . s($depcond) . '" ';
                $rowattrs .= 'data-dep-value="' . s($depval) . '"';
            }

            $html .= '<tr ' . $rowattrs . '><th style="width: 35%; vertical-align: middle;">' . $lbl . '</th>';
            $html .= '<td>' . $inputhtml . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }

    $adminnoteval = isset($gapplyarecord->adminnote) ? $gapplyarecord->adminnote : '';
    $html .= '<h5 class="text-primary mb-2 mt-4" style="font-size: 1.1rem;">' . get_string('adminnote', 'enrol_gapplya') . '</h5>';
    $html .= '<textarea name="adminnote" class="form-control mb-3" rows="3" ';
    $html .= 'placeholder="' . get_string('privatenote', 'enrol_gapplya') . '">';
    $html .= s($adminnoteval) . '</textarea>';

    $html .= '<div class="text-right d-print-none mb-4">';
    $html .= '<button type="button" class="btn btn-primary" id="btn-save-changes"><i class="fa fa-save"></i> ';
    $html .= get_string('savechanges', 'enrol_gapplya') . '</button><span id="save-status" class="ml-2"></span></div></form>';

    if ($gapplyarecord) {
        $history = !empty($gapplyarecord->history) ? json_decode($gapplyarecord->history, true) : [];
        if (!is_array($history)) {
            $history = [];
        }
        $hassubmit = false;
        foreach ($history as $h) {
            if ($h['action'] == 'submitted') {
                $hassubmit = true;
            }
        }
        if (!$hassubmit) {
            $history[] = [
                'time' => $gapplyarecord->timecreated,
                'action' => 'submitted',
                'userid' => $gapplyarecord->userid,
                'details' => '',
            ];
        }

        usort($history, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        $lastedittime = !empty($history) ? $history[0]['time'] : $gapplyarecord->timecreated;
        $html .= '<div class="text-right text-muted mb-2 mt-2" style="font-size: 0.85rem;">';
        $html .= '<em>' . get_string('lastedited', 'enrol_gapplya') . ': ' . userdate($lastedittime) . '</em>';
        $html .= '</div>';

        $huids = [];
        foreach ($history as $h) {
            if (!empty($h['userid'])) {
                $huids[$h['userid']] = $h['userid'];
            }
        }
        $husers = [];
        if (!empty($huids)) {
            list($usql, $uparams) = $DB->get_in_or_equal($huids);
            $husers = $DB->get_records_select('user', "id $usql", $uparams, '', 'id, firstname, lastname');
        }

        $historydisplay = ($mode == 'history') ? 'block' : 'none';
        $careticon = ($mode == 'history') ? 'fa-caret-up' : 'fa-caret-down';

        $html .= '<div class="d-print-none mt-4 border-top pt-3" id="history-section">';
        $html .= '<h5 class="text-primary history-toggle" style="font-size: 1.1rem; cursor: pointer;">';
        $html .= '<i class="fa fa-history mr-1"></i> ' . get_string('historychanges', 'enrol_gapplya');
        $html .= ' <i id="history-caret" class="fa ' . $careticon . ' ml-1"></i></h5>';

        $html .= '<div id="history-content" style="display:' . $historydisplay . ';" class="mt-2">';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-sm table-hover text-muted" style="font-size: 0.85rem;">';
        $html .= '<thead class="thead-light"><tr>';
        $html .= '<th style="width:120px">' . get_string('action', 'enrol_gapplya') . '</th>';
        $html .= '<th style="width:160px">' . get_string('user', 'core') . '</th>';
        $html .= '<th>' . get_string('details', 'enrol_gapplya') . '</th>';
        $html .= '<th style="width:140px">' . get_string('date', 'core') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($history as $hitem) {
            $sm = get_string_manager();
            $hactionstr = 'log_action_' . $hitem['action'];
            $haction = ucfirst($hitem['action']);
            if ($sm->string_exists($hactionstr, 'enrol_gapplya')) {
                $haction = get_string($hactionstr, 'enrol_gapplya');
            }

            $hdetails = isset($hitem['details']) ? $hitem['details'] : '';
            $hdetailstr = 'log_detail_' . $hitem['action'];
            if (empty($hdetails) && $sm->string_exists($hdetailstr, 'enrol_gapplya')) {
                $hdetails = get_string($hdetailstr, 'enrol_gapplya');
            }

            $husername = get_string('systemuser', 'enrol_gapplya');
            if (isset($husers[$hitem['userid']])) {
                $husername = fullname($husers[$hitem['userid']]);
            }

            $html .= '<tr><td><strong>' . $haction . '</strong></td>';
            $html .= '<td>' . $husername . '</td>';
            $html .= '<td style="white-space: pre-wrap;">' . s($hdetails) . '</td>';
            $html .= '<td>' . userdate($hitem['time'], get_string('strftimedatetime', 'langconfig')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
    }
    $html .= '</div>';

    if ($gapplyarecord) {
        $html .= '<div class="modal-footer d-print-none justify-content-end">';
        $recid = $gapplyarecord->id;

        // Call the renamed helper function to generate status buttons.
        $modalbuttons = enrol_gapplya_get_status_buttons($gapplyarecord->status, $recid, 'modal');
        $html .= $modalbuttons;

        $html .= '<button type="button" class="btn btn-danger action-button ml-3" ';
        $html .= 'data-action="delete" data-id="' . $recid . '" data-dismiss="modal">';
        $html .= '<i class="fa fa-trash mr-1"></i> ' . get_string('delete', 'enrol_gapplya') . '</button>';
        $html .= '</div>';
    }
    echo $html;
    die;
} else if ($action == "getapplications") {
    // 5. Get applications.
    require_sesskey();
    require_capability('enrol/gapplya:manage', $context);
    while (ob_get_level()) {
        ob_end_clean();
    }

    $tab = required_param('tab', PARAM_TEXT);
    if ($tab === 'all') {
        $sql = "SELECT * FROM {enrol_gapplya} WHERE instance = ?";
        $params = [$id];
    } else {
        $sql = "SELECT * FROM {enrol_gapplya} WHERE instance = ? AND status = ?";
        $params = [$id, $tab];
    }

    $records = $DB->get_records_sql($sql, $params);
    $table = new stdClass();
    $table->data = [];

    if ($records) {
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $showuseridentity = [];
        if (!empty($instance->customtext3)) {
            $showuseridentity = explode(',', $instance->customtext3);
        } else {
            $configfields = get_config('enrol_gapplya', 'showuseridentity');
            if ($configfields) {
                $showuseridentity = explode(',', $configfields);
            }
        }

        $fs = get_file_storage();
        $schema = \enrol_gapplya\util::get_application_schema($instance);

        $userids = array_map(function($r) {
            return $r->userid;
        }, $records);

        $users = [];
        if (!empty($userids)) {
            list($usql, $uparams) = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_select('user', "id $usql", $uparams);
        }

        foreach ($records as $record) {
            $user = isset($users[$record->userid]) ? $users[$record->userid] : $DB->get_record('user', ['id' => $record->userid]);
            if (!$user) {
                $user = new stdClass();
                $user->id = $record->userid;
                $user->firstname = '-';
                $user->lastname = '-';
                $user->email = '-';
                $user->picture = 0;
            }
            if (!empty($user->id)) {
                profile_load_custom_fields($user);
            }

            $sm = get_string_manager();
            $statusstr = $record->status;
            if ($sm->string_exists($record->status, 'enrol_gapplya')) {
                $statusstr = get_string($record->status, 'enrol_gapplya');
            }

            $row = [''];
            $row[] = $record->id;

            $row[] = s($user->firstname);
            $row[] = s($user->middlename ?? '');
            $row[] = s($user->lastname);

            $linkhtml = '<a href="javascript:void(0)" class="showuserdetail font-weight-bold" ';
            $linkhtml .= 'data-status="' . $record->status . '" data-statusformatted="' . s($statusstr) . '" ';
            $linkhtml .= 'data-id="' . $record->id . '" data-userid="' . $record->userid . '">' . fullname($user) . '</a>';
            $row[] = $linkhtml;

            $identityfieldstoshow = array_diff($showuseridentity, ['firstname', 'lastname', 'picture']);

            foreach ($identityfieldstoshow as $field) {
                if (strpos($field, 'profile_field_') !== false) {
                    $shortname = str_replace('profile_field_', '', $field);
                    $val = isset($user->profile[$shortname]) ? $user->profile[$shortname] : '';
                    $row[] = format_string($val, true);
                } else {
                    $val = isset($user->$field) ? $user->$field : '';
                    $row[] = format_string($val, true);
                }
            }

            $jsondata = (!empty($record->json_data)) ? json_decode($record->json_data, true) : [];
            if (!empty($schema)) {
                foreach ($schema as $field) {
                    if ($field['type'] == 'header') {
                        continue;
                    }
                    $key = $field['name'];
                    $label = $field['label'];
                    $val = isset($jsondata[$key]) ? $jsondata[$key] : (isset($jsondata[$label]) ? $jsondata[$label] : '');
                    $displayval = $val;
                    if ($field['type'] == 'select' && isset($field['options'][$val])) {
                        $displayval = $field['options'][$val];
                    }
                    if ($field['type'] == 'checkbox') {
                        $displayval = ($val == 'yes' || $val == '1' || $val == 'on') ? get_string('yes', 'core') : '-';
                    }
                    $row[] = '<div class="text-clamp-3">' . s($displayval) . '</div>';
                }
            }

            $showdetails = !empty($instance->customint1) || !empty($instance->customint2) || !empty($instance->customint3);
            if ($showdetails || !empty($instance->customint4)) {
                $showdetails = true;
            }

            $attachmentshtml = '';

            if ($showdetails) {
                if (!empty($instance->customint2) || !empty($instance->customint4)) {
                    $itemid = $id . $record->userid;
                    $files = $fs->get_area_files($context->id, 'enrol_gapplya', 'applyfile', $itemid, 'filename', false);
                    if ($files) {
                        foreach ($files as $file) {
                            $url = moodle_url::make_pluginfile_url(
                                $file->get_contextid(),
                                $file->get_component(),
                                $file->get_filearea(),
                                $file->get_itemid(),
                                $file->get_filepath(),
                                $file->get_filename()
                            )->out();

                            $linkicon = '<i class="fa fa-fw fa-paperclip mr-1"></i>' . $file->get_filename();
                            $attachmentshtml .= html_writer::link(
                                'javascript:void(0)',
                                $linkicon,
                                ['class' => 'small attachmentlink', 'data-type' => $file->get_mimetype(), 'data-url' => $url]
                            ) . '<br>';
                        }
                    }
                }

                $applicationdetails = '<div>';

                if ((!empty($instance->customint1) || !empty($instance->customint3)) && !empty($record->applytext)) {
                    $applicationdetails .= '<div class="applicationtext overflow-auto mb-2" ';
                    $applicationdetails .= 'style="max-height: 200px" data-id="' . $record->id . '">';
                    $applicationdetails .= format_text($record->applytext, FORMAT_HTML) . '</div>';
                } else if (!empty($record->applytext)) {
                    $applicationdetails .= '<div class="applicationtext d-none" data-id="' . $record->id . '">';
                    $applicationdetails .= format_text($record->applytext, FORMAT_HTML) . '</div>';
                }

                $applicationdetails .= '<div class="text-truncate">' . $attachmentshtml . '</div></div>';

                $row[] = $applicationdetails;
            } else if (!empty($record->applytext)) {
                $apptext = format_text($record->applytext, FORMAT_HTML);
                $row[] = '<div class="applicationtext d-none" data-id="' . $record->id . '">' . $apptext . '</div>';
            }

            if ($showdetails) {
                $row[] = format_text($record->applytext, FORMAT_HTML);
            }

            $row[] = $attachmentshtml;

            $noteval = (isset($record->adminnote)) ? $record->adminnote : '';
            $row[] = '<div class="text-clamp-3 text-muted small" title="' . s($noteval) . '">' . s($noteval) . '</div>';

            $type = ($record->status == 'waitlisted') ? "info" : (($record->status == 'rejected') ? "warning" : "primary");
            $row[] = '<span class="badge badge-' . $type . ' status-col">' . $statusstr . '</span>';

            $row[] = userdate($record->timecreated);
            $row[] = $record->timecreated;

            $actionbtn = '<div class="dropdown position-static">';
            $actionbtn .= '<button class="btn btn-sm btn-icon ml-auto" type="button" ';
            $actionbtn .= 'data-toggle="dropdown" data-bs-toggle="dropdown">';
            $actionbtn .= '<i class="icon fa fa-ellipsis-v fa-fw"></i></button>';
            $actionbtn .= '<div class="dropdown-menu dropdown-menu-right">';

            // Call the renamed helper function to generate status buttons (from lib.php).
            $actionbtn .= enrol_gapplya_get_status_buttons($record->status, $record->id, 'dropdown');

            if ($record->status !== 'approved') {
                $actionbtn .= '<div class="dropdown-divider"></div>';
            }

            $actionbtn .= html_writer::link(
                'javascript:void(0)',
                '<i class="icon fa fa-eye fa-fw text-muted"></i> ' . get_string('view', 'core'),
                [
                    'class' => 'dropdown-item showuserdetail',
                    'data-id' => $record->id,
                    'data-userid' => $record->userid,
                    'data-status' => $record->status,
                    'data-statusformatted' => s($statusstr),
                ]
            );
            $actionbtn .= html_writer::link(
                'javascript:void(0)',
                '<i class="icon fa fa-pencil fa-fw text-muted"></i> ' . get_string('edit', 'core'),
                [
                    'class' => 'dropdown-item showuserdetail',
                    'data-id' => $record->id,
                    'data-userid' => $record->userid . '_edit',
                    'data-status' => $record->status,
                    'data-statusformatted' => s($statusstr),
                ]
            );
            $actionbtn .= html_writer::link(
                'javascript:void(0)',
                '<i class="icon fa fa-history fa-fw text-muted"></i> ' . get_string('history', 'enrol_gapplya'),
                [
                    'class' => 'dropdown-item showuserdetail',
                    'data-id' => $record->id,
                    'data-userid' => $record->userid . '_history',
                    'data-status' => $record->status,
                    'data-statusformatted' => s($statusstr),
                ]
            );
            $actionbtn .= html_writer::link(
                'javascript:void(0)',
                '<i class="icon fa fa-trash fa-fw text-danger"></i> ' . get_string('delete', 'core'),
                [
                    'class' => 'dropdown-item menu-action action-button text-danger',
                    'data-action' => 'delete',
                    'data-id' => $record->id,
                ]
            );
            $actionbtn .= '</div></div>';

            $row[] = $actionbtn;
            $row['DT_RowId'] = $record->id;
            $row['DT_RowClass'] = 'status-' . $record->status;
            $table->data[] = $row;
        }
    }
    echo json_encode($table->data);
    die;
} else if ($action == "getgroups") {
    $groups = groups_get_all_groups($courseid, 0, 0, 'g.id, g.name');
    $groupsdata = [];
    foreach ($groups as $group) {
        $groupsdata[] = ['id' => $group->id, 'name' => format_text($group->name, FORMAT_PLAIN)];
    }
    echo json_encode($groupsdata);
    die;
} else if ($action == "getrolesanddates") {
    $roles = get_assignable_roles($context, ROLENAME_BOTH);
    $data = [
        'roles' => $roles,
        'defaultrole' => $instance->roleid,
        'startdate' => $instance->enrolstartdate,
        'enddate' => $instance->enrolenddate,
    ];
    echo json_encode($data);
    die;
}

