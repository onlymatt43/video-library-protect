# 🎬 Guide Installation Rapide - Pages Video Library Protect

## ❌ Problème "Page Not Found" ?

Si vous obtenez une erreur "page not found", c'est normal ! Voici **3 solutions simples** pour installer les pages :

---

## 🚀 **Solution 1: Installation Manuelle (Plus Simple)**

### 📝 Créez les pages directement dans WordPress Admin

1. **Allez dans votre admin WordPress**
2. **Cliquez sur Pages > Ajouter**
3. **Créez ces 4 pages une par une :**

### 🎥 **Page 1: Bibliothèque Vidéo**
- **Titre :** `Bibliothèque Vidéo`
- **Slug :** `video-library`
- **Contenu :**
```html
<h2>🎥 Découvrez Notre Bibliothèque Vidéo</h2>
<p>Explorez toutes nos vidéos disponibles. Certaines sont gratuites, d'autres nécessitent un code cadeau.</p>

[vlp_video_library]

<div style="background: #e8f4fd; padding: 20px; margin: 20px 0; border-left: 4px solid #3498db;">
    <h4>💡 Fonctionnalités :</h4>
    <ul>
        <li>🔍 Recherche rapide</li>
        <li>📁 Filtres par catégorie</li>
        <li>🔒 Protection par codes cadeaux</li>
        <li>👁️ Aperçus gratuits</li>
    </ul>
</div>
```

### 📁 **Page 2: Catégories de Vidéos**
- **Titre :** `Catégories de Vidéos`
- **Slug :** `categories-videos`
- **Contenu :**
```html
<h2>📁 Explorez par Catégories</h2>
<p>Découvrez nos vidéos organisées par thèmes. L'icône 🔒 indique un contenu protégé.</p>

[vlp_video_categories layout="grid" columns="3" show_count="true" show_protected="true"]

<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
    <h4>🔑 Info Codes Cadeaux</h4>
    <p>Certaines catégories nécessitent un code. Une fois saisi, vous accédez à toutes les vidéos de la catégorie !</p>
</div>
```

### 🔒 **Page 3: Contenu Exclusif**
- **Titre :** `Contenu Exclusif`
- **Slug :** `contenu-exclusif`
- **Contenu :**
```html
<h2>🔒 Zone VIP</h2>
<p>Contenu premium protégé par codes cadeaux.</p>

[vlp_protected_content codes="VIP-ACCESS,PREMIUM-2024" message="Ce contenu nécessite un code VIP."]

<h3>🌟 Contenu Exclusif Débloqué !</h3>
<p>Félicitations ! Vous avez accès au contenu premium.</p>

<div style="background: #d4edda; padding: 20px; margin: 20px 0; border-left: 4px solid #28a745;">
    <h4>🎁 Avantages VIP :</h4>
    <ul>
        <li>✨ Vidéos en avant-première</li>
        <li>🎥 Contenus bonus exclusifs</li>
        <li>💬 Communauté VIP privée</li>
        <li>📧 Newsletter premium</li>
    </ul>
</div>

[/vlp_protected_content]
```

### ❓ **Page 4: Aide & Support**
- **Titre :** `Aide & Support`
- **Slug :** `aide-support`
- **Contenu :**
```html
<h2>❓ Centre d'Aide</h2>

<h3>🔑 Comment utiliser les codes cadeaux</h3>
<ol>
    <li>Trouvez une vidéo protégée (icône 🔒)</li>
    <li>Cliquez sur la vidéo</li>
    <li>Saisissez votre code dans le formulaire</li>
    <li>Profitez de la vidéo complète !</li>
</ol>

<h3>📋 Format des codes</h3>
<div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107;">
    <ul>
        <li><strong>3-50 caractères</strong></li>
        <li><strong>Lettres, chiffres, tirets</strong></li>
        <li><strong>Exemples:</strong> NOEL2024, VIP-ACCESS, promo-hiver</li>
    </ul>
</div>

<h3>🛠️ Problèmes courants</h3>
<h4>❌ "Code invalide"</h4>
<ul>
    <li>✅ Vérifiez l'orthographe</li>
    <li>✅ Vérifiez que le code n'est pas expiré</li>
    <li>✅ Contactez le support si nécessaire</li>
</ul>
```

---

## 🔧 **Solution 2: Script d'Installation Automatique**

Si vous voulez utiliser le script automatique :

1. **Copiez le fichier `install-vlp-pages.php`** dans le **dossier principal** de votre WordPress
2. **Le fichier doit être au même niveau que `wp-config.php`**
3. **Accédez à :** `https://votre-site.com/install-vlp-pages.php`
4. **Suivez les instructions** à l'écran
5. **Supprimez le fichier** après utilisation

---

## 📱 **Solution 3: Copier les Fichiers Template**

1. **Copiez les fichiers PHP du dossier `pages/`** dans votre thème WordPress :
   ```
   wp-content/themes/votre-theme/
   ```

2. **Créez les pages vides** dans WordPress avec les bons slugs

3. WordPress utilisera automatiquement les templates

---

## ✅ **Après Installation**

### 🗂️ **Ajoutez au Menu**
1. Allez dans **Apparence > Menus**
2. Ajoutez vos nouvelles pages au menu principal
3. Ordre suggéré :
   - 🏠 Accueil
   - 🎥 Bibliothèque Vidéo
   - 📁 Catégories de Vidéos  
   - 🔒 Contenu Exclusif
   - ❓ Aide & Support

### 🧪 **Testez les Shortcodes**
- Vérifiez que `[vlp_video_library]` s'affiche
- Testez `[vlp_video_categories]`
- Essayez les codes cadeaux sur le contenu protégé

### 🎨 **Personnalisez**
- Modifiez les couleurs selon votre design
- Adaptez les textes à votre audience
- Ajoutez votre propre contenu VIP

---

## 🎯 **Shortcodes Disponibles**

```php
[vlp_video_library]                    // Bibliothèque complète
[vlp_video_categories]                 // Navigation catégories
[vlp_protected_content codes="CODE"]   // Contenu protégé
[vlp_single_video id="123"]           // Vidéo individuelle
```

---

## 📞 **Besoin d'Aide ?**

Si vous rencontrez des difficultés :
1. **Vérifiez** que le plugin Video Library Protect est activé
2. **Testez** les shortcodes sur une page de test
3. **Contactez** le support technique

---

**🎉 Votre bibliothèque vidéo est maintenant prête !**

L'installation manuelle (Solution 1) est la plus simple et la plus sûre. Elle ne prend que 10-15 minutes et fonctionne dans tous les cas.