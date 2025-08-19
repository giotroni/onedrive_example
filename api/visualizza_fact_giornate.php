<?php
// visualizza_fact_giornate.php - Visualizza FACT_GIORNATE con join delle tabelle correlate
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
    <title>Report FACT_GIORNATE</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
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
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        select, button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
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
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            text-align: center;
        }
        .summary-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .summary-label {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            white-space: nowrap;
        }
        th {
            background: #007bff;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        tr:hover {
            background: #e3f2fd;
        }
        
        .task-id, .consulente-id {
            font-family: monospace;
            font-weight: bold;
            color: #007bff;
        }
        .task-name {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        .consulente-name {
            font-weight: 500;
            color: #2c3e50;
        }
        .data-cell {
            font-family: monospace;
            color: #495057;
            font-weight: bold;
            text-align: center;
        }
        .gg-cell {
            text-align: right;
            font-weight: bold;
            color: #28a745;
        }
        .note-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9em;
            color: #6c757d;
        }
        .month-group {
            background: #e3f2fd;
            font-weight: bold;
            color: #1976d2;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .summary-cards {
                grid-template-columns: 1fr;
            }
            table {
                font-size: 12px;
            }
            th, td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Report FACT_GIORNATE con Task e Consulenti</h1>
        
        <div class="filters">
            <h3>🔍 Filtri e Opzioni:</h3>
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="monthFilter"><strong>📅 Mese:</strong></label>
                        <select name="month" id="monthFilter">
                            <option value="">Tutti i mesi</option>
                            <?php
                            $currentMonth = $_GET['month'] ?? '';
                            $months = [
                                '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                                '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                                '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                                '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
                            ];
                            foreach ($months as $num => $name) {
                                $selected = ($currentMonth === $num) ? 'selected' : '';
                                echo "<option value='$num' $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="yearFilter"><strong>📆 Anno:</strong></label>
                        <select name="year" id="yearFilter">
                            <option value="">Tutti gli anni</option>
                            <?php
                            $currentYear = $_GET['year'] ?? date('Y');
                            for ($year = 2020; $year <= date('Y') + 1; $year++) {
                                $selected = ($currentYear == $year) ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit">🔍 Filtra</button>
                    <button type="button" onclick="location.href='?'" class="btn-refresh">🔄 Reset</button>
                </div>
            </form>
            
            <div style="margin-top: 15px; font-size: 14px; color: #6c757d;">
                <strong>Filtri Task applicati:</strong> Tipo = "CAMPO"<br>
                <strong>Formato date:</strong> Le date Excel (numeri seriali) vengono convertite automaticamente in gg/mm/aa
            </div>
        </div>

<?php
try {
    // Verifica token di accesso
    if (!isset($_SESSION['access_token'])) {
        echo "<div class='error'>";
        echo "<h3>❌ Token di accesso non trovato</h3>";
        echo "<p>È necessario autorizzare l'accesso a Excel.</p>";
        echo "<a href='authorize_excel.php' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔐 Autorizza</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    if (isset($_SESSION['token_expires']) && time() >= $_SESSION['token_expires']) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Token scaduto - È necessario rinnovare l'autorizzazione</h3>";
        echo "<a href='authorize_excel.php' style='background:#ffc107;color:#212529;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔄 Rinnova</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
    
    echo "<div class='loading'>📊 Caricamento dati dalle tabelle...</div>";

    // === STEP 1: Carica ANA_TASK ===
    $anaTaskData = $graph->readSheet(Config::SHEET_ANA_TASK);
    if (!$anaTaskData || !isset($anaTaskData['values']) || empty($anaTaskData['values'])) {
        throw new Exception("Impossibile leggere ANA_TASK");
    }

    $anaTaskHeaders = $anaTaskData['values'][0];
    $anaTaskIndexes = [];
    foreach (['ID_TASK', 'Task', 'Tipo', 'Stato_Task'] as $col) {
        $index = array_search($col, $anaTaskHeaders);
        if ($index === false) throw new Exception("Colonna '$col' non trovata in ANA_TASK");
        $anaTaskIndexes[$col] = $index;
    }

    // Filtra e crea lookup per task
    $taskLookup = [];
    $validTaskIds = [];
    for ($i = 1; $i < count($anaTaskData['values']); $i++) {
        $row = $anaTaskData['values'][$i];
        if (count($row) <= max($anaTaskIndexes)) continue;
        
        $tipo = trim($row[$anaTaskIndexes['Tipo']] ?? '');
        $stato = trim($row[$anaTaskIndexes['Stato_Task']] ?? '');
        
        if ($tipo === 'CAMPO') {
            $taskId = $row[$anaTaskIndexes['ID_TASK']] ?? '';
            $taskName = $row[$anaTaskIndexes['Task']] ?? '';
            $taskLookup[$taskId] = $taskName;
            $validTaskIds[] = $taskId;
        }
    }

    // === STEP 2: Carica ANA_CONSULENTI ===
    $anaConsulentiData = $graph->readSheet('ANA_CONSULENTI');
    if (!$anaConsulentiData || !isset($anaConsulentiData['values']) || empty($anaConsulentiData['values'])) {
        throw new Exception("Impossibile leggere ANA_CONSULENTI");
    }

    $consulentiHeaders = $anaConsulentiData['values'][0];
    $consulentiIndexes = [];
    foreach (['ID_CONSULENTE', 'Consulente'] as $col) {
        $index = array_search($col, $consulentiHeaders);
        if ($index === false) throw new Exception("Colonna '$col' non trovata in ANA_CONSULENTI");
        $consulentiIndexes[$col] = $index;
    }

    // Crea lookup per consulenti
    $consulenteLookup = [];
    for ($i = 1; $i < count($anaConsulentiData['values']); $i++) {
        $row = $anaConsulentiData['values'][$i];
        if (count($row) <= max($consulentiIndexes)) continue;
        
        $consulenteId = $row[$consulentiIndexes['ID_CONSULENTE']] ?? '';
        $consulenteName = $row[$consulentiIndexes['Consulente']] ?? '';
        $consulenteLookup[$consulenteId] = $consulenteName;
    }

    // === STEP 3: Carica FACT_GIORNATE ===
    $factGiornateData = $graph->readSheet(Config::SHEET_FACT_GIORNATE);
    if (!$factGiornateData || !isset($factGiornateData['values']) || empty($factGiornateData['values'])) {
        throw new Exception("Impossibile leggere FACT_GIORNATE");
    }

    $factHeaders = $factGiornateData['values'][0];
    $factIndexes = [];
    foreach (['Data', 'ID_TASK', 'ID_CONSULENTE', 'Tipo', 'gg', 'Note'] as $col) {
        $index = array_search($col, $factHeaders);
        if ($index === false) throw new Exception("Colonna '$col' non trovata in FACT_GIORNATE");
        $factIndexes[$col] = $index;
    }

    // === STEP 4: Processa e filtra FACT_GIORNATE ===
    $processedData = [];
    $monthFilter = $_GET['month'] ?? '';
    $yearFilter = $_GET['year'] ?? '';
    $totalGg = 0;
    $totalRecords = 0;

    for ($i = 1; $i < count($factGiornateData['values']); $i++) {
        $row = $factGiornateData['values'][$i];
        if (count($row) <= max($factIndexes)) continue;
        
        $taskId = $row[$factIndexes['ID_TASK']] ?? '';
        
        // Filtra solo task validi (CAMPO)
        if (!in_array($taskId, $validTaskIds)) continue;
        
        $data = $row[$factIndexes['Data']] ?? '';
        $consulenteId = $row[$factIndexes['ID_CONSULENTE']] ?? '';
        $tipo = $row[$factIndexes['Tipo']] ?? '';
        $gg = floatval($row[$factIndexes['gg']] ?? 0);
        $note = $row[$factIndexes['Note']] ?? '';
        
        // Converti e formatta la data
        $formattedData = $data;
        $dateObj = null;
        
        if ($data) {
            // Se è un numero (seriale date di Excel), convertilo
            if (is_numeric($data)) {
                // Excel memorizza le date come numero di giorni dal 1 gennaio 1900
                // Ma ha un bug: considera il 1900 come bisestile, quindi sottraiamo 2 giorni
                $excelEpoch = new DateTime('1899-12-30'); // Base corretta per Excel
                $dateObj = clone $excelEpoch;
                $dateObj->add(new DateInterval('P' . intval($data) . 'D'));
                $formattedData = $dateObj->format('d/m/y');
            } else {
                // Prova diversi formati data di input per stringhe
                $inputFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'];
                foreach ($inputFormats as $format) {
                    $dateObj = DateTime::createFromFormat($format, $data);
                    if ($dateObj !== false) {
                        // Converti sempre in formato gg/mm/aa
                        $formattedData = $dateObj->format('d/m/y');
                        break;
                    }
                }
                
                // Se non riusciamo a parsare la data, proviamo con strtotime
                if ($dateObj === false && strtotime($data) !== false) {
                    $dateObj = new DateTime($data);
                    $formattedData = $dateObj->format('d/m/y');
                }
            }
        }
        
        // Applica filtri temporali (usando dateObj se disponibile)
        if ($dateObj && ($monthFilter || $yearFilter)) {
            if ($monthFilter && $dateObj->format('m') !== $monthFilter) continue;
            if ($yearFilter && $dateObj->format('Y') != $yearFilter) continue;
        }
        
        $processedData[] = [
            'Data' => $formattedData,
            'DataObj' => $dateObj, // Manteniamo l'oggetto per i raggruppamenti
            'ID_TASK' => $taskId,
            'Task' => $taskLookup[$taskId] ?? "Task sconosciuto ($taskId)",
            'ID_CONSULENTE' => $consulenteId,
            'Consulente' => $consulenteLookup[$consulenteId] ?? "Consulente sconosciuto ($consulenteId)",
            'Tipo' => $tipo,
            'gg' => $gg,
            'Note' => $note
        ];
        
        $totalGg += $gg;
        $totalRecords++;
    }

        // Ordina per data (usando DataObj se disponibile, altrimenti per stringa)
        usort($processedData, function($a, $b) {
            if ($a['DataObj'] && $b['DataObj']) {
                return $a['DataObj'] <=> $b['DataObj'];
            }
            return strcmp($a['Data'], $b['Data']);
        });

    echo "<script>document.querySelector('.loading').style.display = 'none';</script>";

    echo "<div class='success'>";
    echo "<h3>✅ Dati caricati con successo</h3>";
    echo "<p><strong>Task validi (CAMPO):</strong> " . count($taskLookup) . "</p>";
    echo "<p><strong>Consulenti totali:</strong> " . count($consulenteLookup) . "</p>";
    echo "<p><strong>Record trovati:</strong> " . count($processedData) . "</p>";
    echo "</div>";

    // Cards riassuntive
    echo "<div class='summary-cards'>";
    echo "<div class='summary-card'>";
    echo "<div class='summary-number'>" . count($processedData) . "</div>";
    echo "<div class='summary-label'>Record Totali</div>";
    echo "</div>";
    echo "<div class='summary-card'>";
    echo "<div class='summary-number'>" . number_format($totalGg, 1) . "</div>";
    echo "<div class='summary-label'>Giorni Totali</div>";
    echo "</div>";
    echo "<div class='summary-card'>";
    echo "<div class='summary-number'>" . count(array_unique(array_column($processedData, 'ID_TASK'))) . "</div>";
    echo "<div class='summary-label'>Task Attivi</div>";
    echo "</div>";
    echo "<div class='summary-card'>";
    echo "<div class='summary-number'>" . count(array_unique(array_column($processedData, 'ID_CONSULENTE'))) . "</div>";
    echo "<div class='summary-label'>Consulenti Attivi</div>";
    echo "</div>";
    echo "</div>";

    if (empty($processedData)) {
        echo "<div class='no-data'>";
        echo "<h3>📭 Nessun dato trovato</h3>";
        echo "<p>Non ci sono giornate registrate per i task di tipo CAMPO nel periodo selezionato.</p>";
        echo "</div>";
    } else {
        // Raggruppa per mese per visualizzazione
        $groupedByMonth = [];
        foreach ($processedData as $record) {
            $dateObj = $record['DataObj'];
            $monthKey = '';
            
            if ($dateObj && $dateObj instanceof DateTime) {
                $monthKey = $dateObj->format('Y-m');
            } else {
                $monthKey = 'Data non valida';
            }
            
            if (!isset($groupedByMonth[$monthKey])) {
                $groupedByMonth[$monthKey] = [];
            }
            $groupedByMonth[$monthKey][] = $record;
        }

        ksort($groupedByMonth);

        echo "<div class='table-container'>";
        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>📅 Data</th>";
        echo "<th>🆔 ID Task</th>";
        echo "<th>📝 Task</th>";
        echo "<th>👤 ID Consulente</th>";
        echo "<th>👨‍💼 Consulente</th>";
        echo "<th>🏷️ Tipo</th>";
        echo "<th>📊 Giorni</th>";
        echo "<th>📄 Note</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($groupedByMonth as $monthKey => $monthRecords) {
            // Header mese
            $monthName = $monthKey;
            if ($monthKey !== 'Data non valida' && $monthKey !== 'Senza data') {
                try {
                    $monthDate = DateTime::createFromFormat('Y-m', $monthKey);
                    if ($monthDate) {
                        $monthName = $monthDate->format('F Y');
                        // Traduzione mesi in italiano
                        $mesiIta = [
                            'January' => 'Gennaio', 'February' => 'Febbraio', 'March' => 'Marzo',
                            'April' => 'Aprile', 'May' => 'Maggio', 'June' => 'Giugno',
                            'July' => 'Luglio', 'August' => 'Agosto', 'September' => 'Settembre',
                            'October' => 'Ottobre', 'November' => 'Novembre', 'December' => 'Dicembre'
                        ];
                        $monthName = str_replace(array_keys($mesiIta), array_values($mesiIta), $monthName);
                    }
                } catch (Exception $e) {
                    // Mantieni il nome originale in caso di errore
                }
            }

            echo "<tr class='month-group'>";
            echo "<td colspan='8'><strong>📅 $monthName (" . count($monthRecords) . " record)</strong></td>";
            echo "</tr>";

            foreach ($monthRecords as $record) {
                echo "<tr>";
                echo "<td class='data-cell'>" . htmlspecialchars($record['Data']) . "</td>";
                echo "<td class='task-id'>" . htmlspecialchars($record['ID_TASK']) . "</td>";
                echo "<td class='task-name' title='" . htmlspecialchars($record['Task']) . "'>" . htmlspecialchars($record['Task']) . "</td>";
                echo "<td class='consulente-id'>" . htmlspecialchars($record['ID_CONSULENTE']) . "</td>";
                echo "<td class='consulente-name'>" . htmlspecialchars($record['Consulente']) . "</td>";
                echo "<td>" . htmlspecialchars($record['Tipo']) . "</td>";
                echo "<td class='gg-cell'>" . number_format($record['gg'], 1) . "</td>";
                echo "<td class='note-cell' title='" . htmlspecialchars($record['Note']) . "'>" . htmlspecialchars($record['Note']) . "</td>";
                echo "</tr>";
            }
        }

        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<script>document.querySelector('.loading').style.display = 'none';</script>";
    echo "<div class='error'>";
    echo "<h3>❌ Errore durante il caricamento</h3>";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Codice:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
    
    if ($e->getCode() == 401) {
        echo "<div class='warning'>";
        echo "<h3>🔐 Problema di autenticazione</h3>";
        echo "<a href='authorize_excel.php' style='background:#ffc107;color:#212529;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔄 Rinnova</a>";
        echo "</div>";
    }
}
?>

        <div style='text-align: center; margin-top: 30px;'>
            <button onclick='location.reload()' class='btn-refresh'>🔄 Aggiorna Dati</button>
            <button onclick='window.open("session_debug.php", "_blank")'>🔍 Debug Sessione</button>
            <button onclick='window.open("visualizza_task.php", "_blank")'>📋 Vedi Solo Task</button>
        </div>
    </div>

    <script>
        // Auto-submit form quando cambiano i filtri
        document.getElementById('monthFilter').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
        document.getElementById('yearFilter').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>