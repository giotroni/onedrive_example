<?php
// oauth_callback_fixed.php - Callback OAuth che salva correttamente il token
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>OAuth Callback</title>";
echo "<style>body{font-family:Arial;max-width:600px;margin:0 auto;padding:20px;}";
echo ".success{background:#d4edda;padding:15px;margin:10px 0;border-radius:5px;color:#155724;}";
echo ".error{background:#f8d7da;padding:15px;margin:10px 0;border-radius:5px;color:#721c24;}";
echo ".info{background:#d1ecf1;padding:15px;margin:10px 0;border-radius:5px;color:#0c5460;}";
echo "button{background:#007cba;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;margin:5px;}";
echo "</style></head><body>";

echo "<h1>🔐 OAuth Callback Result</h1>";

// Debug session iniziale
echo "<div class='info'>";
echo "<h3>🔍 Session iniziale:</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "</div>";

if (isset($_GET['error'])) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore OAuth:</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($_GET['error']) . "</p>";
    echo "<p><strong>Description:</strong> " . htmlspecialchars($_GET['error_description'] ?? 'N/A') . "</p>";
    echo "</div>";
    echo "<p><a href='authorize_excel.php'>🔄 Riprova autorizzazione</a></p>";
    echo "</body></html>";
    exit;
}

if (!isset($_GET['code'])) {
    echo "<div class='error'>";
    echo "<h3>❌ Nessun codice di autorizzazione ricevuto</h3>";
    echo "</div>";
    echo "<p><a href='authorize_excel.php'>🔄 Inizia autorizzazione</a></p>";
    echo "</body></html>";
    exit;
}

$code = $_GET['code'];
echo "<div class='success'>";
echo "<h3>✅ Codice ricevuto:</h3>";
echo "<p>" . htmlspecialchars($code) . "</p>";
echo "</div>";

// Scambia code con token
try {
    // Credenziali: INSERISCI LE TUE CREDENZIALI QUI
    $clientId = 'TUE_CREDENZIALI';
    $clientSecret = 'TUE_CREDENZIALI';

    $redirectUri = 'https://vaglioandpartners.com/test/api/oauth_callback_fixed.php';
    
    $tokenUrl = 'https://login.microsoftonline.com/consumers/oauth2/v2.0/token';
    
    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'scope' => 'https://graph.microsoft.com/Files.ReadWrite offline_access'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['access_token'])) {
        echo "<div class='success'>";
        echo "<h3>🎉 Token ottenuto con successo!</h3>";
        echo "<p><strong>Token Type:</strong> " . ($responseData['token_type'] ?? 'Bearer') . "</p>";
        echo "<p><strong>Expires In:</strong> " . ($responseData['expires_in'] ?? 3600) . " seconds</p>";
        echo "<p><strong>Scope:</strong> " . ($responseData['scope'] ?? 'N/A') . "</p>";
        echo "</div>";
        
        // PULISCI la sessione prima di salvare il nuovo token
        $_SESSION = [];
        
        // Salva token in sessione
        $_SESSION['access_token'] = $responseData['access_token'];
        $_SESSION['token_type'] = $responseData['token_type'] ?? 'Bearer';
        $_SESSION['expires_in'] = $responseData['expires_in'] ?? 3600;
        $_SESSION['token_expires'] = time() + ($responseData['expires_in'] ?? 3600);
        $_SESSION['scope'] = $responseData['scope'] ?? '';
        
        if (isset($responseData['refresh_token'])) {
            $_SESSION['refresh_token'] = $responseData['refresh_token'];
        }
        
        // Salva timestamp
        $_SESSION['oauth_completed'] = time();
        $_SESSION['oauth_session_id'] = session_id();
        
        echo "<div class='info'>";
        echo "<h3>💾 Token salvato in sessione:</h3>";
        echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
        echo "<p><strong>Access Token Length:</strong> " . strlen($_SESSION['access_token']) . " chars</p>";
        echo "<p><strong>Expires:</strong> " . date('Y-m-d H:i:s', $_SESSION['token_expires']) . "</p>";
        echo "</div>";
        
        // Debug session dopo salvataggio
        echo "<div class='info'>";
        echo "<h3>🔍 Session dopo salvataggio:</h3>";
        echo "<pre>";
        foreach ($_SESSION as $key => $value) {
            if ($key === 'access_token') {
                echo "$key: " . substr($value, 0, 50) . "... (" . strlen($value) . " chars)\n";
            } elseif ($key === 'refresh_token') {
                echo "$key: " . substr($value, 0, 30) . "... (" . strlen($value) . " chars)\n";
            } else {
                echo "$key: $value\n";
            }
        }
        echo "</pre>";
        echo "</div>";
        
        echo "<div class='success'>";
        echo "<h3>🚀 Pronto per testare Excel!</h3>";
        echo "<p><a href='test_excel_access.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🧪 Testa Accesso Excel</a></p>";
        echo "<p><a href='session_debug.php'>🔍 Debug Sessione</a></p>";
        echo "</div>";
        
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Errore nel token exchange:</h3>";
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        if (isset($responseData['error'])) {
            echo "<p><strong>Error:</strong> " . htmlspecialchars($responseData['error']) . "</p>";
            echo "<p><strong>Description:</strong> " . htmlspecialchars($responseData['error_description'] ?? 'N/A') . "</p>";
        }
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore durante token exchange:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>