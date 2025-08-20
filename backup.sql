/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: users
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB-0+deb12u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alliance_applications`
--

DROP TABLE IF EXISTS `alliance_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `alliance_id` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_status_unique` (`user_id`,`status`),
  KEY `alliance_id` (`alliance_id`),
  KEY `idx_alliance_status` (`alliance_id`,`status`),
  CONSTRAINT `alliance_applications_ibfk_2` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_alliance` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_bank_logs`
--

DROP TABLE IF EXISTS `alliance_bank_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_bank_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('deposit','withdrawal','purchase','tax','transfer_fee') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_forum_posts`
--

DROP TABLE IF EXISTS `alliance_forum_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_forum_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_content` text NOT NULL,
  `post_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_invitations`
--

DROP TABLE IF EXISTS `alliance_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invitee_id` int(11) NOT NULL,
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitee_id` (`invitee_id`),
  KEY `alliance_id` (`alliance_id`),
  KEY `alliance_invitations_ibfk_2` (`inviter_id`),
  CONSTRAINT `alliance_invitations_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alliance_invitations_ibfk_2` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alliance_invitations_ibfk_3` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_loans`
--

DROP TABLE IF EXISTS `alliance_loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount_loaned` bigint(20) NOT NULL,
  `amount_to_repay` bigint(20) NOT NULL,
  `status` enum('pending','active','paid','denied') NOT NULL DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alliance_id` (`alliance_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_roles`
--

DROP TABLE IF EXISTS `alliance_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `order` int(11) NOT NULL COMMENT 'Lower numbers are higher rank',
  `is_deletable` tinyint(1) NOT NULL DEFAULT 1,
  `can_edit_profile` tinyint(1) NOT NULL DEFAULT 0,
  `can_approve_membership` tinyint(1) NOT NULL DEFAULT 0,
  `can_kick_members` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_roles` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_structures` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_treasury` tinyint(1) NOT NULL DEFAULT 0,
  `can_invite_members` tinyint(1) NOT NULL DEFAULT 0,
  `can_moderate_forum` tinyint(1) NOT NULL DEFAULT 0,
  `can_sticky_threads` tinyint(1) NOT NULL DEFAULT 0,
  `can_lock_threads` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete_posts` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `alliance_id` (`alliance_id`),
  CONSTRAINT `alliance_roles_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_alliance` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_structures`
--

DROP TABLE IF EXISTS `alliance_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `structure_key` varchar(50) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `alliance_id` (`alliance_id`,`structure_key`),
  CONSTRAINT `alliance_structures_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliance_structures_definitions`
--

DROP TABLE IF EXISTS `alliance_structures_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliance_structures_definitions` (
  `structure_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `cost` bigint(20) NOT NULL,
  `bonus_text` varchar(255) NOT NULL,
  `bonuses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`bonuses`)),
  PRIMARY KEY (`structure_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alliances`
--

DROP TABLE IF EXISTS `alliances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alliances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `tag` varchar(5) NOT NULL,
  `description` text DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT 'assets/img/default_alliance.png',
  `leader_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_credits` bigint(20) NOT NULL DEFAULT 0,
  `war_prestige` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `tag` (`tag`),
  KEY `leader_id` (`leader_id`),
  CONSTRAINT `alliances_ibfk_1` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bank_transactions`
--

DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdraw') NOT NULL,
  `amount` bigint(20) unsigned NOT NULL,
  `transaction_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `battle_logs`
--

DROP TABLE IF EXISTS `battle_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `battle_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attacker_id` int(11) NOT NULL,
  `defender_id` int(11) NOT NULL,
  `attacker_name` varchar(50) NOT NULL,
  `defender_name` varchar(50) NOT NULL,
  `outcome` enum('victory','defeat') NOT NULL,
  `credits_stolen` int(11) NOT NULL DEFAULT 0,
  `attack_turns_used` int(11) NOT NULL,
  `attacker_damage` int(11) NOT NULL DEFAULT 0,
  `defender_damage` int(11) NOT NULL DEFAULT 0,
  `attacker_xp_gained` int(11) NOT NULL DEFAULT 0,
  `defender_xp_gained` int(11) NOT NULL DEFAULT 0,
  `guards_lost` int(11) NOT NULL DEFAULT 0,
  `structure_damage` bigint(20) NOT NULL DEFAULT 0,
  `battle_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attacker_id` (`attacker_id`),
  KEY `defender_id` (`defender_id`),
  KEY `idx_attacker_id` (`attacker_id`),
  KEY `idx_defender_id` (`defender_id`)
) ENGINE=InnoDB AUTO_INCREMENT=276 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_recruits`
--

DROP TABLE IF EXISTS `daily_recruits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_recruits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recruiter_id` int(11) NOT NULL,
  `recruited_id` int(11) NOT NULL,
  `recruit_count` int(11) NOT NULL DEFAULT 1,
  `recruit_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recruiter_recruited_date` (`recruiter_id`,`recruited_id`,`recruit_date`),
  KEY `recruiter_id_date` (`recruiter_id`,`recruit_date`),
  KEY `daily_recruits_recruited_fk` (`recruited_id`),
  CONSTRAINT `daily_recruits_recruited_fk` FOREIGN KEY (`recruited_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_recruits_recruiter_fk` FOREIGN KEY (`recruiter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_posts`
--

DROP TABLE IF EXISTS `forum_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `thread_id` (`thread_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_thread_id` (`thread_id`),
  CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forum_threads`
--

DROP TABLE IF EXISTS `forum_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_post_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_stickied` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `alliance_id` (`alliance_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_alliance_id` (`alliance_id`),
  CONSTRAINT `forum_threads_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rivalries`
--

DROP TABLE IF EXISTS `rivalries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rivalries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance1_id` int(11) NOT NULL,
  `alliance2_id` int(11) NOT NULL,
  `heat_level` int(11) NOT NULL DEFAULT 0,
  `last_attack_date` datetime NOT NULL DEFAULT current_timestamp(),
  `a_min` int(11) GENERATED ALWAYS AS (least(`alliance1_id`,`alliance2_id`)) STORED,
  `a_max` int(11) GENERATED ALWAYS AS (greatest(`alliance1_id`,`alliance2_id`)) STORED,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rivalry_normalized` (`a_min`,`a_max`),
  KEY `idx_riv_a1` (`alliance1_id`),
  KEY `idx_riv_a2` (`alliance2_id`),
  CONSTRAINT `fk_riv_a1` FOREIGN KEY (`alliance1_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_riv_a2` FOREIGN KEY (`alliance2_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rivalries_not_self` CHECK (`alliance1_id` <> `alliance2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treaties`
--

DROP TABLE IF EXISTS `treaties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `treaties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance1_id` int(11) NOT NULL,
  `alliance2_id` int(11) NOT NULL,
  `treaty_type` enum('peace','non_aggression','mutual_defense') NOT NULL,
  `proposer_id` int(11) NOT NULL,
  `status` enum('proposed','active','expired','broken') NOT NULL DEFAULT 'proposed',
  `terms` text DEFAULT NULL,
  `expiration_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `alliance1_id` (`alliance1_id`),
  KEY `alliance2_id` (`alliance2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `unverified_users`
--

DROP TABLE IF EXISTS `unverified_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `unverified_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `character_name` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `race` varchar(50) NOT NULL,
  `class` varchar(50) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_armory`
--

DROP TABLE IF EXISTS `user_armory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_armory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_item_key` (`user_id`,`item_key`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_armory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_security_questions`
--

DROP TABLE IF EXISTS `user_security_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_security_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_question_unique` (`user_id`,`question_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `character_name` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `race` varchar(50) NOT NULL,
  `class` varchar(50) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `phone_carrier` varchar(50) DEFAULT NULL,
  `phone_verified` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_path` varchar(255) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `experience` int(11) NOT NULL DEFAULT 0,
  `credits` bigint(20) NOT NULL DEFAULT 10000000,
  `banked_credits` bigint(20) unsigned NOT NULL DEFAULT 0,
  `untrained_citizens` int(11) NOT NULL DEFAULT 250,
  `workers` int(11) NOT NULL DEFAULT 0,
  `soldiers` int(11) NOT NULL DEFAULT 0,
  `guards` int(11) NOT NULL DEFAULT 0,
  `sentries` int(11) NOT NULL DEFAULT 0,
  `spies` int(11) NOT NULL DEFAULT 0,
  `net_worth` int(11) NOT NULL DEFAULT 500,
  `war_prestige` int(11) NOT NULL DEFAULT 0,
  `credit_rating` varchar(3) NOT NULL DEFAULT 'C',
  `energy` int(11) NOT NULL DEFAULT 10,
  `attack_turns` int(11) NOT NULL DEFAULT 10,
  `level_up_points` int(11) NOT NULL DEFAULT 1,
  `strength_points` int(11) NOT NULL DEFAULT 0,
  `constitution_points` int(11) NOT NULL DEFAULT 0,
  `wealth_points` int(11) NOT NULL DEFAULT 0,
  `dexterity_points` int(11) NOT NULL DEFAULT 0,
  `charisma_points` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
  `vacation_until` datetime DEFAULT NULL,
  `deposits_today` int(3) NOT NULL DEFAULT 0,
  `last_deposit_timestamp` timestamp NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `previous_login_ip` varchar(45) DEFAULT NULL,
  `previous_login_at` timestamp NULL DEFAULT NULL,
  `fortification_level` int(11) NOT NULL DEFAULT 0,
  `fortification_hitpoints` bigint(20) NOT NULL DEFAULT 0,
  `offense_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `defense_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `spy_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `economy_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `population_level` int(11) NOT NULL DEFAULT 0,
  `armory_level` int(11) NOT NULL DEFAULT 0,
  `alliance_id` int(11) DEFAULT NULL,
  `alliance_role_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `character_name` (`character_name`),
  KEY `fk_user_role` (`alliance_role_id`),
  KEY `idx_alliance_id` (`alliance_id`),
  KEY `idx_level_experience` (`level`,`experience`),
  KEY `idx_net_worth` (`net_worth`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`alliance_role_id`) REFERENCES `alliance_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `war_battle_logs`
--

DROP TABLE IF EXISTS `war_battle_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `war_battle_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `war_id` int(11) NOT NULL,
  `battle_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `alliance_id` int(11) NOT NULL,
  `prestige_gained` int(11) NOT NULL DEFAULT 0,
  `units_killed` int(11) NOT NULL DEFAULT 0,
  `credits_plundered` bigint(20) NOT NULL DEFAULT 0,
  `structure_damage` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `war_id` (`war_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `war_history`
--

DROP TABLE IF EXISTS `war_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `war_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `war_id` int(11) NOT NULL,
  `declarer_alliance_name` varchar(50) NOT NULL,
  `declared_against_alliance_name` varchar(50) NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `outcome` varchar(255) NOT NULL,
  `casus_belli_text` varchar(255) NOT NULL,
  `goal_text` varchar(255) NOT NULL,
  `mvp_user_id` int(11) DEFAULT NULL,
  `mvp_category` varchar(50) DEFAULT NULL,
  `mvp_value` bigint(20) DEFAULT NULL,
  `mvp_character_name` varchar(50) DEFAULT NULL,
  `final_stats` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `war_id` (`war_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wars`
--

DROP TABLE IF EXISTS `wars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `declarer_alliance_id` int(11) NOT NULL,
  `declared_against_alliance_id` int(11) NOT NULL,
  `casus_belli_key` varchar(255) DEFAULT NULL,
  `casus_belli_custom` text DEFAULT NULL,
  `start_date` datetime NOT NULL DEFAULT current_timestamp(),
  `end_date` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `outcome` varchar(255) DEFAULT NULL,
  `goal_key` varchar(255) DEFAULT NULL,
  `goal_custom_label` varchar(100) DEFAULT NULL,
  `goal_metric` varchar(50) NOT NULL,
  `goal_threshold` int(11) NOT NULL,
  `goal_credits_plundered` bigint(20) NOT NULL DEFAULT 0,
  `goal_units_killed` int(11) NOT NULL DEFAULT 0,
  `goal_structure_damage` bigint(20) NOT NULL DEFAULT 0,
  `goal_prestige_change` int(11) NOT NULL DEFAULT 0,
  `goal_progress_declarer` int(11) NOT NULL DEFAULT 0,
  `goal_progress_declared_against` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `declarer_alliance_id` (`declarer_alliance_id`),
  KEY `declared_against_alliance_id` (`declared_against_alliance_id`),
  CONSTRAINT `wars_ibfk_1` FOREIGN KEY (`declarer_alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wars_ibfk_2` FOREIGN KEY (`declared_against_alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-18  4:17:13
