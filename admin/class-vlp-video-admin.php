<?php
/**
 * Video Admin Class
 *
 * Handles video management in WordPress admin
 *
 * @package VideoLibraryProtect
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class VLP_Video_Admin {

    /**
     * Instance of this class
     *
     * @var VLP_Video_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vlp_save_video', array($this, 'ajax_save_video'));
        add_action('wp_ajax_vlp_delete_video', array($this, 'ajax_delete_video'));
        add_action('wp_ajax_vlp_get_videos', array($this, 'ajax_get_videos'));
        add_action('wp_ajax_vlp_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_vlp_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_vlp_get_categories', array($this, 'ajax_get_categories'));
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            __('Bibliothèque Vidéo', 'video-library-protect'),
            __('Vidéos Protégées', 'video-library-protect'),
            'manage_options',
            'vlp-videos',
            array($this, 'videos_page'),
            'dashicons-video-alt3',
            30
        );

        // Submenu pages
        add_submenu_page(
            'vlp-videos',
            __('Toutes les Vidéos', 'video-library-protect'),
            __('Toutes les Vidéos', 'video-library-protect'),
            'manage_options',
            'vlp-videos',
            array($this, 'videos_page')
        );

        add_submenu_page(
            'vlp-videos',
            __('Ajouter Vidéo', 'video-library-protect'),
            __('Ajouter Vidéo', 'video-library-protect'),
            'manage_options',
            'vlp-add-video',
            array($this, 'add_video_page')
        );

        add_submenu_page(
            'vlp-videos',
            __('Catégories', 'video-library-protect'),
            __('Catégories', 'video-library-protect'),
            'manage_options',
            'vlp-categories',
            array($this, 'categories_page')
        );

        add_submenu_page(
            'vlp-videos',
            __('Réglages', 'video-library-protect'),
            __('Réglages', 'video-library-protect'),
            'manage_options',
            'vlp-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'vlp-videos',
            __('Analytics', 'video-library-protect'),
            __('Analytics', 'video-library-protect'),
            'manage_options',
            'vlp-analytics',
            array($this, 'analytics_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'vlp-') === false) {
            return;
        }

        wp_enqueue_script('vlp-admin', VLP_PLUGIN_URL . 'admin/js/vlp-admin.js', array('jquery'), VLP_VERSION, true);
        wp_enqueue_style('vlp-admin', VLP_PLUGIN_URL . 'admin/css/vlp-admin.css', array(), VLP_VERSION);

        // Localize script
        wp_localize_script('vlp-admin', 'vlp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vlp_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Êtes-vous sûr de vouloir supprimer cette vidéo ?', 'video-library-protect'),
                'uploading' => __('Téléchargement en cours...', 'video-library-protect'),
                'processing' => __('Traitement en cours...', 'video-library-protect'),
                'error' => __('Une erreur est survenue.', 'video-library-protect'),
                'success' => __('Opération réussie.', 'video-library-protect')
            )
        ));
    }

    /**
     * Videos management page
     */
    public function videos_page() {
        ?>
        <div class="wrap vlp-admin-page">
            <h1 class="wp-heading-inline">
                <?php _e('Bibliothèque Vidéo Protégée', 'video-library-protect'); ?>
            </h1>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=vlp-add-video')); ?>" class="page-title-action">
                <?php _e('Ajouter une vidéo', 'video-library-protect'); ?>
            </a>
            
            <hr class="wp-header-end">

            <!-- Filters -->
            <div class="vlp-filters">
                <div class="vlp-filter-group">
                    <select id="vlp-status-filter" class="vlp-filter">
                        <option value=""><?php _e('Tous les statuts', 'video-library-protect'); ?></option>
                        <option value="published"><?php _e('Publié', 'video-library-protect'); ?></option>
                        <option value="draft"><?php _e('Brouillon', 'video-library-protect'); ?></option>
                    </select>
                </div>

                <div class="vlp-filter-group">
                    <select id="vlp-protection-filter" class="vlp-filter">
                        <option value=""><?php _e('Tous les niveaux de protection', 'video-library-protect'); ?></option>
                        <option value="free"><?php _e('Gratuit', 'video-library-protect'); ?></option>
                        <option value="gift_code"><?php _e('Code cadeau', 'video-library-protect'); ?></option>
                        <option value="category"><?php _e('Protection par catégorie', 'video-library-protect'); ?></option>
                    </select>
                </div>

                <div class="vlp-filter-group">
                    <input type="text" id="vlp-search-filter" class="vlp-filter" placeholder="<?php _e('Rechercher...', 'video-library-protect'); ?>">
                </div>

                <button type="button" id="vlp-apply-filters" class="button">
                    <?php _e('Filtrer', 'video-library-protect'); ?>
                </button>
            </div>

            <!-- Videos List -->
            <div id="vlp-videos-container">
                <div class="vlp-loading">
                    <?php _e('Chargement des vidéos...', 'video-library-protect'); ?>
                </div>
            </div>

            <!-- Pagination -->
            <div id="vlp-pagination"></div>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VLP_Admin !== 'undefined') {
                window.vlp_videos_manager = new VLP_Admin.VideosManager();
            }
        });
        </script>
        <?php
    }

    /**
     * Add video page
     */
    public function add_video_page() {
        $video_id = intval($_GET['edit'] ?? 0);
        $video = null;
        
        if ($video_id) {
            $video_manager = VLP_Video_Manager::get_instance();
            $video = $video_manager->get_video($video_id);
        }

        ?>
        <div class="wrap vlp-admin-page">
            <h1>
                <?php echo $video ? __('Modifier la vidéo', 'video-library-protect') : __('Ajouter une vidéo', 'video-library-protect'); ?>
            </h1>

            <form id="vlp-video-form" class="vlp-video-form">
                <?php wp_nonce_field('vlp_save_video', 'vlp_video_nonce'); ?>
                
                <?php if ($video): ?>
                    <input type="hidden" name="video_id" value="<?php echo esc_attr($video->id); ?>">
                <?php endif; ?>

                <div class="vlp-form-row vlp-form-row-full">
                    <div class="vlp-main-content">

                        <!-- Title -->
                        <div class="vlp-form-group">
                            <label for="video_title"><?php _e('Titre de la vidéo', 'video-library-protect'); ?> *</label>
                            <input type="text" 
                                   id="video_title" 
                                   name="video_title" 
                                   value="<?php echo $video ? esc_attr($video->title) : ''; ?>"
                                   required
                                   class="vlp-input-full">
                        </div>

                        <!-- Description -->
                        <div class="vlp-form-group">
                            <label for="video_description"><?php _e('Description', 'video-library-protect'); ?></label>
                            <?php
                            $content = $video ? $video->description : '';
                            wp_editor($content, 'video_description', array(
                                'textarea_name' => 'video_description',
                                'textarea_rows' => 10,
                                'editor_class' => 'vlp-editor'
                            ));
                            ?>
                        </div>

                        <!-- Video Files -->
                        <div class="vlp-form-section">
                            <h3><?php _e('Fichiers vidéo', 'video-library-protect'); ?></h3>

                            <!-- Full Video -->
                            <div class="vlp-form-group">
                                <label for="full_video_url"><?php _e('Vidéo complète', 'video-library-protect'); ?> *</label>
                                <div class="vlp-media-upload">
                                    <input type="text" 
                                           id="full_video_url" 
                                           name="full_video_url" 
                                           value="<?php echo $video ? esc_attr($video->full_video_url ?: $video->bunny_full_guid) : ''; ?>"
                                           placeholder="<?php _e('URL de la vidéo ou GUID Bunny Stream', 'video-library-protect'); ?>"
                                           class="vlp-input-media">
                                    
                                    <button type="button" class="button vlp-upload-btn" data-target="full_video_url">
                                        <?php _e('Télécharger', 'video-library-protect'); ?>
                                    </button>
                                    
                                    <button type="button" class="button vlp-bunny-upload-btn" data-target="full_video_url">
                                        🐰 <?php _e('Upload Bunny', 'video-library-protect'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Preview Video -->
                            <div class="vlp-form-group">
                                <label for="preview_video_url"><?php _e('Vidéo d\'aperçu (optionnel)', 'video-library-protect'); ?></label>
                                <div class="vlp-media-upload">
                                    <input type="text" 
                                           id="preview_video_url" 
                                           name="preview_video_url" 
                                           value="<?php echo $video ? esc_attr($video->preview_video_url ?: $video->bunny_preview_guid) : ''; ?>"
                                           placeholder="<?php _e('URL de l\'aperçu ou GUID Bunny Stream', 'video-library-protect'); ?>"
                                           class="vlp-input-media">
                                    
                                    <button type="button" class="button vlp-upload-btn" data-target="preview_video_url">
                                        <?php _e('Télécharger', 'video-library-protect'); ?>
                                    </button>
                                    
                                    <button type="button" class="button vlp-generate-preview-btn">
                                        ✂️ <?php _e('Générer aperçu', 'video-library-protect'); ?>
                                    </button>
                                </div>
                                
                                <p class="description">
                                    <?php _e('Si non spécifiée, un aperçu sera généré automatiquement à partir de la vidéo complète.', 'video-library-protect'); ?>
                                </p>
                            </div>

                            <!-- Thumbnail -->
                            <div class="vlp-form-group">
                                <label for="thumbnail_url"><?php _e('Image de présentation', 'video-library-protect'); ?></label>
                                <div class="vlp-media-upload">
                                    <input type="url" 
                                           id="thumbnail_url" 
                                           name="thumbnail_url" 
                                           value="<?php echo $video ? esc_url($video->thumbnail_url) : ''; ?>"
                                           placeholder="<?php _e('URL de l\'image de présentation', 'video-library-protect'); ?>"
                                           class="vlp-input-media">
                                    
                                    <button type="button" class="button vlp-media-btn" data-target="thumbnail_url" data-type="image">
                                        <?php _e('Choisir image', 'video-library-protect'); ?>
                                    </button>
                                </div>

                                <div id="thumbnail_preview" class="vlp-thumbnail-preview">
                                    <?php if ($video && !empty($video->thumbnail_url)): ?>
                                        <img src="<?php echo esc_url($video->thumbnail_url); ?>" alt="Thumbnail">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="vlp-sidebar">

                        <!-- Status -->
                        <div class="vlp-metabox">
                            <h3><?php _e('Statut', 'video-library-protect'); ?></h3>
                            
                            <div class="vlp-form-group">
                                <label for="video_status"><?php _e('Statut de publication', 'video-library-protect'); ?></label>
                                <select id="video_status" name="video_status" class="vlp-select">
                                    <option value="published" <?php selected($video ? $video->status : 'published', 'published'); ?>>
                                        <?php _e('Publié', 'video-library-protect'); ?>
                                    </option>
                                    <option value="draft" <?php selected($video ? $video->status : '', 'draft'); ?>>
                                        <?php _e('Brouillon', 'video-library-protect'); ?>
                                    </option>
                                </select>
                            </div>

                            <div class="vlp-form-actions">
                                <button type="submit" class="button-primary">
                                    <?php echo $video ? __('Mettre à jour', 'video-library-protect') : __('Publier', 'video-library-protect'); ?>
                                </button>
                                
                                <?php if ($video): ?>
                                <button type="button" class="button vlp-delete-video-btn" data-video-id="<?php echo esc_attr($video->id); ?>">
                                    <?php _e('Supprimer', 'video-library-protect'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Protection -->
                        <div class="vlp-metabox">
                            <h3><?php _e('Protection', 'video-library-protect'); ?></h3>
                            
                            <div class="vlp-form-group">
                                <label for="protection_level"><?php _e('Niveau de protection', 'video-library-protect'); ?></label>
                                <select id="protection_level" name="protection_level" class="vlp-select">
                                    <option value="free" <?php selected($video ? $video->protection_level : 'free', 'free'); ?>>
                                        <?php _e('Gratuit (aucune protection)', 'video-library-protect'); ?>
                                    </option>
                                    <option value="gift_code" <?php selected($video ? $video->protection_level : '', 'gift_code'); ?>>
                                        <?php _e('Code cadeau requis', 'video-library-protect'); ?>
                                    </option>
                                    <option value="category" <?php selected($video ? $video->protection_level : '', 'category'); ?>>
                                        <?php _e('Protection par catégorie', 'video-library-protect'); ?>
                                    </option>
                                </select>
                            </div>

                            <!-- Gift Codes -->
                            <div id="gift_codes_section" class="vlp-conditional-section" style="display: none;">
                                <label><?php _e('Codes cadeaux requis', 'video-library-protect'); ?></label>
                                <div id="required_codes_container">
                                    <?php 
                                    $required_codes = array();
                                    if ($video && !empty($video->protection_data)) {
                                        $protection_data = maybe_unserialize($video->protection_data);
                                        $required_codes = $protection_data['required_codes'] ?? array();
                                    }
                                    
                                    if (empty($required_codes)) {
                                        $required_codes = array('');
                                    }
                                    
                                    foreach ($required_codes as $index => $code): ?>
                                    <div class="vlp-code-input-row">
                                        <input type="text" 
                                               name="required_codes[]" 
                                               value="<?php echo esc_attr($code); ?>"
                                               placeholder="<?php _e('ex: NOEL2024, PROMO-HIVER', 'video-library-protect'); ?>"
                                               class="vlp-code-input">
                                        
                                        <?php if ($index > 0): ?>
                                        <button type="button" class="button vlp-remove-code">❌</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="button vlp-add-code">
                                    <?php _e('+ Ajouter code', 'video-library-protect'); ?>
                                </button>
                                
                                <p class="description">
                                    <?php _e('Un seul des codes listés est nécessaire pour accéder à la vidéo.', 'video-library-protect'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="vlp-metabox">
                            <h3><?php _e('Catégories', 'video-library-protect'); ?></h3>
                            
                            <div class="vlp-categories-list">
                                <?php echo $this->render_categories_checkboxes($video); ?>
                            </div>
                        </div>

                        <!-- Video Info -->
                        <?php if ($video): ?>
                        <div class="vlp-metabox">
                            <h3><?php _e('Informations', 'video-library-protect'); ?></h3>
                            
                            <div class="vlp-video-stats">
                                <p><strong><?php _e('Vues:', 'video-library-protect'); ?></strong> <?php echo number_format($video->views_count); ?></p>
                                
                                <?php if ($video->duration > 0): ?>
                                <p><strong><?php _e('Durée:', 'video-library-protect'); ?></strong> <?php echo $this->format_duration($video->duration); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($video->file_size > 0): ?>
                                <p><strong><?php _e('Taille:', 'video-library-protect'); ?></strong> <?php echo size_format($video->file_size); ?></p>
                                <?php endif; ?>
                                
                                <p><strong><?php _e('Créé:', 'video-library-protect'); ?></strong> <?php echo date_i18n('d/m/Y H:i', strtotime($video->created_at)); ?></p>
                                
                                <p><strong><?php _e('Modifié:', 'video-library-protect'); ?></strong> <?php echo date_i18n('d/m/Y H:i', strtotime($video->updated_at)); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </form>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VLP_Admin !== 'undefined') {
                window.vlp_video_editor = new VLP_Admin.VideoEditor();
            }
        });
        </script>
        <?php
    }

    /**
     * Categories page
     */
    public function categories_page() {
        ?>
        <div class="wrap vlp-admin-page">
            <h1 class="wp-heading-inline">
                <?php _e('Catégories de Vidéos', 'video-library-protect'); ?>
            </h1>
            
            <hr class="wp-header-end">

            <div class="vlp-categories-admin">
                
                <div class="vlp-add-category-form">
                    <h2><?php _e('Ajouter une catégorie', 'video-library-protect'); ?></h2>
                    
                    <form id="vlp-category-form">
                        <?php wp_nonce_field('vlp_save_category', 'vlp_category_nonce'); ?>
                        
                        <div class="vlp-form-group">
                            <label for="category_name"><?php _e('Nom de la catégorie', 'video-library-protect'); ?></label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>

                        <div class="vlp-form-group">
                            <label for="category_description"><?php _e('Description', 'video-library-protect'); ?></label>
                            <textarea id="category_description" name="category_description" rows="4"></textarea>
                        </div>

                        <div class="vlp-form-group">
                            <label for="category_protection"><?php _e('Protection', 'video-library-protect'); ?></label>
                            <select id="category_protection" name="category_protection">
                                <option value="free"><?php _e('Gratuit', 'video-library-protect'); ?></option>
                                <option value="gift_code"><?php _e('Code cadeau requis', 'video-library-protect'); ?></option>
                            </select>
                        </div>

                        <!-- Gift Codes Section -->
                        <div id="category_gift_codes_section" class="vlp-form-group" style="display: none;">
                            <label><?php _e('Codes cadeaux requis', 'video-library-protect'); ?></label>
                            <div id="category_required_codes_container">
                                <div class="vlp-code-input-row">
                                    <input type="text" 
                                           name="required_codes[]" 
                                           value=""
                                           placeholder="<?php _e('ex: PREMIUM2024, CATEGORY-VIP', 'video-library-protect'); ?>"
                                           class="vlp-code-input regular-text">
                                </div>
                            </div>
                            
                            <button type="button" class="button vlp-add-category-code">
                                <?php _e('+ Ajouter un code', 'video-library-protect'); ?>
                            </button>
                            
                            <p class="description">
                                <?php _e('Un seul des codes listés est nécessaire pour accéder aux vidéos de cette catégorie.', 'video-library-protect'); ?>
                            </p>
                        </div>

                        <button type="submit" class="button-primary">
                            <?php _e('Ajouter la catégorie', 'video-library-protect'); ?>
                        </button>
                    </form>
                </div>

                <div id="vlp-categories-list">
                    <!-- Categories will be loaded via AJAX -->
                </div>

            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VLP_Admin !== 'undefined') {
                window.vlp_categories_manager = new VLP_Admin.CategoriesManager();
            }
        });
        </script>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        $settings = get_option('vlp_settings', array());
        ?>
        <div class="wrap vlp-admin-page">
            <h1><?php _e('Réglages - Bibliothèque Vidéo Protégée', 'video-library-protect'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('vlp_settings', 'vlp_settings_nonce'); ?>

                <table class="form-table">
                    
                    <!-- General Settings -->
                    <tr>
                        <th colspan="2"><h2><?php _e('Réglages Généraux', 'video-library-protect'); ?></h2></th>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Durée d\'aperçu par défaut', 'video-library-protect'); ?></th>
                        <td>
                            <input type="number" 
                                   name="preview_duration" 
                                   value="<?php echo esc_attr($settings['preview_duration'] ?? 30); ?>"
                                   min="5" 
                                   max="300"> secondes
                            <p class="description">
                                <?php _e('Durée des aperçus générés automatiquement.', 'video-library-protect'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Bunny.net Settings -->
                    <tr>
                        <th colspan="2"><h2>🐰 <?php _e('Configuration Bunny.net', 'video-library-protect'); ?></h2></th>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Activer Bunny Stream', 'video-library-protect'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="bunny_stream_enabled" 
                                   value="1" 
                                   <?php checked(!empty($settings['bunny_stream_enabled'])); ?>>
                            <?php _e('Utiliser Bunny Stream pour l\'hébergement vidéo', 'video-library-protect'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Library ID Bunny Stream', 'video-library-protect'); ?></th>
                        <td>
                            <input type="text" 
                                   name="bunny_library_id" 
                                   value="<?php echo esc_attr($settings['bunny_library_id'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Clé API Bunny', 'video-library-protect'); ?></th>
                        <td>
                            <input type="password" 
                                   name="bunny_api_key" 
                                   value="<?php echo esc_attr($settings['bunny_api_key'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Hostname CDN', 'video-library-protect'); ?></th>
                        <td>
                            <input type="text" 
                                   name="bunny_cdn_hostname" 
                                   value="<?php echo esc_attr($settings['bunny_cdn_hostname'] ?? ''); ?>"
                                   placeholder="videos.votresite.com"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Clé de sécurité (optionnel)', 'video-library-protect'); ?></th>
                        <td>
                            <input type="password" 
                                   name="bunny_security_key" 
                                   value="<?php echo esc_attr($settings['bunny_security_key'] ?? ''); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Pour les URLs sécurisées avec expiration.', 'video-library-protect'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Protection Settings -->
                    <tr>
                        <th colspan="2"><h2>🔒 <?php _e('Protection du Site', 'video-library-protect'); ?></h2></th>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Protection globale du site', 'video-library-protect'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="site_wide_protection" 
                                   value="1" 
                                   <?php checked(!empty($settings['site_wide_protection'])); ?>>
                            <?php _e('Activer la protection globale (code requis pour accéder au site)', 'video-library-protect'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Codes d\'accès au site', 'video-library-protect'); ?></th>
                        <td>
                            <?php 
                            $site_codes = $settings['site_protection_codes'] ?? array();
                            $site_codes_string = is_array($site_codes) ? implode(', ', $site_codes) : $site_codes;
                            ?>
                            <textarea name="site_protection_codes" 
                                      rows="3" 
                                      cols="50"
                                      placeholder="CODE1, CODE2, CODE3"><?php echo esc_textarea($site_codes_string); ?></textarea>
                            <p class="description">
                                <?php _e('Codes séparés par des virgules. Un seul code suffit pour accéder au site.', 'video-library-protect'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Integration Settings -->
                    <tr>
                        <th colspan="2"><h2>🔗 <?php _e('Intégrations', 'video-library-protect'); ?></h2></th>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Intégration GiftCode Protect', 'video-library-protect'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="giftcode_integration" 
                                   value="1" 
                                   <?php checked(!empty($settings['giftcode_integration'])); ?>>
                            <?php _e('Utiliser le plugin GiftCode Protect pour la validation des codes', 'video-library-protect'); ?>
                            
                            <?php if (!class_exists('GiftCode_Manager')): ?>
                            <p class="description" style="color: orange;">
                                ⚠️ <?php _e('Plugin GiftCode Protect non détecté.', 'video-library-protect'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Presto Player', 'video-library-protect'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="presto_player_enabled" 
                                   value="1" 
                                   <?php checked(!empty($settings['presto_player_enabled'])); ?>>
                            <?php _e('Utiliser Presto Player comme lecteur vidéo principal', 'video-library-protect'); ?>
                            
                            <?php if (class_exists('VLP_Presto_Integration') && !VLP_Presto_Integration::plugin_available()): ?>
                            <p class="description" style="color: orange;">
                                ⚠️ <?php _e('Plugin Presto Player non détecté.', 'video-library-protect'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Analytics -->
                    <tr>
                        <th scope="row"><?php _e('Analytics', 'video-library-protect'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="enable_analytics" 
                                   value="1" 
                                   <?php checked(!empty($settings['enable_analytics'])); ?>>
                            <?php _e('Activer le suivi des vues et statistiques', 'video-library-protect'); ?>
                        </td>
                    </tr>

                </table>

                <?php submit_button(__('Enregistrer les réglages', 'video-library-protect')); ?>
            </form>

        </div>
        <?php
    }

    /**
     * Analytics page
     */
    public function analytics_page() {
        ?>
        <div class="wrap vlp-admin-page">
            <h1><?php _e('Analytics - Bibliothèque Vidéo', 'video-library-protect'); ?></h1>

            <div class="vlp-analytics-dashboard">
                
                <!-- Summary Stats -->
                <div class="vlp-stats-grid">
                    <?php echo $this->render_analytics_summary(); ?>
                </div>

                <!-- Charts -->
                <div class="vlp-charts-container">
                    <div id="vlp-views-chart" class="vlp-chart"></div>
                    <div id="vlp-popular-videos-chart" class="vlp-chart"></div>
                </div>

                <!-- Recent Activity -->
                <div class="vlp-recent-activity">
                    <h3><?php _e('Activité récente', 'video-library-protect'); ?></h3>
                    <div id="vlp-recent-activity-list">
                        <!-- Loaded via AJAX -->
                    </div>
                </div>

            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof VLP_Admin !== 'undefined') {
                window.vlp_analytics = new VLP_Admin.Analytics();
            }
        });
        </script>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['vlp_settings_nonce'], 'vlp_settings')) {
            wp_die(__('Nonce verification failed', 'video-library-protect'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'video-library-protect'));
        }

        $settings = array(
            'preview_duration' => intval($_POST['preview_duration']),
            'bunny_stream_enabled' => !empty($_POST['bunny_stream_enabled']),
            'bunny_library_id' => sanitize_text_field($_POST['bunny_library_id']),
            'bunny_api_key' => sanitize_text_field($_POST['bunny_api_key']),
            'bunny_cdn_hostname' => sanitize_text_field($_POST['bunny_cdn_hostname']),
            'bunny_security_key' => sanitize_text_field($_POST['bunny_security_key']),
            'site_wide_protection' => !empty($_POST['site_wide_protection']),
            'site_protection_codes' => array_map('trim', explode(',', $_POST['site_protection_codes'])),
            'giftcode_integration' => !empty($_POST['giftcode_integration']),
            'presto_player_enabled' => !empty($_POST['presto_player_enabled']),
            'enable_analytics' => !empty($_POST['enable_analytics'])
        );

        update_option('vlp_settings', $settings);

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Réglages enregistrés avec succès.', 'video-library-protect') . 
                 '</p></div>';
        });
    }

    /**
     * Render categories checkboxes
     */
    private function render_categories_checkboxes($video = null) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $categories = $wpdb->get_results("SELECT * FROM {$categories_table} ORDER BY name");
        
        $video_categories = array();
        if ($video) {
            $video_categories = wp_list_pluck($video->categories, 'id');
        }

        $output = '';
        foreach ($categories as $category) {
            $checked = in_array($category->id, $video_categories) ? 'checked' : '';
            $protection_icon = $category->protection_level !== 'free' ? ' 🔒' : '';
            
            $output .= sprintf(
                '<div class="vlp-category-item">
                    <input type="checkbox" name="video_categories[]" value="%d" id="cat_%d" %s>
                    <label for="cat_%d">%s%s</label>
                </div>',
                $category->id,
                $category->id,
                $checked,
                $category->id,
                esc_html($category->name),
                $protection_icon
            );
        }

        return $output ?: '<p>' . __('Aucune catégorie disponible.', 'video-library-protect') . '</p>';
    }

    /**
     * Format duration
     */
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }

    /**
     * Render analytics summary
     */
    private function render_analytics_summary() {
        global $wpdb;
        
        // Get stats
        $total_videos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vlp_videos WHERE status = 'published'");
        $total_views = $wpdb->get_var("SELECT SUM(views_count) FROM {$wpdb->prefix}vlp_videos");
        $protected_videos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vlp_videos WHERE protection_level != 'free' AND status = 'published'");
        
        $today_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vlp_analytics WHERE DATE(timestamp) = %s",
            current_time('Y-m-d')
        ));

        return sprintf('
            <div class="vlp-stat-box">
                <h3>%s</h3>
                <div class="vlp-stat-number">%s</div>
            </div>
            <div class="vlp-stat-box">
                <h3>%s</h3>
                <div class="vlp-stat-number">%s</div>
            </div>
            <div class="vlp-stat-box">
                <h3>%s</h3>
                <div class="vlp-stat-number">%s</div>
            </div>
            <div class="vlp-stat-box">
                <h3>%s</h3>
                <div class="vlp-stat-number">%s</div>
            </div>',
            __('Total Vidéos', 'video-library-protect'),
            number_format($total_videos),
            __('Total Vues', 'video-library-protect'),
            number_format($total_views),
            __('Vidéos Protégées', 'video-library-protect'),
            number_format($protected_videos),
            __('Vues Aujourd\'hui', 'video-library-protect'),
            number_format($today_views)
        );
    }

    /**
     * AJAX: Save video
     */
    public function ajax_save_video() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Process form data and save video
        $video_data = $this->process_video_form_data($_POST);

        if (is_wp_error($video_data)) {
            wp_send_json_error(array('message' => $video_data->get_error_message()));
        }

        $video_manager = VLP_Video_Manager::get_instance();

        $video_id = !empty($_POST['video_id']) ? intval($_POST['video_id']) : 0;

        if (!empty($_POST['video_id'])) {
            // Update existing video
            $result = $video_manager->update_video(intval($_POST['video_id']), $video_data);
            $message = $result ? 'Vidéo mise à jour avec succès.' : 'Erreur lors de la mise à jour.';
            $video_id = intval($_POST['video_id']);
        } else {
            // Create new video
            $result = $video_manager->create_video($video_data);
            $message = $result ? 'Vidéo créée avec succès.' : 'Erreur lors de la création.';
            if ($result) {
                $video_id = intval($result);
            }
        }

        if ($result && $video_id) {
            $category_ids = array_map('intval', $_POST['video_categories'] ?? array());
            $video_manager->set_video_categories($video_id, $category_ids);

            wp_send_json_success(array(
                'message' => __($message, 'video-library-protect'),
                'video_id' => $video_id
            ));
        } else {
            wp_send_json_error(array('message' => __($message, 'video-library-protect')));
        }
    }

    /**
     * Process video form data
     */
    private function process_video_form_data($post_data) {
        $video_data = array(
            'title'              => sanitize_text_field($post_data['video_title'] ?? ''),
            'description'        => wp_kses_post($post_data['video_description'] ?? ''),
            'full_video_url'     => '',
            'preview_video_url'  => '',
            'thumbnail_url'      => '',
            'bunny_full_guid'    => '',
            'bunny_preview_guid' => '',
            'status'             => sanitize_text_field($post_data['video_status'] ?? 'published'),
            'protection_level'   => sanitize_text_field($post_data['protection_level'] ?? 'free'),
        );

        // Full video input
        $full_input = trim($post_data['full_video_url'] ?? '');
        if ($full_input !== '') {
            if ($this->looks_like_bunny_guid($full_input)) {
                $video_data['bunny_full_guid'] = sanitize_text_field($full_input);
            } else {
                $full_url = esc_url_raw($full_input);
                if (empty($full_url)) {
                    return new WP_Error('invalid_full_video', __('Veuillez fournir une URL ou un GUID Bunny valide pour la vidéo complète.', 'video-library-protect'));
                }

                $video_data['full_video_url'] = $full_url;

                $maybe_guid = $this->extract_guid_from_url($full_url);
                if ($maybe_guid) {
                    $video_data['bunny_full_guid'] = $maybe_guid;
                }
            }
        }

        if (empty($video_data['full_video_url']) && empty($video_data['bunny_full_guid'])) {
            return new WP_Error('missing_full_video', __('Veuillez fournir une URL ou un GUID Bunny valide pour la vidéo complète.', 'video-library-protect'));
        }

        // Preview video input (optional)
        $preview_input = trim($post_data['preview_video_url'] ?? '');
        if ($preview_input !== '') {
            if ($this->looks_like_bunny_guid($preview_input)) {
                $video_data['bunny_preview_guid'] = sanitize_text_field($preview_input);
            } else {
                $preview_url = esc_url_raw($preview_input);
                if (empty($preview_url)) {
                    return new WP_Error('invalid_preview_video', __('Veuillez fournir une URL ou un GUID Bunny valide pour la vidéo d\'aperçu.', 'video-library-protect'));
                }

                $video_data['preview_video_url'] = $preview_url;

                $maybe_guid = $this->extract_guid_from_url($preview_url);
                if ($maybe_guid) {
                    $video_data['bunny_preview_guid'] = $maybe_guid;
                }
            }
        }

        // Thumbnail (optional)
        $thumb_input = trim($post_data['thumbnail_url'] ?? '');
        if ($thumb_input !== '') {
            $thumb_url = esc_url_raw($thumb_input);
            if (empty($thumb_url)) {
                return new WP_Error('invalid_thumbnail', __('Veuillez fournir une URL valide pour l\'image de présentation.', 'video-library-protect'));
            }
            $video_data['thumbnail_url'] = $thumb_url;
        }

        // Process protection data
        if ($video_data['protection_level'] === 'gift_code') {
            $required_codes = array_filter(array_map('sanitize_text_field', $post_data['required_codes'] ?? array()));
            $video_data['protection_data'] = array('required_codes' => $required_codes);
        }

        return $video_data;
    }

    /**
     * Determine if a value looks like a Bunny Stream GUID
     */
    private function looks_like_bunny_guid($value) {
        return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value);
    }

    /**
     * Attempt to extract a GUID from a Bunny Stream URL
     */
    private function extract_guid_from_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (empty($path)) {
            return '';
        }

        $segments = array_filter(explode('/', trim($path, '/')));
        foreach ($segments as $segment) {
            if ($this->looks_like_bunny_guid($segment)) {
                return sanitize_text_field($segment);
            }
        }

        return '';
    }

    /**
     * AJAX: Delete video
     */
    public function ajax_delete_video() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $video_id = intval($_POST['video_id']);
        
        $video_manager = VLP_Video_Manager::get_instance();
        $result = $video_manager->delete_video($video_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Vidéo supprimée avec succès.', 'video-library-protect')));
        } else {
            wp_send_json_error(array('message' => __('Erreur lors de la suppression.', 'video-library-protect')));
        }
    }

    /**
     * AJAX: Get videos list
     */
    public function ajax_get_videos() {
        error_log('VLP: ajax_get_videos called');
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('VLP: User lacks permissions');
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $args = array(
            'limit' => intval($_POST['per_page'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'protection_level' => sanitize_text_field($_POST['protection_level'] ?? '')
        );

        error_log('VLP: Query args: ' . print_r($args, true));

        $video_manager = VLP_Video_Manager::get_instance();
        $videos = $video_manager->get_videos($args);

        error_log('VLP: Found ' . count($videos) . ' videos');

        $html = $this->render_videos_table($videos);

        wp_send_json_success(array(
            'html' => $html,
            'total' => count($videos)
        ));
    }

    /**
     * Render videos table
     */
    private function render_videos_table($videos) {
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped vlp-videos-table">
            <thead>
                <tr>
                    <th><?php _e('Titre', 'video-library-protect'); ?></th>
                    <th><?php _e('Protection', 'video-library-protect'); ?></th>
                    <th><?php _e('Catégories', 'video-library-protect'); ?></th>
                    <th><?php _e('Vues', 'video-library-protect'); ?></th>
                    <th><?php _e('Statut', 'video-library-protect'); ?></th>
                    <th><?php _e('Date', 'video-library-protect'); ?></th>
                    <th><?php _e('Actions', 'video-library-protect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($videos)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">
                            <?php _e('Aucune vidéo trouvée.', 'video-library-protect'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($videos as $video): ?>
                    <tr data-video-id="<?php echo esc_attr($video->id); ?>">
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=vlp-add-video&edit=' . $video->id)); ?>">
                                    <?php echo esc_html($video->title); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php 
                            $protection_labels = array(
                                'free' => '🟢 ' . __('Gratuit', 'video-library-protect'),
                                'gift_code' => '🔒 ' . __('Code cadeau', 'video-library-protect'),
                                'category' => '🏷️ ' . __('Catégorie', 'video-library-protect')
                            );
                            echo $protection_labels[$video->protection_level] ?? $video->protection_level;
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($video->categories)) {
                                $category_names = wp_list_pluck($video->categories, 'name');
                                echo esc_html(implode(', ', $category_names));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo number_format($video->views_count); ?></td>
                        <td>
                            <?php if ($video->status === 'published'): ?>
                                <span class="vlp-status vlp-status-published">
                                    ✅ <?php _e('Publié', 'video-library-protect'); ?>
                                </span>
                            <?php else: ?>
                                <span class="vlp-status vlp-status-draft">
                                    📝 <?php _e('Brouillon', 'video-library-protect'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date_i18n('d/m/Y', strtotime($video->created_at)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vlp-add-video&edit=' . $video->id)); ?>" 
                               class="button button-small">
                                <?php _e('Modifier', 'video-library-protect'); ?>
                            </a>
                            
                            <button type="button" 
                                    class="button button-small vlp-delete-video-btn" 
                                    data-video-id="<?php echo esc_attr($video->id); ?>">
                                <?php _e('Supprimer', 'video-library-protect'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Save category
     */
    public function ajax_save_category() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $category_description = sanitize_textarea_field($_POST['category_description'] ?? '');
        $category_protection = sanitize_text_field($_POST['category_protection'] ?? 'free');
        $required_codes = array_filter(array_map('sanitize_text_field', $_POST['required_codes'] ?? array()));

        if (empty($category_name)) {
            wp_send_json_error(array('message' => 'Le nom de la catégorie est requis'));
        }

        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';

        $category_data = array(
            'name' => $category_name,
            'slug' => sanitize_title($category_name),
            'description' => $category_description,
            'protection_level' => $category_protection,
            'protection_data' => serialize(array('required_codes' => $required_codes)),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($categories_table, $category_data);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Catégorie créée avec succès'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la création de la catégorie'));
        }
    }

    /**
     * AJAX: Delete category
     */
    public function ajax_delete_category() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $category_id = intval($_POST['category_id']);
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $result = $wpdb->delete($categories_table, array('id' => $category_id), array('%d'));

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Catégorie supprimée avec succès'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression'));
        }
    }

    /**
     * AJAX: Get categories list
     */
    public function ajax_get_categories() {
        check_ajax_referer('vlp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        global $wpdb;
        $categories_table = $wpdb->prefix . 'vlp_categories';
        
        $categories = $wpdb->get_results(
            "SELECT *, (SELECT COUNT(*) FROM {$wpdb->prefix}vlp_video_categories vc 
                       WHERE vc.category_id = c.id) as video_count 
             FROM {$categories_table} c 
             ORDER BY created_at DESC"
        );

        $html = $this->render_categories_table($categories);

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Render categories table
     */
    private function render_categories_table($categories) {
        ob_start();
        ?>
        <h3><?php _e('Catégories existantes', 'video-library-protect'); ?></h3>
        <table class="wp-list-table widefat fixed striped vlp-categories-table">
            <thead>
                <tr>
                    <th><?php _e('Nom', 'video-library-protect'); ?></th>
                    <th><?php _e('Description', 'video-library-protect'); ?></th>
                    <th><?php _e('Protection', 'video-library-protect'); ?></th>
                    <th><?php _e('Vidéos', 'video-library-protect'); ?></th>
                    <th><?php _e('Créée', 'video-library-protect'); ?></th>
                    <th><?php _e('Actions', 'video-library-protect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">
                            <?php _e('Aucune catégorie trouvée.', 'video-library-protect'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                    <tr data-category-id="<?php echo esc_attr($category->id); ?>">
                        <td>
                            <strong><?php echo esc_html($category->name); ?></strong>
                            <br><small><?php echo esc_html($category->slug); ?></small>
                        </td>
                        <td><?php echo esc_html($category->description ?: '—'); ?></td>
                        <td>
                            <?php if ($category->protection_level === 'free'): ?>
                                🟢 <?php _e('Gratuit', 'video-library-protect'); ?>
                            <?php else: ?>
                                🔒 <?php _e('Code cadeau', 'video-library-protect'); ?>
                                <?php 
                                $protection_data = maybe_unserialize($category->protection_data);
                                if (!empty($protection_data['required_codes'])): ?>
                                    <br><small><?php echo esc_html(implode(', ', $protection_data['required_codes'])); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($category->video_count); ?></td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($category->created_at)); ?></td>
                        <td>
                            <button type="button" 
                                    class="button button-small vlp-delete-category-btn" 
                                    data-category-id="<?php echo esc_attr($category->id); ?>">
                                <?php _e('Supprimer', 'video-library-protect'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}