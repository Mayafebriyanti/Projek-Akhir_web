<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$logged_in_doctor_id = 2;

$doctor_schedule = [
    ['day' => 'Senin', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['day' => 'Selasa', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
    ['day' => 'Rabu', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['day' => 'Kamis', 'start_time' => '10:00:00', 'end_time' => '18:00:00'],
    ['day' => 'Jumat', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['day' => 'Sabtu', 'start_time' => '09:00:00', 'end_time' => '14:00:00'],
    ['day' => 'Minggu', 'start_time' => '00:00:00', 'end_time' => '23:59:59'], // Minggu sebagai hari tutup
];

/**
 * Fungsi untuk mendapatkan jumlah reservasi yang dikonfirmasi atau tertunda
 * untuk hari dalam seminggu dan rentang waktu tertentu, mulai dari hari ini.
 *
 * @param mysqli $conn Objek koneksi database.
 * @param int $doctor_id ID dokter yang sedang login.
 * @param string $day_name Nama hari dalam bahasa Indonesia (e.g., 'Senin').
 * @param string $start_time Waktu mulai dalam format 'HH:MM:SS'.
 * @param string $end_time Waktu selesai dalam format 'HH:MM:SS'.
 * @return int Jumlah reservasi.
 */
function getReservationCountForSchedule($conn, $doctor_id, $day_name, $start_time, $end_time) {
    $day_map = [
        'Minggu' => 'Sunday',
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday',
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
    ];
    
    $english_day_name = $day_map[$day_name] ?? null; 

    if (is_null($english_day_name)) {
        error_log("Jadwal Dokter: Nama hari tidak valid untuk konversi: " . $day_name);
        return 0; 
    }

    $sql = "SELECT COUNT(*) AS total_reservations
            FROM reservations
            WHERE doctor_id = ?
            AND DAYNAME(reservation_date) = ?
            AND reservation_time >= ?
            AND reservation_time < ?
            AND reservation_date >= CURDATE()
            AND status IN ('Dikonfirmasi', 'Pending')";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Jadwal Dokter: Error mempersiapkan statement SQL: " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param("isss", $doctor_id, $english_day_name, $start_time, $end_time);
    
    error_log("Jadwal Dokter DEBUG: Query Parameters: doctor_id={$doctor_id}, day_name_eng={$english_day_name}, start_time={$start_time}, end_time={$end_time}");

    // Eksekusi statement
    if (!$stmt->execute()) {
        error_log("Jadwal Dokter: Error saat mengeksekusi query: " . $stmt->error);
        return 0;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close(); // Tutup statement

    error_log("Jadwal Dokter DEBUG: Hari: {$day_name} ({$english_day_name}), Waktu: {$start_time}-{$end_time}, Ditemukan Reservasi: " . $row['total_reservations']);
    
    return $row['total_reservations'];
}

foreach ($doctor_schedule as &$slot) {
    if ($slot['day'] === 'Minggu') { // Jika hari Minggu, set sebagai 'Tutup'
        $slot['reservation_count'] = 'Tutup';
    } else {
        // Panggil fungsi untuk mendapatkan jumlah reservasi
        $slot['reservation_count'] = getReservationCountForSchedule(
            $conn,
            $logged_in_doctor_id,
            $slot['day'], // Kirim nama hari dalam bahasa Indonesia
            $slot['start_time'],
            $slot['end_time']
        );
    }
}
unset($slot); // Lepaskan referensi setelah loop selesai

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Praktik Dokter</title>
    <link rel="stylesheet" href="style-D.css"> 
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
                    <li><a href="Daftarpasien.php">Daftar Pasien</a></li>
                    <li><a href="Jadwal.php" class="active">Jadwal Praktik</a></li> <!-- Menandai link aktif -->
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Konten Utama untuk Profil Dokter dan Jadwal -->
    <main class="main-content">
        <div class="container">
            <!-- Bagian Profil Dokter -->
            <section class="doctor-profile">
                <!-- Pastikan path ke gambar foto dokter Anda benar -->
                <img src="../imgs/foto.png" alt="Foto Dokter Ayu Lestari">
                <div class="doctor-profile-details">
                    <h3>Dr. Ayu Lestari</h3>
                    <p class="specialization">Spesialisasi: Dermatologi & Estetika Medis</p>
                    <p>Dr. Ayu Lestari adalah seorang dokter kulit dan estetika yang berdedikasi dengan pengalaman lebih dari 10 tahun. Beliau fokus pada perawatan kulit yang komprehensif dan inovatif untuk membantu pasien mencapai kulit sehat dan bercahaya.</p>
                </div>
            </section>

            <!-- Bagian Jadwal Dokter -->
            <section class="doctor-schedule-section">
                <h2 class="section-title">Jadwal Praktik Dokter</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hari Praktik</th>
                            <th>Jam Praktik</th>
                            <th>Ketersediaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Loop melalui setiap slot jadwal dokter yang telah diisi dengan data reservasi
                        foreach ($doctor_schedule as $slot): ?>
                            <tr>
                                <td data-label="Hari Praktik"><?php echo htmlspecialchars($slot['day']); ?></td>
                                <td data-label="Jam Praktik">
                                    <?php 
                                        // Tampilkan 'Tutup' jika hari Minggu, selain itu tampilkan jam praktik
                                        if ($slot['day'] === 'Minggu') {
                                            echo 'Tutup';
                                        } else {
                                            // Format waktu menjadi HH:MM
                                            echo htmlspecialchars(substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5));
                                        }
                                    ?>
                                </td>
                                <td data-label="Ketersediaan">
                                    <?php 
                                        // Tampilkan status ketersediaan berdasarkan jumlah reservasi
                                        if ($slot['reservation_count'] === 'Tutup') {
                                            echo '<span style="color: red; font-weight: bold;">Tutup</span>';
                                        } elseif (is_numeric($slot['reservation_count']) && $slot['reservation_count'] > 0) {
                                            // Jika ada reservasi, tampilkan jumlah pasien dengan warna hijau
                                            echo '<span style="color: green; font-weight: bold;">' . htmlspecialchars($slot['reservation_count']) . ' pasien</span>';
                                        } else {
                                            // Jika tidak ada reservasi, tampilkan pesan default
                                            echo 'Tidak ada reservasi';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <!-- Bagian Footer -->
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