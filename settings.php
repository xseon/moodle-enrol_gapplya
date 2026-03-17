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
 * Settings configuration for the plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $DB;

require_once($CFG->dirroot . '/enrol/gapplya/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

if (!class_exists('enrol_gapplya_admin_setting_migrate')) {

    /**
     * Custom admin setting class to handle data migration from the old plugin.
     *
     * @package    enrol_gapplya
     * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class enrol_gapplya_admin_setting_migrate extends admin_setting_configcheckbox {

        /**
         * Trigger migration logic when the setting is saved and toggled to 1.
         *
         * @param string $data The new setting value.
         * @return string Empty string on success, error message on error.
         */
        public function write_setting($data) {
            global $DB;

            $oldvalue = $this->get_setting();

            if ($data === '1' && $oldvalue === '0') {
                try {
                    $plugin = enrol_get_plugin('gapplya');
                    $createdinstances = 0;
                    $linkedapps = 0;
                    $deactivatedold = 0;

                    // 1. Migrate application records.
                    if ($DB->get_manager()->table_exists('enrol_gapply')) {
                        $oldrecords = $DB->get_records('enrol_gapply', null, 'id DESC');
                        if ($oldrecords) {
                            foreach ($oldrecords as $rec) {
                                unset($rec->id);
                                $exists = $DB->record_exists('enrol_gapplya', [
                                    'userid' => $rec->userid,
                                    'courseid' => $rec->courseid,
                                ]);
                                if (!$exists) {
                                    $DB->insert_record('enrol_gapplya', $rec);
                                }
                            }
                        }
                    }

                    // 2. Process courses and link applications.
                    $sql = "SELECT DISTINCT courseid
                              FROM {enrol_gapplya}";
                    $courseswithapps = $DB->get_records_sql($sql);

                    if ($courseswithapps) {
                        foreach ($courseswithapps as $course) {
                            $courseid = $course->courseid;
                            $courserecord = $DB->get_record('course', ['id' => $courseid]);

                            if (!$courserecord) {
                                continue;
                            }

                            $oldinstance = $DB->get_record('enrol', [
                                'enrol' => 'gapply',
                                'courseid' => $courseid,
                            ]);

                            // Create the new enrol instance if it does not exist.
                            $instance = $DB->get_record('enrol', [
                                'enrol' => 'gapplya',
                                'courseid' => $courseid,
                            ]);

                            if (!$instance) {
                                $instanceid = $plugin->add_default_instance($courserecord);
                                $instance = $DB->get_record('enrol', ['id' => $instanceid]);
                                $createdinstances++;

                                // Copy specific settings from the old instance.
                                if ($oldinstance) {
                                    $instance->customint1 = $oldinstance->customint1;
                                    $instance->customint2 = $oldinstance->customint2;
                                    $instance->customint3 = $oldinstance->customint3;
                                    $instance->customint4 = $oldinstance->customint4;
                                    $instance->customint5 = $oldinstance->customint5;
                                    $instance->customint6 = $oldinstance->customint6;
                                    $instance->customchar1 = $oldinstance->customchar1;
                                    $instance->customchar2 = $oldinstance->customchar2;
                                    $instance->customtext1 = $oldinstance->customtext1;
                                    $instance->customtext2 = $oldinstance->customtext2;
                                    $instance->customtext3 = $oldinstance->customtext3;
                                    $instance->customtext4 = $oldinstance->customtext4;
                                    $instance->roleid      = $oldinstance->roleid;

                                    $DB->update_record('enrol', $instance);
                                }
                            }

                            // Disable the old plugin instance in this course.
                            if ($oldinstance && $oldinstance->status == ENROL_INSTANCE_ENABLED) {
                                $oldinstance->status = ENROL_INSTANCE_DISABLED;
                                $DB->update_record('enrol', $oldinstance);
                                $deactivatedold++;
                            }

                            if ($instance) {
                                // Link applications to the new instance ID.
                                $updatesql = "UPDATE {enrol_gapplya}
                                                 SET instance = :newid
                                               WHERE courseid = :courseid
                                                 AND instance != :newid2";
                                $DB->execute($updatesql, [
                                    'newid' => $instance->id,
                                    'courseid' => $courseid,
                                    'newid2' => $instance->id,
                                ]);

                                $linkedapps++;

                                // Restore enrolments for approved applications.
                                $approvedapps = $DB->get_records('enrol_gapplya', [
                                    'courseid' => $courseid,
                                    'status' => 'approved',
                                ]);

                                if ($approvedapps) {
                                    foreach ($approvedapps as $app) {
                                        $enrolexists = $DB->record_exists('user_enrolments', [
                                            'enrolid' => $instance->id,
                                            'userid' => $app->userid,
                                        ]);

                                        if (!$enrolexists) {
                                            $plugin->enrol_user($instance, $app->userid, $instance->roleid);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 3. Migrate Files (Attachments).
                    $DB->set_field('files', 'component', 'enrol_gapplya', [
                        'component' => 'enrol_gapply',
                        'filearea' => 'applyfile',
                    ]);

                    // Fetch plugin names for the notification.
                    $oldpluginname = get_string('pluginname', 'enrol_gapply');
                    $newpluginname = get_string('pluginname', 'enrol_gapplya');

                    // Build the success notification.
                    $msg = html_writer::tag('strong', get_string('migration_success', 'enrol_gapplya'));
                    $msg .= html_writer::empty_tag('br') . html_writer::empty_tag('br');

                    $a = new stdClass();
                    $a->pluginname = $newpluginname;
                    $a->count = $createdinstances;
                    $msg .= get_string('migration_created', 'enrol_gapplya', $a);
                    $msg .= html_writer::empty_tag('br');

                    $a = new stdClass();
                    $a->oldplugin = $oldpluginname;
                    $a->newplugin = $newpluginname;
                    $a->count = $linkedapps;
                    $msg .= get_string('migration_linked', 'enrol_gapplya', $a);
                    $msg .= html_writer::empty_tag('br');

                    $a = new stdClass();
                    $a->pluginname = $oldpluginname;
                    $a->count = $deactivatedold;
                    $msg .= get_string('migration_deactivated', 'enrol_gapplya', $a);

                    \core\notification::add($msg, \core\notification::SUCCESS);

                } catch (\Exception $e) {
                    $errormsg = get_string('migration_error', 'enrol_gapplya', $e->getMessage());
                    \core\notification::add($errormsg, \core\notification::ERROR);
                    return $errormsg;
                }
            }

            return parent::write_setting($data);
        }
    }
}

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'enrolsettingsgapplya',
        new lang_string('pluginname', 'enrol_gapplya'),
        'moodle/site:config'
    );

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'enrol_gapplya/plugininfo',
            '',
            new lang_string('pluginname_desc', 'enrol_gapplya')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'enrol_gapplya/defaultenrol',
            new lang_string('defaultenrol', 'enrol'),
            new lang_string('defaultenrol_desc', 'enrol'),
            1
        ));

        $fields = enrol_gapplya_get_profile_fields();
        $defaults = ['city', 'institution', 'phone1', 'email'];

        $settings->add(new admin_setting_configmultiselect(
            'enrol_gapplya/showuseridentity',
            new lang_string('showuseridentity', 'enrol_gapplya'),
            new lang_string('showuseridentity_desc', 'enrol_gapplya'),
            $defaults,
            $fields
        ));

        $settings->add(new admin_setting_configcheckbox(
            'enrol_gapplya/sendnotificationinrecipientlang',
            new lang_string('sendnotificationinrecipientlang', 'enrol_gapplya'),
            new lang_string('sendnotificationinrecipientlang_desc', 'enrol_gapplya'),
            0
        ));

        if (!during_initial_install() && !empty($DB) && $DB->get_manager()->table_exists('role')) {
            $roles = role_get_names(null, null, true);
            asort($roles);

            $studentroleid = 0;
            $archetyperoles = get_archetype_roles('student');
            if (!empty($archetyperoles)) {
                $studentroleid = key($archetyperoles);
            }

            $settings->add(new admin_setting_configselect(
                'enrol_gapplya/roleid',
                new lang_string('defaultrole', 'enrol_gapplya'),
                new lang_string('defaultrole_desc', 'enrol_gapplya'),
                $studentroleid,
                $roles
            ));
        }

        // Migration setting.
        $migrationcompleted = get_config('enrol_gapplya', 'runmigration');
        $oldpluginexists = !empty($DB) && $DB->get_manager()->table_exists('enrol_gapply');

        if (empty($migrationcompleted) && $oldpluginexists) {
            $oldpluginname = get_string('pluginname', 'enrol_gapply');

            $desca = new stdClass();
            $desca->oldplugin = $oldpluginname;
            $desca->newplugin = get_string('pluginname', 'enrol_gapplya');

            // Check if the plugin is globally enabled.
            if (enrol_is_enabled('gapplya')) {
                $settings->add(new enrol_gapplya_admin_setting_migrate(
                    'enrol_gapplya/runmigration',
                    new lang_string('migratedata', 'enrol_gapplya', $oldpluginname),
                    new lang_string('migratedata_desc', 'enrol_gapplya', $desca),
                    0
                ));
            } else {
                // Plugin is disabled, show a helpful message instead of the checkbox.
                $settings->add(new admin_setting_heading(
                    'enrol_gapplya/migrationdisabled',
                    new lang_string('migratedata', 'enrol_gapplya', $oldpluginname),
                    new lang_string('migration_requires_enable', 'enrol_gapplya', $desca)
                ));
            }
        }
    }
}

