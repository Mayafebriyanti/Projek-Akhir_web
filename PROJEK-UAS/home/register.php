<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// Pastikan path ini benar dan file config.php sudah berisi detail koneksi database Anda
require_once '../koneksi/config.php'; // Cukup panggil sekali

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Menggunakan 'name' sesuai dengan kolom database 'name'
    $name = trim(htmlspecialchars($_POST['name'])); // Mengambil dari input 'name'
    $email = trim(htmlspecialchars($_POST['email']));
    $password = trim(htmlspecialchars($_POST['password']));
    $confirm_password = trim(htmlspecialchars($_POST['confirm_password']));
    // Kolom 'phone' tidak ada di tabel 'user', jadi tidak diproses di sini

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua kolom harus diisi.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'pasien'; // Set peran default untuk pengguna baru

        // Periksa duplikasi email (karena email unik di tabel user)
        $check_sql = "SELECT user_id FROM user WHERE email = ?";
        if ($stmt_check = $conn->prepare($check_sql)) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "Email sudah terdaftar. Silakan gunakan email lain.";
            } else {
                // Insert data pengguna baru ke tabel 'user'
                $insert_sql = "INSERT INTO user (name, email, password, role) VALUES (?, ?, ?, ?)";

                if ($stmt_insert = $conn->prepare($insert_sql)) {
                    $stmt_insert->bind_param("ssss", $name, $email, $hashed_password, $role);

                    if ($stmt_insert->execute()) {
                        header("Location: login.php?registration=success");
                        exit();
                    } else {
                        $error_message = "Terjadi kesalahan saat registrasi. Silakan coba lagi. (Error: " . $stmt_insert->error . ")";
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = "Terjadi kesalahan sistem. Silakan coba lagi nanti. (Error prepare insert: " . $conn->error . ")";
                }
            }
            $stmt_check->close();
        } else {
            $error_message = "Terjadi kesalahan sistem. Silakan coba lagi nanti. (Error prepare check: " . $conn->error . ")";
        }
    }
}

// Tutup koneksi database (tempatkan di sini agar selalu tertutup jika tidak ada exit() sebelumnya)
if (isset($conn) && $conn instanceof mysqli) { // Pastikan $conn ada dan objek mysqli sebelum menutup
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Glow Beauty Clinic</title>
    <link rel="stylesheet" href="style-login.css">
</head>
<body>
    <div class="register-container">
        <h2>Daftar Akun Baru</h2>
        <?php
        // Menampilkan pesan error jika ada
        if (!empty($error_message)) {
            echo '<p style="color: red;">' . $error_message . '</p>';
        }
        ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="reg_name">Nama Lengkap:</label>
                <input type="text" id="reg_name" name="name" required
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="reg_email">Email:</label>
                <input type="email" id="reg_email" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="reg_password">Password:</label>
                <input type="password" id="reg_password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="register-submit-button">Daftar</button>
        </form>
        <p class="back-to-login">Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>
</body>
</html>