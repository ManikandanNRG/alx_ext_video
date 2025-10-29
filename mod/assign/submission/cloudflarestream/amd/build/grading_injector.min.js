// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Inject video player into grading interface
 *
 * @module     assignsubmission_cloudflarestream/grading_injector
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
            if ($('.cloudflarestream-two-column-layout').length > 0) {
                console.log('Cloudflare Stream: Two-column layout already exists, skipping injection');
                return;
            }
            
            // Find all video players and links
            var $readyLink = $('.cloudflarestream-watch-link');
            var $existingPlayers = $('.cloudflarestream-grading-view, .cloudflarestream-player-container');
            
            if ($readyLink.length === 0 && $existingPlayers.length === 0) {
                console.log('Cloudflare Stream: No video found on this page');
                return;
            }
            
            console.log('Cloudflare Stream: Found video, injecting two-column layout...');
            
            // Get video info from link or existing player
            var submissionId, videoUid;
            
            if ($readyLink.length > 0) {
                var href = $readyLink.attr('href');
                var match = href.match(/id=(\d+)/);
                var videoMatch = href.match(/video_uid=([^&]+)/);
                
                if (!match || !videoMatch) {
                    console.error('Cloudflare Stream: Could not extract video info from link');
                    return;
                }
                
                submissionId = match[1];
                videoUid = decodeURIComponent(videoMatch[1]);
            } else {
                // Try to get info from existing player
                var $existingPlayer = $existingPlayers.first();
                submissionId = $existingPlayer.attr('id') ? $existingPlayer.attr('id').match(/\d+/)[0] : Date.now();
                videoUid = $existingPlayer.data('video-uid') || '';
                
                if (!videoUid) {
                    console.error('Cloudflare Stream: Could not extract video UID from existing player');
                    return;
                }
            }
            
            // Create player container
            var containerId = 'cloudflarestream-player-' + submissionId + '-' + Date.now();
            
            // Create two-column layout structure
            this.createTwoColumnLayout($readyLink.length > 0 ? $readyLink : $existingPlayers.first(), containerId, videoUid, submissionId);
        },
        
        createTwoColumnLayout: function($videoElement, containerId, videoUid, submissionId) {
            console.log('Cloudflare Stream: Creating two-column layout...');
            
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
                console.warn('Cloudflare Stream: Could not find grading container, using fallback');
                // Fallback to simple replacement
                this.createSimplePlayer($videoElement, containerId, videoUid, submissionId);
                return;
            }
            
            // Remove any existing video players to prevent duplication
            $gradingContainer.find('.cloudflarestream-grading-view, .cloudflarestream-player-container').remove();
            
            // Store original grading content (excluding video elements) - MOVE not clone to preserve functionality
            var $gradingContent = $gradingContainer.children().not('.cloudflarestream-grading-view, .cloudflarestream-player-container, .cloudflarestream-two-column-layout').detach();
            
            // Create two-column layout HTML
            var twoColumnHtml = 
                '<div class="cloudflarestream-two-column-layout">' +
                    '<div class="cloudflarestream-left-column">' +
                        '<div class="cloudflarestream-player-wrapper">' +
                            '<div class="cloudflarestream-player-container" id="' + containerId + '">' +
                                '<div class="cloudflarestream-loading">' +
                                    '<div class="spinner-border text-light" role="status">' +
                                        '<span class="sr-only">Loading video...</span>' +
                                    '</div>' +
                                    '<p>Loading video player...</p>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="cloudflarestream-right-column" id="cloudflarestream-grading-panel">' +
                        '<!-- Grading content will be moved here -->' +
                    '</div>' +
                '</div>';
            
            // Replace the grading container content
            $gradingContainer.html(twoColumnHtml);
            
            // Move grading content to right column
            var $rightColumn = $gradingContainer.find('#cloudflarestream-grading-panel');
            $rightColumn.append($gradingContent);
            
            // Initialize the player
            require(['assignsubmission_cloudflarestream/player'], function(Player) {
                console.log('Cloudflare Stream: Initializing player with:', {
                    videoUid: videoUid,
                    submissionId: submissionId,
                    containerId: containerId
                });
                Player.init(videoUid, submissionId, containerId);
            });
        },
        
        createSimplePlayer: function($readyLink, containerId, videoUid, submissionId) {
            console.log('Cloudflare Stream: Creating simple player (fallback)...');
            
            var playerHtml = '<div class="cloudflarestream-grading-view">' +
                '<div class="cloudflarestream-player-wrapper">' +
                '<div class="cloudflarestream-player-container" id="' + containerId + '">' +
                '<div class="cloudflarestream-loading">' +
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
            require(['assignsubmission_cloudflarestream/player'], function(Player) {
                console.log('Cloudflare Stream: Initializing player with:', {
                    videoUid: videoUid,
                    submissionId: submissionId,
                    containerId: containerId
                });
                Player.init(videoUid, submissionId, containerId);
            });
        }
    };
});
