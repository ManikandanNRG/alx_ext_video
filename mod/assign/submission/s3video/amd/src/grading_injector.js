// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Inject video player into grading interface
 *
 * @module     assignsubmission_s3video/grading_injector
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/templates'], function($, Ajax, Templates) {
    
    return {
        init: function() {
            // Check if we're on the grading page
            var urlParams = new URLSearchParams(window.location.search);
            var action = urlParams.get('action');
            var rownum = urlParams.get('rownum');
            var userid = urlParams.get('userid');
            
            // Only run on individual grading page
            if (action === 'grader' && (rownum !== null || userid !== null)) {
                this.injectPlayer();
            }
        },
        
        injectPlayer: function() {
            // Find the "Ready (XX MB)" text
            var $readyLink = $('.s3video-watch-link');
            
            if ($readyLink.length === 0) {
                console.log('S3 Video: No video found on this page');
                return;
            }
            
            console.log('S3 Video: Found video link, injecting player...');
            
            // Get the submission ID from the link
            var href = $readyLink.attr('href');
            var match = href.match(/id=(\d+)/);
            
            if (!match) {
                console.error('S3 Video: Could not extract submission ID');
                return;
            }
            
            var submissionId = match[1];
            
            // Get the s3_key
            var s3keyMatch = href.match(/s3key=([^&]+)/);
            if (!s3keyMatch) {
                console.error('S3 Video: Could not extract s3_key');
                return;
            }
            
            var s3key = decodeURIComponent(s3keyMatch[1]);
            
            // Create player container
            var containerId = 's3video-player-' + submissionId + '-' + Date.now();
            
            var playerHtml = '<div class="s3video-grading-view">' +
                '<div class="s3video-player-wrapper">' +
                '<div class="s3video-player-container" id="' + containerId + '">' +
                '<div class="s3video-loading">' +
                '<div class="spinner-border text-light" role="status">' +
                '<span class="sr-only">Loading video...</span>' +
                '</div>' +
                '<p>Loading video player...</p>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            // Replace the "Ready (XX MB)" text with the player
            $readyLink.parent().html(playerHtml);
            
            // Initialize the player
            require(['assignsubmission_s3video/player'], function(Player) {
                console.log('S3 Video: Initializing player with:', {
                    s3key: s3key,
                    submissionId: submissionId,
                    containerId: containerId
                });
                Player.init(s3key, submissionId, containerId);
            });
        }
    };
});
