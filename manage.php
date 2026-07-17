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
 * Manage enrollment applications.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/gapplya/lib.php');
require_once($CFG->libdir . '/enrollib.php');

// 1. INPUTS & SECURITY
$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'new', PARAM_ALPHA);

// Load instance with strict type check.
$instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'gapplya'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('enrol/gapplya:manage', $context);

// 2. VALIDATION OF TAB PARAMETER
$allowedtabs = ['new', 'approved', 'waitlisted', 'withdrawn', 'rejected', 'all', 'edit'];
if (!in_array($tab, $allowedtabs)) {
    $tab = 'new';
}

// 3. PAGE SETUP
$PAGE->set_url('/enrol/gapplya/manage.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageapplications', 'enrol_gapplya'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Load DataTables CSS.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/enrol/gapplya/libraries/DataTables/datatables.min.css'));

// 4. LOAD STRINGS FOR JS
$stringman = get_string_manager();
$strings = $stringman->load_component_strings('enrol_gapplya', current_language());
$jsstrings = array_keys($strings);
array_push($jsstrings, 'yes', 'no', 'all', 'copy', 'csv', 'excel', 'columns', 'filter', 'sort', 'recordsperpage');
$PAGE->requires->strings_for_js($jsstrings, 'enrol_gapplya');

// 5. DATA GATHERING (Status Counts)
$sqlcounts = "SELECT status, COUNT(id) AS count
               FROM {enrol_gapplya}
               WHERE instance = :instanceid
               GROUP BY status";
$rawcounts = $DB->get_records_sql($sqlcounts, ['instanceid' => $id]);

$statuscounts = array_fill_keys(['new', 'approved', 'waitlisted', 'rejected', 'withdrawn'], 0);
$totalcount = 0;

foreach ($rawcounts as $data) {
    if (isset($statuscounts[$data->status])) {
        $statuscounts[$data->status] = $data->count;
    }
    $totalcount += $data->count;
}
$statuscounts['all'] = $totalcount;

// 6. TABS CONSTRUCTION
ob_start();
$tabs = [];

// Base statuses.
$statusestoshow = ['new', 'approved', 'waitlisted', 'rejected'];

// Logic to conditionally show 'withdrawn' tab.
$keepwithdrawn = isset($instance->customdec1) ? (int)$instance->customdec1 : 1;
if ($keepwithdrawn) {
    $statusestoshow[] = 'withdrawn';
}

$statusestoshow[] = 'all';

foreach ($statusestoshow as $status) {
    $count = $statuscounts[$status];
    $icon = enrol_gapplya_get_status_icon($status); // Calling renamed function.

    $labelstr = get_string($status, 'enrol_gapplya');
    $counthtml = $count > 0 ? ' <span class="badge badge-secondary ml-1">' . $count . '</span>' : '';
    $labelhtml = '<i class="fa ' . $icon . '"></i> ' . $labelstr . $counthtml;

    $tabs[] = new tabobject(
        $status,
        new moodle_url('/enrol/gapplya/manage.php', ['id' => $id, 'tab' => $status]),
        $labelhtml,
        ' '
    );
}

// Edit tab.
if (has_capability('enrol/gapplya:config', $context)) {
    $tabs[] = new tabobject(
        'edit',
        new moodle_url('/enrol/editinstance.php', [
            'id' => $id,
            'courseid' => $course->id,
            'type' => 'gapplya',
            'returnurl' => new moodle_url('/enrol/gapplya/manage.php', ['id' => $id, 'tab' => $tab]),
        ]),
        '<i class="fa fa-cog"></i> ' . get_string('settings', 'enrol_gapplya'),
        ' '
    );
}

print_tabs([$tabs], $tab);
$tabshtml = ob_get_clean();

// 7. OUTPUT START
echo $OUTPUT->header();

// 8. SEATS INFO BLOCK
$enrolled = count_enrolled_users($context, 'mod/assign:submit');
$seats = $instance->customchar1 > 0 ? $instance->customchar1 : get_string('unlimitedseats', 'enrol_gapplya');

echo html_writer::div(
    get_string('seatsinfo', 'enrol_gapplya', ['enrolled' => $enrolled, 'seats' => $seats]),
    'alert alert-light border px-3 py-2 text-muted mb-3'
);

echo $tabshtml;

// 9. RENDER TABLE
echo html_writer::start_div('container-fluid p-0');

$tablehtml = enrol_gapplya_render_application_table($instance, $DB, $tab);

// Wrapper for Approved status logic in JS.
$tableclass = ($tab === 'approved') ? 'approved' : '';
echo html_writer::div($tablehtml, $tableclass);

echo html_writer::end_div();

// 10. FOOTER & JS INIT
$PAGE->requires->js_call_amd('enrol_gapplya/custom', 'init', ['tab' => $tab, 'id' => $id]);
echo $OUTPUT->footer();

// Helper functions.

/**
 * Returns the FontAwesome icon class for a given status.
 *
 * @param string $status
 * @return string
 * @package enrol_gapplya
 */
function enrol_gapplya_get_status_icon($status) {
    switch ($status) {
        case 'new':
            return 'fa-inbox';
        case 'approved':
            return 'fa-check';
        case 'waitlisted':
            return 'fa-clock-o';
        case 'rejected':
            return 'fa-times';
        case 'withdrawn':
            return 'fa-ban';
        case 'all':
            return 'fa-list';
        default:
            return 'fa-circle-o';
    }
}

/**
 * Builds the HTML table structure.
 *
 * @param stdClass $instance
 * @param moodle_database $DB
 * @param string $tab
 * @return string
 * @package enrol_gapplya
 */
function enrol_gapplya_render_application_table($instance, $DB, $tab) {
    $html = '<table id="gapplytable" class="table table-hover table-striped w-100 generaltable d-none" data-instance="' .
        $instance->id . '">';
    $html .= '<thead><tr>';

    // 1. Checkbox
    $html .= '<th class="noorder col-checkbox"><input type="checkbox" id="selectall"></th>';

    // 2. ID (Hidden internally, never visible)
    $html .= '<th class="d-none inv">ID</th>';

    // 3. User (Full Name) - Fixed column, not in menu
    $html .= '<th class="inv d-none export-only firstname">' . get_string('firstname', 'core') . '</th>';
    $html .= '<th class="inv d-none export-only middlename">' . get_string('middlename', 'core') . '</th>';
    $html .= '<th class="inv d-none export-only lastname">' . get_string('lastname', 'core') . '</th>';
    $html .= '<th class="userdetails col-user">' . get_string('user', 'enrol_gapplya') . '</th>';

    // 4. Dynamic Identity Fields (Selected in settings)
    $extrafields = [];
    if (!empty($instance->customtext3)) {
        $extrafields = explode(',', $instance->customtext3);
    } else {
        $configfields = get_config('enrol_gapplya', 'showuseridentity');
        if ($configfields) {
            $extrafields = explode(',', $configfields);
        }
    }

    $extrafields = array_diff($extrafields, ['firstname', 'lastname', 'picture']);

    $allprofilefields = [];
    $stdfields = ['email', 'city', 'country', 'phone1', 'phone2', 'department', 'institution'];
    $stdfields[] = 'idnumber';
    $stdfields[] = 'address';
    $stdfields[] = 'middlename';

    foreach ($stdfields as $f) {
        $allprofilefields[$f] = get_string($f, 'core');
    }

    if ($customprofilefields = $DB->get_records('user_info_field')) {
        foreach ($customprofilefields as $cpf) {
            $allprofilefields['profile_field_' . $cpf->shortname] = format_string($cpf->name);
        }
    }

    foreach ($extrafields as $field) {
        $label = isset($allprofilefields[$field]) ? $allprofilefields[$field] : $field;
        $html .= '<th class="profilefield colvis ' . s($field) . '">' . s($label) . '</th>';
    }

    // 5. Dynamic JSON Schema Fields
    $schema = \enrol_gapplya\util::get_application_schema($instance);
    if (!empty($schema)) {
        foreach ($schema as $field) {
            if ($field['type'] === 'header') {
                continue;
            }
            $html .= '<th class="text-truncate col-schema colvis inv">' . s($field['label']) . '</th>';
        }
    }

    // 6. Application Details (Text + Files)
    $showdetails = !empty($instance->customint1) || !empty($instance->customint2) || !empty($instance->customint3);
    if ($showdetails || !empty($instance->customint4)) {
        $html .= '<th class="applicationdetails col-details colvis inv">' .
                 get_string('applicationdetails', 'enrol_gapplya') . '</th>';
    }

    // 7. Raw Data Columns (Hidden, for search only, NOT in menu)
    $html .= '<th class="applicationtext inv d-none">' . get_string('applicationtext', 'enrol_gapplya') . '</th>';
    $html .= '<th class="attachment inv d-none">' . get_string('attachment', 'enrol_gapplya') . '</th>';

    // 8. Admin Note
    $html .= '<th class="col-note colvis inv">' . get_string('adminnote', 'enrol_gapplya') . '</th>';

    // 9. Status (Only visible on 'All' tab)
    $html .= '<th class="status">' . get_string('status', 'enrol_gapplya') . '</th>';

    // 10. Time
    $html .= '<th class="timecreated">' . get_string('timecreated', 'enrol_gapplya') . '</th>';

    // 11. Timestamp
    $html .= '<th class="inv d-none">' . get_string('timecreated_timestamp', 'enrol_gapplya') . '</th>';

    // 12. Actions
    $html .= '<th class="noorder col-actions">' . get_string('action', 'enrol_gapplya') . '</th>';

    $html .= '</tr></thead><tbody></tbody></table>';

    return $html;
}
