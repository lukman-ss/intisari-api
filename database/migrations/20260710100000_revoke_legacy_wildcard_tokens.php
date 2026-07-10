<?php

declare(strict_types=1);

/**
 * Migration Script: Revoke Legacy Wildcard Tokens
 * 
 * Strategi Kompatibilitas: REVOKE
 * 
 * Alasan: 
 * Dalam project starter, keamanan (Least Privilege) diprioritaskan di atas kenyamanan sesi. 
 * Token lama dengan abilities ["*"] memiliki privilese sistem penuh (super-admin access).
 * Mengubah (translasi) mereka menjadi subset hak pengguna biasa tanpa persetujuan eksplisit 
 * pengguna berisiko melanggar prinsip kepastian keamanan (bisa saja token tersebut
 * pernah digunakan sebagai API Key admin yang kini diturunkan kastanya secara tidak sengaja).
 * 
 * Konsekuensi:
 * - Keamanan: Menghapus risiko eksploitasi oleh kredensial lama yang bocor dengan hak penuh.
 * - Operasional: Seluruh pengguna lama yang bergantung pada token wildcard harus login ulang (sesi terputus).
 * 
 * Backup Strategy (Bila Diperlukan):
 * Pastikan Anda melakukan backup database `api_tokens` ke dalam storage aman sebelum menjalankan
 * perintah `composer migrate` ini pada server production.
 */
return function (\PDO $pdo) {
    // Kami menggunakan LIKE '%"*"%' dan '=' untuk memastikan json wildcard tepat terhapus.
    // Metode ini idempotent dan tidak merusak skema.
    
    // Tangkap jumlah token legacy wildcard sebelum dihapus untuk kepentingan logging
    $stmt = $pdo->query("SELECT count(*) as total FROM api_tokens WHERE abilities = '[\"*\"]'");
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    $totalLegacy = $result['total'] ?? 0;
    
    if ($totalLegacy > 0) {
        $pdo->exec("DELETE FROM api_tokens WHERE abilities = '[\"*\"]'");
        
        // Log pencabutan agar admin sadar ada sesi yang dimatikan
        $logger = new \App\Support\Logger();
        $logger->info("Security Migration: {$totalLegacy} legacy wildcard tokens have been permanently revoked.");
    }
};
