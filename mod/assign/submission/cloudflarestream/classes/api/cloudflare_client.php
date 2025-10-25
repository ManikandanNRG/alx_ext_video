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
 * Cloudflare Stream API client.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream\api;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_cloudflarestream\logger;
use assignsubmission_cloudflarestream\validator;

/**
 * Exception thrown when Cloudflare API requests fail.
 */
class cloudflare_api_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode The error code
     * @param string $debuginfo Additional debug information
     */
    public function __construct($errorcode, $debuginfo = '') {
        parent::__construct($errorcode, 'assignsubmission_cloudflarestream', '', null, $debuginfo);
    }
}

/**
 * Exception thrown when authentication with Cloudflare fails.
 */
class cloudflare_auth_exception extends cloudflare_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('cloudflare_auth_failed', $debuginfo);
    }
}

/**
 * Exception thrown when a requested video is not found.
 */
class cloudflare_video_not_found_exception extends cloudflare_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('cloudflare_video_not_found', $debuginfo);
    }
}

/**
 * Exception thrown when Cloudflare quota is exceeded.
 */
class cloudflare_quota_exception extends cloudflare_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('cloudflare_quota_exceeded', $debuginfo);
    }
}

/**
 * Cloudflare Stream API client class.
 *
 * Handles all interactions with the Cloudflare Stream API including
 * direct uploads, video management, and signed token generation.
 */
class cloudflare_client {
    /** @var string Cloudflare API token */
    private $apitoken;

    /** @var string Cloudflare account ID */
    private $accountid;

    /** @var string Base URL for Cloudflare API */
    private $baseurl = 'https://api.cloudflare.com/client/v4';

    /**
     * Constructor.
     *
     * @param string $apitoken Cloudflare API token
     * @param string $accountid Cloudflare account ID
     */
    public function __construct($apitoken, $accountid) {
        $this->apitoken = $apitoken;
        $this->accountid = $accountid;
    }

    /**
     * Get a direct upload URL for uploading videos.
     *
     * @param int $maxdurationseconds Maximum video duration in seconds (default: 21600 = 6 hours)
     * @return object Object containing 'uploadURL' and 'uid' properties
     * @throws cloudflare_api_exception If the API request fails
     */
    public function get_direct_upload_url($maxdurationseconds = 21600) {
        // Validate input parameters.
        validator::validate_duration($maxdurationseconds);
        
        $endpoint = "/accounts/{$this->accountid}/stream/direct_upload";
        $data = [
            'maxDurationSeconds' => $maxdurationseconds
        ];

        $response = $this->make_request('POST', $endpoint, $data);

        // Validate API response structure.
        validator::validate_api_response($response, ['uploadURL', 'uid']);

        // Validate the returned video UID.
        validator::validate_video_uid($response->result->uid);

        return $response->result;
    }

    /**
     * Get details about a specific video.
     *
     * @param string $videouid The Cloudflare video UID
     * @return object Video details object
     * @throws cloudflare_video_not_found_exception If the video is not found
     * @throws cloudflare_api_exception If the API request fails
     */
    public function get_video_details($videouid) {
        // Validate input parameters.
        $videouid = validator::validate_video_uid($videouid);
        
        $endpoint = "/accounts/{$this->accountid}/stream/{$videouid}";
        $response = $this->make_request('GET', $endpoint);

        // Validate API response structure.
        validator::validate_api_response($response);

        // Validate video details.
        validator::validate_video_details($response->result);

        return $response->result;
    }

    /**
     * Delete a video from Cloudflare Stream.
     *
     * @param string $videouid The Cloudflare video UID
     * @return bool True if deletion was successful
     * @throws cloudflare_video_not_found_exception If the video is not found
     * @throws cloudflare_api_exception If the API request fails
     */
    public function delete_video($videouid) {
        // Validate input parameters.
        $videouid = validator::validate_video_uid($videouid);
        
        $endpoint = "/accounts/{$this->accountid}/stream/{$videouid}";
        $response = $this->make_request('DELETE', $endpoint);
        
        // Validate API response.
        validator::validate_api_response($response);
        
        return true;
    }

    /**
     * Generate a signed token for video playback.
     *
     * @param string $videouid The Cloudflare video UID
     * @param int $expiryseconds Token expiry time in seconds from now (default: 86400 = 24 hours)
     * @return string The signed JWT token
     * @throws cloudflare_api_exception If the API request fails
     */
    public function generate_signed_token($videouid, $expiryseconds = 86400) {
        // Validate input parameters.
        $videouid = validator::validate_video_uid($videouid);
        
        if (!is_numeric($expiryseconds) || $expiryseconds <= 0 || $expiryseconds > 604800) { // Max 7 days
            throw new cloudflare_api_exception(
                'invalid_expiry_seconds',
                'Expiry seconds must be between 1 and 604800 (7 days)'
            );
        }
        
        $endpoint = "/accounts/{$this->accountid}/stream/{$videouid}/token";
        $data = [
            'exp' => time() + $expiryseconds
        ];

        $response = $this->make_request('POST', $endpoint, $data);

        // Validate API response structure.
        validator::validate_api_response($response, ['token']);

        return $response->result->token;
    }

    /**
     * Make an HTTP request to the Cloudflare API.
     *
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $endpoint API endpoint path
     * @param array|null $data Request body data (for POST requests)
     * @return object Decoded JSON response
     * @throws cloudflare_auth_exception If authentication fails
     * @throws cloudflare_video_not_found_exception If video is not found
     * @throws cloudflare_quota_exception If quota is exceeded
     * @throws cloudflare_api_exception For other API errors
     */
    protected function make_request($method, $endpoint, $data = null) {
        $url = $this->baseurl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->apitoken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors.
        if ($response === false) {
            throw new cloudflare_api_exception(
                'cloudflare_network_error',
                'cURL error: ' . $curlerror
            );
        }

        // Decode JSON response.
        $decoded = json_decode($response);
        if ($decoded === null) {
            throw new cloudflare_api_exception(
                'cloudflare_invalid_response',
                'Failed to decode JSON response: ' . $response
            );
        }

        // Handle HTTP error codes.
        if ($httpcode >= 400) {
            $errormessage = isset($decoded->errors[0]->message) 
                ? $decoded->errors[0]->message 
                : 'Unknown error';

            // Log API error.
            logger::log_api_error($endpoint, $method, 'http_error', $errormessage, $httpcode);

            switch ($httpcode) {
                case 401:
                case 403:
                    throw new cloudflare_auth_exception(
                        "HTTP {$httpcode}: {$errormessage}"
                    );
                case 404:
                    throw new cloudflare_video_not_found_exception(
                        "HTTP {$httpcode}: {$errormessage}"
                    );
                case 429:
                    throw new cloudflare_quota_exception(
                        "HTTP {$httpcode}: {$errormessage}"
                    );
                default:
                    throw new cloudflare_api_exception(
                        'cloudflare_api_error',
                        "HTTP {$httpcode}: {$errormessage}"
                    );
            }
        }

        // Check if API returned success.
        if (!isset($decoded->success) || $decoded->success !== true) {
            $errormessage = isset($decoded->errors[0]->message) 
                ? $decoded->errors[0]->message 
                : 'API returned success=false';
            
            throw new cloudflare_api_exception(
                'cloudflare_api_error',
                $errormessage
            );
        }

        return $decoded;
    }
}
