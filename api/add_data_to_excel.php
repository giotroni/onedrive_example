<?php
// add_data_to_excel.php - Aggiunge dati alla tabella Excel
session_start();
require_once 'microsoft_graph_personale.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Aggiungi Dati Excel</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:0 auto;padding:20px;}";
echo ".result{background:#f5f5f5;padding:15px;margin:10px 0;border-radius:5px;}";
echo ".success{background:#d4edda;color:#155724;}";
echo ".error{background:#f8d7da;color:#721c24;}";
echo ".info{background:#d1ecf1;color:#0c5460;}";
echo "button{background:#007cba;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;margin:5px;}";
echo "</style></head><body>";

echo "<h1>📊 Aggiungi Dati a Excel OneDrive</h1>";

// Verifica se abbiamo il token
if (!isset($_SESSION['access_token'])) {
    echo "<div class='result error'>❌ Nessun access token in sessione</div>";
    echo "<p><a href='authorize_excel.php'>🔄 Riautorizza</a></p>";
    echo "</body></html>";
    exit;
}

try {
    // Inizializza la classe con il token dalla sessione
    $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
    
    echo "<div class='result success'>✅ Connesso a Microsoft Graph</div>";
    
    // Dati da aggiungere
    $nomeValue = 'Giorgio';
    $ggValue = 5;
    $sheetName = 'Prova';
    $tableName = 'Tabella1'; // Nome standard per le tabelle Excel
    
    echo "<div class='result info'>";
    echo "<h3>📋 Dati da aggiungere:</h3>";
    echo "<p><strong>Foglio:</strong> $sheetName</p>";
    echo "<p><strong>Tabella:</strong> $tableName</p>";
    echo "<p><strong>Nome:</strong> $nomeValue</p>";
    echo "<p><strong>gg:</strong> $ggValue</p>";
    echo "</div>";
    
    if (isset($_POST['add_data'])) {
        try {
            // Metodo 1: Prova ad aggiungere usando il metodo Table rows
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets('$sheetName')/tables('$tableName')/rows/add";
            
            $data = [
                'values' => [[$nomeValue, $ggValue]]
            ];
            
            $accessToken = $_SESSION['access_token'];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "<div class='result success'>";
                echo "<h3>🎉 Dati aggiunti con successo!</h3>";
                echo "<p>Nuova riga aggiunta alla tabella '$tableName' nel foglio '$sheetName'</p>";
                echo "<pre>" . json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "</pre>";
                echo "</div>";
            } else {
                // Se il metodo table non funziona, proviamo con il range
                echo "<div class='result error'>";
                echo "<h3>⚠️ Metodo Table fallito, provo con Range...</h3>";
                echo "<p>HTTP Code: $httpCode</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
                echo "</div>";
                
                // Metodo 2: Aggiungi usando range (trova prima l'ultima riga)
                $urlRange = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets('$sheetName')/usedRange";
                
                $ch2 = curl_init($urlRange);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ]);
                
                $rangeResponse = curl_exec($ch2);
                $rangeHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($rangeHttpCode >= 200 && $rangeHttpCode < 300) {
                    $rangeData = json_decode($rangeResponse, true);
                    $rowCount = $rangeData['rowCount'];
                    $nextRow = $rowCount + 1;
                    
                    // Aggiungi alla riga successiva
                    $updateUrl = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets('$sheetName')/range(address='A$nextRow:B$nextRow')";
                    
                    $updateData = [
                        'values' => [[$nomeValue, $ggValue]]
                    ];
                    
                    $ch3 = curl_init($updateUrl);
                    curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($updateData));
                    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json'
                    ]);
                    
                    $updateResponse = curl_exec($ch3);
                    $updateHttpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                    curl_close($ch3);
                    
                    if ($updateHttpCode >= 200 && $updateHttpCode < 300) {
                        echo "<div class='result success'>";
                        echo "<h3>🎉 Dati aggiunti con successo (metodo Range)!</h3>";
                        echo "<p>Nuova riga aggiunta alla riga $nextRow del foglio '$sheetName'</p>";
                        echo "<pre>" . json_encode(json_decode($updateResponse, true), JSON_PRETTY_PRINT) . "</pre>";
                        echo "</div>";
                    } else {
                        echo "<div class='result error'>";
                        echo "<h3>❌ Errore anche con metodo Range</h3>";
                        echo "<p>HTTP Code: $updateHttpCode</p>";
                        echo "<pre>" . htmlspecialchars($updateResponse) . "</pre>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='result error'>";
                    echo "<h3>❌ Errore nel leggere il range del foglio</h3>";
                    echo "<p>HTTP Code: $rangeHttpCode</p>";
                    echo "<pre>" . htmlspecialchars($rangeResponse) . "</pre>";
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='result error'>";
            echo "<h3>❌ Errore durante l'aggiunta:</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
    }
    
    // Form per aggiungere i dati
    if (!isset($_POST['add_data'])) {
        echo "<form method='post'>";
        echo "<div class='result info'>";
        echo "<h3>🚀 Pronto per aggiungere i dati?</h3>";
        echo "<p>Questo aggiungerà una nuova riga con:</p>";
        echo "<ul>";
        echo "<li><strong>Nome:</strong> $nomeValue</li>";
        echo "<li><strong>gg:</strong> $ggValue</li>";
        echo "</ul>";
        echo "<button type='submit' name='add_data' style='background:#28a745;font-size:16px;'>➕ Aggiungi Dati</button>";
        echo "</div>";
        echo "</form>";
    }
    
    // Sezione debug - mostra struttura del foglio
    echo "<h2>🔍 Debug Foglio Excel</h2>";
    echo "<button onclick='showSheetInfo()'>Mostra Info Foglio</button>";
    echo "<button onclick='showTables()'>Mostra Tabelle</button>";
    echo "<button onclick='showData()'>Mostra Dati Attuali</button>";
    echo "<div id='debugResult' class='result'></div>";
    
} catch (Exception $e) {
    echo "<div class='result error'>";
    echo "<h3>❌ Errore di connessione:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<script>";
echo "const accessToken = '" . $_SESSION['access_token'] . "';";
echo "const fileId = '" . Config::EXCEL_FILE_ID . "';";
echo "const sheetName = 'Prova';";
echo "
async function makeGraphRequest(url, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Authorization': 'Bearer ' + accessToken,
                'Content-Type': 'application/json'
            }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        const result = await response.text();
        
        let jsonResult;
        try {
            jsonResult = JSON.parse(result);
        } catch (e) {
            jsonResult = { raw: result, status: response.status };
        }
        
        return {
            ok: response.ok,
            status: response.status,
            data: jsonResult
        };
    } catch (error) {
        return {
            ok: false,
            status: 0,
            data: { error: error.message }
        };
    }
}

async function showSheetInfo() {
    document.getElementById('debugResult').textContent = 'Caricando info foglio...';
    
    const result = await makeGraphRequest(`https://graph.microsoft.com/v1.0/me/drive/items/\${fileId}/workbook/worksheets('\${sheetName}')`);
    
    if (result.ok) {
        document.getElementById('debugResult').textContent = 'Info Foglio:\\n' + JSON.stringify(result.data, null, 2);
        document.getElementById('debugResult').className = 'result success';
    } else {
        document.getElementById('debugResult').textContent = 'Errore: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('debugResult').className = 'result error';
    }
}

async function showTables() {
    document.getElementById('debugResult').textContent = 'Caricando tabelle...';
    
    const result = await makeGraphRequest(`https://graph.microsoft.com/v1.0/me/drive/items/\${fileId}/workbook/worksheets('\${sheetName}')/tables`);
    
    if (result.ok) {
        let output = 'Tabelle nel foglio \"' + sheetName + '\":\\n\\n';
        if (result.data.value && result.data.value.length > 0) {
            result.data.value.forEach(table => {
                output += 'Nome: ' + table.name + '\\n';
                output += 'ID: ' + table.id + '\\n';
                if (table.range) output += 'Range: ' + table.range.address + '\\n';
                output += '\\n';
            });
        } else {
            output += 'Nessuna tabella trovata. Potrebbe essere necessario convertire i dati in tabella.';
        }
        document.getElementById('debugResult').textContent = output;
        document.getElementById('debugResult').className = 'result success';
    } else {
        document.getElementById('debugResult').textContent = 'Errore: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('debugResult').className = 'result error';
    }
}

async function showData() {
    document.getElementById('debugResult').textContent = 'Caricando dati attuali...';
    
    const result = await makeGraphRequest(`https://graph.microsoft.com/v1.0/me/drive/items/\${fileId}/workbook/worksheets('\${sheetName}')/usedRange`);
    
    if (result.ok && result.data.values) {
        let output = 'Dati attuali nel foglio \"' + sheetName + '\":\\n\\n';
        output += 'Righe: ' + result.data.rowCount + ', Colonne: ' + result.data.columnCount + '\\n\\n';
        
        result.data.values.forEach((row, index) => {
            output += 'Riga ' + (index + 1) + ': [' + row.join(', ') + ']\\n';
        });
        
        document.getElementById('debugResult').textContent = output;
        document.getElementById('debugResult').className = 'result success';
    } else {
        document.getElementById('debugResult').textContent = 'Errore o foglio vuoto: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('debugResult').className = 'result error';
    }
}
";
echo "</script>";

echo "</body></html>";
?>