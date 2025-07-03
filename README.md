# UnicySatellite

Package Laravel pour la communication satellite avec UnicyHub dans l'architecture distribuée "Armageddon".

## 📋 Vue d'ensemble

UnicySatellite permet aux applications satellites (UnicyLogistik, UnicyVinci, UnicyPixel) de communiquer avec UnicyHub central pour :

- 📡 **Enregistrement automatique** auprès d'UnicyHub
- 🔄 **Synchronisation bidirectionnelle** des données (tenants, utilisateurs)
- 📊 **Collecte et envoi de métriques** en temps réel
- 🏥 **Health checks** et monitoring
- 🎮 **Exécution de commandes à distance**
- 🗄️ **Gestion du cache distribué**

## 🚀 Installation

### 1. Ajout du package

```bash
# Dans votre application satellite (UnicyLogistik, UnicyVinci, UnicyPixel)
composer require unicy/unicysatellite
```

### 2. Publication des fichiers

```bash
# Publier la configuration
php artisan vendor:publish --tag="satellite-config"

# Publier les migrations (optionnel)
php artisan vendor:publish --tag="satellite-migrations"

# Publier les routes (optionnel)
php artisan vendor:publish --tag="satellite-routes"
```

### 3. Configuration

Ajouter dans votre `.env` :

```env
# Configuration UnicyHub Central
UNICYHUB_URL=https://hub.unicy.io
UNICYHUB_API_KEY=your-api-key-here

# Configuration Satellite
SATELLITE_NAME="UnicyLogistik"
SATELLITE_TYPE=logistik
SATELLITE_VERSION=1.0.0
SATELLITE_URL=https://logistik.unicy.io

# Options (facultatives)
SATELLITE_SYNC_ENABLED=true
SATELLITE_METRICS_ENABLED=true
SATELLITE_HEALTH_ENABLED=true
```

### 4. Enregistrement

```bash
# Enregistrer le satellite auprès d'UnicyHub
php artisan satellite:register
```

## 🎯 Utilisation

### Commandes Artisan

```bash
# Enregistrement
php artisan satellite:register [--force] [--test]

# Synchronisation
php artisan satellite:sync [--type=all|tenants|users] [--dry-run]

# Envoi de métriques
php artisan satellite:metrics [--show] [--detailed]
```

### Utilisation programmatique

```php
use UnicySatellite\Facades\SatelliteHub;

// Enregistrement
if (SatelliteHub::shouldRegister()) {
    $result = SatelliteHub::registerSatellite();
}

// Synchronisation
SatelliteHub::syncData($data, 'tenants');

// Métriques
$metrics = app(MetricsCollectorService::class)->collectAll();
SatelliteHub::sendMetrics($metrics);

// Health check
$health = SatelliteHub::healthCheck();
```

### Endpoints API automatiques

Le package expose automatiquement ces endpoints :

- `GET /api/satellite/health` - Health check
- `GET /api/satellite/status` - Statut du satellite
- `GET /api/satellite/metrics` - Métriques en temps réel
- `POST /api/satellite/commands` - Exécution de commandes
- `POST /api/satellite/updates` - Réception de mises à jour
- `DELETE /api/satellite/cache` - Gestion du cache
- `GET /api/satellite/info` - Informations détaillées

## 📊 Métriques collectées

### Métriques générales
- Nombre d'utilisateurs
- Nombre de tenants
- Sessions actives
- Utilisation mémoire/CPU/disque

### Métriques UnicyLogistik
- Commandes du jour/en attente
- Expéditions actives

### Métriques UnicyVinci
- Courtiers actifs
- Jobs du jour/en attente

### Métriques UnicyPixel
- QR codes générés
- Scans du jour

## 🔧 Configuration avancée

### Métriques personnalisées

```php
// config/satellite.php
'metrics' => [
    'include' => [
        'users_count' => true,
        'custom_metric' => true,
    ],
],
```

### Health checks personnalisés

```php
// Dans votre ServiceProvider
use UnicySatellite\Services\SatelliteHubService;

$hubService = app(SatelliteHubService::class);
$hubService->addHealthCheck('custom', function() {
    // Votre logique de health check
    return ['status' => 'healthy', 'message' => 'Custom check OK'];
});
```

### Sécurité

```php
// config/satellite.php
'security' => [
    'rate_limit' => 100, // requêtes par minute
    'ip_whitelist' => '192.168.1.0/24,10.0.0.1',
    'verify_ssl' => true,
],
```

## 🔄 Synchronisation automatique

Le package configure automatiquement les tâches cron :

```php
// Synchronisation toutes les 5 minutes
$schedule->command('satellite:sync')->everyFiveMinutes();

// Métriques toutes les minutes
$schedule->command('satellite:metrics')->everyMinute();
```

## 🛡️ Sécurité

- **Authentification API** via Bearer token
- **Rate limiting** configurable
- **IP whitelisting** avec support CIDR
- **Validation SSL** obligatoire en production
- **Commandes limitées** pour l'exécution à distance

## 📁 Structure du package

```
UnicySatellite/
├── config/
│   └── satellite.php
├── src/
│   ├── Commands/
│   ├── Exceptions/
│   ├── Facades/
│   │   ├── Controllers/
│   │   ├── Integrations/
│   │   └── Requests/
│   ├── Middleware/
│   ├── Providers/
│   └── Services/
├── routes/
│   └── api.php
└── README.md
```

## 🧪 Tests

```bash
# Tester la configuration
php artisan satellite:register --test

# Tester la synchronisation
php artisan satellite:sync --dry-run

# Afficher les métriques
php artisan satellite:metrics --show --detailed
```

## 🔍 Debugging

```bash
# Vérifier les logs
tail -f storage/logs/laravel.log | grep -i satellite

# Vérifier la santé
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-satellite.com/api/satellite/health

# Vérifier les métriques
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-satellite.com/api/satellite/metrics
```

## 🤝 Support

Pour toute question ou problème :

1. Vérifiez la configuration dans `.env`
2. Consultez les logs Laravel
3. Testez la connectivité avec UnicyHub
4. Vérifiez les permissions API

## 📝 Changelog

### v1.0.0
- 🎉 Version initiale
- 📡 Communication avec UnicyHub via Saloon
- 🔄 Synchronisation bidirectionnelle
- 📊 Collecte de métriques temps réel
- 🏥 Health monitoring
- 🎮 Commandes à distance
- 🛡️ Sécurité complète

---

**UnicySatellite** - Partie intégrante de l'architecture distribuée "Armageddon" 🚀 