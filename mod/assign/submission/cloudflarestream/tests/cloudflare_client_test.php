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
 * Unit tests for Cloudflare Stream API client.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_cloudflarestream;

use assignsubmission_cloudflarestream\api\cloudflare_client;
use assignsubmission_cloudflarestream\api\cloudflare_api_exception;
use assignsubmission_cloudflarestream\api\cloudflare_auth_exception;
use assignsubmission_cloudflarestream\api\cloudflare_video_not_found_exception;
use assignsubmission_cloudflarestream\api\cloudflare_quota_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Mock Cloudflare client for testing.
 *
 * This class extends the real client and overrides the make_request method
 * to return mocked responses instead of making actual API calls.
 */
class mock_cloudflare_client extends cloudflare_client {
    /** @var array Queue of mocked responses */
    private $mockedresponses = [];

    /** @var array Log of requests made */
    private $requestlog = [];

    /**
     * Add a mocked response to the queue.
     *
     * @param string $method HTTP method
     * @param string $endpointpattern Regex pattern to match endpoint
     * @param mixed $response Response to return (object, exception, or array with 'httpcode' and 'body')
     */
    public function add_mocked_response($method, $endpointpattern, $response) {
        $this->mockedresponses[] = [
            'method' => $method,
            'pattern' => $endpointpattern,
            'response' => $response
        ];
    }

    /**
     * Get the log of requests made.
     *
     * @return array Array of request details
     */
    public function get_request_log() {
        return $this->requestlog;
    }

    /**
     * Override make_request to return mocked responses.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @return object Mocked response
     * @throws \Exception If response is an exception
     */
    protected function make_request($method, $endpoint, $data = null) {
        // Log the request.
        $this->requestlog[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data
        ];

        // Find matching mocked response.
        foreach ($this->mockedresponses as $index => $mock) {
            if ($mock['method'] === $method && preg_match($mock['pattern'], $endpoint)) {
                // Remove used response.
                unset($this->mockedresponses[$index]);
                $this->mockedresponses = array_values($this->mockedresponses);

                // Return or throw the response.
                if ($mock['response'] instanceof \Exception) {
                    throw $mock['response'];
                }

                return $mock['response'];
            }
        }

        throw new \Exception("No mocked response found for {$method} {$endpoint}");
    }
}

/**
 * Unit tests for cloudflare_client class.
 *
 * @package    assignsubmission_cloudflarestream
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloudflare_client_test extends \advanced_testcase {

    /**
     * Test get_direct_upload_url returns correct data.
     */
    public function test_get_direct_upload_url_success() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        // Mock successful response.
        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uploadURL' => 'https://upload.cloudflarestream.com/test-upload-url',
                'uid' => 'test-video-uid-12345'
            ]
        ];

        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $mockresponse);

        $result = $client->get_direct_upload_url(21600);

        $this->assertEquals('https://upload.cloudflarestream.com/test-upload-url', $result->uploadURL);
        $this->assertEquals('test-video-uid-12345', $result->uid);

        // Verify request was made correctly.
        $log = $client->get_request_log();
        $this->assertCount(1, $log);
        $this->assertEquals('POST', $log[0]['method']);
        $this->assertStringContainsString('direct_upload', $log[0]['endpoint']);
        $this->assertEquals(21600, $log[0]['data']['maxDurationSeconds']);
    }

    /**
     * Test get_direct_upload_url with custom duration.
     */
    public function test_get_direct_upload_url_custom_duration() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uploadURL' => 'https://upload.cloudflarestream.com/test-url',
                'uid' => 'test-uid'
            ]
        ];

        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $mockresponse);

        $result = $client->get_direct_upload_url(3600);

        $log = $client->get_request_log();
        $this->assertEquals(3600, $log[0]['data']['maxDurationSeconds']);
    }

    /**
     * Test get_video_details returns correct data.
     */
    public function test_get_video_details_success() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uid' => 'test-video-uid',
                'status' => (object)['state' => 'ready'],
                'duration' => 120.5,
                'size' => 1024000
            ]
        ];

        $client->add_mocked_response('GET', '/\/accounts\/.*\/stream\/test-video-uid/', $mockresponse);

        $result = $client->get_video_details('test-video-uid');

        $this->assertEquals('test-video-uid', $result->uid);
        $this->assertEquals('ready', $result->status->state);
        $this->assertEquals(120.5, $result->duration);
        $this->assertEquals(1024000, $result->size);
    }

    /**
     * Test delete_video returns true on success.
     */
    public function test_delete_video_success() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[]
        ];

        $client->add_mocked_response('DELETE', '/\/accounts\/.*\/stream\/test-video-uid/', $mockresponse);

        $result = $client->delete_video('test-video-uid');

        $this->assertTrue($result);

        $log = $client->get_request_log();
        $this->assertEquals('DELETE', $log[0]['method']);
        $this->assertStringContainsString('test-video-uid', $log[0]['endpoint']);
    }

    /**
     * Test generate_signed_token returns token.
     */
    public function test_generate_signed_token_success() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature'
            ]
        ];

        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/.*\/token/', $mockresponse);

        $result = $client->generate_signed_token('test-video-uid', 86400);

        $this->assertEquals('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature', $result);

        $log = $client->get_request_log();
        $this->assertEquals('POST', $log[0]['method']);
        $this->assertArrayHasKey('exp', $log[0]['data']);
    }

    /**
     * Test authentication failure throws correct exception.
     */
    public function test_authentication_failure() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('invalid_token', 'test_account');

        $exception = new cloudflare_auth_exception('HTTP 401: Invalid API token');
        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $exception);

        $this->expectException(cloudflare_auth_exception::class);
        $this->expectExceptionMessage('Invalid API token');

        $client->get_direct_upload_url();
    }

    /**
     * Test video not found throws correct exception.
     */
    public function test_video_not_found() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $exception = new cloudflare_video_not_found_exception('HTTP 404: Video not found');
        $client->add_mocked_response('GET', '/\/accounts\/.*\/stream\/nonexistent/', $exception);

        $this->expectException(cloudflare_video_not_found_exception::class);
        $this->expectExceptionMessage('Video not found');

        $client->get_video_details('nonexistent');
    }

    /**
     * Test quota exceeded throws correct exception.
     */
    public function test_quota_exceeded() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $exception = new cloudflare_quota_exception('HTTP 429: Rate limit exceeded');
        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $exception);

        $this->expectException(cloudflare_quota_exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $client->get_direct_upload_url();
    }

    /**
     * Test network error handling.
     */
    public function test_network_error() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $exception = new cloudflare_api_exception('cloudflare_network_error', 'cURL error: Connection timeout');
        $client->add_mocked_response('GET', '/\/accounts\/.*\/stream\/test-uid/', $exception);

        $this->expectException(cloudflare_api_exception::class);
        $this->expectExceptionMessage('Connection timeout');

        $client->get_video_details('test-uid');
    }

    /**
     * Test invalid response format.
     */
    public function test_invalid_response_missing_upload_url() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        // Response missing uploadURL.
        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uid' => 'test-uid'
            ]
        ];

        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $mockresponse);

        $this->expectException(cloudflare_api_exception::class);
        $this->expectExceptionMessage('Missing uploadURL or uid');

        $client->get_direct_upload_url();
    }

    /**
     * Test invalid response missing result.
     */
    public function test_invalid_response_missing_result() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true
        ];

        $client->add_mocked_response('GET', '/\/accounts\/.*\/stream\/test-uid/', $mockresponse);

        $this->expectException(cloudflare_api_exception::class);
        $this->expectExceptionMessage('Missing result in response');

        $client->get_video_details('test-uid');
    }

    /**
     * Test invalid response missing token.
     */
    public function test_invalid_response_missing_token() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $mockresponse = (object)[
            'success' => true,
            'result' => (object)[]
        ];

        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/.*\/token/', $mockresponse);

        $this->expectException(cloudflare_api_exception::class);
        $this->expectExceptionMessage('Missing token in response');

        $client->generate_signed_token('test-uid');
    }

    /**
     * Test delete video with non-existent video.
     */
    public function test_delete_nonexistent_video() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        $exception = new cloudflare_video_not_found_exception('HTTP 404: Video not found');
        $client->add_mocked_response('DELETE', '/\/accounts\/.*\/stream\/nonexistent/', $exception);

        $this->expectException(cloudflare_video_not_found_exception::class);

        $client->delete_video('nonexistent');
    }

    /**
     * Test multiple sequential API calls.
     */
    public function test_multiple_api_calls() {
        $this->resetAfterTest();

        $client = new mock_cloudflare_client('test_token', 'test_account');

        // Mock upload URL request.
        $uploadresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uploadURL' => 'https://upload.url',
                'uid' => 'video-123'
            ]
        ];
        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/direct_upload/', $uploadresponse);

        // Mock video details request.
        $detailsresponse = (object)[
            'success' => true,
            'result' => (object)[
                'uid' => 'video-123',
                'status' => (object)['state' => 'ready']
            ]
        ];
        $client->add_mocked_response('GET', '/\/accounts\/.*\/stream\/video-123/', $detailsresponse);

        // Mock token generation.
        $tokenresponse = (object)[
            'success' => true,
            'result' => (object)[
                'token' => 'jwt-token-here'
            ]
        ];
        $client->add_mocked_response('POST', '/\/accounts\/.*\/stream\/.*\/token/', $tokenresponse);

        // Execute calls.
        $upload = $client->get_direct_upload_url();
        $this->assertEquals('video-123', $upload->uid);

        $details = $client->get_video_details('video-123');
        $this->assertEquals('ready', $details->status->state);

        $token = $client->generate_signed_token('video-123');
        $this->assertEquals('jwt-token-here', $token);

        // Verify all requests were logged.
        $log = $client->get_request_log();
        $this->assertCount(3, $log);
    }
}
