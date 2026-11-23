<?php
// config.php
session_start();

// ==========================================
// KONFIGURASI DATABASE TIDB CLOUD
// ==========================================
// Menggunakan getenv() agar aman saat di Vercel.
// Jika getenv() kosong (misal saat tes di laptop), ia akan memakai nilai setelah tanda ?: (fallback).

$host     = getenv('DB_HOST') ?: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$port     = getenv('DB_PORT') ?: '4000';
$dbname   = getenv('DB_NAME') ?: 'rentgo_db'; // Sesuai request
$username = getenv('DB_USER') ?: '4Q8SoSzarardXav.root';
$password = getenv('DB_PASS') ?: 'Oi3Lbp0qJ5G5JiJe';

try {
    // DSN dengan tambahan port 4000
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    
    // Opsi SSL (Wajib untuk TiDB Cloud di Vercel)
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/cert.pem', 
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// ==========================================
// FUNGSI OTOMATISASI STATUS PESANAN & KENDARAAN
// ==========================================
function process_expired_bookings($pdo) {
    try {
        // Cari pesanan berstatus 'paid' yang waktu selesainya (+ 1 jam grace period) sudah terlewati
        // Logika: (Tanggal Ambil + Jam Ambil) + (Durasi Hari * 24 Jam) + 1 Jam < Waktu Sekarang
        $sql = "SELECT id, vehicle_id FROM bookings 
                WHERE status = 'paid' 
                AND DATE_ADD(CONCAT(tgl_ambil, ' ', jam_ambil), INTERVAL (duration * 24 + 1) HOUR) < NOW()";
        
        $stmt = $pdo->query($sql);
        $expired_bookings = $stmt->fetchAll();

        if (count($expired_bookings) > 0) {
            $pdo->beginTransaction();

            foreach ($expired_bookings as $row) {
                // 1. Ubah status pesanan menjadi 'completed' (Selesai)
                $upd_booking = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $upd_booking->execute([$row['id']]);

                // 2. Kembalikan status kendaraan menjadi 'tersedia'
                $upd_vehicle = $pdo->prepare("UPDATE vehicles SET status = 'tersedia' WHERE id = ?");
                $upd_vehicle->execute([$row['vehicle_id']]);
            }

            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Silent error: jangan hentikan website jika proses auto-expire gagal
    }
}

// Jalankan fungsi otomatisasi setiap kali halaman dimuat
process_expired_bookings($pdo);


// ==========================================
// FUNGSI BANTUAN (HELPER)
// ==========================================

// Cek apakah user sudah login (bukan guest)
function is_login() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] !== 'guest';
}

// Cek apakah ada session (login atau guest)
function is_logged_in_or_guest() {
    return isset($_SESSION['role']);
}
?>