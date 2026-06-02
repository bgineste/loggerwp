<?php
/*
Plugin Name: LoggerWP
Description: Plugin de journalisation JS/PHP avec interface d’administration.
Version: 1.4.0
Author: BG
*/

register_activation_hook(__FILE__, function () {
    setcookie('ccl_plugin_active', '1', time() + (10 * 365 * 24 * 3600), "/");
});

if (!defined('ABSPATH')) exit;

global $path_logs;


$path_logs = get_option('pathlogs');
if (!$path_logs) {
	$path_logs = "logs"; // pour être certain que ça fonctionne
	update_option('pathlogs','logs');
}

// Chemin global pour les logs JS
define('LOGGERWP_LOGS_PATH', ABSPATH . $path_logs .'/');
define('LOGGERWP_LOGS_TRACK_PATH', ABSPATH . $path_logs .'/tracks/');
define('LOGGERWP_LOGS_DEBUG_PATH', ABSPATH . $path_logs .'/debug/');
define('LOGGERWP_LOGS_CONSOLE_PATH', ABSPATH . $path_logs .'/console/');


// Crée les répertoires s’ils n’existent pas
if (!file_exists(LOGGERWP_LOGS_PATH)) {
	mkdir(LOGGERWP_LOGS_PATH, 0755, true);
}
if (!file_exists(LOGGERWP_LOGS_TRACK_PATH)) {
	mkdir(LOGGERWP_LOGS_TRACK_PATH, 0755, true);
}
if (!file_exists(LOGGERWP_LOGS_DEBUG_PATH)) {
	mkdir(LOGGERWP_LOGS_DEBUG_PATH, 0755, true);
}
if (!file_exists(LOGGERWP_LOGS_CONSOLE_PATH)) {
	mkdir(LOGGERWP_LOGS_CONSOLE_PATH, 0755, true);
}

/*=================================================================================
 Débogage scripts php
=================================================================================*/

// Rapports de toutes les erreurs PHP (fonctionne avec un serveur local)
error_reporting(E_ALL); 
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOGGERWP_LOGS_DEBUG_PATH.'log-erreurs-php-général.txt');
//error_log('Début');


/*
=====================================================================================================
Suivi Javascript
=====================================================================================================
*/

/*
----------------------------------------------------------------------------------------------------------------------------------
Trace des évènements Javascript (erreurs, warnings, logs)
----------------------------------------------------------------------------------------------------------------------------------
*/

// Injecte le script JS dans les pages

add_action('wp_enqueue_scripts', 'ccl_enqueue_script');

function ccl_enqueue_script() {
    wp_enqueue_script('ccl-client-logger', plugin_dir_url(__FILE__) . 'js/client-logger.js', [], null, true);
    wp_localize_script('ccl-client-logger', 'CCL_Ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
		'user_id' => get_current_user_id(),
    ]);
}

// Gère la requête AJAX (non connecté)
add_action('wp_ajax_nopriv_ccl_log', 'ccl_handle_log');
add_action('wp_ajax_ccl_log', 'ccl_handle_log');

function ccl_handle_log() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        wp_send_json_error('Invalid data', 400);
    }

    $timestamp = date('c');
    $level = sanitize_text_field($data['level'] ?? 'log');
    $message = sanitize_text_field($data['message'] ?? '');
//    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
	$user_id = intval($data['userId'] ?? 0);
	$url = esc_url_raw($data['url'] ?? '');
	$referrer = esc_url_raw($data['referrer'] ?? '');
	$user_agent = sanitize_text_field($data['userAgent'] ?? 'unknown');

//    $line = "[$timestamp] [$level] [$user_agent] $message\n";
	$line = "[$timestamp] [user:$user_id] [$level] [$url] [$referrer] [$user_agent] $message\n";
    file_put_contents(LOGGERWP_LOGS_CONSOLE_PATH. 'trace-console.txt', $line, FILE_APPEND);

    wp_send_json_success('Log saved');
}

/*
----------------------------------------------------------------------------------------------------------------------------------
Logger Javascript
----------------------------------------------------------------------------------------------------------------------------------
*/
// Utilisation principale : suivis d'historiques 
// Pour les simples messages, on peut utiliser console.log qui est capté par la trace des évènements javascript

// Enqueue du script JavaScript
function cl_enqueue_class_logger_script() {
    // Enregistre le script JavaScript
    wp_enqueue_script('class-logger-js', plugin_dir_url(__FILE__) . 'js/class-logger.js', array('jquery'), '1.0', true);

    // Passe l'URL du fichier PHP de gestion des logs à JavaScript
    wp_localize_script('class-logger-js', 'cl_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'cl_enqueue_class_logger_script');

// Gestion de l'endpoint AJAX pour l'enregistrement du log
function cl_handle_class_logger_request() {
    if (isset($_POST['message']) && isset($_POST['logFile']) && isset($_POST['ligneDate']) && isset($_POST['afficherIP'])) {
        $message = sanitize_text_field($_POST['message']);
        $logFile = sanitize_file_name($_POST['logFile']);
//		$ligneDate = sanitize_text_field($_POST['ligneDate']);
//		$afficherIP = sanitize_text_field($_POST['afficherIP']);
		$ligneDate = $_POST['ligneDate'];
		$afficherIP = $_POST['afficherIP'];

        // Crée le chemin vers le fichier dans le dossier 'js-logs' à la racine du site
        $filePath = LOGGERWP_LOGS_TRACK_PATH . $logFile;

        // Ouvre le fichier en mode 'append' pour y écrire le message
		$timeout = 5; // secondes
		$start = time();
		$f = fopen($filePath, 'a+');

		if ($f) {
			$locked = false;
			while (!( $locked = flock($f, LOCK_EX | LOCK_NB) )) {
				if ((time() - $start) >= $timeout) {
					error_log("Timeout : impossible d'obtenir le verrou sur le fichier log ".$nf);
					fclose($f);
					return;
				}
				usleep(100000); // 100ms
			}
		} else {
			wp_send_json_error("Erreur lors de l'ouverture du fichier de log.");
		}
		
		fwrite($f, "[JS]");

        if ($ligneDate) {
            fwrite($f, "** " . date("d/m/Y H:i:s"));
            fwrite($f, "\n");
        }
		$mess = ($afficherIP) ? "IP"." - ". $_SERVER['REMOTE_ADDR']." " : "";
		$mess .= '"' . $message . '"';
        fwrite($f, $mess . "\n");
		flock($f, LOCK_UN);
        fclose($f);

        wp_send_json_success("Message logué avec succès.");
    } else {
        wp_send_json_error("Paramètres manquants.");
    }
}
add_action('wp_ajax_cl_log_message', 'cl_handle_class_logger_request');
add_action('wp_ajax_nopriv_cl_log_message', 'cl_handle_class_logger_request');


/*
=====================================================================================================
Logger PHP
=====================================================================================================
*/


/*-------------------------------------------------------------------------------------------------------------------------
Fichiers de log
--------------------------------------------------------------------------------------------------------------------------*/

class Logger {
    private $nomFicLog;
    private $cheminFicLog;
    private $ligneDate;
	private $afficherIP;
	private $IP;
	private $test = false;

    // Constructeur pour initialiser les paramètres par défaut
    public function __construct($nomFicLog, $cheminFicLog, $ligneDate, $afficherIP) {
        $this->nomFicLog = $nomFicLog;
		//$this->cheminFicLog = pathAbsolu($cheminFicLog);
		$this->cheminFicLog = $cheminFicLog;
		
		if ($this->test) var_dump($this->cheminFicLog);
        $this->ligneDate = $ligneDate;
		$this->afficherIP = $afficherIP;
		$this->IP = $afficherIP ? $_SERVER['REMOTE_ADDR']: "";
    }

    // Méthode pour écrire dans le fichier de log
    public function log($texte, $ligneDate=null, $afficherIP=null, $bref=false, $fichier="", $ligne="", $indent=0) {
        // paramètres $fichier = $file=basename(__FILE__), $ligne=__LINE__
        $tab = 4; // constante nombre de caractères d'une indentation
        $nf = $this->cheminFicLog."/".$this->nomFicLog;
		
		$timeout = 5; // secondes
		$start = time();
		$f = fopen($nf, 'a+');

		if ($f) {
			$locked = false;
			while (!( $locked = flock($f, LOCK_EX | LOCK_NB) )) {
				if ((time() - $start) >= $timeout) {
					error_log("Timeout : impossible d'obtenir le verrou sur le fichier log ".$nf);
					fclose($f);
					return;
				}
				usleep(100000); // 100ms
			}
		} else {
			error_log("Échec de l'ouverture du fichier log ".$nf);
			return;
		}
		
        fwrite($f, "\n".str_repeat(" ", $indent * $tab)."** ");

        $ld = (!is_null($ligneDate)) ? $ligneDate : $this->ligneDate;

        if ($ld) {
            fwrite($f,  date("d/m/Y H:i:s"));
        //    fwrite($f, "\n");
        }
        $aIP = (!is_null($afficherIP)) ? $afficherIP : $this->afficherIP;
		//$mess = ($aIP) ? $this->IP." - " : "";
        if ($aIP) {
            fwrite($f, " " . $this->IP);
        }
        if ($ld || $aIP) {
            fwrite($f,"\n");
        }
	
    	//$xfile = (is_null($file) ? "" : $file);
        //$xline = (is_null($line) ? "" : "(".$line.")");
        if (!empty($fichier)) {
            fwrite($f, "[PHP] ".$fichier." (".$ligne.")\n");
        }
        fwrite($f,$texte);
        if (!$bref) {
			fwrite($f, "\n");
		}
		flock($f, LOCK_UN);
        fclose($f);
    }

    // Méthode pour tracer les ip
    public function log_ip() {
        $nf = $this->cheminFicLog."/".$this->nomFicLog;
		//var_dump($nf);
        $f = fopen($nf, "a+");

        if (!$f) {
            error_log("Échec de l'ouverture du fichier ".$nf);
            return;
        }

        if ($this->ligneDate) {
            fwrite($f, "** " . date("d/m/Y H:i:s"));
            fwrite($f, "\n");
        }
		$mess = $this->IP;
		fwrite($f, $mess."\n");

        fclose($f);
    }

	// Méthode pour récupérer le fichier log avec son path
	public function fichier_log() {
		return $this->cheminFicLog."/".$this->nomFicLog;
	}

	// Méthode pour zapper le fichier log
	public function fichier_zap() {
        $nf = $this->cheminFicLog."/".$this->nomFicLog;
		$f = fopen($nf, 'w');
		if ($f) {
			fclose($f);
		}
	}
}




//=================================================================================

/*
	surveiller la taille des fichiers
    (Cette séquence est positionnée en fin de fichier car elle utilise la class $logger et la constante LOGGERWP_LOGS_TRACK_PATH)
*/

global $suivi_logs;
$suivi_logs = new Logger("suivi_fichiers_logs.txt", LOGGERWP_LOGS_TRACK_PATH, ligneDate: true, afficherIP: true);
$suivi_logs->log("===== Début logging");

add_action('init', 'ccl_maybe_run_weekly_log_check');

function ccl_maybe_run_weekly_log_check() {

    ccl_check_file_size(); // à virer
    delete_option('ccl_last_log_check'); // à virer

    // Récupère la dernière date d’exécution
    $last_run = get_option('ccl_last_log_check');

    // S’il n’y a jamais eu d’exécution ou si plus d’une semaine s’est écoulée
    if (!$last_run || (time() - $last_run) > WEEK_IN_SECONDS) {

        // Exécute la vérification
        ccl_check_file_size();

        // Enregistre la date actuelle
        update_option('ccl_last_log_check', time());
    }
}

function ccl_check_file_size() {
    global $suivi_logs;

    $suivi_logs->log("=====",ligneDate: false,afficherIP: false);
    $suivi_logs->log('Liste des fichiers de debug et de trace', ligneDate: true, afficherIP: true, fichier: basename(__FILE__), ligne: __LINE__);
    if (!isset($_COOKIE['ccl_plugin_active']) || $_COOKIE['ccl_plugin_active'] !== '1') {
        //wp_send_json_error(['error' => 'Non autorisé'], 403);
        $suivi_logs->log("check-file-size non autorisé");
    }

    $dirs = [
        LOGGERWP_LOGS_TRACK_PATH,
        LOGGERWP_LOGS_DEBUG_PATH,
        LOGGERWP_LOGS_CONSOLE_PATH
    ];
    $max_bytes = 1024 * 1024 * 4; // 4 Mo
    $suivi_logs->log("max : ".$max_bytes);
    //$results = [];

    foreach ($dirs as $un_dir) {
        // Lister tous les fichiers du répertoire (ignorer les sous-dossiers)
		$suivi_logs->log("----- Répertoire : " . $un_dir, ligneDate: false, afficherIP: false);
        foreach (glob($un_dir . '*') as $log_file) {
            if (strtolower(pathinfo($log_file, PATHINFO_EXTENSION)) === 'bak') continue;
			//$suivi_logs->log("$log_file,true");
            $aff = $log_file;
            if (is_file($log_file)) {
				
                $size = filesize($log_file);
                //$suivi_logs->log(" " . $size,true);
                $aff .= " taille : " . $size. " octets";
                $suivi_logs->log($aff, ligneDate: false, afficherIP: false, indent: 1);
                if ($size > $max_bytes) {
                    //$suivi_logs->log("trop gros");
                    rename($log_file, $log_file . '.' . date('Ymd_His') . '.bak');
                }
            }
        }
    }

//    wp_send_json($results);
}



/*add_action('admin_enqueue_scripts', 'ccl_enqueue_admin_notice_script'); // pour l'admin
add_action('wp_enqueue_scripts', 'ccl_enqueue_admin_notice_script'); // pour le front si tu veux aussi

function ccl_enqueue_admin_notice_script() {
    $is_admin = current_user_can('manage_options');
    $has_cookie = isset($_COOKIE['ccl_plugin_active']);

    if ($is_admin || $has_cookie) {
        wp_enqueue_script('ccl-file-checker', plugin_dir_url(__FILE__) . 'js/file-checker.js', [], null, true);
        wp_localize_script('ccl-file-checker', 'CCL_FileCheck', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
}

add_action('wp_ajax_ccl_check_file_size', 'ccl_check_file_size');
add_action('wp_ajax_nopriv_ccl_check_file_size', 'ccl_check_file_size');

function ccl_check_file_size() {
    global $suivi_logs;

    $suivi_logs->log("=====",ligneDate: false,afficherIP: false);
    $suivi_logs->log('Liste des fichiers de debug et de trace', ligneDate: true, afficherIP: true, fichier: basename(__FILE__), ligne: __LINE__);
    if (!isset($_COOKIE['ccl_plugin_active']) || $_COOKIE['ccl_plugin_active'] !== '1') {
        wp_send_json_error(['error' => 'Non autorisé'], 403);
    }

    $dirs = [
        LOGGERWP_LOGS_TRACK_PATH,
        LOGGERWP_LOGS_DEBUG_PATH,
        LOGGERWP_LOGS_CONSOLE_PATH
    ];
    $max_bytes = 1024 * 1024 * 20; // 20 Mo
    $results = [];

    foreach ($dirs as $un_dir) {
        // Lister tous les fichiers du répertoire (ignorer les sous-dossiers)
		$suivi_logs->log("----- Répertoire : " . $un_dir, ligneDate: false, afficherIP: false);
        foreach (glob($un_dir . '*') as $log_file) {
			//$suivi_logs->log("$log_file,true");
            $aff = $log_file;
            if (is_file($log_file)) {
				
                $size = filesize($log_file);
                //$suivi_logs->log(" " . $size,true);
                $aff .= " taille : " . $size. " octets";
                $suivi_logs->log($aff, ligneDate: false, afficherIP: false, indent: 1);
                if ($size > $max_bytes) {
                    $results[] = [
                        'exists' => true,
                        'tooLarge' => true,
                        'size' => $size,
                        'humanSize' => size_format($size, 2),
                        'filename' => basename($log_file),
                        'path' => $log_file,
                    ];
                    rename($log_file, $log_file . '.' . date('Ymd_His') . '.bak');
                }
            }
        }
    }

    wp_send_json($results);
}
*/
