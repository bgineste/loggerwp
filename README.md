# LoggerWP

Plugin WordPress de journalisation PHP et JavaScript. Conçu pour être déployé en **mu-plugin**, il se charge très tôt dans le cycle WordPress et met à disposition une classe de logging réutilisable sur plusieurs sites.

---

## Objectif

LoggerWP fournit :

- Une classe PHP `BG_Logger` pour écrire des messages dans des fichiers de log (debug, trace, suivi)
- Un logger JavaScript pour capturer les événements console du navigateur
- Un logger JavaScript orienté "tracks" pour suivre des historiques métier
- Une surveillance automatique de la taille des fichiers de log (rotation à 4 Mo)

---

## Structure du plugin

```
loggerwp/
├── loggerwp.php          ← Fichier principal : classe BG_Logger, hooks WordPress, loggers JS
└── js/
    ├── client-logger.js  ← Capture des événements console (errors, warnings, logs)
    └── class-logger.js   ← Logger JS orienté tracks
```

### Répertoires de logs créés automatiquement

À partir du chemin configuré (option `loggerwp_path_logs`, défaut : `logs/`), LoggerWP crée :

```
{ABSPATH}/logs/
├── debug/      ← Logs d'erreurs PHP et de debug
├── tracks/     ← Fichiers de suivi métier
└── console/    ← Trace des événements JavaScript
```

---

## Installation

LoggerWP est conçu pour être utilisé en **mu-plugin avec un loader**. Cette approche permet de contrôler l'ordre de chargement et d'isoler la configuration spécifique à chaque site.

### 1. Déposer le plugin

Copier le dossier `loggerwp/` dans `wp-content/mu-plugins/` :

```
wp-content/mu-plugins/
├── mu-plugins-loader.php
├── loggerwp/
│   └── loggerwp.php
└── site-config/
    └── init-debug-track.php
```

### 2. Créer le loader

WordPress ne charge pas automatiquement les fichiers dans les sous-dossiers de `mu-plugins/`. Il faut un fichier loader à la racine qui orchestre le chargement dans le bon ordre :

```php
<?php
// wp-content/mu-plugins/mu-plugins-loader.php

// 1. LoggerWP en premier — définit la classe BG_Logger et les constantes de chemins
require_once __DIR__ . '/loggerwp/loggerwp.php';

// 2. Initialisation des trackers propres à ce site
require_once __DIR__ . '/site-config/init-debug-track.php';

// 3. Autres mu-plugins éventuels
// require_once __DIR__ . '/autre-plugin/autre-plugin.php';
```

> **Important** : `loggerwp.php` doit impérativement être chargé **avant** `init-debug-track.php`, car ce dernier instancie des objets `BG_Logger` qui dépendent des constantes définies par LoggerWP.

### 3. Créer le fichier d'initialisation des trackers

Ce fichier est **spécifique à chaque site**. Il instancie les objets `BG_Logger` dont le site a besoin :

```php
<?php
// wp-content/mu-plugins/site-config/init-debug-track.php

ini_set('display_errors', '0');

// Tracker de debug général
global $log_php_debug;
$log_php_debug = new BG_Logger('log-php-debug.txt', LOGGERWP_LOGS_DEBUG_PATH, true, true);

// Tracker de trace général
global $log_php_trace;
$log_php_trace = new BG_Logger('log-php-trace.txt', LOGGERWP_LOGS_TRACK_PATH, true, true);
$log_php_trace->log('>>>>>> Init trace');

// Ajouter ici les trackers spécifiques au site...
```

---

## Utilisation

### Logger PHP

#### Instancier un logger

```php
$mon_logger = new BG_Logger(
    'mon-fichier.txt',          // Nom du fichier de log
    LOGGERWP_LOGS_TRACK_PATH,   // Répertoire (utiliser les constantes LoggerWP)
    true,                       // Afficher la date sur chaque entrée
    true                        // Afficher l'IP
);
```

#### Écrire dans le log

```php
// Message simple
$mon_logger->log('Mon message');

// Avec localisation dans le code
$mon_logger->log('Mon message', fichier: basename(__FILE__), ligne: __LINE__);

// Avec indentation (pour structurer visuellement les logs)
$mon_logger->log('Détail imbriqué', indent: 1);

// Sans date ni IP (surcharge ponctuelle)
$mon_logger->log('Séparateur', ligneDate: false, afficherIP: false);
```

#### Autres méthodes

```php
// Récupérer le chemin complet du fichier log
$chemin = $mon_logger->fichier_log();

// Vider le fichier log
$mon_logger->fichier_zap();

// Logger uniquement l'IP
$mon_logger->log_ip();
```

### Constantes disponibles

Définies automatiquement au chargement du plugin :

| Constante | Chemin |
|---|---|
| `LOGGERWP_LOGS_PATH` | Racine des logs |
| `LOGGERWP_LOGS_TRACK_PATH` | Sous-dossier `tracks/` |
| `LOGGERWP_LOGS_DEBUG_PATH` | Sous-dossier `debug/` |
| `LOGGERWP_LOGS_CONSOLE_PATH` | Sous-dossier `console/` |

### Logger JavaScript

Les scripts JS sont automatiquement injectés dans le front-end via `wp_enqueue_scripts`.

**Trace console** (`client-logger.js`) : capture automatiquement les `console.error`, `console.warn` et `console.log` et les envoie vers `logs/console/trace-console.txt`.

**Class logger** (`class-logger.js`) : logger JS à instancier manuellement pour suivre des historiques métier, avec écriture dans `logs/tracks/`.

---

## Configuration

Le chemin racine des logs est configurable via l'option WordPress `loggerwp_path_logs` (défaut : `logs`). Elle peut être modifiée directement en base via un `update_option` ou via une interface d'administration.

---

## Mise à jour

Ce plugin est versionné sur GitHub. Pour mettre à jour un site :

1. Télécharger le ZIP depuis GitHub (`Code > Download ZIP`)
2. Extraire le dossier `loggerwp/`
3. Le déposer via FTP/SFTP dans `wp-content/mu-plugins/` en remplacement de l'existant

Le fichier `init-debug-track.php` n'est pas touché par cette opération.

---

## Notes

- Les fichiers de log sont protégés contre les écritures concurrentes via `flock`
- Une rotation automatique est effectuée : tout fichier dépassant **4 Mo** est renommé en `.bak` horodaté
- La vérification de taille est exécutée au plus une fois par semaine (option `loggerwp_last_log_check`)
