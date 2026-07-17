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
 * Read-only AJAX handler for enrol_gapplya plugin (DataTables and Modals).
 * All write actions are handled via External Services (classes/external/api.php).
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/gapplya/lib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_TEXT);
$id = required_param('id', PARAM_INT); // Instance ID.

$instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
$courseid = $instance->courseid;
$context = context_course::instance($courseid);
$PAGE->set_context($context);

// Permissions check (Global Gatekeeper).
require_capability('enrol/gapplya:manage', $context);

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

if ($action == 'getuserbyid') {
    // 1. Get user details (Modal HTML).
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
        $statusstr = $sm->string_exists($gapplyarecord->status, 'enrol_gapplya') ?
            get_string($gapplyarecord->status, 'enrol_gapplya') : $gapplyarecord->status;
        $statushtml = '<span class="badge ' . $statusclass . ' ml-2" ' .
            'style="font-size: 0.6em; vertical-align: middle;">' . $statusstr . '</span>';
    }

    $html = '';
    $html .= '<div class="modal-header border-bottom-0 pb-0">';
    $html .= '<h4 class="modal-title text-truncate">' . get_string('application', 'enrol_gapplya') . '</h4>';
    $html .= '<div class="d-print-none">';
    $html .= '<a href="javascript:window.print()" class="btn btn-sm btn-light text-muted mr-2" ' .
        'title="' . get_string('print', 'enrol_gapplya') . '" data-toggle="tooltip" ' .
        'style="width: 35px; height: 35px; padding: 6px 0;"><i class="fa fa-print"></i></a>';
    $html .= '<button data-dismiss="modal" class="btn btn-sm btn-light text-muted" ' .
        'title="' . get_string('close', 'enrol_gapplya') . '" data-toggle="tooltip" ' .
        'style="width: 35px; height: 35px; padding: 6px 0;"><i class="fa fa-times"></i></button>';
    $html .= '</div></div>';

    $html .= '<div class="bg-light border-bottom shadow-sm px-4 py-3 d-flex justify-content-between ' .
        'align-items-center mb-0">';
    $html .= '<div class="d-flex align-items-center">';
    if ($user->picture > 0) {
        $html .= $OUTPUT->user_picture($user, ['size' => 40, 'class' => 'mr-3 rounded-circle']);
    }
    $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);

    $html .= '<div><h3 class="m-0 text-dark" style="font-weight: 300;">';
    $html .= '<a href="' . $profileurl . '" target="_blank" class="text-dark" title="Profile">' .
        fullname($user) . '</a>' . $statushtml . '</h3>';

    $lastaccessstr = empty($user->lastaccess) ? get_string('never') : userdate($user->lastaccess);
    $html .= '<div class="text-muted mt-1" style="font-size: 0.85rem;">' .
        get_string('lastaccess', 'enrol_gapplya') . ': ' . $lastaccessstr . '</div></div></div>';

    if (isset($gapplyarecord->timecreated)) {
        $submissiondate = userdate($gapplyarecord->timecreated);
        $html .= '<div class="text-right"><a href="javascript:void(0);" ' .
            'class="text-primary font-weight-bold history-trigger">' .
            '<small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">' .
            get_string('timecreated', 'enrol_gapplya') . '</small><i class="fa fa-clock-o mr-1"></i> ' .
            $submissiondate . '</a></div>';
    }
    $html .= '</div><div class="modal-body pt-4">';

    $html .= '<table class="table table-bordered table-sm table-striped mb-4" ' .
        'style="font-size: 0.95rem;"><tbody>';
    $priorityfields = ['department', 'institution', 'city', 'phone1', 'phone2', 'email'];
    foreach ($priorityfields as $fieldkey) {
        $val = !empty($user->$fieldkey) ? s($user->$fieldkey) : '';
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
        $html .= '<h5 class="text-primary mb-2" style="font-size: 1.1rem;">' .
            get_string('applicationtext', 'enrol_gapplya') . '</h5>';
        $html .= '<div class="p-3 bg-light border rounded mb-4"><div>' .
            format_text($gapplyarecord->applytext, FORMAT_HTML) . '</div></div>';
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'enrol_gapplya', 'applyfile', $id . $userid, 'filename', false);
    if ($files) {
        $html .= '<div class="p-3 bg-light border rounded mb-4">' .
            '<h6 class="font-weight-bold text-muted mb-2">' .
            get_string('appattachment', 'enrol_gapplya') . ':</h6>';
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
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">' .
            '<h5 class="text-primary m-0" style="font-size: 1.1rem;">' .
            get_string('additionalinfo', 'enrol_gapplya') . '</h5>' .
            '<button type="button" class="btn btn-sm btn-outline-secondary d-print-none" ' .
            'id="btn-toggle-edit">' . $initialbtntext . '</button></div>';

        $jsondata = (!empty($gapplyarecord->json_data)) ? json_decode($gapplyarecord->json_data, true) : [];

        $html .= '<div id="view-mode-container" ' . $viewstyle . '>' .
            '<table class="table table-bordered table-sm table-striped mb-3"><tbody>';
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

        $html .= '<div id="edit-mode-container" ' . $editstyle . '>' .
            '<table class="table table-bordered table-sm mb-3 bg-white"><tbody>';
        foreach ($schema as $field) {
            if ($field['type'] == 'header') {
                $lbl = $field['label'];
                $rowattrs = '';
                if (isset($field['dependency']) && is_array($field['dependency'])) {
                    $dep = $field['dependency'];
                    $rowattrs = 'data-dep-element="' . s($dep['element'] ?? '') . '" ' .
                        'data-dep-condition="' . s($dep['condition'] ?? 'eq') . '" ' .
                        'data-dep-value="' . s($dep['value'] ?? '') . '"';
                }
                $html .= '<tr ' . $rowattrs . '><td colspan="2" class="font-weight-bold bg-light">' .
                    $lbl . '</td></tr>';
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
                $inputhtml = '<input type="text" name="edit_' . $key . '" value="' . s($val) . '" ' .
                    'class="form-control form-control-sm">';
            }

            $rowattrs = 'data-fieldname="' . s($key) . '"';
            if (isset($field['dependency']) && is_array($field['dependency'])) {
                $dep = $field['dependency'];
                $rowattrs .= ' data-dep-element="' . s($dep['element'] ?? '') . '" ' .
                    'data-dep-condition="' . s($dep['condition'] ?? 'eq') . '" ' .
                    'data-dep-value="' . s($dep['value'] ?? '') . '"';
            }

            $html .= '<tr ' . $rowattrs . '><th style="width: 35%; vertical-align: middle;">' . $lbl .
                '</th><td>' . $inputhtml . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }

    $adminnoteval = isset($gapplyarecord->adminnote) ? $gapplyarecord->adminnote : '';
    $html .= '<h5 class="text-primary mb-2 mt-4" style="font-size: 1.1rem;">' .
        get_string('adminnote', 'enrol_gapplya') . '</h5>';
    $html .= '<textarea name="adminnote" class="form-control mb-3" rows="3" ' .
        'placeholder="' . get_string('privatenote', 'enrol_gapplya') . '">' . s($adminnoteval) . '</textarea>';

    $html .= '<div class="text-right d-print-none mb-4">' .
        '<button type="button" class="btn btn-primary" id="btn-save-changes"><i class="fa fa-save"></i> ' .
        get_string('savechanges', 'enrol_gapplya') . '</button><span id="save-status" class="ml-2"></span>' .
        '</div></form>';

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
        $html .= '<div class="text-right text-muted mb-2 mt-2" style="font-size: 0.85rem;">' .
            '<em>' . get_string('lastedited', 'enrol_gapplya') . ': ' . userdate($lastedittime) . '</em></div>';

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
        $html .= '<h5 class="text-primary history-toggle" style="font-size: 1.1rem; cursor: pointer;">' .
            '<i class="fa fa-history mr-1"></i> ' . get_string('historychanges', 'enrol_gapplya') .
            ' <i id="history-caret" class="fa ' . $careticon . ' ml-1"></i></h5>';
        $html .= '<div id="history-content" style="display:' . $historydisplay . ';" class="mt-2">' .
            '<div class="table-responsive"><table class="table table-sm table-hover text-muted" ' .
            'style="font-size: 0.85rem;"><thead class="thead-light"><tr>' .
            '<th style="width:120px">' . get_string('action', 'enrol_gapplya') . '</th>' .
            '<th style="width:160px">' . get_string('user', 'core') . '</th>' .
            '<th>' . get_string('details', 'enrol_gapplya') . '</th>' .
            '<th style="width:140px">' . get_string('date', 'core') . '</th></tr></thead><tbody>';

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

            $html .= '<tr><td><strong>' . $haction . '</strong></td><td>' . $husername . '</td>' .
                '<td style="white-space: pre-wrap;">' . s($hdetails) . '</td>' .
                '<td>' . userdate($hitem['time'], get_string('strftimedatetime', 'langconfig')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div></div></div>';
    }

    if ($gapplyarecord) {
        $html .= '<div class="modal-footer d-print-none justify-content-end">';
        $html .= enrol_gapplya_get_status_buttons($gapplyarecord->status, $gapplyarecord->id, 'modal');
        $html .= '<button type="button" class="btn btn-danger action-button ml-3" ' .
            'data-action="delete" data-id="' . $gapplyarecord->id . '" data-dismiss="modal">' .
            '<i class="fa fa-trash mr-1"></i> ' . get_string('delete', 'enrol_gapplya') . '</button></div>';
    }
    echo $html;
    die;

} else if ($action == 'getapplications') {
    // 2. Get applications for DataTables JSON.
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
        $userprofiles = [];
        if (!empty($userids)) {
            list($usql, $uparams) = $DB->get_in_or_equal($userids);
            $users = $DB->get_records_select('user', "id $usql", $uparams);

            $sql = "SELECT uid.id, uid.userid, uif.shortname, uid.data
                    FROM {user_info_data} uid
                    JOIN {user_info_field} uif ON uid.fieldid = uif.id
                    WHERE uid.userid $usql";
            $customdata = $DB->get_records_sql($sql, $uparams);
            if ($customdata) {
                foreach ($customdata as $c) {
                    if (!isset($userprofiles[$c->userid])) {
                        $userprofiles[$c->userid] = [];
                    }
                    $userprofiles[$c->userid][$c->shortname] = $c->data;
                }
            }
        }

        foreach ($records as $record) {
            $user = isset($users[$record->userid]) ? $users[$record->userid] : clone($USER);
            if (!isset($users[$record->userid])) {
                $user->id = $record->userid;
                $user->firstname = '-';
                $user->lastname = '-';
                $user->email = '-';
                $user->picture = 0;
            } else {
                if (!isset($user->profile)) {
                    $user->profile = [];
                }
                if (isset($userprofiles[$user->id])) {
                    foreach ($userprofiles[$user->id] as $k => $v) {
                        $user->profile[$k] = $v;
                    }
                }
            }

            $sm = get_string_manager();
            $statusstr = $sm->string_exists($record->status, 'enrol_gapplya') ?
                get_string($record->status, 'enrol_gapplya') : $record->status;

            $row = [''];
            $row[] = $record->id;
            $row[] = s($user->firstname);
            $row[] = s($user->middlename ?? '');
            $row[] = s($user->lastname);

            $contextdata = [
                'status' => $record->status,
                'statusformatted' => s($statusstr),
                'id' => $record->id,
                'userid' => $record->userid,
                'fullname' => fullname($user),
            ];
            $linkhtml = $OUTPUT->render_from_template('enrol_gapplya/user_detail_link', $contextdata);
            $row[] = $linkhtml;

            $identityfieldstoshow = array_diff($showuseridentity, ['firstname', 'lastname', 'picture']);
            foreach ($identityfieldstoshow as $field) {
                if (strpos($field, 'profile_field_') !== false) {
                    $shortname = str_replace('profile_field_', '', $field);
                    $row[] = format_string(isset($user->profile[$shortname]) ? $user->profile[$shortname] : '', true);
                } else {
                    $row[] = format_string(isset($user->$field) ? $user->$field : '', true);
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
                    if ($field['type'] == 'select' && isset($field['options'][$val])) {
                        $val = $field['options'][$val];
                    }
                    if ($field['type'] == 'checkbox') {
                        $val = ($val == 'yes' || $val == '1' || $val == 'on') ? get_string('yes', 'core') : '-';
                    }
                    $row[] = '<div class="text-clamp-3">' . s($val) . '</div>';
                }
            }

            $showdetails = !empty($instance->customint1) || !empty($instance->customint2) ||
                !empty($instance->customint3) || !empty($instance->customint4);
            $attachmentshtml = '';

            if ($showdetails) {
                if (!empty($instance->customint2) || !empty($instance->customint4)) {
                    $files = $fs->get_area_files(
                        $context->id,
                        'enrol_gapplya',
                        'applyfile',
                        $id . $record->userid,
                        'filename',
                        false
                    );

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

                            $attachmentshtml .= html_writer::link(
                                'javascript:void(0)',
                                '<i class="fa fa-fw fa-paperclip mr-1"></i>' . $file->get_filename(),
                                [
                                    'class' => 'small attachmentlink',
                                    'data-type' => $file->get_mimetype(),
                                    'data-url' => $url,
                                ]
                            ) . '<br>';
                        }
                    }
                }

                $applicationdetails = '<div>';
                if ((!empty($instance->customint1) || !empty($instance->customint3)) && !empty($record->applytext)) {
                    $applicationdetails .= '<div class="applicationtext overflow-auto mb-2" ' .
                        'style="max-height: 200px" data-id="' . $record->id . '">' .
                        format_text($record->applytext, FORMAT_HTML) . '</div>';
                } else if (!empty($record->applytext)) {
                    $applicationdetails .= '<div class="applicationtext d-none" ' .
                        'data-id="' . $record->id . '">' .
                        format_text($record->applytext, FORMAT_HTML) . '</div>';
                }
                $applicationdetails .= '<div class="text-truncate">' . $attachmentshtml . '</div></div>';
                $row[] = $applicationdetails;
            } else if (!empty($record->applytext)) {
                $row[] = '<div class="applicationtext d-none" data-id="' . $record->id . '">' .
                    format_text($record->applytext, FORMAT_HTML) . '</div>';
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

            $actiondata = [
                'statusbuttons' => enrol_gapplya_get_status_buttons($record->status, $record->id, 'dropdown'),
                'isapproved' => ($record->status === 'approved'),
                'id' => $record->id,
                'userid' => $record->userid,
                'status' => $record->status,
                'statusformatted' => s($statusstr),
            ];
            $actionbtn = $OUTPUT->render_from_template('enrol_gapplya/user_action_menu', $actiondata);

            $row[] = $actionbtn;
            $row['DT_RowId'] = $record->id;
            $row['DT_RowClass'] = 'status-' . $record->status;
            $table->data[] = $row;
        }
    }
    echo json_encode($table->data);
    die;
}
