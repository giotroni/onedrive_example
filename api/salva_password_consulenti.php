<?php
// salva_password_consulenti.php - Salva password hash nella tabella ANA_CONSULENTI
session_start();
require_once 'config.php';
require_once 'microsoft_graph_personal.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salva Password Consulenti</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .password-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        .password-list {
            display: grid;
            gap: 10px;
            margin: 15px 0;
        }
        .password-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .consulente-id {
            font-family: monospace;
            font-weight: bold;
            color: #007bff;
        }
        .password-plain {
            font-family: monospace;
            background: #fff3cd;
            padding: 4px 8px;
            border-radius: 3px;
            color: #856404;
        }
        .password-hash {
            font-family: monospace;
            font-size: 12px;
            color: #6c757d;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .confirmation {
            background: #fff3cd;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border: 2px solid #ffc107;
        }
        .confirm-buttons {
            text-align: center;
            margin-top: 20px;
        }
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-confirm {
            background: #dc3545;
            color: white;
        }
        .btn-confirm:hover {
            background: #c82333;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        .btn-cancel:hover {
            background: #5a6268;
        }
        .btn-back {
            background: #007bff;
            color: white;
        }
        .btn-back:hover {
            background: #0056b3;
        }
        
        .progress {
            background: #f8f9fa;
            border-radius: 5px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            height: 30px;
            background: #28a745;
            color: white;
            text-align: center;
            line-height: 30px;
            transition: width 0.3s ease;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .result-table th,
        .result-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .result-table th {
            background: #007bff;
            color: white;
        }
        .result-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .status-icon {
            font-size: 20px;
            margin-right: 5px;
        }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Salva Password Consulenti in ANA_CONSULENTI</h1>

<?php
// Password da salvare
$passwordData = [
    'CONS001' => 'Boss01',
    'CONS002' => 'CeoCeo02',
    'CONS003' => 'Partner1963',
    'CONS004' => 'Gattina99',
    'CONS005' => 'Analista00'
];

// Genera hash delle password
$hashedPasswords = [];
foreach ($passwordData as $consultId => $plainPassword) {
    $hashedPasswords[$consultId] = [
        'plain' => $plainPassword,
        'hash' => password_hash($plainPassword, PASSWORD_DEFAULT)
    ];
}

try {
    // Verifica token di accesso
    if (!isset($_SESSION['access_token'])) {
        echo "<div class='error'>";
        echo "<h3>❌ Token di accesso non trovato</h3>";
        echo "<p>È necessario autorizzare l'accesso a Excel.</p>";
        echo "<a href='authorize_excel.php' class='btn-back'>🔐 Autorizza</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    if (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires']) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Token scaduto</h3>";
        echo "<a href='authorize_excel.php' class='btn-back'>🔄 Rinnova</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // Mostra le password che verranno salvate
    echo "<div class='password-info'>";
    echo "<h3>🔑 Password da salvare (con hash):</h3>";
    echo "<div class='password-list'>";
    
    foreach ($hashedPasswords as $consultId => $data) {
        echo "<div class='password-item'>";
        echo "<span class='consulente-id'>$consultId</span>";
        echo "<span class='password-plain'>{$data['plain']}</span>";
        echo "<span class='password-hash' title='{$data['hash']}'>" . substr($data['hash'], 0, 50) . "...</span>";
        echo "</div>";
    }
    
    echo "</div>";
    echo "<p><strong>Algoritmo:</strong> password_hash() con PASSWORD_DEFAULT (bcrypt)</p>";
    echo "</div>";

    // Se non è stata confermata l'operazione, mostra il form di conferma
    if (!isset($_POST['confirm_save'])) {
        echo "<div class='confirmation'>";
        echo "<h3>⚠️ Conferma Operazione</h3>";
        echo "<p><strong>ATTENZIONE:</strong> Questa operazione aggiornerà la colonna PWD nella tabella ANA_CONSULENTI con le password hashate.</p>";
        echo "<p>Le password verranno salvate con hash sicuro (bcrypt) e non saranno più recuperabili in chiaro.</p>";
        echo "<p><strong>Vuoi procedere?</strong></p>";
        
        echo "<form method='POST'>";
        echo "<div class='confirm-buttons'>";
        echo "<button type='submit' name='confirm_save' value='1' class='btn-confirm'>✅ Conferma e Salva</button>";
        echo "<a href='visualizza_fact_giornate.php' class='btn-cancel'>❌ Annulla</a>";
        echo "</div>";
        echo "</form>";
        echo "</div>";
        
        echo "</div></body></html>";
        exit;
    }

    // Operazione confermata, procediamo
    echo "<div class='info'>";
    echo "<h3>🚀 Inizializzazione salvataggio password...</h3>";
    echo "</div>";

    $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
    
    // Leggi la struttura attuale della tabella ANA_CONSULENTI
    echo "<div class='info'>📊 Lettura tabella ANA_CONSULENTI...</div>";
    $consulentiData = $graph->readSheet('ANA_CONSULENTI');
    
    if (!$consulentiData || !isset($consulentiData['values']) || empty($consulentiData['values'])) {
        throw new Exception("Impossibile leggere ANA_CONSULENTI");
    }

    $headers = $consulentiData['values'][0];
    $rows = array_slice($consulentiData['values'], 1);
    
    // Trova gli indici delle colonne
    $idConsIndex = array_search('ID_CONSULENTE', $headers);
    $pwdIndex = array_search('PWD', $headers);
    
    if ($idConsIndex === false) {
        throw new Exception("Colonna 'ID_CONSULENTE' non trovata");
    }
    
    // Se la colonna PWD non esiste, dobbiamo aggiungerla
    $needToAddPwdColumn = ($pwdIndex === false);
    
    if ($needToAddPwdColumn) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Colonna PWD non trovata</h3>";
        echo "<p>La colonna PWD non esiste nella tabella. Verrà aggiunta automaticamente.</p>";
        echo "</div>";
        
        // Aggiungi colonna PWD all'header
        $headers[] = 'PWD';
        $pwdIndex = count($headers) - 1;
        
        // Estendi tutte le righe esistenti con una colonna vuota
        foreach ($rows as &$row) {
            while (count($row) <= $pwdIndex) {
                $row[] = '';
            }
        }
        unset($row);
    }

    echo "<div class='success'>";
    echo "<h3>✅ Struttura tabella analizzata</h3>";
    echo "<p><strong>Colonne trovate:</strong> " . implode(', ', $headers) . "</p>";
    echo "<p><strong>Righe esistenti:</strong> " . count($rows) . "</p>";
    echo "<p><strong>Colonna PWD:</strong> " . ($needToAddPwdColumn ? "Aggiunta (indice $pwdIndex)" : "Esistente (indice $pwdIndex)") . "</p>";
    echo "</div>";

    // Progress bar
    echo "<div class='progress'>";
    echo "<div class='progress-bar' id='progressBar' style='width: 0%'>0%</div>";
    echo "</div>";

    // Aggiorna le password riga per riga
    $results = [];
    $totalUpdates = count($hashedPasswords);
    $currentUpdate = 0;

    foreach ($hashedPasswords as $consultId => $data) {
        $currentUpdate++;
        $progressPercent = ($currentUpdate / $totalUpdates) * 100;
        
        echo "<script>";
        echo "document.getElementById('progressBar').style.width = '{$progressPercent}%';";
        echo "document.getElementById('progressBar').textContent = 'Aggiornamento $consultId... {$progressPercent}%';";
        echo "</script>";
        
        try {
            // Trova la riga del consulente
            $rowIndex = null;
            foreach ($rows as $index => $row) {
                if (isset($row[$idConsIndex]) && $row[$idConsIndex] === $consultId) {
                    $rowIndex = $index + 2; // +2 perché Excel è 1-based e saltiamo l'header
                    break;
                }
            }
            
            if ($rowIndex === null) {
                $results[$consultId] = ['status' => 'error', 'message' => 'Consulente non trovato nella tabella'];
                continue;
            }
            
            // Se la colonna PWD non esiste, dobbiamo prima aggiungerla
            if ($needToAddPwdColumn) {
                echo "<div class='info'>📝 Aggiungendo colonna PWD per la prima volta...</div>";
                
                // Aggiorna l'header per aggiungere la colonna PWD
                try {
                    $headerRange = "ANA_CONSULENTI!1:1";
                    $newHeaders = [$headers];
                    
                    $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/ANA_CONSULENTI/range(address='{$headerRange}')";
                    $updateData = ['values' => $newHeaders];
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $_SESSION['access_token'],
                        'Content-Type: application/json'
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode < 200 || $httpCode >= 300) {
                        throw new Exception("Errore aggiornamento header: $response");
                    }
                    
                    $needToAddPwdColumn = false; // Non farlo più per le altre righe
                    echo "<div class='success'>✅ Colonna PWD aggiunta con successo</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Errore aggiunta colonna: " . $e->getMessage() . "</div>";
                    $results[$consultId] = ['status' => 'error', 'message' => 'Errore aggiunta colonna: ' . $e->getMessage()];
                    continue;
                }
            }
            
            // Aggiorna la singola cella della password
            $cellAddress = chr(65 + $pwdIndex) . $rowIndex; // Converte l'indice in lettera (A, B, C...)
            $range = "ANA_CONSULENTI!{$cellAddress}:{$cellAddress}";
            
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/ANA_CONSULENTI/range(address='{$range}')";
            $updateData = ['values' => [[$data['hash']]]];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $_SESSION['access_token'],
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $results[$consultId] = ['status' => 'success', 'message' => "Password salvata in cella $cellAddress"];
                echo "<div class='info'>✅ $consultId: Password salvata in cella $cellAddress</div>";
            } else {
                $results[$consultId] = ['status' => 'error', 'message' => "Errore HTTP $httpCode: $response"];
                echo "<div class='error'>❌ $consultId: Errore HTTP $httpCode</div>";
            }
            
        } catch (Exception $e) {
            $results[$consultId] = ['status' => 'error', 'message' => $e->getMessage()];
            echo "<div class='error'>❌ $consultId: " . $e->getMessage() . "</div>";
        }
        
        // Flush output per mostrare progress in tempo reale
        ob_flush();
        flush();
        usleep(1000000); // Pausa di 1 secondo tra gli aggiornamenti per evitare rate limiting
    }

    echo "<script>";
    echo "document.getElementById('progressBar').style.width = '100%';";
    echo "document.getElementById('progressBar').textContent = 'Salvataggio completato!';";
    echo "</script>";

    // Mostra i risultati del salvataggio effettivo
    echo "<div class='success'>";
    echo "<h3>✅ Salvataggio Completato</h3>";
    $successCount = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
    $errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
    echo "<p><strong>✅ Successi:</strong> $successCount</p>";
    echo "<p><strong>❌ Errori:</strong> $errorCount</p>";
    echo "</div>";

    echo "<table class='result-table'>";
    echo "<thead>";
    echo "<tr><th>ID Consulente</th><th>Password Originale</th><th>Hash Generato</th><th>Cella Excel</th><th>Stato</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($results as $consultId => $result) {
        $statusIcon = $result['status'] === 'success' ? '✅' : '❌';
        $statusClass = $result['status'] === 'success' ? 'status-success' : 'status-error';
        $hash = $hashedPasswords[$consultId]['hash'];
        
        // Estrai informazioni sulla cella se presente nel messaggio
        $cellInfo = '';
        if ($result['status'] === 'success' && strpos($result['message'], 'cella ') !== false) {
            preg_match('/cella ([A-Z]+\d+)/', $result['message'], $matches);
            $cellInfo = $matches[1] ?? '';
        }
        
        echo "<tr>";
        echo "<td class='consulente-id'>$consultId</td>";
        echo "<td class='password-plain'>{$hashedPasswords[$consultId]['plain']}</td>";
        echo "<td class='password-hash' title='$hash'>" . substr($hash, 0, 40) . "...</td>";
        echo "<td><strong>$cellInfo</strong></td>";
        echo "<td class='$statusClass'><span class='status-icon'>$statusIcon</span>{$result['message']}</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";

    echo "<script>";
    echo "document.getElementById('progressBar').textContent = 'Completato!';";
    echo "</script>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore durante il salvataggio</h3>";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Codice:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
    
    if ($e->getCode() == 401) {
        echo "<div class='warning'>";
        echo "<h3>🔐 Problema di autenticazione</h3>";
        echo "<a href='authorize_excel.php' class='btn-back'>🔄 Rinnova</a>";
        echo "</div>";
    }
}
?>

        <div style='text-align: center; margin-top: 30px;'>
            <a href='visualizza_fact_giornate.php' class='btn-back'>🔙 Torna al Report</a>
            <button onclick='location.reload()' class='btn-back'>🔄 Ricarica</button>
        </div>
    </div>

    <script>
        // Conferma prima di lasciare la pagina durante il salvataggio
        let isSaving = <?php echo isset($_POST['confirm_save']) ? 'true' : 'false'; ?>;
        
        if (isSaving) {
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault();
                e.returnValue = 'Operazione in corso. Sei sicuro di voler uscire?';
            });
        }
    </script>
</body>
</html>