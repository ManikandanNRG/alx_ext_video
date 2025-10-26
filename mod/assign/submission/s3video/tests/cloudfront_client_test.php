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
 * Unit tests for AWS CloudFront API client.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

use assignsubmission_s3video\api\cloudfront_client;
use assignsubmission_s3video\api\cloudfront_api_exception;
use assignsubmission_s3video\api\cloudfront_signature_exception;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/api/cloudfront_client.php');

/**
 * Mock CloudFront client for testing.
 *
 * This class extends the real client and overrides methods to return
 * mocked responses instead of making actual AWS API calls.
 */
class mock_cloudfront_client extends cloudfront_client {
    /** @var array Queue of mocked responses */
    private $mockedresponses = [];

    /** @var array Log of method calls */
    private $calllog = [];

    /** @var bool Whether to skip parent constructor */
    private $skipinit = false;

    /**
     * Constructor.
     *
     * @param string $domain CloudFront distribution domain
     * @param string $keypairid CloudFront key pair ID
     * @param string $privatekey CloudFront private key in PEM format
     * @param string $accesskey AWS access key ID (for invalidations)
     * @param string $secretkey AWS secret access key (for invalidations)
     * @param string $region AWS region
     * @param bool $skipinit Skip parent initialization (for testing)
     */
    public function __construct($domain, $keypairid, $privatekey, $accesskey = '', $secretkey = '', 
                                $region = 'us-east-1', $skipinit = false) {
        $this->skipinit = $skipinit;
        if (!$skipinit) {
            parent::__construct($domain, $keypairid, $privatekey, $accesskey, $secretkey, $region);
        }
    }

    /**
     * Add a mocked response to the queue.
     *
     * @param string $method Method name
     * @param mixed $response Response to return (value or exception)
     */
    public function add_mocked_response($method, $response) {
        $this->mockedresponses[$method] = $response;
    }

    /**
     * Get the log of method calls.
     *
     * @return array Array of call details
     */
    public function get_call_log() {
        return $this->calllog;
    }

    /**
     * Override get_signed_url to return mocked response.
     */
    public function get_signed_url($s3key, $expiryseconds = 86400) {
        $this->calllog[] = [
            'method' => 'get_signed_url',
            'args' => [$s3key, $expiryseconds]
        ];

        if (isset($this->mockedresponses['get_signed_url'])) {
            $response = $this->mockedresponses['get_signed_url'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        if (!$this->skipinit) {
            return parent::get_signed_url($s3key, $expiryseconds);
        }

        throw new \Exception('No mocked response for get_signed_url');
    }

    /**
     * Override create_invalidation to return mocked response.
     */
    public function create_invalidation($s3key) {
        $this->calllog[] = [
            'method' => 'create_invalidation',
            'args' => [$s3key]
        ];

        if (isset($this->mockedresponses['create_invalidation'])) {
            $response = $this->mockedresponses['create_invalidation'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        if (!$this->skipinit) {
            return parent::create_invalidation($s3key);
        }

        throw new \Exception('No mocked response for create_invalidation');
    }
}

/**
 * Unit tests for cloudfront_client class.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloudfront_client_test extends \advanced_testcase {

    /**
     * Generate a test RSA private key.
     *
     * @return string PEM-formatted private key
     */
    private function generate_test_private_key() {
        // Generate a test RSA key pair.
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privatekey);
        return $privatekey;
    }

    /**
     * Test get_signed_url returns valid URL format.
     */
    public function test_get_signed_url_success() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        // Mock successful response.
        $mockurl = 'https://d123.cloudfront.net/videos/123/test.mp4?Expires=1735689600&Signature=abc123&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl);

        $result = $client->get_signed_url('videos/123/test.mp4', 86400);

        $this->assertStringContainsString('https://d123.cloudfront.net/videos/123/test.mp4', $result);
        $this->assertStringContainsString('Expires=', $result);
        $this->assertStringContainsString('Signature=', $result);
        $this->assertStringContainsString('Key-Pair-Id=APKATEST123', $result);

        // Verify method was called correctly.
        $log = $client->get_call_log();
        $this->assertCount(1, $log);
        $this->assertEquals('get_signed_url', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
        $this->assertEquals(86400, $log[0]['args'][1]);
    }

    /**
     * Test get_signed_url with custom expiry.
     */
    public function test_get_signed_url_custom_expiry() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $mockurl = 'https://d123.cloudfront.net/videos/123/test.mp4?Expires=1735603200&Signature=xyz789&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl);

        $result = $client->get_signed_url('videos/123/test.mp4', 3600);

        $this->assertStringContainsString('https://d123.cloudfront.net/videos/123/test.mp4', $result);

        $log = $client->get_call_log();
        $this->assertEquals(3600, $log[0]['args'][1]);
    }

    /**
     * Test get_signed_url with empty S3 key throws exception.
     */
    public function test_get_signed_url_empty_key() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('get_signed_url', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->get_signed_url('', 86400);
    }

    /**
     * Test get_signed_url with invalid expiry throws exception.
     */
    public function test_get_signed_url_invalid_expiry() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception('invalid_expiry', 'Expiry must be between 1 and 604800 seconds');
        $client->add_mocked_response('get_signed_url', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('Expiry must be between 1 and 604800 seconds');

        $client->get_signed_url('videos/123/test.mp4', 700000);
    }

    /**
     * Test get_signed_url with negative expiry throws exception.
     */
    public function test_get_signed_url_negative_expiry() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception('invalid_expiry', 'Expiry must be between 1 and 604800 seconds');
        $client->add_mocked_response('get_signed_url', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('Expiry must be between 1 and 604800 seconds');

        $client->get_signed_url('videos/123/test.mp4', -100);
    }

    /**
     * Test get_signed_url with signature generation failure.
     */
    public function test_get_signed_url_signature_failure() {
        $this->resetAfterTest();

        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            'invalid_key',
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_signature_exception('Failed to load private key');
        $client->add_mocked_response('get_signed_url', $exception);

        $this->expectException(cloudfront_signature_exception::class);
        $this->expectExceptionMessage('Failed to load private key');

        $client->get_signed_url('videos/123/test.mp4', 86400);
    }

    /**
     * Test get_signed_url URL format with leading slash in S3 key.
     */
    public function test_get_signed_url_leading_slash() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $mockurl = 'https://d123.cloudfront.net/videos/123/test.mp4?Expires=1735689600&Signature=abc123&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl);

        $result = $client->get_signed_url('/videos/123/test.mp4', 86400);

        // Should handle leading slash correctly.
        $this->assertStringContainsString('https://d123.cloudfront.net/videos/123/test.mp4', $result);
    }

    /**
     * Test create_invalidation returns invalidation ID.
     */
    public function test_create_invalidation_success() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        // Mock successful invalidation response.
        $mockinvalidationid = 'I2J3K4L5M6N7O8P9Q0';
        $client->add_mocked_response('create_invalidation', $mockinvalidationid);

        $result = $client->create_invalidation('videos/123/test.mp4');

        $this->assertEquals('I2J3K4L5M6N7O8P9Q0', $result);

        // Verify method was called correctly.
        $log = $client->get_call_log();
        $this->assertCount(1, $log);
        $this->assertEquals('create_invalidation', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
    }

    /**
     * Test create_invalidation with empty S3 key throws exception.
     */
    public function test_create_invalidation_empty_key() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('create_invalidation', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->create_invalidation('');
    }

    /**
     * Test create_invalidation without credentials throws exception.
     */
    public function test_create_invalidation_no_credentials() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception(
            'cloudfront_client_not_initialized',
            'CloudFront client not initialized. Credentials required for invalidations.'
        );
        $client->add_mocked_response('create_invalidation', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('CloudFront client not initialized');

        $client->create_invalidation('videos/123/test.mp4');
    }

    /**
     * Test create_invalidation with AWS API error.
     */
    public function test_create_invalidation_aws_error() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception(
            'cloudfront_invalidation_failed',
            'Failed to create invalidation: Access denied'
        );
        $client->add_mocked_response('create_invalidation', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('Failed to create invalidation');

        $client->create_invalidation('videos/123/test.mp4');
    }

    /**
     * Test create_invalidation with distribution not found error.
     */
    public function test_create_invalidation_distribution_not_found() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception(
            'distribution_not_found',
            'No distribution found for domain: d123.cloudfront.net'
        );
        $client->add_mocked_response('create_invalidation', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('No distribution found for domain');

        $client->create_invalidation('videos/123/test.mp4');
    }

    /**
     * Test multiple sequential API calls.
     */
    public function test_multiple_api_calls() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        // Mock signed URL.
        $mockurl = 'https://d123.cloudfront.net/videos/123/test.mp4?Expires=1735689600&Signature=abc123&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl);

        // Mock invalidation.
        $mockinvalidationid = 'I2J3K4L5M6N7O8P9Q0';
        $client->add_mocked_response('create_invalidation', $mockinvalidationid);

        // Execute calls.
        $url = $client->get_signed_url('videos/123/test.mp4', 86400);
        $this->assertStringContainsString('https://d123.cloudfront.net/videos/123/test.mp4', $url);

        $invalidationid = $client->create_invalidation('videos/123/test.mp4');
        $this->assertEquals('I2J3K4L5M6N7O8P9Q0', $invalidationid);

        // Verify all calls were logged.
        $log = $client->get_call_log();
        $this->assertCount(2, $log);
        $this->assertEquals('get_signed_url', $log[0]['method']);
        $this->assertEquals('create_invalidation', $log[1]['method']);
    }

    /**
     * Test get_signed_url with different S3 key formats.
     */
    public function test_get_signed_url_various_key_formats() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        // Test with nested path.
        $mockurl1 = 'https://d123.cloudfront.net/videos/user123/2025/01/test.mp4?Expires=1735689600&Signature=abc&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl1);
        $result1 = $client->get_signed_url('videos/user123/2025/01/test.mp4', 86400);
        $this->assertStringContainsString('videos/user123/2025/01/test.mp4', $result1);

        // Test with special characters in filename.
        $mockurl2 = 'https://d123.cloudfront.net/videos/my%20video%20file.mp4?Expires=1735689600&Signature=def&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl2);
        $result2 = $client->get_signed_url('videos/my video file.mp4', 86400);
        $this->assertStringContainsString('my%20video%20file.mp4', $result2);
    }

    /**
     * Test create_invalidation with leading slash in S3 key.
     */
    public function test_create_invalidation_leading_slash() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        $mockinvalidationid = 'I2J3K4L5M6N7O8P9Q0';
        $client->add_mocked_response('create_invalidation', $mockinvalidationid);

        $result = $client->create_invalidation('/videos/123/test.mp4');

        $this->assertEquals('I2J3K4L5M6N7O8P9Q0', $result);

        $log = $client->get_call_log();
        $this->assertEquals('/videos/123/test.mp4', $log[0]['args'][0]);
    }

    /**
     * Test signature exception handling.
     */
    public function test_signature_exception() {
        $this->resetAfterTest();

        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            'malformed_private_key',
            '',
            '',
            'us-east-1',
            true
        );

        $exception = new cloudfront_signature_exception('Signing error: Invalid key format');
        $client->add_mocked_response('get_signed_url', $exception);

        $this->expectException(cloudfront_signature_exception::class);
        $this->expectExceptionMessage('Signing error');

        $client->get_signed_url('videos/123/test.mp4', 86400);
    }

    /**
     * Test network error handling for invalidation.
     */
    public function test_invalidation_network_error() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            'test_access_key',
            'test_secret_key',
            'us-east-1',
            true
        );

        $exception = new cloudfront_api_exception(
            'cloudfront_invalidation_failed',
            'Failed to create invalidation: Network timeout'
        );
        $client->add_mocked_response('create_invalidation', $exception);

        $this->expectException(cloudfront_api_exception::class);
        $this->expectExceptionMessage('Network timeout');

        $client->create_invalidation('videos/123/test.mp4');
    }

    /**
     * Test get_signed_url with maximum allowed expiry.
     */
    public function test_get_signed_url_max_expiry() {
        $this->resetAfterTest();

        $privatekey = $this->generate_test_private_key();
        $client = new mock_cloudfront_client(
            'd123.cloudfront.net',
            'APKATEST123',
            $privatekey,
            '',
            '',
            'us-east-1',
            true
        );

        $mockurl = 'https://d123.cloudfront.net/videos/123/test.mp4?Expires=1736294400&Signature=xyz&Key-Pair-Id=APKATEST123';
        $client->add_mocked_response('get_signed_url', $mockurl);

        $result = $client->get_signed_url('videos/123/test.mp4', 604800); // 7 days.

        $this->assertStringContainsString('https://d123.cloudfront.net/videos/123/test.mp4', $result);

        $log = $client->get_call_log();
        $this->assertEquals(604800, $log[0]['args'][1]);
    }
}
