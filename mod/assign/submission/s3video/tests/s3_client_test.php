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
 * Unit tests for AWS S3 API client.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_s3video;

use assignsubmission_s3video\api\s3_client;
use assignsubmission_s3video\api\s3_api_exception;
use assignsubmission_s3video\api\s3_auth_exception;
use assignsubmission_s3video\api\s3_object_not_found_exception;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/api/s3_client.php');

/**
 * Mock S3 client for testing.
 *
 * This class extends the real client and overrides methods to return
 * mocked responses instead of making actual AWS API calls.
 */
class mock_s3_client extends s3_client {
    /** @var array Queue of mocked responses */
    private $mockedresponses = [];

    /** @var array Log of method calls */
    private $calllog = [];

    /** @var bool Whether to skip parent constructor */
    private $skipinit = false;

    /**
     * Constructor.
     *
     * @param string $accesskey AWS access key ID
     * @param string $secretkey AWS secret access key
     * @param string $bucket S3 bucket name
     * @param string $region AWS region
     * @param bool $skipinit Skip parent initialization (for testing)
     */
    public function __construct($accesskey, $secretkey, $bucket, $region = 'us-east-1', $skipinit = false) {
        $this->skipinit = $skipinit;
        if (!$skipinit) {
            parent::__construct($accesskey, $secretkey, $bucket, $region);
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
     * Override get_presigned_post to return mocked response.
     */
    public function get_presigned_post($s3key, $maxsize, $mimetype, $expiry = 3600) {
        $this->calllog[] = [
            'method' => 'get_presigned_post',
            'args' => [$s3key, $maxsize, $mimetype, $expiry]
        ];

        if (isset($this->mockedresponses['get_presigned_post'])) {
            $response = $this->mockedresponses['get_presigned_post'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        // Call parent if no mock.
        if (!$this->skipinit) {
            return parent::get_presigned_post($s3key, $maxsize, $mimetype, $expiry);
        }

        throw new \Exception('No mocked response for get_presigned_post');
    }

    /**
     * Override object_exists to return mocked response.
     */
    public function object_exists($s3key) {
        $this->calllog[] = [
            'method' => 'object_exists',
            'args' => [$s3key]
        ];

        if (isset($this->mockedresponses['object_exists'])) {
            $response = $this->mockedresponses['object_exists'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        if (!$this->skipinit) {
            return parent::object_exists($s3key);
        }

        throw new \Exception('No mocked response for object_exists');
    }

    /**
     * Override delete_object to return mocked response.
     */
    public function delete_object($s3key) {
        $this->calllog[] = [
            'method' => 'delete_object',
            'args' => [$s3key]
        ];

        if (isset($this->mockedresponses['delete_object'])) {
            $response = $this->mockedresponses['delete_object'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        if (!$this->skipinit) {
            return parent::delete_object($s3key);
        }

        throw new \Exception('No mocked response for delete_object');
    }

    /**
     * Override get_object_metadata to return mocked response.
     */
    public function get_object_metadata($s3key) {
        $this->calllog[] = [
            'method' => 'get_object_metadata',
            'args' => [$s3key]
        ];

        if (isset($this->mockedresponses['get_object_metadata'])) {
            $response = $this->mockedresponses['get_object_metadata'];
            if ($response instanceof \Exception) {
                throw $response;
            }
            return $response;
        }

        if (!$this->skipinit) {
            return parent::get_object_metadata($s3key);
        }

        throw new \Exception('No mocked response for get_object_metadata');
    }
}

/**
 * Unit tests for s3_client class.
 *
 * @package    assignsubmission_s3video
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class s3_client_test extends \advanced_testcase {

    /**
     * Test get_presigned_post returns correct data.
     */
    public function test_get_presigned_post_success() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        // Mock successful response.
        $mockresponse = [
            'url' => 'https://test-bucket.s3.amazonaws.com',
            'fields' => [
                'key' => 'videos/123/test.mp4',
                'policy' => 'base64encodedpolicy',
                'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
                'x-amz-credential' => 'credentials',
                'x-amz-date' => '20250101T000000Z',
                'x-amz-signature' => 'signature'
            ],
            'key' => 'videos/123/test.mp4'
        ];

        $client->add_mocked_response('get_presigned_post', $mockresponse);

        $result = $client->get_presigned_post('videos/123/test.mp4', 5368709120, 'video/mp4', 3600);

        $this->assertEquals('https://test-bucket.s3.amazonaws.com', $result['url']);
        $this->assertEquals('videos/123/test.mp4', $result['key']);
        $this->assertArrayHasKey('policy', $result['fields']);
        $this->assertArrayHasKey('x-amz-signature', $result['fields']);

        // Verify method was called correctly.
        $log = $client->get_call_log();
        $this->assertCount(1, $log);
        $this->assertEquals('get_presigned_post', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
        $this->assertEquals(5368709120, $log[0]['args'][1]);
        $this->assertEquals('video/mp4', $log[0]['args'][2]);
        $this->assertEquals(3600, $log[0]['args'][3]);
    }

    /**
     * Test get_presigned_post with custom expiry.
     */
    public function test_get_presigned_post_custom_expiry() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $mockresponse = [
            'url' => 'https://test-bucket.s3.amazonaws.com',
            'fields' => ['key' => 'test.mp4'],
            'key' => 'test.mp4'
        ];

        $client->add_mocked_response('get_presigned_post', $mockresponse);

        $client->get_presigned_post('test.mp4', 1000000, 'video/mp4', 7200);

        $log = $client->get_call_log();
        $this->assertEquals(7200, $log[0]['args'][3]);
    }

    /**
     * Test get_presigned_post with empty S3 key throws exception.
     */
    public function test_get_presigned_post_empty_key() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('get_presigned_post', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->get_presigned_post('', 1000000, 'video/mp4');
    }

    /**
     * Test get_presigned_post with invalid max size throws exception.
     */
    public function test_get_presigned_post_invalid_size() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_max_size', 'Max size must be greater than 0');
        $client->add_mocked_response('get_presigned_post', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('Max size must be greater than 0');

        $client->get_presigned_post('test.mp4', 0, 'video/mp4');
    }

    /**
     * Test get_presigned_post with invalid expiry throws exception.
     */
    public function test_get_presigned_post_invalid_expiry() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_expiry', 'Expiry must be between 1 and 604800 seconds');
        $client->add_mocked_response('get_presigned_post', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('Expiry must be between 1 and 604800 seconds');

        $client->get_presigned_post('test.mp4', 1000000, 'video/mp4', 700000);
    }

    /**
     * Test object_exists returns true when object exists.
     */
    public function test_object_exists_true() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $client->add_mocked_response('object_exists', true);

        $result = $client->object_exists('videos/123/test.mp4');

        $this->assertTrue($result);

        $log = $client->get_call_log();
        $this->assertEquals('object_exists', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
    }

    /**
     * Test object_exists returns false when object does not exist.
     */
    public function test_object_exists_false() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $client->add_mocked_response('object_exists', false);

        $result = $client->object_exists('videos/123/nonexistent.mp4');

        $this->assertFalse($result);
    }

    /**
     * Test object_exists with empty key throws exception.
     */
    public function test_object_exists_empty_key() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('object_exists', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->object_exists('');
    }

    /**
     * Test delete_object returns true on success.
     */
    public function test_delete_object_success() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $client->add_mocked_response('delete_object', true);

        $result = $client->delete_object('videos/123/test.mp4');

        $this->assertTrue($result);

        $log = $client->get_call_log();
        $this->assertEquals('delete_object', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
    }

    /**
     * Test delete_object with non-existent object throws exception.
     */
    public function test_delete_object_not_found() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_object_not_found_exception('Object not found: videos/123/nonexistent.mp4');
        $client->add_mocked_response('delete_object', $exception);

        $this->expectException(s3_object_not_found_exception::class);
        $this->expectExceptionMessage('Object not found');

        $client->delete_object('videos/123/nonexistent.mp4');
    }

    /**
     * Test delete_object with empty key throws exception.
     */
    public function test_delete_object_empty_key() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('delete_object', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->delete_object('');
    }

    /**
     * Test get_object_metadata returns correct data.
     */
    public function test_get_object_metadata_success() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $mockresponse = (object)[
            'size' => 1024000,
            'content_type' => 'video/mp4',
            'last_modified' => '2025-01-01T00:00:00Z',
            'etag' => 'abc123def456'
        ];

        $client->add_mocked_response('get_object_metadata', $mockresponse);

        $result = $client->get_object_metadata('videos/123/test.mp4');

        $this->assertEquals(1024000, $result->size);
        $this->assertEquals('video/mp4', $result->content_type);
        $this->assertEquals('2025-01-01T00:00:00Z', $result->last_modified);
        $this->assertEquals('abc123def456', $result->etag);

        $log = $client->get_call_log();
        $this->assertEquals('get_object_metadata', $log[0]['method']);
        $this->assertEquals('videos/123/test.mp4', $log[0]['args'][0]);
    }

    /**
     * Test get_object_metadata with non-existent object throws exception.
     */
    public function test_get_object_metadata_not_found() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_object_not_found_exception('Object not found: videos/123/nonexistent.mp4');
        $client->add_mocked_response('get_object_metadata', $exception);

        $this->expectException(s3_object_not_found_exception::class);
        $this->expectExceptionMessage('Object not found');

        $client->get_object_metadata('videos/123/nonexistent.mp4');
    }

    /**
     * Test get_object_metadata with empty key throws exception.
     */
    public function test_get_object_metadata_empty_key() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('invalid_s3_key', 'S3 key cannot be empty');
        $client->add_mocked_response('get_object_metadata', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('S3 key cannot be empty');

        $client->get_object_metadata('');
    }

    /**
     * Test authentication failure throws correct exception.
     */
    public function test_authentication_failure() {
        $this->resetAfterTest();

        $client = new mock_s3_client('invalid_key', 'invalid_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_auth_exception('Failed to initialize S3 client: Invalid credentials');
        $client->add_mocked_response('get_presigned_post', $exception);

        $this->expectException(s3_auth_exception::class);
        $this->expectExceptionMessage('Invalid credentials');

        $client->get_presigned_post('test.mp4', 1000000, 'video/mp4');
    }

    /**
     * Test AWS API error handling.
     */
    public function test_aws_api_error() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('s3_presigned_post_failed', 'Failed to generate presigned POST: AWS error');
        $client->add_mocked_response('get_presigned_post', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('AWS error');

        $client->get_presigned_post('test.mp4', 1000000, 'video/mp4');
    }

    /**
     * Test network error handling.
     */
    public function test_network_error() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('s3_object_check_failed', 'Failed to check object existence: Network timeout');
        $client->add_mocked_response('object_exists', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('Network timeout');

        $client->object_exists('test.mp4');
    }

    /**
     * Test multiple sequential API calls.
     */
    public function test_multiple_api_calls() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        // Mock presigned POST.
        $postresponse = [
            'url' => 'https://test-bucket.s3.amazonaws.com',
            'fields' => ['key' => 'test.mp4'],
            'key' => 'test.mp4'
        ];
        $client->add_mocked_response('get_presigned_post', $postresponse);

        // Mock object exists.
        $client->add_mocked_response('object_exists', true);

        // Mock metadata.
        $metadataresponse = (object)[
            'size' => 1024000,
            'content_type' => 'video/mp4',
            'last_modified' => '2025-01-01T00:00:00Z',
            'etag' => 'abc123'
        ];
        $client->add_mocked_response('get_object_metadata', $metadataresponse);

        // Execute calls.
        $post = $client->get_presigned_post('test.mp4', 1000000, 'video/mp4');
        $this->assertEquals('test.mp4', $post['key']);

        $exists = $client->object_exists('test.mp4');
        $this->assertTrue($exists);

        $metadata = $client->get_object_metadata('test.mp4');
        $this->assertEquals(1024000, $metadata->size);

        // Verify all calls were logged.
        $log = $client->get_call_log();
        $this->assertCount(3, $log);
        $this->assertEquals('get_presigned_post', $log[0]['method']);
        $this->assertEquals('object_exists', $log[1]['method']);
        $this->assertEquals('get_object_metadata', $log[2]['method']);
    }

    /**
     * Test delete operation error handling.
     */
    public function test_delete_operation_error() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('s3_delete_failed', 'Failed to delete object: Permission denied');
        $client->add_mocked_response('delete_object', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('Permission denied');

        $client->delete_object('test.mp4');
    }

    /**
     * Test metadata retrieval error handling.
     */
    public function test_metadata_retrieval_error() {
        $this->resetAfterTest();

        $client = new mock_s3_client('test_key', 'test_secret', 'test-bucket', 'us-east-1', true);

        $exception = new s3_api_exception('s3_metadata_failed', 'Failed to get object metadata: Access denied');
        $client->add_mocked_response('get_object_metadata', $exception);

        $this->expectException(s3_api_exception::class);
        $this->expectExceptionMessage('Access denied');

        $client->get_object_metadata('test.mp4');
    }
}
