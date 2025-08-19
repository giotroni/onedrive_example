<?php
// login_consulente.php - Esempio di login per consulenti
session_start();
require_once 'config.php';
require_once 'microsoft_graph_personal.php';

header('Content-Type: text/html; charset=utf-8');

// Funzione per verificare login consulente
function verificaLoginConsulente($idConsulente, $passwordInserita, $graph) {
    try {
        // Leggi la tabella ANA_CONSULENTI
        $consulentiData = $graph->readSheet('ANA_CONSULENTI');
        
        if (!$consulentiData || !isset($consulentiData['values'])) {
            return ['success' => false, 'message' => 'Errore lettura tabella consulenti'];
        }
        
        $headers = $consulentiData['values'][0];
        $rows = array_slice($consulentiData['values'], 1);
        
        // Trova gli indici delle colonne
        $idIndex = array_search('ID_CONSULENTE', $headers);
        $nomeIndex = array_search('Consulente', $headers);
        $pwdIndex = array_search('PWD', $headers);
        
        if ($idIndex === false || $pwdIndex === false) {
            return ['success' => false, 'message' => 'Colonne mancanti nella tabella'];
        }
        
        // Cerca il consulente
        foreach ($rows as $row) {
            if (isset($row[$idIndex]) && $row[$idIndex] === $idConsulente) {
                $hashSalvato = $row[$pwdIndex] ?? '';
                $nomeConsulente = $row[$nomeIndex] ?? '';
                
                if (empty($hashSalvato)) {
                    return ['success' => false, 'message' => 'Password non impostata per questo consulente'];
                }
                
                // Verifica la password usando password_verify()
                if (password_verify($passwordInserita, $hashSalvato)) {
                    return [
                        'success' => true, 
                        'message' => 'Login effettuato con successo',
                        'consulente' => $nomeConsulente,
                        'id' => $idConsulente
                    ];
                } else {
                    return ['success' => false, 'message' => 'Password errata'];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Consulente non trovato'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Consulente</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #495057;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0,123,255,0.3);
        }
        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .password-demo {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        .password-demo h4 {
            margin-top: 0;
            color: #495057;
        }
        .password-item {
            font-family: monospace;
            margin: 5px 0;
            padding: 5px;
            background: white;
            border-radius: 3px;
        }
        .code-example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .code-example pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Login Consulente</h1>
        
        <?php
        // Gestisci il login se il form è stato inviato
        if ($_POST && isset($_POST['id_consulente']) && isset($_POST['password'])) {
            // Verifica token di accesso
            if (!isset($_SESSION['access_token'])) {
                echo "<div class='alert alert-error'>";
                echo "❌ Token di accesso non trovato. <a href='authorize_excel.php'>Autorizza qui</a>";
                echo "</div>";
            } else {
                try {
                    $graph = new MicrosoftGraphPersonal($_SESSION['access_token']);
                    $risultato = verificaLoginConsulente($_POST['id_consulente'], $_POST['password'], $graph);
                    
                    if ($risultato['success']) {
                        echo "<div class='alert alert-success'>";
                        echo "✅ " . $risultato['message'] . "<br>";
                        echo "<strong>Benvenuto:</strong> " . htmlspecialchars($risultato['consulente']) . " (" . htmlspecialchars($risultato['id']) . ")";
                        echo "</div>";
                        
                        // Qui potresti salvare in sessione i dati del consulente loggato
                        $_SESSION['consulente_loggato'] = $risultato;
                        
                    } else {
                        echo "<div class='alert alert-error'>";
                        echo "❌ " . htmlspecialchars($risultato['message']);
                        echo "</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-error'>";
                    echo "❌ Errore di sistema: " . htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }
        }
        ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="id_consulente">ID Consulente:</label>
                <select name="id_consulente" id="id_consulente" required>
                    <option value="">Seleziona consulente...</option>
                    <option value="CONS001" <?= (($_POST['id_consulente'] ?? '') === 'CONS001') ? 'selected' : '' ?>>CONS001</option>
                    <option value="CONS002" <?= (($_POST['id_consulente'] ?? '') === 'CONS002') ? 'selected' : '' ?>>CONS002</option>
                    <option value="CONS003" <?= (($_POST['id_consulente'] ?? '') === 'CONS003') ? 'selected' : '' ?>>CONS003</option>
                    <option value="CONS004" <?= (($_POST['id_consulente'] ?? '') === 'CONS004') ? 'selected' : '' ?>>CONS004</option>
                    <option value="CONS005" <?= (($_POST['id_consulente'] ?? '') === 'CONS005') ? 'selected' : '' ?>>CONS005</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required 
                       placeholder="Inserisci la password...">
            </div>
            
            <button type="submit">🔑 Accedi</button>
        </form>
        
        <div class="password-demo">
            <h4>🧪 Password di Test:</h4>
            <div class="password-item">CONS001: <strong>Boss01</strong></div>
            <div class="password-item">CONS002: <strong>CeoCeo02</strong></div>
            <div class="password-item">CONS003: <strong>Partner1963</strong></div>
            <div class="password-item">CONS004: <strong>Gattina99</strong></div>
            <div class="password-item">CONS005: <strong>Analista00</strong></div>
        </div>
        
        <div class="code-example">
            <h4>💡 Come funziona password_verify():</h4>
            <pre>
// NON si può decrittare l'hash
$hash = "$2y$10$example...";
$password = "Boss01";

// SI può verificare se la password è corretta
if (password_verify($password, $hash)) {
    echo "Password corretta!";
} else {
    echo "Password sbagliata!";
}
            </pre>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="visualizza_fact_giornate.php" style="color: #007bff; text-decoration: none;">
                🔙 Torna al Report
            </a>
        </div>
    </div>
</body>
</html>