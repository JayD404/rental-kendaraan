<?php
// config/auth.php
// ─────────────────────────────────────────────────────────────────────────────
// Middleware API Key Security.
// Include file ini lalu panggil validateApiKey($pdo) di setiap endpoint.
//
// Cara pakai di Postman:
//   Tab Headers → tambah:  X-API-Key : <key_token dari tabel admin>
// ─────────────────────────────────────────────────────────────────────────────

function getApiKeyFromRequest(): string
{
    // Header X-API-Key (standar, dianjurkan)
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }
    // Header key (sesuai modul praktikum)
    if (!empty($_SERVER['HTTP_KEY'])) {
        return trim($_SERVER['HTTP_KEY']);
    }
    return '';
}

function validateApiKey(PDO $pdo): array
{
    $key = getApiKeyFromRequest();

    if (empty($key)) {
        http_response_code(401);
        echo json_encode([
            "meta" => [
                "status"    => 401,
                "message"   => "Akses ditolak. Sertakan header 'X-API-Key' pada request.",
                "timestamp" => date('c')
            ],
            "data" => null
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_user, nama FROM admin WHERE key_token = ? LIMIT 1");
    $stmt->execute([$key]);
    $admin = $stmt->fetch();

    if (!$admin) {
        http_response_code(401);
        echo json_encode([
            "meta" => [
                "status"    => 401,
                "message"   => "API Key tidak valid atau sudah tidak aktif.",
                "timestamp" => date('c')
            ],
            "data" => null
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $admin;
}