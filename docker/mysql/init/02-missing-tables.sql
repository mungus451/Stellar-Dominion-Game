-- -------------------------------------------------------------
-- Stellar Dominion - Missing Tables Schema
--
-- This script adds tables that are referenced in the application
-- but missing from the main database.sql schema.
-- This file will be executed before the main database.sql schema.
-- -------------------------------------------------------------

USE users;

-- --------------------------------------------------------

--
-- Table structure for table `structures`
--

CREATE TABLE `structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `structure_type` varchar(50) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `hitpoints` bigint(20) NOT NULL DEFAULT 0,
  `max_hitpoints` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `structure_type` (`structure_type`),
  UNIQUE KEY `user_structure_unique` (`user_id`, `structure_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_armory`
--

CREATE TABLE `user_armory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `attack_bonus` int(11) NOT NULL DEFAULT 0,
  `defense_bonus` int(11) NOT NULL DEFAULT 0,
  `durability` int(11) NOT NULL DEFAULT 100,
  `max_durability` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `item_key` (`item_key`),
  UNIQUE KEY `user_item_unique` (`user_id`, `item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_attributes`
-- Additional table for user stats that might be referenced
--

CREATE TABLE `user_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `attribute_name` varchar(50) NOT NULL,
  `attribute_value` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_attribute_unique` (`user_id`, `attribute_name`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alliance_structures_definitions`
-- Defines the types of structures that alliances can build
--

CREATE TABLE `alliance_structures_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `structure_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_cost` bigint(20) NOT NULL DEFAULT 0,
  `cost_multiplier` decimal(5,2) NOT NULL DEFAULT 1.5,
  `max_level` int(11) NOT NULL DEFAULT 10,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `bonuses` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `structure_key` (`structure_key`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Add foreign key constraints
--

ALTER TABLE `structures`
  ADD CONSTRAINT `structures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_armory`
  ADD CONSTRAINT `user_armory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_attributes`
  ADD CONSTRAINT `user_attributes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Documentation for structure and item types
--

-- Structure types that can be built:
-- barracks: Train military units
-- armory: Store weapons and equipment  
-- treasury: Secure credit storage
-- walls: Defensive fortifications
-- radar: Early warning systems
-- factories: Resource production
-- research_lab: Technology development
-- hospital: Unit healing and recovery

-- Alliance structure definitions:
-- These define the types of structures alliances can build
-- Categories: military, economic, defensive, research, social
-- Each structure has base cost, cost multiplier per level, and max level
-- Bonuses and requirements are stored as JSON or text

-- User armory item keys that can be stored:
-- weapon_sword: Melee weapons
-- weapon_gun: Ranged weapons
-- armor_shield: Defense equipment
-- armor_suit: Body protection
-- consumable_potion: Healing items
-- consumable_bomb: Attack items
-- equipment_scanner: Utility tools
-- equipment_repair: Maintenance tools
