/**
 * Video Library Protect - Public JavaScript
 * 
 * Handles frontend interactions and video protection
 */

(function($) {
    'use strict';

    // Global namespace
    window.VLP_Public = window.VLP_Public || {};

    // Main Video Library Class
    VLP_Public.VideoLibrary = class {
        constructor() {
            this.currentFilters = {};
            this.currentPage = 1;
            this.isLoading = false;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initLazyLoading();
            
            // Load videos immediately on init
            console.log('VLP_Public.VideoLibrary initialized, loading videos...');
            this.loadVideos();
        }

        bindEvents() {
            // Filter form submission
            $(document).on('submit', '.vlp-filters form', (e) => {
                e.preventDefault();
                this.applyFilters();
            });

            // Filter button click
            $(document).on('click', '.vlp-filter-button', (e) => {
                e.preventDefault();
                this.applyFilters();
            });

            // Video card clicks
            $(document).on('click', '.vlp-video-card', (e) => {
                if (!$(e.target).closest('.vlp-video-actions').length) {
                    const videoUrl = $(e.currentTarget).data('video-url');
                    if (videoUrl) {
                        window.location.href = videoUrl;
                    }
                }
            });

            // Play button clicks
            $(document).on('click', '.vlp-play-button', (e) => {
                e.stopPropagation();
                const videoCard = $(e.target).closest('.vlp-video-card');
                const videoId = videoCard.data('video-id');
                
                if (videoId) {
                    this.playVideo(videoId);
                }
            });

            // Pagination clicks
            $(document).on('click', '.vlp-pagination a', (e) => {
                e.preventDefault();
                const page = $(e.target).data('page');
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadVideos();
                }
            });

            // Category filter clicks
            $(document).on('click', '.vlp-category-tag', (e) => {
                e.preventDefault();
                const category = $(e.target).data('category');
                if (category) {
                    this.filterByCategory(category);
                }
            });
        }

        applyFilters() {
            const form = $('.vlp-filters form');
            
            this.currentFilters = {
                search: form.find('input[name="search"]').val() || '',
                category: form.find('select[name="category"]').val() || '',
                protection: form.find('select[name="protection"]').val() || '',
                sort: form.find('select[name="sort"]').val() || 'newest'
            };
            
            this.currentPage = 1;
            this.loadVideos();
        }

        filterByCategory(category) {
            this.currentFilters.category = category;
            this.currentPage = 1;
            this.loadVideos();
            
            // Update UI
            $('.vlp-filter-select[name="category"]').val(category);
        }

        loadVideos() {
            if (this.isLoading) return;
            
            console.log('loadVideos() called');
            console.log('vlp_public object:', vlp_public);
            
            this.isLoading = true;
            this.showLoading();

            const data = {
                action: 'vlp_load_videos',
                nonce: vlp_public.nonce,
                page: this.currentPage,
                per_page: vlp_public.per_page || 12,
                ...this.currentFilters
            };

            console.log('AJAX request data:', data);

            $.post(vlp_public.ajax_url, data)
                .done((response) => {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        console.log('Videos loaded successfully, total:', response.data.total);
                        console.log('HTML content:', response.data.html);
                        
                        // Let's also log if we see vlp-protected class
                        const protectedCount = (response.data.html.match(/vlp-protected/g) || []).length;
                        console.log('Number of protected videos in HTML:', protectedCount);
                        
                        this.updateVideoGrid(response.data.html);
                        this.updatePagination(response.data.pagination);
                        this.updateResultsCount(response.data.total);
                    } else {
                        console.error('AJAX error:', response.data.message);
                        this.showError(response.data.message || 'Erreur lors du chargement des vidéos');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('AJAX failed:', {xhr, status, error});
                    this.showError('Erreur de connexion');
                })
                .always(() => {
                    this.isLoading = false;
                    this.hideLoading();
                });
        }

        playVideo(videoId) {
            // Check if video is protected
            const videoCard = $(`.vlp-video-card[data-video-id="${videoId}"]`);
            const isProtected = videoCard.hasClass('vlp-protected');
            
            if (isProtected) {
                this.showProtectionModal(videoId);
            } else {
                this.loadVideoPlayer(videoId, videoCard);
            }
        }

        loadVideoPlayer(videoId, videoCard) {
            // For non-protected videos, redirect to the video URL
            const videoUrl = videoCard.data('video-url');
            if (videoUrl) {
                console.log(`Loading video player for non-protected video ${videoId}, redirecting to: ${videoUrl}`);
                window.location.href = videoUrl;
            } else {
                console.error(`No video URL found for video ${videoId}`);
                this.showError('URL de la vidéo introuvable');
            }
        }

        showProtectionModal(videoId) {
            const modal = this.createProtectionModal(videoId);
            $('body').append(modal);
            modal.fadeIn();
        }

        createProtectionModal(videoId) {
            return $(`
                <div class="vlp-modal vlp-protection-modal" data-video-id="${videoId}">
                    <div class="vlp-modal-overlay"></div>
                    <div class="vlp-modal-content">
                        <button class="vlp-modal-close">&times;</button>
                        <div class="vlp-protection-form">
                            <div class="vlp-protection-icon">🔒</div>
                            <h3 class="vlp-protection-title">Contenu Protégé</h3>
                            <p class="vlp-protection-message">
                                Cette vidéo nécessite un code d'accès pour être visionnée.
                            </p>
                            <form class="vlp-unlock-form">
                                <div class="vlp-code-form">
                                    <input type="text" 
                                           class="vlp-code-input" 
                                           name="unlock_code" 
                                           placeholder="Entrez votre code"
                                           autocomplete="off">
                                    <button type="submit" class="vlp-unlock-button">
                                        Déverrouiller
                                    </button>
                                </div>
                                <div class="vlp-unlock-messages"></div>
                            </form>
                        </div>
                    </div>
                </div>
            `);
        }

        updateVideoGrid(html) {
            const grid = $('.vlp-videos-grid');
            grid.fadeOut(200, () => {
                grid.html(html).fadeIn(200);
                this.initLazyLoading();
            });
        }

        updatePagination(html) {
            $('.vlp-pagination').html(html);
        }

        updateResultsCount(total) {
            const resultsText = total === 1 ? 
                `${total} vidéo trouvée` : 
                `${total} vidéos trouvées`;
            
            $('.vlp-results-count').text(resultsText);
        }

        showLoading() {
            const loading = $(`
                <div class="vlp-loading-overlay">
                    <div class="vlp-loading-spinner"></div>
                    <p>Chargement des vidéos...</p>
                </div>
            `);
            
            $('.vlp-videos-grid').prepend(loading);
        }

        hideLoading() {
            $('.vlp-loading-overlay').remove();
        }

        showError(message) {
            const error = $(`
                <div class="vlp-message vlp-message-error">
                    ${message}
                </div>
            `);
            
            $('.vlp-video-library').prepend(error);
            
            setTimeout(() => {
                error.fadeOut(() => error.remove());
            }, 5000);
        }

        initLazyLoading() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const src = img.dataset.src;
                            
                            if (src) {
                                img.src = src;
                                img.removeAttribute('data-src');
                                observer.unobserve(img);
                            }
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for older browsers
                $('img[data-src]').each(function() {
                    $(this).attr('src', $(this).data('src')).removeAttr('data-src');
                });
            }
        }
    };

    // Video Protection Class
    VLP_Public.VideoProtection = class {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Unlock form submission
            $(document).on('submit', '.vlp-unlock-form', (e) => {
                e.preventDefault();
                this.handleUnlock($(e.target));
            });

            // Modal close events
            $(document).on('click', '.vlp-modal-close, .vlp-modal-overlay', (e) => {
                this.closeModal($(e.target).closest('.vlp-modal'));
            });

            // Code input formatting
            $(document).on('input', '.vlp-code-input', (e) => {
                const input = $(e.target);
                let value = input.val().toUpperCase().replace(/[^A-Z0-9-]/g, '');
                input.val(value);
            });

            // Enter key handling
            $(document).on('keydown', '.vlp-code-input', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(e.target).closest('form').trigger('submit');
                }
            });
        }

        handleUnlock(form) {
            const code = form.find('.vlp-code-input').val().trim();
            const modal = form.closest('.vlp-modal');
            const videoId = modal.data('video-id') || form.data('video-id');
            const button = form.find('.vlp-unlock-button');
            
            if (!code) {
                this.showUnlockMessage(form, 'error', 'Veuillez entrer un code');
                return;
            }

            const originalText = button.text();
            button.prop('disabled', true).text('Vérification...');

            const data = {
                action: 'vlp_unlock_video',
                nonce: vlp_public.nonce,
                video_id: videoId,
                unlock_code: code
            };

            $.post(vlp_public.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        this.showUnlockMessage(form, 'success', response.data.message);
                        
                        setTimeout(() => {
                            if (modal.length) {
                                this.closeModal(modal);
                            }
                            
                            // Redirect to video or reload page
                            if (response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            } else if (response.data.reload) {
                                window.location.reload();
                            }
                        }, 1000);
                        
                    } else {
                        this.showUnlockMessage(form, 'error', response.data.message);
                    }
                })
                .fail(() => {
                    this.showUnlockMessage(form, 'error', 'Erreur de connexion');
                })
                .always(() => {
                    button.prop('disabled', false).text(originalText);
                });
        }

        showUnlockMessage(form, type, message) {
            const messagesContainer = form.find('.vlp-unlock-messages');
            const messageElement = $(`
                <div class="vlp-message vlp-message-${type}">
                    ${message}
                </div>
            `);
            
            messagesContainer.html(messageElement);
            
            if (type === 'error') {
                setTimeout(() => {
                    messageElement.fadeOut(() => messageElement.remove());
                }, 4000);
            }
        }

        closeModal(modal) {
            modal.fadeOut(() => modal.remove());
        }
    };

    // Video Player Class
    VLP_Public.VideoPlayer = class {
        constructor(container) {
            this.container = $(container);
            this.videoId = this.container.data('video-id');
            this.isPlaying = false;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initPlayer();
        }

        bindEvents() {
            // Track video events for analytics
            this.container.on('play', () => {
                this.trackEvent('play');
                this.isPlaying = true;
            });

            this.container.on('pause', () => {
                this.trackEvent('pause');
                this.isPlaying = false;
            });

            this.container.on('ended', () => {
                this.trackEvent('complete');
                this.isPlaying = false;
            });

            // Track viewing progress
            this.container.on('timeupdate', () => {
                this.trackProgress();
            });
        }

        initPlayer() {
            // Initialize Presto Player if available
            if (typeof PrestoPlayer !== 'undefined' && this.container.hasClass('presto-player')) {
                this.initPrestoPlayer();
            } else {
                this.initHTML5Player();
            }
        }

        initPrestoPlayer() {
            // Presto Player initialization would go here
            console.log('Initializing Presto Player for video', this.videoId);
        }

        initHTML5Player() {
            const video = this.container.find('video')[0];
            
            if (video) {
                // Add custom controls if needed
                video.addEventListener('loadedmetadata', () => {
                    this.trackEvent('loaded');
                });

                video.addEventListener('error', () => {
                    this.trackEvent('error');
                    this.showError('Erreur lors du chargement de la vidéo');
                });
            }
        }

        trackEvent(eventType) {
            if (!vlp_public.analytics_enabled) return;

            const data = {
                action: 'vlp_track_video_event',
                nonce: vlp_public.nonce,
                video_id: this.videoId,
                event_type: eventType,
                timestamp: Date.now()
            };

            $.post(vlp_public.ajax_url, data);
        }

        trackProgress() {
            const video = this.container.find('video')[0];
            
            if (video && video.duration && video.currentTime) {
                const progress = (video.currentTime / video.duration) * 100;
                
                // Track at 25%, 50%, 75%, 100%
                const milestones = [25, 50, 75, 100];
                milestones.forEach(milestone => {
                    if (progress >= milestone && !this[`milestone_${milestone}`]) {
                        this[`milestone_${milestone}`] = true;
                        this.trackEvent(`progress_${milestone}`);
                    }
                });
            }
        }

        showError(message) {
            const error = $(`
                <div class="vlp-video-error">
                    <div class="vlp-error-icon">⚠️</div>
                    <div class="vlp-error-message">${message}</div>
                    <button class="vlp-retry-button">Réessayer</button>
                </div>
            `);
            
            this.container.html(error);
            
            error.find('.vlp-retry-button').on('click', () => {
                location.reload();
            });
        }
    };

    // Utility functions
    VLP_Public.utils = {
        formatDuration: function(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);

            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                return `${minutes}:${secs.toString().padStart(2, '0')}`;
            }
        },

        formatViews: function(views) {
            if (views >= 1000000) {
                return (views / 1000000).toFixed(1) + 'M';
            } else if (views >= 1000) {
                return (views / 1000).toFixed(1) + 'K';
            }
            return views.toString();
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Initialize video library
        if ($('.vlp-video-library').length) {
            window.vlp_video_library = new VLP_Public.VideoLibrary();
        }

        // Initialize video protection
        window.vlp_video_protection = new VLP_Public.VideoProtection();

        // Initialize video players
        $('.vlp-video-player-container').each(function() {
            new VLP_Public.VideoPlayer(this);
        });

        // Global keyboard shortcuts
        $(document).on('keydown', (e) => {
            // Escape to close modals
            if (e.key === 'Escape') {
                $('.vlp-modal').fadeOut(() => $('.vlp-modal').remove());
            }
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.vlp_filters) {
                if (window.vlp_video_library) {
                    window.vlp_video_library.currentFilters = e.state.vlp_filters;
                    window.vlp_video_library.loadVideos();
                }
            }
        });

        // Auto-refresh protection status
        if (vlp_public.auto_refresh && $('.vlp-protection-form').length) {
            setInterval(() => {
                // Check if user has gained access through another tab
                $.post(vlp_public.ajax_url, {
                    action: 'vlp_check_access_status',
                    nonce: vlp_public.nonce,
                    video_id: $('.vlp-protection-form').data('video-id')
                }).done((response) => {
                    if (response.success && response.data.has_access) {
                        location.reload();
                    }
                });
            }, 30000); // Check every 30 seconds
        }
    });

    // Handle AJAX errors globally
    $(document).ajaxError((event, xhr, settings, thrownError) => {
        if (xhr.status === 403) {
            // Session expired
            const message = $(`
                <div class="vlp-message vlp-message-warning">
                    Votre session a expiré. Veuillez recharger la page.
                    <button onclick="location.reload()" style="margin-left: 10px;">Recharger</button>
                </div>
            `);
            
            $('body').prepend(message);
        }
    });

})(jQuery);

// Add modal CSS if not already included
if (!document.getElementById('vlp-modal-styles')) {
    const modalCSS = `
        <style id="vlp-modal-styles">
        .vlp-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: none;
        }
        
        .vlp-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
        }
        
        .vlp-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            max-height: 90%;
            overflow-y: auto;
        }
        
        .vlp-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
            z-index: 1;
        }
        
        .vlp-modal-close:hover {
            color: #1d2327;
        }
        
        .vlp-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .vlp-video-error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .vlp-error-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .vlp-error-message {
            margin-bottom: 20px;
            color: #646970;
            font-size: 16px;
        }
        
        .vlp-retry-button {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .vlp-retry-button:hover {
            background: #005a87;
        }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', modalCSS);
}