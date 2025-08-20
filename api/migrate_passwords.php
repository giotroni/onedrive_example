<?php
// migrate_passwords.php - Endpoint migrazione con gestione automatica token
session_start();
require_once 'config.php';
require_once 'microsoft_graph_personal.php';
require_once 'password_security.php';
require_once 'token_manager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class PasswordMigrationTool {
    private $graph;
    private $adminPassword = 'admin123'; // CAMBIARE CON PASSWORD SICURA
    
    public function __construct() {
        // Ottiene informazioni sui token
    public function getTokenStatus() {
        return TokenManager::getTokenInfo();
    }
}

// Gestione richieste
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    $migrationTool = new PasswordMigrationTool();
    
    switch ($action) {
        case 'authenticate':
            if (!isset($input['password'])) {
                throw new Exception('Password mancante');
            }
            
            $result = $migrationTool->authenticateAdmin($input['password']);
            echo json_encode($result);
            break;
            
        case 'analyze':
            $result = $migrationTool->analyzePasswords();
            echo json_encode($result);
            break;
            
        case 'migrate':
            $dryRun = $input['dry_run'] ?? false;
            $result = $migrationTool->migratePasswords($dryRun);
            echo json_encode($result);
            break;
            
        case 'check-auth':
            if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
                throw new Exception('Non autenticato');
            }
            
            // Verifica anche lo stato del token durante il check
            try {
                TokenManager::ensureValidToken();
                $tokenInfo = $migrationTool->getTokenStatus();
                
                echo json_encode([
                    'success' => true,
                    'authenticated' => true,
                    'expires' => $_SESSION['admin_auth_time'] + 3600,
                    'token_info' => $tokenInfo
                ]);
            } catch (Exception $e) {
                throw new Exception('Sessione valida ma token scaduto: ' . $e->getMessage());
            }
            break;
            
        case 'logout':
            unset($_SESSION['admin_authenticated']);
            unset($_SESSION['admin_auth_time']);
            echo json_encode([
                'success' => true,
                'message' => 'Logout effettuato'
            ]);
            break;
            
        case 'refresh-token':
            // Endpoint per refresh manuale token
            try {
                TokenManager::forceRefresh();
                $tokenInfo = TokenManager::getTokenInfo();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Token refreshato con successo',
                    'token_info' => $tokenInfo
                ]);
            } catch (Exception $e) {
                throw new Exception('Impossibile refreshare il token: ' . $e->getMessage());
            }
            break;
            
        case 'check-token':
            // Endpoint per verifica stato token
            try {
                TokenManager::ensureValidToken();
                $tokenInfo = TokenManager::getTokenInfo();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Token valido',
                    'token_info' => $tokenInfo
                ]);
            } catch (Exception $e) {
                throw new Exception('Token non valido: ' . $e->getMessage());
            }
            break;
            
        default:
            throw new Exception('Azione non riconosciuta');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'requires_reauth' => strpos($e->getMessage(), 'Riautorizzazione richiesta') !== false ||
                           strpos($e->getMessage(), 'Token di accesso non valido') !== false
    ]);
}
?> Usa TokenManager per gestione automatica token
        try {
            TokenManager::ensureValidToken();
            $this->graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
        } catch (Exception $e) {
            throw new Exception('Errore autenticazione: ' . $e->getMessage());
        }
    }
    
    // Wrapper per operazioni con retry automatico token
    private function executeWithTokenRetry($operation) {
        return TokenManager::apiCallWithRetry($operation, 2);
    }
    
    // Verifica autenticazione amministratore
    public function authenticateAdmin($password) {
        if ($password !== $this->adminPassword) {
            Config::log('Tentativo di accesso amministratore fallito', 'WARNING');
            throw new Exception('Password amministratore non corretta');
        }
        
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_auth_time'] = time();
        Config::log('Amministratore autenticato con successo', 'INFO');
        
        return [
            'success' => true,
            'message' => 'Autenticazione amministratore riuscita',
            'expires' => time() + 3600 // 1 ora
        ];
    }
    
    // Verifica se admin è autenticato
    private function isAdminAuthenticated() {
        return isset($_SESSION['admin_authenticated']) && 
               $_SESSION['admin_authenticated'] === true &&
               isset($_SESSION['admin_auth_time']) &&
               (time() - $_SESSION['admin_auth_time']) < 3600; // 1 ora di validità
    }
    
    // Analizza le password con gestione automatica token
    public function analyzePasswords() {
        if (!$this->isAdminAuthenticated()) {
            throw new Exception('Autenticazione amministratore richiesta');
        }
        
        return $this->executeWithTokenRetry(function() {
            try {
                $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
                
                if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                    throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
                }
                
                $rows = $sheetData['values'];
                $headers = array_shift($rows);
                
                $emailIndex = array_search('Email', $headers);
                $pwdIndex = array_search('PWD', $headers);
                
                if ($emailIndex === false || $pwdIndex === false) {
                    throw new Exception('Colonne Email o PWD non trovate');
                }
                
                $analysis = [
                    'total_users' => 0,
                    'legacy_passwords' => 0,
                    'hashed_passwords' => 0,
                    'empty_passwords' => 0,
                    'users_to_migrate' => []
                ];
                
                foreach ($rows as $rowIndex => $row) {
                    $email = $row[$emailIndex] ?? '';
                    $password = $row[$pwdIndex] ?? '';
                    
                    if (empty($email)) continue;
                    
                    $analysis['total_users']++;
                    
                    if (empty($password)) {
                        $analysis['empty_passwords']++;
                    } elseif (PasswordSecurity::isBcryptHash($password)) {
                        $analysis['hashed_passwords']++;
                    } else {
                        $analysis['legacy_passwords']++;
                        $analysis['users_to_migrate'][] = [
                            'email' => $email,
                            'row' => $rowIndex + 2
                        ];
                    }
                }
                
                Config::log("Analisi password completata: {$analysis['legacy_passwords']} da migrare", 'INFO');
                
                return [
                    'success' => true,
                    'analysis' => $analysis,
                    'token_refreshed' => isset($_SESSION['token_updated']) && 
                                       ($_SESSION['token_updated'] > (time() - 60))
                ];
                
            } catch (Exception $e) {
                Config::log('Errore durante analisi: ' . $e->getMessage(), 'ERROR');
                throw $e;
            }
        });
    }
    
    // Migra le password legacy con gestione automatica token
    public function migratePasswords($dryRun = false) {
        if (!$this->isAdminAuthenticated()) {
            throw new Exception('Autenticazione amministratore richiesta');
        }
        
        Config::log("Inizio migrazione password (dry run: " . ($dryRun ? 'sì' : 'no') . ")", 'INFO');
        
        return $this->executeWithTokenRetry(function() use ($dryRun) {
            try {
                $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
                
                if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                    throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
                }
                
                $rows = $sheetData['values'];
                $headers = array_shift($rows);
                
                $emailIndex = array_search('Email', $headers);
                $pwdIndex = array_search('PWD', $headers);
                
                if ($emailIndex === false || $pwdIndex === false) {
                    throw new Exception('Colonne Email o PWD non trovate');
                }
                
                $migrationResults = [
                    'total_processed' => 0,
                    'migrated' => 0,
                    'already_hashed' => 0,
                    'empty_skipped' => 0,
                    'errors' => []
                ];
                
                foreach ($rows as $rowIndex => $row) {
                    $email = $row[$emailIndex] ?? '';
                    $currentPassword = $row[$pwdIndex] ?? '';
                    
                    if (empty($email)) continue;
                    
                    $migrationResults['total_processed']++;
                    
                    try {
                        if (empty($currentPassword)) {
                            $migrationResults['empty_skipped']++;
                            Config::log("Password vuota saltata per: $email", 'WARNING');
                            continue;
                        }
                        
                        if (PasswordSecurity::isBcryptHash($currentPassword)) {
                            $migrationResults['already_hashed']++;
                            continue;
                        }
                        
                        // Password legacy trovata - migra
                        if (!$dryRun) {
                            $hashedPassword = PasswordSecurity::hashPassword($currentPassword);
                            
                            // Aggiorna la riga con retry automatico
                            $updatedRow = $row;
                            $updatedRow[$pwdIndex] = $hashedPassword;
                            
                            $this->graph->updateRow('ANA_CONSULENTI', $rowIndex + 2, $updatedRow);
                            
                            Config::log("Password migrata per: $email", 'INFO');
                        }
                        
                        $migrationResults['migrated']++;
                        
                    } catch (Exception $e) {
                        $migrationResults['errors'][] = [
                            'email' => $email,
                            'error' => $e->getMessage()
                        ];
                        Config::log("Errore migrazione per $email: " . $e->getMessage(), 'ERROR');
                    }
                }
                
                $message = $dryRun ? 
                    "Analisi completata: {$migrationResults['migrated']} password da migrare" :
                    "Migrazione completata: {$migrationResults['migrated']} password migrate";
                
                Config::log($message, 'INFO');
                
                return [
                    'success' => true,
                    'message' => $message,
                    'results' => $migrationResults,
                    'dry_run' => $dryRun,
                    'token_refreshed' => isset($_SESSION['token_updated']) && 
                                       ($_SESSION['token_updated'] > (time() - 60))
                ];
                
            } catch (Exception $e) {
                Config::log('Errore durante migrazione: ' . $e->getMessage(), 'ERROR');
                throw $e;
            }
        });
    }
    
    //<?php
// migrate_passwords.php - Endpoint dedicato per migrazione password
session_start();
require_once 'config.php';
require_once 'microsoft_graph_personal.php';
require_once 'password_security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class PasswordMigrationTool {
    private $graph;
    private $adminPassword = 'admin123'; // CAMBIARE CON PASSWORD SICURA
    
    public function __construct() {
        // Verifica se abbiamo un access token valido
        if (!isset($_SESSION['access_token']) || $this->isTokenExpired()) {
            throw new Exception('Token di accesso non valido o scaduto. Riautenticare.');
        }
        
        $this->graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
    }
    
    private function isTokenExpired() {
        return isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires'];
    }
    
    // Verifica autenticazione amministratore
    public function authenticateAdmin($password) {
        if ($password !== $this->adminPassword) {
            Config::log('Tentativo di accesso amministratore fallito', 'WARNING');
            throw new Exception('Password amministratore non corretta');
        }
        
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_auth_time'] = time();
        Config::log('Amministratore autenticato con successo', 'INFO');
        
        return [
            'success' => true,
            'message' => 'Autenticazione amministratore riuscita',
            'expires' => time() + 3600 // 1 ora
        ];
    }
    
    // Verifica se admin è autenticato
    private function isAdminAuthenticated() {
        return isset($_SESSION['admin_authenticated']) && 
               $_SESSION['admin_authenticated'] === true &&
               isset($_SESSION['admin_auth_time']) &&
               (time() - $_SESSION['admin_auth_time']) < 3600; // 1 ora di validità
    }
    
    // Analizza le password nella tabella
    public function analyzePasswords() {
        if (!$this->isAdminAuthenticated()) {
            throw new Exception('Autenticazione amministratore richiesta');
        }
        
        try {
            $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
            
            if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
            }
            
            $rows = $sheetData['values'];
            $headers = array_shift($rows);
            
            $emailIndex = array_search('Email', $headers);
            $pwdIndex = array_search('PWD', $headers);
            
            if ($emailIndex === false || $pwdIndex === false) {
                throw new Exception('Colonne Email o PWD non trovate');
            }
            
            $analysis = [
                'total_users' => 0,
                'legacy_passwords' => 0,
                'hashed_passwords' => 0,
                'empty_passwords' => 0,
                'users_to_migrate' => []
            ];
            
            foreach ($rows as $rowIndex => $row) {
                $email = $row[$emailIndex] ?? '';
                $password = $row[$pwdIndex] ?? '';
                
                if (empty($email)) continue;
                
                $analysis['total_users']++;
                
                if (empty($password)) {
                    $analysis['empty_passwords']++;
                } elseif (PasswordSecurity::isBcryptHash($password)) {
                    $analysis['hashed_passwords']++;
                } else {
                    $analysis['legacy_passwords']++;
                    $analysis['users_to_migrate'][] = [
                        'email' => $email,
                        'row' => $rowIndex + 2
                    ];
                }
            }
            
            Config::log("Analisi password completata: {$analysis['legacy_passwords']} da migrare", 'INFO');
            
            return [
                'success' => true,
                'analysis' => $analysis
            ];
            
        } catch (Exception $e) {
            Config::log('Errore durante analisi: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    // Migra le password legacy
    public function migratePasswords($dryRun = false) {
        if (!$this->isAdminAuthenticated()) {
            throw new Exception('Autenticazione amministratore richiesta');
        }
        
        Config::log("Inizio migrazione password (dry run: " . ($dryRun ? 'sì' : 'no') . ")", 'INFO');
        
        try {
            $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
            
            if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
            }
            
            $rows = $sheetData['values'];
            $headers = array_shift($rows);
            
            $emailIndex = array_search('Email', $headers);
            $pwdIndex = array_search('PWD', $headers);
            
            if ($emailIndex === false || $pwdIndex === false) {
                throw new Exception('Colonne Email o PWD non trovate');
            }
            
            $migrationResults = [
                'total_processed' => 0,
                'migrated' => 0,
                'already_hashed' => 0,
                'empty_skipped' => 0,
                'errors' => []
            ];
            
            foreach ($rows as $rowIndex => $row) {
                $email = $row[$emailIndex] ?? '';
                $currentPassword = $row[$pwdIndex] ?? '';
                
                if (empty($email)) continue;
                
                $migrationResults['total_processed']++;
                
                try {
                    if (empty($currentPassword)) {
                        $migrationResults['empty_skipped']++;
                        Config::log("Password vuota saltata per: $email", 'WARNING');
                        continue;
                    }
                    
                    if (PasswordSecurity::isBcryptHash($currentPassword)) {
                        $migrationResults['already_hashed']++;
                        continue;
                    }
                    
                    // Password legacy trovata - migra
                    if (!$dryRun) {
                        $hashedPassword = PasswordSecurity::hashPassword($currentPassword);
                        
                        // Aggiorna la riga
                        $updatedRow = $row;
                        $updatedRow[$pwdIndex] = $hashedPassword;
                        
                        $this->graph->updateRow('ANA_CONSULENTI', $rowIndex + 2, $updatedRow);
                        
                        Config::log("Password migrata per: $email", 'INFO');
                    }
                    
                    $migrationResults['migrated']++;
                    
                } catch (Exception $e) {
                    $migrationResults['errors'][] = [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ];
                    Config::log("Errore migrazione per $email: " . $e->getMessage(), 'ERROR');
                }
            }
            
            $message = $dryRun ? 
                "Analisi completata: {$migrationResults['migrated']} password da migrare" :
                "Migrazione completata: {$migrationResults['migrated']} password migrate";
            
            Config::log($message, 'INFO');
            
            return [
                'success' => true,
                'message' => $message,
                'results' => $migrationResults,
                'dry_run' => $dryRun
            ];
            
        } catch (Exception $e) {
            Config::log('Errore durante migrazione: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
}

// Gestione richieste
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    $migrationTool = new PasswordMigrationTool();
    
    switch ($action) {
        case 'authenticate':
            if (!isset($input['password'])) {
                throw new Exception('Password mancante');
            }
            
            $result = $migrationTool->authenticateAdmin($input['password']);
            echo json_encode($result);
            break;
            
        case 'analyze':
            $result = $migrationTool->analyzePasswords();
            echo json_encode($result);
            break;
            
        case 'migrate':
            $dryRun = $input['dry_run'] ?? false;
            $result = $migrationTool->migratePasswords($dryRun);
            echo json_encode($result);
            break;
            
        case 'check-auth':
            if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
                throw new Exception('Non autenticato');
            }
            
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'expires' => $_SESSION['admin_auth_time'] + 3600
            ]);
            break;
            
        case 'logout':
            unset($_SESSION['admin_authenticated']);
            unset($_SESSION['admin_auth_time']);
            echo json_encode([
                'success' => true,
                'message' => 'Logout effettuato'
            ]);
            break;
            
        default:
            throw new Exception('Azione non riconosciuta');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>