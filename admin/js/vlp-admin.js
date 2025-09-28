/**
 * Video Library Protect - Admin JavaScript
 * 
 * Handles all admin panel interactions
 */

(function($) {
    'use strict';

    // Global namespace
    window.VLP_Admin = window.VLP_Admin || {};

    // Videos Manager Class
    VLP_Admin.VideosManager = class {
        constructor() {
             openMediaUploader($btn) {
            this.currentTarget = $('#' + $btn.data('target'));
            const mediaType = $btn.data('type') || 'video'; // Default to video

            // Ensure the media uploader is initialized
            if (!this.mediaUploader) {
                this.initMediaUploader();
            }
            
            // Set the library type (image or video)
            if (this.mediaUploader.options.library) {
                this.mediaUploader.options.library.type = mediaType;
            } else {
                this.mediaUploader.options.library = { type: mediaType };
            }
            
            this.mediaUploader.open();
        },.currentPage = 1;
            this.perPage = 20;
            this.filters = {};
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadVideos();
        }

        bindEvents() {
            // Filter events
            $('#vlp-apply-filters').on('click', () => this.applyFilters());
            
            // Search on enter
            $('#vlp-search-filter').on('keypress', (e) => {
                if (e.which === 13) {
                    this.applyFilters();
                }
            });

            // Delete video events
            $(document).on('click', '.vlp-delete-video-btn', (e) => {
                this.deleteVideo($(e.target).data('video-id'));
            });

            // Pagination events
            $(document).on('click', '#vlp-pagination .page-numbers', (e) => {
                e.preventDefault();
                const page = $(e.target).data('page');
                if (page) {
                    this.currentPage = page;
                    this.loadVideos();
                }
            });
        }

        applyFilters() {
            this.filters = {
                search: $('#vlp-search-filter').val(),
                status: $('#vlp-status-filter').val(),
                protection_level: $('#vlp-protection-filter').val()
            };
            
            this.currentPage = 1;
            this.loadVideos();
        }

        loadVideos() {
            console.log('loadVideos called');
            const container = $('#vlp-videos-container');
            container.html('<div class="vlp-loading">Chargement des vidéos...</div>');

            const data = {
                action: 'vlp_get_videos',
                nonce: vlp_admin.nonce,
                per_page: this.perPage,
                offset: (this.currentPage - 1) * this.perPage,
                ...this.filters
            };

            console.log('AJAX data:', data);
            console.log('AJAX URL:', vlp_admin.ajax_url);

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        container.html(response.data.html);
                        this.updatePagination(response.data.total);
                        console.log('Videos loaded successfully, total:', response.data.total);
                    } else {
                        console.error('AJAX error:', response.data.message);
                        this.showNotice('error', response.data.message || 'Erreur lors du chargement');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('AJAX failed:', {xhr, status, error});
                    this.showNotice('error', 'Erreur de connexion');
                });
        }

        deleteVideo(videoId) {
            if (!confirm(vlp_admin.strings.confirm_delete)) {
                return;
            }

            const data = {
                action: 'vlp_delete_video',
                nonce: vlp_admin.nonce,
                video_id: videoId
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        this.loadVideos(); // Reload list
                    } else {
                        this.showNotice('error', response.data.message);
                    }
                })
                .fail(() => {
                    this.showNotice('error', 'Erreur de connexion');
                });
        }

        updatePagination(total) {
            const totalPages = Math.ceil(total / this.perPage);
            let paginationHtml = '';

            if (totalPages > 1) {
                // Previous
                if (this.currentPage > 1) {
                    paginationHtml += `<a href="#" class="page-numbers" data-page="${this.currentPage - 1}">‹ Précédent</a>`;
                }

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    if (i === this.currentPage) {
                        paginationHtml += `<span class="page-numbers current">${i}</span>`;
                    } else if (i <= 3 || i >= totalPages - 2 || Math.abs(i - this.currentPage) <= 2) {
                        paginationHtml += `<a href="#" class="page-numbers" data-page="${i}">${i}</a>`;
                    } else if (i === 4 && this.currentPage > 6) {
                        paginationHtml += `<span class="page-numbers">…</span>`;
                    } else if (i === totalPages - 3 && this.currentPage < totalPages - 5) {
                        paginationHtml += `<span class="page-numbers">…</span>`;
                    }
                }

                // Next
                if (this.currentPage < totalPages) {
                    paginationHtml += `<a href="#" class="page-numbers" data-page="${this.currentPage + 1}">Suivant ›</a>`;
                }
            }

            $('#vlp-pagination').html(paginationHtml);
        }

        showNotice(type, message) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible vlp-notice vlp-notice-${type}">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Masquer cette notice.</span>
                    </button>
                </div>
            `);

            $('.vlp-admin-page h1').after(notice);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);

            // Manual dismiss
            notice.on('click', '.notice-dismiss', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
    };

    // Video Editor Class
    VLP_Admin.VideoEditor = class {
        constructor() {
            this.uploading = false;
            this.init();
        }

        init() {
            this.bindEvents();
            this.initConditionalSections();
            this.initMediaUploader();
        }

        bindEvents() {
            // Form submission - use document delegation to ensure it works
            $(document).on('submit', '#vlp-category-form', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Form submission intercepted');
                this.saveCategory();
                return false;
            });

            // Protection level change
            $(document).on('change', '#category_protection', () => {
                console.log('Protection level changed');
                this.toggleProtectionSection();
            });

            // Add/Remove code buttons
            $(document).on('click', '.vlp-add-code', () => this.addCodeInput());
            $(document).on('click', '.vlp-remove-code', (e) => this.removeCodeInput($(e.target)));

            // Upload buttons
            $(document).on('click', '.vlp-upload-btn', (e) => {
                e.preventDefault();
                this.openMediaUploader($(e.target));
            });

            $(document).on('click', '.vlp-bunny-upload-btn', (e) => {
                e.preventDefault();
                this.openBunnyUploader($(e.target));
            });

            $(document).on('click', '.vlp-generate-preview-btn', (e) => {
                e.preventDefault();
                this.generatePreview();
            });

            // Thumbnail preview
            $('#thumbnail_url').on('change', () => {
                this.updateThumbnailPreview();
            });

            // Delete video
            $('.vlp-delete-video-btn').on('click', (e) => {
                this.deleteVideo($(e.target).data('video-id'));
            });
        }

        initConditionalSections() {
            this.toggleProtectionSections();
        }

        toggleProtectionSections() {
            const protectionLevel = $('#protection_level').val();
            
            // Hide all conditional sections
            $('.vlp-conditional-section').hide();
            
            // Show relevant sections
            if (protectionLevel === 'gift_code') {
                $('#gift_codes_section').show();
            }
        }

        addCodeInput() {
            const container = $('#required_codes_container');
            const newRow = $(`
                <div class="vlp-code-input-row">
                    <input type="text" name="required_codes[]" value="" placeholder="ex: NOEL2024, PROMO-HIVER" class="vlp-code-input">
                    <button type="button" class="button vlp-remove-code">❌</button>
                </div>
            `);
            
            container.append(newRow);
            newRow.addClass('vlp-fade-in');
        }

        removeCodeInput($btn) {
            $btn.closest('.vlp-code-input-row').fadeOut(() => {
                $btn.closest('.vlp-code-input-row').remove();
            });
        }

        initMediaUploader() {
            this.mediaUploader = wp.media({
                title: 'Choisir un média',
                button: {
                    text: 'Utiliser ce média'
                },
                multiple: false
            });

            this.mediaUploader.on('select', () => {
                const attachment = this.mediaUploader.state().get('selection').first().toJSON();
                
                if (this.currentTarget) {
                    this.currentTarget.val(attachment.url);
                    
                    // Update thumbnail preview if it's the thumbnail field
                    if (this.currentTarget.attr('id') === 'thumbnail_url') {
                        this.updateThumbnailPreview();
                    }
                }
            });
        }

        openMediaUploader($btn) {
            this.currentTarget = $('#' + $btn.data('target'));
            
            if ($btn.data('type') === 'image') {
                this.mediaUploader.options.set('library', { type: 'image' });
            } else {
                this.mediaUploader.options.set('library', { type: 'video' });
            }
            
            this.mediaUploader.open();
        }

        openBunnyUploader($btn) {
            const target = $('#' + $btn.data('target'));
            const fileInput = $('<input type="file" accept="video/*" style="display: none;">');
            
            $('body').append(fileInput);
            
            fileInput.on('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.uploadToBunny(file, target, $btn);
                }
                fileInput.remove();
            });
            
            fileInput.trigger('click');
        }

        uploadToBunny(file, $target, $btn) {
            if (this.uploading) {
                this.showNotice('warning', 'Un téléchargement est déjà en cours...');
                return;
            }

            this.uploading = true;
            const originalText = $btn.text();
            
            // Create progress elements
            const progressContainer = $(`
                <div class="vlp-upload-progress">
                    <div class="vlp-upload-progress-bar"></div>
                </div>
                <div class="vlp-upload-status">Préparation du téléchargement...</div>
            `);
            
            $target.after(progressContainer);
            $btn.prop('disabled', true).text('📤 Téléchargement...');

            // Simulate Bunny.net upload (replace with actual API call)
            const formData = new FormData();
            formData.append('video', file);
            formData.append('action', 'vlp_bunny_upload');
            formData.append('nonce', vlp_admin.nonce);

            const xhr = $.ajaxSettings.xhr();
            
            // Track upload progress
            if (xhr.upload) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $('.vlp-upload-progress-bar').css('width', percentComplete + '%');
                        $('.vlp-upload-status').text(`Téléchargement: ${Math.round(percentComplete)}%`);
                    }
                });
            }

            $.ajax({
                url: vlp_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: () => xhr
            })
            .done((response) => {
                if (response.success) {
                    $target.val(response.data.video_url || response.data.guid);
                    $('.vlp-upload-status').text('✅ Téléchargement terminé!').css('color', 'green');
                    this.showNotice('success', 'Vidéo téléchargée avec succès sur Bunny.net');
                } else {
                    $('.vlp-upload-status').text('❌ Erreur de téléchargement').css('color', 'red');
                    this.showNotice('error', response.data.message || 'Erreur lors du téléchargement');
                }
            })
            .fail(() => {
                $('.vlp-upload-status').text('❌ Erreur de connexion').css('color', 'red');
                this.showNotice('error', 'Erreur de connexion lors du téléchargement');
            })
            .always(() => {
                this.uploading = false;
                $btn.prop('disabled', false).text(originalText);
                
                // Remove progress elements after delay
                setTimeout(() => {
                    progressContainer.fadeOut(() => progressContainer.remove());
                }, 3000);
            });
        }

        generatePreview() {
            const fullVideoUrl = $('#full_video_url').val();
            
            if (!fullVideoUrl) {
                this.showNotice('warning', 'Veuillez d\'abord sélectionner une vidéo complète');
                return;
            }

            const $btn = $('.vlp-generate-preview-btn');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('✂️ Génération...');

            const data = {
                action: 'vlp_generate_preview',
                nonce: vlp_admin.nonce,
                video_url: fullVideoUrl,
                duration: 30 // Default preview duration
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        $('#preview_video_url').val(response.data.preview_url);
                        this.showNotice('success', 'Aperçu généré avec succès');
                    } else {
                        this.showNotice('error', response.data.message || 'Erreur lors de la génération');
                    }
                })
                .fail(() => {
                    this.showNotice('error', 'Erreur de connexion');
                })
                .always(() => {
                    $btn.prop('disabled', false).text(originalText);
                });
        }

        updateThumbnailPreview() {
            const thumbnailUrl = $('#thumbnail_url').val();
            const preview = $('#thumbnail_preview');
            
            if (thumbnailUrl) {
                const img = $(`<img src="${thumbnailUrl}" alt="Thumbnail">`);
                
                img.on('load', function() {
                    preview.html(img).addClass('vlp-fade-in');
                });
                
                img.on('error', function() {
                    preview.html('<p style="color: red;">❌ Impossible de charger l\'image</p>');
                });
            } else {
                preview.empty();
            }
        }

        saveVideo() {
            if (this.uploading) {
                this.showNotice('warning', 'Veuillez attendre la fin du téléchargement...');
                return;
            }

            const $form = $('#vlp-video-form');
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Validation
            const title = $('#video_title').val().trim();
            const fullVideoUrl = $('#full_video_url').val().trim();
            
            if (!title) {
                this.showNotice('error', 'Le titre est obligatoire');
                return;
            }
            
            if (!fullVideoUrl) {
                this.showNotice('error', 'La vidéo complète est obligatoire');
                return;
            }

            $submitBtn.prop('disabled', true).text('⏳ Enregistrement...');

            const formData = new FormData($form[0]);
            formData.append('action', 'vlp_save_video');
            formData.append('nonce', vlp_admin.nonce);

            $.ajax({
                url: vlp_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    
                    // Redirect to edit page if it's a new video
                    if (response.data.video_id && !$('input[name="video_id"]').val()) {
                        setTimeout(() => {
                            window.location.href = `?page=vlp-add-video&edit=${response.data.video_id}`;
                        }, 1500);
                    }
                } else {
                    this.showNotice('error', response.data.message);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Erreur de connexion');
            })
            .always(() => {
                $submitBtn.prop('disabled', false).text(originalText);
            });
        }

        deleteVideo(videoId) {
            if (!confirm(vlp_admin.strings.confirm_delete)) {
                return;
            }

            const data = {
                action: 'vlp_delete_video',
                nonce: vlp_admin.nonce,
                video_id: videoId
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        // Redirect to videos list
                        setTimeout(() => {
                            window.location.href = '?page=vlp-videos';
                        }, 1500);
                    } else {
                        this.showNotice('error', response.data.message);
                    }
                })
                .fail(() => {
                    this.showNotice('error', 'Erreur de connexion');
                });
        }

        showNotice(type, message) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible vlp-notice vlp-notice-${type}">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Masquer cette notice.</span>
                    </button>
                </div>
            `);

            $('.vlp-admin-page h1').after(notice);

            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);

            notice.on('click', '.notice-dismiss', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
    };

    // Analytics Class
    VLP_Admin.Analytics = class {
        constructor() {
            this.charts = {};
            this.init();
        }

        init() {
            this.loadRecentActivity();
            
            // Initialize charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.initCharts();
            } else {
                // Fallback: Load Chart.js from CDN
                this.loadChartJS().then(() => {
                    this.initCharts();
                });
            }
        }

        loadChartJS() {
            return new Promise((resolve) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        }

        initCharts() {
            this.initViewsChart();
            this.initPopularVideosChart();
        }

        initViewsChart() {
            const ctx = document.getElementById('vlp-views-chart');
            if (!ctx) return;

            // Sample data - replace with actual AJAX call
            const data = {
                labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                datasets: [{
                    label: 'Vues',
                    data: [12, 19, 3, 5, 2, 3, 7],
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4
                }]
            };

            this.charts.views = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Vues par jour'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        initPopularVideosChart() {
            const ctx = document.getElementById('vlp-popular-videos-chart');
            if (!ctx) return;

            // Sample data - replace with actual AJAX call
            const data = {
                labels: ['Vidéo 1', 'Vidéo 2', 'Vidéo 3', 'Vidéo 4', 'Vidéo 5'],
                datasets: [{
                    label: 'Vues',
                    data: [300, 250, 180, 120, 90],
                    backgroundColor: [
                        '#2271b1',
                        '#72aee6',
                        '#00a32a',
                        '#dba617',
                        '#d63638'
                    ]
                }]
            };

            this.charts.popular = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Vidéos populaires'
                        }
                    }
                }
            });
        }

        loadRecentActivity() {
            const container = $('#vlp-recent-activity-list');
            
            const data = {
                action: 'vlp_get_recent_activity',
                nonce: vlp_admin.nonce
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        container.html(response.data.html);
                    } else {
                        container.html('<p>Aucune activité récente.</p>');
                    }
                })
                .fail(() => {
                    container.html('<p style="color: red;">Erreur lors du chargement de l\'activité.</p>');
                });
        }
    };

    // Utility functions
    VLP_Admin.utils = {
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        formatDuration: function(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                return `${minutes}:${secs.toString().padStart(2, '0')}`;
            }
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
        }
    };

    // Categories Manager Class
    VLP_Admin.CategoriesManager = class {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadCategories();
            this.toggleProtectionSection(); // Initialize protection section visibility
        }

        bindEvents() {
            // Form submission - use both submit and click handlers
            $(document).on('submit', '#vlp-category-form', (e) => {
                e.preventDefault();
                console.log('Form submitted via submit event');
                this.saveCategory();
                return false;
            });
            
            // Backup - also catch button clicks
            $(document).on('click', '#vlp-category-form button[type="submit"]', (e) => {
                e.preventDefault();
                console.log('Form submitted via button click');
                this.saveCategory();
                return false;
            });

            // Protection level change
            $('#category_protection').on('change', () => {
                this.toggleProtectionSection();
            });

            // Add/Remove code buttons for categories
            $(document).on('click', '.vlp-add-category-code', () => this.addCategoryCodeInput());
            $(document).on('click', '.vlp-remove-category-code', (e) => this.removeCategoryCodeInput($(e.target)));

            // Delete category events
            $(document).on('click', '.vlp-delete-category-btn', (e) => {
                this.deleteCategory($(e.target).data('category-id'));
            });
        }

        toggleProtectionSection() {
            const protectionLevel = $('#category_protection').val();
            console.log('Protection level changed to:', protectionLevel);
            
            if (protectionLevel === 'gift_code') {
                console.log('Showing gift codes section');
                $('#category_gift_codes_section').show();
            } else {
                console.log('Hiding gift codes section');
                $('#category_gift_codes_section').hide();
            }
        }

        addCategoryCodeInput() {
            const container = $('#category_required_codes_container');
            const newRow = $(`
                <div class="vlp-code-input-row">
                    <input type="text" name="required_codes[]" value="" placeholder="ex: PREMIUM2024, CATEGORY-VIP" class="vlp-code-input regular-text">
                    <button type="button" class="button vlp-remove-category-code">❌</button>
                </div>
            `);
            
            container.append(newRow);
            newRow.addClass('vlp-fade-in');
        }

        removeCategoryCodeInput($btn) {
            $btn.closest('.vlp-code-input-row').fadeOut(() => {
                $btn.closest('.vlp-code-input-row').remove();
            });
        }

        saveCategory() {
            console.log('saveCategory called');
            const $form = $('#vlp-category-form');
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Validation
            const name = $('#category_name').val().trim();
            
            if (!name) {
                this.showNotice('error', 'Le nom de la catégorie est obligatoire');
                return;
            }

            console.log('Submitting form with data:', {
                name: name,
                protection: $('#category_protection').val(),
                codes: $('input[name="required_codes[]"]').map(function() { return this.value; }).get()
            });

            $submitBtn.prop('disabled', true).text('⏳ Création...');

            const formData = new FormData($form[0]);
            formData.append('action', 'vlp_save_category');
            formData.append('nonce', vlp_admin.nonce);

            $.ajax({
                url: vlp_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice('success', response.data.message);
                    $form[0].reset();
                    this.toggleProtectionSection();
                    this.loadCategories(); // Reload list
                } else {
                    this.showNotice('error', response.data.message);
                }
            })
            .fail(() => {
                this.showNotice('error', 'Erreur de connexion');
            })
            .always(() => {
                $submitBtn.prop('disabled', false).text(originalText);
            });
        }

        deleteCategory(categoryId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
                return;
            }

            const data = {
                action: 'vlp_delete_category',
                nonce: vlp_admin.nonce,
                category_id: categoryId
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message);
                        this.loadCategories(); // Reload list
                    } else {
                        this.showNotice('error', response.data.message);
                    }
                })
                .fail(() => {
                    this.showNotice('error', 'Erreur de connexion');
                });
        }

        loadCategories() {
            const container = $('#vlp-categories-list');
            container.html('<div class="vlp-loading">Chargement des catégories...</div>');

            const data = {
                action: 'vlp_get_categories',
                nonce: vlp_admin.nonce
            };

            $.post(vlp_admin.ajax_url, data)
                .done((response) => {
                    if (response.success) {
                        container.html(response.data.html);
                    } else {
                        this.showNotice('error', response.data.message || 'Erreur lors du chargement');
                    }
                })
                .fail(() => {
                    this.showNotice('error', 'Erreur de connexion');
                });
        }

        showNotice(type, message) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible vlp-notice vlp-notice-${type}">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Masquer cette notice.</span>
                    </button>
                </div>
            `);

            $('.vlp-admin-page h1').after(notice);

            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);

            notice.on('click', '.notice-dismiss', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Auto-initialize based on page
        const currentPage = new URLSearchParams(window.location.search).get('page');
        console.log('Current WordPress page:', currentPage);
        console.log('vlp_admin object:', vlp_admin);
        
        switch(currentPage) {
            case 'vlp-videos':
                console.log('Initializing VideosManager');
                if (typeof window.vlp_videos_manager === 'undefined') {
                    window.vlp_videos_manager = new VLP_Admin.VideosManager();
                    console.log('VideosManager created');
                }
                break;
                
            case 'vlp-add-video':
                if (typeof window.vlp_video_editor === 'undefined') {
                    window.vlp_video_editor = new VLP_Admin.VideoEditor();
                }
                break;
                
            case 'vlp-categories':
                if (typeof window.vlp_categories_manager === 'undefined') {
                    window.vlp_categories_manager = new VLP_Admin.CategoriesManager();
                }
                break;
                
            case 'vlp-analytics':
                if (typeof window.vlp_analytics === 'undefined') {
                    window.vlp_analytics = new VLP_Admin.Analytics();
                }
                break;
        }

        // Global keyboard shortcuts
        $(document).on('keydown', (e) => {
            // Ctrl+S / Cmd+S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if ($('#vlp-video-form').length) {
                    $('#vlp-video-form').trigger('submit');
                }
            }
        });

        // Global AJAX error handler
        $(document).ajaxError((event, xhr, settings, thrownError) => {
            console.error('AJAX Error:', {
                url: settings.url,
                error: thrownError,
                status: xhr.status,
                response: xhr.responseText
            });
        });
    });

})(jQuery);