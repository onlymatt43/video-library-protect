<?php
/**
 * Installation Rapide des Pages Video Library Protect
 * 
 * INSTRUCTIONS D'UTILISATION:
 * 
 * MÉTHODE 1 - Via WordPress Admin (Plus Sûre):
 * 1. Copiez ce fichier dans votre dossier WordPress principal
 * 2. Accédez à: votre-site.com/install-vlp-pages.php
 * 3. Suivez les instructions
 * 4. Supprimez le fichier après utilisation
 * 
 * MÉTHODE 2 - Exécution directe:
 * Placez ce fichier dans le dossier de votre site WordPress et exécutez-le
 */

// Tentative de chargement de WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/wp-config.php',
    dirname(__DIR__) . '/wp-load.php',
    dirname(__DIR__) . '/wp-config.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        if (strpos($path, 'wp-load.php') !== false) {
            require_once $path;
        } else {
            require_once $path;
            require_once ABSPATH . 'wp-load.php';
        }
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    // Affichage sans WordPress
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Installation Pages VLP - Instructions</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
            .info { background: #e8f4fd; padding: 15px; border-left: 4px solid #3498db; margin: 20px 0; }
            .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
            .code { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; margin: 15px 0; }
        </style>
    </head>
    <body>
        <h1>🎬 Installation Pages Video Library Protect</h1>
        
        <div class="error">
            <h3>❌ WordPress non détecté</h3>
            <p>Ce script n'a pas pu se connecter à WordPress automatiquement.</p>
        </div>
        
        <div class="info">
            <h3>🔧 Solutions Alternatives</h3>
            
            <h4>📍 Solution 1: Placement correct du fichier</h4>
            <p>1. Copiez ce fichier <code>install-vlp-pages.php</code> dans le <strong>dossier principal</strong> de votre WordPress</p>
            <p>2. Le fichier doit être au même niveau que <code>wp-config.php</code></p>
            <p>3. Accédez ensuite à: <code>https://votre-site.com/install-vlp-pages.php</code></p>
            
            <h4>📝 Solution 2: Installation manuelle (Recommandée)</h4>
            <p>Créez les pages manuellement dans WordPress Admin :</p>
        </div>
        
        <div class="success">
            <h3>📋 Pages à Créer Manuellement</h3>
            
            <h4>1. 🎥 Bibliothèque Vidéo</h4>
            <ul>
                <li><strong>Titre:</strong> Bibliothèque Vidéo</li>
                <li><strong>Slug:</strong> video-library</li>
                <li><strong>Contenu:</strong></li>
            </ul>
            <div class="code">
&lt;h2&gt;🎥 Découvrez Notre Bibliothèque Vidéo&lt;/h2&gt;
&lt;p&gt;Explorez toutes nos vidéos disponibles. Certaines sont gratuites, d'autres nécessitent un code cadeau.&lt;/p&gt;

[vlp_video_library]

&lt;div style="background: #e8f4fd; padding: 20px; margin: 20px 0; border-left: 4px solid #3498db;"&gt;
    &lt;h4&gt;💡 Fonctionnalités :&lt;/h4&gt;
    &lt;ul&gt;
        &lt;li&gt;🔍 Recherche rapide&lt;/li&gt;
        &lt;li&gt;📁 Filtres par catégorie&lt;/li&gt;
        &lt;li&gt;🔒 Protection par codes cadeaux&lt;/li&gt;
        &lt;li&gt;👁️ Aperçus gratuits&lt;/li&gt;
    &lt;/ul&gt;
&lt;/div&gt;
            </div>
            
            <h4>2. 📁 Catégories de Vidéos</h4>
            <ul>
                <li><strong>Titre:</strong> Catégories de Vidéos</li>
                <li><strong>Slug:</strong> categories-videos</li>
                <li><strong>Contenu:</strong></li>
            </ul>
            <div class="code">
&lt;h2&gt;📁 Explorez par Catégories&lt;/h2&gt;
&lt;p&gt;Découvrez nos vidéos organisées par thèmes. L'icône 🔒 indique un contenu protégé.&lt;/p&gt;

[vlp_video_categories layout="grid" columns="3" show_count="true" show_protected="true"]
            </div>
            
            <h4>3. 🔒 Contenu Protégé (Exemple)</h4>
            <ul>
                <li><strong>Titre:</strong> Contenu Exclusif</li>
                <li><strong>Slug:</strong> contenu-exclusif</li>
                <li><strong>Contenu:</strong></li>
            </ul>
            <div class="code">
&lt;h2&gt;🔒 Zone VIP&lt;/h2&gt;

[vlp_protected_content codes="VIP-ACCESS,PREMIUM-2024" message="Ce contenu nécessite un code VIP."]

&lt;h3&gt;🌟 Contenu Exclusif Débloqué !&lt;/h3&gt;
&lt;p&gt;Félicitations ! Vous avez accès au contenu premium.&lt;/p&gt;
&lt;ul&gt;
    &lt;li&gt;✨ Vidéos en avant-première&lt;/li&gt;
    &lt;li&gt;🎥 Contenus bonus&lt;/li&gt;
    &lt;li&gt;💬 Communauté VIP&lt;/li&gt;
&lt;/ul&gt;

[/vlp_protected_content]
            </div>
            
            <h4>4. ❓ Aide & Support</h4>
            <ul>
                <li><strong>Titre:</strong> Aide & Support</li>
                <li><strong>Slug:</strong> aide-support</li>
                <li><strong>Contenu:</strong></li>
            </ul>
            <div class="code">
&lt;h2&gt;❓ Centre d'Aide&lt;/h2&gt;

&lt;h3&gt;🔑 Comment utiliser les codes cadeaux&lt;/h3&gt;
&lt;ol&gt;
    &lt;li&gt;Trouvez une vidéo protégée (icône 🔒)&lt;/li&gt;
    &lt;li&gt;Cliquez sur la vidéo&lt;/li&gt;
    &lt;li&gt;Saisissez votre code dans le formulaire&lt;/li&gt;
    &lt;li&gt;Profitez de la vidéo complète !&lt;/li&gt;
&lt;/ol&gt;

&lt;h3&gt;📋 Format des codes&lt;/h3&gt;
&lt;ul&gt;
    &lt;li&gt;3-50 caractères&lt;/li&gt;
    &lt;li&gt;Lettres, chiffres, tirets&lt;/li&gt;
    &lt;li&gt;Exemples: NOEL2024, VIP-ACCESS&lt;/li&gt;
&lt;/ul&gt;
            </div>
        </div>
        
        <div class="info">
            <h3>🚀 Après Création des Pages</h3>
            <ol>
                <li>Ajoutez les pages à votre menu WordPress</li>
                <li>Testez les shortcodes avec du contenu</li>
                <li>Configurez vos codes cadeaux</li>
                <li>Personnalisez le design selon vos besoins</li>
            </ol>
        </div>
        
    </body>
    </html>
    <?php
    exit;
}

// WordPress chargé - Procéder à l'installation
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Installation Pages VLP</title>
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #f1f1f1; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.13); }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0; border-radius: 4px; }
        .info { background: #e8f4fd; padding: 15px; border-left: 4px solid #17a2b8; margin: 15px 0; border-radius: 4px; }
        .btn { background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #005a87; color: white; }
        h1 { color: #23282d; border-bottom: 1px solid #ccd0d4; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎬 Installation Pages Video Library Protect</h1>
        
        <?php
        // Vérification des prérequis
        $errors = [];
        $warnings = [];
        
        if (!current_user_can('edit_pages')) {
            $errors[] = "Vous devez être connecté en tant qu'administrateur";
        }
        
        if (!class_exists('Video_Library_Protect')) {
            $warnings[] = "Le plugin Video Library Protect ne semble pas activé";
        }
        
        if (!class_exists('GiftCode_Manager')) {
            $warnings[] = "Le plugin GiftCode Protect v2 n'est pas détecté (optionnel)";
        }
        
        // Affichage des erreurs
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<div class='error'>❌ {$error}</div>";
            }
            echo "<p>Veuillez corriger ces erreurs avant de continuer.</p>";
        } else {
            // Affichage des avertissements
            if (!empty($warnings)) {
                foreach ($warnings as $warning) {
                    echo "<div class='warning'>⚠️ {$warning}</div>";
                }
            }
            
            // Traitement de l'installation
            if (isset($_POST['install_pages'])) {
                install_vlp_pages();
            } else {
                show_installation_form();
            }
        }
        ?>
        
    </div>
</body>
</html>

<?php
function show_installation_form() {
    ?>
    <div class="info">
        <h3>📋 Pages qui seront créées :</h3>
        <ul>
            <li><strong>Bibliothèque Vidéo</strong> - Page principale avec toutes les vidéos</li>
            <li><strong>Catégories de Vidéos</strong> - Navigation par thèmes</li>
            <li><strong>Contenu Exclusif</strong> - Exemple de contenu protégé</li>
            <li><strong>Aide & Support</strong> - Guide d'utilisation</li>
        </ul>
    </div>
    
    <form method="post">
        <input type="hidden" name="install_pages" value="1">
        <p>
            <input type="submit" value="🚀 Installer les Pages" class="btn">
        </p>
    </form>
    
    <div class="warning">
        <p><strong>Note :</strong> Si des pages existent déjà avec les mêmes noms, elles ne seront pas écrasées.</p>
    </div>
    <?php
}

function install_vlp_pages() {
    $pages_data = [
        [
            'title' => 'Bibliothèque Vidéo',
            'slug' => 'video-library',
            'content' => get_video_library_content()
        ],
        [
            'title' => 'Catégories de Vidéos',
            'slug' => 'categories-videos', 
            'content' => get_categories_content()
        ],
        [
            'title' => 'Contenu Exclusif',
            'slug' => 'contenu-exclusif',
            'content' => get_exclusive_content()
        ],
        [
            'title' => 'Aide & Support',
            'slug' => 'aide-support',
            'content' => get_help_content()
        ]
    ];
    
    $created = 0;
    $skipped = 0;
    
    foreach ($pages_data as $page) {
        $existing = get_page_by_path($page['slug']);
        
        if ($existing) {
            echo "<div class='warning'>⚠️ Page '{$page['title']}' existe déjà - <a href='" . get_permalink($existing->ID) . "' target='_blank'>Voir</a> | <a href='" . get_edit_post_link($existing->ID) . "' target='_blank'>Modifier</a></div>";
            $skipped++;
            continue;
        }
        
        $page_id = wp_insert_post([
            'post_title' => $page['title'],
            'post_name' => $page['slug'],
            'post_content' => $page['content'],
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
        
        if ($page_id && !is_wp_error($page_id)) {
            echo "<div class='success'>✅ Page '{$page['title']}' créée - <a href='" . get_permalink($page_id) . "' target='_blank'>Voir</a> | <a href='" . get_edit_post_link($page_id) . "' target='_blank'>Modifier</a></div>";
            $created++;
        } else {
            echo "<div class='error'>❌ Erreur lors de la création de '{$page['title']}'</div>";
        }
    }
    
    echo "<div class='info'>";
    echo "<h3>📊 Résumé :</h3>";
    echo "<p>✅ {$created} pages créées<br>";
    echo "⚠️ {$skipped} pages déjà existantes</p>";
    echo "</div>";
    
    if ($created > 0) {
        echo "<div class='success'>";
        echo "<h3>🎉 Installation terminée !</h3>";
        echo "<h4>🚀 Prochaines étapes :</h4>";
        echo "<ol>";
        echo "<li>Ajoutez les pages à votre menu WordPress</li>";
        echo "<li>Testez les shortcodes Video Library Protect</li>";
        echo "<li>Configurez vos codes cadeaux si nécessaire</li>";
        echo "<li><strong>Supprimez ce fichier install-vlp-pages.php</strong></li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<p><strong>🔒 Sécurité :</strong> N'oubliez pas de supprimer ce fichier après l'installation !</p>";
        echo "</div>";
    }
}

function get_video_library_content() {
    return '
<h2>🎥 Découvrez Notre Bibliothèque Vidéo</h2>
<p>Explorez toutes nos vidéos disponibles. Certaines sont gratuites, d\'autres nécessitent un code cadeau pour accéder au contenu complet.</p>

[vlp_video_library]

<div style="background: #e8f4fd; padding: 20px; margin: 20px 0; border-left: 4px solid #3498db; border-radius: 4px;">
    <h4>💡 Fonctionnalités disponibles :</h4>
    <ul>
        <li>🔍 <strong>Recherche intelligente</strong> - Trouvez rapidement une vidéo</li>
        <li>📁 <strong>Filtres par catégorie</strong> - Naviguez par thème</li>
        <li>🔒 <strong>Protection par codes</strong> - Accès sécurisé aux contenus premium</li>
        <li>👁️ <strong>Aperçus gratuits</strong> - Découvrez le contenu avant l\'accès complet</li>
        <li>📱 <strong>Design responsive</strong> - Fonctionne parfaitement sur mobile et tablette</li>
    </ul>
</div>

<div style="background: #d4edda; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745; border-radius: 4px;">
    <p><strong>🎯 Astuce :</strong> Si vous avez des codes cadeaux, saisissez-les directement sur les vidéos protégées pour débloquer l\'accès complet instantanément !</p>
</div>';
}

function get_categories_content() {
    return '
<h2>📁 Explorez nos Catégories</h2>
<p>Découvrez nos vidéos organisées par thèmes et sujets. Chaque catégorie peut avoir son propre niveau de protection.</p>

[vlp_video_categories layout="grid" columns="3" show_count="true" show_protected="true"]

<div style="background: #f8f9fa; padding: 25px; margin: 25px 0; border-radius: 8px;">
    <h4>🚀 Comment naviguer dans les catégories :</h4>
    <ol style="line-height: 1.8;">
        <li><strong>Parcourez les catégories</strong> - Chaque carte affiche le nombre de vidéos et le statut de protection</li>
        <li><strong>Identifiez les protections</strong> - L\'icône 🔒 indique qu\'un code cadeau est requis</li>
        <li><strong>Cliquez pour explorer</strong> - Accédez directement aux vidéos de votre catégorie préférée</li>
        <li><strong>Utilisez vos codes</strong> - Un code de catégorie débloque toutes les vidéos du thème</li>
    </ol>
</div>

<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
    <h4>🔑 Codes Cadeaux de Catégorie</h4>
    <p>Certaines catégories nécessitent un code cadeau valide. Une fois un code saisi, vous aurez accès à <strong>toutes les vidéos</strong> de cette catégorie !</p>
</div>';
}

function get_exclusive_content() {
    return '
<h2>🔒 Zone VIP Exclusive</h2>
<p>Bienvenue dans notre espace premium ! Ce contenu est protégé par des codes cadeaux spéciaux.</p>

[vlp_protected_content codes="VIP-ACCESS,PREMIUM-2024" message="Ce contenu exclusif nécessite un code VIP ou Premium." unlock_message="🎉 Bienvenue dans notre espace VIP !"]

<h3>🌟 Contenu Exclusif Débloqué !</h3>
<p>Félicitations ! Vous avez débloqué notre contenu premium réservé aux membres VIP.</p>

<div style="background: #d4edda; padding: 25px; margin: 25px 0; border-left: 4px solid #28a745; border-radius: 8px;">
    <h4>🎁 Avantages VIP inclus :</h4>
    <ul style="line-height: 1.8;">
        <li>✨ <strong>Accès anticipé</strong> - Nouvelles vidéos avant tout le monde</li>
        <li>🎥 <strong>Contenu bonus</strong> - Coulisses et interviews exclusives</li>
        <li>💬 <strong>Communauté privée</strong> - Échangez avec d\'autres membres VIP</li>
        <li>📧 <strong>Newsletter exclusive</strong> - Conseils et astuces privés</li>
        <li>🎯 <strong>Formations avancées</strong> - Masterclass réservées aux membres</li>
    </ul>
</div>

<h4>🎬 Vidéo Exclusive du Mois</h4>
<p>Cette section contiendrait votre contenu premium - vidéos, documents, formations, etc.</p>

[/vlp_protected_content]

<div style="background: #e8f4fd; padding: 20px; margin: 20px 0; border-left: 4px solid #17a2b8; border-radius: 4px;">
    <h4>🔑 Codes acceptés pour cette page :</h4>
    <ul>
        <li><code>VIP-ACCESS</code> - Accès VIP complet</li>
        <li><code>PREMIUM-2024</code> - Abonnement premium annuel</li>
    </ul>
    <p><em>Les codes sont sensibles à la casse et peuvent contenir des lettres, chiffres et tirets.</em></p>
</div>';
}

function get_help_content() {
    return '
<h2>❓ Centre d\'Aide Video Library Protect</h2>
<p>Trouvez rapidement les réponses à vos questions sur l\'utilisation de notre bibliothèque vidéo et des codes cadeaux.</p>

<div style="background: #f8f9fa; padding: 25px; margin: 25px 0; border-radius: 8px;">
    <h3>🚀 Guide de démarrage rapide</h3>
    <ol style="line-height: 2;">
        <li><strong>Découvrez la bibliothèque</strong><br><small>Rendez-vous sur la page Bibliothèque Vidéo pour voir toutes les vidéos disponibles</small></li>
        <li><strong>Explorez les catégories</strong><br><small>Naviguez par thème sur la page Catégories de Vidéos</small></li>
        <li><strong>Regardez les aperçus gratuits</strong><br><small>Toutes les vidéos protégées ont un aperçu gratuit pour vous aider à choisir</small></li>
        <li><strong>Utilisez vos codes cadeaux</strong><br><small>Saisissez votre code pour débloquer la vidéo complète ou une catégorie entière</small></li>
    </ol>
</div>

<h3>🔑 Utilisation des Codes Cadeaux</h3>

<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
    <h4>📋 Format des codes :</h4>
    <ul>
        <li>Entre 3 et 50 caractères</li>
        <li>Lettres, chiffres et tirets autorisés</li>
        <li>Pas de distinction majuscules/minuscules</li>
        <li><strong>Exemples valides :</strong> <code>NOEL2024</code>, <code>promo-hiver</code>, <code>VIP-ACCESS</code></li>
    </ul>
</div>

<h3>🎯 Types de Protection</h3>

<div style="display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0;">
    <div style="flex: 1; min-width: 300px; background: #e8f4fd; padding: 15px; border-radius: 6px;">
        <h4>🆓 Vidéos Gratuites</h4>
        <p>Accès immédiat et complet sans code requis.</p>
    </div>
    <div style="flex: 1; min-width: 300px; background: #fff3cd; padding: 15px; border-radius: 6px;">
        <h4>🔒 Vidéos Protégées</h4>
        <p>Aperçu gratuit + code pour la vidéo complète.</p>
    </div>
</div>

<div style="display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0;">
    <div style="flex: 1; min-width: 300px; background: #f8d7da; padding: 15px; border-radius: 6px;">
        <h4>📁 Catégories Protégées</h4>
        <p>Un code donne accès à toute la catégorie.</p>
    </div>
    <div style="flex: 1; min-width: 300px; background: #d4edda; padding: 15px; border-radius: 6px;">
        <h4>🌐 Accès Site Entier</h4>
        <p>Codes premium pour tout le contenu.</p>
    </div>
</div>

<h3>🛠️ Problèmes Courants</h3>

<div style="background: #f8f9fa; padding: 25px; margin: 25px 0; border-radius: 8px;">
    <h4>❌ "Code invalide"</h4>
    <ul>
        <li>✅ Vérifiez l\'orthographe et les espaces</li>
        <li>✅ Assurez-vous que le code n\'est pas expiré</li>
        <li>✅ Contactez le support si le problème persiste</li>
    </ul>
    
    <h4>❌ "La vidéo ne se charge pas"</h4>
    <ul>
        <li>✅ Vérifiez votre connexion internet</li>
        <li>✅ Rafraîchissez la page</li>
        <li>✅ Désactivez temporairement les bloqueurs de publicité</li>
    </ul>
</div>

<div style="background: #d4edda; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745; border-radius: 4px;">
    <h4>📞 Besoin d\'aide supplémentaire ?</h4>
    <p>Notre équipe support est là pour vous aider !</p>
    <ul>
        <li>📧 <strong>Email :</strong> support@votresite.com</li>
        <li>💬 <strong>Chat :</strong> Disponible 9h-18h en semaine</li>
        <li>⏰ <strong>Réponse :</strong> Moins de 2h en moyenne</li>
    </ul>
</div>';
}
?>