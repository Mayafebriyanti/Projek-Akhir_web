<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../koneksi/config.php'; // Cukup panggil sekali

$error_message = "";
$success_message = "";

// Bagian ini menangani pesan sukses dari registrasi (redirect dari register.php)
if (isset($_GET['registration']) && $_GET['registration'] == 'success') {
    $success_message = "Registrasi berhasil! Silakan login.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Memeriksa apakah kunci 'name' dan 'password' ada di $_POST
    if (isset($_POST['name']) && isset($_POST['password'])) {
        $name_input = trim(htmlspecialchars($_POST['name'])); // Mengambil input 'name' dari form
        $password = trim(htmlspecialchars($_POST['password']));

        if (empty($name_input) || empty($password)) {
            $error_message = "Nama Pengguna dan password tidak boleh kosong.";
        } else {
            // SELECT query untuk mencari pengguna berdasarkan 'name' (nama pengguna)
            $sql = "SELECT user_id, name, password, role FROM user WHERE name = ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $name_input); // Bind input 'name'
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        // bind_result HARUS menyebutkan variabel untuk kolom yang diambil
                        $stmt->bind_result($user_id, $db_name, $hashed_password_from_db, $db_role);
                        $stmt->fetch();

                        if (password_verify($password, $hashed_password_from_db)) {
                            // Set session variables
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['name'] = $db_name;
                            $_SESSION['role'] = $db_role;

                            // --- PENGARAHAN BERDASARKAN ROLE ---
                            if ($_SESSION['role'] == 'pasien') {
                                header("Location: ../pasien/Dasboard.php"); // Arahkan ke dashboard pasien
                            } elseif ($_SESSION['role'] == 'admin') {
                                header("Location: ../admin/Dasboard-A.php"); // Arahkan ke dashboard admin
                            } elseif ($_SESSION['role'] == 'dokter') {
                                header("Location: ../dokter/Dasboard-D.php"); // Contoh nama file untuk dashboard dokter
                            } else {
                                // Opsi default jika role tidak dikenali atau kosong
                                header("Location: dashboard.php"); // Arahkan ke dashboard umum
                            }
                            exit(); // Penting: hentikan eksekusi skrip setelah redirect
                        } else {
                            $error_message = "Nama Pengguna atau password salah!"; // Pesan error generik untuk keamanan
                        }
                    } else {
                        $error_message = "Nama Pengguna atau password salah!"; // Pesan error generik untuk keamanan
                    }
                } else {
                    $error_message = "Oops! Ada yang salah. Silakan coba lagi nanti. (Error eksekusi: " . $stmt->error . ")";
                }
                $stmt->close();
            } else {
                $error_message = "Oops! Ada yang salah. Silakan coba lagi nanti. (Error prepare: " . $conn->error . ")";
            }
        }
    } else {
        // Ini akan terpicu jika 'name' atau 'password' tidak ada di POST request
        $error_message = "Data login tidak lengkap. Silakan coba lagi.";
    }
}

// Tutup koneksi database dengan aman
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dermalux Clinic</title>
    <link rel="stylesheet" href="style-login.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php
        // Menampilkan pesan sukses atau error di sini
        if (!empty($success_message)) {
            echo '<p style="color: green; text-align: center;">' . $success_message . '</p>';
        }
        if (!empty($error_message)) {
            echo '<p style="color: red; text-align: center;">' . $error_message . '</p>';
        }
        ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="name">Nama Pengguna:</label>
                <input type="text" id="name" name="name" required
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
        <div class="form-toggle">
            <p>Belum punya akun? <a href="register.php">Daftar disini</a></p>
        </div>
        <div class="back-to-home">
            <a href="../index.php">&larr;</a> </div>
    </div>
</body>
</html>