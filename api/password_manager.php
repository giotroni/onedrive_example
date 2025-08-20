<?php
// password_manager.php - Gestione password crittografate con refresh automatico token
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

class PasswordManager {
    private $graph;
    
    public function __construct() {
        // Usa il TokenManager per assicurarsi che il token sia valido
        try {
            TokenManager::ensureValidToken();
            $this->graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
        } catch (Exception $e) {
            throw new Exception('Errore autenticazione: ' . $e->getMessage());
        }
    }
    
    // Wrapper per operazioni che potrebbero richiedere refresh token
    private function executeWithTokenRetry($operation) {
        return TokenManager::apiCallWithRetry($operation, 2);
    }
    
    // Legge la tabella ANA_CONSULENTI con retry automatico
    public function getConsultantsData() {
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
                    throw new Exception('Colonne Email o PWD non trovate nella tabella');
                }
                
                return [
                    'headers' => $headers,
                    'rows' => $rows,
                    'emailIndex' => $emailIndex,
                    'pwdIndex' => $pwdIndex
                ];
                
            } catch (Exception $e) {
                Config::log('Errore lettura tabella: ' . $e->getMessage(), 'ERROR');
                throw new Exception('Errore lettura tabella: ' . $e->getMessage());
            }
        });
    }
    
    // Trova un utente per email
    public function findUserByEmail($email) {
        $data = $this->getConsultantsData();
        
        foreach ($data['rows'] as $rowIndex => $row) {
            if (isset($row[$data['emailIndex']]) && 
                strtolower(trim($row[$data['emailIndex']])) === strtolower(trim($email))) {
                return [
                    'found' => true,
                    'rowIndex' => $rowIndex + 2,
                    'currentPasswordHash' => $row[$data['pwdIndex']] ?? '',
                    'email' => $row[$data['emailIndex']],
                    'fullRow' => $row,
                    'pwdColumnIndex' => $data['pwdIndex']
                ];
            }
        }
        
        return ['found' => false];
    }
    
    // Cambia password con refresh automatico token
    public function changePassword($email, $currentPassword, $newPassword) {
        Config::log("Tentativo cambio password per: $email", 'INFO');
        
        // Validazioni
        if (strlen($newPassword) < Config::PASSWORD_MIN_LENGTH) {
            throw new Exception('La nuova password deve essere di almeno ' . Config::PASSWORD_MIN_LENGTH . ' caratteri');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Validazione forza password
        $strengthCheck = PasswordSecurity::validatePasswordStrength($newPassword);
        if (!$strengthCheck['is_valid']) {
            throw new Exception('Password troppo debole. ' . implode(' ', $strengthCheck['requirements']));
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            Config::log("Email non trovata: $email", 'WARNING');
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Verifica password attuale
        $currentHash = $user['currentPasswordHash'];
        
        if (!PasswordSecurity::isBcryptHash($currentHash)) {
            Config::log("Rilevata password legacy per: $email - migrazione automatica", 'INFO');
            if ($currentHash !== $currentPassword) {
                Config::log("Password attuale non corretta per: $email", 'WARNING');
                throw new Exception('Password attuale non corretta');
            }
        } else {
            if (!PasswordSecurity::verifyPassword($currentPassword, $currentHash)) {
                Config::log("Password attuale non corretta per: $email", 'WARNING');
                throw new Exception('Password attuale non corretta');
            }
        }
        
        // Audit della nuova password
        $audit = PasswordSecurity::auditPassword($newPassword);
        if ($audit['risk_level'] === 'Alto') {
            Config::log("Password ad alto rischio per: $email - " . implode(', ', $audit['issues']), 'WARNING');
        }
        
        // Genera hash sicuro per la nuova password
        try {
            $newPasswordHash = PasswordSecurity::hashPassword($newPassword);
            Config::log("Password hashata con successo per: $email", 'INFO');
        } catch (Exception $e) {
            Config::log("Errore hashing password per: $email - " . $e->getMessage(), 'ERROR');
            throw new Exception('Errore durante la crittografia della password: ' . $e->getMessage());
        }
        
        // Aggiorna la password in Excel con retry automatico
        return $this->executeWithTokenRetry(function() use ($user, $newPasswordHash, $email, $strengthCheck, $currentPassword, $newPassword) {
            try {
                // Prepara la riga aggiornata
                $updatedRow = $user['fullRow'];
                $updatedRow[$user['pwdColumnIndex']] = $newPasswordHash;
                
                // Aggiorna in Excel
                $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
                
                Config::log("Password aggiornata con successo per: $email", 'INFO');
                
                // Pulisce i dati sensibili dalla memoria
                PasswordSecurity::clearSensitiveData($currentPassword);
                PasswordSecurity::clearSensitiveData($newPassword);
                
                return [
                    'success' => true,
                    'message' => 'Password aggiornata con successo',
                    'email' => $email,
                    'strength' => $strengthCheck['strength'],
                    'token_refreshed' => isset($_SESSION['token_updated']) && 
                                       ($_SESSION['token_updated'] > (time() - 60))
                ];
                
            } catch (Exception $e) {
                Config::log("Errore aggiornamento Excel per: $email - " . $e->getMessage(), 'ERROR');
                throw new Exception('Errore durante l\'aggiornamento: ' . $e->getMessage());
            }
        });
    }
    
    // Reset password con refresh automatico
    public function resetPassword($email) {
        Config::log("Tentativo reset password per: $email", 'INFO');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            Config::log("Email non trovata per reset: $email", 'WARNING');
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Genera nuova password sicura
        $newPassword = PasswordSecurity::generateSecurePassword(Config::PASSWORD_RESET_LENGTH, false);
        
        return $this->executeWithTokenRetry(function() use ($user, $newPassword, $email) {
            try {
                // Genera hash sicuro
                $newPasswordHash = PasswordSecurity::hashPassword($newPassword);
                Config::log("Password di reset generata e hashata per: $email", 'INFO');
                
                // Aggiorna in Excel
                $updatedRow = $user['fullRow'];
                $updatedRow[$user['pwdColumnIndex']] = $newPasswordHash;
                
                $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
                
                // Invia email con nuova password
                $this->sendPasswordEmail($email, $newPassword);
                
                Config::log("Password di reset inviata via email per: $email", 'INFO');
                
                // Pulisce la password dalla memoria
                PasswordSecurity::clearSensitiveData($newPassword);
                
                return [
                    'success' => true,
                    'message' => 'Nuova password generata e inviata via email',
                    'email' => $email,
                    'token_refreshed' => isset($_SESSION['token_updated']) && 
                                       ($_SESSION['token_updated'] > (time() - 60))
                ];
                
            } catch (Exception $e) {
                Config::log("Errore durante reset password per: $email - " . $e->getMessage(), 'ERROR');
                throw new Exception('Errore durante il reset: ' . $e->getMessage());
            }
        });
    }
    
    // Migra password legacy con refresh automatico
    public function migrateLegacyPasswords() {
        Config::log("Inizio migrazione password legacy", 'INFO');
        
        return $this->executeWithTokenRetry(function() {
            try {
                $data = $this->getConsultantsData();
                $migratedCount = 0;
                
                foreach ($data['rows'] as $rowIndex => $row) {
                    $email = $row[$data['emailIndex']] ?? '';
                    $currentPwd = $row[$data['pwdIndex']] ?? '';
                    
                    // Salta righe vuote
                    if (empty($email) || empty($currentPwd)) {
                        continue;
                    }
                    
                    // Se non è già un hash bcrypt, migra
                    if (!PasswordSecurity::isBcryptHash($currentPwd)) {
                        $hashedPassword = PasswordSecurity::hashPassword($currentPwd);
                        
                        // Aggiorna la riga
                        $updatedRow = $row;
                        $updatedRow[$data['pwdIndex']] = $hashedPassword;
                        
                        $this->graph->updateRow('ANA_CONSULENTI', $rowIndex + 2, $updatedRow);
                        
                        $migratedCount++;
                        Config::log("Password migrata per: $email", 'INFO');
                    }
                }
                
                Config::log("Migrazione completata. Password migrate: $migratedCount", 'INFO');
                
                return [
                    'success' => true,
                    'message' => "Migrazione completata. $migratedCount password migrate.",
                    'migrated_count' => $migratedCount,
                    'token_refreshed' => isset($_SESSION['token_updated']) && 
                                       ($_SESSION['token_updated'] > (time() - 60))
                ];
                
            } catch (Exception $e) {
                Config::log("Errore durante migrazione: " . $e->getMessage(), 'ERROR');
                throw new Exception('Errore durante la migrazione: ' . $e->getMessage());
            }
        });
    }
    
    // Ottiene informazioni sui token
    public function getTokenStatus() {
        return TokenManager::getTokenInfo();
    }
    
    // Invia email con nuova password
    private function sendPasswordEmail($email, $password) {
        $mailConfig = Config::getMailConfig();
        
        $subject = '🔐 Nuova Password Temporanea - Sistema DATI_VP';
        $message = "
        <html>
        <head>
            <title>Nuova Password Temporanea</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .password-box { background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007cba; }
                .password { font-family: monospace; font-size: 24px; font-weight: bold; color: #007cba; letter-spacing: 2px; }
                .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Temporanea Generata</h1>
                </div>
                <div class='content'>
                    <p>Ciao,</p>
                    <p>È stata generata una nuova password temporanea per il tuo account nel sistema DATI_VP.</p>
                    
                    <div class='password-box'>
                        <strong>La tua nuova password temporanea è:</strong>
                        <div class='password'>{$password}</div>
                    </div>
                    
                    <div class='warning'>
                        <h3>⚠️ Importante - Istruzioni di Sicurezza:</h3>
                        <ul>
                            <li><strong>Questa è una password temporanea</strong> - cambiala al primo accesso</li>
                            <li><strong>Conservala in un luogo sicuro</strong> e non condividerla con nessuno</li>
                            <li><strong>La password è crittografata</strong> nel nostro sistema per la tua sicurezza</li>
                            <li><strong>Usa una password forte</strong> quando la cambi (almeno 8 caratteri, maiuscole, minuscole, numeri)</li>
                        </ul>
                    </div>
                    
                    <p>Puoi accedere al sistema e cambiare la password utilizzando il link:</p>
                    <p><a href='" . Config::BASE_URL . "password_manager.html' style='color: #007cba;'>Gestione Password</a></p>
                    
                    <p>Se non hai richiesto questo reset, contatta immediatamente l'amministratore di sistema.</p>
                </div>
                <div class='footer'>
                    <p>Questo messaggio è stato generato automaticamente dal sistema di gestione password DATI_VP.<br>
                    Per supporto: " . $mailConfig['reply_to'] . "</p>
                    <p><em>Password generata il: " . date('d/m/Y H:i:s') . "</em></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $mailConfig['from_name'] . ' <' . $mailConfig['from_address'] . '>',
            'Reply-To: ' . $mailConfig['reply_to'],
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1',
            'X-MSMail-Priority: High'
        ];
        
        if (!mail($email, $subject, $message, implode("\r\n", $headers))) {
            throw new Exception('Errore durante l\'invio dell\'email');
        }
    }
}

// Gestione richieste API
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    $action = $_GET['action'] ?? '';
    $passwordManager = new PasswordManager();
    
    switch ($action) {
        case 'change-password':
            if (!isset($input['email'], $input['currentPassword'], $input['newPassword'])) {
                throw new Exception('Parametri mancanti');
            }
            
            $result = $passwordManager->changePassword(
                $input['email'],
                $input['currentPassword'],
                $input['newPassword']
            );
            
            echo json_encode($result);
            break;
            
        case 'forgot-password':
            if (!isset($input['recoveryEmail'])) {
                throw new Exception('Email mancante');
            }
            
            $result = $passwordManager->resetPassword($input['recoveryEmail']);
            echo json_encode($result);
            break;
            
        case 'check-session':
            // Verifica e refresha automaticamente se necessario
            try {
                TokenManager::ensureValidToken();
                $tokenInfo = $passwordManager->getTokenStatus();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Sessione valida',
                    'token_info' => $tokenInfo
                ]);
            } catch (Exception $e) {
                throw new Exception('Sessione non valida: ' . $e->getMessage());
            }
            break;
            
        case 'refresh-token':
            // Forza refresh del token
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
            
        case 'validate-password':
            if (!isset($input['password'])) {
                throw new Exception('Password mancante');
            }
            
            $validation = PasswordSecurity::validatePasswordStrength($input['password']);
            $audit = PasswordSecurity::auditPassword($input['password']);
            
            echo json_encode([
                'success' => true,
                'validation' => $validation,
                'audit' => $audit
            ]);
            break;
            
        default:
            throw new Exception('Azione non riconosciuta');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'requires_reauth' => strpos($e->getMessage(), 'Riautorizzazione richiesta') !== false
    ]);
}
?>

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class PasswordManager {
    private $graph;
    
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
    
    // Legge la tabella ANA_CONSULENTI
    public function getConsultantsData() {
        try {
            $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
            
            if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
            }
            
            $rows = $sheetData['values'];
            $headers = array_shift($rows); // Prima riga = headers
            
            // Trova gli indici delle colonne
            $emailIndex = array_search('Email', $headers);
            $pwdIndex = array_search('PWD', $headers);
            
            if ($emailIndex === false || $pwdIndex === false) {
                throw new Exception('Colonne Email o PWD non trovate nella tabella');
            }
            
            return [
                'headers' => $headers,
                'rows' => $rows,
                'emailIndex' => $emailIndex,
                'pwdIndex' => $pwdIndex
            ];
            
        } catch (Exception $e) {
            Config::log('Errore lettura tabella: ' . $e->getMessage(), 'ERROR');
            throw new Exception('Errore lettura tabella: ' . $e->getMessage());
        }
    }
    
    // Trova un utente per email
    public function findUserByEmail($email) {
        $data = $this->getConsultantsData();
        
        foreach ($data['rows'] as $rowIndex => $row) {
            if (isset($row[$data['emailIndex']]) && 
                strtolower(trim($row[$data['emailIndex']])) === strtolower(trim($email))) {
                return [
                    'found' => true,
                    'rowIndex' => $rowIndex + 2, // +2 perché Excel parte da 1 e abbiamo gli headers
                    'currentPasswordHash' => $row[$data['pwdIndex']] ?? '',
                    'email' => $row[$data['emailIndex']],
                    'fullRow' => $row,
                    'pwdColumnIndex' => $data['pwdIndex']
                ];
            }
        }
        
        return ['found' => false];
    }
    
    // Cambia password utente con crittografia sicura
    public function changePassword($email, $currentPassword, $newPassword) {
        Config::log("Tentativo cambio password per: $email", 'INFO');
        
        // Validazioni
        if (strlen($newPassword) < Config::PASSWORD_MIN_LENGTH) {
            throw new Exception('La nuova password deve essere di almeno ' . Config::PASSWORD_MIN_LENGTH . ' caratteri');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Validazione forza password
        $strengthCheck = PasswordSecurity::validatePasswordStrength($newPassword);
        if (!$strengthCheck['is_valid']) {
            throw new Exception('Password troppo debole. ' . implode(' ', $strengthCheck['requirements']));
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            Config::log("Email non trovata: $email", 'WARNING');
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Verifica password attuale
        $currentHash = $user['currentPasswordHash'];
        
        // Se la password attuale non è già hashata, potrebbe essere una migrazione da sistema legacy
        if (!PasswordSecurity::isBcryptHash($currentHash)) {
            Config::log("Rilevata password legacy per: $email - migrazione automatica", 'INFO');
            // Per sistemi legacy: confronta in chiaro e poi migra
            if ($currentHash !== $currentPassword) {
                Config::log("Password attuale non corretta per: $email", 'WARNING');
                throw new Exception('Password attuale non corretta');
            }
        } else {
            // Password già hashata: usa verifica sicura
            if (!PasswordSecurity::verifyPassword($currentPassword, $currentHash)) {
                Config::log("Password attuale non corretta per: $email", 'WARNING');
                throw new Exception('Password attuale non corretta');
            }
        }
        
        // Audit della nuova password
        $audit = PasswordSecurity::auditPassword($newPassword);
        if ($audit['risk_level'] === 'Alto') {
            Config::log("Password ad alto rischio per: $email - " . implode(', ', $audit['issues']), 'WARNING');
        }
        
        // Genera hash sicuro per la nuova password
        try {
            $newPasswordHash = PasswordSecurity::hashPassword($newPassword);
            Config::log("Password hashata con successo per: $email", 'INFO');
        } catch (Exception $e) {
            Config::log("Errore hashing password per: $email - " . $e->getMessage(), 'ERROR');
            throw new Exception('Errore durante la crittografia della password: ' . $e->getMessage());
        }
        
        // Aggiorna la password in Excel
        try {
            // Prepara la riga aggiornata
            $updatedRow = $user['fullRow'];
            $updatedRow[$user['pwdColumnIndex']] = $newPasswordHash;
            
            // Aggiorna in Excel
            $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
            
            Config::log("Password aggiornata con successo per: $email", 'INFO');
            
            // Pulisce i dati sensibili dalla memoria
            PasswordSecurity::clearSensitiveData($currentPassword);
            PasswordSecurity::clearSensitiveData($newPassword);
            
            return [
                'success' => true,
                'message' => 'Password aggiornata con successo',
                'email' => $email,
                'strength' => $strengthCheck['strength']
            ];
            
        } catch (Exception $e) {
            Config::log("Errore aggiornamento Excel per: $email - " . $e->getMessage(), 'ERROR');
            throw new Exception('Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }
    
    // Reset password (password dimenticata) con crittografia
    public function resetPassword($email) {
        Config::log("Tentativo reset password per: $email", 'INFO');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            Config::log("Email non trovata per reset: $email", 'WARNING');
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Genera nuova password sicura
        $newPassword = PasswordSecurity::generateSecurePassword(Config::PASSWORD_RESET_LENGTH, false);
        
        try {
            // Genera hash sicuro
            $newPasswordHash = PasswordSecurity::hashPassword($newPassword);
            Config::log("Password di reset generata e hashata per: $email", 'INFO');
            
            // Aggiorna in Excel
            $updatedRow = $user['fullRow'];
            $updatedRow[$user['pwdColumnIndex']] = $newPasswordHash;
            
            $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
            
            // Invia email con nuova password
            $this->sendPasswordEmail($email, $newPassword);
            
            Config::log("Password di reset inviata via email per: $email", 'INFO');
            
            // Pulisce la password dalla memoria
            PasswordSecurity::clearSensitiveData($newPassword);
            
            return [
                'success' => true,
                'message' => 'Nuova password generata e inviata via email',
                'email' => $email
            ];
            
        } catch (Exception $e) {
            Config::log("Errore durante reset password per: $email - " . $e->getMessage(), 'ERROR');
            throw new Exception('Errore durante il reset: ' . $e->getMessage());
        }
    }
    
    // Migra password legacy a hash sicuro
    public function migrateLegacyPasswords() {
        Config::log("Inizio migrazione password legacy", 'INFO');
        
        try {
            $data = $this->getConsultantsData();
            $migratedCount = 0;
            
            foreach ($data['rows'] as $rowIndex => $row) {
                $email = $row[$data['emailIndex']] ?? '';
                $currentPwd = $row[$data['pwdIndex']] ?? '';
                
                // Salta righe vuote
                if (empty($email) || empty($currentPwd)) {
                    continue;
                }
                
                // Se non è già un hash bcrypt, migra
                if (!PasswordSecurity::isBcryptHash($currentPwd)) {
                    $hashedPassword = PasswordSecurity::hashPassword($currentPwd);
                    
                    // Aggiorna la riga
                    $updatedRow = $row;
                    $updatedRow[$data['pwdIndex']] = $hashedPassword;
                    
                    $this->graph->updateRow('ANA_CONSULENTI', $rowIndex + 2, $updatedRow);
                    
                    $migratedCount++;
                    Config::log("Password migrata per: $email", 'INFO');
                }
            }
            
            Config::log("Migrazione completata. Password migrate: $migratedCount", 'INFO');
            
            return [
                'success' => true,
                'message' => "Migrazione completata. $migratedCount password migrate.",
                'migrated_count' => $migratedCount
            ];
            
        } catch (Exception $e) {
            Config::log("Errore durante migrazione: " . $e->getMessage(), 'ERROR');
            throw new Exception('Errore durante la migrazione: ' . $e->getMessage());
        }
    }
    
    // Invia email con nuova password
    private function sendPasswordEmail($email, $password) {
        $mailConfig = Config::getMailConfig();
        
        $subject = '🔐 Nuova Password Temporanea - Sistema DATI_VP';
        $message = "
        <html>
        <head>
            <title>Nuova Password Temporanea</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .password-box { background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007cba; }
                .password { font-family: monospace; font-size: 24px; font-weight: bold; color: #007cba; letter-spacing: 2px; }
                .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Temporanea Generata</h1>
                </div>
                <div class='content'>
                    <p>Ciao,</p>
                    <p>È stata generata una nuova password temporanea per il tuo account nel sistema DATI_VP.</p>
                    
                    <div class='password-box'>
                        <strong>La tua nuova password temporanea è:</strong>
                        <div class='password'>{$password}</div>
                    </div>
                    
                    <div class='warning'>
                        <h3>⚠️ Importante - Istruzioni di Sicurezza:</h3>
                        <ul>
                            <li><strong>Questa è una password temporanea</strong> - cambiala al primo accesso</li>
                            <li><strong>Conservala in un luogo sicuro</strong> e non condividerla con nessuno</li>
                            <li><strong>La password è crittografata</strong> nel nostro sistema per la tua sicurezza</li>
                            <li><strong>Usa una password forte</strong> quando la cambi (almeno 8 caratteri, maiuscole, minuscole, numeri)</li>
                        </ul>
                    </div>
                    
                    <p>Puoi accedere al sistema e cambiare la password utilizzando il link:</p>
                    <p><a href='" . Config::BASE_URL . "password_manager.html' style='color: #007cba;'>Gestione Password</a></p>
                    
                    <p>Se non hai richiesto questo reset, contatta immediatamente l'amministratore di sistema.</p>
                </div>
                <div class='footer'>
                    <p>Questo messaggio è stato generato automaticamente dal sistema di gestione password DATI_VP.<br>
                    Per supporto: " . $mailConfig['reply_to'] . "</p>
                    <p><em>Password generata il: " . date('d/m/Y H:i:s') . "</em></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $mailConfig['from_name'] . ' <' . $mailConfig['from_address'] . '>',
            'Reply-To: ' . $mailConfig['reply_to'],
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1', // Alta priorità per email di sicurezza
            'X-MSMail-Priority: High'
        ];
        
        if (!mail($email, $subject, $message, implode("\r\n", $headers))) {
            throw new Exception('Errore durante l\'invio dell\'email');
        }
    }
}

// Gestione richieste API
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    $action = $_GET['action'] ?? '';
    $passwordManager = new PasswordManager();
    
    switch ($action) {
        case 'change-password':
            if (!isset($input['email'], $input['currentPassword'], $input['newPassword'])) {
                throw new Exception('Parametri mancanti');
            }
            
            $result = $passwordManager->changePassword(
                $input['email'],
                $input['currentPassword'],
                $input['newPassword']
            );
            
            echo json_encode($result);
            break;
            
        case 'forgot-password':
            if (!isset($input['recoveryEmail'])) {
                throw new Exception('Email mancante');
            }
            
            $result = $passwordManager->resetPassword($input['recoveryEmail']);
            echo json_encode($result);
            break;
            
        case 'check-session':
            // Verifica validità sessione
            if (!isset($_SESSION['access_token']) || 
                (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires'])) {
                throw new Exception('Sessione scaduta');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Sessione valida',
                'expires' => $_SESSION['token_expires'] ?? null
            ]);
            break;
            
        case 'migrate-legacy':
            // Funzione di migrazione per amministratori
            if (!isset($_SESSION['admin_logged']) || !$_SESSION['admin_logged']) {
                throw new Exception('Accesso amministratore richiesto');
            }
            
            $result = $passwordManager->migrateLegacyPasswords();
            echo json_encode($result);
            break;
            
        case 'validate-password':
            if (!isset($input['password'])) {
                throw new Exception('Password mancante');
            }
            
            $validation = PasswordSecurity::validatePasswordStrength($input['password']);
            $audit = PasswordSecurity::auditPassword($input['password']);
            
            echo json_encode([
                'success' => true,
                'validation' => $validation,
                'audit' => $audit
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
            $sheetData = $this->graph->readSheet('ANA_CONSULENTI');
            
            if (!isset($sheetData['values']) || empty($sheetData['values'])) {
                throw new Exception('Tabella ANA_CONSULENTI non trovata o vuota');
            }
            
            $rows = $sheetData['values'];
            $headers = array_shift($rows); // Prima riga = headers
            
            // Trova gli indici delle colonne
            $emailIndex = array_search('Email', $headers);
            $pwdIndex = array_search('PWD', $headers);
            
            if ($emailIndex === false || $pwdIndex === false) {
                throw new Exception('Colonne Email o PWD non trovate nella tabella');
            }
            
            return [
                'headers' => $headers,
                'rows' => $rows,
                'emailIndex' => $emailIndex,
                'pwdIndex' => $pwdIndex
            ];
            
        } catch (Exception $e) {
            throw new Exception('Errore lettura tabella: ' . $e->getMessage());
        }
    }
    
    // Trova un utente per email
    public function findUserByEmail($email) {
        $data = $this->getConsultantsData();
        
        foreach ($data['rows'] as $rowIndex => $row) {
            if (isset($row[$data['emailIndex']]) && 
                strtolower(trim($row[$data['emailIndex']])) === strtolower(trim($email))) {
                return [
                    'found' => true,
                    'rowIndex' => $rowIndex + 2, // +2 perché Excel parte da 1 e abbiamo gli headers
                    'currentPassword' => $row[$data['pwdIndex']] ?? '',
                    'email' => $row[$data['emailIndex']],
                    'fullRow' => $row,
                    'pwdColumnIndex' => $data['pwdIndex']
                ];
            }
        }
        
        return ['found' => false];
    }
    
    // Cambia password utente
    public function changePassword($email, $currentPassword, $newPassword) {
        // Validazioni
        if (strlen($newPassword) < 5) {
            throw new Exception('La nuova password deve essere di almeno 5 caratteri');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Verifica password attuale
        if ($user['currentPassword'] !== $currentPassword) {
            throw new Exception('Password attuale non corretta');
        }
        
        // Aggiorna la password in Excel
        try {
            // Prepara la riga aggiornata
            $updatedRow = $user['fullRow'];
            $updatedRow[$user['pwdColumnIndex']] = $newPassword;
            
            // Aggiorna in Excel
            $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
            
            return [
                'success' => true,
                'message' => 'Password aggiornata con successo',
                'email' => $email
            ];
            
        } catch (Exception $e) {
            throw new Exception('Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }
    
    // Genera password casuale
    public function generateRandomPassword($length = 8) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    // Reset password (password dimenticata)
    public function resetPassword($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        // Trova l'utente
        $user = $this->findUserByEmail($email);
        
        if (!$user['found']) {
            throw new Exception('Email non trovata nel sistema');
        }
        
        // Genera nuova password
        $newPassword = $this->generateRandomPassword(8);
        
        try {
            // Aggiorna in Excel
            $updatedRow = $user['fullRow'];
            $updatedRow[$user['pwdColumnIndex']] = $newPassword;
            
            $result = $this->graph->updateRow('ANA_CONSULENTI', $user['rowIndex'], $updatedRow);
            
            // Invia email con nuova password
            $this->sendPasswordEmail($email, $newPassword);
            
            return [
                'success' => true,
                'message' => 'Nuova password generata e inviata via email',
                'email' => $email
            ];
            
        } catch (Exception $e) {
            throw new Exception('Errore durante il reset: ' . $e->getMessage());
        }
    }
    
    // Invia email con nuova password
    private function sendPasswordEmail($email, $password) {
        $subject = 'Nuova Password - Sistema DATI_VP';
        $message = "
        <html>
        <head>
            <title>Nuova Password</title>
        </head>
        <body>
            <h2>🔐 Nuova Password Generata</h2>
            <p>La tua nuova password temporanea è:</p>
            <div style='background:#f8f9fa; padding:15px; border-radius:8px; font-family:monospace; font-size:18px; font-weight:bold; color:#007cba;'>
                {$password}
            </div>
            <p><strong>⚠️ Importante:</strong></p>
            <ul>
                <li>Questa è una password temporanea</li>
                <li>Ti consigliamo di cambiarla al primo accesso</li>
                <li>Conservala in un luogo sicuro</li>
            </ul>
            <hr>
            <p style='color:#666; font-size:12px;'>
                Questo messaggio è stato generato automaticamente dal sistema di gestione password DATI_VP.
            </p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Sistema DATI_VP <noreply@vaglioandpartners.com>',
            'Reply-To: noreply@vaglioandpartners.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (!mail($email, $subject, $message, implode("\r\n", $headers))) {
            throw new Exception('Errore durante l\'invio dell\'email');
        }
    }
}

// Gestione richieste API
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    $action = $_GET['action'] ?? '';
    $passwordManager = new PasswordManager();
    
    switch ($action) {
        case 'change-password':
            if (!isset($input['email'], $input['currentPassword'], $input['newPassword'])) {
                throw new Exception('Parametri mancanti');
            }
            
            $result = $passwordManager->changePassword(
                $input['email'],
                $input['currentPassword'],
                $input['newPassword']
            );
            
            echo json_encode($result);
            break;
            
        case 'forgot-password':
            if (!isset($input['recoveryEmail'])) {
                throw new Exception('Email mancante');
            }
            
            $result = $passwordManager->resetPassword($input['recoveryEmail']);
            echo json_encode($result);
            break;
            
        case 'check-session':
            // Verifica validità sessione
            if (!isset($_SESSION['access_token']) || 
                (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires'])) {
                throw new Exception('Sessione scaduta');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Sessione valida',
                'expires' => $_SESSION['token_expires'] ?? null
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
