-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-01-10 18:03:17
-- 服务器版本： 5.7.44-log
-- PHP 版本： 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 表：site_home_templates
-- --------------------------------------------------------
DROP TABLE IF EXISTS `site_home_templates`;
CREATE TABLE `site_home_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '模板名称',
  `folder` varchar(50) NOT NULL COMMENT '模板文件夹名',
  `is_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否激活',
  `thumbnail` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `description` varchar(255) DEFAULT NULL COMMENT '模板描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `folder` (`folder`),
  KEY `idx_is_active` (`is_active`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `site_home_templates` (`id`, `name`, `folder`, `is_active`, `thumbnail`, `description`, `created_at`, `updated_at`) VALUES
(1, '默认首页模板', 'default', 0, NULL, '系统默认首页模板', '2026-01-10 11:22:49', '2026-01-10 04:14:27'),
(2, 'v1模板', 'v1', 1, NULL, 'v1测试模板', '2026-01-10 07:25:26', '2026-01-10 10:00:54');

-- --------------------------------------------------------
-- 表：site_user_templates
-- --------------------------------------------------------
DROP TABLE IF EXISTS `site_user_templates`;
CREATE TABLE `site_user_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '模板名称',
  `folder` varchar(50) NOT NULL COMMENT '模板文件夹名',
  `is_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否激活',
  `thumbnail` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `description` varchar(255) DEFAULT NULL COMMENT '模板描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `folder` (`folder`),
  KEY `idx_is_active` (`is_active`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `site_user_templates` (`id`, `name`, `folder`, `is_active`, `thumbnail`, `description`, `created_at`, `updated_at`) VALUES
(1, '默认用户中心模板', 'defualt', 0, NULL, '系统默认用户中心模板', '2026-01-10 11:22:49', '2026-01-10 10:07:49'),
(2, 'v1白子模板', 'v1', 1, NULL, 'v1的白子模板', '2026-01-10 09:27:03', '2026-01-10 10:07:49');

-- --------------------------------------------------------
-- 表：sl_admins
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_admins`;
CREATE TABLE `sl_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_status` (`status`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_admins` (`id`, `username`, `password`, `email`, `created_at`, `last_login`, `status`) VALUES
(1, 'admin', '123456', 'admin@163.com', '2026-01-10 10:08:40', '2026-01-10 07:28:53', 1);

-- --------------------------------------------------------
-- 表：sl_announcements
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_announcements`;
CREATE TABLE `sl_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_at` (`created_at`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_announcements` (`id`, `title`, `content`, `created_at`, `is_active`) VALUES
(1, '欢迎使用白子api', '2026-01-10 13:33:46', '2026-01-10 13:33:46', 1);

-- --------------------------------------------------------
-- 表：sl_apis
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_apis`;
CREATE TABLE `sl_apis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GET',
  `type` enum('local','remote') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remote_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parameters` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_modification` json DEFAULT NULL,
  `status` enum('normal','error','maintenance','deprecated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `total_calls` bigint(20) NOT NULL DEFAULT '0',
  `visibility` enum('public','private','balance','points') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `is_billable` tinyint(1) NOT NULL DEFAULT '0',
  `price_per_call` decimal(10,4) DEFAULT '0.0000',
  `points_per_call` int(11) NOT NULL DEFAULT '1',
  `test_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_response` text COLLATE utf8mb4_unicode_ci,
  `last_checked` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `request_example` text COLLATE utf8mb4_unicode_ci,
  `response_format` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/json',
  `response_example` text COLLATE utf8mb4_unicode_ci,
  UNIQUE KEY `endpoint` (`endpoint`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_visibility` (`visibility`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_apis` (`id`, `admin_id`, `category_id`, `name`, `description`, `endpoint`, `method`, `type`, `file_path`, `remote_url`, `parameters`, `response_modification`, `status`, `total_calls`, `visibility`, `is_billable`, `price_per_call`, `points_per_call`, `test_url`, `expected_response`, `last_checked`, `created_at`, `updated_at`, `request_example`, `response_format`, `response_example`) VALUES
(1, 1, 1, '测试', '测试', 'test', 'GET', 'local', 'API/test.php', NULL, '[{\"desc\": \"密钥（登录用户自动获取）\", \"name\": \"apikey\", \"type\": \"string\", \"required\": \"no\"}]', NULL, 'normal', 1, 'private', 0, '0.0000', 0, NULL, NULL, NULL, '2026-01-10 09:59:50', '2026-01-10 10:01:27', '', 'application/json', '');

-- --------------------------------------------------------
-- 表：sl_api_categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_api_categories`;
CREATE TABLE `sl_api_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_name` (`name`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `sl_api_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, '默认', '默认分类', '2026-01-10 12:41:08');

-- --------------------------------------------------------
-- 表：sl_api_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_api_logs`;
CREATE TABLE `sl_api_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `api_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_code` int(4) NOT NULL,
  `is_success` tinyint(1) NOT NULL,
  `billing_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'free',
  `billing_amount` decimal(10,4) DEFAULT '0.0000',
  KEY `idx_api_id` (`api_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_request_time` (`request_time`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_api_logs` (`id`, `api_id`, `user_id`, `ip_address`, `request_time`, `response_code`, `is_success`, `billing_type`, `billing_amount`) VALUES
(1, 1, 1, '223.73.10.153', '2026-01-10 10:00:18', 200, 1, 'free', 0.0000);

-- --------------------------------------------------------
-- 表：sl_billing_plans
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_billing_plans`;
CREATE TABLE `sl_billing_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(16,4) NOT NULL,
  `balance_to_add` decimal(16,4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `billing_type` enum('balance','points') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'balance',
  `points_to_add` int(11) NOT NULL DEFAULT '0',
  `is_card` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_is_active` (`is_active`),
  KEY `idx_billing_type` (`billing_type`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_billing_plans` (`id`, `name`, `description`, `price`, `balance_to_add`, `is_active`, `created_at`, `billing_type`, `points_to_add`, `is_card`, `updated_at`) VALUES
(1, '默认套餐', '默认的初始套餐', '0.0100', '0.0100', 1, '2026-01-10 12:45:02', 'balance', 0, 0, '2026-01-10 14:30:50');

-- --------------------------------------------------------
-- 表：sl_cdkeys
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_cdkeys`;
CREATE TABLE `sl_cdkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cdkey` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(16,0) NOT NULL,
  `status` enum('unused','used') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `used_by_user_id` int(11) DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('balance','points') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'balance',
  `points` int(11) NOT NULL DEFAULT '0',
  UNIQUE KEY `cdkey` (`cdkey`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_used_by_user_id` (`used_by_user_id`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_cdkeys` (`id`, `cdkey`, `balance`, `status`, `used_by_user_id`, `used_at`, `created_at`, `type`, `points`) VALUES
(1, 'A3A100A1AC3BC2A158FB1DAE8ED1322E', 10000, 'unused', NULL, NULL, '2026-01-10 09:49:09', 'balance', 0);

-- --------------------------------------------------------
-- 表：sl_feedback
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_feedback`;
CREATE TABLE `sl_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `api_id` int(11) DEFAULT NULL,
  `type` enum('api','general') COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','viewed','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_api_id` (`api_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 表：sl_friend_links（友情链接表）
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_friend_links`;
CREATE TABLE `sl_friend_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '友链网站名称',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '友链网站URL',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '网站描述',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '网站LOGO图片路径',
  `user_id` int(11) DEFAULT NULL COMMENT '申请用户ID（关联sl_users表，游客申请可为NULL）',
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '审核状态：待审核/已通过/已拒绝',
  `status_check` enum('normal','broken') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '友链健康状态：正常/异常',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否隐藏：0=显示，1=隐藏',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序权重（数字越大越靠前）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT '审核时间',
  `reviewer_id` int(11) DEFAULT NULL COMMENT '审核管理员ID（关联sl_admins表）',
  `review_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '审核备注（如拒绝原因）',
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_hidden` (`is_hidden`),
  KEY `idx_status_check` (`status_check`),
  KEY `idx_sort_order` (`sort_order`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 表：sl_orders
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_orders`;
CREATE TABLE `sl_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '支付方式（如alipay、wxpay等）',
  UNIQUE KEY `order_id` (`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_orders` (`id`, `order_id`, `user_id`, `plan_id`, `amount`, `status`, `created_at`, `paid_at`, `payment_method`) VALUES
(1, '2026011017535691008', 1, 1, '0.01', 'pending', '2026-01-10 09:53:56', NULL, '支付方式（如alipay、wxpay等）');

-- --------------------------------------------------------
-- 表：sl_settings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_settings`;
CREATE TABLE `sl_settings` (
  `setting_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_settings` (`setting_key`, `setting_value`) VALUES
('allow_registration', '1'),
('allow_temp_key', '0'),
('copyright_info', 'Copyright © 2024-2026 白子API 版权所有'),
('epay_key', 'vMmoM3sMYuxzIUsz2Bb01rrr4ZN0aUWj'),
('epay_pid', '1'),
('epay_url', 'http://8.137.14.126:3086/'),
('mail_forgot_enabled', '1'),
('mail_reg_enabled', '1'),
('mail_smtp_host', 'smtp.qq.com'),
('mail_smtp_pass', 'gbjzjbdnsfwhdhbj'),
('mail_smtp_port', '465'),
('mail_smtp_secure', 'ssl'),
('mail_smtp_user', 'admin@163.com'),
('payment_alipay_enabled', '1'),
('payment_qqpay_enabled', '0'),
('payment_wxpay_enabled', '0'),
('site_description', '一个稳定、快速、易用的高质量API服务平台'),
('site_name', '白子API'),
('temp_key_duration', '24'),
('temp_key_limit', '10');

-- --------------------------------------------------------
-- 表：sl_temp_key_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_temp_key_logs`;
CREATE TABLE `sl_temp_key_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_temp_key_logs` (`id`, `ip_address`, `created_at`) VALUES
(1, '223.73.10.153', '2026-01-10 09:51:18');

-- --------------------------------------------------------
-- 表：sl_transactions（用户交易记录表）
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_transactions`;
CREATE TABLE `sl_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'recharge:充值, payment:消费',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易描述',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态: pending, completed, failed',
  `transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '外部交易号',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_transactions` (`id`, `user_id`, `type`, `amount`, `description`, `status`, `transaction_id`, `created_at`, `updated_at`) VALUES
(26, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'completed', NULL, '2026-01-10 09:10:56', '2026-01-10 09:10:56'),
(27, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'completed', NULL, '2026-01-10 09:11:14', '2026-01-10 09:11:14'),
(28, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'completed', NULL, '2026-01-10 09:11:41', '2026-01-10 09:11:41'),
(29, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 09:45:50', '2026-01-10 09:45:50'),
(30, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 09:46:21', '2026-01-10 09:46:21'),
(31, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 09:52:19', '2026-01-10 09:52:19'),
(32, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 09:56:20', '2026-01-10 09:56:20'),
(33, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:04:28', '2026-01-10 10:04:28'),
(34, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:05:27', '2026-01-10 10:05:27'),
(35, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:25:26', '2026-01-10 10:25:26'),
(36, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:26:46', '2026-01-10 10:26:46'),
(37, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:26:55', '2026-01-10 10:26:55'),
(38, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 10:28:59', '2026-01-10 10:28:59'),
(39, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 10:29:13', '2026-01-10 10:29:13'),
(40, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:31:38', '2026-01-10 10:31:38'),
(41, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:46:12', '2026-01-10 10:46:12'),
(42, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:49:25', '2026-01-10 10:49:25'),
(43, 2, 'recharge', 10.00, '购买余额充值（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 10:52:38', '2026-01-10 10:52:38'),
(44, 2, 'recharge', 1.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 11:08:43', '2026-01-10 11:08:43'),
(45, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 11:09:46', '2026-01-10 11:09:46'),
(46, 2, 'recharge', 1.00, '购买黄金套餐（卡密，支付方式：balance）', 'paid', NULL, '2026-01-10 11:10:00', '2026-01-10 11:10:00'),
(47, 2, 'recharge', 5.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 15:13:03', '2026-01-10 15:13:03'),
(48, 2, 'recharge', 5.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 15:13:17', '2026-01-10 15:13:17'),
(49, 2, 'cdkey', 10.00, '余额卡密充值: C7085D6F261C66F4EE706C7C8EB5C43B', 'pending', NULL, '2026-01-10 10:32:21', '2026-01-10 10:32:21'),
(50, 2, 'cdkey', 1000.00, '点数卡密充值: 2421364965806380FCEEE62D8BE86CC8', 'pending', NULL, '2026-01-10 10:32:50', '2026-01-10 10:32:50'),
(51, 2, 'recharge', 5.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 22:12:37', '2026-01-10 22:12:37'),
(52, 2, 'recharge', 5.00, '购买黄金套餐（直接到账，支付方式：balance）', 'paid', NULL, '2026-01-10 22:12:50', '2026-01-10 22:12:50');

-- --------------------------------------------------------
-- 表：sl_users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sl_users`;
CREATE TABLE `sl_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_count` bigint(20) NOT NULL DEFAULT '0',
  `balance` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `points` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','banned','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NULL DEFAULT NULL,
  `call_limit` int(11) DEFAULT NULL,
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sl_users` (`id`, `username`, `password`, `email`, `api_key`, `call_count`, `balance`, `points`, `created_at`, `status`, `expires_at`, `call_limit`) VALUES
(1, 'admin', '123456', 'admin@163.com', '23f2e4dfcd2e11704b7a29973b8a347765547a32678382f1800dcd49dd3e8bcd', 1, '0.0000', 0, '2026-01-10 09:53:28', 'active', NULL, NULL);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;