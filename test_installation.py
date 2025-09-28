#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de test pour vérifier l'installation des pages VLP
"""

import os
from pathlib import Path

def test_installation():
    """Tester si tous les fichiers sont présents"""
    
    print("🔍 Vérification de l'Installation Video Library Protect")
    print("=" * 60)
    
    # Vérifier la structure du plugin
    plugin_files = [
        'video-library-protect.php',
        'includes/class-vlp-protection-manager.php',
        'includes/class-vlp-video-manager.php',
        'public/class-vlp-shortcodes.php',
        'admin/class-vlp-admin.php'
    ]
    
    print("📁 Fichiers du plugin Video Library Protect :")
    for file in plugin_files:
        if os.path.exists(file):
            print(f"  ✅ {file}")
        else:
            print(f"  ❌ {file} - MANQUANT")
    
    print()
    
    # Vérifier les pages créées
    pages_files = [
        'pages/page-video-library.php',
        'pages/page-categories-videos.php', 
        'pages/page-contenu-protege-exemple.php',
        'pages/page-aide-support-video.php'
    ]
    
    print("📄 Pages WordPress générées :")
    for file in pages_files:
        if os.path.exists(file):
            size = os.path.getsize(file)
            print(f"  ✅ {file} ({size:,} bytes)")
        else:
            print(f"  ❌ {file} - MANQUANT")
    
    print()
    
    # Vérifier les outils d'installation
    install_files = [
        'install-vlp-pages.php',
        'install-pages-wordpress.php',
        'create_pages.py'
    ]
    
    print("🔧 Outils d'installation :")
    for file in install_files:
        if os.path.exists(file):
            size = os.path.getsize(file)
            print(f"  ✅ {file} ({size:,} bytes)")
        else:
            print(f"  ❌ {file} - MANQUANT")
    
    print()
    
    # Vérifier la documentation
    doc_files = [
        'README-PAGES-CREEES.md',
        'GUIDE-INTEGRATION.md',
        'INSTALLATION-SIMPLE.md'
    ]
    
    print("📚 Documentation :")
    for file in doc_files:
        if os.path.exists(file):
            size = os.path.getsize(file)
            print(f"  ✅ {file} ({size:,} bytes)")
        else:
            print(f"  ❌ {file} - MANQUANT")
    
    print()
    
    # Compter les shortcodes dans les pages
    shortcode_count = 0
    shortcodes_found = []
    
    for file in pages_files:
        if os.path.exists(file):
            with open(file, 'r', encoding='utf-8') as f:
                content = f.read()
                if '[vlp_' in content:
                    import re
                    found = re.findall(r'\[vlp_[^\]]+\]', content)
                    shortcodes_found.extend(found)
                    shortcode_count += len(found)
    
    print(f"🎯 Shortcodes VLP détectés : {shortcode_count}")
    unique_shortcodes = list(set(shortcodes_found))
    for shortcode in unique_shortcodes[:5]:  # Limiter l'affichage
        print(f"  📝 {shortcode}")
    if len(unique_shortcodes) > 5:
        print(f"  📝 ... et {len(unique_shortcodes) - 5} autres")
    
    print()
    
    # Résumé final
    all_pages_exist = all(os.path.exists(f) for f in pages_files)
    install_tools_exist = any(os.path.exists(f) for f in install_files)
    docs_exist = all(os.path.exists(f) for f in doc_files)
    
    print("🎉 RÉSUMÉ DE L'INSTALLATION :")
    print(f"  📄 Pages WordPress : {'✅ Toutes créées' if all_pages_exist else '❌ Incomplètes'}")
    print(f"  🔧 Outils d'installation : {'✅ Disponibles' if install_tools_exist else '❌ Manquants'}")
    print(f"  📚 Documentation : {'✅ Complète' if docs_exist else '❌ Incomplète'}")
    print(f"  🎯 Shortcodes VLP : {'✅ ' + str(shortcode_count) + ' détectés' if shortcode_count > 0 else '❌ Aucun'}")
    
    if all_pages_exist and install_tools_exist and docs_exist:
        print("\n🚀 INSTALLATION PRÊTE ! Vous pouvez maintenant :")
        print("  1. Utiliser install-vlp-pages.php pour installation automatique")
        print("  2. Ou suivre INSTALLATION-SIMPLE.md pour installation manuelle")
        print("  3. Consulter GUIDE-INTEGRATION.md pour la configuration avancée")
    else:
        print("\n⚠️  Installation incomplète. Vérifiez les fichiers manquants.")
    
    return all_pages_exist and shortcode_count > 0

if __name__ == "__main__":
    test_installation()