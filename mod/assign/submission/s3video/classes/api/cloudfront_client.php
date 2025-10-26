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
 * AWS CloudFront API client.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

/**
 * Exception thrown when AWS CloudFront API requests fail.
 */
class cloudfront_api_exception extends \moodle_exception {
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
 * Exception thrown when CloudFront signature generation fails.
 */
class cloudfront_signature_exception extends cloudfront_api_exception {
    /**
     * Constructor.
     *
     * @param string $debuginfo Additional debug information
     */
    public function __construct($debuginfo = '') {
        parent::__construct('cloudfront_signature_failed', $debuginfo);
    }
}

/**
 * AWS CloudFront API client class.
 *
 * Handles all interactions with AWS CloudFront including signed URL generation
 * and cache invalidation.
 */
class cloudfront_client {
    /** @var CloudFrontClient AWS CloudFront client instance */
    private $cfclient;

    /** @var string CloudFront distribution domain */
    private $domain;

    /** @var string CloudFront key pair ID */
    private $keypairid;

    /** @var string CloudFront private key (PEM format) */
    private $privatekey;

    /**
     * Constructor.
     *
     * @param string $domain CloudFront distribution domain (e.g., d123.cloudfront.net)
     * @param string $keypairid CloudFront key pair ID
     * @param string $privatekey CloudFront private key in PEM format
     * @param string $accesskey AWS access key ID (for invalidations)
     * @param string $secretkey AWS secret access key (for invalidations)
     * @param string $region AWS region (default: us-east-1)
     */
    public function __construct($domain, $keypairid, $privatekey, $accesskey = '', $secretkey = '', $region = 'us-east-1') {
        $this->domain = rtrim($domain, '/');
        $this->keypairid = $keypairid;
        $this->privatekey = $privatekey;

        // Initialize CloudFront client only if credentials provided (for invalidations).
        if (!empty($accesskey) && !empty($secretkey)) {
            try {
                $this->cfclient = new CloudFrontClient([
                    'version' => 'latest',
                    'region' => $region,
                    'credentials' => [
                        'key' => $accesskey,
                        'secret' => $secretkey,
                    ],
                ]);
            } catch (\Exception $e) {
                throw new cloudfront_api_exception(
                    'cloudfront_init_failed',
                    'Failed to initialize CloudFront client: ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Generate a CloudFront signed URL for secure video access.
     *
     * @param string $s3key The S3 key (path) of the video
     * @param int $expiryseconds Time in seconds until URL expires (default: 86400 = 24 hours)
     * @return string The signed CloudFront URL
     * @throws cloudfront_signature_exception If signature generation fails
     */
    public function get_signed_url($s3key, $expiryseconds = 86400) {
        try {
            // Validate inputs.
            if (empty($s3key)) {
                throw new cloudfront_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }
            if ($expiryseconds <= 0 || $expiryseconds > 604800) { // Max 7 days.
                throw new cloudfront_api_exception('invalid_expiry', 'Expiry must be between 1 and 604800 seconds');
            }

            // Build the resource URL.
            $resource = 'https://' . $this->domain . '/' . ltrim($s3key, '/');
            $expires = time() + $expiryseconds;

            // Create the policy.
            $policy = $this->create_policy($resource, $expires);

            // Generate the signature.
            $signature = $this->sign_policy($policy);

            // Build the signed URL.
            $signedurl = $resource . '?' .
                'Expires=' . $expires .
                '&Signature=' . $signature .
                '&Key-Pair-Id=' . $this->keypairid;

            return $signedurl;

        } catch (cloudfront_api_exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new cloudfront_signature_exception('Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Create a CloudFront invalidation to clear cached content.
     *
     * @param string $s3key The S3 key (path) to invalidate
     * @return string The invalidation ID
     * @throws cloudfront_api_exception If invalidation creation fails
     */
    public function create_invalidation($s3key) {
        try {
            if (empty($this->cfclient)) {
                throw new cloudfront_api_exception(
                    'cloudfront_client_not_initialized',
                    'CloudFront client not initialized. Credentials required for invalidations.'
                );
            }

            if (empty($s3key)) {
                throw new cloudfront_api_exception('invalid_s3_key', 'S3 key cannot be empty');
            }

            // Get distribution ID from domain.
            $distributionid = $this->get_distribution_id();

            // Create invalidation.
            $result = $this->cfclient->createInvalidation([
                'DistributionId' => $distributionid,
                'InvalidationBatch' => [
                    'CallerReference' => uniqid('moodle-s3video-', true),
                    'Paths' => [
                        'Quantity' => 1,
                        'Items' => ['/' . ltrim($s3key, '/')],
                    ],
                ],
            ]);

            return $result['Invalidation']['Id'];

        } catch (AwsException $e) {
            throw new cloudfront_api_exception(
                'cloudfront_invalidation_failed',
                'Failed to create invalidation: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new cloudfront_api_exception(
                'cloudfront_invalidation_failed',
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create a CloudFront policy for signed URLs.
     *
     * @param string $resource The resource URL
     * @param int $expires Unix timestamp when the URL expires
     * @return string JSON-encoded policy
     */
    private function create_policy($resource, $expires) {
        $policy = [
            'Statement' => [
                [
                    'Resource' => $resource,
                    'Condition' => [
                        'DateLessThan' => [
                            'AWS:EpochTime' => $expires,
                        ],
                    ],
                ],
            ],
        ];

        return json_encode($policy, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sign a CloudFront policy using RSA-SHA1.
     *
     * @param string $policy The policy to sign
     * @return string URL-safe base64-encoded signature
     * @throws cloudfront_signature_exception If signing fails
     */
    private function sign_policy($policy) {
        try {
            // Load the private key.
            $pkeyid = openssl_pkey_get_private($this->privatekey);
            if ($pkeyid === false) {
                throw new cloudfront_signature_exception('Failed to load private key');
            }

            // Sign the policy.
            $signature = '';
            $success = openssl_sign($policy, $signature, $pkeyid, OPENSSL_ALGO_SHA1);
            openssl_free_key($pkeyid);

            if (!$success) {
                throw new cloudfront_signature_exception('Failed to sign policy');
            }

            // Encode signature for URL.
            return $this->url_safe_base64_encode($signature);

        } catch (\Exception $e) {
            throw new cloudfront_signature_exception('Signing error: ' . $e->getMessage());
        }
    }

    /**
     * Encode data in URL-safe base64 format for CloudFront.
     *
     * CloudFront requires base64 encoding with these character replacements:
     * + becomes -
     * = becomes _
     * / becomes ~
     *
     * @param string $data The data to encode
     * @return string URL-safe base64-encoded string
     */
    private function url_safe_base64_encode($data) {
        // Standard base64 encode
        $encoded = base64_encode($data);
        // Replace characters for CloudFront URL-safe format
        $encoded = str_replace('+', '-', $encoded);
        $encoded = str_replace('=', '_', $encoded);
        $encoded = str_replace('/', '~', $encoded);
        return $encoded;
    }

    /**
     * Get the CloudFront distribution ID from the domain.
     *
     * @return string The distribution ID
     * @throws cloudfront_api_exception If distribution cannot be found
     */
    private function get_distribution_id() {
        try {
            // List distributions and find matching domain.
            $result = $this->cfclient->listDistributions();

            if (isset($result['DistributionList']['Items'])) {
                foreach ($result['DistributionList']['Items'] as $distribution) {
                    if ($distribution['DomainName'] === $this->domain) {
                        return $distribution['Id'];
                    }
                }
            }

            throw new cloudfront_api_exception(
                'distribution_not_found',
                'No distribution found for domain: ' . $this->domain
            );

        } catch (AwsException $e) {
            throw new cloudfront_api_exception(
                'distribution_lookup_failed',
                'Failed to lookup distribution: ' . $e->getMessage()
            );
        }
    }
}
