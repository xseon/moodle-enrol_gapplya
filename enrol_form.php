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
 * Application form class for enrol_gapplya.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The custom enrolment application form.
 *
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_gapplya_form extends moodleform {

    /**
     * Define the form structure.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;
        $instance = $this->_customdata['instance'];

        $mform->addElement('hidden', 'id', $instance->courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'header', get_string('application', 'enrol_gapplya'));

        if (!empty($instance->customtext1)) {
            $cleantext = \enrol_gapplya\util::clean_description($instance->customtext1);
            $mform->addElement('html', $cleantext);
        }

        // 1. Dynamic fields.
        $schema = \enrol_gapplya\util::get_application_schema($instance);

        if (!empty($schema)) {
            foreach ($schema as $field) {
                $key = isset($field['name']) ? $field['name'] : uniqid('h_');
                $elname = 'custom_' . $key;
                $label = isset($field['label']) ? format_string($field['label']) : '';

                switch ($field['type']) {
                    case 'header':
                        $mform->addElement('static', $elname, '', '<h4 class="mt-3">' . $label . '</h4>');
                        break;

                    case 'text':
                        $mform->addElement('text', $elname, $label);
                        $mform->setType($elname, PARAM_TEXT);
                        break;

                    case 'textarea':
                        $mform->addElement('textarea', $elname, $label, 'wrap="virtual" rows="5" cols="50"');
                        $mform->setType($elname, PARAM_TEXT);
                        break;

                    case 'select':
                        $options = isset($field['options']) ? $field['options'] : [];
                        $mform->addElement('select', $elname, $label, $options);
                        if (isset($field['default'])) {
                            $mform->setDefault($elname, $field['default']);
                        }
                        break;

                    case 'date_selector':
                        $mform->addElement('date_selector', $elname, $label);
                        $mform->setType($elname, PARAM_INT);

                        if (isset($field['default'])) {
                            $defaultts = is_numeric($field['default']) ? (int)$field['default'] : strtotime($field['default']);
                            if ($defaultts) {
                                $mform->setDefault($elname, $defaultts);
                            }
                        }
                        break;

                    case 'checkbox':
                        $mform->addElement('advcheckbox', $elname, $label, '', [], [0, 1]);
                        if (isset($field['default']) && ($field['default'] == 'yes' || $field['default'] == true)) {
                            $mform->setDefault($elname, 1);
                        }
                        break;
                }

                if (!empty($field['description']) && $field['type'] !== 'header') {
                    $desc = '<small class="text-muted">' . format_text($field['description'], FORMAT_HTML) . '</small>';
                    $mform->addElement('static', $elname . '_desc', '', $desc);
                }

                if (!empty($field['required'])) {
                    $mform->addRule($elname, get_string('required', 'core'), 'required', null, 'client');
                }

                if (!empty($field['dependency'])) {
                    $dep = $field['dependency'];
                    $parent = 'custom_' . $dep['element'];
                    $value = $dep['value'];
                    $condition = isset($dep['condition']) ? $dep['condition'] : 'eq';

                    if ($condition == 'eq') {
                        $mform->hideIf($elname, $parent, 'neq', $value);
                        if (!empty($field['description'])) {
                            $mform->hideIf($elname . '_desc', $parent, 'neq', $value);
                        }
                    } else if ($condition == 'neq') {
                        $mform->hideIf($elname, $parent, 'eq', $value);
                        if (!empty($field['description'])) {
                            $mform->hideIf($elname . '_desc', $parent, 'eq', $value);
                        }
                    }
                }
            }
        }

        // 2. Standard fields.
        if ($instance->customint1) {
            $mform->addElement('editor', 'applytext', get_string('applicationtext', 'enrol_gapplya'));
            $mform->setType('applytext', PARAM_RAW);
            $mform->addRule('applytext', get_string('required', 'core'), 'required', null, 'client');
        } else if ($instance->customint3) {
            $mform->addElement('editor', 'applytext', get_string('applicationtext', 'enrol_gapplya'));
            $mform->setType('applytext', PARAM_RAW);
        }

        $fileoptions = [
            'subdirs' => 0,
            'maxbytes' => $instance->customint6,
            'maxfiles' => $instance->customint5,
            'accepted_types' => $instance->customtext2,
        ];

        if ($instance->customint2) {
            $mform->addElement('filemanager', 'applyfile', get_string('appattachment', 'enrol_gapplya'), null, $fileoptions);
            $mform->addRule('applyfile', get_string('required', 'core'), 'required', null, 'client');
        } else if ($instance->customint4) {
            $mform->addElement('filemanager', 'applyfile', get_string('appattachment', 'enrol_gapplya'), null, $fileoptions);
        }

        $mform->addElement('checkbox', 'sendcopy', get_string('sendcopy', 'enrol_gapplya'));
        $mform->setDefault('sendcopy', 0);

        $this->add_action_buttons(false, get_string('apply', 'enrol_gapplya'));
    }

    /**
     * Server-side validation of the form data.
     *
     * @param array $data
     * @param array $files
     * @return array Array of errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $instance = $this->_customdata['instance'];

        if ($instance->customint2) {
            $draftitemid = isset($data['applyfile']) ? $data['applyfile'] : 0;
            if (empty($draftitemid)) {
                $errors['applyfile'] = get_string('required', 'core');
            } else {
                global $USER;
                $usercontext = context_user::instance($USER->id);
                $fs = get_file_storage();
                $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
                if (count($files) < 1) {
                    $errors['applyfile'] = get_string('required', 'core');
                }
            }
        }

        if ($instance->customint1) {
            $text = isset($data['applytext']['text']) ? $data['applytext']['text'] : '';
            if (trim(strip_tags($text)) === '') {
                $errors['applytext'] = get_string('required', 'core');
            }
        }

        $schema = \enrol_gapplya\util::get_application_schema($instance);
        if (!empty($schema)) {
            foreach ($schema as $field) {
                if ($field['type'] == 'header') {
                    continue;
                }
                if (!empty($field['dependency'])) {
                    continue;
                }

                if (!empty($field['required'])) {
                    $key = 'custom_' . $field['name'];
                    if (empty($data[$key]) && $data[$key] !== '0' && $data[$key] !== 0) {
                        $errors[$key] = get_string('required', 'core');
                    }
                }
            }
        }

        return $errors;
    }
}

