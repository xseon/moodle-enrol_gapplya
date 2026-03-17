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
 * Library of functions and constants for the enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enrolment plugin class for enrol_gapplya.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_gapplya_plugin extends enrol_plugin {

    /**
     * Return an array of action icons for the instance.
     *
     * @param stdClass $instance Course enrol instance.
     * @return array Array of action icons.
     */
    public function get_action_icons($instance) {
        global $OUTPUT;
        $context = context_course::instance($instance->courseid);
        $icons = [];

        if (has_capability('enrol/gapplya:config', $context)) {
            $enrolurl = new moodle_url('/enrol/editinstance.php', [
                'id' => $instance->id,
                'courseid' => $instance->courseid,
                'type' => 'gapplya',
            ]);
            $icons[] = $OUTPUT->action_icon(
                $enrolurl,
                new pix_icon('i/edit', get_string('edit', 'core'), 'core', ['class' => 'iconsmall'])
            );
        }

        if (has_capability('enrol/gapplya:manage', $context)) {
            $managelink = new moodle_url('/enrol/gapplya/manage.php', [
                'id' => $instance->id,
                'courseid' => $instance->courseid,
            ]);
            $icons[] = $OUTPUT->action_icon(
                $managelink,
                new pix_icon('i/users', get_string('applications', 'enrol_gapplya'), 'core', ['class' => 'iconsmall'])
            );
        }

        return $icons;
    }

    /**
     * Returns true if the plugin can be added to a course.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_enrol($instance) {
        return true;
    }

    /**
     * Returns true if users can be unenrolled.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol($instance) {
        return true;
    }

    /**
     * Returns true if the instance can be managed.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage($instance) {
        return true;
    }

    /**
     * Determines if the plugin should show an "Enrol me" link.
     * This is strictly required for the Mobile App Web Services to list this method.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance) {
        return true;
    }

    /**
     * Returns true if the user can unenrol themselves.
     *
     * @param stdClass $instance
     * @param stdClass $ue
     * @return bool
     */
    public function allow_unenrol_user($instance, $ue) {
        return true;
    }

    /**
     * Use standard Moodle UI for editing the instance.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Build the form elements for the instance settings.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $PAGE, $CFG, $OUTPUT;

        $PAGE->add_body_class('limitedwidth');

        // 1. Button to applications.
        if ($instance && !empty($instance->id)) {
            $manageurl = new moodle_url('/enrol/gapplya/manage.php', ['id' => $instance->id, 'courseid' => $instance->courseid]);
            $icon = $OUTPUT->pix_icon('i/users', get_string('applications', 'enrol_gapplya'));
            $button = html_writer::link(
                $manageurl,
                $icon . ' ' . get_string('applications', 'enrol_gapplya'),
                ['class' => 'btn btn-outline-primary float-right mb-3', 'target' => '_self']
            );
            $mform->addElement('html', '<div class="clearfix">' . $button . '</div>');
        }

        if ($instance !== null && $instance->roleid == 0) {
            $instance->roleid = get_config('enrol_gapplya', 'roleid');
        }

        $mform->addElement('text', 'name', get_string('name', 'enrol_gapplya'), ['size' => '100']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'name', 'enrol_gapplya');

        // Prepare Description and JSON.
        $descdefault = '';
        $jsondefault = '';

        if ($instance !== null && !empty($instance->customtext1)) {
            $fulltext = $instance->customtext1;
            $descdefault = \enrol_gapplya\util::clean_description($fulltext);
            $jsondefault = \enrol_gapplya\util::extract_config($fulltext);
        }

        $mform->addElement('editor', 'customtext1', get_string('description', 'enrol_gapplya'), ['rows' => 10]);
        $mform->setType('customtext1', PARAM_RAW);
        $mform->setDefault('customtext1', ['text' => $descdefault, 'format' => FORMAT_HTML]);

        // Settings.
        $mform->addElement('header', 'settingsheader', get_string('settingsheader', 'enrol_gapplya'));

        $mform->addElement('advcheckbox', 'customint1', get_string('requireapplicationtext', 'enrol_gapplya'), null, [0, 1]);
        $mform->setDefault('customint1', 1);
        $mform->addHelpButton('customint1', 'requireapplicationtext', 'enrol_gapplya');

        $mform->addElement('advcheckbox', 'customint3', get_string('showapplicationtext', 'enrol_gapplya'), null, [0, 1]);
        $mform->hideIf('customint3', 'customint1', 'checked');
        $mform->addHelpButton('customint3', 'showapplicationtext', 'enrol_gapplya');

        $mform->addElement('advcheckbox', 'customint2', get_string('requireapplicationfile', 'enrol_gapplya'), null, [0, 1]);
        $mform->setDefault('customint2', 1);
        $mform->addHelpButton('customint2', 'requireapplicationfile', 'enrol_gapplya');

        $mform->addElement('advcheckbox', 'customint4', get_string('showapplicationfile', 'enrol_gapplya'), null, [0, 1]);
        $mform->hideIf('customint4', 'customint2', 'checked');
        $mform->addHelpButton('customint4', 'showapplicationfile', 'enrol_gapplya');

        // Withdrawal settings.
        $mform->addElement('advcheckbox', 'customchar3', get_string('allowwithdrawal', 'enrol_gapplya'), null, null, [0, 1]);

        // Data Retention (Soft vs Hard Delete) stored in customdec1.
        $mform->addElement('advcheckbox', 'customdec1', get_string('keepwithdrawn', 'enrol_gapplya'), null, null, [0, 1]);
        $mform->setDefault('customdec1', 1);
        $mform->addHelpButton('customdec1', 'keepwithdrawn', 'enrol_gapplya');
        $mform->hideIf('customdec1', 'customchar3', 'notchecked');

        // Custom Form JSON.
        $mform->addElement('header', 'customformheader', get_string('customformheader', 'enrol_gapplya'));
        $mform->setExpanded('customformheader', true);

        $mform->addElement(
            'textarea',
            'json_schema_virtual',
            get_string('jsonfields', 'enrol_gapplya'),
            'wrap="virtual" rows="15" cols="60" style="font-family: monospace;"'
        );
        $mform->setType('json_schema_virtual', PARAM_RAW);
        $mform->setDefault('json_schema_virtual', $jsondefault);

        $jsonhelp = '<pre style="font-size: 0.8em; background: #f8f9fa; padding: 10px;">';
        $jsonhelp .= get_string('jsonexample', 'enrol_gapplya') . "\n";
        $jsonhelp .= '[{"type": "text", "name": "phone", "label": "Phone" }]' . "\n";
        $jsonhelp .= '[{"type": "header", "label": "Section Header" }]</pre>';
        $mform->addElement('static', 'json_help', '', $jsonhelp);

        // JSON EXAMPLE BLOCK.
        $jsonexamplestr = '[
            {
                "type": "select",
                "name": "accommodation",
                "label": "Желаете ли настаняване?",
                "options": {
                    "no": "Не",
                    "yes": "Да"
                },
                "default": "no"
            },
            {
                "type": "header",
                "label": "Моля, изберете конкретни дати за нощувка",
                "dependency": {
                    "element": "accommodation",
                    "condition": "eq",
                    "value": "yes"
                }
            },
            {
                "type": "checkbox",
                "name": "date_01",
                "label": "01.02.2026",
                "dependency": {
                    "element": "accommodation",
                    "condition": "eq",
                    "value": "yes"
                }
            },
            {
                "type": "checkbox",
                "name": "date_02",
                "label": "02.02.2026",
                "dependency": {
                    "element": "accommodation",
                    "condition": "eq",
                    "value": "yes"
                }
            }
        ]';
        $jssafeexample = json_encode($jsonexamplestr);
        $jscode = "document.getElementById('id_json_schema_virtual').value = " . $jssafeexample . "; return false;";
        $safeonclick = htmlspecialchars($jscode, ENT_QUOTES);

        $htmldetails = '<div class="form-group row fitem" style="margin-bottom: 0;">';
        $htmldetails .= '<div class="col-md-3"></div>';
        $htmldetails .= '<div class="col-md-9">';
        $htmldetails .= '<div class="d-flex align-items-center mb-2">';
        $htmldetails .= '<details style="width: 100%">';
        $htmldetails .= '<summary style="cursor: pointer; color: #0f6cb5; font-weight: bold; outline: none;">';
        $htmldetails .= '<i class="fa fa-code mr-1"></i> ' . get_string('viewexample', 'enrol_gapplya') . '</summary>';
        $htmldetails .= '<div class="mt-2 text-right">';
        $htmldetails .= '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="' . $safeonclick . '">';
        $htmldetails .= '<i class="fa fa-copy"></i> ' . get_string('copy', 'enrol_gapplya') . ' JSON</button>';
        $htmldetails .= '</div>';
        $htmldetails .= '<pre style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; ';
        $htmldetails .= 'border-radius: 5px; margin-top: 5px; font-size: 0.85rem; color: #212529; white-space: pre-wrap;">';
        $htmldetails .= htmlspecialchars($jsonexamplestr) . '</pre>';
        $htmldetails .= '</details></div></div></div>';

        $mform->addElement('html', $htmldetails);

        // Availability.
        $mform->addElement('header', 'availability', get_string('availability', 'enrol_gapplya'));
        $mform->setExpanded('availability', true);

        $mform->addElement(
            'date_time_selector',
            'customint7',
            get_string('applicationstartdate', 'enrol_gapplya'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'customint8',
            get_string('applicationenddate', 'enrol_gapplya'),
            ['optional' => true]
        );

        // Profile fields.
        $allfields = enrol_gapplya_get_profile_fields();

        $mform->addElement(
            'select',
            'customtext3',
            get_string('profilefields', 'enrol_gapplya'),
            $allfields,
            ['multiple' => 'multiple', 'style' => 'width: 100%;']
        );

        $currentselection = [];
        if (empty($instance->id)) {
            $globalconfig = get_config('enrol_gapplya', 'showuseridentity');
            if ($globalconfig) {
                $currentselection = explode(',', $globalconfig);
            }
        } else {
            if (isset($instance->customtext3) && is_string($instance->customtext3) && $instance->customtext3 !== '') {
                $currentselection = explode(',', $instance->customtext3);
            }
        }
        $mform->setDefault('customtext3', $currentselection);

        $mform->addElement('text', 'customchar1', get_string('availableseats', 'enrol_gapplya'), ['size' => '10']);
        $mform->setType('customchar1', PARAM_INT);
        $mform->addHelpButton('customchar1', 'availableseats', 'enrol_gapplya');
        $mform->setDefault('customchar1', 0);
        $mform->addElement('advcheckbox', 'customchar2', get_string('allowoverenrol', 'enrol_gapplya'), null, [0, 1]);

        // Submission.
        $mform->addElement('header', 'submission', get_string('appattachment', 'enrol_gapplya'));
        $mform->setExpanded('submission', true);
        $options = [];
        for ($i = 1; $i <= 20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'customint5', get_string('maxattachmentnum', 'enrol_gapplya'), $options);
        $choices = get_max_upload_sizes($CFG->maxbytes, $PAGE->course->maxbytes);
        $mform->addElement('select', 'customint6', get_string('maxattachmentsize', 'enrol_gapplya'), $choices);
        $mform->addElement('filetypes', 'customtext2', get_string('acceptedfiletypes', 'enrol_gapplya'));
        $mform->setDefault('customtext2', '.doc .docx .pdf web_image');

        // Enrolment.
        $mform->addElement('header', 'enrolment', get_string('enrolment', 'enrol_gapplya'));
        $mform->setExpanded('enrolment', true);

        $mform->addElement(
            'date_time_selector',
            'enrolstartdate',
            get_string('enrolstartdate', 'enrol_gapplya'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'enrolenddate',
            get_string('enrolenddate', 'enrol_gapplya'),
            ['optional' => true]
        );

        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'enrol_gapplya'), $roles);
        $mform->setDefault('roleid', get_config('enrol_gapplya', 'roleid'));

        // Notification.
        $mform->addElement('header', 'notificationheader', get_string('notifications', 'enrol_gapplya'));
        $mform->setExpanded('notificationheader', true);
        $users = get_enrolled_users($context, 'enrol/gapplya:manage', 0, 'u.*', 'u.lastname ASC', 0, 0, true);
        $formattedusers = [];
        foreach ($users as $id => $user) {
            $formattedusers[$id] = fullname($user);
        }

        $mform->addElement(
            'select',
            'customtext4',
            get_string('notifyusers', 'enrol_gapplya'),
            $formattedusers,
            ['multiple' => 'multiple']
        );
        $mform->addHelpButton('customtext4', 'notifyusers', 'enrol_gapplya');
    }

    /**
     * Validate the instance settings form.
     *
     * @param array $data
     * @param array $files
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];
        if (!empty($data['json_schema_virtual'])) {
            $decoded = json_decode($data['json_schema_virtual']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['json_schema_virtual'] = 'Invalid JSON: ' . json_last_error_msg();
            }
        }
        return $errors;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array|null $fields
     * @return int
     */
    public function add_instance($course, ?array $fields = null) {
        if (!empty($fields) && !empty($fields['expirynotify'])) {
            if ($fields['expirynotify'] == 2) {
                $fields['expirynotify'] = 1;
                $fields['notifyall'] = 1;
            } else {
                $fields['notifyall'] = 0;
            }
        }

        $cleandesc = isset($fields['customtext1']['text']) ? $fields['customtext1']['text'] : '';
        if (empty($cleandesc)) {
            $cleandesc = isset($fields['customtext1']) ? $fields['customtext1'] : '';
        }

        $jsonconfig = isset($fields['json_schema_virtual']) ? $fields['json_schema_virtual'] : '';
        $mergeddesc = \enrol_gapplya\util::append_config($cleandesc, $jsonconfig);

        if (isset($fields['customtext1']) && is_array($fields['customtext1'])) {
            $fields['customtext1']['text'] = $mergeddesc;
            $fields['customtext1'] = $mergeddesc;
        } else {
            $fields['customtext1'] = $mergeddesc;
        }

        if (isset($fields['customtext3']) && is_array($fields['customtext3'])) {
            $fields['customtext3'] = implode(',', $fields['customtext3']);
        } else {
            $fields['customtext3'] = $fields['customtext3'] ?? '';
        }

        if (isset($fields['customtext4']) && is_array($fields['customtext4'])) {
            $fields['customtext4'] = implode(',', $fields['customtext4']);
        } else {
            $fields['customtext4'] = $fields['customtext4'] ?? '';
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data
     * @return bool
     */
    public function update_instance($instance, $data) {
        $cleandesc = isset($data->customtext1['text']) ? $data->customtext1['text'] : '';
        if (empty($cleandesc)) {
            $cleandesc = isset($data->customtext1) ? $data->customtext1 : '';
        }

        $jsonconfig = isset($data->json_schema_virtual) ? $data->json_schema_virtual : '';
        $data->customtext1 = \enrol_gapplya\util::append_config($cleandesc, $jsonconfig);

        $data->customtext3 = is_array($data->customtext3) ? implode(',', $data->customtext3) : $data->customtext3;
        $data->customtext4 = is_array($data->customtext4) ? implode(',', $data->customtext4) : $data->customtext4;

        return parent::update_instance($instance, $data);
    }

    /**
     * Renders the frontend enrol UI.
     *
     * @param stdClass $instance
     * @return string|null
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT, $DB, $USER, $PAGE, $SESSION;

        if (isguestuser()) {
            return null;
        }

        $record = $DB->get_record('enrol_gapplya', ['instance' => $instance->id, 'userid' => $USER->id]);
        $notification = '';

        if ($record && $record->status !== 'withdrawn') {
            $recordcontext = [];
            $recordcontext['instance'] = $instance->id;
            $timeapplied = userdate($record->timecreated);
            $recordcontext['time'] = $timeapplied;

            if ($record->status == 'new') {
                $recordcontext['new'] = true;
            } else if ($record->status == 'waitlisted') {
                $recordcontext['waitlisted'] = true;
            } else if ($record->status == 'rejected') {
                $recordcontext['rejected'] = true;
            } else if ($record->status == 'approved') {
                $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $USER->id]);
                if ($enrolment) {
                    if ($enrolment->status == ENROL_USER_ACTIVE && $enrolment->timestart >= time() && $enrolment->timestart != 0) {
                        $recordcontext['approved'] = true;
                        $recordcontext['enrolmentstart'] = userdate($enrolment->timestart);
                    } else if ($enrolment->status == ENROL_USER_ACTIVE &&
                               $enrolment->timeend < time() && $enrolment->timeend != 0) {
                        $recordcontext['expired'] = true;
                        $recordcontext['enrolmentend'] = userdate($enrolment->timeend);
                    } else if ($enrolment->status == ENROL_USER_SUSPENDED) {
                        $recordcontext['suspended'] = true;
                    }
                } else {
                    $recordcontext['noenrolment'] = true;
                }
            }

            // Construct content.
            $finalhtml = '';

            // 1. TEXT
            if (!empty($record->applytext)) {
                $finalhtml .= format_text($record->applytext, FORMAT_HTML);
            }

            // 2. FILES
            $fs = get_file_storage();
            $contextid = context_course::instance($instance->courseid)->id;
            $itemid = $instance->id . $record->userid;
            $files = $fs->get_area_files($contextid, 'enrol_gapplya', 'applyfile', $itemid, 'filename', false);

            if ($files) {
                $finalhtml .= '<div class="mt-3 pt-2 border-top">';
                $finalhtml .= '<h6 class="font-weight-bold text-muted mb-2">';
                $finalhtml .= get_string('appattachment', 'enrol_gapplya') . ':</h6>';

                foreach ($files as $file) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out();

                    $mimetype = $file->get_mimetype();
                    $finalhtml .= html_writer::link(
                        'javascript:void(0)',
                        '<i class="fa fa-download"></i> ' . $file->get_filename(),
                        [
                            'class' => 'btn btn-sm btn-outline-secondary mr-2 mb-1 bg-white attachmentlink',
                            'data-type' => $mimetype,
                            'data-url' => $url,
                        ]
                    );
                }
                $finalhtml .= '</div>';
            }

            // 3. ADDITIONAL QUESTIONS
            $schema = \enrol_gapplya\util::get_application_schema($instance);
            $jsondata = !empty($record->json_data) ? json_decode($record->json_data, true) : [];

            if (!empty($schema)) {
                $finalhtml .= '<div class="mt-3 pt-2 border-top">';
                $finalhtml .= '<table class="table table-bordered table-sm table-striped mb-0">';
                $finalhtml .= '<tbody>';
                foreach ($schema as $field) {
                    if ($field['type'] == 'header') {
                        continue;
                    }
                    $label = s($field['label']);
                    $val = isset($jsondata[$field['label']]) ? $jsondata[$field['label']] : '-';
                    $displayval = $val;
                    if ($field['type'] == 'select' && isset($field['options'][$val])) {
                        $displayval = $field['options'][$val];
                    }
                    if ($field['type'] == 'checkbox') {
                        $displayval = ($val == 'yes' || $val == '1' || $val == 'on') ? get_string('yes', 'core') : '-';
                    }
                    $finalhtml .= '<tr><th style="width: 40%; font-weight: normal; color: #555;">' . $label . '</th>';
                    $finalhtml .= '<td>' . nl2br(s($displayval)) . '</td></tr>';
                }
                $finalhtml .= '</tbody></table></div>';
            }

            $recordcontext['applytext'] = $finalhtml;
            if (!empty($finalhtml)) {
                $recordcontext['hasapplication'] = true;
            }
            $recordcontext['courseid'] = (int)$instance->courseid;
            $recordcontext['sesskey'] = sesskey();

            if ($instance->customchar3 == 1 && $record->status == 'new') {
                $recordcontext['allowwithdrawal'] = true;
            }

            $output = $OUTPUT->render_from_template('enrol_gapplya/applicationstatus', $recordcontext);

            $formclass = 'enrol_gapplya\form\defaultform';
            $form = new $formclass(
                null,
                ['instance' => $instance, 'output' => $output],
                'post',
                new moodle_url('/enrol/index.php', ['id' => (int)$instance->courseid]),
                ['class' => 'enrolgapplyform']
            );

            if (optional_param('withdraw', 0, PARAM_INT) && confirm_sesskey()) {
                $this->unenrol_user($instance, $USER->id);

                redirect(
                    new moodle_url('/enrol/index.php', ['id' => (int)$instance->courseid]),
                    get_string('applicationwithdrawnsuccess', 'enrol_gapplya'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

            $jsreqs = [
                'cannotopenfile', 'cannotopenpdffile', 'download', 'close', 'withdraw',
                'withdrawapplication', 'withdrawapplicationconfirm', 'applicationwithdrawnsuccess', 'anerroroccurred',
            ];
            $PAGE->requires->strings_for_js($jsreqs, 'enrol_gapplya');

            $return = html_writer::start_tag('div', ['class' => 'box py-3 generalbox']);
            $return .= $form->render();
            $return .= html_writer::end_tag('div');
            return $return;

        } else if ($record && $record->status === 'withdrawn') {
            $msg = get_string('previouslywithdrawn', 'enrol_gapplya', userdate($record->timemodified));
            $notification = $OUTPUT->notification($msg, 'warning');
        }

        $enrolledusers = count_enrolled_users(context_course::instance($instance->courseid), 'mod/assign:submit');
        if ($instance->customchar1 > 0 && $enrolledusers >= $instance->customchar1 && !$instance->customchar2) {
            $tplparams = ['full' => true, 'availableseats' => $instance->customchar1];
            return $OUTPUT->render_from_template('enrol_gapplya/status', $tplparams);
        }
        if ($instance->customint7 > 0 && $instance->customint7 > time()) {
            $msg = get_string('notavailableyet', 'enrol_gapplya', userdate($instance->customint7));
            return $OUTPUT->render_from_template('enrol_gapplya/status', ['notavailableyet' => true, 'time' => $msg]);
        }
        if ($instance->customint8 > 0 && $instance->customint8 < time()) {
            $msg = get_string('notavailableanymore', 'enrol_gapplya', userdate($instance->customint8));
            return $OUTPUT->render_from_template('enrol_gapplya/status', ['notavailableanymore' => true, 'time' => $msg]);
        }

        // Profile fields check hook.
        if (!empty($instance->customtext3)) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $profilefields = explode(",", $instance->customtext3);
            $missingfields = [];
            $profiledata = profile_user_record($USER->id);
            $fields = profile_get_custom_fields();

            foreach ($profilefields as $profilefield) {
                if (strpos($profilefield, "profile_field_") !== false) {
                    $profilefield = str_replace("profile_field_", "", $profilefield);
                    $profilefielda = array_filter($fields, function ($var) use ($profilefield) {
                        return $var->shortname == $profilefield;
                    });

                    if (!empty($profilefielda)) {
                        $profilefielda = array_values($profilefielda)[0];
                        if ($profilefielda->datatype == "checkbox") {
                            $profilefieldvalue = isset($profiledata->$profilefield) && $profiledata->$profilefield == 1 ? 1 : '';
                        } else {
                            $profilefieldvalue = isset($profiledata->$profilefield) ? $profiledata->$profilefield : '';
                        }

                        // Check if empty, but allow '0' as valid.
                        if ($profilefieldvalue === '' || $profilefieldvalue === null) {
                            $missingfields[] = format_text($profilefielda->name, FORMAT_HTML);
                        }
                    }
                } else {
                    if ($profilefield == 'picture' && $USER->$profilefield == 0) {
                        $missingfields[] = get_string('pictureofuser', 'core');
                    } else if (empty($USER->$profilefield) && $USER->$profilefield !== '0' && $USER->$profilefield !== 0) {
                        $missingfields[] = get_string($profilefield, 'core');
                    }
                }
            }

            if (!empty($missingfields)) {
                $contextdata = ['hasfields' => true, 'notready' => true];
                if (count($missingfields) >= 1) {
                    $contextdata['editprofile'] = new moodle_url("/user/edit.php", ["id" => $USER->id, 'returnto' => $PAGE->url]);
                }
                $mfields = [];
                foreach ($missingfields as $missingfield) {
                    $mfields[] = ['name' => $missingfield];
                }
                $contextdata['fields'] = $mfields;
                return $OUTPUT->render_from_template('enrol_gapplya/status', $contextdata);
            }
        }

        require_once($CFG->dirroot . '/enrol/gapplya/enrol_form.php');
        $mform = new enrol_gapplya_form(null, ['instance' => $instance], 'post', '', ['class' => 'enrolgapplyform']);

        if ($mform->is_cancelled()) {
            return false;
        } else if ($data = $mform->get_data()) {

            // Transaction start for data integrity.
            $transaction = $DB->start_delegated_transaction();

            try {
                $data->userid = $USER->id;
                $data->instance = $instance->id;
                $data->courseid = $instance->courseid;
                $data->usermodified = $USER->id;
                if (isset($data->id)) {
                    unset($data->id);
                }

                $schema = \enrol_gapplya\util::get_application_schema($instance);
                $customresponses = [];
                foreach ($schema as $field) {
                    if ($field['type'] == 'header') {
                        continue;
                    }
                    $key = 'custom_' . $field['name'];
                    if (isset($data->$key)) {
                        $val = $data->$key;
                        if ($field['type'] == 'date_selector') {
                            $val = userdate($val, get_string('strftimedate', 'core_langconfig'));
                        } else if ($field['type'] == 'checkbox') {
                            $val = $val ? 'yes' : 'no';
                        }
                        $customresponses[$field['label']] = $val;
                    }
                }
                $data->json_data = json_encode($customresponses, JSON_UNESCAPED_UNICODE);

                $data->applytext = isset($data->applytext) ? $data->applytext['text'] : '';
                $data->format = 1;
                $data->status = 'new';
                $data->timemodified = time();
                $data->timecreated = time();

                if ($record) {
                    $data->id = $record->id;
                    $DB->update_record('enrol_gapplya', $data);

                    // Clear old files before saving new ones.
                    $this->delete_application_files($instance->id, $data->userid, $instance->courseid);
                } else {
                    $DB->insert_record('enrol_gapplya', $data);
                }

                $filecontext = context_course::instance($instance->courseid);
                if (!empty($data->applyfile)) {
                    $fileops = [
                        'subdirs' => 0,
                        'maxbytes' => $instance->customint6,
                        'maxfiles' => $instance->customint5,
                    ];
                    $itemid = $instance->id . $data->userid;
                    file_save_draft_area_files($data->applyfile, $filecontext->id, 'enrol_gapplya', 'applyfile', $itemid, $fileops);
                }

                $course = get_course($instance->courseid);
                $coursecontacts = [];
                if (!empty($instance->customtext4)) {
                    $coursecontacts = explode(',', $instance->customtext4);
                    $coursecontacts = array_filter($coursecontacts, function ($contact) use ($filecontext) {
                        return is_enrolled($filecontext, $contact, 'enrol/gapplya:manage');
                    });
                    if (!empty($coursecontacts)) {
                        [$insql, $inparams] = $DB->get_in_or_equal($coursecontacts, SQL_PARAMS_NAMED, 'id');
                        $coursecontacts = $DB->get_records_sql("SELECT u.* FROM {user} u WHERE u.id $insql", $inparams);
                    }
                } else {
                    $courseelement = new core_course_list_element($course);
                    if ($courseelement->has_course_contacts()) {
                        $coursecontacts = $courseelement->get_course_contacts();
                    }
                }

                if ($coursecontacts) {
                    $message = new stdClass();
                    $message->subject = get_string('newapplicationfor', 'enrol_gapplya', format_string($course->fullname));

                    $msgparams = ['coursefullname' => format_string($course->fullname), 'username' => fullname($USER)];
                    $message->text = get_string('newapplicationtext', 'enrol_gapplya', $msgparams);

                    $message->contexturl = new moodle_url('/enrol/gapplya/manage.php', ['id' => $instance->id]);
                    $message->contexturlname = get_string('manageapplications', 'enrol_gapplya');
                    $currentlang = current_language();

                    foreach ($coursecontacts as $coursecontact) {
                        $contactobj = (object)$coursecontact;
                        $preferredlang = isset($contactobj->lang) ? $contactobj->lang : $currentlang;
                        if (get_config('enrol_gapplya', 'sendnotificationinrecipientlang')) {
                            $SESSION->lang = $preferredlang;
                            $message->subject = get_string('newapplicationfor', 'enrol_gapplya', format_string($course->fullname));
                            $message->text = get_string('newapplicationtext', 'enrol_gapplya', $msgparams);
                            $message->contexturlname = get_string('manageapplications', 'enrol_gapplya');
                        }
                        \enrol_gapplya\util::send_notification($contactobj, $USER, $message);
                    }
                    $SESSION->lang = $currentlang;
                }

                // USER CONFIRMATION (OPTIONAL).
                if (!empty($data->sendcopy)) {
                    $noreplyuser = core_user::get_noreply_user();
                    $confmsg = new stdClass();
                    $confmsg->subject = get_string('application', 'enrol_gapplya') . ': ' . format_string($course->fullname);
                    $confmsg->text = get_string('alreadyapplied', 'enrol_gapplya', userdate(time()));
                    $confmsg->contexturl = new moodle_url('/enrol/gapplya/manage.php', ['id' => $instance->id]);
                    $confmsg->contexturlname = get_string('applicationdetails', 'enrol_gapplya');
                    \enrol_gapplya\util::send_notification($USER, $noreplyuser, $confmsg);
                }

                $transaction->allow_commit();

            } catch (Exception $e) {
                $transaction->rollback($e);
                throw new moodle_exception('anerroroccurred', 'enrol_gapplya');
            }

            redirect(new moodle_url('/enrol/index.php', ['id' => $instance->courseid]));
        } else {
            $output = $mform->render();
            return $notification . $OUTPUT->box($output);
        }
    }

    /**
     * Helper to send notification.
     *
     * @param stdClass $user
     * @param stdClass $userfrom
     * @param stdClass|null $msg
     * @return bool
     */
    public function send_notification(stdClass $user, $userfrom, $msg = null) {
        return \enrol_gapplya\util::send_notification($user, $userfrom, $msg);
    }

    /**
     * Check if a new instance can be added to the course.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'gapplya'], '*', IGNORE_MISSING);
        return $instance ? false : true;
    }

    /**
     * Get bulk operations for the enrollment manager.
     *
     * @param course_enrolment_manager $manager
     * @return array
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        $context = $manager->get_context();
        $bulkoperations = [];
        if (has_capability("enrol/gapplya:manage", $context)) {
            $bulkoperations['editselectedusers'] = new enrol_gapplya_editselectedusers_operation($manager, $this);
        }
        if (has_capability("enrol/gapplya:unenrol", $context)) {
            $bulkoperations['deleteselectedusers'] = new enrol_gapplya_deleteselectedusers_operation($manager, $this);
        }
        return $bulkoperations;
    }

    /**
     * Helper to delete all files associated with a specific application record.
     *
     * @param int $instanceid Enrolment instance ID.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return void
     */
    protected function delete_application_files(int $instanceid, int $userid, int $courseid) {
        $fs = get_file_storage();
        $context = context_course::instance($courseid);

        $itemid = (string)$instanceid . (string)$userid;
        $fs->delete_area_files($context->id, 'enrol_gapplya', 'applyfile', $itemid);
    }

    /**
     * Unenrol a user from the course.
     *
     * @param stdClass $instance The enrolment instance.
     * @param int $userid The user ID.
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;

        // 1. Core Cleanup.
        parent::unenrol_user($instance, $userid);

        // 2. Fetch Plugin Data.
        $courseid = $instance->courseid;
        $gapplyarecord = $DB->get_record('enrol_gapplya', ['userid' => $userid, 'courseid' => $courseid]);

        if ($gapplyarecord) {
            // 3. Determine Data Retention Policy.
            $keeprecords = isset($instance->customdec1) ? (int)$instance->customdec1 : 1;

            if ($keeprecords) {
                $update = new stdClass();
                $update->id = $gapplyarecord->id;
                $update->status = 'withdrawn';
                $update->timemodified = time();
                $DB->update_record('enrol_gapplya', $update);

                \enrol_gapplya\util::add_history($gapplyarecord->id, 'withdrawn', $userid, '');
            } else {
                $this->delete_application_files($instance->id, $userid, $courseid);
                $DB->delete_records('enrol_gapplya', ['id' => $gapplyarecord->id]);
            }
        }
    }

    /**
     * Adds a default instance to a new course.
     *
     * @param stdClass $course
     * @return int
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     *
     * @return array
     */
    public function get_instance_defaults() {
        $fields = [];
        $fields['name'] = get_string('pluginname', 'enrol_gapplya');
        $fields['customtext1'] = ['text' => ''];

        $globalconfig = get_config('enrol_gapplya', 'profile_fields');
        $fields['customtext3'] = (!empty($globalconfig)) ? explode(',', $globalconfig) : [];

        $fields['customint1'] = 0;
        $fields['customint2'] = 0;
        $fields['customtext2'] = '.doc .docx .pdf web_image';
        $fields['customtext4'] = '';
        $fields['customint6'] = 1048576;
        $fields['roleid'] = get_config('enrol_gapplya', 'roleid');
        $fields['customchar3'] = 0; // Allow withdrawal.
        $fields['customdec1'] = 1; // Keep withdrawn apps by default.

        return $fields;
    }

    /**
     * Can hide/show the instance.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return true;
    }

    /**
     * Can delete the instance.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/gapplya:config', $context);
    }

    /**
     * Delete the enrolment instance.
     *
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        $records = $DB->get_records('enrol_gapplya', ['instance' => $instance->id]);
        foreach ($records as $record) {
            $this->delete_application_files($instance->id, $record->userid, $instance->courseid);
        }

        $DB->delete_records('enrol_gapplya', ['instance' => $instance->id]);

        return parent::delete_instance($instance);
    }
}

/**
 * File serving callback.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @package enrol_gapplya
 */
function enrol_gapplya_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    // 1. Ditch the course requirement. The user is just applying, not enrolled!
    require_login();

    if ($context->contextlevel == CONTEXT_COURSE && ($filearea === 'applyfile')) {
        $itemid = array_shift($args); // This is instanceid . userid concatenated.
        $filename = array_pop($args);
        $filepath = !$args ? '/' : '/' . implode('/', $args) . '/';

        // 2. Security Check: Can they manage applications?
        if (!has_capability('enrol/gapplya:manage', $context)) {
            // 3. If not a manager, they can ONLY view their own files.
            $canaccess = false;

            // Check if the itemid ends with their own userid.
            $useridstr = (string)$USER->id;
            if (substr((string)$itemid, -strlen($useridstr)) === $useridstr) {
                 $canaccess = true;
            }

            if (!$canaccess) {
                send_file_not_found();
            }
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'enrol_gapplya', $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            return false;
        }

        \core\session\manager::write_close();
        send_stored_file($file, null, 0, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Extends the course navigation node.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 * @package enrol_gapplya
 */
function enrol_gapplya_extend_navigation_course(\navigation_node $navigation, \stdClass $course, \context $context) {
    global $DB;

    if (!has_capability('enrol/gapplya:manage', $context)) {
        return;
    }

    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'gapplya', 'status' => 0], '*', IGNORE_MISSING);
    if (!$instance) {
        return;
    }

    $url = new moodle_url('/enrol/gapplya/manage.php', ['id' => $instance->id, 'courseid' => $course->id]);
    $navigation->add(
        get_string('enrolmentapplications', 'enrol_gapplya'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        null,
        new pix_icon('i/report', 'core')
    );
}

/**
 * Helper function to generate status UI buttons.
 *
 * @param string $currentstatus The current application status.
 * @param int $recordid The application record ID.
 * @param string $format The output format ('dropdown' or 'modal').
 * @return string HTML string of the buttons.
 * @package enrol_gapplya
 */
function enrol_gapplya_get_status_buttons($currentstatus, $recordid, $format = 'dropdown') {
    if ($currentstatus === 'approved') {
        return '';
    }

    $actions = [
        'approve'  => ['target_status' => 'approved',   'icon' => 'fa-check',   'color' => 'success'],
        'waitlist' => ['target_status' => 'waitlisted', 'icon' => 'fa-clock-o', 'color' => 'info'],
        'reject'   => ['target_status' => 'rejected',   'icon' => 'fa-times',   'color' => 'warning'],
        'withdraw' => ['target_status' => 'withdrawn',  'icon' => 'fa-ban',     'color' => 'secondary'],
    ];

    $html = '';

    foreach ($actions as $action => $data) {
        if ($currentstatus === $data['target_status']) {
            continue;
        }

        $label = get_string($action, 'enrol_gapplya');

        if ($format === 'dropdown') {
            $html .= html_writer::link(
                'javascript:void(0)',
                '<i class="icon fa ' . $data['icon'] . ' fa-fw"></i> ' . $label,
                [
                    'class' => 'dropdown-item menu-action action-button',
                    'data-action' => $action,
                    'data-id' => $recordid,
                ]
            );
        } else {
            $html .= '<button type="button" class="btn btn-' . $data['color'] . ' action-button mr-2" ' .
                     'data-action="' . $action . '" data-id="' . $recordid . '" data-dismiss="modal">';
            $html .= '<i class="fa ' . $data['icon'] . ' mr-1"></i> ' . $label;
            $html .= '</button>';
        }
    }

    return $html;
}

/**
 * Returns an array of all available user profile fields (standard + custom).
 *
 * This function is used both in global plugin settings and in the course instance form.
 *
 * @package    enrol_gapplya
 * @return     array An associative array of field names and their translated labels.
 */
function enrol_gapplya_get_profile_fields() {
    global $CFG;
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $allfields = [];
    $standardfields = [
        'firstname', 'middlename', 'lastname', 'email', 'city', 'country',
        'phone1', 'phone2', 'address', 'institution', 'department',
    ];

    foreach ($standardfields as $std) {
        $allfields[$std] = get_string($std, 'core');
    }
    $allfields['picture'] = get_string('pictureofuser', 'core');

    $customfields = profile_get_custom_fields();
    if ($customfields) {
        foreach ($customfields as $cf) {
            if ((int)$cf->visible !== 0) {
                $allfields['profile_field_' . $cf->shortname] = format_string($cf->name);
            }
        }
    }

    asort($allfields);
    return $allfields;
}

