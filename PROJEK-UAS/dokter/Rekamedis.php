<?php
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

$logged_in_doctor_id = 2; // Contoh: ID dokter dari tabel 'user' Anda

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_id'])) {
    $reservation_id_to_update = $_POST['update_status_id'];
    $new_status = $_POST['new_status'];

    $update_sql = "UPDATE reservations SET status = ? WHERE reservation_id = ?";
    $stmt = $conn->prepare($update_sql);
    if ($stmt === false) {
        echo "Error mempersiapkan statement update status: " . $conn->error;
    } else {
        $stmt->bind_param("si", $new_status, $reservation_id_to_update);

        if ($stmt->execute()) {
            header("Location: Rekamedis.php?status=success");
            exit();
        } else {
            echo "Error saat memperbarui status reservasi: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle aksi "Simpan Rekam Medis"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_medical_record'])) {
    $reservation_id = $_POST['record_reservation_id'];
    $notes = $_POST['notes'];
    $diagnosis = $_POST['diagnosis'];
    $treatment_details = $_POST['treatment_details'];

    // Cek apakah rekam medis untuk reservasi ini sudah ada
    $check_sql = "SELECT record_id FROM rekamedis WHERE reservation_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    // Periksa jika prepare statement berhasil
    if ($check_stmt === false) {
        echo "Error mempersiapkan statement cek rekam medis: " . $conn->error;
    } else {
        $check_stmt->bind_param("i", $reservation_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // Update rekam medis yang sudah ada
            $update_record_sql = "UPDATE rekamedis SET notes = ?, diagnosis = ?, treatment_details = ?, doctor_id = ? WHERE reservation_id = ?";
            $update_record_stmt = $conn->prepare($update_record_sql);
            if ($update_record_stmt === false) {
                echo "Error mempersiapkan statement update rekam medis: " . $conn->error;
            } else {
                $update_record_stmt->bind_param("sssii", $notes, $diagnosis, $treatment_details, $logged_in_doctor_id, $reservation_id);
                if ($update_record_stmt->execute()) {
                    header("Location: Rekamedis.php?status=record_updated");
                    exit();
                } else {
                    echo "Error saat memperbarui rekam medis: " . $update_record_stmt->error;
                }
                $update_record_stmt->close();
            }
        } else {
            // Tambah rekam medis baru
            $insert_record_sql = "INSERT INTO rekamedis (reservation_id, doctor_id, notes, diagnosis, treatment_details) VALUES (?, ?, ?, ?, ?)";
            $insert_record_stmt = $conn->prepare($insert_record_sql);
            if ($insert_record_stmt === false) {
                echo "Error mempersiapkan statement insert rekam medis: " . $conn->error;
            } else {
                $insert_record_stmt->bind_param("iisss", $reservation_id, $logged_in_doctor_id, $notes, $diagnosis, $treatment_details);
                if ($insert_record_stmt->execute()) {
                    header("Location: Rekamedis.php?status=record_added");
                    exit();
                } else {
                    echo "Error saat menambahkan rekam medis: " . $insert_record_stmt->error;
                }
                $insert_record_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

$sql_all_reservations = "
    SELECT
        R.reservation_id AS id,
        U.name AS patient_name,
        R.reservation_date,
        R.reservation_time,
        S.service_name AS services,
        R.status,
        MR.notes,
        MR.diagnosis,
        MR.treatment_details
    FROM
        reservations AS R
    JOIN
        user AS U ON R.customer_id = U.user_id
    JOIN
        services AS S ON R.service_id = S.service_id
    LEFT JOIN
        rekamedis AS MR ON R.reservation_id = MR.reservation_id
    WHERE
        R.status IN ('Dikonfirmasi', 'Selesai') -- Hanya tampilkan status Dikonfirmasi dan Selesai
    ORDER BY
        R.reservation_date ASC, R.reservation_time ASC"; // Urutkan berdasarkan tanggal dan waktu

$result_all_reservations = $conn->query($sql_all_reservations);
$all_reservations = [];
if ($result_all_reservations === FALSE) {
    echo "Error pada query Semua Reservasi: " . $conn->error;
} else {
    if ($result_all_reservations->num_rows > 0) {
        while ($row = $result_all_reservations->fetch_assoc()) {
            $all_reservations[] = $row;
        }
    }
}

$current_record_to_edit = null;
if (isset($_GET['action']) && $_GET['action'] == 'view_record' && isset($_GET['reservation_id'])) {
    $reservation_id_to_view = $_GET['reservation_id'];
    $sql_single_record = "
        SELECT
            R.reservation_id AS id,
            U.name AS patient_name,
            R.reservation_date,
            R.reservation_time,
            S.service_name AS services,
            R.status,
            MR.notes,
            MR.diagnosis,
            MR.treatment_details
        FROM
            reservations AS R
        JOIN
            user AS U ON R.customer_id = U.user_id
        JOIN
            services AS S ON R.service_id = S.service_id
        LEFT JOIN
            rekamedis AS MR ON R.reservation_id = MR.reservation_id
        WHERE
            R.reservation_id = ?";
    $stmt_single = $conn->prepare($sql_single_record);
    // Periksa jika prepare statement berhasil
    if ($stmt_single === false) {
        echo "Error mempersiapkan statement single record: " . $conn->error;
    } else {
        $stmt_single->bind_param("i", $reservation_id_to_view);
        $stmt_single->execute();
        $result_single = $stmt_single->get_result();
        if ($result_single->num_rows > 0) {
            $current_record_to_edit = $result_single->fetch_assoc();
        }
        $stmt_single->close();
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
    <title>Rekam Medis Dokter</title>
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
                    <li><a href="Rekamedis.php">Rekam Medis</a></li> <!-- Diperbarui dari Reservasi.php -->
                    <li><a href="Daftarpasien.php">Daftar Pasien</a></li> <!-- Menambahkan link Daftar Pasien -->
                    <li><a href="jadwal.php">Jadwal Praktik</a></li> <!-- Menambahkan link Jadwal Praktik -->
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h1 class="section-title">Rekam Medis Pasien</h1>

        <?php if (isset($_GET['status'])): ?>
            <?php
            $message = '';
            $bg_color = '';
            $text_color = '';
            if ($_GET['status'] == 'success') {
                $message = 'Status reservasi berhasil diperbarui!';
                $bg_color = '#d4edda';
                $text_color = '#155724';
            } elseif ($_GET['status'] == 'record_added') {
                $message = 'Rekam medis berhasil ditambahkan!';
                $bg_color = '#d1ecf1';
                $text_color = '#0c5460';
            } elseif ($_GET['status'] == 'record_updated') {
                $message = 'Rekam medis berhasil diperbarui!';
                $bg_color = '#fff3cd';
                $text_color = '#856404';
            }
            ?>
            <div style="background-color: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; border: 1px solid <?php echo str_replace('d', 'c', $bg_color); ?>; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <section class="all-reservations-section">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Reservasi</th>
                        <th>Nama Pasien</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Layanan</th>
                        <th>Status</th>
                        <th>Detail Rekam Medis</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_reservations)): ?>
                        <?php foreach ($all_reservations as $record): ?>
                            <tr>
                                <!-- Menghilangkan '#' dari ID Reservasi -->
                                <td data-label="ID Reservasi"><?php echo htmlspecialchars($record['id']); ?></td>
                                <td data-label="Nama Pasien"><?php echo htmlspecialchars($record['patient_name']); ?></td>
                                <td data-label="Tanggal"><?php echo htmlspecialchars($record['reservation_date']); ?></td>
                                <td data-label="Waktu"><?php echo htmlspecialchars($record['reservation_time']); ?></td>
                                <td data-label="Layanan">
                                    <ul class="item-list">
                                        <li><?php echo htmlspecialchars($record['services']); ?></li>
                                    </ul>
                                </td>
                                <td data-label="Status">
                                    <form method="POST" action="Rekamedis.php" class="status-form">
                                        <input type="hidden" name="update_status_id" value="<?php echo htmlspecialchars($record['id']); ?>">
                                        <select name="new_status" onchange="this.form.submit()">
                                            <option value="Pending" <?php echo ($record['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Dikonfirmasi" <?php echo ($record['status'] == 'Dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                            <option value="Selesai" <?php echo ($record['status'] == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                                        </select>
                                    </form>
                                </td>
                                <td data-label="Detail Rekam Medis">
                                    <?php if ($record['status'] == 'Selesai' || $record['status'] == 'Dikonfirmasi'): ?>
                                        <?php if (!empty($record['diagnosis'])): ?>
                                            <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($record['treatment_details'])): ?>
                                            <strong>Perawatan:</strong> <?php echo htmlspecialchars($record['treatment_details']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($record['notes'])): ?>
                                            <strong>Catatan:</strong> <?php echo htmlspecialchars($record['notes']); ?>
                                        <?php endif; ?>
                                        <?php if (empty($record['diagnosis']) && empty($record['treatment_details']) && empty($record['notes'])): ?>
                                            Belum ada catatan medis.
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #888;">(Reservasi belum siap)</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Aksi">
                                    <?php if ($record['status'] === 'Selesai' || $record['status'] === 'Dikonfirmasi'): ?>
                                        <a href="Rekamedis.php?action=view_record&reservation_id=<?php echo htmlspecialchars($record['id']); ?>" class="btn" style="padding: 5px 10px; font-size: 0.9em; background-color: #007bff;">
                                            <?php echo ($record['notes'] || $record['diagnosis'] || $record['treatment_details']) ? 'Lihat/Edit Catatan' : 'Tambah Catatan'; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #888;">(Aksi tidak tersedia)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-status-message">Tidak ada reservasi atau rekam medis yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php if ($current_record_to_edit): ?>
            <section class="medical-record-form-container">
                <h2><?php echo ($current_record_to_edit['notes'] || $current_record_to_edit['diagnosis'] || $current_record_to_edit['treatment_details']) ? 'Edit Rekam Medis' : 'Tambah Rekam Medis'; ?></h2>
                <div class="medical-record-info">
                    <p><strong>ID Reservasi:</strong> <?php echo htmlspecialchars($current_record_to_edit['id']); ?></p>
                    <p><strong>Nama Pasien:</strong> <?php echo htmlspecialchars($current_record_to_edit['patient_name']); ?></p>
                    <p><strong>Tanggal:</strong> <?php echo htmlspecialchars($current_record_to_edit['reservation_date']); ?></p>
                    <p><strong>Waktu:</strong> <?php echo htmlspecialchars($current_record_to_edit['reservation_time']); ?></p>
                    <p><strong>Layanan:</strong> <?php echo htmlspecialchars($current_record_to_edit['services']); ?></p>
                </div>
                <form method="POST" action="Rekamedis.php">
                    <input type="hidden" name="save_medical_record" value="1">
                    <input type="hidden" name="record_reservation_id" value="<?php echo htmlspecialchars($current_record_to_edit['id']); ?>">

                    <div class="form-group">
                        <label for="diagnosis">Diagnosis:</label>
                        <textarea id="diagnosis" name="diagnosis" class="form-control" placeholder="Masukkan diagnosis pasien..."><?php echo htmlspecialchars($current_record_to_edit['diagnosis'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="treatment_details">Detail Perawatan:</label>
                        <textarea id="treatment_details" name="treatment_details" class="form-control" placeholder="Masukkan detail perawatan yang diberikan..."><?php echo htmlspecialchars($current_record_to_edit['treatment_details'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="notes">Catatan Tambahan:</label>
                        <textarea id="notes" name="notes" class="form-control" placeholder="Tambahkan catatan tambahan tentang pasien atau perawatan..."><?php echo htmlspecialchars($current_record_to_edit['notes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-buttons">
                        <a href="Rekamedis.php" class="btn-cancel">Batal</a>
                        <button type="submit" class="btn-submit">Simpan Rekam Medis</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
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
