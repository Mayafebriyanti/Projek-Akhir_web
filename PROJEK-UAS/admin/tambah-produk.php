<?php
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

$message = ""; 


// Tangani pengiriman formulir
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari formulir dan sanitasi
    $product_name = htmlspecialchars($_POST['product_name']); // Mengubah dari service_name
    $price = filter_var($_POST['price'], FILTER_SANITIZE_NUMBER_INT); // Hanya ambil angka
    $stock = filter_var($_POST['stock'], FILTER_SANITIZE_NUMBER_INT); // Mengubah dari duration ke stock
    $description = htmlspecialchars($_POST['description']); // Ambil data deskripsi

    // Validasi sederhana
    if (empty($product_name) || empty($price) || empty($stock)) { // Mengubah dari service_name, duration
        $message = "<p class='message error'>Semua kolom (kecuali Deskripsi) harus diisi!</p>";
    } else {
        // Menggunakan prepared statement untuk mencegah SQL Injection
        // Pastikan tabel products_table memiliki kolom 'description' (misal: TEXT atau VARCHAR)
        $stmt = $conn->prepare("INSERT INTO products_table (product_name, price, stock, description) VALUES (?, ?, ?, ?)"); // Mengubah tabel dan kolom
        $stmt->bind_param("siis", $product_name, $price, $stock, $description); // s: string, i: integer, i: integer, s: string

        if ($stmt->execute()) {
            $message = "<p class='message success'>Produk berhasil ditambahkan!</p>";
            // Opsional: Redirect ke halaman manajemen layanan & produk setelah sukses
            header("Location: Manajemen-Layanan-Produk.php?status=success_add_product");
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
    <title>Tambah Produk</title> <!-- Mengubah judul halaman -->
    <!-- Menautkan ke file CSS eksternal baru -->
    <link rel="stylesheet" href="style-Pro.css">
</head>
<body>
    <div class="form-card-container">
        <h2>Tambah Produk Baru</h2> <!-- Mengubah judul di dalam body -->

        <?php echo $message; // Tampilkan pesan sukses/error ?>
        <form method="POST" action="tambah-produk.php"> <!-- Mengubah action form -->
            <div class="form-group">
                <label for="product_name">Nama Produk:</label> <!-- Mengubah label -->
                <input type="text" id="product_name" name="product_name" required> <!-- Mengubah name dan id -->
            </div>
            <div class="form-group">
                <label for="price">Harga (Rp):</label>
                <input type="number" id="price" name="price" required min="0">
            </div>
            <div class="form-group">
                <label for="stock">Stok:</label> <!-- Mengubah label -->
                <input type="number" id="stock" name="stock" required min="1"> <!-- Mengubah name dan id -->
            </div>
            <div class="form-group">
                <label for="description">Deskripsi:</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <div class="form-actions">
                <a href="Layanan-Produk.php" class="btn-cancel-form">Batal</a>
                <button type="submit" class="btn-submit-form">Tambah Produk</button> <!-- Mengubah teks tombol -->
            </div>
        </form>
    </div>
</body>
</html>
