<?php
// Mengaktifkan pelaporan kesalahan MySQLi untuk debugging yang lebih baik
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Pastikan session sudah dimulai, jika belum, mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

// Periksa apakah koneksi database berhasil
if (!isset($conn) || $conn->connect_error) {
    // Pesan error yang akan ditampilkan di halaman jika koneksi gagal
    $connection_error_message = "
        <div style='background-color: #ffe0e0; border: 1px solid #ff0000; padding: 20px; margin: 30px auto; border-radius: 10px; max-width: 90%; color: #cc0000; font-family: Arial, sans-serif; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>
            <h2 style='color: #ff0000; margin-top: 0;'>&#9888; Kesalahan Fatal: Koneksi Database Gagal!</h2>
            <p style='font-size: 1.1em;'>Sistem tidak dapat terhubung ke database. Ini adalah masalah kritis yang harus segera diperbaiki.</p>
            <h3 style='color: #cc0000;'>Langkah-langkah Pemecahan Masalah:</h3>
            <ol style='list-style-type: decimal; margin-left: 20px;'>
                <li>Pastikan **MySQL (MariaDB)** di **XAMPP Control Panel** Anda sedang dalam status **<span style='color: green; font-weight: bold;'>Running</span>**.</li>
                <li>Periksa kembali file koneksi Anda: <code>C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php</code>.
                    Pastikan <code>\$host</code>, <code>\$username</code>, <code>\$password</code>, dan <code>\$database</code> sudah benar.
                    Untuk XAMPP default, <code>\$username</code> biasanya <code>root</code> dan <code>\$password</code> kosong (<code>&quot;&quot;</code>).
                </li>
                <li>Jalankan file tes koneksi: Buka browser Anda dan akses <code style='background-color: #f0f0f0; padding: 2px 5px; border-radius: 3px;'>http://localhost/PROJEK-UAS/test_db_connection.php</code>.
                    **Salin dan berikan seluruh output dari halaman tersebut kepada saya.** Ini akan sangat membantu dalam diagnosis.
                </li>
            </ol>
            <p style='font-size: 0.9em; margin-top: 20px;'>Pesan kesalahan teknis dari MySQL: <code style='background-color: #f0f0f0; padding: 2px 5px; border-radius: 3px; color: #ff0000;'>&quot;" . ($conn->connect_error ?? 'Variabel $conn tidak ada atau belum diinisialisasi.') . "&quot;</code></p>
        </div>
    ";
    // Hentikan eksekusi script PHP lebih lanjut setelah menampilkan error
    die($connection_error_message);
}

// Inisialisasi variabel peran
$isDoctor = false;
$logged_in_doctor_id = null;

// Periksa peran pengguna yang login
if (isset($_SESSION['user_id'])) {
    $tempUserId = $_SESSION['user_id'];
    $stmt_user_role = $conn->prepare("SELECT user_id, role FROM user WHERE user_id = ?");
    if ($stmt_user_role) {
        $stmt_user_role->bind_param("i", $tempUserId);
        $stmt_user_role->execute();
        $result_user_role = $stmt_user_role->get_result();
        if ($result_user_role->num_rows > 0) {
            $user = $result_user_role->fetch_assoc();
            if ($user['role'] === 'dokter') { // Periksa peran 'dokter'
                $isDoctor = true;
                $logged_in_doctor_id = $user['user_id']; // Dapatkan ID dokter yang login
            }
        }
        $stmt_user_role->close();
    } else {
        error_log("Gagal menyiapkan statement user role: " . $conn->error);
    }
}

// Jika pengguna bukan dokter, arahkan ke halaman login atau tampilkan pesan akses ditolak
if (!$isDoctor || $logged_in_doctor_id === null) {
    header("Location: login.php?message=access_denied_doctor");
    exit();
}

// --- Tangani Permintaan Update Status (untuk dokter) ---
// Dokter dapat mengubah status reservasi
// Bagian ini tetap ada jika Anda ingin fungsionalitas update status tetap berjalan
// namun tombol/select untuk aksi tersebut akan dihapus dari tampilan HTML.
// Jika Anda ingin sepenuhnya menghapus fungsionalitas update, Anda bisa menghapus blok ini.
if (isset($_POST['update_status_id']) && isset($_POST['new_status'])) {
    $update_id = (int)$_POST['update_status_id'];
    $new_status = htmlspecialchars($_POST['new_status']);

    // Pastikan reservasi yang diupdate adalah milik dokter yang login
    $stmt_check_owner = $conn->prepare("SELECT doctor_id FROM reservations WHERE reservation_id = ?");
    if ($stmt_check_owner) {
        $stmt_check_owner->bind_param("i", $update_id);
        $stmt_check_owner->execute();
        $result_check_owner = $stmt_check_owner->get_result();
        $reservation_owner = $result_check_owner->fetch_assoc();
        $stmt_check_owner->close();

        if ($reservation_owner && $reservation_owner['doctor_id'] == $logged_in_doctor_id) {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
            if ($stmt === false) {
                error_log("Error preparing update status statement: " . $conn->error);
            } else {
                $stmt->bind_param("si", $new_status, $update_id);
                if ($stmt->execute()) {
                    header("Location: Dasboard-D.php?status=updated");
                    exit();
                } else {
                    error_log("Error updating status: " . $stmt->error);
                }
                $stmt->close();
            }
        } else {
            // Jika reservasi bukan milik dokter ini atau tidak ditemukan
            header("Location: Dasboard-D.php?status=unauthorized_update");
            exit();
        }
    } else {
        error_log("Error preparing check owner statement: " . $conn->error);
    }
}


// --- Ambil Data untuk Kartu Dashboard (STATISTIK DOKTER) ---

// Reservasi Mendatang (status 'Pending' atau 'Dikonfirmasi' untuk hari ini atau masa depan, untuk dokter ini)
$sql_reservasi_mendatang = "SELECT COUNT(*) AS total FROM reservations WHERE doctor_id = ? AND reservation_date >= CURDATE() AND status IN ('Pending', 'Dikonfirmasi')";
$stmt_reservasi_mendatang = $conn->prepare($sql_reservasi_mendatang);
if ($stmt_reservasi_mendatang === FALSE) {
    error_log("Error mempersiapkan query Reservasi Mendatang: " . $conn->error);
    $reservasi_mendatang = 0;
} else {
    $stmt_reservasi_mendatang->bind_param("i", $logged_in_doctor_id);
    $stmt_reservasi_mendatang->execute();
    $result_reservasi_mendatang = $stmt_reservasi_mendatang->get_result();
    $reservasi_mendatang = $result_reservasi_mendatang->fetch_assoc()['total'] ?? 0;
    $stmt_reservasi_mendatang->close();
}

// Pasien Hari Ini (Menghitung pasien unik berdasarkan customer_id yang sudah dikonfirmasi untuk dokter ini *untuk hari ini*)
$sql_pasien_hari_ini = "SELECT COUNT(DISTINCT customer_id) AS total FROM reservations WHERE doctor_id = ? AND status = 'Dikonfirmasi' AND reservation_date = CURDATE()";
$stmt_pasien_hari_ini = $conn->prepare($sql_pasien_hari_ini);
if ($stmt_pasien_hari_ini === FALSE) {
    error_log("Error mempersiapkan query Pasien Hari Ini: " . $conn->error);
    $pasien_hari_ini = 0;
} else {
    $stmt_pasien_hari_ini->bind_param("i", $logged_in_doctor_id);
    $stmt_pasien_hari_ini->execute();
    $result_pasien_hari_ini = $stmt_pasien_hari_ini->get_result();
    $pasien_hari_ini = $result_pasien_hari_ini->fetch_assoc()['total'] ?? 0;
    $stmt_pasien_hari_ini->close();
}

// Total Pasien (Menghitung total pasien unik berdasarkan customer_id dari semua reservasi untuk dokter ini)
$sql_total_pasien = "SELECT COUNT(DISTINCT customer_id) AS total FROM reservations WHERE doctor_id = ?";
$stmt_total_pasien = $conn->prepare($sql_total_pasien);
if ($stmt_total_pasien === FALSE) {
    error_log("Error mempersiapkan query Total Pasien: " . $conn->error);
    $total_pasien = 0;
} else {
    $stmt_total_pasien->bind_param("i", $logged_in_doctor_id);
    $stmt_total_pasien->execute();
    $result_total_pasien = $stmt_total_pasien->get_result();
    $total_pasien = $result_total_pasien->fetch_assoc()['total'] ?? 0;
    $stmt_total_pasien->close();
}

// --- Ambil Data untuk Tabel Reservasi Terbaru (Dokter) ---
// Menggunakan JOIN untuk mendapatkan nama pasien dari tabel 'user' dan nama layanan dari tabel 'services'
// Filter berdasarkan doctor_id yang login, dan ambil layanan saja (tidak termasuk item produk)
$sql_reservasi_terbaru = "
    SELECT
        R.reservation_id AS id,
        U.name AS patient_name,
        R.reservation_date,
        R.reservation_time,
        S.service_name AS services,
        R.status
    FROM
        reservations AS R
    JOIN
        user AS U ON R.customer_id = U.user_id
    JOIN
        services AS S ON R.service_id = S.service_id
    WHERE
        R.doctor_id = ? -- Filter berdasarkan ID dokter yang login
    ORDER BY
        R.reservation_date DESC, R.reservation_time DESC
    LIMIT 5";

$stmt_reservasi_terbaru = $conn->prepare($sql_reservasi_terbaru);
$reservations = []; // Menggunakan nama variabel yang lebih sesuai untuk daftar reservasi
if ($stmt_reservasi_terbaru === FALSE) {
    error_log("Error mempersiapkan query Reservasi Terbaru: " . $conn->error);
} else {
    $stmt_reservasi_terbaru->bind_param("i", $logged_in_doctor_id);
    $stmt_reservasi_terbaru->execute();
    $result_reservasi_terbaru = $stmt_reservasi_terbaru->get_result();
    if ($result_reservasi_terbaru->num_rows > 0) {
        while ($row = $result_reservasi_terbaru->fetch_assoc()) {
            $reservations[] = $row;
        }
    }
    $stmt_reservasi_terbaru->close();
}

// Tutup koneksi database setelah semua data diambil
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dermalux Clinik - Dashboard Dokter</title> <!-- Judul disesuaikan untuk Dokter -->
    <link rel="stylesheet" href="style-D.css"> <!-- Menggunakan style-D.css -->
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <img src="../imgs/logo.png" alt="logo">
            </div>
            <nav>
                <ul>
                    <li><a href="Dasboard-D.php" class="active">Dashboard</a></li>
                    <li><a href="Rekamedis.php">Rekam Medis</a></li>
                    <li><a href="Daftarpasien.php">Daftar Pasien</a></li>
                    <li><a href="Jadwal.php">Jadwal Praktik</a></li>
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 class="section-title pink">Selamat Datang, Dokter!</h2>

            <?php
            // PHP-only message display based on URL parameters
            if (isset($_GET['status'])) {
                $status = htmlspecialchars($_GET['status']);
                echo '<div class="message ';
                if ($status === 'updated' || $status === 'medical_record_saved') {
                    echo 'success';
                } else if ($status === 'unauthorized_update' || $status === 'unauthorized_medical_record' || $status === 'access_denied_doctor') {
                    echo 'error';
                }
                echo '">';
                if ($status === 'updated') {
                    echo 'Status reservasi berhasil diperbarui!';
                } else if ($status === 'unauthorized_update') {
                    echo 'Anda tidak memiliki izin untuk memperbarui reservasi ini.';
                } else if ($status === 'medical_record_saved') {
                    echo 'Rekam medis berhasil disimpan!';
                } else if ($status === 'unauthorized_medical_record') {
                    echo 'Anda tidak memiliki izin untuk menyimpan rekam medis.';
                } else if ($status === 'access_denied_doctor') {
                    echo 'Akses ditolak. Anda harus login sebagai dokter.';
                }
                echo '</div>';
            }
            ?>

            <div class="dashboard-cards">
                <div class="stat-card">
                    <h3>Reservasi Mendatang</h3>
                    <p><?php echo $reservasi_mendatang; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pasien Hari Ini</h3>
                    <p><?php echo $pasien_hari_ini; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Pasien</h3>
                    <p><?php echo $total_pasien; ?></p>
                </div>
            </div>

            <!-- Bagian "Reservasi Terbaru" untuk Dokter -->
            <section class="recent-reservations">
                <h2 class="section-title pink">Reservasi Terbaru</h2>
                <?php if (empty($reservations)): ?>
                    <p class="empty-status-message">Tidak ada reservasi terbaru untuk Anda.</p>
                <?php else: ?>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>ID Reservasi</th>
                                <th>Nama Pasien</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Layanan</th>
                                <th>Status</th>
                                <!-- Kolom Aksi telah dihapus sesuai permintaan -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td data-label="ID Reservasi"><?php echo htmlspecialchars($reservation['id']); ?></td>
                                    <td data-label="Nama Pasien"><?php echo htmlspecialchars($reservation['patient_name']); ?></td>
                                    <td data-label="Tanggal"><?php echo htmlspecialchars($reservation['reservation_date']); ?></td>
                                    <td data-label="Waktu"><?php echo htmlspecialchars($reservation['reservation_time']); ?></td>
                                    <td data-label="Layanan">
                                        <ul class="item-list">
                                            <li><?php echo htmlspecialchars($reservation['services']); ?></li>
                                        </ul>
                                    </td>
                                    <td data-label="Status">
                                        <?php
                                            $status_text = htmlspecialchars($reservation['status']);
                                            $status_class = strtolower($status_text);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status_text); ?></span>
                                    </td>
                                    <!-- Sel Aksi telah dihapus sesuai permintaan -->
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
                &copy; 2025 Dokter Panel. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
