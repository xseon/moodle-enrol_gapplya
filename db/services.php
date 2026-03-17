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
 * Access.
 *
 * @package     enrol_gapplya
 * @copyright   2026 Dimitar Mitev <info@napravisisait.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'enrol_gapplya_save_data' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'save_data',
        'description'   => 'Save edited application data',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_execute_action' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'execute_action',
        'description'   => 'Execute bulk actions (approve, waitlist, reject, withdraw, delete, sendmessage)',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_withdraw' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'withdraw',
        'description'   => 'Withdraw own application',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_get_user_details' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'get_user_details',
        'description'   => 'Get user application details HTML',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_get_applications' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'get_applications',
        'description'   => 'Get applications JSON for datatable',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_get_groups' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'get_groups',
        'description'   => 'Get course groups',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'enrol_gapplya_get_roles_and_dates' => [
        'classname'     => 'enrol_gapplya\external\api',
        'methodname'    => 'get_roles_and_dates',
        'description'   => 'Get assignable roles and dates',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];

