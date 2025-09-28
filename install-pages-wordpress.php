<?php
/**
 * Script d'Installation Automatique des Pages Video Library Protect
 * 
 * Ce script crée automatiquement toutes les pages nécessaires 
 * pour le système Video Library Protect dans WordPress.
 * 
 * UTILISATION :
 * 1. Placez ce fichier dans votre installation WordPress
 * 2. Accédez à l'URL : votre-site.com/install-pages-wordpress.php
 * 3. Les pages seront créées automatiquement
 * 4. Supprimez ce fichier après utilisation
 */

// Sécurité : Vérifier que nous sommes dans WordPress
if (!defined('ABSPATH')) {
    // Essayer de charger WordPress
    $wp_config_paths = [
        __DIR__ . '/wp-config.php',
        __DIR__ . '/../wp-config.php', 
        __DIR__ . '/../../wp-config.php',
        __DIR__ . '/../../../wp-config.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_config_paths as $config_path) {
        if (file_exists($config_path)) {
            require_once $config_path;
            require_once ABSPATH . 'wp-admin/includes/post.php';
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('❌ Erreur : Impossible de charger WordPress. Placez ce fichier dans le répertoire WordPress.');
    }
}

/**
 * Classe pour installer les pages Video Library Protect
 */
class VLP_Pages_Installer {
    
    private $pages_created = [];
    private $errors = [];
    
    /**
     * Installer toutes les pages
     */
    public function install_all_pages() {
        echo "<h1>🎬 Installation des Pages Video Library Protect</h1>";
        echo "<div style='max-width: 800px; margin: 20px auto; font-family: Arial, sans-serif;'>";
        
        // Vérifier les prérequis
        $this->check_prerequisites();
        
        // Créer les pages
        $pages = $this->get_pages_data();
        
        foreach ($pages as $page_data) {
            $this->create_page($page_data);
        }
        
        // Afficher le résumé
        $this->display_summary();
        
        echo "</div>";
    }
    
    /**
     * Vérifier les prérequis
     */
    private function check_prerequisites() {
        echo "<h2>🔍 Vérification des prérequis...</h2>";
        
        // Vérifier les permissions
        if (!current_user_can('edit_pages')) {
            $this->errors[] = "Permissions insuffisantes pour créer des pages";
        }
        
        // Vérifier si le plugin VLP est actif
        if (!class_exists('Video_Library_Protect')) {
            echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
            echo "⚠️ <strong>Attention :</strong> Le plugin Video Library Protect ne semble pas être activé.";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;'>";
            echo "✅ Plugin Video Library Protect détecté";
            echo "</div>";
        }
        
        // Vérifier GiftCode Protect
        if (class_exists('GiftCode_Manager')) {
            echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;'>";
            echo "✅ Plugin GiftCode Protect v2 détecté";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
            echo "❌ Plugin GiftCode Protect v2 non trouvé (optionnel mais recommandé)";
            echo "</div>";
        }
        
        if (!empty($this->errors)) {
            echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
            echo "<strong>❌ Erreurs détectées :</strong><ul>";
            foreach ($this->errors as $error) {
                echo "<li>{$error}</li>";
            }
            echo "</ul></div>";
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtenir les données des pages à créer
     */
    private function get_pages_data() {
        return [
            [
                'title' => 'Bibliothèque Vidéo',
                'slug' => 'video-library',
                'content' => $this->get_video_library_content(),
                'description' => 'Page principale pour découvrir et regarder toutes nos vidéos'
            ],
            [
                'title' => 'Catégories de Vidéos', 
                'slug' => 'categories-videos',
                'content' => $this->get_categories_content(),
                'description' => 'Naviguez dans nos vidéos par catégories thématiques'
            ],
            [
                'title' => 'Contenu Protégé - Exemple',
                'slug' => 'contenu-protege-exemple', 
                'content' => $this->get_protected_content(),
                'description' => 'Exemple de page avec du contenu protégé par codes cadeaux'
            ],
            [
                'title' => 'Aide & Support Vidéo',
                'slug' => 'aide-support-video',
                'content' => $this->get_help_content(), 
                'description' => 'Guide complet pour utiliser la bibliothèque vidéo et les codes cadeaux'
            ]
        ];
    }
    
    /**
     * Créer une page WordPress
     */
    private function create_page($page_data) {
        echo "<h3>📄 Création de la page : {$page_data['title']}</h3>";
        
        // Vérifier si la page existe déjà
        $existing_page = get_page_by_path($page_data['slug']);
        
        if ($existing_page) {
            echo "<div style='background: #fff3cd; padding: 10px; margin: 5px 0;'>";
            echo "⚠️ La page existe déjà. <a href='" . get_edit_post_link($existing_page->ID) . "'>Modifier</a> | ";
            echo "<a href='" . get_permalink($existing_page->ID) . "'>Voir</a>";
            echo "</div>";
            return;
        }
        
        // Créer la page
        $page_id = wp_insert_post([
            'post_title' => $page_data['title'],
            'post_name' => $page_data['slug'],
            'post_content' => $page_data['content'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                '_vlp_page_description' => $page_data['description']
            ]
        ]);
        
        if (is_wp_error($page_id)) {
            echo "<div style='background: #f8d7da; padding: 10px; margin: 5px 0;'>";
            echo "❌ Erreur lors de la création : " . $page_id->get_error_message();
            echo "</div>";
            $this->errors[] = "Impossible de créer la page : {$page_data['title']}";
        } else {
            echo "<div style='background: #d4edda; padding: 10px; margin: 5px 0;'>";
            echo "✅ Page créée avec succès ! ";
            echo "<a href='" . get_edit_post_link($page_id) . "'>Modifier</a> | ";
            echo "<a href='" . get_permalink($page_id) . "'>Voir</a>";
            echo "</div>";
            
            $this->pages_created[] = [
                'title' => $page_data['title'],
                'url' => get_permalink($page_id),
                'edit_url' => get_edit_post_link($page_id)
            ];
        }
    }
    
    /**
     * Afficher le résumé de l'installation
     */
    private function display_summary() {
        echo "<h2>📊 Résumé de l'Installation</h2>";
        
        if (!empty($this->pages_created)) {
            echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>";
            echo "<h3>✅ Pages créées avec succès (" . count($this->pages_created) . ") :</h3>";
            echo "<ul>";
            foreach ($this->pages_created as $page) {
                echo "<li><strong>{$page['title']}</strong> - ";
                echo "<a href='{$page['url']}' target='_blank'>Voir</a> | ";
                echo "<a href='{$page['edit_url']}' target='_blank'>Modifier</a>";
                echo "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        if (!empty($this->errors)) {
            echo "<div style='background: #f8d7da; padding: 20px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
            echo "<h3>❌ Erreurs rencontrées :</h3>";
            echo "<ul>";
            foreach ($this->errors as $error) {
                echo "<li>{$error}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        echo "<div style='background: #e8f4fd; padding: 20px; border-left: 4px solid #3498db; margin: 20px 0;'>";
        echo "<h3>🚀 Prochaines étapes :</h3>";
        echo "<ol>";
        echo "<li>Vérifiez que toutes les pages s'affichent correctement</li>";
        echo "<li>Configurez votre menu de navigation pour inclure ces pages</li>";
        echo "<li>Testez les shortcodes avec des vidéos de démonstration</li>";
        echo "<li>Personnalisez le CSS selon votre design</li>";
        echo "<li><strong>Supprimez ce fichier install-pages-wordpress.php</strong></li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
        echo "🔒 <strong>Sécurité :</strong> N'oubliez pas de supprimer ce fichier après l'installation !";
        echo "</div>";
    }
    
    /**
     * Contenu de la page Bibliothèque Vidéo
     */
    private function get_video_library_content() {
        return '
<div class="vlp-info-box" style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0;">
    <h3>🎥 Votre Bibliothèque Vidéo</h3>
    <p>Cette page affiche toutes vos vidéos disponibles. Les vidéos peuvent être gratuites ou protégées par des codes cadeaux.</p>
</div>

[vlp_video_library]

<div class="vlp-features" style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0;">
    <h4>💡 Fonctionnalités disponibles :</h4>
    <ul>
        <li>🔍 <strong>Recherche</strong> : Trouvez rapidement une vidéo</li>
        <li>📁 <strong>Filtres par catégorie</strong> : Naviguez par thème</li>
        <li>🔒 <strong>Protection par codes</strong> : Accès sécurisé aux contenus premium</li>
        <li>👁️ <strong>Aperçus gratuits</strong> : Découvrez le contenu avant l\'achat</li>
        <li>📱 <strong>Design responsive</strong> : Fonctionne sur tous les appareils</li>
    </ul>
</div>

<div class="vlp-tip" style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
    <p><strong>🎯 Astuce :</strong> Si vous avez des codes cadeaux, saisissez-les directement sur les vidéos protégées pour débloquer l\'accès complet !</p>
</div>';
    }
    
    /**
     * Contenu de la page Catégories
     */
    private function get_categories_content() {
        return '
<div class="vlp-info-box" style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0;">
    <h3>📁 Explorez nos Catégories</h3>
    <p>Découvrez nos vidéos organisées par thèmes et sujets. Chaque catégorie peut avoir son propre niveau de protection.</p>
</div>

[vlp_video_categories layout="grid" columns="3" show_count="true" show_protected="true"]

<div class="vlp-guide" style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0;">
    <h4>🚀 Comment naviguer dans les catégories :</h4>
    <ol>
        <li><strong>Parcourez les catégories</strong><br>Chaque catégorie affiche le nombre de vidéos et son statut de protection</li>
        <li><strong>Identifiez les protections</strong><br>L\'icône 🔒 indique qu\'un code cadeau est requis pour accéder au contenu</li>
        <li><strong>Cliquez pour explorer</strong><br>Accédez directement aux vidéos de la catégorie qui vous intéresse</li>
        <li><strong>Utilisez vos codes</strong><br>Saisissez votre code cadeau pour débloquer une catégorie entière</li>
    </ol>
</div>';
    }
    
    /**
     * Contenu de la page Protégée
     */
    private function get_protected_content() {
        return '
<div class="vlp-info-box" style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0;">
    <h3>🔒 Contenu Exclusif</h3>
    <p>Cette page démontre comment protéger du contenu spécifique avec des codes cadeaux personnalisés.</p>
</div>

[vlp_protected_content codes="VIP-ACCESS,PREMIUM-2024" message="Ce contenu exclusif nécessite un code VIP ou Premium." unlock_message="🎉 Bienvenue dans notre espace VIP !"]

<h3>🌟 Contenu Exclusif VIP</h3>
<p>Félicitations ! Vous avez débloqué notre contenu exclusif.</p>

<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
    <h4>🎁 Avantages VIP inclus :</h4>
    <ul>
        <li>✨ Accès anticipé aux nouvelles vidéos</li>
        <li>🎥 Contenu bonus et coulisses</li>
        <li>💬 Communauté privée VIP</li>
        <li>📧 Newsletter exclusive avec conseils privés</li>
        <li>🎯 Formations avancées réservées aux membres</li>
    </ul>
</div>

[/vlp_protected_content]';
    }
    
    /**
     * Contenu de la page d'aide
     */
    private function get_help_content() {
        return '
<div class="vlp-info-box" style="background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin: 20px 0;">
    <h3>❓ Centre d\'Aide - Video Library Protect</h3>
    <p>Trouvez rapidement les réponses à vos questions sur l\'utilisation de notre bibliothèque vidéo et des codes cadeaux.</p>
</div>

<h3>🚀 Guide de démarrage rapide</h3>
<div style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0;">
    <ol>
        <li><strong>Découvrez la bibliothèque</strong><br>Rendez-vous sur la page de la bibliothèque vidéo pour voir toutes les vidéos disponibles</li>
        <li><strong>Explorez les catégories</strong><br>Naviguez par thème sur la page des catégories</li>
        <li><strong>Regardez les aperçus gratuits</strong><br>Toutes les vidéos ont un aperçu gratuit pour vous aider à choisir</li>
        <li><strong>Utilisez vos codes cadeaux</strong><br>Saisissez votre code pour débloquer la vidéo complète ou une catégorie entière</li>
    </ol>
</div>

<h3>🔑 Utilisation des Codes Cadeaux</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
    <h4>Format des codes :</h4>
    <ul>
        <li>Entre 3 et 50 caractères</li>
        <li>Lettres, chiffres et tirets autorisés</li>
        <li>Pas de distinction majuscules/minuscules</li>
        <li>Exemples : <code>NOEL2024</code>, <code>promo-hiver</code>, <code>VIP-ACCESS</code></li>
    </ul>
</div>';
    }
}

// Exécuter l'installation si on accède directement au fichier
if (!defined('WP_CLI') && !wp_doing_ajax()) {
    // Vérifier les permissions basiques
    if (!function_exists('wp_insert_post')) {
        die('❌ Erreur : Ce script doit être exécuté dans un contexte WordPress valide.');
    }
    
    $installer = new VLP_Pages_Installer();
    $installer->install_all_pages();
    
    echo "<div style='text-align: center; margin: 30px 0; padding: 20px; background: #fff3cd; border-radius: 8px;'>";
    echo "<h3>🚨 IMPORTANT - SÉCURITÉ</h3>";
    echo "<p><strong>N'oubliez pas de supprimer ce fichier après l'installation !</strong></p>";
    echo "<p>Pour des raisons de sécurité, supprimez <code>install-pages-wordpress.php</code> de votre serveur.</p>";
    echo "</div>";
}
?>