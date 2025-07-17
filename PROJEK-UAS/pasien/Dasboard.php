<?php
session_start(); // Mulai sesi jika diperlukan untuk manajemen pengguna atau lainnya

// Sertakan file konfigurasi database
include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

// Pastikan koneksi database berhasil setelah include config.php
if ($conn->connect_error) {
    die("Koneksi database gagal setelah include config: " . $conn->connect_error);
}

$upload_dir = '../uploads/'; // Direktori untuk menyimpan gambar

// Ambil semua produk dari database (termasuk image_url)
$products = [];
// Pastikan query mengambil kolom yang relevan dari tabel products
$sql_products = "SELECT product_id, product_name, description, price, stock, image_url FROM products ORDER BY product_id ASC";
$result_products = $conn->query($sql_products);

// Tambahkan penanganan error untuk kueri produk
if ($result_products === false) {
    error_log("SQL Error fetching products: " . $conn->error);
    // Biarkan $products kosong jika kueri gagal
} else {
    if ($result_products->num_rows > 0) {
        while($row = $result_products->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Ambil semua layanan dari database (termasuk image_url, description, duration, dan includes)
$services = [];
// Perbarui query untuk mengambil kolom description, duration, dan includes
$sql_services = "SELECT service_id, service_name, price, image_url, description, duration, includes FROM services ORDER BY service_id ASC";
$result_services = $conn->query($sql_services);

// Tambahkan penanganan error untuk kueri layanan
if ($result_services === false) {
    error_log("SQL Error fetching services: " . $conn->error);
    // Biarkan $services kosong jika kueri gagal
} else {
    if ($result_services->num_rows > 0) {
        while($row = $result_services->fetch_assoc()) {
            $services[] = $row;
        }
    }
}

$conn->close(); // Tutup koneksi setelah mengambil semua data

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

    <main>
        <section class="hero">
            <h1>Welcome To Dermalux Clinic</h1>
            <p>Dermalux Clinic menyediakan perawatan kecantikan profesional dengan teknologi terkini dan dokter kulit berpengalaman</p>
        </section>

        <section class="service-packages">
            <div class="container">
                <h2>Layanan Paket Perawatan Kami</h2>
                <div class="packages-grid">
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <div class="package-card">
                                <div class="package-image-placeholder">
                                    <?php
                                    // Gunakan image_url dari database untuk layanan
                                    $serviceImagePath = !empty($service['image_url']) ? $upload_dir . htmlspecialchars($service['image_url']) : 'https://placehold.co/300x200/ef6480/FFFFFF?text=Layanan+Tidak+Ditemukan';
                                    ?>
                                    <img src="<?php echo $serviceImagePath; ?>" alt="<?php echo htmlspecialchars($service['service_name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x200/ef6480/FFFFFF?text=Layanan+Tidak+Ditemukan';">
                                </div>
                                <div class="package-details">
                                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                    <div class="package-footer">
                                        <span class="package-price">Rp <?php echo number_format($service['price'], 0, ',', '.'); ?></span>
                                        <a href="#modal-service-<?php echo htmlspecialchars($service['service_id']); ?>" class="btn-select">Pilih Paket</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Tidak ada layanan yang tersedia saat ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Modals for Service Packages (Dihasilkan secara dinamis oleh PHP) -->
        <?php foreach ($services as $service): ?>
            <?php
            $serviceId = $service['service_id'];
            // Ambil durasi langsung dari data layanan yang diambil dari DB
            $serviceDescription = htmlspecialchars($service['description'] ?? 'Deskripsi tidak tersedia.');
            $serviceDuration = htmlspecialchars($service['duration'] ?? 'Tidak diketahui');

            // Pisahkan string includes menjadi array item
            $serviceIncludes = [];
            if (!empty($service['includes'])) {
                $serviceIncludes = array_map('trim', explode(',', $service['includes']));
            }

            // Gunakan image_url dari database untuk modal layanan
            $modalImagePath = !empty($service['image_url']) ? $upload_dir . htmlspecialchars($service['image_url']) : 'https://placehold.co/300x200/ef6480/FFFFFF?text=Layanan+Tidak+Ditemukan';
            ?>
            <div id="modal-service-<?php echo htmlspecialchars($serviceId); ?>" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?php echo htmlspecialchars($service['service_name']); ?></h2>
                        <a href="#" class="modal-close-css">&times;</a>
                    </div>
                    <div class="modal-body">
                        <div class="image-section">
                            <img src="<?php echo $modalImagePath; ?>" alt="Gambar Layanan" onerror="this.onerror=null;this.src='https://placehold.co/300x200/ef6480/FFFFFF?text=Layanan+Tidak+Ditemukan';">
                        </div>
                        <div class="details-section">
                            <div class="price-duration">
                                <span>Harga: Rp <?php echo number_format($service['price'], 0, ',', '.'); ?></span>
                                <span class="duration-badge">Durasi: <?php echo $serviceDuration; ?></span>
                            </div>
                            <h3>Deskripsi:</h3>
                            <p><?php echo $serviceDescription; ?></p>
                            <h4>Termasuk dalam Paket:</h4>
                            <ul>
                                <?php if (!empty($serviceIncludes)): ?>
                                    <?php foreach ($serviceIncludes as $item): ?>
                                        <li><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>Tidak ada detail tambahan.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="Reservasi.php?service_id=<?php echo htmlspecialchars($serviceId); ?>" class="select-button">Pilih Paket Ini</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <section class="featured-products">
            <div class="container">
                <h2>Produk Unggulan Kami</h2>
                <div class="products-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image-placeholder">
                                    <?php
                                    // Gunakan image_url dari database untuk produk
                                    $productImagePath = !empty($product['image_url']) ? $upload_dir . htmlspecialchars($product['image_url']) : 'https://placehold.co/300x200/ef6480/FFFFFF?text=Produk+Tidak+Ditemukan';
                                    ?>
                                    <img src="<?php echo $productImagePath; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/300x200/ef6480/FFFFFF?text=Produk+Tidak+Ditemukan';">
                                </div>
                                <div class="product-details">
                                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($product['description'] ?? 'Deskripsi tidak tersedia.'); ?></p>
                                    <div class="product-footer">
                                        <span class="product-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></span>
                                        <a href="Keranjang.php?product_id=<?php echo htmlspecialchars($product['product_id']); ?>" class="btn-add-to-cart">Tambah ke Keranjang</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Tidak ada produk yang tersedia saat ini.</p>
                    <?php endif; ?>
                </div>
            </div>
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
