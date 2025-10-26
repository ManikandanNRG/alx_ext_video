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
 * Retry handler for API operations with exponential backoff.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles retry logic for API operations with exponential backoff.
 */
class retry_handler {
    
    /** @var int Maximum number of retry attempts */
    const MAX_RETRIES = 3;
    
    /** @var int Base delay in milliseconds */
    const BASE_DELAY_MS = 1000;
    
    /** @var int Maximum delay in milliseconds */
    const MAX_DELAY_MS = 30000;
    
    /** @var float Exponential backoff multiplier */
    const BACKOFF_MULTIPLIER = 2.0;
    
    /** @var float Jitter factor to avoid thundering herd */
    const JITTER_FACTOR = 0.1;
    
    /**
     * Execute a callable with retry logic and exponential backoff.
     *
     * @param callable $operation The operation to execute
     * @param array $transientErrors Array of error types that should trigger retries
     * @param int $maxRetries Maximum number of retry attempts
     * @return mixed The result of the operation
     * @throws Exception The last exception if all retries fail
     */
    public static function execute_with_retry(callable $operation, array $transientErrors = [], int $maxRetries = self::MAX_RETRIES) {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt <= $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                // Don't retry if we've exceeded max attempts
                if ($attempt > $maxRetries) {
                    break;
                }
                
                // Don't retry if this is not a transient error
                if (!self::is_transient_error($e, $transientErrors)) {
                    break;
                }
                
                // Calculate delay with exponential backoff and jitter
                $delay = self::calculate_delay($attempt);
                
                // Log retry attempt
                error_log(sprintf(
                    'Cloudflare Stream: Retrying operation (attempt %d/%d) after %dms delay. Error: %s',
                    $attempt,
                    $maxRetries,
                    $delay,
                    $e->getMessage()
                ));
                
                // Wait before retrying (convert to seconds for usleep)
                usleep($delay * 1000);
            }
        }
        
        // All retries failed, throw the last exception
        throw $lastException;
    }
    
    /**
     * Check if an error is transient and should trigger a retry.
     *
     * @param Exception $exception The exception to check
     * @param array $transientErrors Array of error types that should trigger retries
     * @return bool True if the error is transient
     */
    protected static function is_transient_error(\Exception $exception, array $transientErrors = []): bool {
        // Default transient error patterns
        $defaultTransientErrors = [
            'network',
            'timeout',
            'connection',
            'temporary',
            'server error',
            '5xx',
            'service unavailable',
            'too many requests',
            'rate limit',
            'quota exceeded'
        ];
        
        // Merge with provided transient errors
        $allTransientErrors = array_merge($defaultTransientErrors, $transientErrors);
        
        // Check exception type
        if ($exception instanceof \assignsubmission_cloudflarestream\api\cloudflare_quota_exception) {
            return true;
        }
        
        // Check exception message
        $errorMessage = strtolower($exception->getMessage());
        foreach ($allTransientErrors as $pattern) {
            if (strpos($errorMessage, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        // Check for HTTP status codes in API exceptions
        if ($exception instanceof \assignsubmission_cloudflarestream\api\cloudflare_api_exception) {
            $debugInfo = $exception->debuginfo ?? '';
            if (preg_match('/HTTP (\d+)/', $debugInfo, $matches)) {
                $httpCode = (int)$matches[1];
                // Retry on 5xx server errors and 429 rate limiting
                if ($httpCode >= 500 || $httpCode === 429) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calculate delay for retry with exponential backoff and jitter.
     *
     * @param int $attempt The current attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected static function calculate_delay(int $attempt): int {
        // Calculate exponential backoff: base_delay * (multiplier ^ (attempt - 1))
        $delay = self::BASE_DELAY_MS * pow(self::BACKOFF_MULTIPLIER, $attempt - 1);
        
        // Cap at maximum delay
        $delay = min($delay, self::MAX_DELAY_MS);
        
        // Add jitter to avoid thundering herd problem
        $jitter = $delay * self::JITTER_FACTOR * (mt_rand() / mt_getrandmax());
        $delay += $jitter;
        
        return (int)$delay;
    }
    
    /**
     * Get retry delay for a specific attempt (for client-side use).
     *
     * @param int $attempt The attempt number (1-based)
     * @return int Delay in milliseconds
     */
    public static function get_retry_delay(int $attempt): int {
        return self::calculate_delay($attempt);
    }
    
    /**
     * Check if an operation should be retried based on error analysis.
     *
     * @param Exception $exception The exception that occurred
     * @param int $currentAttempt Current attempt number (1-based)
     * @param int $maxAttempts Maximum allowed attempts
     * @return array Array with 'should_retry' boolean and 'delay_ms' integer
     */
    public static function should_retry(\Exception $exception, int $currentAttempt, int $maxAttempts = self::MAX_RETRIES): array {
        $shouldRetry = false;
        $delayMs = 0;
        
        // Don't retry if we've exceeded max attempts
        if ($currentAttempt < $maxAttempts) {
            // Check if error is transient
            if (self::is_transient_error($exception)) {
                $shouldRetry = true;
                $delayMs = self::calculate_delay($currentAttempt + 1);
            }
        }
        
        return [
            'should_retry' => $shouldRetry,
            'delay_ms' => $delayMs,
            'attempt' => $currentAttempt,
            'max_attempts' => $maxAttempts
        ];
    }
}