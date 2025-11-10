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
        processing: false,
        debounceTimer: null,
        
        init: function() {
            var self = this;
            
            // Only run on individual grading page
            if (this.isGradingPage()) {
                // Wait a bit for Moodle to finish loading, then inject
                setTimeout(function() {
                    self.injectPlayer();
                }, 500);
                
                // Listen for Moodle's grading panel updates
                self.observeGradingPanel();
            }
        },
        
        /**
         * Check if we're on the grading page
         */
        isGradingPage: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var action = urlParams.get('action');
            var rownum = urlParams.get('rownum');
            var userid = urlParams.get('userid');
            return action === 'grader' && (rownum !== null || userid !== null);
        },
        
        /**
         * Observe Moodle's grading panel for content changes
         */
        observeGradingPanel: function() {
            var self = this;
            
            // Find the grading panel that Moodle updates
            var $gradingPanel = $('[data-region="grade-panel"]');
            if ($gradingPanel.length === 0) {
                return;
            }
            
            // Store observer reference
            this.panelObserver = new MutationObserver(function(mutations) {
                // Debounce: clear existing timer
                if (self.debounceTimer) {
                    clearTimeout(self.debounceTimer);
                }
                
                // Set new timer to process after mutations settle
                self.debounceTimer = setTimeout(function() {
                    self.handleContentChange();
                }, 500); // Wait 500ms after last mutation
            });
            
            // Start observing
            this.panelObserver.observe($gradingPanel[0], {
                childList: true,
                subtree: true
            });
        },
        
        /**
         * Handle content changes in grading panel
         */
        handleContentChange: function() {
            // Prevent concurrent processing
            if (this.processing) {
                return;
            }
            
            this.processing = true;
            
            // Check current state
            var $existingLayout = $('.cloudflarestream-two-column-layout');
            var $videoLink = $('.cloudflarestream-watch-link, .cfstream-grading-link');
            
            // Count ALL video links (inside and outside layout)
            var totalVideoLinks = $videoLink.length;
            
            // Filter out links inside existing layout
            var $newVideoLink = $videoLink.filter(function() {
                return $(this).closest('.cloudflarestream-two-column-layout').length === 0;
            });
            
            // Case 1: New video found outside layout and no layout exists
            if ($newVideoLink.length > 0 && $existingLayout.length === 0) {
                this.pauseObserver();
                this.injectPlayer();
                this.resumeObserver();
            }
            // Case 2: No video links at all but layout exists (user switched to non-video submission)
            else if (totalVideoLinks === 0 && $existingLayout.length > 0) {
                this.pauseObserver();
                this.restoreMoodleLayout();
                this.resumeObserver();
            }
            
            // Reset processing flag
            var self = this;
            setTimeout(function() {
                self.processing = false;
            }, 1000);
        },
        
        /**
         * Pause the observer temporarily
         */
        pauseObserver: function() {
            if (this.panelObserver) {
                this.panelObserver.disconnect();
            }
        },
        
        /**
         * Resume the observer
         */
        resumeObserver: function() {
            var self = this;
            if (this.panelObserver) {
                setTimeout(function() {
                    var $gradingPanel = $('[data-region="grade-panel"]');
                    if ($gradingPanel.length > 0) {
                        self.panelObserver.observe($gradingPanel[0], {
                            childList: true,
                            subtree: true
                        });
                    }
                }, 1000); // Wait 1 second before resuming
            }
        },
        
        /**
         * Restore Moodle's original layout (remove two-column layout)
         */
        restoreMoodleLayout: function() {
            var $layout = $('.cloudflarestream-two-column-layout');
            if ($layout.length === 0) {
                return;
            }
            
            // Get the grading content from right column
            var $rightColumn = $layout.find('.cloudflarestream-right-column');
            var $gradingContent = $rightColumn.children();
            
            // Find the parent container
            var $container = $layout.parent();
            
            // Move grading content back to parent
            $container.append($gradingContent);
            
            // Remove the two-column layout
            $layout.remove();
        },
        
        injectPlayer: function() {
            // Check if we already injected the player
            if ($('.cloudflarestream-two-column-layout').length > 0) {
                return;
            }
            
            // Find all video players and links (support both old and new class names)
            var $readyLink = $('.cloudflarestream-watch-link, .cfstream-grading-link');
            var $existingPlayers = $('.cloudflarestream-grading-view, .cloudflarestream-player-container');
            
            if ($readyLink.length === 0 && $existingPlayers.length === 0) {
                return;
            }
            
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
                Player.init(videoUid, submissionId, containerId);
            });
        },
        
        createSimplePlayer: function($readyLink, containerId, videoUid, submissionId) {
            
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
                Player.init(videoUid, submissionId, containerId);
            });
        }
    };
});
