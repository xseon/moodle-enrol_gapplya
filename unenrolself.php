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
 * Unenrol self from a course using enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'gapplya'], '*', MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context  = context_course::instance($course->id, MUST_EXIST);

require_login($course);

$plugin = enrol_get_plugin('gapplya');

// Check permissions.
if (!$plugin->get_unenrolself_link($instance)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Set up page parameters.
$PAGE->set_url('/enrol/gapplya/unenrolself.php', ['enrolid' => $instance->id]);
$PAGE->set_title($plugin->get_instance_name($instance));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

if ($confirm && confirm_sesskey()) {
    // Unenrolment.
    $plugin->unenrol_user($instance, $USER->id);

    // Redirect to course after unenrolment.
    redirect(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('applicationwithdrawnsuccess', 'enrol_gapplya'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Unenrolment confirmation.
echo $OUTPUT->header();

$yesurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$nourl = new moodle_url('/course/view.php', ['id' => $course->id]);
$message = get_string('withdrawapplicationconfirm', 'enrol_gapplya', format_string($course->fullname));

echo $OUTPUT->confirm($message, $yesurl, $nourl);
echo $OUTPUT->footer();

