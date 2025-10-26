<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->version   = 2025102300;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2020061500;        // Requires Moodle 3.9 or higher.
$plugin->component = 'assignsubmission_cloudflarestream';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = array();
