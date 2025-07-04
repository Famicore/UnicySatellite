# Changelog - UnicySatellite

Toutes les modifications notables de ce package seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versioning Sémantique](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-03

### ✨ Ajouté
- **🔐 SensitiveDataDetector Service** : Nouveau service de détection automatique des données sensibles dans le fichier `.env`
- **🤖 Détection Intelligente** : Plus de 70 patterns de détection pour identifiquer automatiquement :
  - Mots de passe (password, passwd, pwd, pass)
  - Clés API (api_key, client_secret, consumer_key, etc.)
  - Tokens (access_token, bearer_token, jwt_secret, etc.)
  - Secrets et clés de chiffrement (app_key, encryption_key, private_key, etc.)
  - Services externes (Stripe, AWS, Google, GitHub, GitLab, etc.)
  - Certificats et clés SSL/TLS
  - Variables de base de données sensibles
  - Webhooks secrets et tokens OAuth

### 🔌 Nouveau Endpoint API
- **`GET /api/satellite/sensitive-keys`** : Endpoint pour récupérer automatiquement toutes les données sensibles détectées
- **Paramètres supportés** :
  - `show_raw_values` (boolean) : Afficher les vraies valeurs ou masquées
  - `include_stats` (boolean) : Inclure les statistiques de sécurité
- **Réponse JSON** avec données sensibles, compteurs et métadonnées

### 🛡️ Fonctionnalités de Sécurité
- **Masquage Intelligent** : Masquage automatique des valeurs avec préservation début/fin pour identification
- **Pattern Recognition** : Détection basée sur regex pour formats spécialisés (Stripe keys, JWT tokens, etc.)
- **Liste d'Exclusion** : Variables système automatiquement ignorées (app_debug, db_host, etc.)
- **Validation de Complexité** : Détection des valeurs longues et complexes probablement sensibles

### 📊 Statistiques de Sécurité
- **Ratio de sécurité** : Pourcentage de variables sensibles vs total
- **Compteurs détaillés** : Variables totales, sensibles, patterns utilisés
- **Historique de scan** : Timestamp et metadata de chaque analyse

### 🔧 API Flexible
- **Patterns Extensibles** : Méthodes pour ajouter des patterns personnalisés
- **Variables Ignorées** : Configuration des variables à ignorer
- **Mode Debug** : Affichage des vraies valeurs pour le développement
- **Gestion d'Erreurs** : Fallbacks gracieux en cas d'erreur de lecture

### 🎯 Cas d'Usage
- **UnicyHub** : Récupération automatique des clés sensibles des satellites
- **Monitoring** : Surveillance des nouvelles variables sensibles ajoutées
- **Audit Sécurité** : Analyse et rapport des données critiques
- **Configuration** : Validation automatique des variables environnement

### 📝 Logging Intégré
- **Scan Complet** : Log détaillé de chaque analyse avec métadonnées
- **Variables Détectées** : Liste des variables sensibles trouvées
- **Erreurs Gracieuses** : Gestion d'erreurs avec logs d'information

### ⚡ Performance Optimisée
- **Parsing Efficace** : Analyse rapide du fichier .env sans chargement Laravel
- **Cache Friendly** : Service stateless compatible avec mise en cache
- **Regex Optimisées** : Patterns de détection performants

## [1.0.1] - 2025-01-03

### 🐛 Corrigé
- **Performance Endpoint** : Correction de l'appel `count()` pour compatibilité PHP 8+
- **DB Connections** : Utilisation de `count(\DB::getConnections())` au lieu de `\DB::getConnections()->count()`
- **Stabilité API** : Endpoint `/api/satellite/performance` maintenant pleinement fonctionnel

### ⚡ Performance  
- **Laravel Octane** : Validation complète de compatibilité avec FrankenPHP
- **Optimisations** : Tests réussis avec serveurs haute performance

### ✅ Validations
- Tests avec Laravel Octane dans UnicyLogistik réussis
- Endpoint `/api/satellite/performance` entièrement opérationnel
- Communication satellite optimisée confirmée

## [1.0.0] - 2025-01-02

### Ajouté
- 🎉 **Version initiale du package UnicySatellite**
- 📡 **Communication avec UnicyHub** via Saloon HTTP client
- 🔄 **Synchronisation bidirectionnelle** des données (tenants, utilisateurs)
- 📊 **Collecte de métriques en temps réel** avec support multi-types
- 🏥 **Health monitoring** avec checks automatiques
- 🎮 **Exécution de commandes à distance** sécurisée
- 🛡️ **Système de sécurité complet** (API auth, rate limiting, IP whitelist)
- 🗄️ **Gestion du cache distribué** avec invalidation intelligente

### Fonctionnalités principales

#### Services
- `SatelliteHubService` - Communication principale avec UnicyHub
- `MetricsCollectorService` - Collecte de métriques système et applicatives
- `SyncService` - Synchronisation des données avec UnicyHub

#### Commandes Artisan
- `satellite:register` - Enregistrement auprès d'UnicyHub
- `satellite:sync` - Synchronisation des données
- `satellite:metrics` - Collecte et envoi de métriques

#### Endpoints API
- `/api/satellite/health` - Health check complet
- `/api/satellite/status` - Statut du satellite
- `/api/satellite/metrics` - Métriques en temps réel
- `/api/satellite/commands` - Exécution de commandes distantes
- `/api/satellite/updates` - Réception de mises à jour
- `/api/satellite/cache` - Gestion du cache
- `/api/satellite/info` - Informations détaillées

#### Métriques supportées
- **Générales** : utilisateurs, tenants, sessions actives
- **Système** : mémoire, CPU, disque
- **UnicyLogistik** : commandes, expéditions
- **UnicyVinci** : courtiers, jobs
- **UnicyPixel** : QR codes, scans

#### Sécurité
- Authentification par Bearer token
- Rate limiting configurable (100 req/min par défaut)
- IP whitelisting avec support CIDR
- Validation SSL obligatoire
- Liste blanche de commandes autorisées

#### Configuration
- Support complet des variables d'environnement
- Configuration flexible par type de satellite
- Auto-enregistrement configurable
- Intervalles de sync/métriques personnalisables

### Architecture
- **Saloon HTTP Client** pour communication élégante
- **Laravel Service Provider** avec auto-discovery
- **Facade** pour accès simplifié (`SatelliteHub::`)
- **Middleware** d'authentification sécurisé
- **Exception** personnalisée pour gestion d'erreurs
- **Schedulable** avec tâches automatiques

### Documentation
- README complet avec exemples
- Configuration détaillée
- Guide de debugging
- Structure du package documentée

### Corrections appliquées (intégration UnicyLogistik)
- 🐛 **Laravel 11 compatibility** - Remplacement `everyNMinutes()` par méthodes compatibles
- 🐛 **Saloon v3 compatibility** - Suppression trait `AlwaysThrowsOnErrors` inexistant
- 🔧 **Configuration handling** - Gestion gracieuse variables d'environnement manquantes
- 🔧 **Service Provider** - Validation configuration avant instantiation services

### Tests d'intégration validés ✅
- **Installation UnicyLogistik** - Package discovery et autoload réussis
- **Configuration automatique** - 25 variables .env ajoutées proprement
- **Commandes Artisan** - `register --test`, `sync --dry-run`, `metrics --show` fonctionnelles
- **Endpoints API** - 7 routes créées avec sécurité active
- **Métriques temps réel** - Collecte utilisateurs/tenants/sessions opérationnelle
- **Health checks** - Authentification API validée

### Compatibilité
- **Laravel** : 10.x, 11.x (testé sur 11.x)
- **PHP** : 8.1+
- **Saloon** : 3.x (testé avec 3.14.0)
- **Spatie Laravel Health** : 1.x (testé avec 1.34.3)

---

## Format du changelog

### Types de modifications
- **Ajouté** pour les nouvelles fonctionnalités
- **Modifié** pour les changements dans les fonctionnalités existantes
- **Déprécié** pour les fonctionnalités qui seront supprimées
- **Supprimé** pour les fonctionnalités supprimées
- **Corrigé** pour les corrections de bugs
- **Sécurité** pour les vulnérabilités corrigées

### Conventions d'émojis
- 🎉 Nouvelle version majeure
- ✨ Nouvelle fonctionnalité
- 🔧 Modification/amélioration
- 🐛 Correction de bug
- 🛡️ Sécurité
- 📚 Documentation
- ⚡ Performance
- 🗑️ Suppression
- ⚠️ Dépréciation 