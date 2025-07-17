<?php
session_start(); // Mulai sesi untuk mengakses data keranjang dan pengguna

include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

if ($conn->connect_error) {
    die("Koneksi database gagal setelah include config: " . $conn->connect_error);
}

$totalPrice = 0;
$serviceIdToReserve = null; // Akan menjadi NULL jika reservasi adalah produk
$reservationItemsSummary = [];
$reservationMessage = '';


if (!isset($_SESSION['reservation_data'])) {
    if (isset($_GET['service_id'])) {
        $selectedServiceId = (int)$_GET['service_id'];

        $stmt_service = $conn->prepare("SELECT service_name, price FROM services WHERE service_id = ?");
        if ($stmt_service === false) {
            error_log("Gagal menyiapkan statement layanan: " . $conn->error);
            $reservationMessage = "Terjadi kesalahan saat mengambil detail layanan.";
        } else {
            $stmt_service->bind_param("i", $selectedServiceId);
            $stmt_service->execute();
            $result_service = $stmt_service->get_result();

            if ($result_service->num_rows > 0) {
                $service = $result_service->fetch_assoc();
                $_SESSION['reservation_data'] = [
                    'service_id_to_reserve' => $selectedServiceId,
                    'total_price' => (float)$service['price'],
                    'items_summary' => [
                        [
                            'type' => 'service',
                            'name' => htmlspecialchars($service['service_name']),
                            'quantity' => 1,
                            'price' => (float)$service['price']
                        ]
                    ]
                ];
                $_SESSION['cart'] = []; // Kosongkan keranjang jika reservasi adalah layanan tunggal
            } else {
                $reservationMessage = "Layanan yang dipilih tidak ditemukan.";
                header('Location: Dasboard.php?message=service_not_found');
                exit();
            }
            $stmt_service->close();
        }
    } elseif (!empty($_SESSION['cart'])) {
        // Skenario 2: Ambil dari keranjang jika tidak ada service_id di GET dan keranjang tidak kosong
        $cartTotalPrice = 0;
        $cartItemsSummary = [];
        foreach ($_SESSION['cart'] as $item) {
            $cartTotalPrice += $item['price'] * $item['quantity'];
            $cartItemsSummary[] = [
                'type' => 'product', // Tetap tandai sebagai 'product'
                'name' => htmlspecialchars($item['name']),
                'quantity' => htmlspecialchars($item['quantity']),
                'price' => (float)$item['price']
            ];
        }
        $_SESSION['reservation_data'] = [
            'service_id_to_reserve' => null, // NULL untuk reservasi produk
            'total_price' => $cartTotalPrice,
            'items_summary' => $cartItemsSummary
        ];
    }
}

// Muat data reservasi dari sesi untuk ditampilkan di formulir
// Ini dilakukan setelah potensi inisialisasi di atas
if (isset($_SESSION['reservation_data'])) {
    $serviceIdToReserve = $_SESSION['reservation_data']['service_id_to_reserve'];
    $totalPrice = $_SESSION['reservation_data']['total_price'];
    $reservationItemsSummary = $_SESSION['reservation_data']['items_summary'];
}

// Jika tidak ada data reservasi di sesi, dan bukan karena status sukses (setelah POST), redirect.
// Ini adalah validasi akhir sebelum menampilkan formulir.
if (empty($reservationItemsSummary) && (!isset($_GET['status']) || $_GET['status'] !== 'success')) {
    header('Location: Dasboard.php?message=empty_reservation_data'); // Pesan lebih umum
    exit();
}

// --- AKHIR LOGIKA PENANGANAN DATA RESERVASI UNTUK TAMPILAN FORMULIR ---


// Tangani pengiriman formulir reservasi (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationDate = htmlspecialchars($_POST['reservation_date']);
    $reservationTime = htmlspecialchars($_POST['reservation_time']);

    // Mengambil user_id dari sesi setelah login
    // Pastikan sistem login Anda menyimpan user_id ke $_SESSION['user_id']
    $customerId = $_SESSION['user_id'] ?? null;
    // Untuk tujuan pengujian sementara tanpa login, Anda bisa menggunakan ID statis:
    // $customerId = 1;

    if ($customerId === null) {
        $reservationMessage = "Anda harus login untuk membuat reservasi.";
    } elseif (empty($_SESSION['reservation_data']['items_summary'])) { // Cek dari sesi untuk memastikan ada item
        $reservationMessage = "Tidak ada layanan atau produk yang dipilih untuk reservasi. Silakan ulangi proses pemilihan.";
    } else {
        // Ambil data yang akan direservasi dari sesi
        $serviceIdToInsert = $_SESSION['reservation_data']['service_id_to_reserve'];
        $totalPriceToInsert = $_SESSION['reservation_data']['total_price'];
        $itemsToInsert = $_SESSION['reservation_data']['items_summary'];

        // --- Perbaikan di sini: Menentukan doctor_id ---
        // Asumsi: Semua reservasi dari halaman ini akan terkait dengan doctor_id 2
        // Jika ada logika pemilihan dokter yang lebih kompleks, ini perlu disesuaikan.
        $doctorIdForReservation = 2; // Menggunakan ID dokter yang sama dengan Jadwal.php

        // Masukkan reservasi utama ke database
        // service_id bisa NULL jika reservasi berbasis produk.
        // Pastikan kolom 'service_id' di tabel 'reservations' Anda mengizinkan nilai NULL.
        // --- PERBAIKAN: Menambahkan doctor_id ke dalam query INSERT ---
        $stmt = $conn->prepare("INSERT INTO reservations (customer_id, service_id, doctor_id, reservation_date, reservation_time, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");

        if ($stmt === false) {
            error_log("Reservasi DEBUG: Gagal menyiapkan statement reservasi: " . $conn->error);
            $reservationMessage = "Terjadi kesalahan sistem saat menyiapkan reservasi.";
        } else {
            // Parameter binding:
            // 'i' untuk int (customer_id)
            // 'i' untuk int (service_id, bisa NULL)
            // 'i' untuk int (doctor_id) <-- BARU
            // 's' untuk string (date)
            // 's' untuk string (time)
            // 'd' untuk double (total_price)
            // 's' untuk string (status)
            // --- PERBAIKAN: Menambahkan $doctorIdForReservation ke bind_param ---
            $stmt->bind_param("iiissd", $customerId, $serviceIdToInsert, $doctorIdForReservation, $reservationDate, $reservationTime, $totalPriceToInsert);

            // --- DEBUGGING BARU ---
            error_log("Reservasi DEBUG: Query Parameters: customer_id={$customerId}, service_id={$serviceIdToInsert}, doctor_id={$doctorIdForReservation}, reservation_date={$reservationDate}, reservation_time={$reservationTime}, total_price={$totalPriceToInsert}");
            // --- END DEBUGGING BARU ---

            if ($stmt->execute()) {
                $reservationId = $conn->insert_id; // Dapatkan ID reservasi yang baru dibuat
                $reservationMessage = "Reservasi Anda berhasil dibuat!";

                // Simpan detail item ke reservation_items
                // PENTING: Disesuaikan dengan skema tabel reservation_items Anda (hanya product_name)
                $stmt_items = $conn->prepare("INSERT INTO reservation_items (reservation_id, product_name, quantity, price) VALUES (?, ?, ?, ?)");
                if ($stmt_items === false) {
                    error_log("Reservasi DEBUG: Gagal menyiapkan statement reservation_items: " . $conn->error);
                } else {
                    foreach ($itemsToInsert as $item) {
                        // Menggunakan $item['name'] yang berisi nama produk atau layanan
                        $itemNameForDb = $item['name'];
                        $quantity = $item['quantity'];
                        $itemPrice = $item['price'];
                        // Perhatikan: 'isid' karena product_name adalah string, quantity int, price double
                        $stmt_items->bind_param("isid", $reservationId, $itemNameForDb, $quantity, $itemPrice);
                        $stmt_items->execute();
                    }
                    $stmt_items->close();
                }

                // Kosongkan keranjang dan data reservasi di sesi setelah berhasil
                $_SESSION['cart'] = [];
                unset($_SESSION['reservation_data']);

                // Arahkan ke halaman ini lagi dengan parameter status sukses untuk mencegah resubmission
                header('Location: Reservasi.php?status=success');
                exit();
            } else {
                error_log("Reservasi DEBUG: Gagal mengeksekusi statement reservasi: " . $stmt->error);
                $reservationMessage = "Gagal membuat reservasi: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Jika halaman dimuat dengan status sukses dari redirect (setelah reservasi berhasil)
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $reservationMessage = "Reservasi Anda berhasil dibuat!";
    // Kosongkan ringkasan untuk tampilan setelah sukses agar formulir tidak muncul lagi
    $reservationItemsSummary = [];
    $totalPrice = 0;
}

$conn->close(); // Tutup koneksi database
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservasi - Dermalux Clinik</title>
    <link rel="stylesheet" href="style-R.css">
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
        <section class="reservation-section">
            <h1 class="section-title">Formulir Reservasi</h1>

            <?php if (!empty($reservationMessage)): ?>
                <div class="message <?php echo (strpos($reservationMessage, 'berhasil') !== false) ? 'success' : 'error'; ?>">
                    <?php echo $reservationMessage; ?>
                </div>
            <?php endif; ?>

            <?php
            // Tampilkan formulir reservasi hanya jika ada item untuk direservasi,
            // atau jika ada pesan sukses (setelah reservasi berhasil dan keranjang/layanan dikosongkan).
            // Ini untuk memastikan formulir tidak muncul setelah berhasil dan data sudah dihapus.
            if (!empty($reservationItemsSummary) || (isset($_GET['status']) && $_GET['status'] === 'success')):
            ?>
                <div class="cart-summary-for-reservation">
                    <h2>Ringkasan Pesanan Anda:</h2>
                    <?php if (!empty($reservationItemsSummary)): ?>
                        <ul>
                            <?php foreach ($reservationItemsSummary as $item): ?>
                                <li>
                                    <?php echo $item['name']; ?> (x<?php echo $item['quantity']; ?>) - Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><strong>Total Pembayaran: Rp <?php echo number_format($totalPrice, 0, ',', '.'); ?></strong></p>
                    <?php else: ?>
                        <p>Pesanan telah dikonfirmasi. Silakan kembali ke Dashboard.</p>
                    <?php endif; ?>
                </div>

                <?php if (empty($reservationItemsSummary) && (isset($_GET['status']) && $_GET['status'] === 'success')): ?>
                    <div class="empty-cart-actions">
                        <a href="Dasboard.php" class="btn-primary">Kembali ke Dashboard</a>
                    </div>
                <?php else: ?>
                    <form action="Reservasi.php" method="POST" class="reservation-form">
                        <div class="form-group">
                            <label for="reservation_date">Tanggal Kedatangan:</label>
                            <input type="date" id="reservation_date" name="reservation_date" required>
                        </div>
                        <div class="form-group">
                            <label for="reservation_time">Waktu Kedatangan:</label>
                            <select id="reservation_time" name="reservation_time" required>
                                <option value="">Pilih Waktu</option>
                                <?php
                                // Generate options from 08:00 AM to 07:00 PM
                                for ($hour = 8; $hour <= 19; $hour++) {
                                    $time24 = sprintf('%02d:00', $hour); // Format 24 jam untuk nilai (misal: 13:00)
                                    $time12 = ''; // Untuk tampilan AM/PM

                                    if ($hour == 0) {
                                        $time12 = '12:00 AM';
                                    } elseif ($hour < 12) {
                                        $time12 = sprintf('%02d:00 AM', $hour);
                                    } elseif ($hour == 12) {
                                        $time12 = '12:00 PM';
                                    } else {
                                        $time12 = sprintf('%02d:00 PM', $hour - 12);
                                    }
                                    echo "<option value=\"{$time24}\">{$time12}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary">Konfirmasi Reservasi</button>
                    </form>
                <?php endif; ?>

            <?php else: // Jika tidak ada item untuk direservasi dan tidak ada status sukses (kondisi awal jika tidak ada pilihan) ?>
                <p class="empty-cart-message">Tidak ada layanan atau produk yang dipilih untuk reservasi.</p>
                <div class="empty-cart-actions">
                    <a href="Dasboard.php" class="btn-primary">Lanjutkan Belanja</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>