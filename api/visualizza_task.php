<?php
// visualizza_task.php - Visualizza task da ANA_TASK con filtri
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
    <title>Visualizza Task ANA_TASK</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
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
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        .filters h3 {
            margin-top: 0;
            color: #495057;
        }
        .filter-item {
            margin: 10px 0;
            font-size: 14px;
        }
        .filter-value {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        tr:hover {
            background: #e3f2fd;
        }
        .task-id {
            font-family: monospace;
            font-weight: bold;
            color: #007bff;
        }
        .task-name {
            font-weight: 500;
            color: #2c3e50;
        }
        .task-type, .task-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .type-campo {
            background: #28a745;
            color: white;
        }
        .status-in-corso {
            background: #ffc107;
            color: #212529;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        .count-info {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 5px;
            font-weight: bold;
            color: #1976d2;
        }
        
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        button:hover {
            background: #0056b3;
        }
        .btn-refresh {
            background: #28a745;
        }
        .btn-refresh:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Visualizza Task ANA_TASK</h1>
        
        <div class="filters">
            <h3>🔍 Filtri Applicati:</h3>
            <div class="filter-item">
                <strong>Tipo:</strong> <span class="filter-value">CAMPO</span>
            </div>
            <div class="filter-item">
                <strong>Stato Task:</strong> <span class="filter-value">In corso</span>
            </div>
            <div class="filter-item">
                <strong>Campi Visualizzati:</strong> ID_TASK, Task, Tipo, Stato_Task
            </div>
        </div>

<?php
try {
    // Verifica se abbiamo un token di accesso
    if (!isset($_SESSION['access_token'])) {
        echo "<div class='error'>";
        echo "<h3>❌ Token di accesso non trovato</h3>";
        echo "<p>È necessario autorizzare l'accesso a Excel prima di poter visualizzare i dati.</p>";
        echo "<a href='authorize_excel.php' class='button'>🔐 Autorizza Accesso Excel</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // Verifica se il token è scaduto
    if (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires']) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Token scaduto</h3>";
        echo "<p>Il token di accesso è scaduto. È necessario rinnovare l'autorizzazione.</p>";
        echo "<a href='authorize_excel.php' class='button'>🔄 Rinnova Autorizzazione</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<h3>🔑 Stato Autenticazione:</h3>";
    echo "<p><strong>Token presente:</strong> ✅</p>";
    echo "<p><strong>Scade:</strong> " . date('Y-m-d H:i:s', $_SESSION['token_expires']) . "</p>";
    echo "</div>";

    // Inizializza client Microsoft Graph
    $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
    
    echo "<div class='info'>";
    echo "<h3>📊 Caricamento dati...</h3>";
    echo "</div>";

    // Leggi dati dal foglio ANA_TASK
    $sheetData = $graph->readSheet(Config::SHEET_ANA_TASK);
    
    if (!$sheetData || !isset($sheetData['values'])) {
        echo "<div class='error'>";
        echo "<h3>❌ Errore nel caricamento dati</h3>";
        echo "<p>Impossibile leggere i dati dal foglio ANA_TASK.</p>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    $values = $sheetData['values'];
    
    if (empty($values)) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Nessun dato trovato</h3>";
        echo "<p>Il foglio ANA_TASK è vuoto.</p>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // La prima riga dovrebbe contenere gli header
    $headers = $values[0];
    
    // Trova gli indici delle colonne che ci interessano
    $columnIndexes = [];
    $requiredColumns = ['ID_TASK', 'Task', 'Tipo', 'Stato_Task'];
    
    foreach ($requiredColumns as $column) {
        $index = array_search($column, $headers);
        if ($index === false) {
            echo "<div class='error'>";
            echo "<h3>❌ Colonna mancante</h3>";
            echo "<p>La colonna '$column' non è stata trovata nel foglio.</p>";
            echo "<p><strong>Colonne disponibili:</strong> " . implode(', ', $headers) . "</p>";
            echo "</div>";
            echo "</div></body></html>";
            exit;
        }
        $columnIndexes[$column] = $index;
    }

    echo "<div class='success'>";
    echo "<h3>✅ Dati caricati con successo</h3>";
    echo "<p><strong>Righe totali:</strong> " . (count($values) - 1) . " (escluso header)</p>";
    echo "<p><strong>Colonne trovate:</strong> " . implode(', ', $requiredColumns) . "</p>";
    echo "</div>";

    // Filtra i dati
    $filteredData = [];
    
    for ($i = 1; $i < count($values); $i++) {
        $row = $values[$i];
        
        // Verifica che la riga abbia abbastanza colonne
        if (count($row) <= max($columnIndexes)) {
            continue;
        }
        
        $tipo = $row[$columnIndexes['Tipo']] ?? '';
        $statoTask = $row[$columnIndexes['Stato_Task']] ?? '';
        
        // Applica filtri: Tipo = "CAMPO" AND Stato_Task = "In corso"
        if (trim($tipo) === 'CAMPO' && trim($statoTask) === 'In corso') {
            $filteredData[] = [
                'ID_TASK' => $row[$columnIndexes['ID_TASK']] ?? '',
                'Task' => $row[$columnIndexes['Task']] ?? '',
                'Tipo' => $row[$columnIndexes['Tipo']] ?? '',
                'Stato_Task' => $row[$columnIndexes['Stato_Task']] ?? ''
            ];
        }
    }

    echo "<div class='count-info'>";
    echo "📊 Trovati " . count($filteredData) . " task che soddisfano i criteri";
    echo "</div>";

    if (empty($filteredData)) {
        echo "<div class='no-data'>";
        echo "<h3>📭 Nessun task trovato</h3>";
        echo "<p>Non ci sono task con Tipo = 'CAMPO' e Stato_Task = 'In corso'</p>";
        echo "</div>";
    } else {
        // Visualizza la tabella
        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>🆔 ID Task</th>";
        echo "<th>📝 Task</th>";
        echo "<th>🏷️ Tipo</th>";
        echo "<th>📊 Stato Task</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($filteredData as $row) {
            echo "<tr>";
            echo "<td><span class='task-id'>" . htmlspecialchars($row['ID_TASK']) . "</span></td>";
            echo "<td><span class='task-name'>" . htmlspecialchars($row['Task']) . "</span></td>";
            echo "<td><span class='task-type type-campo'>" . htmlspecialchars($row['Tipo']) . "</span></td>";
            echo "<td><span class='task-status status-in-corso'>" . htmlspecialchars($row['Stato_Task']) . "</span></td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }

    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<button onclick='location.reload()' class='btn-refresh'>🔄 Aggiorna Dati</button>";
    echo "<button onclick='window.open(\"session_debug.php\", \"_blank\")'>🔍 Debug Sessione</button>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore durante la lettura dei dati</h3>";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Codice:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
    
    if ($e->getCode() == 401) {
        echo "<div class='warning'>";
        echo "<h3>🔐 Problema di autenticazione</h3>";
        echo "<p>Il token potrebbe essere scaduto o non valido. Prova a rinnovare l'autorizzazione.</p>";
        echo "<a href='authorize_excel.php' class='button'>🔄 Rinnova Autorizzazione</a>";
        echo "</div>";
    }
}
?>

    </div>
</body>
</html>