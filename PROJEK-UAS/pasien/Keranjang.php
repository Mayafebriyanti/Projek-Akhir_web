<?php
session_start(); // Mulai sesi untuk mengelola item keranjang

// Sertakan file konfigurasi database
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

// Pastikan koneksi database berhasil setelah include config.php
if ($conn->connect_error) {
    die("Koneksi database gagal setelah include config: " . $conn->connect_error);
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Periksa apakah produk sedang ditambahkan (sekarang hanya mengharapkan product_id dari URL)
if (isset($_GET['product_id'])) {
    $productId = (int)$_GET['product_id']; // Konversi ke integer sesuai struktur tabel Anda

    // Ambil detail produk dari database menggunakan prepared statement
    $stmt = $conn->prepare("SELECT product_name, price FROM products WHERE product_id = ?");
    if ($stmt === false) {
        error_log("Gagal menyiapkan statement: " . $conn->error);
        header('Location: Keranjang.php?error=db_error');
        exit();
    }
    $stmt->bind_param("i", $productId); // "i" untuk integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $productName = $product['product_name'];
        $productPrice = (float)$product['price']; // Pastikan harga adalah float/desimal

        // Check if the product already exists in the cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] === $productId) { // Bandingkan ID integer
                $item['quantity']++;
                $found = true;
                break;
            }
        }
        unset($item); // Unset the reference to avoid issues in subsequent iterations

        // If not found, add as a new item
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $productId,
                'name' => $productName,
                'price' => $productPrice,
                'quantity' => 1
            ];
        }
    } else {
        error_log("Mencoba menambahkan ID produk yang tidak ada: " . $productId);
        header('Location: Keranjang.php?error=product_not_found');
        exit();
    }
    $stmt->close();

    // Redirect untuk mencegah penambahan ulang saat refresh dan membersihkan URL
    header('Location: Keranjang.php');
    exit();
}

// Tangani penghapusan item dari keranjang
if (isset($_GET['remove_item'])) {
    $removeItemId = (int)$_GET['remove_item']; // Konversi ke integer
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['id'] === $removeItemId) {
            unset($_SESSION['cart'][$key]);
            break;
        }
    }
    // Indeks ulang array setelah penghapusan untuk mencegah celah di kunci
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header('Location: Keranjang.php');
    exit();
}

// Hitung total harga
$totalPrice = 0;
foreach ($_SESSION['cart'] as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}

// Tutup koneksi database
$conn->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dermalux Clinik</title>
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
        <section class="cart-section">
            <h1 class="section-title">Keranjang Belanja Anda</h1>

            <?php if (empty($_SESSION['cart'])): ?>
                <p class="empty-cart-message">Keranjang Anda kosong. Mari mulai berbelanja!</p>
                <div class="empty-cart-actions">
                    <a href="Dasboard.php" class="btn-primary">Lanjutkan Belanja</a>
                </div>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                <td>
                                    <a href="Keranjang.php?remove_item=<?php echo htmlspecialchars($item['id']); ?>" class="remove-btn">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-summary">
                    Total: Rp <?php echo number_format($totalPrice, 0, ',', '.'); ?>
                </div>

                <div class="action-buttons">
                    <a href="Dasboard.php" class="btn-secondary">Lanjutkan Belanja</a>
                    <a href="Reservasi.php" class="btn-primary">Lanjutkan ke Reservasi</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>
