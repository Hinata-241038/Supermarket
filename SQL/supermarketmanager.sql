-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: 127.0.0.1
-- 生成日時: 2026-03-11 01:31:45
-- サーバのバージョン： 10.4.32-MariaDB
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `supermarketmanager`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `categories`
--

CREATE TABLE `categories` (
  `id` int(255) NOT NULL,
  `category_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `category_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `category_label_ja` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='カテゴリー';

--
-- テーブルのデータのダンプ `categories`
--

INSERT INTO `categories` (`id`, `category_group`, `category_name`, `category_label_ja`) VALUES
(1, 'food', 'meat', '肉'),
(2, 'food', 'fish', '魚'),
(3, 'food', 'milk', '乳製品'),
(4, 'food', 'drink', '飲み物'),
(5, 'food', 'fermentation', '発酵'),
(6, 'food', 'prepared_dishes', '総菜'),
(7, 'food', 'processed_food', '加工食品'),
(8, 'food', 'snack', 'お菓子'),
(9, 'food', 'vegetables', '野菜'),
(10, 'food', 'noodles', '麺'),
(11, 'food', 'frozen_food', '冷食'),
(12, 'food', 'egg', '卵'),
(13, 'daily_necessities', 'detergent', '洗剤'),
(14, 'daily_necessities', 'roll', 'ロール'),
(15, 'daily_necessities', 'clean', '掃除');

-- --------------------------------------------------------

--
-- テーブルの構造 `disposal`
--

CREATE TABLE `disposal` (
  `id` int(255) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `expire_date` date DEFAULT NULL,
  `item_id` int(255) NOT NULL,
  `disposal_quantity` int(255) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `disposal_date` date NOT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `disposal`
--

INSERT INTO `disposal` (`id`, `stock_id`, `expire_date`, `item_id`, `disposal_quantity`, `reason`, `disposal_date`, `created_at`) VALUES
(1, 0, NULL, 1, 4, '廃棄', '2026-01-20', '2026-01-20'),
(5, 8, '2028-06-22', 3, 10, '手動廃棄', '2026-03-10', '2026-03-10'),
(6, 7, '2028-10-18', 1, 12, '手動廃棄', '2026-03-10', '2026-03-10'),
(7, 9, '2028-11-16', 2, 20, '手動廃棄', '2026-03-10', '2026-03-10'),
(8, 39, '2026-03-11', 34, 10, '手動廃棄', '2026-03-10', '2026-03-10');

-- --------------------------------------------------------

--
-- テーブルの構造 `items`
--

CREATE TABLE `items` (
  `id` int(255) NOT NULL,
  `jan_code` varchar(13) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category_id` int(255) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `created_at` date NOT NULL,
  `updated_at` date NOT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `reorder_point` int(11) NOT NULL DEFAULT 5,
  `is_limited` tinyint(1) NOT NULL DEFAULT 0 COMMENT '期間限定フラグ',
  `limited_start` date DEFAULT NULL COMMENT '販売開始日',
  `limited_end` date DEFAULT NULL COMMENT '販売終了日'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品';

--
-- テーブルのデータのダンプ `items`
--

INSERT INTO `items` (`id`, `jan_code`, `item_name`, `category_id`, `unit`, `supplier`, `created_at`, `updated_at`, `price`, `reorder_point`, `is_limited`, `limited_start`, `limited_end`) VALUES
(1, '123456789', 'キャベツ', 9, '個', 'トライアル', '2026-01-20', '2026-02-12', 110, 5, 0, NULL, NULL),
(2, '9087654321', 'コーラ', 4, '本', 'ダイソー', '2026-02-05', '2026-02-16', 150, 5, 0, NULL, NULL),
(3, '111111111', 'キノコの里', 8, '個', 'イオン', '2026-02-10', '2026-02-16', 175, 5, 0, NULL, NULL),
(4, '4901234567894', '春限定いちご大福', 8, '個', '山崎製パン', '2026-03-10', '2026-03-10', 198, 5, 1, NULL, NULL),
(5, '4901234567887', '夏限定スイカゼリー', 8, '個', '井村屋', '2026-03-10', '2026-03-10', 158, 5, 1, NULL, NULL),
(6, '4901234567870', '490123456787', 7, '個', 'グリコ', '2026-03-10', '2026-03-10', 178, 5, 1, NULL, NULL),
(7, '4901234567863', '冬限定チョコまん', 7, '個', '井村屋', '2026-03-10', '2026-03-10', 160, 5, 1, NULL, NULL),
(8, '4901234567856', '春限定桜もち', 7, '袋', 'ブルボン', '2026-03-10', '2026-03-10', 298, 5, 1, NULL, NULL),
(9, '4901234567849', 'ハロウィンクッキー', 8, '袋', 'ブルボン', '2026-03-10', '2026-03-10', 298, 5, 1, NULL, NULL),
(10, '4901234567832', 'クリスマスチョコアソート', 8, '箱', '明治', '2026-03-10', '2026-03-10', 498, 5, 1, NULL, NULL),
(11, '4901234567825', '夏限定ラムネ味アイス', 7, '本', '森永', '2026-03-10', '2026-03-10', 140, 5, 1, NULL, NULL),
(12, '4901234567818', '冬限定濃厚ミルクココア', 4, '本', 'コラコーラ', '2026-03-10', '2026-03-10', 178, 5, 1, NULL, NULL),
(13, '4901234567801', '秋限定パンプキンスープ', 6, '袋', '味の素', '2026-03-10', '2026-03-10', 320, 5, 1, NULL, NULL),
(14, '4912345678010', '手作り鮭おにぎり', 6, '個', 'デリカフーズ', '2026-03-10', '2026-03-10', 150, 5, 0, NULL, NULL),
(15, '4912345678027', '手作り梅おにぎり', 6, '個', 'デリカフーズ', '2026-03-10', '2026-03-10', 150, 5, 0, NULL, NULL),
(16, '4912345678034', 'ミックスサンド', 6, 'パック', '山崎製パン', '2026-03-10', '2026-03-10', 320, 5, 0, NULL, NULL),
(17, '4912345678041', 'ポテトサラダ', 6, 'パック', '日本ハム', '2026-03-10', '2026-03-10', 280, 5, 0, NULL, NULL),
(18, '4912345678058', '若鶏の唐揚げ', 6, 'パック', 'ニチレイ', '2026-03-10', '2026-03-10', 398, 5, 0, NULL, NULL),
(19, '4912345678065', '生寿司盛り合わせ', 2, 'パック', 'マルハニチロ', '2026-03-10', '2026-03-10', 780, 5, 0, NULL, NULL),
(20, '4912345678072', 'カットフルーツ盛り合わせ', 9, 'パック', 'フルーツ山田', '2026-03-10', '2026-03-10', 420, 5, 0, NULL, NULL),
(21, '4912345678089', '生クリームショートケーキ', 7, '個', '不二家', '2026-03-10', '2026-03-10', 450, 5, 0, NULL, NULL),
(22, '4912345678096', '手作りハンバーグ弁当', 6, '個', 'デリカフーズ', '2026-03-10', '2026-03-10', 520, 5, 0, NULL, NULL),
(23, '4912345678102', '海鮮ちらし寿司', 2, 'パック', 'マルハニチロ', '2026-03-10', '2026-03-10', 680, 5, 0, NULL, NULL),
(24, '4901345678212', '食パン6枚切り', 5, '袋', '山崎製パン', '2026-03-10', '2026-03-10', 198, 5, 0, NULL, NULL),
(25, '4901345678229', 'カップラーメンしょうゆ', 10, '個', '日清商品', '2026-03-10', '2026-03-10', 168, 5, 0, NULL, NULL),
(26, '4901345678236', 'チョコレートバー', 8, '本', 'カルビー', '2026-03-10', '2026-03-10', 128, 5, 0, NULL, NULL),
(27, '4901345678236', 'ポテトチップスうすしお', 8, '袋', 'カルビー', '2026-03-10', '2026-03-10', 158, 5, 0, NULL, NULL),
(28, '4901345678250', '缶コーヒー微糖', 4, '本', '明治', '2026-03-10', '2026-03-10', 120, 5, 0, NULL, NULL),
(29, '4901345678274', 'マヨネーズ', 7, '本', 'キューピー', '2026-03-10', '2026-03-10', 278, 5, 0, NULL, NULL),
(30, '4901345678267', 'トマトケチャップ', 7, '本', 'カゴメ', '2026-03-10', '2026-03-10', 298, 5, 0, NULL, NULL),
(31, '4901345678281', '冷凍チャーハン', 11, '袋', '味の素', '2026-03-10', '2026-03-10', 328, 5, 0, NULL, NULL),
(32, '4901345678298', 'レトルトカレー', 7, '箱', 'ハウス食品', '2026-03-10', '2026-03-10', 220, 5, 0, NULL, NULL),
(33, '4901345678304', 'ビスケット', 8, '箱', 'ブルボン', '2026-03-10', '2026-03-10', 198, 5, 0, NULL, NULL),
(34, '1234567890104', 'はくさい', 9, '個', 'トライアル', '2026-03-10', '2026-03-10', 120, 5, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- テーブルの構造 `orders`
--

CREATE TABLE `orders` (
  `id` int(255) NOT NULL,
  `item_id` int(255) NOT NULL,
  `order_quantity` int(255) NOT NULL,
  `order_date` date NOT NULL,
  `status` int(255) NOT NULL,
  `created_at` date NOT NULL,
  `updated_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `orders`
--

INSERT INTO `orders` (`id`, `item_id`, `order_quantity`, `order_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 4, '2026-01-28', 1, '2026-01-13', '2026-01-13'),
(2, 1, 6, '2026-01-14', 1, '2026-01-13', '2026-02-02'),
(3, 1, 6, '2026-01-13', 1, '2026-01-13', '2026-02-03'),
(4, 1, 11, '2026-01-20', 1, '2026-01-20', '2026-01-20'),
(5, 2, 20, '2026-02-13', 1, '2026-02-05', '2026-02-05'),
(6, 3, 10, '2026-02-11', 1, '2026-02-10', '2026-02-10'),
(7, 4, 32, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(8, 5, 24, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(9, 6, 23, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(10, 7, 31, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(11, 8, 24, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(12, 9, 32, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(13, 10, 32, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(14, 11, 27, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(15, 12, 36, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(16, 13, 41, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(17, 14, 36, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(18, 15, 42, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(19, 16, 32, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(20, 17, 34, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(21, 18, 32, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(22, 19, 38, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(23, 20, 30, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(24, 21, 25, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(25, 22, 20, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(26, 23, 20, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(27, 24, 31, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(28, 25, 25, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(29, 26, 30, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(30, 26, 25, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(31, 28, 30, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(32, 29, 37, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(33, 30, 36, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(34, 31, 37, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(35, 32, 36, '2026-03-10', 1, '2026-03-10', '2026-03-10'),
(36, 33, 42, '2026-03-10', 0, '2026-03-10', '2026-03-10'),
(37, 34, 10, '2026-03-10', 1, '2026-03-10', '2026-03-10');

-- --------------------------------------------------------

--
-- テーブルの構造 `stock`
--

CREATE TABLE `stock` (
  `id` int(255) NOT NULL,
  `item_id` int(255) NOT NULL,
  `quantity` int(255) NOT NULL,
  `consume_date` date DEFAULT NULL,
  `best_before_date` date DEFAULT NULL,
  `expire_date` date NOT NULL,
  `created_at` date NOT NULL,
  `updated_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `stock`
--

INSERT INTO `stock` (`id`, `item_id`, `quantity`, `consume_date`, `best_before_date`, `expire_date`, `created_at`, `updated_at`) VALUES
(10, 4, 32, '2026-03-31', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(11, 5, 24, '2026-04-02', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(12, 6, 23, '2026-03-20', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(13, 7, 31, '2026-04-08', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(14, 8, 24, NULL, '2026-04-07', '0000-00-00', '2026-03-10', '2026-03-10'),
(15, 9, 32, NULL, '2026-04-07', '0000-00-00', '2026-03-10', '2026-03-10'),
(16, 10, 32, NULL, '2026-04-07', '0000-00-00', '2026-03-10', '2026-03-10'),
(17, 11, 27, NULL, '2026-04-06', '0000-00-00', '2026-03-10', '2026-03-10'),
(18, 12, 36, '2026-04-07', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(19, 13, 41, NULL, '2026-04-01', '0000-00-00', '2026-03-10', '2026-03-10'),
(20, 14, 36, '2026-04-11', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(21, 15, 42, '2026-04-21', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(22, 16, 32, '2026-04-21', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(23, 17, 34, '2026-04-27', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(24, 18, 32, '2026-03-30', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(25, 19, 38, '2026-04-11', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(26, 20, 30, '2026-05-08', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(27, 21, 25, '2026-05-04', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(28, 22, 20, '2026-05-01', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(29, 23, 20, '2026-04-27', NULL, '0000-00-00', '2026-03-10', '2026-03-10'),
(30, 24, 31, NULL, '2026-04-06', '0000-00-00', '2026-03-10', '2026-03-10'),
(31, 25, 25, NULL, '2026-04-11', '0000-00-00', '2026-03-10', '2026-03-10'),
(32, 26, 30, NULL, '2026-04-29', '0000-00-00', '2026-03-10', '2026-03-10'),
(33, 26, 25, NULL, '2026-04-29', '0000-00-00', '2026-03-10', '2026-03-10'),
(34, 28, 30, NULL, '2026-04-27', '0000-00-00', '2026-03-10', '2026-03-10'),
(35, 29, 37, NULL, '2026-03-31', '0000-00-00', '2026-03-10', '2026-03-10'),
(36, 30, 36, NULL, '2026-04-23', '0000-00-00', '2026-03-10', '2026-03-10'),
(37, 31, 37, NULL, '2026-05-02', '0000-00-00', '2026-03-10', '2026-03-10'),
(38, 32, 36, NULL, '2026-05-04', '0000-00-00', '2026-03-10', '2026-03-10');

-- --------------------------------------------------------

--
-- テーブルの構造 `users`
--

CREATE TABLE `users` (
  `id` int(6) NOT NULL,
  `login_id` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('mng','fte','ptj') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ログイン';

--
-- テーブルのデータのダンプ `users`
--

INSERT INTO `users` (`id`, `login_id`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(8, 'mng110', '$2y$10$XX3FLsF4.xjWttJU1NUTo.GR/QWZY2lm4G89y5cXYQlFLYk93Xlgu', 'mng', '2026-02-03 15:13:10', '2026-02-16 13:55:01'),
(13, 'mng3', '$2y$10$mD4.pSZK4dy.kLJERSlqoepBmD55z3nv55sYKSBAIpc11lDxQPi4K', 'mng', '2026-02-10 15:13:58', '2026-02-16 13:31:22'),
(15, 'ptj0424', '$2y$10$oRsxjfnc0ZKFvo0bS5mHC.49hSvPB6AdpP9qtLC//N22AsG9pwLxm', 'ptj', '2026-02-12 10:28:08', '2026-02-13 14:00:47'),
(19, 'mng01', '$2y$10$h6U8Dc.HfTcnU0AXt9hOIOoV7qsAdj6Q8JdRFIcbBwbD15CJOWaoi', 'mng', '2026-03-10 09:27:00', '2026-03-10 09:27:00'),
(20, 'ptj01', '$2y$10$jN5/c7z4/zRXfnLzO0657u/3621dZtrwElXIZkbWSh69Sfb0EN2M6', 'ptj', '2026-03-10 09:29:13', '2026-03-10 09:29:13'),
(21, 'fte01', '$2y$10$MGZeO/xgfN/6lcZgJMYKbu1Z3ispGT2FeJnF1BtGNvb0qI2J03UsW', 'fte', '2026-03-10 10:59:21', '2026-03-10 11:10:51');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `disposal`
--
ALTER TABLE `disposal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_disposal_stock_id` (`stock_id`),
  ADD KEY `idx_disposal_item_id` (`item_id`);

--
-- テーブルのインデックス `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_items_limited` (`is_limited`,`limited_end`);

--
-- テーブルのインデックス `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- テーブルのインデックス `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- テーブルのインデックス `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- テーブルの AUTO_INCREMENT `disposal`
--
ALTER TABLE `disposal`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- テーブルの AUTO_INCREMENT `items`
--
ALTER TABLE `items`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- テーブルの AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- テーブルの AUTO_INCREMENT `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- テーブルの AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
