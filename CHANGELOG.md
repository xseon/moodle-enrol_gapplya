# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-03-17

### Added
- **Mobile App Support:** The plugin is now fully compatible with the official Moodle Mobile App. It utilizes the `CoreEnrolDelegate` to seamlessly redirect mobile users to the application form via the app's internal browser (In-App Browser) with automatic login.

### Changed
- **External Services Migration:** Completely refactored the frontend-backend communication. Removed legacy `ajax.php` endpoints and migrated all data operations to Moodle's Web Services API. 

### Fixed
- **Security Enhancements:** Removed all direct superglobal access (`$_POST`, `$_GET`) and `PARAM_RAW` usages. All inputs are now strictly validated and sanitized through Moodle's secure `external_function_parameters` definitions.
- **Privacy API Implementation:** Fully implemented the Moodle Privacy Provider (`classes/privacy/provider.php`) to ensure complete GDPR compliance. The plugin now correctly exports and deletes all user-related personal data (applications, form data, attachments, and history logs) upon request.
- **Database Performance (N+1 Queries):** Resolved potential performance bottlenecks by eliminating nested database queries within loops. User and course records are now preloaded in bulk via `get_records_list()` before iteration.
- **Cross-Database Compatibility:** Replaced hardcoded `$DB->execute()` statements with specialized Moodle DML methods (`$DB->set_field_select()`) for safer and fully compatible cross-database data updates.
- **Missing Strings:** Added missing language string definitions to the language files.
- **Alignment issue:** Fixed an alignment issue in the management datatable where the dynamic filters row overflowed the main container due to inherited Bootstrap negative margins.
- **Boilerplate Headers:** Added standard Moodle GPL v3 boilerplate license headers to all plugin source files (PHP, JavaScript, CSS, and Mustache templates) to strictly comply with Moodle's plugin contribution guidelines.

## [1.0.0] - 2026-03-04
### Initial Release of "Enrolment Request"
This is the initial release of the plugin as a majorly extended version of the original "Enrolment Application" plugin, designed for institutions that need complex application forms, strict audit trails, and better data management.

### Added
- **Custom Form Questions:** Option to assign a number of different types of questions to the application form via a dynamic JSON schema (supports text, textareas, select dropdowns, dates, checkboxes, and conditional dependencies).
- **Custom Notification Recipients:** Option to specify exactly which course teachers/administrators should receive email notifications for new applications.
- **Silent Mode for Status Changes:** A convenient checkbox inside the action confirmation modal allows administrators to change application statuses silently, without triggering automated email notifications to the applicant.
- **"All" Applications Tab:** A new dedicated tab in the management view to see all applications with their respective statuses at a glance.
- **Data Retention:** Option to keep all withdrawn applications data (soft-delete) instead of permanently deleting them from the database.
- **Admin Notes:** Option for administrators to save private internal notes to individual applications.
- **Bulk Messaging:** Option to send custom email messages (with customizable subjects and HTML support) to selected applicants directly from the management datatable. The exact message text is automatically logged in each applicant's history panel for future reference.
- **Data Editing:** Option for administrators to directly edit the applicant's submitted data from the application details modal.
- **History of Changes:** A comprehensive audit trail panel inside the application details that tracks every action (submissions, status changes, emails sent, data edits, note updates) along with the user and timestamp.
- **Application Metadata:** Display of the applicant's "Last access" and the application's "Last edited" timestamps directly inside the review modal.
- **Dynamic Filtering & Sorting:** The management datatable now dynamically builds search filters and sort options based on the currently visible columns, including the custom form fields.
- **Seamless "Enrolment Application" Migration Tool:** A built-in, safe migration setting accessible from the plugin's configuration page that allows seamless migration of all applications, settings, and attached files from any existing course instance of "Enrolment Application" enrolment method.

### Changed
- **Code refactoring** to comply with Moodle's strict coding standards (Moodle Codechecker).
- **UI/UX improvements** in the applications management page, including an optimized datatable toolbar and perfectly aligned action buttons.
- **Renamed terminology** across language files to strictly align with Moodle standards (e.g., using "Enrolment Request" internally).

