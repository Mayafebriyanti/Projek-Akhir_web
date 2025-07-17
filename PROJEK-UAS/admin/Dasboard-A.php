<?php

session_start(); 

include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

$isAdmin = false;
$isDoctor = false; // Tambahkan variabel untuk peran dokter
if (isset($_SESSION['user_id'])) {
    $tempUserId = $_SESSION['user_id'];
    $stmt_user_role = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
    if ($stmt_user_role) {
        $stmt_user_role->bind_param("i", $tempUserId);
        $stmt_user_role->execute();
        $result_user_role = $stmt_user_role->get_result();
        if ($result_user_role->num_rows > 0) {
            $user = $result_user_role->fetch_assoc();
            if ($user['role'] === 'admin') {
                $isAdmin = true;
            } elseif ($user['role'] === 'doctor') { // Periksa juga peran dokter
                $isDoctor = true;
            }
        }
        $stmt_user_role->close();
    } else {
        error_log("Gagal menyiapkan statement user role: " . $conn->error);
    }
}

// Jika bukan admin DAN bukan dokter, arahkan ke halaman login atau tampilkan pesan akses ditolak
if (!$isAdmin && !$isDoctor) {
    header("Location: login.php?message=access_denied_admin");
    exit();
}

$current_user_role = $isAdmin ? 'admin' : ($isDoctor ? 'doctor' : '');


// Pastikan koneksi database berhasil setelah include config.php
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// --- Tangani Permintaan Hapus ---
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id']; // Pastikan ini integer

    // Hapus terlebih dahulu dari reservation_items jika ada
    $stmt_delete_items = $conn->prepare("DELETE FROM reservation_items WHERE reservation_id = ?");
    if ($stmt_delete_items) {
        $stmt_delete_items->bind_param("i", $delete_id);
        $stmt_delete_items->execute();
        $stmt_delete_items->close();
    } else {
        error_log("Error preparing delete reservation_items statement: " . $conn->error);
    }

    // Kemudian hapus dari reservations
    $stmt = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
    if ($stmt === false) {
        error_log("Error preparing delete reservations statement: " . $conn->error);
    } else {
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            // Redirect with a status message
            header("Location: Dasboard-A.php?status=deleted");
            exit();
        } else {
            error_log("Error deleting record from reservations: " . $stmt->error);
        }
        $stmt->close();
    }
}

// --- Tangani Permintaan Update Status ---
if (isset($_POST['update_status_id']) && isset($_POST['new_status'])) {
    $update_id = (int)$_POST['update_status_id'];
    $new_status = htmlspecialchars($_POST['new_status']);

    // Logic to restrict 'Selesai' status update to 'doctor' role only
    if ($new_status === 'Selesai' && $current_user_role !== 'doctor') {
        header("Location: Dasboard-A.php?status=unauthorized_status_change");
        exit();
    }

    // Proceed with the update if the status is not 'Selesai' or if the user is a 'doctor'.
    $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
    if ($stmt === false) {
        error_log("Error preparing update status statement: " . $conn->error);
    } else {
        $stmt->bind_param("si", $new_status, $update_id);
        if ($stmt->execute()) {
            // Redirect with a status message
            header("Location: Dasboard-A.php?status=updated");
            exit();
        } else {
            error_log("Error updating status: " . $stmt->error);
        }
        $stmt->close();
    }
}

// --- Ambil Data untuk Kartu Dashboard (STATISTIK ADMIN) ---

// Total Reservasi (semua status)
$sql_total_reservasi_count = "SELECT COUNT(*) AS total FROM reservations";
$result_total_reservasi_count = $conn->query($sql_total_reservasi_count);
$totalReservasiCount = ($result_total_reservasi_count && $result_total_reservasi_count->num_rows > 0) ? $result_total_reservasi_count->fetch_assoc()['total'] : 0;

// Reservasi Dikonfirmasi
$sql_dikonfirmasi_count = "SELECT COUNT(*) AS total FROM reservations WHERE status = 'Dikonfirmasi'";
$result_dikonfirmasi_count = $conn->query($sql_dikonfirmasi_count);
$dikonfirmasiCount = ($result_dikonfirmasi_count && $result_dikonfirmasi_count->num_rows > 0) ? $result_dikonfirmasi_count->fetch_assoc()['total'] : 0;

// Reservasi Menunggu Konfirmasi (Pending)
$sql_menunggu_konfirmasi_count = "SELECT COUNT(*) AS total FROM reservations WHERE status = 'Pending'";
$result_menunggu_konfirmasi_count = $conn->query($sql_menunggu_konfirmasi_count);
$menungguKonfirmasiCount = ($result_menunggu_konfirmasi_count && $result_menunggu_konfirmasi_count->num_rows > 0) ? $result_menunggu_konfirmasi_count->fetch_assoc()['total'] : 0;

// Reservasi Selesai
$sql_selesai_count = "SELECT COUNT(*) AS total FROM reservations WHERE status = 'Selesai'";
$result_selesai_count = $conn->query($sql_selesai_count);
$selesaiCount = ($result_selesai_count && $result_selesai_count->num_rows > 0) ? $result_selesai_count->fetch_assoc()['total'] : 0;

// --- Ambil Data untuk Tabel Reservasi Terbaru ---
// Menggunakan LEFT JOIN untuk mendapatkan nama pasien dari tabel 'user' dan nama layanan dari tabel 'services'
// Serta LEFT JOIN untuk reservation_items untuk mengambil produk yang dibeli
$sql_reservasi_terbaru = "
    SELECT
        R.reservation_id,
        U.name AS customer_name,
        R.reservation_date AS date,
        R.reservation_time AS time,
        S.service_name,
        R.status,
        RI.product_name, -- Tambahkan kolom produk
        RI.quantity      -- Tambahkan kolom kuantitas produk
    FROM
        reservations AS R
    LEFT JOIN
        user AS U ON R.customer_id = U.user_id
    LEFT JOIN
        services AS S ON R.service_id = S.service_id
    LEFT JOIN
        reservation_items AS RI ON R.reservation_id = RI.reservation_id -- Gabungkan dengan item reservasi
    ORDER BY
        R.created_at DESC
    LIMIT 5"; // Batasi 5 reservasi terbaru

$result_reservasi_terbaru = $conn->query($sql_reservasi_terbaru);
$allReservations = [];
if ($result_reservasi_terbaru === FALSE) {
    error_log("Error pada query Reservasi Terbaru: " . $conn->error);
} else {
    // Proses hasil query untuk mengelompokkan item produk per reservasi
    $temp_reservations = [];
    while ($row = $result_reservasi_terbaru->fetch_assoc()) {
        $reservationId = $row['reservation_id'];
        if (!isset($temp_reservations[$reservationId])) {
            // Inisialisasi entri reservasi jika belum ada
            $temp_reservations[$reservationId] = [
                'reservation_id' => $row['reservation_id'],
                'customer_name' => $row['customer_name'],
                'date' => $row['date'],
                'time' => $row['time'],
                'service_name' => $row['service_name'],
                'status' => $row['status'],
                'items' => [] // Array untuk menyimpan produk
            ];
        }
        // Tambahkan produk ke array items jika ada
        if (!empty($row['product_name'])) {
            $temp_reservations[$reservationId]['items'][] = [
                'product_name' => $row['product_name'],
                'quantity' => $row['quantity']
            ];
        }
    }
    $allReservations = array_values($temp_reservations); // Konversi kembali ke array berindeks numerik
}

// Tutup koneksi database setelah semua data diambil dan dihitung
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dermalux Clinik - Dashboard Admin</title> <!-- Judul disesuaikan -->
    <link rel="stylesheet" href="style-A.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <img src="../imgs/logo.png" alt="logo">
            </div>
            <nav>
                <ul>
                    <li><a href="Dasboard-A.php">Dashboard</a></li>
                    <li><a href="reservasi.php">Reservasi</a></li>
                    <li><a href="Layanan-produk.php">Layanan & Produk</a></li>
                    <li><a href="Rekamedis-A.php">Rekam Medis</a></li>
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 class="section-title pink">Dashboard Admin</h2>

            <?php
            // PHP-only message display based on URL parameters
            if (isset($_GET['status'])) {
                $status = htmlspecialchars($_GET['status']);
                echo '<div class="message ';
                if ($status === 'deleted' || $status === 'updated' || $status === 'medical_record_saved') {
                    echo 'success';
                } else if ($status === 'unauthorized_status_change' || $status === 'unauthorized_medical_record' || $status === 'unauthorized_access') {
                    echo 'error';
                }
                echo '">';
                if ($status === 'deleted') {
                    echo 'Reservasi berhasil dihapus!';
                } else if ($status === 'updated') {
                    echo 'Status reservasi berhasil diperbarui!';
                } else if ($status === 'unauthorized_status_change') {
                    echo 'Anda tidak memiliki izin untuk mengubah status menjadi "Selesai".';
                } else if ($status === 'medical_record_saved') {
                    echo 'Rekam medis berhasil disimpan!';
                } else if ($status === 'unauthorized_medical_record') {
                    echo 'Anda tidak memiliki izin untuk menyimpan rekam medis.';
                } else if ($status === 'unauthorized_access') {
                    echo 'Akses tidak sah ke halaman rekam medis.';
                }
                echo '</div>';
            }
            ?>

            <div class="dashboard-cards">
                <div class="stat-card">
                    <h3>Total Reservasi</h3>
                    <p><?php echo $totalReservasiCount; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Dikonfirmasi</h3>
                    <p><?php echo $dikonfirmasiCount; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Menunggu Konfirmasi</h3>
                    <p><?php echo $menungguKonfirmasiCount; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Selesai</h3>
                    <p><?php echo $selesaiCount; ?></p>
                </div>
            </div>

            <!-- Bagian "Reservasi Terbaru" -->
            <section class="recent-reservations">
                <h2 class="section-title pink">Reservasi Terbaru</h2>
                <?php if (empty($allReservations)): ?>
                    <p class="empty-status-message">Belum ada reservasi yang tercatat.</p>
                <?php else: ?>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>ID Reservasi</th>
                                <th>Nama Pasien</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Layanan/Item</th> <!-- Diubah kembali ke Layanan/Item -->
                                <th>Status</th>
                                <!-- Aksi dihapus dari header -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReservations as $res): ?>
                                <tr>
                                    <td data-label="ID Reservasi"><?php echo htmlspecialchars($res['reservation_id']); ?></td>
                                    <td data-label="Nama Pasien"><?php echo htmlspecialchars($res['customer_name']); ?></td>
                                    <td data-label="Tanggal"><?php echo htmlspecialchars($res['date']); ?></td>
                                    <td data-label="Waktu"><?php echo htmlspecialchars($res['time']); ?></td>
                                    <td data-label="Layanan/Item">
                                        <?php if (!empty($res['service_name'])): ?>
                                            <strong>Layanan:</strong> <?php echo htmlspecialchars($res['service_name']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($res['items'])): ?>
                                            <strong>Produk:</strong>
                                            <ul class="item-list">
                                                <?php foreach ($res['items'] as $item): ?>
                                                    <li><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if (empty($res['service_name']) && empty($res['items'])): ?>
                                            Tidak ada detail.
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php
                                            $status_text = htmlspecialchars($res['status']);
                                            $status_class = strtolower($status_text);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status_text); ?></span>
                                    </td>
                                    <!-- Bagian Aksi dihapus dari body tabel -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3 class="section-title">Klinik Kecantikan</h3>
                    <p class="section-text">Kulit sehat dan bercahaya menanti. Jadwalkan konsultasi hari ini.</p>
                </div>
                <div class="footer-section">
                    <h3 class="section-title">Kontak Kami</h3>
                    <p class="section-text">Jl. Cromwell Ave No. 123, Batam</p>
                    <p class="section-text">Telp: (021) 1234567</p>
                    <p class="section-text">Email: dermalux@klinikcantik.com</p>
                </div>
                <div class="footer-section">
                    <h3 class="section-title">Jam Operasional</h3>
                    <p class="section-text">Senin-Jumat: 08:00 - 16:00</p>
                    <p class="section-text">Sabtu: 08:00 - 14:00</p>
                    <p class="section-text">Minggu: Tutup</p>
                </div>
            </div>
            <div class="footer-copyright">
                &copy; 2025 Admin Panel. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
