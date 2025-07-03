# Changelog - UnicySatellite

Toutes les modifications notables de ce package seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versioning Sémantique](https://semver.org/spec/v2.0.0.html).

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