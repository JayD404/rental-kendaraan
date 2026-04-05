<?php
// config/supabase.php

define('SUPABASE_URL', 'https://qknthjqazdjyyyhrlpwv.supabase.co'); // ← URL API project, bukan URL dashboard
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFrbnRoanFhemRqeXl5aHJscHd2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MzcyNDMxNywiZXhwIjoyMDg5MzAwMzE3fQ.WqAoJzLFSH4HMas_bgkqLqyihyhxW46YiWgIChkf5Ho'); // service_role key
define('SUPABASE_BUCKET', 'kendaraan'); // nama bucket di Supabase Storage

/**
 * Upload file ke Supabase Storage
 *
 * @param string $fileData  Raw binary content dari file
 * @param string $fileName  Nama file tujuan di bucket (misal: "gambar_123.jpg")
 * @param string $mimeType  MIME type file (misal: "image/jpeg", "application/pdf")
 * @return array ['url' => '...'] jika sukses, ['error' => '...'] jika gagal
 */
function uploadToSupabase(string $fileData, string $fileName, string $mimeType): array {
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $fileName;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $fileData,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: ' . $mimeType,
            'x-upsert: true', // overwrite jika nama file sudah ada
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'cURL error: ' . $curlErr];
    }

    if ($httpCode === 200 || $httpCode === 201) {
        $publicUrl = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $fileName;
        return ['url' => $publicUrl];
    }

    return ['error' => "Upload gagal (HTTP $httpCode): $response"];
}

/**
 * Hapus file dari Supabase Storage
 *
 * @param string $fileName  Nama file di bucket
 * @return bool
 */
function deleteFromSupabase(string $fileName): bool {
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $fileName;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_KEY,
        ],
    ]);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}
