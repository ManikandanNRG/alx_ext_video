<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->version   = 2025102701;        // The current plugin version (Date: YYYYMMDDXX) - BUMPED TO FORCE JS RELOAD.
$plugin->requires  = 2022112800;        // Requires Moodle 4.1 or higher (compatible with 4.2).
$plugin->component = 'assignsubmission_cloudflarestream';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.1';           // Bug fix release
$plugin->dependencies = array();
