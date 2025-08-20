<?php
// password_security.php - Classe per gestione sicura delle password
class PasswordSecurity {
    
    // Algoritmo di hashing (default: PASSWORD_DEFAULT usa bcrypt)
    const HASH_ALGORITHM = PASSWORD_DEFAULT;
    
    // Opzioni per l'algoritmo bcrypt
    const HASH_OPTIONS = [
        'cost' => 12 // Aumenta la sicurezza ma richiede più tempo di calcolo
    ];
    
    /**
     * Cripta una password usando un hash sicuro
     * 
     * @param string $password Password in chiaro
     * @return string Password hashata
     * @throws Exception Se l'hashing fallisce
     */
    public static function hashPassword($password) {
        if (empty($password)) {
            throw new Exception('Password non può essere vuota');
        }
        
        $hash = password_hash($password, self::HASH_ALGORITHM, self::HASH_OPTIONS);
        
        if ($hash === false) {
            throw new Exception('Errore durante l\'hashing della password');
        }
        
        return $hash;
    }
    
    /**
     * Verifica se una password corrisponde al suo hash
     * 
     * @param string $password Password in chiaro da verificare
     * @param string $hash Hash memorizzato
     * @return bool True se la password corrisponde
     */
    public static function verifyPassword($password, $hash) {
        if (empty($password) || empty($hash)) {
            return false;
        }
        
        return password_verify($password, $hash);
    }
    
    /**
     * Controlla se un hash deve essere aggiornato
     * (utile per migrare vecchi hash o cambiare algoritmo)
     * 
     * @param string $hash Hash da controllare
     * @return bool True se l'hash deve essere aggiornato
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, self::HASH_ALGORITHM, self::HASH_OPTIONS);
    }
    
    /**
     * Genera una password casuale sicura
     * 
     * @param int $length Lunghezza password (default: 12)
     * @param bool $includeSymbols Includere simboli speciali
     * @return string Password generata
     */
    public static function generateSecurePassword($length = 12, $includeSymbols = true) {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        // Costruisce il set di caratteri
        $charset = $lowercase . $uppercase . $numbers;
        if ($includeSymbols) {
            $charset .= $symbols;
        }
        
        $password = '';
        $charsetLength = strlen($charset);
        
        // Assicura che ci sia almeno un carattere di ogni tipo
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        
        if ($includeSymbols && $length > 3) {
            $password .= $symbols[random_int(0, strlen($symbols) - 1)];
            $remainingLength = $length - 4;
        } else {
            $remainingLength = $length - 3;
        }
        
        // Riempie il resto della password
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $charset[random_int(0, $charsetLength - 1)];
        }
        
        // Mescola i caratteri
        return str_shuffle($password);
    }
    
    /**
     * Valida la forza di una password
     * 
     * @param string $password Password da validare
     * @return array Risultato validazione con score e suggerimenti
     */
    public static function validatePasswordStrength($password) {
        $score = 0;
        $feedback = [];
        $requirements = [];
        
        // Lunghezza minima
        if (strlen($password) >= 8) {
            $score += 2;
        } elseif (strlen($password) >= 5) {
            $score += 1;
            $feedback[] = 'Considera una password più lunga (almeno 8 caratteri)';
        } else {
            $feedback[] = 'Password troppo corta (minimo 5 caratteri)';
            $requirements[] = 'Almeno 5 caratteri';
        }
        
        // Lettere minuscole
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Aggiungi lettere minuscole';
            $requirements[] = 'Lettere minuscole';
        }
        
        // Lettere maiuscole
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Aggiungi lettere maiuscole';
        }
        
        // Numeri
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Aggiungi numeri';
        }
        
        // Simboli speciali
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 2;
        } else {
            $feedback[] = 'Considera l\'aggiunta di simboli speciali';
        }
        
        // Lunghezza bonus
        if (strlen($password) >= 12) {
            $score += 1;
        }
        
        // Determina il livello di sicurezza
        if ($score >= 7) {
            $strength = 'Molto Forte';
            $color = '#28a745';
        } elseif ($score >= 5) {
            $strength = 'Forte';
            $color = '#17a2b8';
        } elseif ($score >= 3) {
            $strength = 'Media';
            $color = '#ffc107';
        } else {
            $strength = 'Debole';
            $color = '#dc3545';
        }
        
        return [
            'score' => $score,
            'max_score' => 8,
            'strength' => $strength,
            'color' => $color,
            'percentage' => min(100, ($score / 8) * 100),
            'feedback' => $feedback,
            'requirements' => $requirements,
            'is_valid' => $score >= 3 && strlen($password) >= 5
        ];
    }
    
    /**
     * Migra password in chiaro a hash (per migrazione da sistemi legacy)
     * 
     * @param string $plainPassword Password in chiaro
     * @return string Password hashata
     */
    public static function migrateFromPlaintext($plainPassword) {
        Config::log("Migrazione password da testo chiaro", 'INFO');
        return self::hashPassword($plainPassword);
    }
    
    /**
     * Verifica se una stringa è già un hash bcrypt
     * 
     * @param string $string Stringa da verificare
     * @return bool True se è un hash bcrypt
     */
    public static function isBcryptHash($string) {
        // Hash bcrypt inizia sempre con $2y$ (o $2a$, $2x$, $2b$)
        return preg_match('/^\$2[ayxb]\$/', $string) === 1;
    }
    
    /**
     * Pulisce i dati sensibili dalla memoria
     * 
     * @param string &$sensitive_data Riferimento ai dati sensibili
     */
    public static function clearSensitiveData(&$sensitive_data) {
        if (is_string($sensitive_data)) {
            // Sovrascrive la stringa con dati casuali
            $length = strlen($sensitive_data);
            for ($i = 0; $i < 3; $i++) {
                $sensitive_data = str_repeat(chr(random_int(0, 255)), $length);
            }
            $sensitive_data = '';
        }
        unset($sensitive_data);
    }
    
    /**
     * Genera un salt casuale (per compatibilità legacy)
     * 
     * @param int $length Lunghezza del salt
     * @return string Salt generato
     */
    public static function generateSalt($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Audit delle password - controlla pattern comuni
     * 
     * @param string $password Password da analizzare
     * @return array Risultati audit
     */
    public static function auditPassword($password) {
        $issues = [];
        
        // Pattern comuni da evitare
        $commonPatterns = [
            '/(.)\1{2,}/' => 'Evita caratteri ripetuti consecutivi',
            '/^[a-zA-Z]+$/' => 'Password solo lettere - aggiungi numeri o simboli',
            '/^[0-9]+$/' => 'Password solo numeri - aggiungi lettere',
            '/^(password|admin|user|test|123)/i' => 'Evita parole comuni come inizio',
            '/(password|admin|user|test)$/i' => 'Evita parole comuni come fine',
            '/^.{1,4}$/' => 'Password troppo corta',
            '/keyboard|qwerty|asdf|zxcv/i' => 'Evita sequenze di tastiera',
            '/abc|xyz|123|321/i' => 'Evita sequenze semplici'
        ];
        
        foreach ($commonPatterns as $pattern => $message) {
            if (preg_match($pattern, $password)) {
                $issues[] = $message;
            }
        }
        
        // Controlla date comuni
        if (preg_match('/19[0-9]{2}|20[0-9]{2}/', $password)) {
            $issues[] = 'Evita di usare anni nella password';
        }
        
        return [
            'issues' => $issues,
            'risk_level' => count($issues) > 2 ? 'Alto' : (count($issues) > 0 ? 'Medio' : 'Basso')
        ];
    }
}
?>