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
 * Utility class for enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_gapplya;

/**
 * Utility class for enrol_gapplya plugin.
 *
 * @package    enrol_gapplya
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /**
     * Add a history log entry for a specific application record.
     *
     * @param int $recordid The ID of the application record in enrol_gapplya table.
     * @param string $actionname The action key (e.g., 'submitted', 'approved', 'withdrawn').
     * @param int $userid The ID of the user performing the action.
     * @param string $details Additional details (optional).
     * @return bool True on success, false otherwise.
     */
    public static function add_history($recordid, $actionname, $userid, $details = '') {
        global $DB;

        $record = $DB->get_record('enrol_gapplya', ['id' => $recordid]);
        if ($record) {
            $history = !empty($record->history) ? json_decode($record->history, true) : [];
            if (!is_array($history)) {
                $history = [];
            }

            $history[] = [
                'time' => time(),
                'action' => $actionname,
                'userid' => $userid,
                'details' => $details,
            ];

            // Using JSON_UNESCAPED_UNICODE is better for non-English languages like Bulgarian.
            return $DB->set_field('enrol_gapplya', 'history', json_encode($history, JSON_UNESCAPED_UNICODE), ['id' => $recordid]);
        }

        return false;
    }

    /**
     * Send a notification message to a user.
     *
     * @param mixed $user The user object, array, or ID to send to.
     * @param object $userfrom The user object sending the message.
     * @param object $msg The message object containing subject, text, contexturl, etc.
     * @return bool True on success, false otherwise.
     */
    public static function send_notification($user, $userfrom, $msg = null) {
        global $DB;

        $userid = 0;
        if (is_numeric($user)) {
            $userid = $user;
        } else if (is_object($user) && isset($user->id)) {
            $userid = $user->id;
        } else if (is_array($user) && isset($user['id'])) {
            $userid = $user['id'];
        }

        if (empty($userid)) {
            return false;
        }

        $userto = $DB->get_record('user', ['id' => $userid]);
        if (!$userto) {
            return false;
        }

        $message = new \core\message\message();
        $message->component = 'enrol_gapplya';
        $message->name = 'gapplya';
        $message->userfrom = $userfrom;
        $message->userto = $userto;
        $message->subject = $msg->subject;
        $message->fullmessage = $msg->text . "\n" . $msg->contexturlname . ': ' . $msg->contexturl;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = $msg->text . '<br><a href="' . $msg->contexturl . '">' . $msg->contexturlname . '</a>';
        $message->smallmessage = $msg->text;
        $message->notification = 1;
        $message->contexturl = $msg->contexturl;
        $message->contexturlname = $msg->contexturlname;

        message_send($message);
        return true;
    }

    /**
     * Extracts the JSON config string from the description text.
     *
     * @param string|array $text The description text (or array with 'text' key).
     * @return string The extracted JSON string or empty string.
     */
    public static function extract_config($text) {
        if (is_array($text)) {
            $text = isset($text['text']) ? (string)$text['text'] : '';
        } else {
            $text = (string)$text;
        }

        if (preg_match('/<span[^>]+id="gapplya_json_store"[^>]*>(.*?)<\/span>/is', $text, $matches)) {
            $content = $matches[1];
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
            $content = strip_tags($content);
            $content = str_replace("\xc2\xa0", ' ', $content);
            $content = trim($content);
            return $content;
        }
        return '';
    }

    /**
     * Removes the hidden JSON config span from the description text.
     *
     * @param string|array $text The full description text.
     * @return string The clean description.
     */
    public static function clean_description($text) {
        if (is_array($text)) {
            $text = isset($text['text']) ? (string)$text['text'] : '';
        } else {
            $text = (string)$text;
        }
        return preg_replace('/<span[^>]+id="gapplya_json_store"[^>]*>.*?<\/span>/is', '', $text);
    }

    /**
     * Parses the JSON configuration and returns the schema array.
     *
     * @param \stdClass $instance The enrolment instance record.
     * @return array The decoded schema or empty array.
     */
    public static function get_application_schema($instance) {
        if (!empty($instance->customtext1)) {
            // We use self:: because the function is in the same class.
            $jsonstr = self::extract_config($instance->customtext1);
            if (!empty($jsonstr)) {
                $schema = json_decode($jsonstr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($schema)) {
                    return $schema;
                }
            }
        }
        return [];
    }

    /**
     * Appends the hidden JSON config to the description text.
     *
     * @param string|array $text The clean description text.
     * @param string $jsonstr The JSON string to embed.
     * @return string The formatted text with hidden JSON span.
     */
    public static function append_config($text, $jsonstr) {
        // Extract string if passed from an editor array.
        if (is_array($text)) {
            $text = isset($text['text']) ? (string)$text['text'] : '';
        } else {
            $text = (string)$text;
        }

        if (empty($jsonstr)) {
            return $text;
        }
        // We use s() for safety, as per Moodle standards.
        return $text . '<span id="gapplya_json_store" style="display:none;">' . s($jsonstr) . '</span>';
    }
}
