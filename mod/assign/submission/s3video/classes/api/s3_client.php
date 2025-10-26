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
 * AWS S3 API client.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\PostObjectV4;

/**
 * Exception thrown when AWS S3 API requests fail.
 */
class s3_api_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode The error code
     * @param string $debuginfo Additional debug information
     */
    public function __construct($errorcode, $debuginfo = '') {
        parent::__construct($errorcode, 'assignsubmission_s3video', '', null, $debuginfo);
    }
}

/**
 * Exception thrown when authentication with AWS S3 fails.
 */
class s3_auth_exception extends s3_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('s3_auth_failed', $debuginfo);
    }
}

/**
 * Exception thrown when a requested object is not found.
 */
class s3_object_not_found_exception extends s3_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('s3_object_not_found', $debuginfo);
    }
}

/**
 * AWS S3 API client class.
 *
 * Handles all interactions with AWS S3 including presigned POST generation,
 * object existence checks, deletion, and metadata retrieval.
 */
class s3_client {
    /** @var S3Client AWS S3 client instance */
    private $s3client;

    /** @var string S3 bucket name */
    private $bucket;

    /** @var string AWS region */
    private $region;

    /**
     * Constructor.
     *
     * @param string $accesskey AWS access key ID
     * @param string $secretkey AWS secret access key
     * @param string $bucket S3 bucket name
     * @param string $region AWS region (default: us-east-1)
     */
    public function __construct($accesskey, $secretkey, $bucket, $region = 'us-east-1') {
        $this->bucket = $bucket;
        $this->region = $region;

        try {
            $this->s3client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $accesskey,
                    'secret' => $secretkey,
                ],
            ]);
        } catch (\Exception $e) {
            throw new s3_auth_exception('Failed to initialize S3 client: ' . $e->getMessage());
        }
    }

    /**
     * Generate presigned POST data for direct browser upload to S3.
     *
     * @param string $s3key The S3 key (path) where the file will be stored
     * @param int $maxsize Maximum file size in bytes
     * @param string $mimetype Expected MIME type (e.g., 'video/mp4')
     * @param int $expiry Expiration time in seconds (default: 3600 = 1 hour)
     * @return array Array containing 'url', 'fields', and 'key'
     * @throws s3_api_exception If presigned POST generation fails
     */
    public function get_presigned_post($s3key, $maxsize, $mimetype, $expiry = 3600) {
        try {
            // Validate inputs.
            if (empty($s3key)) {
                throw new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }
            if ($maxsize <= 0) {
                throw new s3_api_exception('invalid_max_size', 'Max size must be greater than 0');
            }
            if ($expiry <= 0 || $expiry > 604800) { // Max 7 days.
                throw new s3_api_exception('invalid_expiry', 'Expiry must be between 1 and 604800 seconds');
            }

            // Create PostObjectV4 for presigned POST.
            // Form inputs that will be included as hidden fields.
            $forminputs = [
                'key' => $s3key,
                'Content-Type' => $mimetype,
            ];

            // Policy conditions.
            $options = [
                ['bucket' => $this->bucket],
                ['key' => $s3key],
                ['Content-Type' => $mimetype],
                ['content-length-range', 1, $maxsize],
            ];

            $expires = '+' . $expiry . ' seconds';
            $postobject = new PostObjectV4(
                $this->s3client,
                $this->bucket,
                $forminputs,
                $options,
                $expires
            );

            $formattributes = $postobject->getFormAttributes();
            $forminputs = $postobject->getFormInputs();

            return [
                'url' => $formattributes['action'],
                'fields' => $forminputs,
                'key' => $s3key,
            ];

        } catch (AwsException $e) {
            throw new s3_api_exception(
                's3_presigned_post_failed',
                'Failed to generate presigned POST: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new s3_api_exception(
                's3_presigned_post_failed',
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if an object exists in S3.
     *
     * @param string $s3key The S3 key to check
     * @return bool True if object exists, false otherwise
     * @throws s3_api_exception If the check fails
     */
    public function object_exists($s3key) {
        try {
            if (empty($s3key)) {
                throw new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }

            return $this->s3client->doesObjectExist($this->bucket, $s3key);

        } catch (AwsException $e) {
            throw new s3_api_exception(
                's3_object_check_failed',
                'Failed to check object existence: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new s3_api_exception(
                's3_object_check_failed',
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Delete an object from S3.
     *
     * @param string $s3key The S3 key to delete
     * @return bool True if deletion was successful
     * @throws s3_object_not_found_exception If the object is not found
     * @throws s3_api_exception If deletion fails
     */
    public function delete_object($s3key) {
        try {
            if (empty($s3key)) {
                throw new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }

            // Check if object exists before attempting deletion.
            if (!$this->object_exists($s3key)) {
                throw new s3_object_not_found_exception('Object not found: ' . $s3key);
            }

            $this->s3client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3key,
            ]);

            return true;

        } catch (s3_object_not_found_exception $e) {
            throw $e;
        } catch (AwsException $e) {
            throw new s3_api_exception(
                's3_delete_failed',
                'Failed to delete object: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new s3_api_exception(
                's3_delete_failed',
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get metadata for an S3 object.
     *
     * @param string $s3key The S3 key
     * @return object Object containing metadata (size, content_type, last_modified, etag)
     * @throws s3_object_not_found_exception If the object is not found
     * @throws s3_api_exception If metadata retrieval fails
     */
    public function get_object_metadata($s3key) {
        try {
            if (empty($s3key)) {
                throw new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }

            $result = $this->s3client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $s3key,
            ]);

            return (object) [
                'size' => $result['ContentLength'],
                'content_type' => $result['ContentType'],
                'last_modified' => $result['LastModified'],
                'etag' => trim($result['ETag'], '"'),
            ];

        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFound' || $e->getStatusCode() === 404) {
                throw new s3_object_not_found_exception('Object not found: ' . $s3key);
            }
            throw new s3_api_exception(
                's3_metadata_failed',
                'Failed to get object metadata: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new s3_api_exception(
                's3_metadata_failed',
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }
}
