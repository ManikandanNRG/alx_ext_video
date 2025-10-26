<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script for assignsubmission_cloudflarestream plugin.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_assignsubmission_cloudflarestream_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add logging table for monitoring and analytics.
    if ($oldversion < 2025102301) {
        // Define table assignsubmission_cfstream_log to be created.
        $table = new xmldb_table('assignsubmission_cfstream_log');

        // Adding fields to table.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('assignmentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('video_uid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('event_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('error_code', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('error_context', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('file_size', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('retry_count', XMLDB_TYPE_INTEGER, '5', null, null, null, null);
        $table->add_field('user_role', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('assignmentid', XMLDB_KEY_FOREIGN, ['assignmentid'], 'assign', ['id']);
        $table->add_key('submissionid', XMLDB_KEY_FOREIGN, ['submissionid'], 'assign_submission', ['id']);

        // Adding indexes to table.
        $table->add_index('event_type', XMLDB_INDEX_NOTUNIQUE, ['event_type']);
        $table->add_index('timestamp', XMLDB_INDEX_NOTUNIQUE, ['timestamp']);
        $table->add_index('video_uid', XMLDB_INDEX_NOTUNIQUE, ['video_uid']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Cloudflarestream savepoint reached.
        upgrade_plugin_savepoint(true, 2025102301, 'assignsubmission', 'cloudflarestream');
    }

    return true;
}
