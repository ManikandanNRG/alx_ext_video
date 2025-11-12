<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Dashboard and video management are accessed via links in settings page only
// Removed admin_externalpage entries to avoid section errors

if ($ADMIN->fulltree) {
    // Add links to dashboard and video management at the top of settings.
    $settings->add(new admin_setting_heading(
        'assignsubmission_cloudflarestream/adminlinks',
        '',
        html_writer::link(
            new moodle_url('/mod/assign/submission/cloudflarestream/dashboard.php'),
            get_string('viewdashboard', 'assignsubmission_cloudflarestream'),
            ['class' => 'btn btn-primary mr-2']
        ) . 
        html_writer::link(
            new moodle_url('/mod/assign/submission/cloudflarestream/videomanagement.php'),
            get_string('viewvideomanagement', 'assignsubmission_cloudflarestream'),
            ['class' => 'btn btn-secondary']
        )
    ));
    // Cloudflare API Token.
    $settings->add(new admin_setting_configpasswordunmask(
        'assignsubmission_cloudflarestream/apitoken',
        get_string('apitoken', 'assignsubmission_cloudflarestream'),
        get_string('apitoken_desc', 'assignsubmission_cloudflarestream'),
        ''
    ));

    // Cloudflare Account ID.
    $settings->add(new admin_setting_configtext(
        'assignsubmission_cloudflarestream/accountid',
        get_string('accountid', 'assignsubmission_cloudflarestream'),
        get_string('accountid_desc', 'assignsubmission_cloudflarestream'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // Video retention period in days.
    $settings->add(new admin_setting_configselect(
        'assignsubmission_cloudflarestream/retention_days',
        get_string('retention_days', 'assignsubmission_cloudflarestream'),
        get_string('retention_days_desc', 'assignsubmission_cloudflarestream'),
        90,
        [
            30 => '30 days',
            60 => '60 days',
            90 => '90 days',
            180 => '180 days',
            365 => '365 days (1 year)',
            730 => '730 days (2 years)',
            1095 => '1095 days (3 years)',
            1825 => '1825 days (5 years)',
            -1 => get_string('retention_always', 'assignsubmission_cloudflarestream')
        ]
    ));

    // Maximum file size in bytes (default 5GB).
    $sizeoptions = array(
        209715200 => '200 MB',
        419430400 => '400 MB',
        524288000 => '500 MB',
        629145600 => '600 MB',
        734003200 => '700 MB',
        838860800 => '800 MB',
        1073741824 => '1 GB',
        2147483648 => '2 GB',
        3221225472 => '3 GB',
        4294967296 => '4 GB',
        5368709120 => '5 GB'
    );
    
    $settings->add(new admin_setting_configselect(
        'assignsubmission_cloudflarestream/max_file_size',
        get_string('max_file_size', 'assignsubmission_cloudflarestream'),
        get_string('max_file_size_desc', 'assignsubmission_cloudflarestream'),
        5368709120,
        $sizeoptions
    ));
    
    // Rate limiting settings.
    $settings->add(new admin_setting_heading(
        'assignsubmission_cloudflarestream/ratelimitheading',
        get_string('ratelimitsettings', 'assignsubmission_cloudflarestream'),
        get_string('ratelimitsettings_desc', 'assignsubmission_cloudflarestream')
    ));
    
    // Upload rate limit (requests per hour per user).
    $settings->add(new admin_setting_configtext(
        'assignsubmission_cloudflarestream/upload_rate_limit',
        get_string('upload_rate_limit', 'assignsubmission_cloudflarestream'),
        get_string('upload_rate_limit_desc', 'assignsubmission_cloudflarestream'),
        '10',
        PARAM_INT
    ));
    
    // Playback rate limit (requests per hour per user).
    $settings->add(new admin_setting_configtext(
        'assignsubmission_cloudflarestream/playback_rate_limit',
        get_string('playback_rate_limit', 'assignsubmission_cloudflarestream'),
        get_string('playback_rate_limit_desc', 'assignsubmission_cloudflarestream'),
        '100',
        PARAM_INT
    ));
}
