<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/../koneksi/config.php'; // Sesuaikan path ini jika struktur folder berbeda

// Pastikan koneksi berhasil sebelum melanjutkan
if ($conn->connect_error) {
    die("Koneksi database gagal di Rekamedis.php: " . $conn->connect_error);
}

$sql_rekam_medis_list = "
    SELECT
        R.reservation_id,
        U.name AS customer_name,
        R.reservation_date,
        R.reservation_time,
        S.service_name,
        R.status,
        RM.diagnosis,
        RM.treatment_details,
        RM.notes
    FROM
        reservations AS R
    LEFT JOIN
        user AS U ON R.customer_id = U.user_id
    LEFT JOIN
        services AS S ON R.service_id = S.service_id
    LEFT JOIN
        rekamedis AS RM ON R.reservation_id = RM.reservation_id
    WHERE R.status IN ('Dikonfirmasi', 'Selesai') -- Hanya tampilkan yang sudah dikonfirmasi atau selesai
    ORDER BY
        R.reservation_date DESC, R.reservation_time DESC";

$result_rekam_medis_list = $conn->query($sql_rekam_medis_list);
$medicalRecords = [];

// Periksa apakah query berhasil dieksekusi
if ($result_rekam_medis_list === FALSE) {
    // Log error ke server log atau tampilkan di halaman (untuk debugging)
    error_log("Error pada query Rekam Medis List: " . $conn->error);
    echo "<p style='color: red; text-align: center;'>Terjadi kesalahan saat mengambil data rekam medis. Silakan coba lagi nanti.</p>";
} else {
    // Jika ada baris data yang ditemukan, masukkan ke array
    if ($result_rekam_medis_list->num_rows > 0) {
        while ($row = $result_rekam_medis_list->fetch_assoc()) {
            $medicalRecords[] = $row;
        }
    }
}

$conn->close(); // Tutup koneksi database
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Rekam Medis - Dermalux Clinik</title>
    <link rel="stylesheet" href="style-A.css"> <!-- Pastikan path ini benar -->
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <img src="../imgs/logo.png" alt="logo"> <!-- Pastikan path ini benar -->
            </div>
            <nav>
                <ul>
                    <li><a href="Dasboard-A.php">Dashboard</a></li>
                    <li><a href="reservasi.php">Reservasi</a></li>
                    <li><a href="Layanan-produk.php">Layanan & Produk</a></li>
                    <li><a href="Rekamedis-A.php" class="active">Rekam Medis</a></li> <!-- Link ke halaman ini -->
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="medical-record-list-container">
            <h1 class="section-title">Rekam Medis Pasien</h1>
            <?php if (empty($medicalRecords)): ?>
                <p class="empty-status-message">Belum ada rekam medis yang tercatat dengan status Dikonfirmasi atau Selesai.</p>
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
                            <th>Detail Rekam Medis</th>
                            <!-- Kolom Aksi telah dihapus -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicalRecords as $record): ?>
                            <tr>
                                <td data-label="ID Reservasi"><?php echo htmlspecialchars($record['reservation_id']); ?></td>
                                <td data-label="Nama Pasien"><?php echo htmlspecialchars($record['customer_name'] ?? 'N/A'); ?></td>
                                <td data-label="Tanggal"><?php echo htmlspecialchars($record['reservation_date']); ?></td>
                                <td data-label="Waktu"><?php echo htmlspecialchars($record['reservation_time']); ?></td>
                                <td data-label="Layanan"><?php echo htmlspecialchars($record['service_name'] ?? 'N/A'); ?></td>
                                <td data-label="Status">
                                    <?php
                                        $status_text = htmlspecialchars($record['status']);
                                        $status_class = strtolower($status_text);
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status_text); ?></span>
                                </td>
                                <td data-label="Detail Rekam Medis">
                                    <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis'] ?? 'Belum ada'); ?><br>
                                    <strong>Perawatan:</strong> <?php echo htmlspecialchars($record['treatment_details'] ?? 'Belum ada'); ?><br>
                                    <strong>Catatan:</strong> <?php echo htmlspecialchars($record['notes'] ?? 'Belum ada'); ?>
                                </td>
                                <!-- Sel Aksi telah dihapus -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
