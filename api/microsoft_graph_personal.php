<?php
require_once 'config.php';

class MicrosoftGraphPersonal {
    private $accessToken;
    
    public function __construct($accessToken = null) {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        } else {
            // Per account personali, devi implementare OAuth flow completo
            throw new Exception("Access token richiesto per account personali");
        }
    }
    
    // Genera URL per autorizzazione utente (da usare nel browser)
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
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        throw new Exception("API Error: " . $response, $httpCode);
    }
    
    // Legge dati da un foglio
    public function readSheet($sheetName) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/usedRange";
        return $this->makeRequest('GET', $url);
    }
    
    // Aggiunge una riga al foglio
    public function addRow($sheetName, $values) {
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/tables/Table1/rows/add";
        $data = ['values' => [$values]];
        return $this->makeRequest('POST', $url, $data);
    }
    
    // Aggiorna una riga specifica
    public function updateRow($sheetName, $rowIndex, $values) {
        $range = $sheetName . '!' . $rowIndex . ':' . $rowIndex;
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/" . Config::EXCEL_FILE_ID . "/workbook/worksheets/{$sheetName}/range(address='{$range}')";
        $data = ['values' => [$values]];
        return $this->makeRequest('PATCH', $url, $data);
    }
}
?>