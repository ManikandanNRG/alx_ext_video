// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Cloudflare Stream player module.
 *
 * @module     assignsubmission_cloudflarestream/player
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/str'], function ($, Ajax, Str) {

    /**
     * Token expiry buffer in seconds (refresh 5 minutes before expiry).
     * @const {number}
     */
    const TOKEN_REFRESH_BUFFER = 300;

    /**
     * CloudflarePlayer class for handling video playback.
     */
    class CloudflarePlayer {
        /**
         * Constructor.
         *
         * @param {string} videoUid The Cloudflare video UID
         * @param {number} submissionId The submission ID
         * @param {string} containerId The container element ID
         */
        constructor(videoUid, submissionId, containerId) {
            this.videoUid = videoUid;
            this.submissionId = submissionId;
            this.containerId = containerId;
            this.container = null;
            this.playerIframe = null;
            this.token = null;
            this.tokenExpiry = null;
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

                // Get signed token
                await this.getSignedToken();

                // Embed player
                this.embedPlayer();

                // Set up token refresh
                this.scheduleTokenRefresh();

            } catch (error) {
                this.handleError(error);
            }
        }

        /**
         * Get a signed playback token from Moodle backend.
         *
         * @return {Promise<void>}
         */
        async getSignedToken() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_playback_token.php',
                    method: 'GET',
                    data: {
                        submission_id: this.submissionId,
                        video_uid: this.videoUid,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        this.token = data.token;
                        this.tokenExpiry = Date.now() + (data.expiry_seconds * 1000);
                        resolve();
                    } else {
                        // Create enhanced error object with server-provided details
                        const error = new Error(data.user_message || data.message || 'Failed to get playback token');
                        error.errorType = data.error_type || 'unknown';
                        error.suggestions = data.suggestions || [];
                        error.canRetry = data.can_retry !== false;
                        error.serverResponse = data;
                        reject(error);
                    }
                }).fail((jqXHR, textStatus) => {
                    // Handle network or HTTP errors with enhanced error information
                    let errorMessage = 'Network error occurred while loading video';
                    let errorType = 'network_error';
                    let suggestions = [
                        'Check your internet connection',
                        'Refresh the page and try again'
                    ];
                    let canRetry = true;

                    // Try to parse error response
                    if (jqXHR.responseJSON && jqXHR.responseJSON.user_message) {
                        errorMessage = jqXHR.responseJSON.user_message;
                        errorType = jqXHR.responseJSON.error_type || errorType;
                        suggestions = jqXHR.responseJSON.suggestions || suggestions;
                        canRetry = jqXHR.responseJSON.can_retry !== false;
                    } else if (jqXHR.status === 403) {
                        errorMessage = 'You do not have permission to view this video';
                        errorType = 'permission_error';
                        suggestions = ['Contact your instructor if you believe this is an error'];
                        canRetry = false;
                    } else if (jqXHR.status === 429) {
                        errorMessage = 'Too many requests. Please wait a moment and try again.';
                        errorType = 'rate_limit';
                        suggestions = ['Wait a moment and try again'];
                        canRetry = true;
                    } else if (jqXHR.status >= 500) {
                        errorMessage = 'Server error occurred. Please try again later.';
                        errorType = 'server_error';
                        suggestions = [
                            'Wait a few minutes and try again',
                            'Contact support if the problem persists'
                        ];
                    }

                    const error = new Error(errorMessage);
                    error.errorType = errorType;
                    error.suggestions = suggestions;
                    error.canRetry = canRetry;
                    error.httpStatus = jqXHR.status;
                    reject(error);
                });
            });
        }

        /**
         * Embed Cloudflare Stream player using simple IFRAME (for PUBLIC videos).
         * No token needed - videos are public with domain restrictions.
         */
        embedPlayer() {
            this.container.empty();

            // Create simple IFRAME with video UID only (no token needed for public videos)
            const iframeUrl = `https://iframe.videodelivery.net/${this.videoUid}`;

            const iframe = $('<iframe>')
                .attr('src', iframeUrl)
                .attr('style', 'border: none; width: 100%; aspect-ratio: 16/9; min-height: 400px;')
                .attr('allow', 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture')
                .attr('allowfullscreen', true)
                .attr('loading', 'lazy');

            this.container.append(iframe);
            this.playerIframe = iframe[0];

            // Log for debugging
            // eslint-disable-next-line no-console
            console.log('Cloudflare player embedded (public video)');
            // eslint-disable-next-line no-console
            console.log('Video UID:', this.videoUid);
        }

        /**
         * Schedule automatic token refresh before expiry.
         */
        scheduleTokenRefresh() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
            }

            if (!this.tokenExpiry) {
                return;
            }

            // Calculate when to refresh (5 minutes before expiry)
            const refreshTime = this.tokenExpiry - Date.now() - (TOKEN_REFRESH_BUFFER * 1000);

            if (refreshTime > 0) {
                this.refreshTimer = setTimeout(() => {
                    this.refreshToken();
                }, refreshTime);
            }
        }

        /**
         * Refresh the playback token and reload the player with retry logic.
         * For IFRAME method, we need to update the iframe src with new token.
         */
        async refreshToken() {
            const maxRetries = 3;
            let attempt = 0;

            const attemptRefresh = async () => {
                attempt++;
                try {
                    // Get new token
                    await this.getSignedToken();

                    // Update iframe src with new token (seamless refresh)
                    if (this.playerIframe) {
                        const newUrl = `https://iframe.videodelivery.net/${this.videoUid}?token=${this.token}`;
                        $(this.playerIframe).attr('src', newUrl);

                        // eslint-disable-next-line no-console
                        console.log('Token refreshed successfully');
                    }

                    // Schedule next refresh
                    this.scheduleTokenRefresh();

                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error(`Token refresh attempt ${attempt} failed:`, error);

                    // Check if we should retry
                    const shouldRetry = this.shouldRetryTokenRefresh(error, attempt, maxRetries);

                    if (shouldRetry.should_retry) {
                        // eslint-disable-next-line no-console
                        console.log(`Retrying token refresh in ${shouldRetry.delay_ms}ms...`);

                        setTimeout(() => {
                            attemptRefresh();
                        }, shouldRetry.delay_ms);
                    } else {
                        // Final failure - show error to user
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
         * Determine if token refresh should be retried.
         *
         * @param {Error} error The error that occurred
         * @param {number} attempt Current attempt number
         * @param {number} maxAttempts Maximum attempts allowed
         * @return {Object} Retry decision object
         */
        shouldRetryTokenRefresh(error, attempt, maxAttempts) {
            const result = {
                should_retry: false,
                delay_ms: 0
            };

            // Don't retry if we've exceeded max attempts
            if (attempt >= maxAttempts) {
                return result;
            }

            // Don't retry permission errors
            if (error.errorType === 'permission_error') {
                return result;
            }

            // Retry transient errors
            const transientErrors = ['network_error', 'server_error', 'rate_limit', 'auth_error'];
            if (transientErrors.includes(error.errorType)) {
                result.should_retry = true;
                // Exponential backoff: 1s, 2s, 4s
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
                .addClass('cloudflarestream-loading text-center p-5')
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

            // Get error message and details
            let errorMessage = error.message || 'An error occurred while loading the video';
            let suggestions = error.suggestions || ['Refresh the page and try again'];
            let canRetry = error.canRetry !== false;

            // Create comprehensive error display
            const errorContainer = $('<div>')
                .addClass('alert alert-danger cloudflarestream-playback-error');

            // Add error icon and title
            const errorHeader = $('<div>')
                .addClass('d-flex align-items-center mb-2')
                .append(
                    $('<i>').addClass('fa fa-exclamation-triangle text-danger me-2'),
                    $('<strong>').text('Video Playback Error')
                );

            // Add main error message
            const errorMessageDiv = $('<p>')
                .addClass('mb-2')
                .text(errorMessage);

            // Add suggestions if available
            let suggestionsDiv = null;
            if (suggestions && suggestions.length > 0) {
                suggestionsDiv = $('<div>').addClass('mt-3');

                const suggestionsTitle = $('<p>')
                    .addClass('mb-2 font-weight-bold')
                    .text('You can try the following:');

                const suggestionsList = $('<ul>').addClass('mb-2');

                suggestions.forEach(suggestion => {
                    suggestionsList.append($('<li>').text(suggestion));
                });

                suggestionsDiv.append(suggestionsTitle, suggestionsList);
            }

            // Add action buttons
            const buttonContainer = $('<div>').addClass('mt-3');

            // Retry button (if error is retryable)
            if (canRetry) {
                const retryBtn = $('<button>')
                    .addClass('btn btn-primary me-2')
                    .text('Try Again')
                    .on('click', () => {
                        this.init();
                    });
                buttonContainer.append(retryBtn);
            }

            // Refresh page button
            const refreshBtn = $('<button>')
                .addClass('btn btn-secondary me-2')
                .text('Refresh Page')
                .on('click', () => {
                    window.location.reload();
                });
            buttonContainer.append(refreshBtn);

            // Help button for complex errors
            if (error.errorType && error.errorType !== 'permission_error') {
                const helpBtn = $('<button>')
                    .addClass('btn btn-outline-info')
                    .text('Troubleshooting Help')
                    .on('click', () => {
                        this.showPlaybackTroubleshootingHelp();
                    });
                buttonContainer.append(helpBtn);
            }

            // Assemble error display
            errorContainer.append(errorHeader, errorMessageDiv);
            if (suggestionsDiv) {
                errorContainer.append(suggestionsDiv);
            }
            errorContainer.append(buttonContainer);

            this.container.append(errorContainer);
        }

        /**
         * Show detailed troubleshooting help for playback issues.
         */
        showPlaybackTroubleshootingHelp() {
            const helpModal = $('<div>')
                .addClass('modal fade')
                .attr('tabindex', '-1')
                .attr('role', 'dialog');

            const modalDialog = $('<div>')
                .addClass('modal-dialog modal-lg')
                .attr('role', 'document');

            const modalContent = $('<div>').addClass('modal-content');

            const modalHeader = $('<div>')
                .addClass('modal-header')
                .append(
                    $('<h5>').addClass('modal-title').text('Video Playback Troubleshooting'),
                    $('<button>')
                        .addClass('btn-close')
                        .attr('type', 'button')
                        .attr('data-bs-dismiss', 'modal')
                        .attr('aria-label', 'Close')
                );

            const modalBody = $('<div>')
                .addClass('modal-body')
                .append(
                    $('<h6>').text('Common Playback Issues and Solutions:'),
                    $('<ol>')
                        .append(
                            $('<li>').html('<strong>Video Not Loading:</strong> Refresh the page and ensure you have a stable internet connection'),
                            $('<li>').html('<strong>Permission Denied:</strong> Make sure you are logged in and have access to this assignment'),
                            $('<li>').html('<strong>Slow Loading:</strong> Check your internet speed and try again during off-peak hours'),
                            $('<li>').html('<strong>Browser Issues:</strong> Try using Chrome, Firefox, Safari, or Edge for best compatibility'),
                            $('<li>').html('<strong>Firewall/Proxy:</strong> Check if your network blocks video streaming')
                        ),
                    $('<h6>').addClass('mt-4').text('If problems persist:'),
                    $('<ul>')
                        .append(
                            $('<li>').text('Contact your instructor or course administrator'),
                            $('<li>').text('Try accessing from a different device or network'),
                            $('<li>').text('Clear your browser cache and cookies')
                        )
                );

            const modalFooter = $('<div>')
                .addClass('modal-footer')
                .append(
                    $('<button>')
                        .addClass('btn btn-secondary')
                        .attr('type', 'button')
                        .attr('data-bs-dismiss', 'modal')
                        .text('Close')
                );

            modalContent.append(modalHeader, modalBody, modalFooter);
            modalDialog.append(modalContent);
            helpModal.append(modalDialog);

            // Add to page and show
            $('body').append(helpModal);
            helpModal.modal('show');

            // Remove from DOM when hidden
            helpModal.on('hidden.bs.modal', function () {
                helpModal.remove();
            });
        }

        /**
         * Destroy the player and clean up resources.
         */
        destroy() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
                this.refreshTimer = null;
            }

            if (this.playerIframe) {
                this.playerIframe.remove();
                this.playerIframe = null;
            }

            this.token = null;
            this.tokenExpiry = null;
        }
    }

    return {
        /**
         * Initialize a player instance.
         *
         * @param {string} videoUid The Cloudflare video UID
         * @param {number} submissionId The submission ID
         * @param {string} containerId The container element ID
         * @return {CloudflarePlayer} The player instance
         */
        init: function (videoUid, submissionId, containerId) {
            const player = new CloudflarePlayer(videoUid, submissionId, containerId);
            player.init();
            return player;
        }
    };
});
