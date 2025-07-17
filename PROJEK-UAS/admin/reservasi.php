<?php

session_start(); 

include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'admin'; 
}
$current_user_role = $_SESSION['user_role'];


if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id']; // Pastikan ini integer

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
    // If the new status is 'Selesai' and the current user's role is NOT 'doctor',
    // then prevent the update and redirect with an unauthorized status message.
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
            header("Location: Dasboard-A.php?status=updated");
            exit();
        } else {
            error_log("Error updating status: " . $stmt->error);
        }
        $stmt->close();
    }
}

// Fungsi untuk mengambil semua data reservasi (untuk statistik dan daftar terbaru)
// Membungkus fungsi dengan if (!function_exists()) untuk mencegah redeklarasi
if (!function_exists('getReservations')) {
    function getReservations($conn) {
        $sql = "SELECT
                    r.reservation_id,
                    u.name AS customer_name,
                    s.service_name, -- Bisa NULL jika reservasi hanya produk
                    r.reservation_date AS date,
                    r.reservation_time AS time,
                    r.total_price,
                    r.status,
                    r.created_at
                FROM
                    reservations r
                JOIN
                    user u ON r.customer_id = u.user_id
                LEFT JOIN -- Gunakan LEFT JOIN karena service_id di reservations bisa NULL jika reservasi hanya produk
                    services s ON r.service_id = s.service_id
                ORDER BY
                    r.created_at DESC";

        $result = $conn->query($sql);

        if ($result === false) {
            error_log("SQL Error in getReservations: " . $conn->error);
            return [];
        }

        $reservations = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $reservationId = $row['reservation_id'];
                $row['items'] = []; // Inisialisasi array untuk item produk

                // Ambil item produk dari tabel reservation_items untuk reservasi ini
                $stmt_items = $conn->prepare("SELECT product_name, quantity, price FROM reservation_items WHERE reservation_id = ?");
                if ($stmt_items) {
                    $stmt_items->bind_param("i", $reservationId);
                    $stmt_items->execute();
                    $result_items = $stmt_items->get_result();
                    while ($item = $result_items->fetch_assoc()) {
                        $row['items'][] = $item;
                    }
                    $stmt_items->close();
                } else {
                    error_log("Error preparing reservation_items statement: " . $conn->error);
                }
                $reservations[] = $row;
            }
        }
        return $reservations;
    }
}


// Ambil semua reservasi dari database
$allReservations = getReservations($conn);

// PHP untuk menghitung statistik reservasi/kunjungan
$totalVisits = count($allReservations);
$dikonfirmasiCount = 0;
$menungguKonfirmasiCount = 0; // Menggunakan 'Pending' sesuai ENUM di DB Anda
$selesaiCount = 0;
$dibatalkanCount = 0; // Tambahkan hitungan untuk status Dibatalkan

foreach ($allReservations as $res) {
    if ($res['status'] === 'Dikonfirmasi') {
        $dikonfirmasiCount++;
    } elseif ($res['status'] === 'Pending') { // Menggunakan 'Pending' sesuai ENUM di DB Anda
        $menungguKonfirmasiCount++;
    } elseif ($res['status'] === 'Selesai') {
        $selesaiCount++;
    } elseif ($res['status'] === 'Dibatalkan') { // Menambahkan kondisi untuk status Dibatalkan
        $dibatalkanCount++;
    }
}

// Tutup koneksi database setelah semua data diambil dan dihitung
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dermalux Clinik - Admin Dashboard</title> <link rel="stylesheet" href="style-A.css">
    <style>
        /* Basic styling for messages */
        .message {
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.fade-out {
            opacity: 0;
        }
    </style>
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
            <h2 class="section-title">Reservasi Pasien</h2>

            <section class="recent-reservations">
                <?php if (empty($allReservations)): ?>
                    <p class="empty-status-message">Belum ada reservasi yang tercatat.</p>
                <?php else: ?>
                    <table class="status-table"> <thead>
                                <tr>
                                    <th>ID Reservasi</th>
                                    <th>Nama Pasien</th>
                                    <th>Layanan/Item</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Total Harga</th>
                                    <th>Status</th>
                                    <th>Dibuat Pada</th>
                                    <th>Aksi</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReservations as $res): ?>
                                <tr>
                                    <td data-label="ID Reservasi"><?php echo htmlspecialchars($res['reservation_id']); ?></td>
                                    <td data-label="Nama Pasien"><?php echo htmlspecialchars($res['customer_name']); ?></td>
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
                                    <td data-label="Tanggal"><?php echo htmlspecialchars($res['date']); ?></td>
                                    <td data-label="Waktu"><?php echo htmlspecialchars($res['time']); ?></td>
                                    <td data-label="Total Harga">Rp <?php echo number_format($res['total_price'], 0, ',', '.'); ?></td>
                                    <td data-label="Status">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="update_status_id" value="<?php echo htmlspecialchars($res['reservation_id']); ?>">
                                            <select name="new_status" onchange="this.form.submit()">
                                                <option value="Pending" <?php echo ($res['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Dikonfirmasi" <?php echo ($res['status'] == 'Dikonfirmasi') ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                                <option value="Selesai" <?php echo ($res['status'] == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                                                <option value="Dibatalkan" <?php echo ($res['status'] == 'Dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td data-label="Dibuat Pada"><?php echo htmlspecialchars($res['created_at']); ?></td>
                                    <td data-label="Aksi">
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($res['reservation_id']); ?>">
                                                <button type="submit" class="delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus reservasi ini?');">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
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
                &copy; 2024 Admin Panel. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
