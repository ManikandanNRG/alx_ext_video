<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Add dashboard link to admin menu.
if ($hassiteconfig) {
    $ADMIN->add('assignsubmissionplugins', new admin_externalpage(
        'assignsubmission_cloudflarestream_dashboard',
        get_string('dashboard', 'assignsubmission_cloudflarestream'),
        new moodle_url('/mod/assign/submission/cloudflarestream/dashboard.php'),
        'moodle/site:config'
    ));
    
    // Add video management page to admin menu.
    $ADMIN->add('assignsubmissionplugins', new admin_externalpage(
        'assignsubmission_cloudflarestream_videomanagement',
        get_string('videomanagement', 'assignsubmission_cloudflarestream'),
        new moodle_url('/mod/assign/submission/cloudflarestream/videomanagement.php'),
        'moodle/site:config'
    ));
}

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
    $settings->add(new admin_setting_configtext(
        'assignsubmission_cloudflarestream/retention_days',
        get_string('retention_days', 'assignsubmission_cloudflarestream'),
        get_string('retention_days_desc', 'assignsubmission_cloudflarestream'),
        '90',
        PARAM_INT
    ));

    // Maximum file size in bytes (default 5GB).
    $sizeoptions = array(
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
