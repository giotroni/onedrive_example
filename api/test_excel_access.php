<?php
// test_excel_access.php - Test accesso al file Excel
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Test Excel Access</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:0 auto;padding:20px;}";
echo ".result{background:#f5f5f5;padding:15px;margin:10px 0;border-radius:5px;white-space:pre-wrap;}";
echo ".success{background:#d4edda;color:#155724;}";
echo ".error{background:#f8d7da;color:#721c24;}";
echo "button{background:#007cba;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;margin:5px;}";
echo "</style></head><body>";

echo "<h1>🧪 Test Accesso Excel OneDrive</h1>";

// Verifica se abbiamo il token
if (!isset($_SESSION['access_token'])) {
    echo "<div class='error'>❌ Nessun access token in sessione</div>";
    echo "<p><a href='authorize_excel.php'>🔄 Riautorizza</a></p>";
    echo "</body></html>";
    exit;
}

$accessToken = $_SESSION['access_token'];
echo "<div class='success'>✅ Access token trovato in sessione</div>";
echo "<p><strong>Token expires:</strong> " . (isset($_SESSION['token_expires']) ? date('Y-m-d H:i:s', $_SESSION['token_expires']) : 'Unknown') . "</p>";

// Test 1: Lista file OneDrive
echo "<h2>📁 Test 1: Lista file OneDrive</h2>";
echo "<button onclick='listFiles()'>Lista File OneDrive</button>";
echo "<div id='filesResult' class='result'></div>";

// Test 2: Cerca file Excel
echo "<h2>📊 Test 2: Cerca file Excel</h2>";
echo "<input type='text' id='fileName' value='DATI_VP.xlsx' placeholder='Nome file Excel'>";
echo "<button onclick='searchExcel()'>Cerca File Excel</button>";
echo "<div id='searchResult' class='result'></div>";

// Test 3: Test con ID file specifico
echo "<h2>🎯 Test 3: Accesso diretto con ID file</h2>";
echo "<input type='text' id='fileId' placeholder='ID del file Excel' style='width:400px;'>";
echo "<button onclick='testFileAccess()'>Testa Accesso File</button>";
echo "<div id='fileResult' class='result'></div>";

// Test 4: Leggi fogli del file
echo "<h2>📋 Test 4: Lista fogli Excel</h2>";
echo "<button onclick='listSheets()'>Lista Fogli</button>";
echo "<div id='sheetsResult' class='result'></div>";

echo "<script>";
echo "const accessToken = '" . $accessToken . "';";
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
            jsonResult = { raw: result };
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

async function listFiles() {
    document.getElementById('filesResult').textContent = 'Caricando file OneDrive...';
    
    const result = await makeGraphRequest('https://graph.microsoft.com/v1.0/me/drive/root/children');
    
    if (result.ok && result.data.value) {
        let output = 'File trovati in OneDrive:\\n\\n';
        result.data.value.forEach(file => {
            output += '📄 ' + file.name + '\\n';
            output += '   ID: ' + file.id + '\\n';
            output += '   Size: ' + file.size + ' bytes\\n';
            output += '   Modified: ' + file.lastModifiedDateTime + '\\n\\n';
        });
        document.getElementById('filesResult').textContent = output;
        document.getElementById('filesResult').className = 'result success';
    } else {
        document.getElementById('filesResult').textContent = 'Errore: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('filesResult').className = 'result error';
    }
}

async function searchExcel() {
    const fileName = document.getElementById('fileName').value;
    if (!fileName) {
        alert('Inserisci il nome del file');
        return;
    }
    
    document.getElementById('searchResult').textContent = 'Cercando file Excel...';
    
    const result = await makeGraphRequest('https://graph.microsoft.com/v1.0/me/drive/search(q=\\'' + fileName + '\\')');
    
    if (result.ok && result.data.value) {
        if (result.data.value.length === 0) {
            document.getElementById('searchResult').textContent = 'Nessun file trovato con nome: ' + fileName;
            document.getElementById('searchResult').className = 'result error';
        } else {
            let output = 'File Excel trovati (' + result.data.value.length + '):\\n\\n';
            result.data.value.forEach(file => {
                output += '📊 ' + file.name + '\\n';
                output += '   ID: ' + file.id + '\\n';
                output += '   Size: ' + file.size + ' bytes\\n';
                output += '   WebUrl: ' + file.webUrl + '\\n';
                output += '   Modified: ' + file.lastModifiedDateTime + '\\n\\n';
                
                // Auto-fill il primo file ID
                if (!document.getElementById('fileId').value) {
                    document.getElementById('fileId').value = file.id;
                }
            });
            document.getElementById('searchResult').textContent = output;
            document.getElementById('searchResult').className = 'result success';
        }
    } else {
        document.getElementById('searchResult').textContent = 'Errore: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('searchResult').className = 'result error';
    }
}

async function testFileAccess() {
    const fileId = document.getElementById('fileId').value;
    if (!fileId) {
        alert('Inserisci un ID file');
        return;
    }
    
    document.getElementById('fileResult').textContent = 'Testando accesso al file...';
    
    const result = await makeGraphRequest('https://graph.microsoft.com/v1.0/me/drive/items/' + fileId);
    
    if (result.ok) {
        let output = 'File accessibile!\\n\\n';
        output += 'Nome: ' + result.data.name + '\\n';
        output += 'ID: ' + result.data.id + '\\n';
        output += 'Size: ' + result.data.size + ' bytes\\n';
        output += 'Type: ' + (result.data.file ? result.data.file.mimeType : 'Unknown') + '\\n';
        output += 'WebUrl: ' + result.data.webUrl + '\\n\\n';
        output += 'Questo ID file può essere usato in config.php!';
        
        document.getElementById('fileResult').textContent = output;
        document.getElementById('fileResult').className = 'result success';
    } else {
        document.getElementById('fileResult').textContent = 'Errore accesso file: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('fileResult').className = 'result error';
    }
}

async function listSheets() {
    const fileId = document.getElementById('fileId').value;
    if (!fileId) {
        alert('Prima trova un file Excel e inserisci il suo ID');
        return;
    }
    
    document.getElementById('sheetsResult').textContent = 'Caricando fogli Excel...';
    
    const result = await makeGraphRequest('https://graph.microsoft.com/v1.0/me/drive/items/' + fileId + '/workbook/worksheets');
    
    if (result.ok && result.data.value) {
        let output = 'Fogli Excel trovati (' + result.data.value.length + '):\\n\\n';
        result.data.value.forEach(sheet => {
            output += '📋 ' + sheet.name + '\\n';
            output += '   ID: ' + sheet.id + '\\n';
            output += '   Position: ' + sheet.position + '\\n';
            output += '   Visibility: ' + sheet.visibility + '\\n\\n';
        });
        document.getElementById('sheetsResult').textContent = output;
        document.getElementById('sheetsResult').className = 'result success';
    } else {
        document.getElementById('sheetsResult').textContent = 'Errore: ' + JSON.stringify(result.data, null, 2);
        document.getElementById('sheetsResult').className = 'result error';
    }
}
";
echo "</script>";
echo "</body></html>";
?>