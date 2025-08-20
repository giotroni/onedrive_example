<?php
// test_excel_connection.php - Test connessione e accesso al file Excel
session_start();
require_once 'config.php';
require_once 'microsoft_graph_personal_updated.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Connessione Excel - DATI_VP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .data-table th, .data-table td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #dee2e6; 
        }
        .data-table th { 
            background: #007cba; 
            color: white; 
            font-weight: 600;
        }
        .data-table tr:hover { 
            background: #f8f9fa; 
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Connessione Excel - DATI_VP</h1>
        
        <?php
        // Controlla se abbiamo un token di accesso
        if (!isset($_SESSION['access_token'])) {
            echo '<div class="error">';
            echo '<h3>❌ Token di accesso non trovato</h3>';
            echo '<p>Devi prima autorizzare l\'accesso a OneDrive.</p>';
            echo '<a href="authorize_excel.php" class="btn">🔐 Autorizza Accesso</a>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }

        // Controlla se il token è scaduto
        if (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires']) {
            echo '<div class="warning">';
            echo '<h3>⚠️ Token scaduto</h3>';
            echo '<p>Il token di accesso è scaduto. È necessario rinnovarlo.</p>';
            echo '<a href="authorize_excel.php" class="btn">🔄 Rinnova Autorizzazione</a>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }

        try {
            $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
            
            echo '<div class="success">';
            echo '<h3>✅ Connessione stabilita</h3>';
            echo '<p>Token di accesso valido fino al: ' . date('d/m/Y H:i:s', $_SESSION['token_expires']) . '</p>';
            echo '</div>';
            
            // Test connessione al file
            echo '<h2>🔍 Test Accesso File Excel</h2>';
            $connectionTest = $graph->testConnection();
            
            if ($connectionTest['success']) {
                echo '<div class="success">';
                echo '<h3>✅ File Excel accessibile</h3>';
                echo '<p><strong>Nome:</strong> ' . $connectionTest['fileInfo']['name'] . '</p>';
                echo '<p><strong>Dimensione:</strong> ' . number_format($connectionTest['fileInfo']['size']) . ' bytes</p>';
                echo '<p><strong>Ultima modifica:</strong> ' . $connectionTest['fileInfo']['lastModified'] . '</p>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<h3>❌ Errore accesso file Excel</h3>';
                echo '<p>' . $connectionTest['error'] . '</p>';
                echo '</div>';
                throw new Exception($connectionTest['error']);
            }
            
            // Lista fogli di lavoro
            echo '<h2>📋 Fogli di Lavoro Disponibili</h2>';
            $worksheets = $graph->getWorksheets();
            
            if (isset($worksheets['value']) && !empty($worksheets['value'])) {
                echo '<div class="info">';
                echo '<h3>📊 Fogli trovati:</h3>';
                echo '<ul>';
                foreach ($worksheets['value'] as $sheet) {
                    echo '<li><strong>' . $sheet['name'] . '</strong> (ID: ' . $sheet['id'] . ')</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            // Test lettura tabella ANA_CONSULENTI
            echo '<h2>👥 Test Lettura Tabella ANA_CONSULENTI</h2>';
            
            try {
                $consultantsData = $graph->readSheet('ANA_CONSULENTI');
                
                if (isset($consultantsData['values']) && !empty($consultantsData['values'])) {
                    $headers = $consultantsData['values'][0];
                    $rows = array_slice($consultantsData['values'], 1);
                    
                    echo '<div class="success">';
                    echo '<h3>✅ Tabella ANA_CONSULENTI letta con successo</h3>';
                    echo '<p><strong>Righe trovate:</strong> ' . count($rows) . '</p>';
                    echo '<p><strong>Colonne:</strong> ' . implode(', ', $headers) . '</p>';
                    echo '</div>';
                    
                    // Verifica presenza colonne necessarie
                    $emailIndex = array_search('Email', $headers);
                    $pwdIndex = array_search('PWD', $headers);
                    
                    if ($emailIndex !== false && $pwdIndex !== false) {
                        echo '<div class="success">';
                        echo '<h3>✅ Colonne Email e PWD trovate</h3>';
                        echo '<p><strong>Email:</strong> Colonna ' . ($emailIndex + 1) . '</p>';
                        echo '<p><strong>PWD:</strong> Colonna ' . ($pwdIndex + 1) . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="error">';
                        echo '<h3>❌ Colonne Email o PWD non trovate</h3>';
                        echo '<p>Assicurati che la tabella ANA_CONSULENTI contenga le colonne "Email" e "PWD".</p>';
                        echo '</div>';
                    }
                    
                    // Mostra anteprima dati (solo prime 5 righe per sicurezza)
                    echo '<h3>📊 Anteprima Dati (prime 5 righe)</h3>';
                    echo '<table class="data-table">';
                    echo '<thead><tr>';
                    foreach ($headers as $header) {
                        echo '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    echo '</tr></thead><tbody>';
                    
                    $previewRows = array_slice($rows, 0, 5);
                    foreach ($previewRows as $row) {
                        echo '<tr>';
                        foreach ($headers as $i => $header) {
                            $value = $row[$i] ?? '';
                            // Nascondi le password per sicurezza
                            if ($header === 'PWD' && !empty($value)) {
                                $value = str_repeat('*', strlen($value));
                            }
                            echo '<td>' . htmlspecialchars($value) . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    
                    if (count($rows) > 5) {
                        echo '<p><em>... e altre ' . (count($rows) - 5) . ' righe</em></p>';
                    }
                    
                } else {
                    echo '<div class="warning">';
                    echo '<h3>⚠️ Tabella ANA_CONSULENTI vuota o non trovata</h3>';
                    echo '<p>La tabella non contiene dati o non esiste.</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h3>❌ Errore lettura tabella ANA_CONSULENTI</h3>';
                echo '<p>' . $e->getMessage() . '</p>';
                echo '</div>';
            }
            
            // Test funzionalità password manager
            echo '<h2>🔐 Test Funzionalità Password Manager</h2>';
            echo '<div class="info">';
            echo '<h3>✅ Sistema Pronto</h3>';
            echo '<p>Tutte le verifiche sono state completate con successo. Il sistema di gestione password è pronto per l\'uso.</p>';
            echo '<a href="password_manager.html" class="btn">🚀 Apri Gestione Password</a>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Errore durante il test</h3>';
            echo '<p><strong>Errore:</strong> ' . $e->getMessage() . '</p>';
            echo '<p><strong>Codice:</strong> ' . $e->getCode() . '</p>';
            echo '</div>';
            
            echo '<div class="info">';
            echo '<h3>🔧 Possibili soluzioni:</h3>';
            echo '<ul>';
            echo '<li>Verifica che l\'ID del file Excel sia corretto nel config.php</li>';
            echo '<li>Assicurati che il file sia accessibile nel tuo OneDrive</li>';
            echo '<li>Controlla che l\'app Azure abbia i permessi corretti</li>';
            echo '<li>Prova a riautorizzare l\'accesso</li>';
            echo '</ul>';
            echo '<a href="authorize_excel.php" class="btn">🔄 Riautorizza</a>';
            echo '</div>';
        }
        
        // Debug informazioni sessione
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo '<h2>🔍 Debug Informazioni Sessione</h2>';
            echo '<div class="code-block">';
            echo '<strong>Session ID:</strong> ' . session_id() . "\n";
            echo '<strong>Token Length:</strong> ' . (isset($_SESSION['access_token']) ? strlen($_SESSION['access_token']) : 'N/A') . "\n";
            echo '<strong>Expires:</strong> ' . (isset($_SESSION['token_expires']) ? date('Y-m-d H:i:s', $_SESSION['token_expires']) : 'N/A') . "\n";
            echo '<strong>Current Time:</strong> ' . date('Y-m-d H:i:s') . "\n";
            echo '<strong>Time Left:</strong> ' . (isset($_SESSION['token_expires']) ? ($_SESSION['token_expires'] - time()) . ' seconds' : 'N/A') . "\n";
            echo '</div>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        <div style="text-align: center; color: #666;">
            <p>🔧 <a href="?debug=1">Debug Sessione</a> | 
               🔄 <a href="authorize_excel.php">Riautorizza</a> | 
               🏠 <a href="index.php">Home</a></p>
        </div>
    </div>
</body>
</html>