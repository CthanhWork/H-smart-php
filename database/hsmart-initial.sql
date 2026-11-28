-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: hsmart
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `hsmart`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `hsmart` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `hsmart`;

--
-- Table structure for table `account_tokens`
--

DROP TABLE IF EXISTS `account_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_tokens_token_hash_unique` (`token_hash`),
  KEY `account_tokens_user_id_type_used_at_index` (`user_id`,`type`,`used_at`),
  CONSTRAINT `account_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_tokens`
--

LOCK TABLES `account_tokens` WRITE;
/*!40000 ALTER TABLE `account_tokens` DISABLE KEYS */;
INSERT INTO `account_tokens` VALUES (17,60,'password_reset','9ea6801b96595fecb2c46b868611262cb7d4481cc0ff306322e4f51cf6fdbcc8','2026-10-15 13:12:50','2026-10-15 12:13:05','2026-10-15 12:12:50','2026-10-15 12:13:05'),(18,60,'refresh','e4cfdfa0f922593ec1bf4687bbfbfcc83163d93a75c1fe0c35004362ed5d33f5','2026-11-14 12:13:14','2026-10-15 12:13:19','2026-10-15 12:13:14','2026-10-15 12:13:19'),(19,60,'refresh','187e05f92321002ffa634b18e08453ac46a37917f073862a3905bebd495c7355','2026-11-14 12:13:25','2026-10-15 12:13:31','2026-10-15 12:13:25','2026-10-15 12:13:31'),(20,60,'refresh','6a15a1b42452e7d764b98c5aa30d43cee933f618ddf8ca2507787355b384964f','2026-11-14 12:13:38','2026-10-15 12:13:42','2026-10-15 12:13:38','2026-10-15 12:13:42'),(21,59,'refresh','d0ed931b8b12f10af1bf0b7df159084c73405edc97f20213a0b268c7e8559dd5','2026-11-14 12:17:08',NULL,'2026-10-15 12:17:08','2026-10-15 12:17:08'),(24,1,'refresh','1e004d76c433c9cb47182167d698f8883e2444ad4e3f1e3c2a18bc85faccba0c','2026-11-14 13:14:03','2026-10-15 13:14:24','2026-10-15 13:14:03','2026-10-15 13:14:24'),(25,60,'refresh','b8f06f17ada62d9fc8ae69cf25b2dd56d8b90f469b490dc020c4b258d2240c1c','2026-11-14 13:15:00',NULL,'2026-10-15 13:15:00','2026-10-15 13:15:00'),(27,60,'refresh','d808e91dacc46a274fe878f0e73cde1b42269d59c061956eef93cfbb1f8775e6','2026-11-14 13:15:57',NULL,'2026-10-15 13:15:57','2026-10-15 13:15:57'),(29,60,'refresh','5004997320f037a6469c9d8d332685cc34008658d73c2817c600edc0e6571109','2026-11-14 13:16:51',NULL,'2026-10-15 13:16:51','2026-10-15 13:16:51'),(30,1,'refresh','1499c2a58411d528d75eab4991d5ede78e50a7323957cfcce872a3fdc8f0efce','2026-11-14 19:33:18',NULL,'2026-10-15 19:33:18','2026-10-15 19:33:18'),(31,1,'refresh','a00fc33c7fb4e832eb06ca189b4422cc525fd038c4d3b2960800ab4fc88e7264','2026-11-14 19:35:05',NULL,'2026-10-15 19:35:05','2026-10-15 19:35:05'),(32,1,'refresh','b0fb11ed696371d6a81cbb3bae1ecc290cec85c82b298ed857f21044498a968f','2026-11-14 19:36:17',NULL,'2026-10-15 19:36:17','2026-10-15 19:36:17'),(44,1,'refresh','66c67a3b9d3b19f374c6c92960be120d91fa51cf1158abd3728ae891a9566c90','2026-11-15 07:56:06',NULL,'2026-10-16 07:56:06','2026-10-16 07:56:06');
/*!40000 ALTER TABLE `account_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Điện tử','dien-tu','2026-10-15 19:32:25','2026-10-15 19:32:25'),(2,'Sách và giáo trình','sach-va-giao-trinh','2026-10-15 19:32:25','2026-10-15 19:32:25'),(3,'Đồ dùng học tập','do-dung-hoc-tap','2026-10-15 19:32:25','2026-10-15 19:32:25'),(4,'Thời trang','thoi-trang','2026-10-15 19:32:25','2026-10-15 19:32:25'),(5,'Đồ gia dụng','do-gia-dung','2026-10-15 19:32:25','2026-10-15 19:32:25'),(6,'Khác','khac','2026-10-15 19:32:25','2026-10-15 19:32:25'),(8,'QA danh mục 1792115138926 đã sửa','qa-danh-muc-1792115138926-da-sua','2026-10-16 01:45:39','2026-10-16 01:45:43');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `favorites_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `favorites_product_id_foreign` (`product_id`),
  KEY `favorites_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `favorites_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `recipient_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_recipient_id_foreign` (`recipient_id`),
  KEY `messages_thread_idx` (`product_id`,`sender_id`,`recipient_id`,`id`),
  CONSTRAINT `messages_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_recipient_id_foreign` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_distinct_users` CHECK ((`sender_id` <> `recipient_id`))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'2026_09_27_000001_add_auth_version_to_users_table',1),(3,'2026_09_30_000001_create_product_tables',1),(4,'2026_10_12_000001_create_offers_table',1),(5,'2026_10_15_000001_create_order_and_payment_tables',2),(6,'2026_10_15_000002_enforce_one_active_order_per_product',3),(7,'2026_10_16_000001_seed_product_categories',4),(8,'2026_10_16_000002_create_reviews_table',4),(9,'2026_10_16_000003_create_messages_table',4),(10,'2026_10_16_000004_add_shipping_address_to_users',5),(11,'2026_10_16_000005_create_favorites_table',6),(12,'2026_10_16_000006_add_shipment_tracking_to_orders',6);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offers`
--

DROP TABLE IF EXISTS `offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `offer_price` bigint NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `offers_seller_id_foreign` (`seller_id`),
  KEY `offers_product_id_status_index` (`product_id`,`status`),
  KEY `idx_offers_buyer_product_status` (`buyer_id`,`product_id`,`status`),
  KEY `idx_offers_product_status` (`product_id`,`status`),
  CONSTRAINT `offers_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `offers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `offers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `offers_buyer_seller_different` CHECK ((`buyer_id` <> `seller_id`)),
  CONSTRAINT `offers_price_positive` CHECK ((`offer_price` > 0)),
  CONSTRAINT `offers_status_valid` CHECK ((`status` in (_utf8mb4'pending',_utf8mb4'accepted',_utf8mb4'rejected',_utf8mb4'cancelled',_utf8mb4'expired',_utf8mb4'ordered')))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offers`
--

LOCK TABLES `offers` WRITE;
/*!40000 ALTER TABLE `offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `offer_id` bigint unsigned DEFAULT NULL,
  `product_amount` bigint NOT NULL,
  `shipping_fee` bigint NOT NULL DEFAULT '0',
  `platform_fee` bigint NOT NULL DEFAULT '0',
  `total_amount` bigint NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `shipping_address_snapshot` json NOT NULL,
  `expires_at` timestamp NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `shipping_carrier` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_product_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'pending_payment',_utf8mb4'paid',_utf8mb4'processing',_utf8mb4'shipping',_utf8mb4'completed')) then `product_id` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_active_product` (`active_product_id`),
  KEY `orders_offer_id_foreign` (`offer_id`),
  KEY `orders_buyer_id_created_at_index` (`buyer_id`,`created_at`),
  KEY `orders_seller_id_created_at_index` (`seller_id`,`created_at`),
  KEY `orders_product_id_status_index` (`product_id`,`status`),
  CONSTRAINT `orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_amounts_valid` CHECK (((`product_amount` >= 0) and (`shipping_fee` >= 0) and (`platform_fee` >= 0) and (`total_amount` = (`product_amount` + `shipping_fee`)))),
  CONSTRAINT `orders_buyer_seller_different` CHECK ((`buyer_id` <> `seller_id`)),
  CONSTRAINT `orders_status_valid` CHECK ((`status` in (_utf8mb4'pending_payment',_utf8mb4'paid',_utf8mb4'processing',_utf8mb4'shipping',_utf8mb4'completed',_utf8mb4'cancelled')))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `txn_ref` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vnpay',
  `amount` bigint NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `transaction_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_order_id_unique` (`order_id`),
  UNIQUE KEY `payments_txn_ref_unique` (`txn_ref`),
  UNIQUE KEY `payments_transaction_no_unique` (`transaction_no`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_amount_positive` CHECK ((`amount` > 0)),
  CONSTRAINT `payments_status_valid` CHECK ((`status` in (_utf8mb4'pending',_utf8mb4'paid',_utf8mb4'failed',_utf8mb4'expired')))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (12,32,'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(13,33,'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(14,34,'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(15,35,'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(16,36,'https://images.unsplash.com/photo-1690220928060-a32811e39f54?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(17,37,'https://images.unsplash.com/photo-1505468726633-0069fc52f4b9?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(18,38,'https://images.unsplash.com/photo-1540821924489-7690c70c4eac?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:39'),(19,39,'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(20,40,'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(21,41,'https://images.unsplash.com/photo-1499760824500-675daed0dafd?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(22,42,'https://images.unsplash.com/photo-1701382334837-d3a98fff1770?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(23,43,'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(24,44,'https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(25,45,'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(26,46,'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(27,47,'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(28,48,'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(29,49,'https://images.unsplash.com/photo-1687444334081-8ca04ed2a1a3?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(30,50,'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(31,51,'https://images.unsplash.com/photo-1506439773649-6e0eb8cfb237?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 03:44:40'),(32,52,'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(33,53,'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(34,54,'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(35,55,'https://images.unsplash.com/photo-1690220928060-a32811e39f54?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(36,56,'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(37,57,'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(38,58,'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(39,59,'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(40,60,'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(41,61,'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(42,62,'https://images.unsplash.com/photo-1519682337058-a94d519337bc?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(43,63,'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(44,64,'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(45,65,'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(46,66,'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(47,67,'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(48,68,'https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(49,69,'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(50,70,'https://images.unsplash.com/photo-1560343090-f0409e92791a?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:14:40'),(51,71,'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=900&q=80',0,'2026-10-16 04:16:14');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` bigint NOT NULL,
  `condition` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `negotiable` tinyint(1) NOT NULL DEFAULT '0',
  `min_price` bigint DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_seller_id_created_at_index` (`seller_id`,`created_at`),
  KEY `products_category_id_status_index` (`category_id`,`status`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_condition_valid` CHECK ((`condition` in (_utf8mb4'new',_utf8mb4'like_new',_utf8mb4'good',_utf8mb4'fair'))),
  CONSTRAINT `products_min_price_valid` CHECK (((`min_price` is null) or ((`min_price` > 0) and (`min_price` <= `price`)))),
  CONSTRAINT `products_price_positive` CHECK ((`price` > 0)),
  CONSTRAINT `products_status_valid` CHECK ((`status` in (_utf8mb4'draft',_utf8mb4'pending_review',_utf8mb4'active',_utf8mb4'reserved',_utf8mb4'sold',_utf8mb4'hidden')))
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (28,60,NULL,'QA Laptop 20261015','San pham QA phuc vu kiem thu chuc nang.',12500000,'good',1,NULL,'active',NULL,NULL,'2026-10-15 12:12:22','2026-10-15 12:12:22'),(32,65,1,'MacBook Air M1 2020 8GB/256GB','Laptop còn hoạt động tốt, pin dùng ổn cho học tập và làm việc văn phòng. Kèm sạc theo máy.',13500000,'good',1,12150000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(33,65,1,'MacBook Pro 13 inch Intel 2020','Máy phù hợp học lập trình và thiết kế cơ bản. Ngoại hình còn đẹp, có sạc.',14800000,'good',1,13320000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(34,65,1,'Laptop Dell XPS 13 9310','Laptop mỏng nhẹ, màn hình 13 inch, phù hợp mang đi học mỗi ngày. Máy đã qua sử dụng, hoạt động bình thường.',11900000,'good',1,10710000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(35,65,1,'iPad Gen 9 Wi-Fi 64GB','Máy tính bảng dùng đọc tài liệu, ghi chú và học trực tuyến. Màn hình không nứt, cảm ứng hoạt động tốt.',5900000,'like_new',1,5310000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(36,65,1,'iPhone 12 128GB','Điện thoại đã qua sử dụng, camera và Face ID hoạt động bình thường. Có thể kiểm tra trực tiếp khi nhận.',7800000,'good',1,7020000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(37,65,1,'Samsung Galaxy A54 5G 128GB','Điện thoại màn hình Super AMOLED, pin dùng tốt. Phù hợp nhu cầu học tập và giải trí.',4900000,'good',1,4410000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(38,65,1,'Tai nghe Sony WH-1000XM4','Tai nghe chống ồn, đệm tai còn tốt, kết nối Bluetooth ổn định. Có cáp sạc USB-C.',3900000,'good',1,3510000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:39','2026-10-16 03:44:39'),(39,65,1,'Tai nghe chụp tai Logitech G Pro X','Tai nghe có dây, micro hoạt động tốt, phù hợp học online và chơi game.',1450000,'like_new',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(40,65,1,'Bàn phím cơ Keychron K2','Bàn phím cơ layout gọn, kết nối Bluetooth và USB-C. Phím hoạt động ổn, đã vệ sinh trước khi đăng.',1250000,'good',1,1125000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(41,65,1,'Máy ảnh Canon EOS M50 kèm lens kit','Máy ảnh mirrorless dành cho người mới tập chụp. Bao gồm thân máy và ống kính kit.',8900000,'good',1,8010000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(42,65,1,'Máy ảnh Fujifilm X-T30','Máy ảnh nhỏ gọn, phù hợp chụp ảnh du lịch và làm nội dung. Ngoại hình có trầy nhẹ ở đáy.',14500000,'fair',1,13050000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(43,65,2,'Giáo trình Cơ sở dữ liệu','Sách phục vụ môn Cơ sở dữ liệu, nội dung còn đầy đủ. Có ghi chú bút chì ở một số trang.',85000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(44,65,2,'English Grammar in Use – Fifth Edition','Sách ngữ pháp tiếng Anh bản in, bìa và ruột còn tốt. Phù hợp tự học và ôn tập.',220000,'like_new',1,198000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(45,65,2,'Sách Toán cao cấp tập 1','Giáo trình dùng cho các học phần toán đại cương. Có một ít ghi chú ở lề trang.',65000,'fair',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(46,65,3,'Balo laptop 15.6 inch chống thấm','Balo nhiều ngăn, có ngăn đệm đựng laptop. Khóa kéo và quai đeo còn chắc chắn.',320000,'good',1,288000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(47,65,3,'Đèn bàn LED kẹp bàn','Đèn học có thể điều chỉnh góc chiếu, ánh sáng dịu, dùng nguồn USB.',180000,'like_new',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(48,65,3,'Máy tính cầm tay Casio FX-580VN X','Máy tính khoa học dùng tốt, màn hình rõ, đầy đủ nắp và nắp trượt.',430000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(49,65,4,'Giày sneaker trắng xanh size 41','Giày đã sử dụng vài lần, đế còn tốt, phù hợp đi học và đi chơi.',420000,'good',1,378000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(50,65,4,'Áo thun cotton form rộng size L','Áo cotton màu trung tính, không rách hay xù lông. Giặt sạch trước khi giao.',120000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(51,65,5,'Ghế làm việc lưng lưới','Ghế xoay dùng ở bàn học, bánh xe và cần nâng hạ hoạt động bình thường.',650000,'good',1,585000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 03:44:40','2026-10-16 03:44:40'),(52,65,1,'Microsoft Surface Laptop Go 2 8GB/128GB','Laptop nhỏ gọn, màn hình cảm ứng, phù hợp ghi chép và làm việc văn phòng. Máy đã qua sử dụng, kèm bộ sạc.',8900000,'good',1,8010000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(53,65,1,'Lenovo ThinkPad T14 Gen 2','Laptop dòng ThinkPad, bàn phím và các cổng kết nối hoạt động bình thường. Có vài vết xước nhẹ ở vỏ.',10500000,'good',1,9450000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(54,65,1,'Samsung Galaxy Tab S7 FE 64GB','Máy tính bảng màn hình lớn, loa và cảm ứng hoạt động tốt. Có bao da, không kèm bút S Pen.',6500000,'good',1,5850000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(55,65,1,'Xiaomi Redmi Note 12 Pro 5G','Điện thoại đã qua sử dụng, camera và sạc hoạt động bình thường. Người mua có thể kiểm tra máy khi nhận.',4200000,'good',1,3780000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(56,65,1,'Apple AirPods Pro thế hệ 2','Tai nghe và hộp sạc hoạt động tốt, đã vệ sinh trước khi đăng. Có thể kết nối thử trực tiếp.',3600000,'good',1,3240000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(57,65,1,'Chuột Logitech MX Master 3S','Chuột không dây dùng Bluetooth hoặc đầu thu USB, các nút và con lăn hoạt động ổn định.',1750000,'like_new',1,1575000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(58,65,1,'Ổ cứng SSD di động Samsung T7 500GB','Ổ cứng di động USB-C, đã kiểm tra đọc ghi và nhận máy tính bình thường. Kèm cáp kết nối.',1250000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(59,65,1,'Loa Bluetooth JBL Flip 6','Loa Bluetooth nhỏ gọn, âm thanh và pin hoạt động tốt. Có cáp sạc, ngoại hình còn sạch.',1650000,'good',1,1485000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(60,65,1,'Ống kính Canon EF 50mm f/1.8 STM','Ống kính lấy nét tự động bình thường, kính không mốc. Có nắp trước và nắp sau.',1900000,'good',1,1710000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(61,65,3,'Balo Herschel Classic','Balo đi học màu đen, ngăn chính rộng, khóa kéo và quai đeo còn tốt. Có dấu hiệu sử dụng nhẹ.',480000,'good',1,432000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(62,65,2,'Dế Mèn Phiêu Lưu Ký – Tô Hoài','Sách tiếng Việt, ruột đầy đủ và không rách trang. Bìa có một vài dấu trầy nhỏ.',45000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(63,65,2,'Clean Code – Robert C. Martin','Sách tiếng Anh về viết mã dễ bảo trì, bản in đã qua sử dụng. Không thiếu trang, có ghi chú nhỏ ở một số đoạn.',260000,'good',1,234000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(64,65,3,'Sổ tay ghi chép bìa cứng A5','Sổ còn nhiều trang trắng, giấy không bị ố. Phù hợp ghi chép bài học hoặc kế hoạch cá nhân.',35000,'like_new',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(65,65,3,'Bộ bút highlight 6 màu','Bộ bút đánh dấu màu, mực còn sử dụng tốt và đầu bút chưa bị tòe.',55000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(66,65,3,'Giá đỡ laptop nhôm có thể gấp gọn','Giá đỡ điều chỉnh được độ cao, chân đế chắc chắn. Phù hợp laptop kích thước phổ biến.',180000,'like_new',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(67,65,4,'Đồng hồ Casio MTP-V001D','Đồng hồ kim dây thép, mặt kính còn tốt và chạy bình thường. Dây có vết xước nhỏ do sử dụng.',420000,'good',1,378000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(68,65,4,'Kính mát gọng đen unisex','Kính đã qua sử dụng, gọng và bản lề còn chắc. Tròng không nứt, có hộp đựng.',150000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(69,65,4,'Áo khoác denim xanh size M','Áo khoác jean còn tốt, không rách, khóa cúc đầy đủ. Đã giặt sạch trước khi đăng bán.',280000,'good',1,252000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(70,65,5,'Bình giữ nhiệt inox 500ml','Bình giữ nhiệt có nắp vặn kín, không móp và không rò nước. Đã vệ sinh sạch sẽ.',95000,'good',0,NULL,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:14:40','2026-10-16 04:14:40'),(71,65,1,'Màn hình LG 24 inch Full HD','Màn hình máy tính đã qua sử dụng, hiển thị ổn định, không sọc. Có chân đế và cáp nguồn.',1750000,'good',1,1575000,'active','TP. Hồ Chí Minh','Quận 10','2026-10-16 04:16:14','2026-10-16 04:16:14');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_order_id_unique` (`order_id`),
  KEY `reviews_buyer_id_foreign` (`buyer_id`),
  KEY `reviews_product_id_created_at_index` (`product_id`,`created_at`),
  CONSTRAINT `reviews_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_verification',
  `trust_score` decimal(4,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `auth_version` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@hsmart.local','$argon2id$v=19$m=65536,t=4,p=1$Y3pZMWQxZDBIZ1hpVnFTNA$/89UFnTIwtBJK/baqzVYz3gURz59qX2VGQQsCyuIpuQ','admin',NULL,NULL,NULL,NULL,'active',0.00,'2026-10-15 12:10:53','2026-10-15 13:14:03',0),(59,'qa.admin.20261015@hsmart.local','qa.admin.20261015@hsmart.local','$argon2id$v=19$m=65536,t=4,p=1$TW15VmFJN0ZqTncuT3dqRw$wRX8eGCf+YmzskcuanPKKMMWOw5LqPDnZb8ywwu25iM','admin','QA Admin',NULL,NULL,NULL,'active',0.00,'2026-10-15 12:12:21','2026-10-15 12:12:21',0),(60,'qa.seller.20261015@hsmart.local','qa.seller.20261015@hsmart.local','$argon2id$v=19$m=65536,t=4,p=1$bUJLd2FYVERXWmJDb1l4Zg$R2u2Cb4t9znNVcjqI5vASWDYj5/kF+YwW6fV1Y050pU','member','QA Seller',NULL,NULL,NULL,'active',0.00,'2026-10-15 12:12:21','2026-10-15 13:17:23',4),(61,'qa.buyer.20261015@hsmart.local','qa.buyer.20261015@hsmart.local','$argon2id$v=19$m=65536,t=4,p=1$YUpZT3dER0FwdTVtQWNJVw$nRjKOxUvi+74q+GdowCKPXj2/kGT2+iLqqn2vNmCxmw','member','QA Buyer',NULL,NULL,NULL,'active',0.00,'2026-10-15 12:12:21','2026-10-15 12:12:21',0),(65,'demo-seller','demo-seller@hsmart.local','$argon2id$v=19$m=65536,t=4,p=1$RWdVUTBnMGlTYVRrRGd3cA$+pM/kDG5hnNleYHvAOp0fL+s/GobBqj40oAYP+gQbg8','member','Người bán mẫu',NULL,NULL,NULL,'active',0.00,'2026-10-16 03:44:39','2026-10-16 03:44:39',0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'hsmart'
--

--
-- Dumping routines for database 'hsmart'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16  8:15:35
