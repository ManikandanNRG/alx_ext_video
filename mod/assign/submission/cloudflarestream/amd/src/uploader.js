// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Cloudflare Stream uploader module.
 *
 * @module     assignsubmission_cloudflarestream/uploader
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function ($, Ajax, Notification, Str) {

    /**
     * Maximum file size (5GB in bytes).
     * @const {number}
     */
    const MAX_FILE_SIZE = 5368709120;

    /**
     * Allowed video MIME types.
     * @const {Array<string>}
     */
    const ALLOWED_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm',
        'video/mpeg',
        'video/ogg',
        'video/3gpp',
        'video/x-flv'
    ];

    /**
     * CloudflareUploader class for handling video uploads.
     */
    class CloudflareUploader {
        /**
         * Constructor.
         *
         * @param {number} assignmentId The assignment ID
         * @param {number} submissionId The submission ID (optional)
         * @param {number} maxFileSize Maximum file size in bytes
         */
        constructor(assignmentId, submissionId, maxFileSize) {
            this.assignmentId = assignmentId;
            this.submissionId = submissionId || 0;
            this.maxFileSize = maxFileSize || MAX_FILE_SIZE;
            this.uploadInProgress = false;
            this.currentUpload = null;
            this.retryCount = 0;
            this.maxRetries = 3;

            // Initialize tus client (will be loaded dynamically)
            this.tus = null;
        }

        /**
         * Initialize the uploader and attach event handlers.
         *
         * @param {string} containerSelector jQuery selector for the upload container
         */
        init(containerSelector) {
            this.container = $(containerSelector);

            if (this.container.length === 0) {
                return;
            }

            // Get elements
            this.dropzone = this.container.find('.cloudflarestream-dropzone');
            this.fileInput = this.container.find('.cloudflarestream-file-input');
            this.selectBtn = this.container.find('.cloudflarestream-select-btn');
            this.progressContainer = this.container.find('.cloudflarestream-progress-container');
            this.progressBar = this.container.find('.cloudflarestream-progress-bar');
            this.progressPercentage = this.container.find('.cloudflarestream-progress-percentage');
            this.statusMessage = this.container.find('.cloudflarestream-status-message');

            // Attach event handlers
            this.attachEventHandlers();

            // Load tus-js-client library
            this.loadTusLibrary();
        }

        /**
         * Attach event handlers for file selection and drag-drop.
         */
        attachEventHandlers() {
            // Select button click
            this.selectBtn.on('click', (e) => {
                e.preventDefault();
                this.fileInput.click();
            });

            // File input change
            this.fileInput.on('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelection(file);
                }
            });

            // Drag and drop events
            this.dropzone.on('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.dropzone.addClass('dragover');
            });

            this.dropzone.on('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.dropzone.removeClass('dragover');
            });

            this.dropzone.on('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.dropzone.removeClass('dragover');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileSelection(files[0]);
                }
            });
        }

        /**
         * Load the tus-js-client library dynamically.
         * We need to temporarily disable AMD to prevent tus from registering as an AMD module.
         */
        loadTusLibrary() {
            // Check if tus is already loaded
            if (window.tus) {
                this.tus = window.tus;
                return;
            }

            // Temporarily disable AMD define to prevent tus from registering as AMD module
            const originalDefine = window.define;
            window.define = undefined;

            // Load tus from CDN
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/tus-js-client@3.1.1/dist/tus.min.js';
            script.onload = () => {
                // Restore AMD define
                window.define = originalDefine;
                this.tus = window.tus;
            };
            script.onerror = () => {
                // Restore AMD define even on error
                window.define = originalDefine;
                this.showError('Failed to load upload library. Please refresh the page.');
            };
            document.head.appendChild(script);
        }

        /**
         * Handle file selection.
         *
         * @param {File} file The selected file
         */
        handleFileSelection(file) {
            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError(validation.error);
                return;
            }

            // Start upload
            this.startUpload(file);
        }

        /**
         * Validate the selected file.
         *
         * @param {File} file The file to validate
         * @return {Object} Validation result with 'valid' and 'error' properties
         */
        validateFile(file) {
            // Check if file exists
            if (!file) {
                return {
                    valid: false,
                    error: 'No file selected'
                };
            }

            // Check file size (must be positive and within limits)
            if (file.size <= 0) {
                return {
                    valid: false,
                    error: 'Invalid file size'
                };
            }

            if (file.size > this.maxFileSize) {
                return {
                    valid: false,
                    error: 'File size exceeds maximum allowed size of ' + this.formatFileSize(this.maxFileSize)
                };
            }

            // Check MIME type
            if (!file.type) {
                console.log('DEBUG: File has no MIME type');
                return {
                    valid: false,
                    error: 'Unable to determine file type'
                };
            }

            console.log('DEBUG: File MIME type:', file.type);
            console.log('DEBUG: ALLOWED_MIME_TYPES:', ALLOWED_MIME_TYPES);
            console.log('DEBUG: file.type.startsWith("video/"):', file.type.startsWith('video/'));

            if (!ALLOWED_MIME_TYPES.includes(file.type) && !file.type.startsWith('video/')) {
                console.log('DEBUG: MIME type validation FAILED');
                return {
                    valid: false,
                    error: 'Unsupported file type: ' + file.type + '. Please upload a video file.'
                };
            }

            // Check file extension
            const extension = this.getFileExtension(file.name);
            const allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mpeg', 'mpg', 'ogv', '3gp', 'flv'];

            console.log('DEBUG: File extension:', extension);
            console.log('DEBUG: Extension in allowed list:', allowedExtensions.includes(extension.toLowerCase()));

            if (!allowedExtensions.includes(extension.toLowerCase())) {
                console.log('DEBUG: Extension validation FAILED');
                return {
                    valid: false,
                    error: 'Unsupported file extension: .' + extension + '. Allowed extensions: ' + allowedExtensions.join(', ')
                };
            }

            // Check filename length and characters
            if (file.name.length > 255) {
                return {
                    valid: false,
                    error: 'Filename is too long (maximum 255 characters)'
                };
            }

            // Check for potentially dangerous characters in filename
            const dangerousChars = /[<>:"|?*\x00-\x1f]/;
            if (dangerousChars.test(file.name)) {
                return {
                    valid: false,
                    error: 'Filename contains invalid characters'
                };
            }

            return { valid: true };
        }

        /**
         * Get file extension from filename.
         *
         * @param {string} filename The filename
         * @return {string} The file extension (without dot)
         */
        getFileExtension(filename) {
            const lastDot = filename.lastIndexOf('.');
            if (lastDot === -1) {
                return '';
            }
            return filename.substring(lastDot + 1);
        }

        /**
         * Start the upload process.
         *
         * @param {File} file The file to upload
         */
        async startUpload(file) {
            if (this.uploadInProgress) {
                this.showError('An upload is already in progress.');
                return;
            }

            try {
                this.uploadInProgress = true;
                this.showProgress();
                this.updateProgress(0);

                // Request upload URL from Moodle
                const uploadData = await this.requestUploadUrl();

                // DEBUG: Log the ENTIRE response
                console.log('=== UPLOAD DATA RECEIVED ===');
                console.log('Full uploadData object:', JSON.stringify(uploadData, null, 2));
                console.log('uploadData.uid:', uploadData.uid);
                console.log('uploadData.uploadURL:', uploadData.uploadURL);
                console.log('uploadData.submissionid:', uploadData.submissionid);
                console.log('===========================');

                // Validate that we have the required data
                if (!uploadData.uid || !uploadData.uploadURL) {
                    console.error('VALIDATION FAILED - uploadData:', uploadData);
                    throw new Error('Invalid response from server: missing upload URL or video ID');
                }

                // Upload file to Cloudflare using tus with retry logic
                await this.uploadFileWithRetry(file, uploadData.uploadURL, uploadData.uid);

                // Debug: Log the uid before confirming
                console.log('=== BEFORE CONFIRM UPLOAD ===');
                console.log('Confirming upload with uid:', uploadData.uid, 'submissionid:', uploadData.submissionid);
                console.log('typeof uid:', typeof uploadData.uid);
                console.log('uid length:', uploadData.uid ? uploadData.uid.length : 'null/undefined');
                console.log('============================');

                // Confirm upload completion with Moodle
                await this.confirmUpload(uploadData.uid, uploadData.submissionid);

                // Show success message
                this.showSuccess();

            } catch (error) {
                this.handleError(error);
            }
        }

        /**
         * Request a direct upload URL from Moodle backend.
         *
         * @return {Promise<Object>} Upload data with uploadURL and uid
         */
        async requestUploadUrl() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_upload_url.php',
                    method: 'POST',
                    data: {
                        assignmentid: this.assignmentId,
                        submissionid: this.submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: (data) => {
                        if (data.success) {
                            resolve(data);
                        } else {
                            // Create enhanced error object with server-provided details
                            const error = new Error(data.user_message || data.error || 'Failed to get upload URL');
                            error.errorType = data.error_type || 'unknown';
                            error.suggestions = data.suggestions || [];
                            error.canRetry = data.can_retry !== false;
                            error.retryAfter = data.retry_after;
                            error.serverResponse = data;
                            reject(error);
                        }
                    },
                    error: (xhr, status, error) => {
                        // Handle network or parsing errors
                        const enhancedError = new Error('Network error occurred while requesting upload URL');
                        enhancedError.errorType = 'network_error';
                        enhancedError.suggestions = [
                            'Check your internet connection',
                            'Refresh the page and try again'
                        ];
                        enhancedError.canRetry = true;
                        enhancedError.originalError = error;
                        reject(enhancedError);
                    }
                });
            });
        }

        /**
         * Upload file to Cloudflare using direct HTTP upload.
         * Cloudflare's direct upload URL expects a simple PUT request, not TUS protocol.
         *
         * @param {File} file The file to upload
         * @param {string} uploadURL The Cloudflare upload URL
         * @param {string} uid The video UID
         * @return {Promise<void>}
         */
        uploadFile(file, uploadURL, uid) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                // Cloudflare expects a POST request with form data
                xhr.open('POST', uploadURL, true);

                // Don't set Content-Type - let browser set it for FormData
                // xhr.setRequestHeader('Content-Type', ...) - removed

                // Track upload progress
                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percentage = Math.round((event.loaded / event.total) * 100);
                        this.updateProgress(percentage);
                    }
                };

                // Handle successful upload
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        reject(new Error(`Upload failed with status ${xhr.status}: ${xhr.responseText}`));
                    }
                };

                // Handle upload errors
                xhr.onerror = () => {
                    reject(new Error('Network error during upload'));
                };

                // Handle upload timeout
                xhr.ontimeout = () => {
                    reject(new Error('Upload timed out'));
                };

                // Set timeout (30 seconds)
                xhr.timeout = 30000;

                // Store reference for potential cancellation
                this.currentUpload = xhr;

                // Create FormData and append the file
                const formData = new FormData();
                formData.append('file', file);

                // Start the upload with FormData
                xhr.send(formData);
            });
        }

        /**
         * Perform the actual tus upload.
         *
         * @param {File} file The file to upload
         * @param {string} uploadURL The Cloudflare upload URL
         * @param {string} uid The video UID
         * @param {Function} resolve Promise resolve function
         * @param {Function} reject Promise reject function
         */
        performTusUpload(file, uploadURL, uid, resolve, reject) {
            const upload = new this.tus.Upload(file, {
                // Cloudflare provides pre-created URL - tell TUS to use it directly
                uploadUrl: uploadURL,
                storeFingerprintForResuming: true, // Enable resume for reliability
                removeFingerprintOnSuccess: true,
                retryDelays: [0, 2000, 5000, 10000, 20000, 30000],
                chunkSize: 5 * 1024 * 1024, // 5MB chunks - more reliable for most connections
                uploadLengthDeferred: false,
                parallelUploads: 1,
                uploadDataDuringCreation: true,
                onError: (error) => {
                    console.error('Upload error:', error);
                    this.currentUpload = null;

                    // Better error messages for common issues
                    let userMessage = error.message || 'Upload failed';
                    if (error.message && error.message.includes('timeout')) {
                        userMessage = 'Upload timeout - this can happen with large files on slow connections. Please try again or use a smaller file.';
                    } else if (error.message && error.message.includes('network')) {
                        userMessage = 'Network error - please check your internet connection and try again.';
                    }

                    reject(new Error(userMessage));
                },
                onProgress: (bytesUploaded, bytesTotal) => {
                    const percentage = Math.round((bytesUploaded / bytesTotal) * 100);
                    this.updateProgress(percentage);

                    // Show detailed progress for large files
                    const uploadedMB = Math.round(bytesUploaded / 1024 / 1024);
                    const totalMB = Math.round(bytesTotal / 1024 / 1024);
                    console.log(`Upload progress: ${uploadedMB}MB / ${totalMB}MB (${percentage}%)`);
                },
                onSuccess: () => {
                    this.currentUpload = null;
                    resolve();
                }
            });

            this.currentUpload = upload;
            upload.start();
        }

        /**
         * Confirm upload completion with Moodle backend.
         *
         * @param {string} videoUid The Cloudflare video UID
         * @param {number} submissionId The submission ID
         * @return {Promise<Object>} Confirmation response
         */
        async confirmUpload(videoUid, submissionId) {
            return new Promise((resolve, reject) => {
                // DEBUG: Log what we're sending
                console.log('=== CONFIRM UPLOAD AJAX CALL ===');
                console.log('videoUid parameter:', videoUid);
                console.log('submissionId parameter:', submissionId);
                console.log('typeof videoUid:', typeof videoUid);
                console.log('videoUid length:', videoUid ? videoUid.length : 'null/undefined');
                console.log('Data being sent:', {
                    videouid: videoUid,
                    submissionid: submissionId,
                    sesskey: M.cfg.sesskey
                });
                console.log('================================');

                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/confirm_upload.php',
                    method: 'POST',
                    data: {
                        videouid: videoUid,
                        submissionid: submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        // Update hidden form fields
                        $('input[name="cloudflarestream_video_uid"]').val(videoUid);
                        $('input[name="cloudflarestream_status"]').val(data.status);
                        if (data.filesize) {
                            $('input[name="cloudflarestream_file_size"]').val(data.filesize);
                        }
                        if (data.duration) {
                            $('input[name="cloudflarestream_duration"]').val(data.duration);
                        }
                        resolve(data);
                    } else {
                        // Create enhanced error object with server-provided details
                        const error = new Error(data.user_message || data.error || 'Failed to confirm upload');
                        error.errorType = data.error_type || 'unknown';
                        error.suggestions = data.suggestions || [];
                        error.canRetry = data.can_retry !== false;
                        error.serverResponse = data;
                        reject(error);
                    }
                }).fail((jqXHR, textStatus, errorThrown) => {
                    // Handle network or HTTP errors
                    let errorMessage = 'Network error occurred while confirming upload';
                    let errorType = 'network_error';
                    let suggestions = [
                        'Check your internet connection',
                        'Refresh the page and try again'
                    ];

                    // Try to parse error response
                    if (jqXHR.responseJSON && jqXHR.responseJSON.user_message) {
                        errorMessage = jqXHR.responseJSON.user_message;
                        errorType = jqXHR.responseJSON.error_type || errorType;
                        suggestions = jqXHR.responseJSON.suggestions || suggestions;
                    } else if (jqXHR.status === 403) {
                        errorMessage = 'Permission denied. Please refresh the page and try again.';
                        errorType = 'permission_error';
                        suggestions = ['Refresh the page and try again'];
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
                    error.canRetry = true;
                    error.httpStatus = jqXHR.status;
                    reject(error);
                });
            });
        }

        /**
         * Show the progress bar.
         */
        showProgress() {
            this.dropzone.hide();
            this.progressContainer.show();
            this.statusMessage.empty().removeClass('alert-danger alert-success');
        }

        /**
         * Update the progress bar.
         *
         * @param {number} percentage Progress percentage (0-100)
         */
        updateProgress(percentage) {
            this.progressBar.css('width', percentage + '%')
                .attr('aria-valuenow', percentage)
                .text(percentage + '%');
            this.progressPercentage.text(percentage + '%');
        }

        /**
         * Show success message.
         */
        showSuccess() {
            this.uploadInProgress = false;
            this.progressContainer.hide();

            Str.get_string('uploadsuccess', 'assignsubmission_cloudflarestream').then((message) => {
                this.statusMessage.html(message)
                    .addClass('alert alert-success')
                    .show();
                return null;
            }).catch(() => {
                this.statusMessage.html('Video uploaded successfully!')
                    .addClass('alert alert-success')
                    .show();
            });

            // Reset file input
            this.fileInput.val('');
        }

        /**
         * Show error message.
         *
         * @param {string|Error} error The error message or Error object
         */
        showError(error) {
            const errorMessage = error instanceof Error ? error.message : error;

            this.statusMessage.html(errorMessage)
                .addClass('alert alert-danger')
                .show();

            // Show retry button if upload was in progress
            if (this.uploadInProgress && this.retryCount < this.maxRetries) {
                const retryBtn = $('<button>')
                    .addClass('btn btn-secondary mt-2')
                    .text('Retry Upload')
                    .on('click', () => {
                        this.retryCount++;
                        this.retry();
                    });
                this.statusMessage.append(retryBtn);
            }
        }

        /**
         * Handle upload errors with comprehensive error analysis and recovery suggestions.
         *
         * @param {Error} error The error object
         */
        handleError(error) {
            this.uploadInProgress = false;
            this.progressContainer.hide();
            this.dropzone.show();

            // Log error to console for debugging
            // eslint-disable-next-line no-console
            console.error('Upload error:', error);

            // Analyze error and provide specific guidance
            const errorAnalysis = this.analyzeError(error);

            // Show comprehensive error message with recovery suggestions
            this.showComprehensiveError(errorAnalysis);
        }

        /**
         * Analyze error and determine appropriate user guidance.
         *
         * @param {Error} error The error object
         * @return {Object} Error analysis with message, type, and suggestions
         */
        analyzeError(error) {
            const analysis = {
                type: 'unknown',
                message: 'An unexpected error occurred during upload.',
                suggestions: ['retry_refresh_page', 'retry_check_connection'],
                canRetry: true,
                isTransient: true
            };

            if (!error) {
                return analysis;
            }

            // If error has enhanced properties from server response, use them
            if (error.errorType && error.suggestions) {
                analysis.type = error.errorType;
                analysis.message = error.message;
                analysis.suggestions = error.suggestions.map(suggestion => {
                    // Convert server suggestions to our suggestion codes
                    if (suggestion.includes('refresh')) return 'retry_refresh_page';
                    if (suggestion.includes('connection')) return 'retry_check_connection';
                    if (suggestion.includes('wait')) return 'retry_wait_and_retry';
                    if (suggestion.includes('smaller')) return 'retry_smaller_file';
                    if (suggestion.includes('different')) return 'retry_different_file';
                    if (suggestion.includes('browser')) return 'retry_different_browser';
                    if (suggestion.includes('support')) return 'retry_contact_support';
                    return 'retry_refresh_page'; // Default fallback
                });
                analysis.canRetry = error.canRetry !== false;

                // Determine if error is transient based on type
                analysis.isTransient = ['network_error', 'server_error', 'rate_limit', 'api_error'].includes(error.errorType);

                return analysis;
            }

            // Fallback to legacy error analysis for errors without enhanced properties
            const errorMessage = error.message || '';
            const errorLower = errorMessage.toLowerCase();

            // Network-related errors
            if (errorLower.includes('network') || errorLower.includes('connection') ||
                errorLower.includes('timeout') || errorLower.includes('fetch')) {
                analysis.type = 'network';
                analysis.message = 'Network connection error. Please check your internet connection and try again.';
                analysis.suggestions = ['retry_check_connection', 'retry_wait_and_retry', 'retry_refresh_page'];
                analysis.isTransient = true;
            }
            // File size errors
            else if (errorLower.includes('file size') || errorLower.includes('too large') ||
                errorLower.includes('exceeds')) {
                analysis.type = 'file_size';
                analysis.message = errorMessage; // Use the specific size message
                analysis.suggestions = ['retry_smaller_file'];
                analysis.canRetry = false;
                analysis.isTransient = false;
            }
            // File type/format errors
            else if (errorLower.includes('file type') || errorLower.includes('format') ||
                errorLower.includes('mime') || errorLower.includes('extension')) {
                analysis.type = 'file_format';
                analysis.message = 'Unsupported video format. Please use MP4, MOV, AVI, MKV, or WebM format.';
                analysis.suggestions = ['retry_different_file'];
                analysis.canRetry = false;
                analysis.isTransient = false;
            }
            // Authentication/permission errors
            else if (errorLower.includes('permission') || errorLower.includes('unauthorized') ||
                errorLower.includes('forbidden') || errorLower.includes('auth')) {
                analysis.type = 'permission';
                analysis.message = 'You do not have permission to upload videos. Please contact your instructor.';
                analysis.suggestions = ['retry_refresh_page', 'retry_contact_support'];
                analysis.canRetry = false;
                analysis.isTransient = false;
            }
            // Quota/storage errors
            else if (errorLower.includes('quota') || errorLower.includes('storage') ||
                errorLower.includes('limit exceeded')) {
                analysis.type = 'quota';
                analysis.message = 'Storage quota exceeded. Please contact your administrator or try again later.';
                analysis.suggestions = ['retry_wait_and_retry', 'retry_contact_support'];
                analysis.canRetry = true;
                analysis.isTransient = true;
            }
            // Server/service errors
            else if (errorLower.includes('server') || errorLower.includes('service') ||
                errorLower.includes('unavailable') || errorLower.includes('maintenance')) {
                analysis.type = 'server';
                analysis.message = 'The video service is temporarily unavailable. Please try again in a few minutes.';
                analysis.suggestions = ['retry_wait_and_retry', 'retry_refresh_page'];
                analysis.isTransient = true;
            }
            // Rate limiting errors
            else if (errorLower.includes('rate limit') || errorLower.includes('too many requests')) {
                analysis.type = 'rate_limit';
                analysis.message = 'Too many upload attempts. Please wait a moment before trying again.';
                analysis.suggestions = ['retry_wait_and_retry'];
                analysis.isTransient = true;
            }
            // Upload library errors
            else if (errorLower.includes('upload library') || errorLower.includes('tus')) {
                analysis.type = 'library';
                analysis.message = 'Upload system initialization failed. Please refresh the page and try again.';
                analysis.suggestions = ['retry_refresh_page', 'retry_different_browser'];
                analysis.isTransient = true;
            }
            // Generic API errors
            else if (errorMessage.length > 0) {
                analysis.message = errorMessage;
                analysis.suggestions = ['retry_refresh_page', 'retry_check_connection', 'retry_wait_and_retry'];
            }

            return analysis;
        }

        /**
         * Show comprehensive error message with recovery suggestions.
         *
         * @param {Object} errorAnalysis Error analysis object
         */
        showComprehensiveError(errorAnalysis) {
            // Create error container
            const errorContainer = $('<div>')
                .addClass('alert alert-danger cloudflarestream-error-detailed');

            // Add error icon and title
            const errorHeader = $('<div>')
                .addClass('d-flex align-items-center mb-2')
                .append(
                    $('<i>').addClass('fa fa-exclamation-triangle text-danger me-2'),
                    $('<strong>').text('Upload Failed')
                );

            // Add main error message
            const errorMessage = $('<p>')
                .addClass('mb-2')
                .text(errorAnalysis.message);

            // Add retry information if applicable
            let retryInfoDiv = null;
            if (this.retryCount > 0) {
                retryInfoDiv = $('<div>')
                    .addClass('mb-2 text-muted small')
                    .text(`Attempted ${this.retryCount} time${this.retryCount > 1 ? 's' : ''}`);
            }

            // Add suggestions if available
            let suggestionsDiv = null;
            if (errorAnalysis.suggestions && errorAnalysis.suggestions.length > 0) {
                suggestionsDiv = $('<div>').addClass('mt-3');

                const suggestionsTitle = $('<p>')
                    .addClass('mb-2 font-weight-bold')
                    .text('You can try the following:');

                const suggestionsList = $('<ul>').addClass('mb-2');

                errorAnalysis.suggestions.forEach(suggestion => {
                    const suggestionText = this.getSuggestionText(suggestion);
                    if (suggestionText) {
                        suggestionsList.append($('<li>').text(suggestionText));
                    }
                });

                suggestionsDiv.append(suggestionsTitle, suggestionsList);
            }

            // Add action buttons
            const buttonContainer = $('<div>').addClass('mt-3');

            // Manual retry button (always available for user-initiated retries)
            const manualRetryBtn = $('<button>')
                .addClass('btn btn-primary me-2')
                .text('Try Again')
                .on('click', () => {
                    this.performManualRetry();
                });
            buttonContainer.append(manualRetryBtn);

            // Smart retry button (if error is retryable and we haven't exceeded max retries)
            if (errorAnalysis.canRetry && this.retryCount < this.maxRetries) {
                const smartRetryBtn = $('<button>')
                    .addClass('btn btn-success me-2')
                    .text('Smart Retry')
                    .attr('title', 'Automatically retry with optimized settings')
                    .on('click', () => {
                        this.performSmartRetry(errorAnalysis);
                    });
                buttonContainer.append(smartRetryBtn);
            }

            // Refresh page button
            const refreshBtn = $('<button>')
                .addClass('btn btn-secondary me-2')
                .text('Refresh Page')
                .on('click', () => {
                    window.location.reload();
                });
            buttonContainer.append(refreshBtn);

            // Help/troubleshooting button
            const helpBtn = $('<button>')
                .addClass('btn btn-outline-info')
                .text('Troubleshooting Help')
                .on('click', () => {
                    this.showTroubleshootingHelp();
                });
            buttonContainer.append(helpBtn);

            // Assemble error display
            errorContainer.append(errorHeader, errorMessage);
            if (retryInfoDiv) {
                errorContainer.append(retryInfoDiv);
            }
            if (suggestionsDiv) {
                errorContainer.append(suggestionsDiv);
            }
            errorContainer.append(buttonContainer);

            // Show the error
            this.statusMessage.empty().append(errorContainer).show();
        }

        /**
         * Perform manual retry initiated by user.
         */
        performManualRetry() {
            this.clearError();
            this.retryCount = 0; // Reset retry count for manual retries

            // If we have a current upload that can be resumed
            if (this.currentUpload) {
                try {
                    this.showProgress();
                    this.updateProgress(0);
                    this.currentUpload.start();
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error('Failed to retry upload:', error);
                    this.handleError(new Error('Failed to retry upload. Please select your file again.'));
                }
            } else {
                // No current upload - user needs to select file again
                this.dropzone.show();
                this.progressContainer.hide();
                this.fileInput.click();
            }
        }

        /**
         * Perform smart retry with optimized settings based on error analysis.
         *
         * @param {Object} errorAnalysis Error analysis object
         */
        performSmartRetry(errorAnalysis) {
            this.clearError();
            this.retryCount++;

            // Adjust settings based on error type
            if (errorAnalysis.type === 'network_error') {
                // For network errors, use smaller chunks and longer timeouts
                this.optimizeForSlowConnection();
            } else if (errorAnalysis.type === 'server_error') {
                // For server errors, wait longer before retrying
                this.showRetryMessage(this.retryCount, this.maxRetries);
                setTimeout(() => {
                    this.retry();
                }, 5000);
                return;
            }

            // Immediate retry for other error types
            this.retry();
        }

        /**
         * Optimize upload settings for slow or unreliable connections.
         */
        optimizeForSlowConnection() {
            // These settings will be used in the next upload attempt
            this.optimizedSettings = {
                chunkSize: 1048576, // 1MB chunks instead of 5MB
                retryDelays: [0, 2000, 5000, 10000, 20000, 30000], // Longer delays
                parallelUploads: 1, // Ensure no parallel uploads
                timeout: 60000 // 60 second timeout
            };

            this.showConnectionWarning('optimized', {
                message: 'Upload optimized for slow connection'
            });
        }

        /**
         * Get human-readable text for suggestion codes.
         *
         * @param {string} suggestion Suggestion code
         * @return {string} Human-readable suggestion text
         */
        getSuggestionText(suggestion) {
            const suggestions = {
                'retry_refresh_page': 'Refresh the page and try again',
                'retry_check_connection': 'Check your internet connection',
                'retry_smaller_file': 'Try uploading a smaller video file',
                'retry_different_browser': 'Try using a different web browser',
                'retry_contact_support': 'Contact technical support if the problem continues',
                'retry_wait_and_retry': 'Wait a few minutes and try again',
                'retry_different_file': 'Try uploading a different video file'
            };
            return suggestions[suggestion] || '';
        }

        /**
         * Show detailed troubleshooting help.
         */
        showTroubleshootingHelp() {
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
                    $('<h5>').addClass('modal-title').text('Video Upload Troubleshooting'),
                    $('<button>')
                        .addClass('btn-close')
                        .attr('type', 'button')
                        .attr('data-bs-dismiss', 'modal')
                        .attr('aria-label', 'Close')
                );

            const modalBody = $('<div>')
                .addClass('modal-body')
                .append(
                    $('<h6>').text('Common Issues and Solutions:'),
                    $('<ol>')
                        .append(
                            $('<li>').html('<strong>File Format:</strong> Ensure your video is in MP4, MOV, AVI, MKV, or WebM format'),
                            $('<li>').html('<strong>File Size:</strong> Check that your file is under ' + this.formatFileSize(this.maxFileSize)),
                            $('<li>').html('<strong>Internet Connection:</strong> Verify you have a stable internet connection'),
                            $('<li>').html('<strong>Browser:</strong> Try using Chrome, Firefox, Safari, or Edge for best compatibility'),
                            $('<li>').html('<strong>JavaScript:</strong> Ensure JavaScript is enabled in your browser'),
                            $('<li>').html('<strong>Firewall/Proxy:</strong> Check if your network blocks file uploads')
                        ),
                    $('<h6>').addClass('mt-4').text('If problems persist:'),
                    $('<ul>')
                        .append(
                            $('<li>').text('Contact your instructor or course administrator'),
                            $('<li>').text('Try uploading from a different device or network'),
                            $('<li>').text('Check if the issue occurs with other video files')
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
         * Clear error messages and reset UI.
         */
        clearError() {
            this.statusMessage.empty().removeClass('alert-danger alert-success').hide();
        }

        /**
         * Retry the last upload with improved error handling.
         */
        retry() {
            this.clearError();

            // If we have a current upload that can be resumed
            if (this.currentUpload) {
                try {
                    this.showProgress();
                    this.updateProgress(0);
                    this.currentUpload.start();
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error('Failed to retry upload:', error);
                    this.handleError(new Error('Failed to retry upload. Please select your file again.'));
                }
            } else {
                // No current upload - user needs to select file again
                this.fileInput.click();
            }
        }

        /**
         * Enhanced upload with automatic retry for transient failures using exponential backoff.
         *
         * @param {File} file The file to upload
         * @param {string} uploadURL The Cloudflare upload URL
         * @param {string} uid The video UID
         * @return {Promise<void>}
         */
        uploadFileWithRetry(file, uploadURL, uid) {
            return new Promise((resolve, reject) => {
                let attemptCount = 0;
                const maxAttempts = 3;

                // Exponential backoff with jitter
                const calculateDelay = (attempt) => {
                    const baseDelay = 1000; // 1 second
                    const maxDelay = 30000; // 30 seconds
                    const backoffMultiplier = 2.0;
                    const jitterFactor = 0.1;

                    let delay = baseDelay * Math.pow(backoffMultiplier, attempt - 1);
                    delay = Math.min(delay, maxDelay);

                    // Add jitter to avoid thundering herd
                    const jitter = delay * jitterFactor * Math.random();
                    return Math.floor(delay + jitter);
                };

                const attemptUpload = async () => {
                    attemptCount++;

                    try {
                        // Use the new direct POST upload method
                        await this.uploadFile(file, uploadURL, uid);
                        resolve(); // Success
                    } catch (error) {
                        if (attemptCount < maxAttempts) {
                            // Retry with exponential backoff
                            const delay = calculateDelay(attemptCount);
                            console.log(`Upload attempt ${attemptCount} failed, retrying in ${delay}ms...`);
                            setTimeout(attemptUpload, delay);
                        } else {
                            // Final failure
                            reject(error);
                        }
                    }
                };

                attemptUpload();
            });
        }

        /**
         * Perform tus upload with enhanced retry logic and exponential backoff.
         *
         * @param {File} file The file to upload
         * @param {string} uploadURL The Cloudflare upload URL
         * @param {string} uid The video UID
         * @param {number} attemptCount Current attempt number
         * @param {number} maxAttempts Maximum attempts allowed
         * @param {Function} calculateDelay Function to calculate retry delay
         * @param {Function} resolve Promise resolve function
         * @param {Function} reject Promise reject function
         * @param {Function} attemptUpload Function to retry the upload
         */
        performTusUploadWithRetry(file, uploadURL, uid, attemptCount, maxAttempts, calculateDelay, resolve, reject, attemptUpload) {
            // Use optimized settings if available
            const settings = this.optimizedSettings || {};

            // Cloudflare Stream provides a pre-created TUS upload URL
            // We need to configure TUS to use it directly without trying to create a new upload
            const upload = new this.tus.Upload(file, {
                // CRITICAL: Set uploadUrl to the Cloudflare URL - this tells TUS the upload already exists
                uploadUrl: uploadURL,
                // Disable fingerprinting and URL storage to prevent resume attempts
                storeFingerprintForResuming: false,
                removeFingerprintOnSuccess: true,
                retryDelays: settings.retryDelays || [0, 1000, 3000, 5000, 10000, 20000],
                chunkSize: settings.chunkSize || 5242880, // 5MB chunks
                uploadTimeout: settings.timeout || 30000,
                onError: (error) => {
                    this.currentUpload = null;

                    // Enhanced error analysis
                    const errorAnalysis = this.analyzeUploadError(error);

                    if (errorAnalysis.isTransient && attemptCount < maxAttempts) {
                        const delay = calculateDelay(attemptCount);

                        // eslint-disable-next-line no-console
                        console.log(`Upload attempt ${attemptCount} failed (${errorAnalysis.type}), retrying in ${delay}ms...`);

                        // Show enhanced retry message to user
                        this.showEnhancedRetryMessage(attemptCount, maxAttempts, delay, errorAnalysis);

                        // Retry after calculated delay
                        setTimeout(() => {
                            attemptUpload();
                        }, delay);
                    } else {
                        // Final failure or non-transient error
                        const enhancedError = new Error(error.message);
                        enhancedError.errorType = errorAnalysis.type;
                        enhancedError.suggestions = errorAnalysis.suggestions;
                        enhancedError.canRetry = errorAnalysis.canRetry;
                        enhancedError.isTransient = errorAnalysis.isTransient;
                        enhancedError.originalError = error;
                        reject(enhancedError);
                    }
                },
                onProgress: (bytesUploaded, bytesTotal) => {
                    const percentage = Math.round((bytesUploaded / bytesTotal) * 100);
                    this.updateProgress(percentage);

                    // Enhanced connection monitoring
                    this.monitorConnectionQuality(bytesUploaded, bytesTotal);
                },
                onSuccess: () => {
                    this.currentUpload = null;
                    // Clear any connection warnings
                    this.clearConnectionWarnings();
                    resolve();
                },
                onChunkComplete: (chunkSize, bytesAccepted, bytesTotal) => {
                    // Log chunk completion for debugging
                    // eslint-disable-next-line no-console
                    console.debug(`Chunk completed: ${bytesAccepted}/${bytesTotal} bytes`);
                }
            });

            this.currentUpload = upload;
            upload.start();
        }

        /**
         * Check if an error is transient and worth retrying.
         *
         * @param {Error} error The error to check
         * @return {boolean} True if error is transient
         */
        isTransientError(error) {
            if (!error || !error.message) {
                return false;
            }

            const errorMessage = error.message.toLowerCase();
            const transientPatterns = [
                'network',
                'connection',
                'timeout',
                'temporary',
                'server error',
                '5xx',
                'service unavailable',
                'too many requests',
                'rate limit'
            ];

            return transientPatterns.some(pattern => errorMessage.includes(pattern));
        }

        /**
         * Analyze upload error for enhanced retry logic.
         *
         * @param {Error} error The error to analyze
         * @return {Object} Error analysis object
         */
        analyzeUploadError(error) {
            const analysis = {
                type: 'unknown',
                isTransient: false,
                canRetry: false,
                suggestions: []
            };

            if (!error || !error.message) {
                return analysis;
            }

            const errorMessage = error.message.toLowerCase();

            // Network/connection errors - highly transient
            if (errorMessage.includes('network') || errorMessage.includes('connection') ||
                errorMessage.includes('timeout') || errorMessage.includes('fetch')) {
                analysis.type = 'network_error';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Check your internet connection', 'Try again in a moment'];
            }
            // Server errors - transient
            else if (errorMessage.includes('server') || errorMessage.includes('5xx') ||
                errorMessage.includes('service unavailable')) {
                analysis.type = 'server_error';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Server is temporarily unavailable', 'Try again in a few minutes'];
            }
            // Rate limiting - transient
            else if (errorMessage.includes('rate limit') || errorMessage.includes('too many requests') ||
                errorMessage.includes('429')) {
                analysis.type = 'rate_limit';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Too many requests', 'Wait a moment before trying again'];
            }
            // Quota/storage errors - potentially transient
            else if (errorMessage.includes('quota') || errorMessage.includes('storage') ||
                errorMessage.includes('limit exceeded')) {
                analysis.type = 'quota_error';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Storage quota may be exceeded', 'Try again later or contact support'];
            }
            // Authentication errors - potentially transient (token expiry)
            else if (errorMessage.includes('auth') || errorMessage.includes('unauthorized') ||
                errorMessage.includes('forbidden')) {
                analysis.type = 'auth_error';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Authentication issue', 'Refresh the page and try again'];
            }
            // File/format errors - not transient
            else if (errorMessage.includes('file') || errorMessage.includes('format') ||
                errorMessage.includes('size') || errorMessage.includes('type')) {
                analysis.type = 'file_error';
                analysis.isTransient = false;
                analysis.canRetry = false;
                analysis.suggestions = ['File format or size issue', 'Check your video file'];
            }
            // Generic errors - assume transient for safety
            else {
                analysis.type = 'generic_error';
                analysis.isTransient = true;
                analysis.canRetry = true;
                analysis.suggestions = ['Temporary upload issue', 'Try again'];
            }

            return analysis;
        }

        /**
         * Show retry message to user.
         *
         * @param {number} attemptCount Current attempt number
         * @param {number} maxAttempts Maximum attempts
         */
        showRetryMessage(attemptCount, maxAttempts) {
            const retryMessage = $('<div>')
                .addClass('alert alert-warning mt-2')
                .html(`
                    <i class="fa fa-refresh fa-spin me-2"></i>
                    Upload interrupted. Automatically retrying... (Attempt ${attemptCount} of ${maxAttempts})
                `);

            this.statusMessage.empty().append(retryMessage).show();
        }

        /**
         * Show enhanced retry message with error details and countdown.
         *
         * @param {number} attemptCount Current attempt number
         * @param {number} maxAttempts Maximum attempts
         * @param {number} delay Delay in milliseconds
         * @param {Object} errorAnalysis Error analysis object
         */
        showEnhancedRetryMessage(attemptCount, maxAttempts, delay, errorAnalysis) {
            const retryContainer = $('<div>')
                .addClass('alert alert-warning mt-2 cloudflarestream-retry-message');

            const retryHeader = $('<div>')
                .addClass('d-flex align-items-center mb-2')
                .append(
                    $('<i>').addClass('fa fa-refresh fa-spin me-2'),
                    $('<strong>').text(`Upload Retry (${attemptCount}/${maxAttempts})`)
                );

            const retryReason = $('<p>')
                .addClass('mb-2')
                .text(`${errorAnalysis.suggestions[0] || 'Connection issue detected'}. Retrying automatically...`);

            const countdownContainer = $('<div>')
                .addClass('d-flex align-items-center')
                .append(
                    $('<span>').text('Next attempt in: '),
                    $('<span>').addClass('ms-2 font-weight-bold cloudflarestream-countdown')
                );

            retryContainer.append(retryHeader, retryReason, countdownContainer);
            this.statusMessage.empty().append(retryContainer).show();

            // Start countdown
            this.startRetryCountdown(delay);
        }

        /**
         * Start countdown timer for retry.
         *
         * @param {number} totalDelay Total delay in milliseconds
         */
        startRetryCountdown(totalDelay) {
            const countdownElement = this.statusMessage.find('.cloudflarestream-countdown');
            if (countdownElement.length === 0) {
                return;
            }

            let remainingMs = totalDelay;
            const updateInterval = 100; // Update every 100ms for smooth countdown

            const updateCountdown = () => {
                if (remainingMs <= 0) {
                    countdownElement.text('0s');
                    return;
                }

                const seconds = Math.ceil(remainingMs / 1000);
                countdownElement.text(`${seconds}s`);
                remainingMs -= updateInterval;

                setTimeout(updateCountdown, updateInterval);
            };

            updateCountdown();
        }

        /**
         * Check connection speed and show appropriate messages.
         *
         * @param {number} bytesUploaded Bytes uploaded so far
         * @param {number} bytesTotal Total bytes to upload
         */
        checkConnectionSpeed(bytesUploaded, bytesTotal) {
            // Simple speed check - if we're uploading very slowly, warn the user
            if (!this.uploadStartTime) {
                this.uploadStartTime = Date.now();
                return;
            }

            const elapsedSeconds = (Date.now() - this.uploadStartTime) / 1000;
            const uploadedMB = bytesUploaded / (1024 * 1024);
            const speedMBps = uploadedMB / elapsedSeconds;

            // If speed is very slow (< 0.1 MB/s) and we've been uploading for more than 30 seconds
            if (speedMBps < 0.1 && elapsedSeconds > 30 && !this.slowConnectionWarningShown) {
                this.showConnectionWarning('slow');
                this.slowConnectionWarningShown = true;
            }
        }

        /**
         * Enhanced connection quality monitoring with detailed metrics.
         *
         * @param {number} bytesUploaded Bytes uploaded so far
         * @param {number} bytesTotal Total bytes to upload
         */
        monitorConnectionQuality(bytesUploaded, bytesTotal) {
            if (!this.uploadStartTime) {
                this.uploadStartTime = Date.now();
                this.lastProgressUpdate = Date.now();
                this.lastBytesUploaded = 0;
                this.speedHistory = [];
                return;
            }

            const now = Date.now();
            const elapsedSeconds = (now - this.uploadStartTime) / 1000;
            const timeSinceLastUpdate = (now - this.lastProgressUpdate) / 1000;

            // Calculate current speed
            const bytesSinceLastUpdate = bytesUploaded - this.lastBytesUploaded;
            const currentSpeedBps = timeSinceLastUpdate > 0 ? bytesSinceLastUpdate / timeSinceLastUpdate : 0;
            const currentSpeedMbps = (currentSpeedBps * 8) / (1024 * 1024); // Convert to Mbps

            // Update speed history (keep last 10 measurements)
            this.speedHistory.push(currentSpeedBps);
            if (this.speedHistory.length > 10) {
                this.speedHistory.shift();
            }

            // Calculate average speed
            const avgSpeedBps = this.speedHistory.reduce((sum, speed) => sum + speed, 0) / this.speedHistory.length;
            const avgSpeedMbps = (avgSpeedBps * 8) / (1024 * 1024);

            // Update tracking variables
            this.lastProgressUpdate = now;
            this.lastBytesUploaded = bytesUploaded;

            // Connection quality analysis (only after 10 seconds of upload)
            if (elapsedSeconds > 10 && this.speedHistory.length >= 5) {
                // Very slow connection (< 0.5 Mbps average)
                if (avgSpeedMbps < 0.5 && !this.slowConnectionWarningShown) {
                    this.showConnectionWarning('slow', {
                        avgSpeed: avgSpeedMbps.toFixed(2),
                        currentSpeed: currentSpeedMbps.toFixed(2)
                    });
                    this.slowConnectionWarningShown = true;
                }

                // Unstable connection (high speed variance)
                const speedVariance = this.calculateSpeedVariance();
                if (speedVariance > 0.5 && !this.unstableConnectionWarningShown) {
                    this.showConnectionWarning('unstable', {
                        variance: speedVariance.toFixed(2),
                        avgSpeed: avgSpeedMbps.toFixed(2)
                    });
                    this.unstableConnectionWarningShown = true;
                }

                // Connection improved after being slow
                if (this.slowConnectionWarningShown && avgSpeedMbps > 1.0) {
                    this.showConnectionWarning('improved', {
                        avgSpeed: avgSpeedMbps.toFixed(2)
                    });
                    this.slowConnectionWarningShown = false;
                }
            }

            // Estimate time remaining
            if (avgSpeedBps > 0) {
                const remainingBytes = bytesTotal - bytesUploaded;
                const estimatedSecondsRemaining = remainingBytes / avgSpeedBps;
                this.updateTimeRemaining(estimatedSecondsRemaining);
            }
        }

        /**
         * Calculate speed variance to detect unstable connections.
         *
         * @return {number} Speed variance coefficient
         */
        calculateSpeedVariance() {
            if (this.speedHistory.length < 3) {
                return 0;
            }

            const mean = this.speedHistory.reduce((sum, speed) => sum + speed, 0) / this.speedHistory.length;
            const variance = this.speedHistory.reduce((sum, speed) => sum + Math.pow(speed - mean, 2), 0) / this.speedHistory.length;
            const standardDeviation = Math.sqrt(variance);

            // Return coefficient of variation (standard deviation / mean)
            return mean > 0 ? standardDeviation / mean : 0;
        }

        /**
         * Update estimated time remaining display.
         *
         * @param {number} secondsRemaining Estimated seconds remaining
         */
        updateTimeRemaining(secondsRemaining) {
            if (secondsRemaining < 0 || !isFinite(secondsRemaining)) {
                return;
            }

            let timeText = '';
            if (secondsRemaining < 60) {
                timeText = `${Math.ceil(secondsRemaining)}s remaining`;
            } else if (secondsRemaining < 3600) {
                const minutes = Math.ceil(secondsRemaining / 60);
                timeText = `${minutes}m remaining`;
            } else {
                const hours = Math.floor(secondsRemaining / 3600);
                const minutes = Math.ceil((secondsRemaining % 3600) / 60);
                timeText = `${hours}h ${minutes}m remaining`;
            }

            // Update or create time remaining element
            let timeElement = this.progressContainer.find('.cloudflarestream-time-remaining');
            if (timeElement.length === 0) {
                timeElement = $('<div>')
                    .addClass('cloudflarestream-time-remaining text-muted small mt-1');
                this.progressContainer.append(timeElement);
            }
            timeElement.text(timeText);
        }

        /**
         * Clear connection warnings and monitoring data.
         */
        clearConnectionWarnings() {
            this.slowConnectionWarningShown = false;
            this.unstableConnectionWarningShown = false;
            this.speedHistory = [];

            // Remove time remaining display
            this.progressContainer.find('.cloudflarestream-time-remaining').remove();

            // Remove connection warning messages
            this.statusMessage.find('.cloudflarestream-connection-warning').remove();
        }

        /**
         * Show connection-related warnings with enhanced details.
         *
         * @param {string} type Type of warning ('slow', 'unstable', 'improved')
         * @param {Object} details Additional details about the connection
         */
        showConnectionWarning(type, details = {}) {
            let message = '';
            let alertClass = 'alert-info';
            let icon = 'fa-info-circle';
            let autoHide = false;

            switch (type) {
                case 'slow':
                    message = `<i class="fa ${icon} me-2"></i>Slow connection detected (${details.avgSpeed || 'N/A'} Mbps average). Upload may take longer than usual.`;
                    alertClass = 'alert-info';
                    break;
                case 'unstable':
                    message = `<i class="fa fa-exclamation-triangle me-2"></i>Unstable connection detected. Upload will automatically resume if interrupted.`;
                    alertClass = 'alert-warning';
                    break;
                case 'improved':
                    message = `<i class="fa fa-check-circle me-2"></i>Connection improved (${details.avgSpeed || 'N/A'} Mbps). Upload speed increased.`;
                    alertClass = 'alert-success';
                    autoHide = true;
                    break;
                case 'restored':
                    message = '<i class="fa fa-check-circle me-2"></i>Connection restored. Resuming upload...';
                    alertClass = 'alert-success';
                    autoHide = true;
                    break;
            }

            if (message) {
                // Remove existing connection warnings of the same type
                this.statusMessage.find(`.cloudflarestream-connection-warning[data-type="${type}"]`).remove();

                const warningDiv = $('<div>')
                    .addClass(`alert ${alertClass} mt-2 cloudflarestream-connection-warning`)
                    .attr('data-type', type)
                    .html(message);

                // Add to status message area
                this.statusMessage.append(warningDiv);

                // Auto-hide for success messages
                if (autoHide) {
                    setTimeout(() => {
                        warningDiv.fadeOut(() => {
                            warningDiv.remove();
                        });
                    }, 5000);
                }
            }
        }

        /**
         * Format file size for display.
         *
         * @param {number} bytes File size in bytes
         * @return {string} Formatted file size
         */
        formatFileSize(bytes) {
            if (bytes === 0) {
                return '0 Bytes';
            }
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }

    return {
        /**
         * Initialize the uploader.
         *
         * @param {number} assignmentId The assignment ID
         * @param {number} submissionId The submission ID
         * @param {number} maxFileSize Maximum file size in bytes
         * @param {string} containerSelector jQuery selector for the upload container
         */
        init: function (assignmentId, submissionId, maxFileSize, containerSelector) {
            const uploader = new CloudflareUploader(assignmentId, submissionId, maxFileSize);
            uploader.init(containerSelector || '.cloudflarestream-upload-interface');
        }
    };
});
