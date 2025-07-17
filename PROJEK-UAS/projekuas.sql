-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 15, 2025 at 05:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `projekuas`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_url` varchar(255) DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `description`, `price`, `stock`, `created_at`, `image_url`, `duration`) VALUES
(6, 'Dermalux Brightening Serum', 'Serum pencerah wajah dengan kandungan Vitamin C tinggi untuk kulit cerah dan merata.', 250000.00, 50, '2025-07-06 06:13:14', 'img_68763c7055a1a6.74510417.png', NULL),
(7, 'Dermalux Daily Sunscreen SPF 50+', 'Tabir surya ringan dengan SPF 50+ melindungi kulit dari sinar UVA/UVB\r\n', 200000.00, 34, '2025-07-06 06:49:29', 'img_68763cc10d7858.42594269.png', NULL),
(8, 'Dermalux Gentle Cleanser', 'Pembersih wajah lembut untuk semua jenis kulit, membersihkan tanpa membuat kering.\r\n', 150000.00, 45, '2025-07-06 06:52:18', 'img_68763d1256f813.74851549.png', NULL),
(9, 'Dermalux Hydrating Moisturizer', 'Pelembab wajah intensif untuk menjaga kelembaban kulit sepanjang hari.', 200000.00, 80, '2025-07-06 06:53:27', 'img_68763d2729b636.37028341.png', NULL),
(10, 'Dermalux Anti-Aging Eye Cream', 'Krim mata khusus untuk mengurangi lingkaran hitam dan kerutan di sekitar mata.\r\n', 290000.00, 56, '2025-07-06 06:54:06', 'img_68763c49af4db3.91672388.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rekamedis`
--

CREATE TABLE `rekamedis` (
  `record_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rekamedis`
--

INSERT INTO `rekamedis` (`record_id`, `reservation_id`, `doctor_id`, `notes`, `diagnosis`, `treatment_details`, `created_at`, `updated_at`) VALUES
(9, 88, 2, '', 'Merah merah diarea kulit ', 'mengunakan teknik laser yang termuka ', '2025-07-15 13:31:10', '2025-07-15 13:31:10');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('Pending','Dikonfirmasi','Selesai','Dibatalkan') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doctor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `customer_id`, `service_id`, `reservation_date`, `reservation_time`, `total_price`, `status`, `created_at`, `doctor_id`) VALUES
(88, 6, 11, '2025-07-15', '09:00:00', 900000.00, 'Selesai', '2025-07-15 13:10:44', 2),
(89, 9, 11, '2025-07-17', '15:00:00', 900000.00, 'Dikonfirmasi', '2025-07-15 13:17:00', 2),
(92, 5, 11, '2025-07-21', '11:00:00', 900000.00, 'Pending', '2025-07-15 13:53:07', 2),
(95, 10, 11, '2025-07-25', '13:00:00', 900000.00, 'Pending', '2025-07-15 15:38:53', 2);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_items`
--

CREATE TABLE `reservation_items` (
  `item_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_items`
--

INSERT INTO `reservation_items` (`item_id`, `reservation_id`, `product_name`, `quantity`, `price`) VALUES
(74, 88, 'Paket 3 (Laser Treatment)', 1, 900000.00),
(75, 89, 'Paket 3 (Laser Treatment)', 1, 900000.00),
(78, 92, 'Paket 3 (Laser Treatment)', 1, 900000.00),
(81, 95, 'Paket 3 (Laser Treatment)', 1, 900000.00);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `includes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `price`, `image_url`, `description`, `duration`, `includes`) VALUES
(9, 'Paket 1 (Facial Treatment)', 520000.00, 'img_6874ea5e862140.06196428.png', 'Facial treatment adalah serangkaian prosedur perawatan kulit wajah yang bertujuan untuk membersihkan, meremajakan, dan mengatasi masalah kulit seperti jerawat, komedo, dan penuaan.', '60 menit', 'Pembersihan Wajah Mendalam,\r\nAplikasi Serum Pencerah Vitamin C,\r\nMasker Mencerahkan Wajah,\r\nPijat Wajah Relaksasi,\r\nHydrating Toner dan Pelembab.'),
(10, 'Paket 2 (Botox n Filler)', 700000.00, 'img_6874ea79b727d1.93432540.png', 'Botox &amp;amp; Filler kami menggunakan teknologi terkini untuk merangsang produksi kolagen dan elastin, mengurangi tampilan garis halus, dan mengencangkan kulit. Rasakan kulit yang lebih muda dan elastis.', '120 menit', 'Analisis Kulit Profesional,\r\nPerawatan Mikrodermabrasi Ringan,\r\nAplikasi Serum Anti-Aging Peptida,\r\nMasker Kolagen Khusus,\r\nPijat Wajah Rejuvenasi,'),
(11, 'Paket 3 (Laser Treatment)', 900000.00, 'img_6874ea85873648.18903070.png', 'Prosedur estetika yang menggunakan sinar laser untuk memperbaiki berbagai masalah kulit. Prosedur ini sangat populer karena minim invasif, presisi tinggi, dan waktu pemulihan yang relatif cepat.', '100 menit', 'Pembersihan Wajah Anti-Bakteri,\r\nEkstraksi Komedo dan Milia,\r\nAplikasi Serum Salicylic Acid,\r\nMasker Detoksifikasi Kulit,\r\nTerapi LED Biru (Blue Light Therapy).');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('pasien','admin','dokter') DEFAULT 'pasien'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$O5eLO5GWowDe9EgHM1hyk.oTasQyFekQ0TalibQpAdFZVw3SfUCgK', 'admin'),
(2, 'dokter', 'dokter@gmail.com', '$2y$10$4GEqY4f/N8CdH2ndQuxK8eQWDuHY74DUDC9TEkO5soTFNBdl6FdSq', 'dokter'),
(5, 'mayafeb', 'mayafebriynti@gmail.com', '$2y$10$qJR67w7NQwisKIjkF/oNOuREKOwelIV1AEdiyONeOYc20/C.GloU.', 'pasien'),
(6, 'veraa', 'veraa@gmail.com', '$2y$10$l.qQ9/a0S64O4MLvWB1ziuooFuL9NGIq6nI9KocNfaIdTc0rOXAs2', 'pasien'),
(7, 'fitria', 'fitria@gmail.com', '$2y$10$FIUp/T7vp.3.AYPV9Bdtsu1CN9845tXeHUtRchznWsPaVQh51WC9C', 'pasien'),
(8, 'ayuamanda', 'ayuamanda@gmail.com', '$2y$10$rRwV5M78ajK0XxlGbaQWleTNYnjVfruhEpgUiLg.9ogjKmMY.nEcC', 'pasien'),
(9, 'nadiayu', 'nadiayu@gmail.com', '$2y$10$wU7ld22W3N4zbEKWF4FtR.wrdHgjw25lCKfe5eQP.u8G08DNIIhd6', 'pasien'),
(10, 'kapew', 'kapew@gmail.com', '$2y$10$yHmFfuB8ia.rNWb1m0au4eVooBvgST8k4/BRiGzdn.6yp5jcXRFRu', 'pasien');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `product_name` (`product_name`);

--
-- Indexes for table `rekamedis`
--
ALTER TABLE `rekamedis`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `reservation_id` (`reservation_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `fk_reservations_doctor` (`doctor_id`);

--
-- Indexes for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `rekamedis`
--
ALTER TABLE `rekamedis`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `rekamedis`
--
ALTER TABLE `rekamedis`
  ADD CONSTRAINT `rekamedis_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rekamedis_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD CONSTRAINT `reservation_items_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
