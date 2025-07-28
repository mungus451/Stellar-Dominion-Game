# Stellar Dominion - A PHP & MySQL Web Game

Stellar Dominion is a persistent, turn-based, multiplayer strategy game built with PHP and MySQL. Inspired by classic browser-based empire-building games, it allows players to register, manage resources, train a military, and attack other players to plunder credits and gain experience. The game operates on a server-side cron job that processes "turns" every 10 minutes, ensuring that the game world evolves and players generate resources even while they are offline.

This project features a comprehensive Alliance system, allowing players to form groups, manage roles and permissions, share resources, and engage in forum discussions.

---

## Core Game Mechanics

### The Turn System

* The universe of Stellar Dominion is persistent and ever-evolving. Time progresses in automated **10-minute turns**.

* Each turn, every commander in the galaxy is granted a baseline of resources to fuel their ambition: **2 Attack Turns** and **1 Untrained Citizen**.

* Players also receive a foundational income of **Credits**.

### Economy & Income

* A thriving economy is the bedrock of galactic conquest. Your income is calculated with the formula: `Income = floor((5000 + (Workers * 50)) * (1 + (Wealth Points * 0.01)))`.

* **Base Income**: 5,000 credits per turn.

* **Worker Income**: Each 'Worker' unit adds 50 credits to your income per turn.

* **Wealth Proficiency**: Investing points into your 'Wealth' stat grants a cumulative 1% bonus to your total income for every point allocated.

### Unit Training

* Your population begins as 'Untrained Citizens,' which can be transformed into specialized units on the `Training` page.

* A high 'Charisma' stat reduces the credit cost of all units.

* **Unit Types**:

  * **Economic Units**: Workers generate a steady stream of credits.

  * **Military Units**: Soldiers form your attack fleet, while Guards are essential for protecting your resources.

### Combat & Plunder

* From the `attack.php` page, you can launch assaults against other commanders.

* Your 'Offense Power' is derived from your Soldiers and 'Strength' proficiency.

* The 'Defense Rating' is calculated from Guards and 'Constitution' proficiency.

* A successful attack allows you to steal a percentage of the defender's on-hand credits.

* Every battle, win or lose, grants experience points (XP) to both commanders.

### Leveling System

* As you gain experience from combat, you will level up, granting you a **Proficiency Point**.

* These points can be spent on the `levels.php` page to enhance your core stats, with each point providing a +1% bonus up to a maximum of 75.

* **Stats**:

  * **Strength**: Increases Offense Power.

  * **Constitution**: Bolsters Defense Rating.

  * **Wealth**: Increases credit income.

  * **Charisma**: Reduces the credit cost for training units.

### Alliances

* Players can create or join alliances.

* Creating an alliance costs **1,000,000 Credits**.

* Alliances feature:

  * **Role and Permission Management**: Leaders can create custom roles with specific permissions.

  * **Resource Sharing**: Members can donate to an alliance bank and transfer credits or units to each other.

  * **Alliance Structures**: Purchase structures that provide bonuses to all members.

  * **Forums**: A private forum for alliance members to communicate.

---

## 🚀 Deployment Guide

### Requirements

* An Apache web server.

* PHP version 7.4 or higher with the `mysqli` extension enabled.

* A MySQL or MariaDB database server.

### Step 1: Database Setup

1. **Create a Database**: In your MySQL server, create a new, empty database. For example:

   ```sql
   CREATE DATABASE stellar_dominion;
   ```

2. **Create a User**: Create a dedicated database user and grant it full permissions to the new database.

   ```sql
   CREATE USER 'your_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON stellar_dominion.* TO 'your_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Import the Schema**: Run the `database.sql` script (not provided, but would contain table creation statements) in your new database. This will set up all the necessary tables (`users`, `alliances`, `battle_logs`, etc.).

   *If `database.sql` is unavailable, you will need to manually create the tables based on the queries found throughout the PHP files. Key tables include `users`, `battle_logs`, `alliances`, `alliance_roles`, `alliance_applications`, `alliance_structures`, `alliance_bank_logs`, `forum_threads`, and `forum_posts`.*

### Step 2: File Configuration

1. **Upload Files**: Upload the entire `Stellar-Dominion-Game-main/Stellar-Dominion/` directory to your web server's document root (e.g., `/var/www/html/`).

2. **Configure Database Connection**: Open the `config/config.php` file and update the database credentials with the ones you created in Step 1.

   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'your_user'); // Replace with your username
   define('DB_PASSWORD', 'your_password'); // Replace with your password
   define('DB_NAME', 'stellar_dominion'); // Replace with your database name
   ```

3. **Set Server Permissions**: The application needs to be able to write to the `public/uploads/` directory to handle avatar uploads. Run the following commands from your project's root directory:

   ```bash
   mkdir -p public/uploads/avatars
   sudo chown -R www-data:www-data public/uploads
   sudo chmod -R 755 public/uploads
   ```

   *(Note: The user `www-data` is common for Debian/Ubuntu. It may be `apache` or another user on different systems.)*

### Step 3: Web Server Configuration (`.htaccess`)

Ensure your Apache server is configured to allow `.htaccess` overrides. The provided `public/.htaccess` file routes all requests through `index.php` and sets custom error pages.

```apache
# public/.htaccess
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

ErrorDocument 404 /404.php
ErrorDocument 403 /403.php
ErrorDocument 500 /500.php
```

### Step 4: Cron Job Setup

The game's turn processing relies on a script that must be run automatically every 10 minutes.

1. Log into your server via SSH and open the crontab editor:

   ```bash
   crontab -e
   ```

2. Add the following line, replacing the path with the absolute path to your `TurnProcessor.php` file:

   ```
   */10 * * * * /usr/bin/php /path/to/your/Stellar-Dominion/src/Game/TurnProcessor.php >> /path/to/your/Stellar-Dominion/src/Game/cron_log.txt 2>&1
   ```

   This command executes the turn processor script every 10 minutes and logs its output.

---

## 📂 File Structure & Purpose

### Root Directories

* **`/config/`**: Contains the database connection configuration (`config.php`).

* **`/public/`**: The web server's document root. Contains the front controller (`index.php`), assets (CSS, JS, images), and user-facing error pages.

* **`/src/`**: Contains the core application logic.

  * **`/Controllers/`**: PHP scripts that handle server-side logic, such as processing forms for attacks, training, banking, and settings updates.

  * **`/Game/`**: Core game data files and the main turn processing script.

* **`/template/`**: Contains all the HTML/PHP view files (pages and includes).

  * **`/includes/`**: Reusable components like the navigation menu and AI advisor.

  * **`/pages/`**: The individual pages of the game that the user sees.

### Key Files

* **`public/index.php`**: The **Front Controller** that routes all requests to the appropriate page or controller.

* **`src/Game/TurnProcessor.php`**: The server-side script that processes turns for all players, run by the cron job.

* **`src/Controllers/AttackController.php`**: Handles the entire battle logic from start to finish.

* **`config/config.php`**: Contains the database credentials and establishes the MySQL connection.

* **`template/includes/navigation.php`**: The reusable component for generating the main and sub-navigation menus.

* **`.gitignore`**: Specifies files and directories to be ignored by version control, such as config files and user uploads.
