<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->version   = 2025111701;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022112800;        // Requires Moodle 4.1 or higher (compatible with 4.2).
$plugin->component = 'assignsubmission_cloudflarestream';
$plugin->maturity  = MATURITY_STABLE;   // Stable release.
$plugin->release   = '1.5.0';           // Version 1.5 - Production ready with TUS upload, retry logic, and UI improvements.
$plugin->dependencies = array();
