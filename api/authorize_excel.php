<?php
// authorize_excel.php - Inizia il processo di autorizzazione per Excel
require_once 'config.php';
require_once 'microsoft_graph_personal.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorizzazione Excel - OneDrive</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .auth-button {
            background: #0078d4;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 20px;
        }
        .auth-button:hover {
            background: #106ebe;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Autorizzazione Excel API</h1>
        <p>Per accedere al tuo file Excel su OneDrive personale, devi autorizzare l'applicazione.</p>
        
        <div class="step">
            <h3>📋 Cosa succederà:</h3>
            <ol>
                <li>Verrai reindirizzato a Microsoft per il login</li>
                <li>Dovrai accettare i permessi richiesti</li>
                <li>Tornerai qui con l'autorizzazione completata</li>
                <li>Potrai testare l'accesso al file Excel</li>
            </ol>
        </div>
        
        <div class="step">
            <h3>🔒 Permessi richiesti:</h3>
            <ul>
                <li><strong>Files.ReadWrite:</strong> Lettura e scrittura file OneDrive</li>
                <li><strong>offline_access:</strong> Accesso persistente (refresh token)</li>
            </ul>
        </div>
        
        <a href="<?php echo MicrosoftGraphPersonal::getAuthorizationUrl(); ?>" class="auth-button">
            🚀 Autorizza Accesso OneDrive
        </a>
        
        <div class="step">
            <h3>⚠️ Nota importante:</h3>
            <p>Assicurati di aver aggiornato la tua App Registration Azure con:</p>
            <ul>
                <li><strong>Redirect URI:</strong> https://vaglioandpartners.com/test/api/oauth_callback.php</li>
                <li><strong>Permissions:</strong> Delegated permissions (non Application)</li>
            </ul>
        </div>
    </div>
</body>
</html>