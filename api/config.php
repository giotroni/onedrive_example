<?php
class Config {
    // Credenziali Microsoft Graph API
    const TENANT_ID = 'TUE_CREDENZIALI';
    const CLIENT_ID = 'TUE_CREDENZIALI';
    const CLIENT_SECRET = 'TUE_CREDENZIALI';
    
    // File Excel
    const EXCEL_FILE_ID = 'F62D90EC-FBDB-498D-A7D3-EB355FE3A4E1'; // ID del file su OneDrive
        // Nome del file (per riferimento)
    const EXCEL_FILENAME = 'DATI_VP.xlsx';

    // Autenticazione locale
    const USERS = [
        'admin' => 'admin123', // Cambia con credenziali sicure
        'user1' => 'user123'
    ];
    
    // Nomi dei fogli
    const SHEET_ANA_TASK = 'ANA_TASK';
    const SHEET_FACT_GIORNATE = 'FACT_GIORNATE';
    
    const JWT_SECRET = 'a8f5f167f44f4964e6c998dee827110c'; // Cambia con una chiave sicura
}
?>
