<?php
// config.php - Configurazione aggiornata per il sistema di gestione password
class Config {
    // =====================================
    // CREDENZIALI MICROSOFT GRAPH API
    // =====================================
    // IMPORTANTE: Sostituire con le proprie credenziali Azure App Registration
    const TENANT_ID = 'consumers'; // Per account personali Microsoft
    const CLIENT_ID = 'TUE_CREDENZIALI_CLIENT_ID'; // Sostituire con il proprio Client ID
    const CLIENT_SECRET = 'TUE_CREDENZIALI_CLIENT_SECRET'; // Sostituire con il proprio Client Secret
    
    // =====================================
    // CONFIGURAZIONE FILE EXCEL
    // =====================================
    // ID del file Excel su OneDrive - Trovare tramite Graph Explorer o URL OneDrive
    const EXCEL_FILE_ID = 'F62D90EC-FBDB-498D-A7D3-EB355FE3A4E1'; // Sostituire con l'ID corretto
    
    // Nome del file (solo per riferimento)
    const EXCEL_FILENAME = 'DATI_VP.xlsx';
    
    // =====================================
    // NOMI DEI FOGLI DI LAVORO
    // =====================================
    const SHEET_ANA_TASK = 'ANA_TASK';
    const SHEET_FACT_GIORNATE = 'FACT_GIORNATE';
    const SHEET_ANA_CONSULENTI = 'ANA_CONSULENTI'; // Foglio per la gestione password
    
    // =====================================
    // CONFIGURAZIONE AUTENTICAZIONE LOCALE
    // =====================================
    // Utenti per accesso amministrativo (opzionale)
    const ADMIN_USERS = [
        'admin' => 'admin123', // Cambiare con credenziali sicure
        'supervisor' => 'super456'
    ];
    
    // =====================================
    // CONFIGURAZIONE SICUREZZA PASSWORD
    // =====================================
    // Chiave JWT per token di sessione (generare una chiave sicura)
    const JWT_SECRET = 'a8f5f167f44f4964e6c998dee827110c'; // CAMBIARE CON UNA CHIAVE SICURA
    
    // Configurazione password
    const PASSWORD_MIN_LENGTH = 5;
    const PASSWORD_RESET_LENGTH = 12; // Password di reset più lunghe per sicurezza
    const PASSWORD_HASH_COST = 12; // Costo bcrypt (10-15, più alto = più sicuro ma più lento)
    
    // Durata sessione in secondi (default: 8 ore)
    const SESSION_DURATION = 28800;
    
    // Configurazione crittografia
    const ENCRYPTION_ALGORITHM = PASSWORD_DEFAULT; // bcrypt
    const REQUIRE_STRONG_PASSWORDS = true; // Forza password complesse
    const AUTO_MIGRATE_LEGACY_PASSWORDS = true; // Migra automaticamente password legacy
    
    // Configurazione audit sicurezza
    const LOG_PASSWORD_CHANGES = true;
    const LOG_FAILED_ATTEMPTS = true;
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 1800; // 30 minuti
    
    // =====================================
    // CONFIGURAZIONE EMAIL
    // =====================================
    // Configurazione per l'invio di email di recupero password
    const MAIL_FROM_ADDRESS = 'noreply@vaglioandpartners.com';
    const MAIL_FROM_NAME = 'Sistema DATI_VP';
    const MAIL_REPLY_TO = 'support@vaglioandpartners.com';
    
    // =====================================
    // URL E REDIRECT
    // =====================================
    // URL base dell'applicazione
    const BASE_URL = 'https://vaglioandpartners.com/test/api/';
    
    // URL di callback OAuth (deve essere registrato in Azure)
    const OAUTH_REDIRECT_URI = self::BASE_URL . 'oauth_callback_fixed.php';
    
    // URL autorizzazione
    const OAUTH_AUTHORIZE_URL = self::BASE_URL . 'authorize_excel.php';
    
    // =====================================
    // CONFIGURAZIONE DEBUGGING
    // =====================================
    // Abilitare il debug (disabilitare in produzione)
    const DEBUG_MODE = true;
    
    // Log delle operazioni
    const ENABLE_LOGGING = true;
    const LOG_FILE = __DIR__ . '/logs/password_manager.log';
    
    // =====================================
    // MICROSOFT GRAPH SCOPES
    // =====================================
    const GRAPH_SCOPES = [
        'https://graph.microsoft.com/Files.ReadWrite',
        'offline_access'
    ];
    
    // =====================================
    // METODI DI UTILITÀ
    // =====================================
    
    /**
     * Ottiene l'URL completo per gli scopes Microsoft Graph
     */
    public static function getGraphScopesString() {
        return implode(' ', self::GRAPH_SCOPES);
    }
    
    /**
     * Verifica se il debug è abilitato
     */
    public static function isDebugMode() {
        return self::DEBUG_MODE;
    }
    
    /**
     * Ottiene la configurazione per l'invio email
     */
    public static function getMailConfig() {
        return [
            'from_address' => self::MAIL_FROM_ADDRESS,
            'from_name' => self::MAIL_FROM_NAME,
            'reply_to' => self::MAIL_REPLY_TO
        ];
    }
    
    /**
     * Ottiene la configurazione di sicurezza per le password
     */
    public static function getPasswordSecurityConfig() {
        return [
            'min_length' => self::PASSWORD_MIN_LENGTH,
            'reset_length' => self::PASSWORD_RESET_LENGTH,
            'hash_cost' => self::PASSWORD_HASH_COST,
            'require_strong' => self::REQUIRE_STRONG_PASSWORDS,
            'auto_migrate' => self::AUTO_MIGRATE_LEGACY_PASSWORDS,
            'algorithm' => self::ENCRYPTION_ALGORITHM
        ];
    }
    
    /**
     * Ottiene la configurazione di audit
     */
    public static function getAuditConfig() {
        return [
            'log_changes' => self::LOG_PASSWORD_CHANGES,
            'log_failures' => self::LOG_FAILED_ATTEMPTS,
            'max_attempts' => self::MAX_FAILED_ATTEMPTS,
            'lockout_duration' => self::LOCKOUT_DURATION
        ];
    }
    
    /**
     * Ottiene la configurazione OAuth completa
     */
    public static function getOAuthConfig() {
        return [
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'redirect_uri' => self::OAUTH_REDIRECT_URI,
            'tenant_id' => self::TENANT_ID,
            'scopes' => self::getGraphScopesString()
        ];
    }
    
    /**
     * Verifica se le credenziali sono configurate
     */
    public static function areCredentialsConfigured() {
        return !empty(self::CLIENT_ID) && 
               !empty(self::CLIENT_SECRET) && 
               !empty(self::EXCEL_FILE_ID) &&
               self::CLIENT_ID !== 'TUE_CREDENZIALI_CLIENT_ID' &&
               self::CLIENT_SECRET !== 'TUE_CREDENZIALI_CLIENT_SECRET';
    }
    
    /**
     * Ottiene la configurazione completa come array
     */
    public static function getFullConfig() {
        return [
            'excel' => [
                'file_id' => self::EXCEL_FILE_ID,
                'filename' => self::EXCEL_FILENAME,
                'sheets' => [
                    'consultants' => self::SHEET_ANA_CONSULENTI,
                    'tasks' => self::SHEET_ANA_TASK,
                    'timesheet' => self::SHEET_FACT_GIORNATE
                ]
            ],
            'oauth' => self::getOAuthConfig(),
            'security' => [
                'jwt_secret' => self::JWT_SECRET,
                'session_duration' => self::SESSION_DURATION,
                'password_min_length' => self::PASSWORD_MIN_LENGTH,
                'password_reset_length' => self::PASSWORD_RESET_LENGTH
            ],
            'mail' => self::getMailConfig(),
            'debug' => [
                'enabled' => self::DEBUG_MODE,
                'logging' => self::ENABLE_LOGGING,
                'log_file' => self::LOG_FILE
            ]
        ];
    }
    
    /**
     * Crea la directory dei log se non esiste
     */
    public static function ensureLogDirectory() {
        if (self::ENABLE_LOGGING) {
            $logDir = dirname(self::LOG_FILE);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }
    
    /**
     * Scrive un messaggio nel log
     */
    public static function log($message, $level = 'INFO') {
        if (!self::ENABLE_LOGGING) return;
        
        self::ensureLogDirectory();
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// =====================================
// CONTROLLI DI INIZIALIZZAZIONE
// =====================================

// Verifica che le credenziali siano configurate
if (!Config::areCredentialsConfigured()) {
    if (Config::isDebugMode()) {
        echo '<div style="background:#fff3cd;color:#856404;padding:15px;margin:10px;border-radius:8px;">';
        echo '<h3>⚠️ Configurazione Incompleta</h3>';
        echo '<p>Le credenziali Microsoft Graph non sono state configurate.</p>';
        echo '<p>Aggiorna il file <code>config.php</code> con:</p>';
        echo '<ul>';
        echo '<li><strong>CLIENT_ID:</strong> ID dell\'applicazione Azure</li>';
        echo '<li><strong>CLIENT_SECRET:</strong> Secret dell\'applicazione Azure</li>';
        echo '<li><strong>EXCEL_FILE_ID:</strong> ID del file Excel su OneDrive</li>';
        echo '</ul>';
        echo '</div>';
    }
}

// Inizializza la directory dei log
Config::ensureLogDirectory();

// Log dell'inizializzazione
Config::log('Sistema di gestione password inizializzato');
?>