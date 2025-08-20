<?php
// token_manager.php - Sistema di gestione automatica token OAuth
require_once 'config.php';
require_once 'microsoft_graph_personal.php';

class TokenManager {
    
    /**
     * Verifica se il token è valido e lo refresha automaticamente se necessario
     * 
     * @return bool True se il token è valido o è stato refreshato con successo
     * @throws Exception Se il refresh fallisce
     */
    public static function ensureValidToken() {
        // Controlla se abbiamo un token
        if (!isset($_SESSION['access_token'])) {
            throw new Exception('Nessun token di accesso. Autorizzazione richiesta.');
        }
        
        // Se il token non è ancora scaduto, va bene
        if (!self::isTokenExpired()) {
            return true;
        }
        
        // Token scaduto - proviamo a refresharlo
        if (!isset($_SESSION['refresh_token'])) {
            throw new Exception('Token scaduto e nessun refresh token disponibile. Riautorizzazione richiesta.');
        }
        
        try {
            $newTokens = self::refreshAccessToken($_SESSION['refresh_token']);
            self::saveTokensToSession($newTokens);
            
            Config::log('Token refreshato automaticamente', 'INFO');
            return true;
            
        } catch (Exception $e) {
            Config::log('Errore refresh token: ' . $e->getMessage(), 'ERROR');
            // Pulisce i token non validi
            self::clearTokens();
            throw new Exception('Impossibile rinnovare il token. Riautorizzazione richiesta.');
        }
    }
    
    /**
     * Controlla se il token è scaduto (con margine di sicurezza)
     * 
     * @return bool True se il token è scaduto o sta per scadere
     */
    public static function isTokenExpired() {
        if (!isset($_SESSION['token_expires'])) {
            return true;
        }
        
        // Considera scaduto se mancano meno di 5 minuti
        $safetyMargin = 300; // 5 minuti
        return (time() + $safetyMargin) >= $_SESSION['token_expires'];
    }
    
    /**
     * Refresha l'access token usando il refresh token
     * 
     * @param string $refreshToken Il refresh token memorizzato
     * @return array Nuovi token
     * @throws Exception Se il refresh fallisce
     */
    public static function refreshAccessToken($refreshToken) {
        $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        
        $data = [
            'client_id' => Config::CLIENT_ID,
            'client_secret' => Config::CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => Config::getGraphScopesString()
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error $httpCode during token refresh");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("Token Refresh Error: " . ($result['error_description'] ?? $result['error']));
        }
        
        if (!isset($result['access_token'])) {
            throw new Exception("No access token in refresh response");
        }
        
        return $result;
    }
    
    /**
     * Salva i token nella sessione
     * 
     * @param array $tokenData Dati token da Microsoft
     */
    public static function saveTokensToSession($tokenData) {
        $_SESSION['access_token'] = $tokenData['access_token'];
        $_SESSION['token_type'] = $tokenData['token_type'] ?? 'Bearer';
        $_SESSION['expires_in'] = $tokenData['expires_in'] ?? 3600;
        $_SESSION['token_expires'] = time() + ($tokenData['expires_in'] ?? 3600);
        $_SESSION['scope'] = $tokenData['scope'] ?? '';
        
        // Il refresh token potrebbe essere nuovo o lo stesso
        if (isset($tokenData['refresh_token'])) {
            $_SESSION['refresh_token'] = $tokenData['refresh_token'];
        }
        
        $_SESSION['token_updated'] = time();
        
        Config::log('Token salvati nella sessione', 'INFO');
    }
    
    /**
     * Pulisce i token dalla sessione
     */
    public static function clearTokens() {
        $keysToRemove = [
            'access_token',
            'refresh_token', 
            'token_type',
            'expires_in',
            'token_expires',
            'scope',
            'token_updated',
            'oauth_completed'
        ];
        
        foreach ($keysToRemove as $key) {
            unset($_SESSION[$key]);
        }
        
        Config::log('Token rimossi dalla sessione', 'INFO');
    }
    
    /**
     * Ottiene le informazioni sui token attuali
     * 
     * @return array Informazioni sui token
     */
    public static function getTokenInfo() {
        return [
            'has_access_token' => isset($_SESSION['access_token']),
            'has_refresh_token' => isset($_SESSION['refresh_token']),
            'expires_at' => $_SESSION['token_expires'] ?? null,
            'expires_in_seconds' => isset($_SESSION['token_expires']) ? 
                                   max(0, $_SESSION['token_expires'] - time()) : 0,
            'is_expired' => self::isTokenExpired(),
            'token_updated' => $_SESSION['token_updated'] ?? null,
            'scope' => $_SESSION['scope'] ?? null
        ];
    }
    
    /**
     * Forza il refresh del token (anche se non scaduto)
     * 
     * @return bool True se il refresh è riuscito
     */
    public static function forceRefresh() {
        if (!isset($_SESSION['refresh_token'])) {
            throw new Exception('Nessun refresh token disponibile');
        }
        
        try {
            $newTokens = self::refreshAccessToken($_SESSION['refresh_token']);
            self::saveTokensToSession($newTokens);
            
            Config::log('Token refreshato forzatamente', 'INFO');
            return true;
            
        } catch (Exception $e) {
            Config::log('Errore refresh forzato: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Wrapper per le operazioni che richiedono un token valido
     * 
     * @param callable $callback Funzione da eseguire
     * @return mixed Risultato della funzione
     * @throws Exception Se non è possibile ottenere un token valido
     */
    public static function withValidToken($callback) {
        self::ensureValidToken();
        return $callback();
    }
    
    /**
     * Middleware per API calls automatiche con retry su token scaduto
     * 
     * @param callable $apiCall Chiamata API da eseguire
     * @param int $maxRetries Numero massimo di retry
     * @return mixed Risultato dell'API call
     */
    public static function apiCallWithRetry($apiCall, $maxRetries = 1) {
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                self::ensureValidToken();
                return $apiCall();
                
            } catch (Exception $e) {
                $attempt++;
                
                // Se è un errore di autenticazione e abbiamo retry disponibili
                if ($attempt <= $maxRetries && 
                    (strpos($e->getMessage(), '401') !== false || 
                     strpos($e->getMessage(), 'Unauthorized') !== false ||
                     strpos($e->getMessage(), 'token') !== false)) {
                    
                    Config::log("Tentativo $attempt di refresh token dopo errore API", 'WARNING');
                    
                    try {
                        self::forceRefresh();
                        continue; // Riprova con il nuovo token
                    } catch (Exception $refreshError) {
                        // Se il refresh fallisce, rilancia l'errore originale
                        throw $e;
                    }
                }
                
                // Altri errori o retry esauriti
                throw $e;
            }
        }
    }
    
    /**
     * Programma un refresh automatico del token
     * Può essere chiamato via cron job o task scheduler
     */
    public static function scheduledRefresh() {
        session_start();
        
        try {
            if (!isset($_SESSION['refresh_token'])) {
                Config::log('Nessun refresh token per refresh programmato', 'WARNING');
                return false;
            }
            
            // Refresha solo se il token scade tra meno di 30 minuti
            if (isset($_SESSION['token_expires']) && 
                ($_SESSION['token_expires'] - time()) < 1800) {
                
                self::forceRefresh();
                Config::log('Refresh programmato completato', 'INFO');
                return true;
            }
            
            Config::log('Token non necessita refresh programmato', 'INFO');
            return true;
            
        } catch (Exception $e) {
            Config::log('Errore refresh programmato: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}

// Funzione di utilità per ottenere un'istanza MicrosoftGraph sempre con token valido
function getMicrosoftGraphInstance() {
    TokenManager::ensureValidToken();
    return new MicrosoftGraphPersonal($_SESSION['access_token']);
}
?>