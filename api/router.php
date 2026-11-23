<?php
// api/router.php

// 1. Ambil URL yang diminta
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = ltrim($path, '/');

// 2. Jika URL kosong (buka web utama), arahkan ke index.php
if ($path === '' || $path === 'index.php') {
    require __DIR__ . '/index.php';
    exit;
}

// 3. Jika minta halaman lain (misal: login), cari login.php di folder yang sama
$page_file = __DIR__ . '/' . $path . '.php';

if (file_exists($page_file)) {
    require $page_file;
} else {
    // Jika halaman tidak ada, tampilkan 404
    http_response_code(404);
    echo "<h1>404 - Halaman Tidak Ditemukan</h1>";
}
?>