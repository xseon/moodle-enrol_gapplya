# Enrolment Request (enrol_gapplya)

"Enrolment Request" is a powerful, enterprise-grade enrollment plugin for Moodle. It allows institutions to manage course applications with dynamic custom forms, strict approval workflows, and comprehensive audit trails.

This plugin is a majorly extended and completely refactored version of the original "Enrolment Application" plugin, designed to meet strict Moodle coding standards and provide advanced data management capabilities.

## Key Features

* Mobile App Ready: Fully compatible with the official Moodle Mobile App. Students can seamlessly open and submit complex application forms directly within the app via a secure, auto-login internal browser.
* Dynamic Application Forms: Build custom forms using a flexible JSON schema directly from the plugin settings. Supports text, textareas, dropdowns, date selectors, checkboxes, and conditional logic.
* Strict Audit Trail: A permanent history log that tracks every change made to an application (submissions, status changes, data edits, sent emails) with user references and timestamps. It also tracks metadata such as the user's last access and the application's last edited dates.
* Smart Data Management: Administrators can edit applicant data directly, add private internal notes, and utilize a dedicated "All" tab to manage everything in one place.
* Bulk Messaging & Communication: Send custom, targeted email messages (with customizable subjects and basic HTML support) to one or multiple applicants at once directly from the dashboard. All sent communications are securely logged in the applicant's permanent history trail.
* Smart Notifications & Silent Mode: Select exactly which teachers or administrators should receive email notifications when a new application is submitted. Administrators also have the power to change user statuses silently without triggering automated emails to the applicants.
* Data Retention: Choose whether to permanently delete withdrawn applications or keep them as soft-deleted records for historical purposes.
* Dynamic Datatables: Management tables automatically build search filters and sort options based on the currently visible columns, including your custom form fields.

---

## ⚠️ Important: Upgrading from the original "Enrolment Application" (enrol_gapply)

If your site is currently using the original "Enrolment Application" plugin, you can upgrade to "Enrolment Request" without losing any user data, applications, or course settings!

Migration Steps:
1. DO NOT uninstall the old "Enrolment Application" plugin yet. If you uninstall it first, Moodle will permanently delete all your existing applications and files.
2. Install this new plugin ("Enrolment Request") following the standard installation instructions below.
3. **Crucial Step:** Go to *Site administration > Plugins > Enrolments > Manage enrol plugins* and **enable** the new plugin globally by clicking the eye icon.
4. Go to the new plugin's settings page (*Site administration > Plugins > Enrolments > Enrolment Request*).
5. Scroll to the bottom of the settings page, check the **Migrate data from legacy plugin** box, and click **Save changes**. 
6. Our built-in script will automatically detect the old plugin and seamlessly migrate all existing applications, custom settings, attached files, and active course instances to the new Advanced version, while safely disabling the old method in your courses.
7. Once the migration is complete and you have verified that everything is working correctly, you can safely uninstall and remove the old "Enrolment Application" plugin from your system.

---

## Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually

The plugin can also be installed by extracting the contents of the ZIP file and putting the `gapplya` directory into:

    {your/moodle/dirroot}/enrol/gapplya

Afterwards, log in to your Moodle site as an admin and go to *Site administration > Notifications* to complete the installation.

Alternatively, you can run the following command from your Moodle root directory to complete the installation via CLI:

    $ php admin/cli/upgrade.php

## License

2026 Dimitar Mitev <info@napravisisait.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.