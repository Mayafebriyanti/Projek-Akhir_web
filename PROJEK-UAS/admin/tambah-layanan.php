<?php
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

$message = ""; 


// Tangani pengiriman formulir
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari formulir dan sanitasi
    $service_name = htmlspecialchars($_POST['service_name']);
    $price = filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_INT); // Hanya ambil angka
    $duration = filter_var($_POST['duration'], FILTER_SANITIZE_NUMBER_INT); // Hanya ambil angka
    $description = htmlspecialchars($_POST['description']); // Ambil data deskripsi

    // Validasi sederhana
    if (empty($service_name) || empty($price) || empty($duration)) {
        $message = "<p class='message error'>Semua kolom (kecuali Deskripsi) harus diisi!</p>";
    } else {
        // Menggunakan prepared statement untuk mencegah SQL Injection
        // Pastikan tabel services_table memiliki kolom 'description' (misal: TEXT atau VARCHAR)
        $stmt = $conn->prepare("INSERT INTO services_table (service_name, price, duration, description) VALUES (?, ?, ?, ?)");
        // s: string, i: integer, i: integer, s: string
        $stmt->bind_param("siis", $service_name, $price, $duration, $description); 

        if ($stmt->execute()) {
            $message = "<p class='message success'>Layanan berhasil ditambahkan!</p>";
            // Opsional: Redirect ke halaman manajemen layanan setelah sukses
            header("Location: Manajemen-Layanan-Produk.php?status=success_add");
            exit();
        } else {
            $message = "<p class='message error'>Error: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// Tutup koneksi database
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Layanan</title>
    <!-- Menautkan ke file CSS eksternal baru -->
    <link rel="stylesheet" href="style-Pro.css">
</head>
<body>
    <div class="form-card-container">
        <h2>Tambah Layanan Baru</h2>

        <?php echo $message; // Tampilkan pesan sukses/error ?>
        <form method="POST" action="tambah-layanan.php">
            <div class="form-group">
                <label for="service_name">Nama Layanan:</label>
                <input type="text" id="service_name" name="service_name" required>
            </div>
            <div class="form-group">
                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" required min="0">
            </div>
            <div class="form-group">
                <label for="duration">Durasi (menit):</label>
                <input type="number" id="duration" name="duration" required min="1">
            </div>
            <div class="form-group">
                <label for="description">Deskripsi:</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <div class="form-actions">
                <a href="Layanan-Produk.php" class="btn-cancel-form">Batal</a>
                <button type="submit" class="btn-submit-form">Tambah Layanan</button>
            </div>
        </form>
    </div>
</body>
</html>
