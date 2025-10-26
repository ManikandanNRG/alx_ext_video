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
 * Rate limiting functionality for S3 Video plugin.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown when rate limit is exceeded.
 */
class rate_limit_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode The error code
     * @param int $retryafter Seconds until next attempt is allowed
     */
    public function __construct($errorcode, $retryafter = 0) {
        $debuginfo = $retryafter > 0 ? "Retry after {$retryafter} seconds" : '';
        parent::__construct($errorcode, 'assignsubmission_s3video', '', null, $debuginfo);
    }
}

/**
 * Rate limiter class using sliding window algorithm.
 *
 * Implements rate limiting to prevent abuse of upload URL requests
 * and playback URL requests using Moodle's cache API.
 */
class rate_limiter {
    
    /** @var string Cache area for rate limiting data */
    const CACHE_AREA = 'ratelimit';
    
    /** @var int Default rate limit for upload URL requests (per user per hour) */
    const DEFAULT_UPLOAD_LIMIT = 10;
    
    /** @var int Default rate limit for playback URL requests (per user per hour) */
    const DEFAULT_PLAYBACK_LIMIT = 100;
    
    /** @var int Time window in seconds (1 hour) */
    const TIME_WINDOW = 3600;
    
    /** @var \cache Cache instance */
    private $cache;
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->cache = \cache::make('assignsubmission_s3video', self::CACHE_AREA);
    }
    
    /**
     * Check if upload URL request is allowed for a user.
     *
     * @param int $userid User ID
     * @param int $assignmentid Assignment ID (for additional context)
     * @throws rate_limit_exception If rate limit is exceeded
     */
    public function check_upload_rate_limit($userid, $assignmentid) {
        $limit = get_config('assignsubmission_s3video', 'upload_rate_limit') ?: self::DEFAULT_UPLOAD_LIMIT;
        $key = "upload_{$userid}_{$assignmentid}";
        
        $this->check_rate_limit($key, $limit, 'upload_rate_limit_exceeded');
    }
    
    /**
     * Check if playback URL request is allowed for a user.
     *
     * @param int $userid User ID
     * @param string $s3key S3 key (for additional context)
     * @throws rate_limit_exception If rate limit is exceeded
     */
    public function check_playback_rate_limit($userid, $s3key) {
        $limit = get_config('assignsubmission_s3video', 'playback_rate_limit') ?: self::DEFAULT_PLAYBACK_LIMIT;
        $key = "playback_{$userid}";
        
        $this->check_rate_limit($key, $limit, 'playback_rate_limit_exceeded');
    }
    
    /**
     * Check rate limit using sliding window algorithm.
     *
     * @param string $key Cache key for this rate limit
     * @param int $limit Maximum requests allowed in time window
     * @param string $errorcode Error code to throw if limit exceeded
     * @throws rate_limit_exception If rate limit is exceeded
     */
    private function check_rate_limit($key, $limit, $errorcode) {
        $now = time();
        $windowstart = $now - self::TIME_WINDOW;
        
        // Get current request timestamps from cache.
        $requests = $this->cache->get($key);
        if ($requests === false) {
            $requests = [];
        }
        
        // Remove requests outside the time window.
        $requests = array_filter($requests, function($timestamp) use ($windowstart) {
            return $timestamp > $windowstart;
        });
        
        // Check if limit would be exceeded.
        if (count($requests) >= $limit) {
            // Calculate when the oldest request will expire.
            $oldestRequest = min($requests);
            $retryafter = ($oldestRequest + self::TIME_WINDOW) - $now;
            
            throw new rate_limit_exception($errorcode, max(1, $retryafter));
        }
        
        // Add current request timestamp.
        $requests[] = $now;
        
        // Store updated requests in cache.
        $this->cache->set($key, $requests);
    }
    
    /**
     * Reset rate limit for a specific key (admin function).
     *
     * @param string $type Type of rate limit ('upload' or 'playback')
     * @param int $userid User ID
     * @param int|string $context Assignment ID for upload, S3 key for playback
     */
    public function reset_rate_limit($type, $userid, $context = '') {
        if ($type === 'upload') {
            $key = "upload_{$userid}_{$context}";
        } else if ($type === 'playback') {
            $key = "playback_{$userid}";
        } else {
            throw new \invalid_parameter_exception('Invalid rate limit type');
        }
        
        $this->cache->delete($key);
    }
    
    /**
     * Get current rate limit status for a user.
     *
     * @param string $type Type of rate limit ('upload' or 'playback')
     * @param int $userid User ID
     * @param int|string $context Assignment ID for upload, S3 key for playback
     * @return array Status information including remaining requests and reset time
     */
    public function get_rate_limit_status($type, $userid, $context = '') {
        if ($type === 'upload') {
            $key = "upload_{$userid}_{$context}";
            $limit = get_config('assignsubmission_s3video', 'upload_rate_limit') ?: self::DEFAULT_UPLOAD_LIMIT;
        } else if ($type === 'playback') {
            $key = "playback_{$userid}";
            $limit = get_config('assignsubmission_s3video', 'playback_rate_limit') ?: self::DEFAULT_PLAYBACK_LIMIT;
        } else {
            throw new \invalid_parameter_exception('Invalid rate limit type');
        }
        
        $now = time();
        $windowstart = $now - self::TIME_WINDOW;
        
        // Get current request timestamps from cache.
        $requests = $this->cache->get($key);
        if ($requests === false) {
            $requests = [];
        }
        
        // Remove requests outside the time window.
        $requests = array_filter($requests, function($timestamp) use ($windowstart) {
            return $timestamp > $windowstart;
        });
        
        $used = count($requests);
        $remaining = max(0, $limit - $used);
        
        // Calculate when the window resets (when oldest request expires).
        $resettime = 0;
        if (!empty($requests)) {
            $oldestRequest = min($requests);
            $resettime = $oldestRequest + self::TIME_WINDOW;
        }
        
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'reset_time' => $resettime,
            'window_seconds' => self::TIME_WINDOW
        ];
    }
    
    /**
     * Clean up expired rate limit data (called by scheduled task).
     */
    public function cleanup_expired_data() {
        // This is handled automatically by the sliding window algorithm,
        // but we could implement a more aggressive cleanup here if needed.
        // For now, Moodle's cache TTL will handle cleanup.
    }
    
    /**
     * Check if user is exempt from rate limiting (admins, etc.).
     *
     * @param int $userid User ID
     * @return bool True if user is exempt from rate limiting
     */
    public function is_rate_limit_exempt($userid) {
        // Site admins are exempt from rate limiting.
        if (is_siteadmin($userid)) {
            return true;
        }
        
        // Check if user has special capability to bypass rate limits.
        $systemcontext = \context_system::instance();
        if (has_capability('assignsubmission/s3video:bypassratelimit', $systemcontext, $userid)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Apply rate limiting check with exemption handling.
     *
     * @param string $type Type of rate limit ('upload' or 'playback')
     * @param int $userid User ID
     * @param int|string $context Assignment ID for upload, S3 key for playback
     * @throws rate_limit_exception If rate limit is exceeded
     */
    public function apply_rate_limit($type, $userid, $context = '') {
        // Skip rate limiting for exempt users.
        if ($this->is_rate_limit_exempt($userid)) {
            return;
        }
        
        if ($type === 'upload') {
            $this->check_upload_rate_limit($userid, $context);
        } else if ($type === 'playback') {
            $this->check_playback_rate_limit($userid, $context);
        } else {
            throw new \invalid_parameter_exception('Invalid rate limit type');
        }
    }
}
