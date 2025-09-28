<?php
/**
 * VLP Repair and Migration Tool
 *
 * INSTRUCTIONS:
 * 1. Placez ce fichier à la racine de votre installation WordPress (au même niveau que wp-config.php).
 * 2. Accédez à ce fichier directement dans votre navigateur : https://votresite.com/vlp-repair.php
 * 3. Suivez les instructions affichées à l'écran.
 * 4. Une fois la réparation terminée, SUPPRIMEZ CE FICHIER de votre serveur pour des raisons de sécurité.
 *
 * @package VideoLibraryProtect
 */

// --- Bootstrap WordPress ---
if (!defined('ABSPATH')) {
    $wp_load_path = dirname(__FILE__) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die("Erreur : Impossible de charger l'environnement WordPress. Assurez-vous que ce script est à la racine de votre installation.");
    }
}

// --- Security Check ---
if (!current_user_can('manage_options')) {
    wp_die('Accès refusé. Vous devez être administrateur pour exécuter ce script.');
}

// --- Configuration ---
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$plugin_file = 'video-library-protect/video-library-protect.php';

// --- Header ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Outil de Réparation - Video Library Protect</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 40px auto; padding: 20px; background-color: #f1f1f1; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .step { background-color: #fff; padding: 20px; margin-bottom: 20px; border-radius: 5px; border-left: 5px solid #3498db; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #c0392b; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .log { font-family: monospace; background-color: #ecf0f1; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; max-height: 300px; overflow-y: auto; }
        .button { display: inline-block; text-decoration: none; background-color: #3498db; color: #fff; padding: 10px 20px; border-radius: 4px; font-weight: bold; transition: background-color 0.3s; }
        .button:hover { background-color: #2980b9; }
        .button.disabled { background-color: #bdc3c7; cursor: not-allowed; }
        .danger { background-color: #e74c3c; }
        .danger:hover { background-color: #c0392b; }
    </style>
</head>
<body>
    <h1>Outil de Réparation - Video Library Protect</h1>
    <p class="warning"><strong>Important :</strong> Sauvegardez votre base de données avant de continuer. Une fois la réparation terminée, supprimez ce fichier de votre serveur.</p>

<?php

// --- Main Logic ---
global $wpdb;

// Table names
$tables = [
    'videos' => $wpdb->prefix . 'vlp_videos',
    'categories' => $wpdb->prefix . 'vlp_categories',
    'relations' => $wpdb->prefix . 'vlp_video_categories',
    'access_log' => $wpdb->prefix . 'vlp_access_log',
    'analytics' => $wpdb->prefix . 'vlp_analytics',
];

// Option names
$options = [
    'vlp_settings',
    'vlp_db_version',
];

// --- STEP 1: Diagnostic & Backup ---
if ($step === 1) {
    echo '<div class="step"><h2>Étape 1: Diagnostic et Sauvegarde</h2>';
    $can_proceed = true;

    // Check if plugin is active
    if (!is_plugin_active($plugin_file)) {
        echo '<p class="error">Le plugin Video Library Protect n\'est pas actif. Veuillez l\'activer avant de lancer la réparation.</p>';
        $can_proceed = false;
    } else {
        echo '<p class="success">Le plugin est actif.</p>';
    }

    // Check tables
    $missing_tables = [];
    foreach ($tables as $name => $table_name) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $missing_tables[] = $table_name;
        }
    }

    if (empty($missing_tables)) {
        echo '<p class="success">Toutes les tables requises existent.</p>';
    } else {
        echo '<p class="warning">Tables manquantes : ' . implode(', ', $missing_tables) . '. Le script tentera de les recréer.</p>';
    }

    // Backup data
    try {
        $backup_data = [];
        if (!in_array($tables['videos'], $missing_tables)) {
            $backup_data['videos'] = $wpdb->get_results("SELECT * FROM {$tables['videos']}", ARRAY_A);
        }
        if (!in_array($tables['categories'], $missing_tables)) {
            $backup_data['categories'] = $wpdb->get_results("SELECT * FROM {$tables['categories']}", ARRAY_A);
        }
        if (!in_array($tables['relations'], $missing_tables)) {
            $backup_data['relations'] = $wpdb->get_results("SELECT * FROM {$tables['relations']}", ARRAY_A);
        }

        $backup_file = WP_CONTENT_DIR . '/vlp-backup-' . date('Y-m-d-His') . '.json';
        if (file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT))) {
            echo '<p class="success">Sauvegarde des données critiques (vidéos, catégories) effectuée dans : <code>' . $backup_file . '</code></p>';
            echo '<div class="log">';
            echo 'Vidéos sauvegardées : ' . count($backup_data['videos'] ?? []) . "<br>";
            echo 'Catégories sauvegardées : ' . count($backup_data['categories'] ?? []) . "<br>";
            echo 'Relations sauvegardées : ' . count($backup_data['relations'] ?? []) . "<br>";
            echo '</div>';
            set_transient('vlp_repair_backup_file', $backup_file, HOUR_IN_SECONDS);
        } else {
            echo '<p class="error">Échec de la création du fichier de sauvegarde. Vérifiez les permissions d\'écriture dans <code>wp-content</code>.</p>';
            $can_proceed = false;
        }
    } catch (Exception $e) {
        echo '<p class="error">Une erreur est survenue lors de la sauvegarde : ' . $e->getMessage() . '</p>';
        $can_proceed = false;
    }

    if ($can_proceed) {
        echo '<p>Le diagnostic est terminé. Prêt à passer à l\'étape suivante pour nettoyer et réinstaller la base de données.</p>';
        echo '<a href="?step=2" class="button">Étape 2: Nettoyer et Réinstaller</a>';
    } else {
        echo '<p>Veuillez corriger les erreurs ci-dessus avant de continuer.</p>';
    }

    echo '</div>';
}

// --- STEP 2: Clean & Reinstall ---
if ($step === 2) {
    echo '<div class="step"><h2>Étape 2: Nettoyage et Réinstallation</h2>';
    $can_proceed = true;

    // Deactivate plugin
    deactivate_plugins($plugin_file);
    echo '<p>Plugin désactivé temporairement...</p>';

    // Drop tables
    echo '<p>Suppression des anciennes tables...</p><div class="log">';
    foreach (array_reverse($tables) as $name => $table_name) {
        $result = $wpdb->query("DROP TABLE IF EXISTS `$table_name`;");
        if ($result !== false) {
            echo "Table `$table_name` supprimée avec succès.<br>";
        } else {
            echo "<span class='error'>Erreur lors de la suppression de la table `$table_name`.</span><br>";
            $can_proceed = false;
        }
    }
    echo '</div>';

    // Delete options
    echo '<p>Suppression des anciennes options...</p><div class="log">';
    foreach ($options as $option_name) {
        if (delete_option($option_name)) {
            echo "Option `$option_name` supprimée avec succès.<br>";
        } else {
            echo "<span class='warning'>L'option `$option_name` n'existait pas ou n'a pas pu être supprimée.</span><br>";
        }
    }
    echo '</div>';

    // Reactivate plugin to trigger activation hooks
    $activation_result = activate_plugin($plugin_file, '', false, true); // silent activation
    if (is_wp_error($activation_result)) {
        echo '<p class="error">Erreur lors de la réactivation du plugin : ' . $activation_result->get_error_message() . '</p>';
        $can_proceed = false;
    } else {
        echo '<p class="success">Plugin réactivé et processus d\'activation (création des tables, options par défaut) exécuté.</p>';
    }

    if ($can_proceed) {
        echo '<p>Le nettoyage est terminé. Prêt à restaurer les données.</p>';
        echo '<a href="?step=3" class="button">Étape 3: Restaurer les Données</a>';
    } else {
        echo '<p>Une erreur critique est survenue. La restauration ne peut pas continuer.</p>';
    }

    echo '</div>';
}

// --- STEP 3: Restore Data ---
if ($step === 3) {
    echo '<div class="step"><h2>Étape 3: Restauration des Données</h2>';
    $can_proceed = true;
    $backup_file = get_transient('vlp_repair_backup_file');

    if (!$backup_file || !file_exists($backup_file)) {
        echo '<p class="error">Fichier de sauvegarde introuvable. Impossible de restaurer.</p>';
        $can_proceed = false;
    } else {
        echo '<p>Fichier de sauvegarde trouvé : <code>' . $backup_file . '</code></p>';
        $backup_data = json_decode(file_get_contents($backup_file), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<p class="error">Erreur de lecture du fichier de sauvegarde (JSON invalide).</p>';
            $can_proceed = false;
        }
    }

    if ($can_proceed) {
        try {
            // Restore Videos
            if (!empty($backup_data['videos'])) {
                echo '<p>Restauration des vidéos...</p><div class="log">';
                $videos_inserted = 0;
                foreach ($backup_data['videos'] as $video) {
                    if ($wpdb->insert($tables['videos'], $video)) {
                        $videos_inserted++;
                    }
                }
                echo "<span class='success'>$videos_inserted / " . count($backup_data['videos']) . " vidéos restaurées.</span></div>";
            }

            // Restore Categories
            if (!empty($backup_data['categories'])) {
                echo '<p>Restauration des catégories...</p><div class="log">';
                $categories_inserted = 0;
                foreach ($backup_data['categories'] as $category) {
                    if ($wpdb->insert($tables['categories'], $category)) {
                        $categories_inserted++;
                    }
                }
                echo "<span class='success'>$categories_inserted / " . count($backup_data['categories']) . " catégories restaurées.</span></div>";
            }

            // Restore Relations
            if (!empty($backup_data['relations'])) {
                echo '<p>Restauration des relations vidéo-catégorie...</p><div class="log">';
                $relations_inserted = 0;
                foreach ($backup_data['relations'] as $relation) {
                    if ($wpdb->insert($tables['relations'], $relation)) {
                        $relations_inserted++;
                    }
                }
                echo "<span class='success'>$relations_inserted / " . count($backup_data['relations']) . " relations restaurées.</span></div>";
            }

            // Final check on pages
            $settings = get_option('vlp_settings');
            if (empty($settings['library_page_id'])) {
                 echo '<p class="warning">La page de la bibliothèque n\'est pas définie. Vous devrez peut-être la re-créer ou la ré-assigner manuellement dans les réglages du plugin.</p>';
            } else {
                 echo '<p class="success">La page de la bibliothèque est correctement configurée.</p>';
            }

            echo '<h2>Réparation Terminée !</h2>';
            echo '<p class="success">Le plugin a été réinitialisé et vos données ont été restaurées. Tout devrait être rentré dans l\'ordre.</p>';
            echo '<p class="warning"><strong>ACTION REQUISE :</strong> Pour des raisons de sécurité, veuillez supprimer ce fichier (<code>vlp-repair.php</code>) de votre serveur immédiatement.</p>';
            echo '<a href="' . admin_url('admin.php?page=vlp-videos') . '" class="button">Aller à la bibliothèque de vidéos</a>';
            echo '<a href="' . get_site_url() . '" class="button" style="margin-left: 10px;">Aller à l\'accueil du site</a>';

            // Clean up
            delete_transient('vlp_repair_backup_file');
            // unlink($backup_file); // Uncomment to automatically delete the backup file

        } catch (Exception $e) {
            echo '<p class="error">Une erreur est survenue lors de la restauration : ' . $e->getMessage() . '</p>';
        }
    }

    echo '</div>';
}

?>
</body>
</html>
