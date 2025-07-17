<?php
session_start(); 

// Database connection details
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "projekuas"; 

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// --- LOGIKA VALIDASI AKSES DAN PENENTUAN ROLE PENGGUNA ---
$customerId = null;
$currentUserRole = null; // Variabel untuk menyimpan peran pengguna yang sedang login

if (isset($_SESSION['user_id'])) {
    $tempUserId = $_SESSION['user_id'];

    $stmt_user_role = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
    if ($stmt_user_role) {
        $stmt_user_role->bind_param("i", $tempUserId);
        $stmt_user_role->execute();
        $result_user_role = $stmt_user_role->get_result();

        if ($result_user_role->num_rows > 0) {
            $user = $result_user_role->fetch_assoc();
            $currentUserRole = $user['role']; // Simpan peran pengguna

            if (($currentUserRole === 'pasien' && $tempUserId >= 3) || $currentUserRole === 'admin') {
                $customerId = $tempUserId; // User memenuhi kriteria, set customerId
            }
        }
        $stmt_user_role->close();
    } else {
        error_log("Gagal menyiapkan statement user role: " . $conn->error);
    }
}


$reservations = [];

if ($customerId !== null) {
    $sql_reservations = "SELECT reservation_id, customer_id, service_id, reservation_date, reservation_time, total_price, status, created_at FROM reservations";
    $params = [];
    $types = "";

    if ($currentUserRole === 'pasien') { // Jika pasien, filter berdasarkan customer_id
        $sql_reservations .= " WHERE customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    $sql_reservations .= " ORDER BY created_at DESC";

    $stmt_reservations = $conn->prepare($sql_reservations);
    if ($stmt_reservations === false) {
        error_log("Gagal menyiapkan statement reservasi: " . $conn->error);
    } else {
        if (!empty($params)) {
            $stmt_reservations->bind_param($types, ...$params);
        }
        $stmt_reservations->execute();
        $result_reservations = $stmt_reservations->get_result();

        while ($reservation = $result_reservations->fetch_assoc()) {
            $reservationId = $reservation['reservation_id'];
            $serviceId = $reservation['service_id']; // service_id bisa NULL
            $reservation['items'] = []; // Inisialisasi array item untuk setiap reservasi

            // Ambil detail item dari tabel reservation_items
            // Menggunakan 'product_name' sesuai dengan DDL tabel Anda
            $stmt_items = $conn->prepare("SELECT product_name, quantity, price FROM reservation_items WHERE reservation_id = ?");
            if ($stmt_items === false) {
                error_log("Gagal menyiapkan statement reservation_items: " . $conn->error);
            } else {
                $stmt_items->bind_param("i", $reservationId);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                while ($item = $result_items->fetch_assoc()) {
                    // Gunakan product_name sebagai nama item
                    $reservation['items'][] = [
                        'name' => htmlspecialchars($item['product_name']), // Menggunakan product_name
                        'quantity' => htmlspecialchars($item['quantity']),
                        'price' => (float)$item['price']
                    ];
                }
                $stmt_items->close();
            }

            // Jika tidak ada item yang ditemukan di reservation_items
            // DAN service_id di tabel reservations tidak NULL (berarti ini adalah reservasi layanan lama)
            // Maka coba ambil nama layanan dari tabel services
            if (empty($reservation['items']) && $serviceId !== null) {
                $stmt_service_name = $conn->prepare("SELECT service_name FROM services WHERE service_id = ?");
                if ($stmt_service_name === false) {
                    error_log("Gagal menyiapkan statement service_name: " . $conn->error);
                } else {
                    $stmt_service_name->bind_param("i", $serviceId);
                    $stmt_service_name->execute();
                    $result_service_name = $stmt_service_name->get_result();
                    if ($service_name_row = $result_service_name->fetch_assoc()) {
                        // Tambahkan sebagai item ke array items
                        $reservation['items'][] = [
                            'name' => htmlspecialchars($service_name_row['service_name']),
                            'quantity' => 1, // Asumsi kuantitas 1 untuk layanan tunggal
                            'price' => $reservation['total_price'] // Ambil harga total dari reservasi utama
                        ];
                    }
                    $stmt_service_name->close();
                }
            }
            $reservations[] = $reservation; // Tambahkan reservasi ke array utama setelah memproses item
        }
        $stmt_reservations->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Reservasi - Dermalux Clinik</title>
    <!-- Memuat file CSS eksternal -->
    <link rel="stylesheet" href="style-R.css">
    <style>
        /* Tambahkan sedikit styling untuk kolom nomor urut jika diperlukan */
        .status-table th:first-child,
        .status-table td:first-child {
            width: 80px; /* Sesuaikan lebar kolom No. Reservasi */
            text-align: center;
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
                    <li><a href="Dasboard.php">Dashboard</a></li>
                    <li><a href="Keranjang.php">Keranjang</a></li>
                    <li><a href="Reservasi.php">Reservasi</a></li>
                    <li><a href="Status.php">Status Reservasi</a></li>
                    <li><a href="logout.php" class="login-btn">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="status-section">
            <h1 class="section-title">Status Reservasi Anda</h1>

            <?php if ($customerId === null): ?>
                <p class="empty-status-message">
                    Akses ditolak. Anda harus login sebagai **pasien** dengan **ID 3 atau lebih** untuk melihat status reservasi, atau sebagai **admin**.
                </p>
                <div class="back-button-container">
                    <a href="login.php" class="btn-primary">Login</a>
                </div>
            <?php elseif (empty($reservations)): ?>
                <p class="empty-status-message">Anda belum memiliki reservasi. Mari buat reservasi pertama Anda!</p>
                <div class="empty-cart-actions">
                    <a href="Dasboard.php" class="btn-primary">Buat Reservasi</a>
                </div>
            <?php else: ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>No. Reservasi</th>
                            <!-- Kolom ID Reservasi dihapus dari sini -->
                            <th>Tanggal & Waktu</th>
                            <th>Item/Layanan</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Dibuat Pada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $reservationNumber = 1001; // Dimulai dari 1001 ?>
                        <?php foreach ($reservations as $res): ?>
                            <tr>
                                <td data-label="No. Reservasi"><?php echo $reservationNumber++; ?></td>
                                <!-- Data ID Reservasi dihapus dari sini -->
                                <td data-label="Tanggal & Waktu">
                                    <?php echo htmlspecialchars($res['reservation_date']); ?><br>
                                    <?php echo htmlspecialchars($res['reservation_time']); ?>
                                </td>
                                <td data-label="Item/Layanan">
                                    <ul class="item-list">
                                        <?php if (!empty($res['items'])): ?>
                                            <?php foreach ($res['items'] as $item): ?>
                                                <li>
                                                    <?php echo $item['name']; ?> (x<?php echo $item['quantity']; ?>)
                                                    - Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>Detail tidak tersedia.</li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                                <td data-label="Total Harga">Rp <?php echo number_format($res['total_price'], 0, ',', '.'); ?></td>
                                <td data-label="Status">
                                    <?php
                                        // Normalisasi status dari database untuk kelas CSS
                                        $statusFromDb = strtolower(htmlspecialchars($res['status']));
                                        $statusClass = '';

                                        if ($statusFromDb === 'pending') {
                                            $statusClass = 'pending';
                                        } elseif ($statusFromDb === 'terkonfirmasi' || $statusFromDb === 'dikonfirmasi') {
                                            $statusClass = 'terkonfirmasi';
                                        } elseif ($statusFromDb === 'selesai') {
                                            $statusClass = 'selesai';
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($res['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Dibuat Pada"><?php echo htmlspecialchars($res['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
                <p>&copy; 2025 Dermalux Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
