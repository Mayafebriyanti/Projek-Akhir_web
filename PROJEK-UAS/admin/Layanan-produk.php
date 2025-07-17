<?php

include 'C:/xampp/htdocs/PROJEK-UAS/koneksi/config.php';

if ($conn->connect_error) {
    die("Koneksi database gagal setelah include config: " . $conn->connect_error);
}

$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $tempUserId = $_SESSION['user_id'];
    $stmt_user_role = $conn->prepare("SELECT role FROM user WHERE user_id = ?");
    if ($stmt_user_role) {
        $stmt_user_role->bind_param("i", $tempUserId);
        $stmt_user_role->execute();
        $result_user_role = $stmt_user_role->get_result();
        if ($result_user_role->num_rows > 0) {
            $user = $result_user_role->fetch_assoc();
            if ($user['role'] === 'admin') {
                $isAdmin = true;
            }
        }
        $stmt_user_role->close();
    } else {
        error_log("Gagal menyiapkan statement user role: " . $conn->error);
    }
}

if (!$isAdmin) {
    header("Location: login.php?message=access_denied_admin");
    exit();
}

$message = ''; 
$upload_dir = '../uploads/'; 

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); 
}

function uploadImage($file_input_name, $upload_dir) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('img_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_name, $upload_path)) {
                return $new_file_name; // Kembalikan hanya nama file untuk disimpan di DB
            } else {
                error_log("Gagal memindahkan file yang diunggah: " . $file_tmp_name . " ke " . $upload_path);
                return false;
            }
        } else {
            error_log("Ekstensi file tidak diizinkan: " . $file_ext);
            return false;
        }
    }
    return null; // Tidak ada file diunggah atau ada error
}


// --- Fungsi CRUD untuk Layanan (Services) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] == 'service') {
    $service_name = htmlspecialchars($_POST['service_name']);
    $price = (float)$_POST['service_price'];
    $duration = htmlspecialchars($_POST['service_duration']);
    $includes = htmlspecialchars($_POST['service_includes'] ?? '');
    $image_url = null;

    if (isset($_POST['add_service'])) {
        $stmt_check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ?");
        $stmt_check->bind_param("s", $service_name);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='error-message'>Nama layanan ini sudah ada. Mohon gunakan nama lain.</div>";
        } else {
            $uploaded_file_name = uploadImage('service_image', $upload_dir);
            if ($uploaded_file_name !== false) {
                $image_url = $uploaded_file_name;
            } elseif ($uploaded_file_name === false) {
                $message = "<div class='error-message'>Gagal mengunggah gambar layanan. Pastikan format file benar (jpg, jpeg, png, gif).</div>";
            }

            $stmt = $conn->prepare("INSERT INTO services (service_name, price, image_url, duration, includes) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) {
                error_log("Gagal menyiapkan statement tambah layanan: " . $conn->error);
                $message = "<div class='error-message'>Terjadi kesalahan sistem saat menambahkan layanan.</div>";
            } else {
                $stmt->bind_param("sdsss", $service_name, $price, $image_url, $duration, $includes);
                if ($stmt->execute()) {
                    $message = "<div class='success-message'>Layanan berhasil ditambahkan!</div>";
                } else {
                    $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    } elseif (isset($_POST['edit_service'])) {
        $service_id = (int)$_POST['service_id'];
        $existing_image_url = $_POST['existing_service_image'] ?? null;

        $stmt_check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ? AND service_id != ?");
        $stmt_check->bind_param("si", $service_name, $service_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='error-message'>Nama layanan ini sudah digunakan oleh layanan lain. Mohon gunakan nama lain.</div>";
        } else {
            $uploaded_file_name = uploadImage('service_image', $upload_dir);

            if ($uploaded_file_name !== false) {
                $image_url = $uploaded_file_name;
                if ($existing_image_url && file_exists($upload_dir . $existing_image_url)) {
                    unlink($upload_dir . $existing_image_url);
                }
            } elseif ($uploaded_file_name === null) {
                $image_url = $existing_image_url;
            } else {
                $message = "<div class='error-message'>Gagal mengunggah gambar layanan. Pastikan format file benar (jpg, jpeg, png, gif).</div>";
                $image_url = $existing_image_url;
            }

            $stmt = $conn->prepare("UPDATE services SET service_name = ?, price = ?, image_url = ?, duration = ?, includes = ? WHERE service_id = ?");
            if ($stmt === false) {
                error_log("Gagal menyiapkan statement edit layanan: " . $conn->error);
                $message = "<div class='error-message'>Terjadi kesalahan sistem saat memperbarui layanan.</div>";
            } else {
                $stmt->bind_param("sdsssi", $service_name, $price, $image_url, $duration, $includes, $service_id);
                if ($stmt->execute()) {
                    $message = "<div class='success-message'>Layanan berhasil diperbarui!</div>";
                } else {
                    $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_service'])) {
    $service_id = (int)$_GET['delete_service'];
    $image_to_delete = null;

    $stmt_get_image = $conn->prepare("SELECT image_url FROM services WHERE service_id = ?");
    if ($stmt_get_image) {
        $stmt_get_image->bind_param("i", $service_id);
        $stmt_get_image->execute();
        $result_get_image = $stmt_get_image->get_result();
        $image_row = $result_get_image->fetch_assoc();
        $image_to_delete = $image_row['image_url'] ?? null;
        $stmt_get_image->close();
    }

    $related_reservation_ids = [];
    $stmt_get_reservations = $conn->prepare("SELECT reservation_id FROM reservations WHERE service_id = ?");
    if ($stmt_get_reservations) {
        $stmt_get_reservations->bind_param("i", $service_id);
        $stmt_get_reservations->execute();
        $result_get_reservations = $stmt_get_reservations->get_result();
        while ($row = $result_get_reservations->fetch_assoc()) {
            $related_reservation_ids[] = $row['reservation_id'];
        }
        $stmt_get_reservations->close();
    } else {
        error_log("Gagal menyiapkan statement ambil reservasi terkait: " . $conn->error);
    }

    if (!empty($related_reservation_ids)) {
        $placeholders = implode(',', array_fill(0, count($related_reservation_ids), '?'));
        $types = str_repeat('i', count($related_reservation_ids));
        $stmt_delete_items = $conn->prepare("DELETE FROM reservation_items WHERE reservation_id IN ($placeholders)");
        if ($stmt_delete_items) {
            $stmt_delete_items->bind_param($types, ...$related_reservation_ids);
            $stmt_delete_items->execute();
            $stmt_delete_items->close();
        } else {
            error_log("Gagal menyiapkan statement hapus reservation_items terkait: " . $conn->error);
        }
    }

    $stmt_delete_reservations = $conn->prepare("DELETE FROM reservations WHERE service_id = ?");
    if ($stmt_delete_reservations) {
        $stmt_delete_reservations->bind_param("i", $service_id);
        $stmt_delete_reservations->execute();
        $stmt_delete_reservations->close();
    } else {
        error_log("Gagal menyiapkan statement hapus reservasi: " . $conn->error);
    }

    $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
    if ($stmt === false) {
        error_log("Gagal menyiapkan statement hapus layanan: " . $conn->error);
        $message = "<div class='error-message'>Terjadi kesalahan sistem saat menghapus layanan.</div>";
    } else {
        $stmt->bind_param("i", $service_id);
        if ($stmt->execute()) {
            if ($image_to_delete && file_exists($upload_dir . $image_to_delete)) {
                unlink($upload_dir . $image_to_delete);
            }
            $message = "<div class='success-message'>Layanan berhasil dihapus!</div>";
        } else {
            $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// --- Fungsi CRUD untuk Produk ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] == 'product') {
    $product_name = htmlspecialchars($_POST['product_name']);
    $description = htmlspecialchars($_POST['product_description'] ?? '');
    $price = (float)$_POST['product_price'];
    $stock = (int)$_POST['product_stock'];
    $image_url = null;

    if (isset($_POST['add_product'])) {
        $stmt_check = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
        $stmt_check->bind_param("s", $product_name);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='error-message'>Nama produk ini sudah ada. Mohon gunakan nama lain.</div>";
        } else {
            $uploaded_file_name = uploadImage('product_image', $upload_dir);
            if ($uploaded_file_name !== false) {
                $image_url = $uploaded_file_name;
            } elseif ($uploaded_file_name === false) {
                $message = "<div class='error-message'>Gagal mengunggah gambar produk. Pastikan format file benar (jpg, jpeg, png, gif).</div>";
            }

            $stmt = $conn->prepare("INSERT INTO products (product_name, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) {
                error_log("Gagal menyiapkan statement tambah produk: " . $conn->error);
                $message = "<div class='error-message'>Terjadi kesalahan sistem saat menambahkan produk.</div>";
            } else {
                $stmt->bind_param("ssdis", $product_name, $description, $price, $stock, $image_url);
                if ($stmt->execute()) {
                    $message = "<div class='success-message'>Produk berhasil ditambahkan!</div>";
                } else {
                    $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    } elseif (isset($_POST['edit_product'])) {
        $product_id = (int)$_POST['product_id'];
        $existing_image_url = $_POST['existing_product_image'] ?? null;

        $stmt_check = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? AND product_id != ?");
        $stmt_check->bind_param("si", $product_name, $product_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $message = "<div class='error-message'>Nama produk ini sudah digunakan oleh produk lain. Mohon gunakan nama lain.</div>";
        } else {
            $uploaded_file_name = uploadImage('product_image', $upload_dir);

            if ($uploaded_file_name !== false) {
                $image_url = $uploaded_file_name;
                if ($existing_image_url && file_exists($upload_dir . $existing_image_url)) {
                    unlink($upload_dir . $existing_image_url);
                }
            } elseif ($uploaded_file_name === null) {
                $image_url = $existing_image_url;
            } else {
                $message = "<div class='error-message'>Gagal mengunggah gambar produk. Pastikan format file benar (jpg, jpeg, png, gif).</div>";
                $image_url = $existing_image_url;
            }

            $stmt = $conn->prepare("UPDATE products SET product_name = ?, description = ?, price = ?, stock = ?, image_url = ? WHERE product_id = ?");
            if ($stmt === false) {
                error_log("Gagal menyiapkan statement edit produk: " . $conn->error);
                $message = "<div class='error-message'>Terjadi kesalahan sistem saat memperbarui produk.</div>";
            } else {
                $stmt->bind_param("ssdisi", $product_name, $description, $price, $stock, $image_url, $product_id);
                if ($stmt->execute()) {
                    $message = "<div class='success-message'>Produk berhasil diperbarui!</div>";
                } else {
                    $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['delete_product'])) {
    $product_id = (int)$_GET['delete_product'];
    $image_to_delete = null;

    $stmt_get_image = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
    if ($stmt_get_image) {
        $stmt_get_image->bind_param("i", $product_id);
        $stmt_get_image->execute();
        $result_get_image = $stmt_get_image->get_result();
        $image_row = $result_get_image->fetch_assoc();
        $image_to_delete = $image_row['image_url'] ?? null;
        $stmt_get_image->close();
    }

    $stmt_delete_items_product = $conn->prepare("DELETE FROM reservation_items WHERE product_id = ?");
    if ($stmt_delete_items_product) {
        $stmt_delete_items_product->bind_param("i", $product_id);
        $stmt_delete_items_product->execute();
        $stmt_delete_items_product->close();
    } else {
        error_log("Gagal menyiapkan statement hapus reservation_items produk terkait: " . $conn->error);
    }

    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    if ($stmt === false) {
        error_log("Gagal menyiapkan statement hapus produk: " . $conn->error);
        $message = "<div class='error-message'>Terjadi kesalahan sistem saat menghapus produk.</div>";
    } else {
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            if ($image_to_delete && file_exists($upload_dir . $image_to_delete)) {
                unlink($upload_dir . $image_to_delete);
            }
            $message = "<div class='success-message'>Produk berhasil dihapus!</div>";
        } else {
            $message = "<div class='error-message'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// --- Ambil Data Layanan ---
$services = [];
$result_services = $conn->query("SELECT service_id, service_name, price, image_url, duration, includes FROM services ORDER BY service_name ASC");
if ($result_services === false) {
    error_log("SQL Error fetching services: " . $conn->error);
} else {
    if ($result_services->num_rows > 0) {
        while($row = $result_services->fetch_assoc()) {
            $services[] = $row;
        }
    }
}


// --- Ambil Data Produk ---
$products = [];
$result_products = $conn->query("SELECT product_id, product_name, description, price, stock, image_url FROM products ORDER BY product_name ASC");
if ($result_products === false) {
    error_log("SQL Error fetching products: " . $conn->error);
} else {
    if ($result_products->num_rows > 0) {
        while($row = $result_products->fetch_assoc()) {
            $products[] = $row;
        }
    }
}


// Dapatkan data layanan/produk untuk pengeditan jika ID diberikan di URL
$edit_service = null;
if (isset($_GET['edit_service_id'])) {
    $edit_service_id = (int)$_GET['edit_service_id'];
    $stmt = $conn->prepare("SELECT service_id, service_name, price, image_url, duration, includes FROM services WHERE service_id = ?");
    $stmt->bind_param("i", $edit_service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_service = $result->fetch_assoc();
    $stmt->close();
}

$edit_product = null;
if (isset($_GET['edit_product_id'])) {
    $edit_product_id = (int)$_GET['edit_product_id'];
    $stmt = $conn->prepare("SELECT product_id, product_name, description, price, stock, image_url FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $edit_product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
    $stmt->close();
}

$conn->close(); // Tutup koneksi database di akhir skrip
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dermalux Clinik - Manajemen Layanan & Produk</title>
    <link rel="stylesheet" href="style-A.css">
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

    <main class="container">
        <section class="management-section">
            <h1 class="section-title">Manajemen Layanan & Produk</h1>

            <?php echo $message; ?>

            <!-- Form Tambah/Edit Layanan -->
            <div class="form-container">
                <h3><?php echo $edit_service ? 'Edit Layanan' : 'Tambah Layanan Baru'; ?></h3>
                <form action="Layanan-produk.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="form_type" value="service">
                    <?php if ($edit_service): ?>
                        <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($edit_service['service_id']); ?>">
                        <input type="hidden" name="existing_service_image" value="<?php echo htmlspecialchars($edit_service['image_url'] ?? ''); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="service_name">Nama Layanan:</label>
                        <input type="text" id="service_name" name="service_name" value="<?php echo htmlspecialchars($edit_service['service_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="service_price">Harga Layanan (Rp):</label>
                        <input type="number" id="service_price" name="service_price" value="<?php echo htmlspecialchars($edit_service['price'] ?? ''); ?>" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="service_duration">Durasi (cth: "90 menit"):</label>
                        <input type="text" id="service_duration" name="service_duration" value="<?php echo htmlspecialchars($edit_service['duration'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="service_includes">Termasuk dalam Paket (pisahkan dengan koma):</label>
                        <textarea id="service_includes" name="service_includes"><?php echo htmlspecialchars($edit_service['includes'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="service_image">Gambar Layanan:</label>
                        <input type="file" id="service_image" name="service_image" accept="image/*">
                        <?php if ($edit_service && !empty($edit_service['image_url'])): ?>
                            <p style="margin-top: 10px;">Gambar saat ini:</p>
                            <img src="<?php echo htmlspecialchars($upload_dir . $edit_service['image_url']); ?>" alt="Gambar Layanan" style="max-width: 150px; height: auto; border-radius: 5px; margin-top: 5px;">
                        <?php endif; ?>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" name="<?php echo $edit_service ? 'edit_service' : 'add_service'; ?>" class="btn-submit">
                            <?php echo $edit_service ? 'Perbarui Layanan' : 'Tambah Layanan'; ?>
                        </button>
                        <?php if ($edit_service): ?>
                            <a href="Layanan-produk.php" class="btn-cancel">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Layanan -->
            <h2 class="section-title" style="font-size: 2em; margin-top: 40px;">Daftar Layanan</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gambar</th>
                        <th>Nama Layanan</th>
                        <th>Harga</th>
                        <th>Durasi</th>
                        <th>Termasuk dalam Paket</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Belum ada layanan yang ditambahkan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($service['service_id']); ?></td>
                                <td data-label="Gambar">
                                    <?php if (!empty($service['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($upload_dir . $service['image_url']); ?>" alt="Gambar Layanan" style="max-width: 80px; height: auto; border-radius: 5px;">
                                    <?php else: ?>
                                        Tidak ada gambar
                                    <?php endif; ?>
                                </td>
                                <td data-label="Nama Layanan"><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td data-label="Harga">Rp <?php echo number_format($service['price'], 0, ',', '.'); ?></td>
                                <td data-label="Durasi"><?php echo htmlspecialchars($service['duration'] ?? 'N/A'); ?></td>
                                <td data-label="Termasuk dalam Paket"><?php echo htmlspecialchars(substr($service['includes'] ?? '', 0, 50)) . (strlen($service['includes'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td data-label="Aksi" class="action-links">
                                    <a href="Layanan-produk.php?edit_service_id=<?php echo htmlspecialchars($service['service_id']); ?>">Edit</a>
                                    <a href="Layanan-produk.php?delete_service=<?php echo htmlspecialchars($service['service_id']); ?>" class="delete-link">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Form Tambah/Edit Produk -->
            <div class="form-container" style="margin-top: 60px;">
                <h3><?php echo $edit_product ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h3>
                <form action="Layanan-produk.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="form_type" value="product">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($edit_product['product_id']); ?>">
                        <input type="hidden" name="existing_product_image" value="<?php echo htmlspecialchars($edit_product['image_url'] ?? ''); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="product_name">Nama Produk:</label>
                        <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($edit_product['product_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="product_description">Deskripsi Produk:</label>
                        <textarea id="product_description" name="product_description"><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="product_price">Harga Produk (Rp):</label>
                        <input type="number" id="product_price" name="product_price" value="<?php echo htmlspecialchars($edit_product['price'] ?? ''); ?>" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="product_stock">Stok:</label>
                        <input type="number" id="product_stock" name="product_stock" value="<?php echo htmlspecialchars($edit_product['stock'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="product_image">Gambar Produk:</label>
                        <input type="file" id="product_image" name="product_image" accept="image/*">
                        <?php if ($edit_product && !empty($edit_product['image_url'])): ?>
                            <p style="margin-top: 10px;">Gambar saat ini:</p>
                            <img src="<?php echo htmlspecialchars($upload_dir . $edit_product['image_url']); ?>" alt="Gambar Produk" style="max-width: 150px; height: auto; border-radius: 5px; margin-top: 5px;">
                        <?php endif; ?>
                    </div>
                    <div class="form-buttons">
                        <button type="submit" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" class="btn-submit">
                            <?php echo $edit_product ? 'Perbarui Produk' : 'Tambah Produk'; ?>
                        </button>
                        <?php if ($edit_product): ?>
                            <a href="Layanan-produk.php" class="btn-cancel">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Produk -->
            <h2 class="section-title" style="font-size: 2em; margin-top: 40px;">Daftar Produk</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Deskripsi</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Belum ada produk yang ditambahkan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td data-label="Gambar">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($upload_dir . $product['image_url']); ?>" alt="Gambar Produk" style="max-width: 80px; height: auto; border-radius: 5px;">
                                    <?php else: ?>
                                        Tidak ada gambar
                                    <?php endif; ?>
                                </td>
                                <td data-label="Nama Produk"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td data-label="Deskripsi"><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)) . (strlen($product['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td data-label="Harga">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                <td data-label="Stok"><?php echo htmlspecialchars($product['stock']); ?></td>
                                <td data-label="Aksi" class="action-links">
                                    <a href="Layanan-produk.php?edit_product_id=<?php echo htmlspecialchars($product['product_id']); ?>">Edit</a>
                                    <a href="Layanan-produk.php?delete_product=<?php echo htmlspecialchars($product['product_id']); ?>" class="delete-link">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
                &copy; 2025 Admin Panel. All rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
