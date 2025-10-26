// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * S3 Video uploader module.
 *
 * @module     assignsubmission_s3video/uploader
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

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
     * S3Uploader class for handling video uploads.
     */
    class S3Uploader {
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
            this.currentFile = null;
            this.currentXHR = null;
            this.retryCount = 0;
            this.maxRetries = 3;
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
            this.dropzone = this.container.find('.s3video-dropzone');
            this.fileInput = this.container.find('.s3video-file-input');
            this.selectBtn = this.container.find('.s3video-select-btn');
            this.progressContainer = this.container.find('.s3video-progress-container');
            this.progressBar = this.container.find('.s3video-progress-bar');
            this.progressPercentage = this.container.find('.s3video-progress-percentage');
            this.statusMessage = this.container.find('.s3video-status-message');

            // Attach event handlers
            this.attachEventHandlers();
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

            // Store file for retry
            this.currentFile = file;

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
                return {
                    valid: false,
                    error: 'Unable to determine file type'
                };
            }

            if (!ALLOWED_MIME_TYPES.includes(file.type) && !file.type.startsWith('video/')) {
                return {
                    valid: false,
                    error: 'Unsupported file type: ' + file.type + '. Please upload a video file.'
                };
            }

            // Check file extension
            const extension = this.getFileExtension(file.name);
            const allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mpeg', 'mpg', 'ogv', '3gp', 'flv'];
            
            if (!allowedExtensions.includes(extension.toLowerCase())) {
                return {
                    valid: false,
                    error: 'Unsupported file extension: .' + extension + '. Allowed: ' + allowedExtensions.join(', ')
                };
            }

            // Check filename length
            if (file.name.length > 255) {
                return {
                    valid: false,
                    error: 'Filename is too long (maximum 255 characters)'
                };
            }

            // Check for dangerous characters
            const dangerousChars = /[<>:"|?*\x00-\x1f]/;
            if (dangerousChars.test(file.name)) {
                return {
                    valid: false,
                    error: 'Filename contains invalid characters'
                };
            }

            return {valid: true};
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
         * Format file size for display.
         *
         * @param {number} bytes File size in bytes
         * @return {string} Formatted file size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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

                // Request presigned POST from Moodle with automatic retry
                const uploadData = await this.requestUploadUrlWithRetry(file);

                // Upload file directly to S3
                await this.uploadToS3(file, uploadData);

                // Confirm upload completion with Moodle with automatic retry
                await this.confirmUploadWithRetry(uploadData.s3_key, uploadData.submission_id);

                // Show success message
                this.showSuccess();

            } catch (error) {
                this.handleError(error);
            }
        }

        /**
         * Request upload URL with automatic retry for transient failures.
         *
         * @param {File} file The file to upload
         * @return {Promise<Object>} Upload data
         */
        async requestUploadUrlWithRetry(file) {
            const maxAutoRetries = 2;
            let attempt = 0;

            while (attempt <= maxAutoRetries) {
                try {
                    return await this.requestUploadUrl(file);
                } catch (error) {
                    attempt++;
                    
                    // Check if error is retryable and we haven't exceeded max attempts
                    const isTransient = this.isTransientError(error);
                    if (isTransient && attempt <= maxAutoRetries) {
                        // Show retry message
                        this.showRetryMessage(attempt, maxAutoRetries);
                        
                        // Wait before retrying (exponential backoff)
                        const delay = Math.pow(2, attempt - 1) * 1000;
                        await this.sleep(delay);
                        
                        continue;
                    }
                    
                    // Non-retryable error or max attempts exceeded
                    throw error;
                }
            }
        }

        /**
         * Confirm upload with automatic retry for transient failures.
         *
         * @param {string} s3Key The S3 key
         * @param {number} submissionId The submission ID
         * @return {Promise<Object>} Confirmation response
         */
        async confirmUploadWithRetry(s3Key, submissionId) {
            const maxAutoRetries = 2;
            let attempt = 0;

            while (attempt <= maxAutoRetries) {
                try {
                    return await this.confirmUpload(s3Key, submissionId);
                } catch (error) {
                    attempt++;
                    
                    // Check if error is retryable and we haven't exceeded max attempts
                    const isTransient = this.isTransientError(error);
                    if (isTransient && attempt <= maxAutoRetries) {
                        // Show retry message
                        this.showRetryMessage(attempt, maxAutoRetries);
                        
                        // Wait before retrying (exponential backoff)
                        const delay = Math.pow(2, attempt - 1) * 1000;
                        await this.sleep(delay);
                        
                        continue;
                    }
                    
                    // Non-retryable error or max attempts exceeded
                    throw error;
                }
            }
        }

        /**
         * Check if error is transient and retryable.
         *
         * @param {Error} error The error
         * @return {boolean} True if transient
         */
        isTransientError(error) {
            if (!error.errorType) {
                return false;
            }

            const transientTypes = [
                'network_error',
                'timeout_error',
                'server_error',
                's3_error',
                'cloudfront_error',
                'rate_limit'
            ];

            return transientTypes.includes(error.errorType);
        }

        /**
         * Show retry message during automatic retry.
         *
         * @param {number} attempt Current attempt
         * @param {number} maxAttempts Maximum attempts
         */
        showRetryMessage(attempt, maxAttempts) {
            const message = 'Retrying... (Attempt ' + attempt + ' of ' + maxAttempts + ')';
            this.progressPercentage.text(message);
        }

        /**
         * Sleep for specified milliseconds.
         *
         * @param {number} ms Milliseconds to sleep
         * @return {Promise<void>}
         */
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        /**
         * Request a presigned POST URL from Moodle backend.
         *
         * @param {File} file The file to upload
         * @return {Promise<Object>} Upload data with presigned POST fields
         */
        async requestUploadUrl(file) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/s3video/ajax/get_upload_url.php',
                    method: 'POST',
                    data: {
                        assignmentid: this.assignmentId,
                        submissionid: this.submissionId,
                        filename: file.name,
                        filesize: file.size,
                        mimetype: file.type,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        resolve(data.data);
                    } else {
                        const error = new Error(data.error || 'Failed to get upload URL');
                        error.errorType = data.error_type || 'unknown';
                        error.canRetry = data.can_retry !== false;
                        error.guidance = data.guidance || '';
                        error.technicalDetails = data.technical_details || '';
                        reject(error);
                    }
                }).fail((jqXHR) => {
                    let errorMessage = 'Network error occurred while requesting upload URL';
                    let errorType = 'network_error';
                    let guidance = 'Please check your internet connection and try again.';
                    
                    // Try to parse error response.
                    if (jqXHR.responseJSON) {
                        errorMessage = jqXHR.responseJSON.error || errorMessage;
                        errorType = jqXHR.responseJSON.error_type || errorType;
                        guidance = jqXHR.responseJSON.guidance || guidance;
                    }
                    
                    const error = new Error(errorMessage);
                    error.errorType = errorType;
                    error.canRetry = true;
                    error.guidance = guidance;
                    reject(error);
                });
            });
        }

        /**
         * Upload file directly to S3 using presigned POST.
         *
         * @param {File} file The file to upload
         * @param {Object} uploadData Upload data from backend
         * @return {Promise<void>}
         */
        uploadToS3(file, uploadData) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();

                // Add all presigned POST fields
                Object.keys(uploadData.fields).forEach(key => {
                    formData.append(key, uploadData.fields[key]);
                });

                // Add file last (required by S3)
                formData.append('file', file);

                // Create XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                this.currentXHR = xhr;

                // Progress tracking
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentage = Math.round((e.loaded / e.total) * 100);
                        this.updateProgress(percentage);
                    }
                });

                // Upload complete
                xhr.addEventListener('load', () => {
                    this.currentXHR = null;
                    
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        const error = new Error('S3 upload failed with status: ' + xhr.status);
                        error.errorType = 's3_error';
                        error.canRetry = true;
                        reject(error);
                    }
                });

                // Upload error
                xhr.addEventListener('error', () => {
                    this.currentXHR = null;
                    const error = new Error('Network error during S3 upload');
                    error.errorType = 'network_error';
                    error.canRetry = true;
                    reject(error);
                });

                // Upload aborted
                xhr.addEventListener('abort', () => {
                    this.currentXHR = null;
                    const error = new Error('Upload was cancelled');
                    error.errorType = 'cancelled';
                    error.canRetry = true;
                    reject(error);
                });

                // Send request to S3
                xhr.open('POST', uploadData.url);
                xhr.send(formData);
            });
        }

        /**
         * Confirm upload completion with Moodle backend.
         *
         * @param {string} s3Key The S3 key
         * @param {number} submissionId The submission ID
         * @return {Promise<Object>} Confirmation response
         */
        async confirmUpload(s3Key, submissionId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/s3video/ajax/confirm_upload.php',
                    method: 'POST',
                    data: {
                        s3_key: s3Key,
                        submissionid: submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        // Update hidden form fields
                        $('input[name="s3video_s3_key"]').val(s3Key);
                        $('input[name="s3video_status"]').val(data.data.status);
                        if (data.data.file_size) {
                            $('input[name="s3video_file_size"]').val(data.data.file_size);
                        }
                        resolve(data.data);
                    } else {
                        const error = new Error(data.error || 'Failed to confirm upload');
                        error.errorType = data.error_type || 'unknown';
                        error.canRetry = data.can_retry !== false;
                        error.guidance = data.guidance || '';
                        error.technicalDetails = data.technical_details || '';
                        reject(error);
                    }
                }).fail((jqXHR) => {
                    let errorMessage = 'Network error occurred while confirming upload';
                    let errorType = 'network_error';
                    let guidance = 'Please check your internet connection and try again.';
                    
                    // Try to parse error response.
                    if (jqXHR.responseJSON) {
                        errorMessage = jqXHR.responseJSON.error || errorMessage;
                        errorType = jqXHR.responseJSON.error_type || errorType;
                        guidance = jqXHR.responseJSON.guidance || guidance;
                    } else if (jqXHR.status === 403) {
                        errorMessage = 'Permission denied. Please refresh the page and try again.';
                        errorType = 'permission_error';
                        guidance = 'Try refreshing the page and logging in again.';
                    } else if (jqXHR.status >= 500) {
                        errorMessage = 'Server error occurred. Please try again later.';
                        errorType = 'server_error';
                        guidance = 'This is usually a temporary issue. Wait a few minutes and try again.';
                    }
                    
                    const error = new Error(errorMessage);
                    error.errorType = errorType;
                    error.canRetry = true;
                    error.guidance = guidance;
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
            
            Str.get_string('uploadsuccess', 'assignsubmission_s3video').then((message) => {
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
            this.currentFile = null;
        }

        /**
         * Show error message.
         *
         * @param {string|Error} error The error message or Error object
         */
        showError(error) {
            const errorMessage = error instanceof Error ? error.message : error;
            const guidance = error instanceof Error && error.guidance ? error.guidance : '';
            const technicalDetails = error instanceof Error && error.technicalDetails ? error.technicalDetails : '';
            
            // Build error HTML.
            let errorHtml = '<div class="s3video-error-container">';
            errorHtml += '<div class="s3video-error-message"><strong>Error:</strong> ' + errorMessage + '</div>';
            
            if (guidance) {
                errorHtml += '<div class="s3video-error-guidance mt-2"><strong>What to do:</strong> ' + guidance + '</div>';
            }
            
            if (technicalDetails) {
                errorHtml += '<details class="s3video-error-details mt-2">';
                errorHtml += '<summary>Technical Details</summary>';
                errorHtml += '<pre class="mt-1">' + technicalDetails + '</pre>';
                errorHtml += '</details>';
            }
            
            errorHtml += '</div>';
            
            this.statusMessage.html(errorHtml)
                .addClass('alert alert-danger')
                .show();

            // Show retry button if upload was in progress and retries available.
            if (this.uploadInProgress && this.retryCount < this.maxRetries && this.currentFile) {
                const retryBtn = $('<button>')
                    .addClass('btn btn-secondary mt-2')
                    .text('Retry Upload (' + (this.retryCount + 1) + '/' + this.maxRetries + ')')
                    .on('click', () => {
                        this.retryCount++;
                        this.retry();
                    });
                this.statusMessage.append(retryBtn);
            } else if (this.currentFile && error.canRetry) {
                // Show manual retry button even if max auto-retries reached.
                const retryBtn = $('<button>')
                    .addClass('btn btn-secondary mt-2')
                    .text('Try Again')
                    .on('click', () => {
                        this.retryCount = 0; // Reset retry count for manual retry.
                        this.retry();
                    });
                this.statusMessage.append(retryBtn);
            }
        }

        /**
         * Handle upload errors.
         *
         * @param {Error} error The error object
         */
        handleError(error) {
            this.uploadInProgress = false;
            this.progressContainer.hide();
            this.dropzone.show();

            // Log error for debugging
            // eslint-disable-next-line no-console
            console.error('Upload error:', error);

            // Determine if error is retryable
            const isRetryable = error.canRetry !== false && this.retryCount < this.maxRetries;

            // Show error with retry option
            if (isRetryable) {
                this.showError(error);
            } else {
                // Final error - no retry
                this.statusMessage.html(error.message)
                    .addClass('alert alert-danger')
                    .show();
            }
        }

        /**
         * Retry the last upload.
         */
        retry() {
            if (!this.currentFile) {
                this.showError('No file to retry. Please select a file again.');
                return;
            }

            this.statusMessage.empty().removeClass('alert-danger alert-success').hide();
            this.startUpload(this.currentFile);
        }

        /**
         * Cancel the current upload.
         */
        cancel() {
            if (this.currentXHR) {
                this.currentXHR.abort();
                this.currentXHR = null;
            }
            
            this.uploadInProgress = false;
            this.progressContainer.hide();
            this.dropzone.show();
            this.statusMessage.empty().removeClass('alert-danger alert-success').hide();
        }
    }

    return {
        /**
         * Initialize the uploader.
         *
         * @param {number} assignmentId The assignment ID
         * @param {number} submissionId The submission ID
         * @param {number} maxFileSize Maximum file size
         * @param {string} containerSelector Container selector
         * @return {S3Uploader} The uploader instance
         */
        init: function(assignmentId, submissionId, maxFileSize, containerSelector) {
            const uploader = new S3Uploader(assignmentId, submissionId, maxFileSize);
            uploader.init(containerSelector);
            return uploader;
        }
    };
});