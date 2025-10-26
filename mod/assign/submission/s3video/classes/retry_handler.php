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
 * Retry handler for AWS API operations.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

defined('MOODLE_INTERNAL') || die();

use assignsubmission_s3video\api\s3_api_exception;
use assignsubmission_s3video\api\cloudfront_api_exception;

/**
 * Exception thrown when maximum retry attempts are exceeded.
 */
class max_retries_exceeded_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $operation The operation that failed
     * @param int $attempts Number of attempts made
     * @param string $lasterror Last error message
     */
    public function __construct($operation, $attempts, $lasterror) {
        $debuginfo = "Operation: {$operation}, Attempts: {$attempts}, Last error: {$lasterror}";
        parent::__construct('max_retries_exceeded', 'assignsubmission_s3video', '', null, $debuginfo);
    }
}

/**
 * Retry handler class for AWS API operations.
 *
 * Implements exponential backoff with jitter for transient failures.
 */
class retry_handler {
    /** @var int Maximum number of retry attempts */
    private $maxretries;

    /** @var int Initial delay in milliseconds */
    private $initialdelay;

    /** @var int Maximum delay in milliseconds */
    private $maxdelay;

    /** @var float Backoff multiplier */
    private $backoffmultiplier;

    /** @var bool Whether to add jitter to delays */
    private $usejitter;

    /**
     * Constructor.
     *
     * @param int $maxretries Maximum number of retry attempts (default: 3)
     * @param int $initialdelay Initial delay in milliseconds (default: 100)
     * @param int $maxdelay Maximum delay in milliseconds (default: 5000)
     * @param float $backoffmultiplier Backoff multiplier (default: 2.0)
     * @param bool $usejitter Whether to add jitter (default: true)
     */
    public function __construct(
        $maxretries = 3,
        $initialdelay = 100,
        $maxdelay = 5000,
        $backoffmultiplier = 2.0,
        $usejitter = true
    ) {
        $this->maxretries = $maxretries;
        $this->initialdelay = $initialdelay;
        $this->maxdelay = $maxdelay;
        $this->backoffmultiplier = $backoffmultiplier;
        $this->usejitter = $usejitter;
    }

    /**
     * Execute a callable with automatic retry on transient failures.
     *
     * @param callable $operation The operation to execute
     * @param string $operationname Name of the operation (for logging)
     * @param array $context Additional context for logging
     * @return mixed The result of the operation
     * @throws max_retries_exceeded_exception If max retries exceeded
     * @throws \Exception If a non-retryable error occurs
     */
    public function execute_with_retry(callable $operation, $operationname = 'operation', array $context = []) {
        $attempt = 0;
        $lasterror = null;

        while ($attempt <= $this->maxretries) {
            try {
                // Execute the operation.
                $result = $operation();

                // Log successful retry if this wasn't the first attempt.
                if ($attempt > 0) {
                    $this->log_retry_success($operationname, $attempt, $context);
                }

                return $result;

            } catch (\Exception $e) {
                $lasterror = $e;
                $attempt++;

                // Check if error is retryable.
                if (!$this->is_retryable_error($e)) {
                    // Non-retryable error - throw immediately.
                    throw $e;
                }

                // Check if we've exceeded max retries.
                if ($attempt > $this->maxretries) {
                    // Log final failure.
                    $this->log_retry_failure($operationname, $attempt - 1, $e, $context);
                    throw new max_retries_exceeded_exception($operationname, $attempt - 1, $e->getMessage());
                }

                // Log retry attempt.
                $this->log_retry_attempt($operationname, $attempt, $e, $context);

                // Calculate and apply delay.
                $delay = $this->calculate_delay($attempt);
                $this->sleep($delay);
            }
        }

        // Should never reach here, but just in case.
        throw new max_retries_exceeded_exception($operationname, $attempt, $lasterror->getMessage());
    }

    /**
     * Determine if an error is retryable.
     *
     * @param \Exception $error The error to check
     * @return bool True if error is retryable
     */
    private function is_retryable_error(\Exception $error) {
        // Network errors are always retryable.
        if ($this->is_network_error($error)) {
            return true;
        }

        // AWS throttling errors are retryable.
        if ($this->is_throttling_error($error)) {
            return true;
        }

        // AWS service errors (5xx) are retryable.
        if ($this->is_service_error($error)) {
            return true;
        }

        // Timeout errors are retryable.
        if ($this->is_timeout_error($error)) {
            return true;
        }

        // Authentication errors are NOT retryable.
        if ($this->is_auth_error($error)) {
            return false;
        }

        // Validation errors are NOT retryable.
        if ($this->is_validation_error($error)) {
            return false;
        }

        // Permission errors are NOT retryable.
        if ($this->is_permission_error($error)) {
            return false;
        }

        // Default: not retryable.
        return false;
    }

    /**
     * Check if error is a network error.
     *
     * @param \Exception $error The error
     * @return bool True if network error
     */
    private function is_network_error(\Exception $error) {
        $message = strtolower($error->getMessage());
        $networkkeywords = [
            'network',
            'connection',
            'timeout',
            'timed out',
            'could not resolve host',
            'failed to connect',
            'connection refused',
            'connection reset',
        ];

        foreach ($networkkeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if error is a throttling error.
     *
     * @param \Exception $error The error
     * @return bool True if throttling error
     */
    private function is_throttling_error(\Exception $error) {
        $message = strtolower($error->getMessage());
        $throttlingkeywords = [
            'throttl',
            'rate limit',
            'too many requests',
            'slowdown',
            'requestlimitexceeded',
        ];

        foreach ($throttlingkeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        // Check for HTTP 429 status.
        if (method_exists($error, 'getStatusCode') && $error->getStatusCode() === 429) {
            return true;
        }

        return false;
    }

    /**
     * Check if error is a service error (5xx).
     *
     * @param \Exception $error The error
     * @return bool True if service error
     */
    private function is_service_error(\Exception $error) {
        // Check for HTTP 5xx status codes.
        if (method_exists($error, 'getStatusCode')) {
            $status = $error->getStatusCode();
            return $status >= 500 && $status < 600;
        }

        $message = strtolower($error->getMessage());
        $servicekeywords = [
            'internal server error',
            'service unavailable',
            'bad gateway',
            'gateway timeout',
        ];

        foreach ($servicekeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if error is a timeout error.
     *
     * @param \Exception $error The error
     * @return bool True if timeout error
     */
    private function is_timeout_error(\Exception $error) {
        $message = strtolower($error->getMessage());
        return strpos($message, 'timeout') !== false || strpos($message, 'timed out') !== false;
    }

    /**
     * Check if error is an authentication error.
     *
     * @param \Exception $error The error
     * @return bool True if auth error
     */
    private function is_auth_error(\Exception $error) {
        if ($error instanceof \assignsubmission_s3video\api\s3_auth_exception) {
            return true;
        }

        $message = strtolower($error->getMessage());
        $authkeywords = [
            'authentication',
            'unauthorized',
            'invalid credentials',
            'access denied',
            'forbidden',
        ];

        foreach ($authkeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        // Check for HTTP 401/403 status.
        if (method_exists($error, 'getStatusCode')) {
            $status = $error->getStatusCode();
            return $status === 401 || $status === 403;
        }

        return false;
    }

    /**
     * Check if error is a validation error.
     *
     * @param \Exception $error The error
     * @return bool True if validation error
     */
    private function is_validation_error(\Exception $error) {
        $message = strtolower($error->getMessage());
        $validationkeywords = [
            'invalid',
            'validation',
            'malformed',
            'bad request',
        ];

        foreach ($validationkeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        // Check for HTTP 400 status.
        if (method_exists($error, 'getStatusCode') && $error->getStatusCode() === 400) {
            return true;
        }

        return false;
    }

    /**
     * Check if error is a permission error.
     *
     * @param \Exception $error The error
     * @return bool True if permission error
     */
    private function is_permission_error(\Exception $error) {
        $message = strtolower($error->getMessage());
        return strpos($message, 'permission') !== false || strpos($message, 'nopermission') !== false;
    }

    /**
     * Calculate delay for retry attempt using exponential backoff.
     *
     * @param int $attempt The attempt number (1-indexed)
     * @return int Delay in milliseconds
     */
    private function calculate_delay($attempt) {
        // Calculate base delay with exponential backoff.
        $delay = $this->initialdelay * pow($this->backoffmultiplier, $attempt - 1);

        // Cap at maximum delay.
        $delay = min($delay, $this->maxdelay);

        // Add jitter if enabled (randomize between 50% and 100% of calculated delay).
        if ($this->usejitter) {
            $delay = $delay * (0.5 + (mt_rand() / mt_getrandmax()) * 0.5);
        }

        return (int) $delay;
    }

    /**
     * Sleep for specified milliseconds.
     *
     * @param int $milliseconds Milliseconds to sleep
     */
    private function sleep($milliseconds) {
        usleep($milliseconds * 1000);
    }

    /**
     * Log retry attempt.
     *
     * @param string $operation Operation name
     * @param int $attempt Attempt number
     * @param \Exception $error The error
     * @param array $context Additional context
     */
    private function log_retry_attempt($operation, $attempt, \Exception $error, array $context) {
        $message = "Retry attempt {$attempt}/{$this->maxretries} for operation '{$operation}': {$error->getMessage()}";
        debugging($message, DEBUG_DEVELOPER);

        // Log to system if logger is available.
        if (class_exists('\assignsubmission_s3video\logger')) {
            $contextdata = array_merge($context, [
                'operation' => $operation,
                'attempt' => $attempt,
                'max_retries' => $this->maxretries,
                'error_type' => get_class($error),
                'error_message' => $error->getMessage(),
            ]);
            \assignsubmission_s3video\logger::log_event(
                $context['userid'] ?? 0,
                $context['assignmentid'] ?? 0,
                $context['submissionid'] ?? 0,
                $context['s3_key'] ?? '',
                'retry_attempt',
                $contextdata
            );
        }
    }

    /**
     * Log successful retry.
     *
     * @param string $operation Operation name
     * @param int $totalattempts Total attempts made
     * @param array $context Additional context
     */
    private function log_retry_success($operation, $totalattempts, array $context) {
        $message = "Operation '{$operation}' succeeded after {$totalattempts} attempts";
        debugging($message, DEBUG_DEVELOPER);

        if (class_exists('\assignsubmission_s3video\logger')) {
            $contextdata = array_merge($context, [
                'operation' => $operation,
                'total_attempts' => $totalattempts,
            ]);
            \assignsubmission_s3video\logger::log_event(
                $context['userid'] ?? 0,
                $context['assignmentid'] ?? 0,
                $context['submissionid'] ?? 0,
                $context['s3_key'] ?? '',
                'retry_success',
                $contextdata
            );
        }
    }

    /**
     * Log retry failure.
     *
     * @param string $operation Operation name
     * @param int $totalattempts Total attempts made
     * @param \Exception $error The final error
     * @param array $context Additional context
     */
    private function log_retry_failure($operation, $totalattempts, \Exception $error, array $context) {
        $message = "Operation '{$operation}' failed after {$totalattempts} attempts: {$error->getMessage()}";
        debugging($message, DEBUG_DEVELOPER);

        if (class_exists('\assignsubmission_s3video\logger')) {
            $contextdata = array_merge($context, [
                'operation' => $operation,
                'total_attempts' => $totalattempts,
                'error_type' => get_class($error),
                'error_message' => $error->getMessage(),
            ]);
            \assignsubmission_s3video\logger::log_api_error(
                $context['userid'] ?? 0,
                $context['assignmentid'] ?? 0,
                $context['submissionid'] ?? 0,
                $context['s3_key'] ?? '',
                'retry_failed',
                $error->getMessage(),
                get_class($error),
                $contextdata
            );
        }
    }
}
