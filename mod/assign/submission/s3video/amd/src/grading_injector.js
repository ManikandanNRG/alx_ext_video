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
                // Wait a bit for Moodle to finish loading, then inject
                setTimeout(() => {
                    this.injectPlayer();
                }, 500);
            }
        },
        
        injectPlayer: function() {
            // Check if we already injected the player
            if ($('.s3video-two-column-layout').length > 0) {
                console.log('S3 Video: Two-column layout already exists, skipping injection');
                return;
            }
            
            // Find all video players and links
            var $readyLink = $('.s3video-watch-link');
            var $existingPlayers = $('.s3video-grading-view, .s3video-player-container');
            
            if ($readyLink.length === 0 && $existingPlayers.length === 0) {
                console.log('S3 Video: No video found on this page');
                return;
            }
            
            console.log('S3 Video: Found video, injecting two-column layout...');
            
            // Get video info from link or existing player
            var submissionId, s3key;
            
            if ($readyLink.length > 0) {
                var href = $readyLink.attr('href');
                var match = href.match(/id=(\d+)/);
                var s3keyMatch = href.match(/s3key=([^&]+)/);
                
                if (!match || !s3keyMatch) {
                    console.error('S3 Video: Could not extract video info from link');
                    return;
                }
                
                submissionId = match[1];
                s3key = decodeURIComponent(s3keyMatch[1]);
            } else {
                // Try to get info from existing player
                var $existingPlayer = $existingPlayers.first();
                submissionId = $existingPlayer.attr('id') ? $existingPlayer.attr('id').match(/\d+/)[0] : Date.now();
                s3key = $existingPlayer.data('s3-key') || '';
                
                if (!s3key) {
                    console.error('S3 Video: Could not extract s3key from existing player');
                    return;
                }
            }
            
            // Create player container
            var containerId = 's3video-player-' + submissionId + '-' + Date.now();
            
            // Create two-column layout structure
            this.createTwoColumnLayout($readyLink.length > 0 ? $readyLink : $existingPlayers.first(), containerId, s3key, submissionId);
        },
        
        createTwoColumnLayout: function($videoElement, containerId, s3key, submissionId) {
            console.log('S3 Video: Creating two-column layout...');
            
            // Find the main grading container
            var $gradingContainer = $videoElement.closest('[data-region="grade-panel"]');
            if ($gradingContainer.length === 0) {
                // Fallback: look for other grading containers
                $gradingContainer = $videoElement.closest('.submissionstatustable');
                if ($gradingContainer.length === 0) {
                    $gradingContainer = $videoElement.closest('.assignment');
                    if ($gradingContainer.length === 0) {
                        $gradingContainer = $videoElement.closest('body');
                    }
                }
            }
            
            if ($gradingContainer.length === 0) {
                console.warn('S3 Video: Could not find grading container, using fallback');
                // Fallback to simple replacement
                this.createSimplePlayer($videoElement, containerId, s3key, submissionId);
                return;
            }
            
            // Remove any existing video players to prevent duplication
            $gradingContainer.find('.s3video-grading-view, .s3video-player-container').remove();
            
            // Store original grading content (excluding video elements)
            var $gradingContent = $gradingContainer.children().not('.s3video-grading-view, .s3video-player-container, .s3video-two-column-layout').clone(true);
            
            // Create two-column layout HTML
            var twoColumnHtml = 
                '<div class="s3video-two-column-layout">' +
                    '<div class="s3video-left-column">' +
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
                    '</div>' +
                    '<div class="s3video-right-column" id="s3video-grading-panel">' +
                        '<!-- Grading content will be moved here -->' +
                    '</div>' +
                '</div>';
            
            // Replace the grading container content
            $gradingContainer.html(twoColumnHtml);
            
            // Move grading content to right column
            var $rightColumn = $gradingContainer.find('#s3video-grading-panel');
            $rightColumn.append($gradingContent);
            
            // Initialize the player
            require(['assignsubmission_s3video/player'], function(Player) {
                console.log('S3 Video: Initializing player with:', {
                    s3key: s3key,
                    submissionId: submissionId,
                    containerId: containerId
                });
                Player.init(s3key, submissionId, containerId);
            });
        },
        
        createSimplePlayer: function($readyLink, containerId, s3key, submissionId) {
            console.log('S3 Video: Creating simple player (fallback)...');
            
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
