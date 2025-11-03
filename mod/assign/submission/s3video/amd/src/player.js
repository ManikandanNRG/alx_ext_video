// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * S3 Video player module using Video.js.
 *
 * @module     assignsubmission_s3video/player
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function ($, Ajax) {

    /**
     * URL expiry buffer in seconds (refresh 1 hour before expiry).
     * @const {number}
     */
    const URL_REFRESH_BUFFER = 3600;

    /**
     * S3VideoPlayer class for handling video playback with Video.js.
     */
    class S3VideoPlayer {
        /**
         * Constructor.
         *
         * @param {string} s3Key The S3 key for the video
         * @param {number} submissionId The submission ID
         * @param {string} containerId The container element ID
         */
        constructor(s3Key, submissionId, containerId) {
            this.s3Key = s3Key;
            this.submissionId = submissionId;
            this.containerId = containerId;
            this.container = null;
            this.videoElement = null;
            this.player = null;
            this.signedUrl = null;
            this.urlExpiry = null;
            this.refreshTimer = null;
        }

        /**
         * Initialize the player and load the video.
         */
        async init() {
            this.container = $('#' + this.containerId);

            if (this.container.length === 0) {
                // eslint-disable-next-line no-console
                console.error('Player container not found:', this.containerId);
                return;
            }

            try {
                // Show loading indicator
                this.showLoading();

                // Get signed URL
                await this.getSignedUrl();

                // Initialize Video.js player
                this.initializeVideoJs();

                // Set up URL refresh
                this.scheduleUrlRefresh();

            } catch (error) {
                this.handleError(error);
            }
        }

        /**
         * Get a signed playback URL from Moodle backend.
         *
         * @return {Promise<void>}
         */
        async getSignedUrl() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/s3video/ajax/get_playback_url.php',
                    method: 'GET',
                    data: {
                        submissionid: this.submissionId,
                        s3key: this.s3Key,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    console.log('S3 Video: AJAX response:', data);
                    if (data.success) {
                        this.signedUrl = data.data.signed_url;
                        this.urlExpiry = Date.now() + (data.data.expires_in * 1000);
                        console.log('S3 Video: Got signed URL:', this.signedUrl);
                        resolve();
                    } else {
                        const error = new Error(data.user_message || data.message || 'Failed to get playback URL');
                        error.errorType = data.error_type || 'unknown';
                        error.suggestions = data.suggestions || [];
                        error.canRetry = data.can_retry !== false;
                        error.serverResponse = data;
                        reject(error);
                    }
                }).fail((jqXHR) => {
                    let errorMessage = 'Network error occurred while loading video';
                    let errorType = 'network_error';
                    let guidance = 'Please check your internet connection and try again.';
                    let canRetry = true;

                    // Try to parse error response.
                    if (jqXHR.responseJSON) {
                        errorMessage = jqXHR.responseJSON.error || errorMessage;
                        errorType = jqXHR.responseJSON.error_type || errorType;
                        guidance = jqXHR.responseJSON.guidance || guidance;
                        canRetry = jqXHR.responseJSON.can_retry !== false;
                    } else if (jqXHR.status === 403) {
                        errorMessage = 'You do not have permission to view this video';
                        errorType = 'permission_error';
                        guidance = 'Contact your instructor if you believe this is an error.';
                        canRetry = false;
                    } else if (jqXHR.status === 429) {
                        errorMessage = 'Too many requests. Please wait a moment and try again.';
                        errorType = 'rate_limit';
                        guidance = 'You may be accessing videos too frequently. Wait a moment and try again.';
                        canRetry = true;
                    } else if (jqXHR.status >= 500) {
                        errorMessage = 'Server error occurred. Please try again later.';
                        errorType = 'server_error';
                        guidance = 'This is usually a temporary issue. Wait a few minutes and try again.';
                        canRetry = true;
                    }

                    const error = new Error(errorMessage);
                    error.errorType = errorType;
                    error.guidance = guidance;
                    error.canRetry = canRetry;
                    error.httpStatus = jqXHR.status;
                    reject(error);
                });
            });
        }

        /**
         * Initialize native HTML5 video player with the signed URL.
         */
        initializeVideoJs() {
            // Clear loading indicator
            this.container.empty();

            // Create video element with native HTML5 player
            this.videoElement = $('<video>')
                .attr('id', 's3video-player-' + this.submissionId)
                .attr('controls', true)
                .attr('preload', 'metadata')
                .attr('playsinline', true)
                .css({
                    width: '100%',
                    height: '100%',
                    maxWidth: 'none',
                    backgroundColor: '#000',
                    borderRadius: '4px',
                    display: 'block',
                    objectFit: 'contain'
                });

            // Add source
            console.log('S3 Video: Setting video source to:', this.signedUrl);
            const source = $('<source>')
                .attr('src', this.signedUrl)
                .attr('type', 'video/mp4');

            this.videoElement.append(source);

            // Add fallback message
            this.videoElement.append(
                $('<p>').text('Your browser does not support the video tag.')
            );

            this.container.append(this.videoElement);

            // Get native video element
            this.player = this.videoElement[0];

            // Handle video events
            this.videoElement.on('error', () => {
                const error = this.player.error;
                let errorMessage = 'Video playback error';

                if (error) {
                    switch (error.code) {
                        case 1: // MEDIA_ERR_ABORTED
                            errorMessage = 'Video playback was aborted';
                            break;
                        case 2: // MEDIA_ERR_NETWORK
                            errorMessage = 'Network error occurred while loading video';
                            break;
                        case 3: // MEDIA_ERR_DECODE
                            errorMessage = 'Video format not supported or corrupted';
                            break;
                        case 4: // MEDIA_ERR_SRC_NOT_SUPPORTED
                            errorMessage = 'Video format not supported by your browser';
                            break;
                        default:
                            errorMessage = 'Unknown video error occurred';
                    }
                }

                this.handleError(new Error(errorMessage));
            });

            // Handle successful load
            this.videoElement.on('loadedmetadata', () => {
                // eslint-disable-next-line no-console
                console.log('Video loaded successfully');
            });

            // Handle when video can start playing
            this.videoElement.on('canplay', () => {
                // eslint-disable-next-line no-console
                console.log('Video ready to play');
            });
        }

        /**
         * Schedule automatic URL refresh before expiry.
         */
        scheduleUrlRefresh() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
            }

            if (!this.urlExpiry) {
                return;
            }

            // Calculate when to refresh (1 hour before expiry)
            const refreshTime = this.urlExpiry - Date.now() - (URL_REFRESH_BUFFER * 1000);

            if (refreshTime > 0) {
                this.refreshTimer = setTimeout(() => {
                    this.refreshUrl();
                }, refreshTime);
            }
        }

        /**
         * Refresh the playback URL and reload the player with retry logic.
         */
        async refreshUrl() {
            const maxRetries = 3;
            let attempt = 0;

            const attemptRefresh = async () => {
                attempt++;
                try {
                    // Save current playback position
                    let currentTime = 0;
                    let wasPaused = true;

                    if (this.player) {
                        currentTime = this.player.currentTime();
                        wasPaused = this.player.paused();
                    }

                    // Get new signed URL
                    await this.getSignedUrl();

                    // Update video source
                    if (this.player && this.videoElement) {
                        // Update the source element
                        this.videoElement.find('source').attr('src', this.signedUrl);

                        // Reload the video
                        this.player.load();

                        // Restore playback position when metadata is loaded
                        this.videoElement.one('loadedmetadata', () => {
                            this.player.currentTime = currentTime;

                            if (!wasPaused) {
                                this.player.play();
                            }
                        });
                    }

                    // Schedule next refresh
                    this.scheduleUrlRefresh();

                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error(`URL refresh attempt ${attempt} failed:`, error);

                    const shouldRetry = this.shouldRetryUrlRefresh(error, attempt, maxRetries);

                    if (shouldRetry.should_retry) {
                        // eslint-disable-next-line no-console
                        console.log(`Retrying URL refresh in ${shouldRetry.delay_ms}ms...`);

                        setTimeout(() => {
                            attemptRefresh();
                        }, shouldRetry.delay_ms);
                    } else {
                        const errorMessage = error.canRetry === false
                            ? error.message
                            : 'Video session expired. Please refresh the page.';
                        this.handleError(new Error(errorMessage));
                    }
                }
            };

            await attemptRefresh();
        }

        /**
         * Determine if URL refresh should be retried.
         *
         * @param {Error} error The error that occurred
         * @param {number} attempt Current attempt number
         * @param {number} maxAttempts Maximum attempts allowed
         * @return {Object} Retry decision object
         */
        shouldRetryUrlRefresh(error, attempt, maxAttempts) {
            const result = {
                should_retry: false,
                delay_ms: 0
            };

            if (attempt >= maxAttempts) {
                return result;
            }

            if (error.errorType === 'permission_error') {
                return result;
            }

            const transientErrors = ['network_error', 'server_error', 'rate_limit', 'auth_error'];
            if (transientErrors.includes(error.errorType)) {
                result.should_retry = true;
                result.delay_ms = Math.pow(2, attempt - 1) * 1000;
            }

            return result;
        }

        /**
         * Show loading indicator.
         */
        showLoading() {
            this.container.empty();

            const loadingDiv = $('<div>')
                .addClass('s3video-loading text-center p-5')
                .append(
                    $('<div>')
                        .addClass('spinner-border text-primary')
                        .attr('role', 'status')
                        .append(
                            $('<span>').addClass('sr-only').text('Loading...')
                        )
                )
                .append(
                    $('<p>')
                        .addClass('mt-3')
                        .text('Loading video...')
                );

            this.container.append(loadingDiv);
        }

        /**
         * Handle playback errors with comprehensive error analysis and recovery options.
         *
         * @param {Error} error The error object
         */
        handleError(error) {
            // eslint-disable-next-line no-console
            console.error('Playback error:', error);

            this.container.empty();

            let errorMessage = error.message || 'An error occurred while loading the video';
            let guidance = error.guidance || 'Refresh the page and try again.';
            let canRetry = error.canRetry !== false;

            const errorContainer = $('<div>')
                .addClass('alert alert-danger s3video-playback-error');

            const errorHeader = $('<div>')
                .addClass('d-flex align-items-center mb-2')
                .append(
                    $('<i>').addClass('fa fa-exclamation-triangle text-danger me-2'),
                    $('<strong>').text('Video Playback Error')
                );

            const errorMessageDiv = $('<p>')
                .addClass('mb-2')
                .text(errorMessage);

            let guidanceDiv = null;
            if (guidance) {
                guidanceDiv = $('<div>').addClass('mt-3');

                const guidanceTitle = $('<p>')
                    .addClass('mb-2 font-weight-bold')
                    .text('What to do:');

                const guidanceText = $('<p>')
                    .addClass('mb-2')
                    .text(guidance);

                guidanceDiv.append(guidanceTitle, guidanceText);
            }

            const buttonContainer = $('<div>').addClass('mt-3');

            if (canRetry) {
                const retryBtn = $('<button>')
                    .addClass('btn btn-primary me-2')
                    .text('Try Again')
                    .on('click', () => {
                        this.init();
                    });
                buttonContainer.append(retryBtn);
            }

            const refreshBtn = $('<button>')
                .addClass('btn btn-secondary')
                .text('Refresh Page')
                .on('click', () => {
                    window.location.reload();
                });
            buttonContainer.append(refreshBtn);

            errorContainer.append(errorHeader, errorMessageDiv);
            if (guidanceDiv) {
                errorContainer.append(guidanceDiv);
            }
            errorContainer.append(buttonContainer);

            this.container.append(errorContainer);
        }

        /**
         * Destroy the player and clean up resources.
         */
        destroy() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
                this.refreshTimer = null;
            }

            if (this.player) {
                // Pause and clean up native video element
                this.player.pause();
                this.player.src = '';
                this.player.load();
                this.player = null;
            }

            if (this.videoElement) {
                this.videoElement.off(); // Remove all event listeners
                this.videoElement.remove();
                this.videoElement = null;
            }

            this.signedUrl = null;
            this.urlExpiry = null;
        }
    }

    return {
        /**
         * Initialize a player instance.
         *
         * @param {string} s3Key The S3 key for the video
         * @param {number} submissionId The submission ID
         * @param {string} containerId The container element ID
         * @return {S3VideoPlayer} The player instance
         */
        init: function (s3Key, submissionId, containerId) {
            const player = new S3VideoPlayer(s3Key, submissionId, containerId);
            player.init();
            return player;
        }
    };
});
