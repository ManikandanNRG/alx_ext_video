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
 * Settings for S3 video submission plugin.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // AWS S3 Configuration.
    $settings->add(new admin_setting_heading(
        'assignsubmission_s3video/aws_s3_settings',
        get_string('aws_s3_settings', 'assignsubmission_s3video'),
        get_string('aws_s3_settings_desc', 'assignsubmission_s3video')
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/aws_access_key',
        get_string('aws_access_key', 'assignsubmission_s3video'),
        get_string('aws_access_key_desc', 'assignsubmission_s3video'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'assignsubmission_s3video/aws_secret_key',
        get_string('aws_secret_key', 'assignsubmission_s3video'),
        get_string('aws_secret_key_desc', 'assignsubmission_s3video'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/s3_bucket',
        get_string('s3_bucket', 'assignsubmission_s3video'),
        get_string('s3_bucket_desc', 'assignsubmission_s3video'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/s3_region',
        get_string('s3_region', 'assignsubmission_s3video'),
        get_string('s3_region_desc', 'assignsubmission_s3video'),
        'us-east-1',
        PARAM_TEXT
    ));

    // CloudFront Configuration.
    $settings->add(new admin_setting_heading(
        'assignsubmission_s3video/cloudfront_settings',
        get_string('cloudfront_settings', 'assignsubmission_s3video'),
        get_string('cloudfront_settings_desc', 'assignsubmission_s3video')
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/cloudfront_domain',
        get_string('cloudfront_domain', 'assignsubmission_s3video'),
        get_string('cloudfront_domain_desc', 'assignsubmission_s3video'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/cloudfront_keypair_id',
        get_string('cloudfront_keypair_id', 'assignsubmission_s3video'),
        get_string('cloudfront_keypair_id_desc', 'assignsubmission_s3video'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'assignsubmission_s3video/cloudfront_private_key',
        get_string('cloudfront_private_key', 'assignsubmission_s3video'),
        get_string('cloudfront_private_key_desc', 'assignsubmission_s3video'),
        '',
        PARAM_TEXT
    ));

    // Video Retention Configuration.
    $settings->add(new admin_setting_heading(
        'assignsubmission_s3video/retention_settings',
        get_string('retention_settings', 'assignsubmission_s3video'),
        get_string('retention_settings_desc', 'assignsubmission_s3video')
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/retention_days',
        get_string('retention_days', 'assignsubmission_s3video'),
        get_string('retention_days_desc', 'assignsubmission_s3video'),
        '90',
        PARAM_INT
    ));

    // Rate Limiting Configuration.
    $settings->add(new admin_setting_heading(
        'assignsubmission_s3video/rate_limit_settings',
        get_string('rate_limit_settings', 'assignsubmission_s3video'),
        get_string('rate_limit_settings_desc', 'assignsubmission_s3video')
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/upload_rate_limit',
        get_string('upload_rate_limit', 'assignsubmission_s3video'),
        get_string('upload_rate_limit_desc', 'assignsubmission_s3video'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_s3video/playback_rate_limit',
        get_string('playback_rate_limit', 'assignsubmission_s3video'),
        get_string('playback_rate_limit_desc', 'assignsubmission_s3video'),
        '100',
        PARAM_INT
    ));
}

// Add dashboard link to admin menu.
$ADMIN->add('assignsubmissionplugins', new admin_externalpage(
    'assignsubmission_s3video_dashboard',
    get_string('dashboard', 'assignsubmission_s3video'),
    new moodle_url('/mod/assign/submission/s3video/dashboard.php'),
    'moodle/site:config'
));

// Add video management link to admin menu.
$ADMIN->add('assignsubmissionplugins', new admin_externalpage(
    'assignsubmission_s3video_videomanagement',
    get_string('videomanagement', 'assignsubmission_s3video'),
    new moodle_url('/mod/assign/submission/s3video/videomanagement.php'),
    'moodle/site:config'
));
