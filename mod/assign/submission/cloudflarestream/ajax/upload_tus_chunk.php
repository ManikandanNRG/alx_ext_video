<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint to proxy TUS chunk uploads to Cloudflare.
 * Receives chunk from JavaScript and forwards to Cloudflare.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../../config.php');

use assignsubmission_cloudflarestream\validator;
use assignsubmission_cloudflarestream\validation_exception;

// Get parameters.
try {
    $uploadurl = required_param('uploadurl', PARAM_URL);
    $offset = required_param('offset', PARAM_INT);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

// Require login and valid session.
require_login();
require_sesskey();

// Read binary chunk data from request body.
$chunkdata = file_get_contents('php://input');

if (empty($chunkdata)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No chunk data received'
    ]);
    exit;
}

try {
    // Make PATCH request to Cloudflare.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadurl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Tus-Resumable: 1.0.0',
        'Upload-Offset: ' . $offset,
        'Content-Type: application/offset+octet-stream',
        'Content-Length: ' . strlen($chunkdata)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $chunkdata);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    // Parse headers.
    $headertext = substr($response, 0, $headersize);
    $headers = [];
    foreach (explode("\r\n", $headertext) as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }
    
    // Check response.
    if ($httpcode === 204) {
        // Success - return new offset.
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'offset' => isset($headers['Upload-Offset']) ? (int)$headers['Upload-Offset'] : $offset + strlen($chunkdata)
        ]);
    } else {
        http_response_code($httpcode);
        echo json_encode([
            'success' => false,
            'error' => 'TUS chunk upload failed with HTTP ' . $httpcode
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
