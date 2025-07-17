<?php
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

$sql_all_patients = "
    SELECT
        user_id,
        name,
        email
        -- Kolom phone_number dihapus sesuai permintaan
    FROM
        user
    WHERE
        role = 'pasien'
    ORDER BY
        user_id ASC"; // Urutkan berdasarkan user_id secara ascending untuk nomor urut yang konsisten

$result_all_patients = $conn->query($sql_all_patients);
$all_patients = [];
if ($result_all_patients === FALSE) {
    echo "Error pada query Daftar Pasien: " . $conn->error;
} else {
    if ($result_all_patients->num_rows > 0) {
        while ($row = $result_all_patients->fetch_assoc()) {
            $all_patients[] = $row;
        }
    }
}

// Tutup koneksi database
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pasien</title>
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
                    <li><a href="Dasboard-D.php">Dashboard</a></li>
                    <li><a href="Rekamedis.php">Rekam Medis</a></li>
                    <li><a href="Daftarpasien.php" class="active">Daftar Pasien</a></li> <!-- Menandai link aktif -->
                    <li><a href="jadwal.php">Jadwal Praktik</a></li>
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <section class="patient-list-section">
            <h2 class="section-title">Semua Pasien Terdaftar</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th> <!-- Mengubah header kolom menjadi No. Urut -->
                        <th>Nama Pasien</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_patients)): ?>
                        <?php $no_urut = 1; ?> <!-- Inisialisasi nomor urut -->
                        <?php foreach ($all_patients as $patient): ?>
                            <tr>
                                <td data-label="No. Urut"><?php echo $no_urut++; ?></td> <!-- Menampilkan nomor urut -->
                                <td data-label="Nama Pasien"><?php echo htmlspecialchars($patient['name']); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($patient['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-status-message">Tidak ada pasien terdaftar.</td> <!-- Mengubah colspan menjadi 3 -->
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
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
