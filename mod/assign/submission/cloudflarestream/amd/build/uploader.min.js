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

define(['jquery'], function ($) {

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
            this.currentFile = null;
            this.currentXHR = null;
            this.retryCount = 0;
            this.maxRetries = 3;
            this.uploadData = null; // Store upload data for cleanup
            this.uploadCompleted = false; // Track if upload completed successfully
            
            // Register beforeunload handler for cleanup on page navigation
            this.registerBeforeUnloadHandler();
            
            // Register form submit handler to clear tracking when saved
            this.registerFormSubmitHandler();
        }
        
        /**
         * Register handler to cleanup on page unload/navigation.
         * This catches: Cancel button, browser close, tab close, back button, etc.
         */
        registerBeforeUnloadHandler() {
            const self = this;
            window.addEventListener('beforeunload', (event) => {
                // Cleanup if:
                // 1. Upload is in progress (uploading), OR
                // 2. Upload completed but form not saved yet (uploadCompleted = true)
                if (self.uploadData && self.uploadData.uid && 
                    (self.uploadInProgress || self.uploadCompleted)) {
                    
                    // Use sendBeacon for reliable delivery during page unload
                    const url = M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/cleanup_failed_upload.php';
                    const formData = new FormData();
                    formData.append('videouid', self.uploadData.uid);
                    formData.append('submissionid', self.uploadData.submissionid);
                    formData.append('sesskey', M.cfg.sesskey);
                    
                    // sendBeacon is specifically designed for this use case
                    // It sends data reliably even when page is unloading
                    navigator.sendBeacon(url, formData);
                }
            });
        }
        
        /**
         * Register form submit handler to clear tracking when form is saved.
         * This prevents cleanup when user actually saves the submission.
         */
        registerFormSubmitHandler() {
            // Find the assignment submission form
            const $form = $('form[id*="submissionform"], form.mform');
            
            if ($form.length > 0) {
                // Listen for Save button click specifically
                $form.find('input[type="submit"][name="submitbutton"]').on('click', () => {
                    // User is saving the form - clear tracking to prevent cleanup
                    this.uploadData = null;
                    this.uploadCompleted = false;
                    this.uploadInProgress = false;
                });
            }
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

            // Check file size
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

            return { valid: true };
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
         * Start the upload process with automatic cleanup on failure.
         * Uses hybrid approach: direct upload for <200MB, TUS for >=200MB.
         *
         * @param {File} file The file to upload
         */
        async startUpload(file) {
            if (this.uploadInProgress) {
                this.showError('An upload is already in progress.');
                return;
            }

            let uploadData = null;
            const DIRECT_UPLOAD_LIMIT = 200 * 1024 * 1024; // 200MB

            try {
                this.uploadInProgress = true;
                this.showProgress();
                this.updateProgress(0);

                // Choose upload method based on file size
                if (file.size < DIRECT_UPLOAD_LIMIT) {
                    // Small file: Use direct upload (existing method)
                    console.log('Using direct upload for ' + this.formatFileSize(file.size) + ' file');
                    uploadData = await this.requestUploadUrl(file);
                    this.uploadData = uploadData;
                    await this.uploadToCloudflare(file, uploadData);
                } else {
                    // Large file: Use TUS resumable upload
                    console.log('Using TUS upload for ' + this.formatFileSize(file.size) + ' file');
                    uploadData = await this.createTusSession(file);
                    this.uploadData = uploadData;
                    await this.uploadViaTus(file, uploadData);
                }

                // Confirm upload
                this.updateProgress(100, 'Finalizing upload...');
                await this.confirmUploadWithRetry(uploadData.uid, uploadData.submissionid);

                // Success - keep uploadData for cleanup if user cancels
                this.uploadInProgress = false;
                this.uploadCompleted = true; // Mark as completed but not saved
                this.showSuccess();

            } catch (error) {
                this.uploadInProgress = false;
                
                if (uploadData && uploadData.uid) {
                    console.log('Upload failed, cleaning up video: ' + uploadData.uid);
                    await this.cleanupFailedUpload(uploadData.uid, uploadData.submissionid);
                    this.uploadData = null;
                }
                
                this.handleError(error);
            }
        }

        /**
         * Clean up failed upload - delete video from Cloudflare and database.
         * This prevents dummy "Pending Upload" entries in Cloudflare.
         * (TASK 7 PHASE 1)
         *
         * @param {string} videoUid The Cloudflare video UID to delete
         * @param {number} submissionId The submission ID
         * @return {Promise<void>}
         */
        async cleanupFailedUpload(videoUid, submissionId) {
            if (!videoUid) {
                return; // Nothing to clean up
            }
            
            try {
                console.log('Cleaning up failed upload: ' + videoUid);
                
                await $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/cleanup_failed_upload.php',
                    method: 'POST',
                    data: {
                        videouid: videoUid,
                        submissionid: submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                });
                
                console.log('Successfully cleaned up failed upload: ' + videoUid);
            } catch (error) {
                // Silently fail - cleanup will be handled by scheduled task
                console.error('Failed to cleanup video ' + videoUid + ':', error);
            }
        }

        /**
         * Request upload URL from Moodle backend.
         *
         * @param {File} file The file to upload
         * @return {Promise<Object>} Upload data
         */
        async requestUploadUrl(file) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/get_upload_url.php',
                    method: 'POST',
                    data: {
                        assignmentid: this.assignmentId,
                        submissionid: this.submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        // Response has uploadURL, uid, submissionid at top level
                        resolve({
                            uploadURL: data.uploadURL,
                            uid: data.uid,
                            submissionid: data.submissionid
                        });
                    } else {
                        const error = new Error(data.error || 'Failed to get upload URL');
                        error.canRetry = true;
                        reject(error);
                    }
                }).fail((jqXHR) => {
                    const error = new Error('Network error occurred while requesting upload URL');
                    error.canRetry = true;
                    reject(error);
                });
            });
        }

        /**
         * Upload file directly to Cloudflare Stream.
         *
         * @param {File} file The file to upload
         * @param {Object} uploadData Upload data from backend
         * @return {Promise<void>}
         */
        uploadToCloudflare(file, uploadData) {
            return new Promise((resolve, reject) => {
                // Create FormData
                const formData = new FormData();
                formData.append('file', file);

                // Create XMLHttpRequest for progress tracking
                const xhr = new XMLHttpRequest();
                this.currentXHR = xhr;

                // Progress tracking
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentage = Math.round((e.loaded / e.total) * 100);
                        const uploadedMB = (e.loaded / (1024 * 1024)).toFixed(1);
                        const totalMB = (e.total / (1024 * 1024)).toFixed(1);
                        this.updateProgress(percentage, uploadedMB + 'MB / ' + totalMB + 'MB');
                    }
                });

                // Upload complete
                xhr.addEventListener('load', () => {
                    this.currentXHR = null;

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        const error = new Error('Cloudflare upload failed with status: ' + xhr.status);
                        error.canRetry = true;
                        reject(error);
                    }
                });

                // Upload error
                xhr.addEventListener('error', () => {
                    this.currentXHR = null;
                    const error = new Error('Network error during upload');
                    error.canRetry = true;
                    reject(error);
                });

                // Upload timeout
                xhr.addEventListener('timeout', () => {
                    this.currentXHR = null;
                    const error = new Error('Upload timeout');
                    error.canRetry = true;
                    reject(error);
                });

                // Upload aborted
                xhr.addEventListener('abort', () => {
                    this.currentXHR = null;
                    const error = new Error('Upload was cancelled');
                    error.canRetry = true;
                    reject(error);
                });

                // Send request to Cloudflare Stream
                xhr.open('POST', uploadData.uploadURL);
                xhr.timeout = 0; // No timeout - let it take as long as needed
                xhr.send(formData);
            });
        }

        /**
         * Confirm upload with retry - polls Cloudflare status until ready or max attempts.
         * 
         * For small files: Usually ready on first attempt (5 seconds)
         * For large files: Retries up to 5 times (total 60 seconds)
         * If still processing: Saves as "uploading" and user can refresh later
         *
         * @param {string} videoId The Cloudflare video ID
         * @param {number} submissionId The submission ID
         * @return {Promise<Object>} Confirmation response
         */
        async confirmUploadWithRetry(videoId, submissionId) {
            const maxAttempts = 5;
            const delays = [5000, 10000, 15000, 15000, 15000]; // Total: 60 seconds
            
            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                // Wait before checking (except first attempt which waits 3 seconds)
                if (attempt > 0) {
                    // Show animated dots instead of confusing attempt counter
                    const dots = '.'.repeat((attempt % 3) + 1);
                    this.updateProgress(100, `Processing video${dots}`);
                }
                await this.sleep(delays[attempt]);
                
                try {
                    const result = await this.confirmUpload(videoId, submissionId);
                    
                    // Check if video is ready
                    if (result.status === 'ready') {
                        console.log('✅ Video is ready after ' + (attempt + 1) + ' attempts');
                        return result;
                    }
                    
                    // If still uploading and not last attempt, retry
                    if (result.status === 'uploading' && attempt < maxAttempts - 1) {
                        console.log('Video still processing, will retry... (attempt ' + (attempt + 1) + ')');
                        continue;
                    }
                    
                    // Last attempt or other status - return as is
                    console.log('Video status: ' + result.status + ' after ' + (attempt + 1) + ' attempts');
                    return result;
                    
                } catch (error) {
                    // On error, if not last attempt, retry
                    if (attempt < maxAttempts - 1) {
                        console.log('Error confirming upload, will retry...', error);
                        continue;
                    }
                    // Last attempt - throw error
                    throw error;
                }
            }
        }

        /**
         * Confirm upload completion with Moodle backend.
         *
         * @param {string} videoId The Cloudflare video ID
         * @param {number} submissionId The submission ID
         * @return {Promise<Object>} Confirmation response
         */
        async confirmUpload(videoId, submissionId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/confirm_upload.php',
                    method: 'POST',
                    data: {
                        videouid: videoId,
                        submissionid: submissionId,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        // Update hidden form fields
                        $('input[name="cloudflarestream_video_uid"]').val(videoId);
                        $('input[name="cloudflarestream_status"]').val(data.status);
                        
                        // Also update file size and duration if available
                        if (data.filesize) {
                            $('input[name="cloudflarestream_file_size"]').val(data.filesize);
                        }
                        if (data.duration) {
                            $('input[name="cloudflarestream_duration"]').val(data.duration);
                        }
                        
                        resolve(data);
                    } else {
                        const error = new Error(data.error || 'Failed to confirm upload');
                        error.canRetry = true;
                        reject(error);
                    }
                }).fail((jqXHR) => {
                    const error = new Error('Network error occurred while confirming upload');
                    error.canRetry = true;
                    reject(error);
                });
            });
        }

        /**
         * Create TUS upload session via PHP.
         * PHP handles all Cloudflare communication.
         *
         * @param {File} file The file to upload
         * @return {Promise<Object>} Upload data with uid and submissionid
         */
        async createTusSession(file) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/upload_tus.php',
                    method: 'POST',
                    data: {
                        action: 'create',
                        assignmentid: this.assignmentId,
                        filesize: file.size,
                        filename: file.name,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json'
                }).done((data) => {
                    if (data.success) {
                        resolve({
                            uid: data.uid,
                            submissionid: data.submissionid
                        });
                    } else {
                        reject(new Error(data.error || 'Failed to create TUS session'));
                    }
                }).fail(() => {
                    reject(new Error('Network error while creating TUS session'));
                });
            });
        }

        /**
         * Upload file using TUS resumable upload protocol.
         *
         * @param {File} file The file to upload
         * @param {Object} uploadData Upload data (contains upload_url and uid)
         * @return {Promise<void>}
         */
        async uploadViaTus(file, uploadData) {
            const CHUNK_SIZE = 52428800; // 50MB (recommended by Cloudflare)
            let offset = 0;

            while (offset < file.size) {
                // Read chunk
                const chunkEnd = Math.min(offset + CHUNK_SIZE, file.size);
                const chunk = file.slice(offset, chunkEnd);
                const chunkData = await this.readChunkAsArrayBuffer(chunk);

                // Upload chunk
                const newOffset = await this.uploadTusChunk(uploadData.upload_url, chunkData, offset);

                // Update progress
                offset = newOffset;
                const percentage = Math.round((offset / file.size) * 100);
                const uploadedMB = (offset / (1024 * 1024)).toFixed(1);
                const totalMB = (file.size / (1024 * 1024)).toFixed(1);
                this.updateProgress(percentage, uploadedMB + 'MB / ' + totalMB + 'MB');
            }
        }

        /**
         * Read file chunk as ArrayBuffer.
         *
         * @param {Blob} chunk File chunk
         * @return {Promise<ArrayBuffer>} Chunk data
         */
        readChunkAsArrayBuffer(chunk) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(reader.error);
                reader.readAsArrayBuffer(chunk);
            });
        }

        /**
         * Upload a single TUS chunk via PHP.
         * PHP handles all Cloudflare communication.
         *
         * @param {string} uploadUrl Not used (kept for compatibility)
         * @param {ArrayBuffer} chunkData Chunk data
         * @param {number} offset Current byte offset
         * @return {Promise<number>} New offset after upload
         */
        async uploadTusChunk(uploadUrl, chunkData, offset) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                const url = M.cfg.wwwroot + '/mod/assign/submission/cloudflarestream/ajax/upload_tus.php' +
                    '?action=chunk' +
                    '&assignmentid=' + this.assignmentId +
                    '&offset=' + offset +
                    '&sesskey=' + M.cfg.sesskey;

                xhr.open('POST', url);
                xhr.setRequestHeader('Content-Type', 'application/octet-stream');

                xhr.onload = () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response.offset);
                            } else {
                                reject(new Error(response.error || 'Chunk upload failed'));
                            }
                        } catch (e) {
                            reject(new Error('Invalid response from server'));
                        }
                    } else {
                        reject(new Error('TUS chunk upload failed: ' + xhr.status));
                    }
                };

                xhr.onerror = () => {
                    reject(new Error('Network error during TUS chunk upload'));
                };

                xhr.ontimeout = () => {
                    reject(new Error('TUS chunk upload timeout'));
                };

                xhr.send(chunkData);
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
         * @param {string} details Optional details text
         */
        updateProgress(percentage, details) {
            this.progressBar.css('width', percentage + '%')
                .attr('aria-valuenow', percentage)
                .text(percentage + '%');

            if (details) {
                this.progressPercentage.text(details + ' (' + percentage + '%)');
            } else {
                this.progressPercentage.text(percentage + '%');
            }
        }

        /**
         * Show success message.
         */
        showSuccess() {
            this.uploadInProgress = false;
            this.progressContainer.hide();

            this.statusMessage.html('Video uploaded successfully!')
                .addClass('alert alert-success')
                .show();

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

            this.statusMessage.html('<strong>Error:</strong> ' + errorMessage)
                .addClass('alert alert-danger')
                .show();

            // Show retry button if upload was in progress and retries available
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
                // Show manual retry button
                const retryBtn = $('<button>')
                    .addClass('btn btn-secondary mt-2')
                    .text('Try Again')
                    .on('click', () => {
                        this.retryCount = 0;
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

            // Show error with retry option
            this.showError(error);
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
         * Sleep for specified milliseconds.
         *
         * @param {number} ms Milliseconds to sleep
         * @return {Promise<void>}
         */
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
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
         * @return {CloudflareUploader} The uploader instance
         */
        init: function (assignmentId, submissionId, maxFileSize, containerSelector) {
            const uploader = new CloudflareUploader(assignmentId, submissionId, maxFileSize);
            uploader.init(containerSelector);
            return uploader;
        }
    };
});
