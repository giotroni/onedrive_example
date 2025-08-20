<?php
// microsoft_graph_personal_updated.php - Versione aggiornata con supporto per aggiornamenti specifici
require_once 'config.php';

class MicrosoftGraphPersonal {
    private $accessToken;
    
    public function __construct($accessToken = null) {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        } else {
            throw new Exception("Access token richiesto per account personali");
        }
    }
    
    // Genera URL per autorizzazione utente
    public static function getAuthorizationUrl() {
        $params = [
            'client_id' => Config::CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri' => 'https://vaglioandpartners.com/test/api/oauth_callback_fixed.php',
            'scope' => 'https://graph.microsoft.com/Files.ReadWrite offline_access',
            'response_mode' => 'query'
        ];
        
        return 'https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize?' . http_build_query($params);
    }
    
    // Scambia authorization code con access token
    public static function getAccessTokenFromCode($code) {
        $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        
        $data = [
            'client_id' => Config::CLIENT_ID,
            'client_secret' => Config::CLIENT_SECRET,
            'code' => $code,
            'redirect_uri' => 'https://vaglioandpartners.com/test/api/oauth_callback_fixed.php',
            'grant_type' => 'authorization_code',
            'scope' => 'https://graph.microsoft.com/Files.ReadWrite offline_access'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("OAuth Error: " . $result['error_description']);
        }
        
        return $result;
    }
    
    // Refresh access token usando refresh token
    public static function refreshAccessToken($refreshToken) {
        $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        
        $data = [
            'client_id' => Config::CLIENT_ID,
            'client_secret' => Config::CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => 'https://graph.microsoft.com/Files.ReadWrite offline_access'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("Token Refresh Error: " . $result['error_description']);
        }
        
        return $result;
    }
    
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        $errorResponse = json_decode($response, true);
        $errorMessage = isset($errorResponse['error']['message']) ? 
                       $errorResponse['error']['message'] : 
                       "HTTP Error $httpCode: $response";
        
        throw new Exception("API Error: " . $errorMessage, $httpCode);
    }
    
    // Legge dati da un foglio (range utilizzato)
    public function readSheet($sheetName) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/usedRange";
        return $this->makeRequest('GET', $url);
    }
    
    // Legge una specifica cella o range
    public function readRange($sheetName, $range) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/range(address='{$range}')";
        return $this->makeRequest('GET', $url);
    }
    
    // Aggiorna una riga specifica (metodo migliorato)
    public function updateRow($sheetName, $rowNumber, $values) {
        try {
            // Primo metodo: prova ad aggiornare usando il range della riga
            $columnCount = count($values);
            $lastColumn = $this->numberToColumn($columnCount);
            $range = "A{$rowNumber}:{$lastColumn}{$rowNumber}";
            
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/range(address='{$range}')";
            $data = ['values' => [$values]];
            
            return $this->makeRequest('PATCH', $url, $data);
            
        } catch (Exception $e) {
            // Metodo alternativo: aggiorna cella per cella
            $results = [];
            for ($i = 0; $i < count($values); $i++) {
                $column = $this->numberToColumn($i + 1);
                $cellAddress = "{$column}{$rowNumber}";
                
                try {
                    $results[] = $this->updateCell($sheetName, $cellAddress, $values[$i]);
                } catch (Exception $cellError) {
                    throw new Exception("Errore aggiornamento cella {$cellAddress}: " . $cellError->getMessage());
                }
            }
            return $results;
        }
    }
    
    // Aggiorna una singola cella
    public function updateCell($sheetName, $cellAddress, $value) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/range(address='{$cellAddress}')";
        $data = ['values' => [[$value]]];
        return $this->makeRequest('PATCH', $url, $data);
    }
    
    // Aggiunge una riga alla fine del foglio
    public function addRow($sheetName, $values) {
        try {
            // Prova prima con le tabelle
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/tables/Table1/rows/add";
            $data = ['values' => [$values]];
            return $this->makeRequest('POST', $url, $data);
            
        } catch (Exception $e) {
            // Metodo alternativo: trova l'ultima riga e aggiungi
            $usedRange = $this->readSheet($sheetName);
            $nextRow = count($usedRange['values']) + 1;
            return $this->updateRow($sheetName, $nextRow, $values);
        }
    }
    
    // Trova una riga basata su criteri
    public function findRow($sheetName, $searchColumn, $searchValue) {
        $data = $this->readSheet($sheetName);
        
        if (!isset($data['values']) || empty($data['values'])) {
            return null;
        }
        
        $headers = $data['values'][0];
        $searchIndex = array_search($searchColumn, $headers);
        
        if ($searchIndex === false) {
            throw new Exception("Colonna '{$searchColumn}' non trovata");
        }
        
        for ($i = 1; $i < count($data['values']); $i++) {
            $row = $data['values'][$i];
            if (isset($row[$searchIndex]) && 
                strtolower(trim($row[$searchIndex])) === strtolower(trim($searchValue))) {
                return [
                    'rowIndex' => $i + 1, // Excel row number (1-based)
                    'data' => $row,
                    'headers' => $headers
                ];
            }
        }
        
        return null;
    }
    
    // Ottieni informazioni sui fogli di lavoro
    public function getWorksheets() {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets";
        return $this->makeRequest('GET', $url);
    }
    
    // Ottieni informazioni sulle tabelle in un foglio
    public function getTables($sheetName) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/tables";
        return $this->makeRequest('GET', $url);
    }
    
    // Converte numero colonna in lettera (A, B, C, ..., AA, AB, etc.)
    private function numberToColumn($number) {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr($number % 26 + 65) . $column;
            $number = intval($number / 26);
        }
        return $column;
    }
    
    // Converte lettera colonna in numero
    private function columnToNumber($column) {
        $number = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($column[$i]) - 64);
        }
        return $number;
    }
    
    // Verifica connessione e permessi
    public function testConnection() {
        try {
            $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID;
            $response = $this->makeRequest('GET', $url);
            
            return [
                'success' => true,
                'fileInfo' => [
                    'name' => $response['name'] ?? 'N/A',
                    'size' => $response['size'] ?? 0,
                    'lastModified' => $response['lastModifiedDateTime'] ?? 'N/A'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}