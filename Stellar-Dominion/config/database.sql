-- -------------------------------------------------------------
-- Stellar Dominion - Database Schema
--
-- This script contains all the necessary SQL commands to create
-- and configure the database tables for the game.
-- -------------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `password_resets`
--
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `alliances`
--

CREATE TABLE `alliances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `tag` varchar(5) NOT NULL,
  `description` text DEFAULT NULL,
  `leader_id` int(11) NOT NULL,
  `avatar_path` varchar(255) DEFAULT 'assets/img/default_alliance.avif',
  `bank_credits` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_applications`
--

CREATE TABLE `alliance_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `alliance_id` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_pending_unique` (`user_id`, `status`),
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_invitations`
--
CREATE TABLE `alliance_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invitee_id` int(11) NOT NULL,
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitee_id` (`invitee_id`),
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_bank_logs`
--

CREATE TABLE `alliance_bank_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('deposit','withdrawal','purchase','tax','transfer_fee','loan_given','loan_repaid') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_loans`
--
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `alliance_roles`
--

CREATE TABLE `alliance_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 99,
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
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_structures`
--

CREATE TABLE `alliance_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alliance_id` int(11) NOT NULL,
  `structure_key` varchar(50) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alliance_id_structure_key` (`alliance_id`,`structure_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdraw') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `transaction_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `battle_logs`
--

CREATE TABLE `battle_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attacker_id` int(11) NOT NULL,
  `defender_id` int(11) NOT NULL,
  `attacker_name` varchar(50) NOT NULL,
  `defender_name` varchar(50) NOT NULL,
  `outcome` enum('victory','defeat') NOT NULL,
  `credits_stolen` bigint(20) NOT NULL DEFAULT 0,
  `attack_turns_used` int(11) NOT NULL,
  `attacker_damage` bigint(20) NOT NULL,
  `defender_damage` bigint(20) NOT NULL,
  `attacker_xp_gained` int(11) NOT NULL,
  `defender_xp_gained` int(11) NOT NULL,
  `guards_lost` int(11) NOT NULL DEFAULT 0,
  `structure_damage` bigint(20) NOT NULL DEFAULT 0,
  `battle_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `attacker_id` (`attacker_id`),
  KEY `defender_id` (`defender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `thread_id` (`thread_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_threads`
--

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
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

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
  `credits` bigint(20) NOT NULL DEFAULT 10000000,
  `banked_credits` bigint(20) NOT NULL DEFAULT 0,
  `untrained_citizens` int(11) NOT NULL DEFAULT 250,
  `workers` int(11) NOT NULL DEFAULT 0,
  `soldiers` int(11) NOT NULL DEFAULT 0,
  `guards` int(11) NOT NULL DEFAULT 0,
  `sentries` int(11) NOT NULL DEFAULT 0,
  `spies` int(11) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 1,
  `experience` bigint(20) NOT NULL DEFAULT 0,
  `attack_turns` int(11) NOT NULL DEFAULT 20,
  `level_up_points` int(11) NOT NULL DEFAULT 1,
  `strength_points` int(11) NOT NULL DEFAULT 0,
  `constitution_points` int(11) NOT NULL DEFAULT 0,
  `wealth_points` int(11) NOT NULL DEFAULT 0,
  `dexterity_points` int(11) NOT NULL DEFAULT 0,
  `charisma_points` int(11) NOT NULL DEFAULT 0,
  `fortification_level` int(11) NOT NULL DEFAULT 0,
  `fortification_hitpoints` bigint(20) NOT NULL DEFAULT 0,
  `offense_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `defense_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `spy_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `economy_upgrade_level` int(11) NOT NULL DEFAULT 0,
  `population_level` int(11) NOT NULL DEFAULT 0,
  `armory_level` int(11) NOT NULL DEFAULT 0,
  `net_worth` bigint(20) NOT NULL DEFAULT 0,
  `credit_rating` varchar(3) NOT NULL DEFAULT 'C',
  `avatar_path` varchar(255) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `vacation_until` timestamp NULL DEFAULT NULL,
  `deposits_today` int(11) NOT NULL DEFAULT 0,
  `last_deposit_timestamp` timestamp NULL DEFAULT NULL,
  `alliance_id` int(11) DEFAULT NULL,
  `alliance_role_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `previous_login_ip` varchar(45) DEFAULT NULL,
  `previous_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `character_name` (`character_name`),
  KEY `alliance_id` (`alliance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_security_questions`
--
CREATE TABLE `user_security_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_question_unique` (`user_id`,`question_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add a name to the wars table
ALTER TABLE `wars` ADD `name` VARCHAR(100) NOT NULL AFTER `id`;

-- Add columns for adjustable war goals
ALTER TABLE `wars` ADD `goal_credits_plundered` BIGINT(20) NOT NULL DEFAULT 0 AFTER `goal_threshold`;
ALTER TABLE `wars` ADD `goal_units_killed` INT(11) NOT NULL DEFAULT 0 AFTER `goal_credits_plundered`;
ALTER TABLE `wars` ADD `goal_structure_damage` BIGINT(20) NOT NULL DEFAULT 0 AFTER `goal_units_killed`;
ALTER TABLE `wars` ADD `goal_prestige_change` INT(11) NOT NULL DEFAULT 0 AFTER `goal_structure_damage`;

-- Add columns for MVP to the war_history table
ALTER TABLE `war_history` ADD `mvp_user_id` INT(11) NULL DEFAULT NULL AFTER `goal_text`;
ALTER TABLE `war_history` ADD `mvp_category` VARCHAR(50) NULL DEFAULT NULL AFTER `mvp_user_id`;
ALTER TABLE `war_history` ADD `mvp_value` BIGINT(20) NULL DEFAULT NULL AFTER `mvp_category`;
ALTER TABLE `war_history` ADD `mvp_character_name` VARCHAR(50) NULL DEFAULT NULL AFTER `mvp_value`;

-- Create a new table to track stats for MVP calculation
CREATE TABLE `war_battle_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `war_id` INT(11) NOT NULL,
  `battle_log_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `alliance_id` INT(11) NOT NULL,
  `prestige_gained` INT(11) NOT NULL DEFAULT 0,
  `units_killed` INT(11) NOT NULL DEFAULT 0,
  `credits_plundered` BIGINT(20) NOT NULL DEFAULT 0,
  `structure_damage` BIGINT(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `war_id` (`war_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Constraints for dumped tables
--

ALTER TABLE `alliance_applications`
  ADD CONSTRAINT `alliance_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alliance_applications_ibfk_2` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE;

ALTER TABLE `alliance_invitations`
  ADD CONSTRAINT `alliance_invitations_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alliance_invitations_ibfk_2` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alliance_invitations_ibfk_3` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `alliance_roles`
  ADD CONSTRAINT `alliance_roles_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE;

ALTER TABLE `alliance_structures`
  ADD CONSTRAINT `alliance_structures_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE;

ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `forum_threads`
  ADD CONSTRAINT `forum_threads_ibfk_1` FOREIGN KEY (`alliance_id`) REFERENCES `alliances` (`id`) ON DELETE CASCADE;
  
ALTER TABLE `user_security_questions`
  ADD CONSTRAINT `user_security_questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;