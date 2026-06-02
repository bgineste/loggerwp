<?php
/*
Plugin Name: LoggerWP
Description: Plugin de journalisation JS/PHP avec interface d'administration.
Version: 2.0.2
Author: BG
*/


/*================================================================================
 Classe BG_Logger
=================================================================================*/

    class BG_Logger {
        private string $nomFicLog;
        private string $cheminFicLog;
        private bool $ligneDate;
        private bool $afficherIP;
        private string $IP;

        public function __construct(string $nomFicLog, string $cheminFicLog, bool $ligneDate = true, bool $afficherIP = false) {
            $this->nomFicLog    = $nomFicLog;
            $this->cheminFicLog = rtrim($cheminFicLog, '/');
            $this->ligneDate    = $ligneDate;
            $this->afficherIP   = $afficherIP;
            $this->IP           = $afficherIP ? ($_SERVER['REMOTE_ADDR'] ?? '') : '';
        }

        public function log(string $texte, ?bool $ligneDate = null, ?bool $afficherIP = null, bool $bref = false, string $fichier = '', string $ligne = '', int $indent = 0): void {
            $tab = 4;
            $nf  = $this->cheminFicLog . '/' . $this->nomFicLog;

            $timeout = 5;
            $start   = time();
            $f       = fopen($nf, 'a+');

            if ($f) {
                $locked = false;
                while (!($locked = flock($f, LOCK_EX | LOCK_NB))) {
                    if ((time() - $start) >= $timeout) {
                        error_log("Timeout : impossible d'obtenir le verrou sur le fichier log " . $nf);
                        fclose($f);
                        return;
                    }
                    usleep(100000);
                }
            } else {
                error_log("Échec de l'ouverture du fichier log " . $nf);
                return;
            }

            fwrite($f, "\n" . str_repeat(' ', $indent * $tab) . '** ');

            $ld = (!is_null($ligneDate)) ? $ligneDate : $this->ligneDate;
            if ($ld) {
                fwrite($f, date('d/m/Y H:i:s'));
            }

            $aIP = (!is_null($afficherIP)) ? $afficherIP : $this->afficherIP;
            if ($aIP) {
                fwrite($f, ' ' . $this->IP);
            }
            if ($ld || $aIP) {
                fwrite($f, "\n");
            }

            if (!empty($fichier)) {
                fwrite($f, '[PHP] ' . $fichier . ' (' . $ligne . ")\n");
            }

            fwrite($f, $texte);
            if (!$bref) {
                fwrite($f, "\n");
            }

            flock($f, LOCK_UN);
            fclose($f);
        }

        public function log_ip(): void {
            $nf = $this->cheminFicLog . '/' . $this->nomFicLog;
            $f  = fopen($nf, 'a+');

            if (!$f) {
                error_log("Échec de l'ouverture du fichier " . $nf);
                return;
            }

            if ($this->ligneDate) {
                fwrite($f, '** ' . date('d/m/Y H:i:s') . "\n");
            }
            fwrite($f, $this->IP . "\n");
            fclose($f);
        }

        public function fichier_log(): string {
            return $this->cheminFicLog . '/' . $this->nomFicLog;
        }

        public function fichier_zap(): void {
            $nf = $this->fichier_log();
            $f  = fopen($nf, 'w');
            if ($f) fclose($f);
        }
    }


/*================================================================================
 Initialisation (hooks WordPress)
=================================================================================*/

// On repasse dans le namespace global pour les hooks WordPress
//namespace {

    if (!defined('ABSPATH')) exit;


//    add_action('init', 'loggerwp_init');

    loggerwp_init();

    function loggerwp_init(): void {

        // --- Chemin des logs ---
        $path_logs = get_option('loggerwp_path_logs');
        //echo "Current logger path: " . ($path_logs ?? 'not set') . "\n";
        if (!$path_logs) {
            $path_logs = 'logs';
            update_option('loggerwp_path_logs', 'logs');
        }

        define('LOGGERWP_LOGS_PATH',    ABSPATH . $path_logs . '/');
        define('LOGGERWP_LOGS_TRACK_PATH',   LOGGERWP_LOGS_PATH . 'tracks/');
        define('LOGGERWP_LOGS_DEBUG_PATH',   LOGGERWP_LOGS_PATH . 'debug/');
        define('LOGGERWP_LOGS_CONSOLE_PATH', LOGGERWP_LOGS_PATH . 'console/');

        // --- Création des dossiers ---
        foreach ([LOGGERWP_LOGS_PATH, LOGGERWP_LOGS_TRACK_PATH, LOGGERWP_LOGS_DEBUG_PATH, LOGGERWP_LOGS_CONSOLE_PATH] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        //echo "Log directories ensured.\n";

        // --- Configuration error_log PHP ---
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', LOGGERWP_LOGS_DEBUG_PATH . 'log-erreurs-php-général.txt');

        // --- Suivi taille des fichiers logs ---
        loggerwp_maybe_run_weekly_log_check();
    }

    /*================================================================================
    Surveillance taille des fichiers logs
    =================================================================================*/

    function loggerwp_maybe_run_weekly_log_check(): void {
        $last_run = get_option('loggerwp_last_log_check');

        if (!$last_run || (time() - $last_run) > WEEK_IN_SECONDS) {
            loggerwp_check_file_size();
            update_option('loggerwp_last_log_check', time());
        }
    }

    function loggerwp_check_file_size(): void {
        $suivi = new BG_Logger('suivi_fichiers_logs.txt', LOGGERWP_LOGS_TRACK_PATH, true, false);
        $suivi->log('===== Vérification taille fichiers logs', ligneDate: true, afficherIP: false);

        $dirs      = [LOGGERWP_LOGS_TRACK_PATH, LOGGERWP_LOGS_DEBUG_PATH, LOGGERWP_LOGS_CONSOLE_PATH];
        $max_bytes = 1024 * 1024 * 4; // 4 Mo

        foreach ($dirs as $un_dir) {
            $suivi->log('----- Répertoire : ' . $un_dir, ligneDate: false, afficherIP: false);
            foreach (glob($un_dir . '*') as $log_file) {
                if (strtolower(pathinfo($log_file, PATHINFO_EXTENSION)) === 'bak') continue;
                if (!is_file($log_file)) continue;

                $size = filesize($log_file);
                $suivi->log($log_file . ' — ' . $size . ' octets', ligneDate: false, afficherIP: false, indent: 1);

                if ($size > $max_bytes) {
                    rename($log_file, $log_file . '.' . date('Ymd_His') . '.bak');
                }
            }
        }
    }

    /*================================================================================
    Logger JavaScript — trace console
    =================================================================================*/

    add_action('wp_enqueue_scripts', 'loggerwp_enqueue_console_script');
    function loggerwp_enqueue_console_script(): void {
        wp_enqueue_script('loggerwp-client-logger', plugin_dir_url(__FILE__) . 'js/client-logger.js', [], null, true);
        wp_localize_script('loggerwp-client-logger', 'CCL_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'user_id'  => get_current_user_id(),
        ]);
    }

    add_action('wp_ajax_nopriv_ccl_log', 'loggerwp_handle_console_log');
    add_action('wp_ajax_ccl_log',        'loggerwp_handle_console_log');

    function loggerwp_handle_console_log(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            wp_send_json_error('Invalid data', 400);
        }

        $timestamp  = date('c');
        $level      = sanitize_text_field($data['level']     ?? 'log');
        $message    = sanitize_text_field($data['message']   ?? '');
        $user_id    = intval($data['userId']                 ?? 0);
        $url        = esc_url_raw($data['url']               ?? '');
        $referrer   = esc_url_raw($data['referrer']          ?? '');
        $user_agent = sanitize_text_field($data['userAgent'] ?? 'unknown');

        $line = "[$timestamp] [user:$user_id] [$level] [$url] [$referrer] [$user_agent] $message\n";
        file_put_contents(LOGGERWP_LOGS_CONSOLE_PATH . 'trace-console.txt', $line, FILE_APPEND);

        wp_send_json_success('Log saved');
    }

    /*================================================================================
    Logger JavaScript — class-logger (tracks)
    =================================================================================*/

    add_action('wp_enqueue_scripts', 'loggerwp_enqueue_class_logger_script');

    function loggerwp_enqueue_class_logger_script(): void {
        wp_enqueue_script('loggerwp-class-logger', plugin_dir_url(__FILE__) . 'js/class-logger.js', ['jquery'], '1.0', true);
        wp_localize_script('loggerwp-class-logger', 'cl_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    add_action('wp_ajax_cl_log_message',        'loggerwp_handle_class_logger_request');
    add_action('wp_ajax_nopriv_cl_log_message', 'loggerwp_handle_class_logger_request');
//echo "Hooks for class logger js set.\n";

    function loggerwp_handle_class_logger_request(): void {
        if (!isset($_POST['message'], $_POST['logFile'], $_POST['ligneDate'], $_POST['afficherIP'])) {
            wp_send_json_error('Paramètres manquants.');
        }

        $message   = sanitize_text_field($_POST['message']);
        $logFile   = sanitize_file_name($_POST['logFile']);
        $ligneDate = $_POST['ligneDate'];
        $afficherIP = $_POST['afficherIP'];

        $filePath = LOGGERWP_LOGS_TRACK_PATH . $logFile;

        $timeout = 5;
        $start   = time();
        $f       = fopen($filePath, 'a+');

        if (!$f) {
            wp_send_json_error("Erreur lors de l'ouverture du fichier de log.");
        }

        $locked = false;
        while (!($locked = flock($f, LOCK_EX | LOCK_NB))) {
            if ((time() - $start) >= $timeout) {
                error_log("Timeout verrou fichier log JS : " . $filePath);
                fclose($f);
                return;
            }
            usleep(100000);
        }

        fwrite($f, '[JS]');
        if ($ligneDate) {
            fwrite($f, '** ' . date('d/m/Y H:i:s') . "\n");
        }
        $mess  = $afficherIP ? 'IP - ' . $_SERVER['REMOTE_ADDR'] . ' ' : '';
        $mess .= '"' . $message . '"';
        fwrite($f, $mess . "\n");

        flock($f, LOCK_UN);
        fclose($f);

        wp_send_json_success('Message logué avec succès.');
    }
//    echo "Handlers for class logger AJAX set.\n";
//    echo "LOGGERWP_LOGS_DEBUG_PATH : " . (defined('LOGGERWP_LOGS_DEBUG_PATH') ? LOGGERWP_LOGS_DEBUG_PATH : 'non défini') . "\n";

//}