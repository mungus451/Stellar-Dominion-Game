# Stellar Dominion - A PHP & MySQL Web Game

Stellar Dominion is a persistent, turn-based, multiplayer strategy game built with PHP and MySQL. Inspired by classic browser-based empire-building games, it allows players to register, manage resources, train a military, and attack other players to plunder credits and gain experience. The game operates on a server-side cron job that processes "turns" every 10 minutes, ensuring that the game world evolves and players generate resources even while they are offline.

This project features a comprehensive Alliance system, allowing players to form groups, manage roles and permissions, share resources, purchase collaborative structures, and engage in private forum discussions.

---

## Core Game Mechanics

### The Turn System

* The universe of Stellar Dominion is persistent and ever-evolving. Time progresses in automated **10-minute turns**.
* Each turn, every commander in the galaxy is granted a baseline of resources: **2 Attack Turns** and a base of **1 Untrained Citizen** per turn, which can be increased with upgrades.
* Players also receive a foundational income of **Credits**.

### Economy & Income

* A thriving economy is the bedrock of galactic conquest. Your income is calculated based on a base income, the number of workers you have, and various percentage-based bonuses from stats and upgrades.
* **Base Income**: 5,000 credits per turn.
* **Worker Income**: Each 'Worker' unit adds 50 credits to your income per turn.
* **Wealth Proficiency**: Investing points into your 'Wealth' stat grants a cumulative 1% bonus to your total income for every point allocated.
* **Economic Upgrades**: Building structures like the 'Trade Hub' provides stacking percentage-based bonuses to your income.
* **Alliance Bonuses**: Being a member of an alliance provides a base credit bonus and can be further enhanced by building alliance structures like the 'Command Nexus'.

### Unit Training & Disbanding

* Your population begins as 'Untrained Citizens,' which can be transformed into specialized units on the `Training` page.
* A high 'Charisma' stat reduces the credit cost of all units.
* **Unit Types**:
    * **Workers**: Generate a steady stream of credits.
    * **Soldiers**: Form your attack fleet.
    * **Guards**: Protect your resources.
    * **Sentries**: Increase your fortification strength.
    * **Spies**: Enhance infiltration capabilities.
* Units can be disbanded to reclaim a portion of their cost (75%) and return them to the 'Untrained Citizens' pool.

### Combat & Plunder

* From the `Attack` page, you can launch assaults against other commanders.
* Your 'Offense Power' is derived from your Soldiers, their equipment from the Armory, your 'Strength' proficiency, and offense-boosting structures.
* The 'Defense Rating' is calculated from Guards, their Armory equipment, 'Constitution' proficiency, and defense-boosting structures.
* A successful attack allows you to steal a percentage of the defender's on-hand (not banked) credits. The amount stolen increases with the number of attack turns used.
* A 10% tax on all plunder is automatically contributed to the attacker's alliance bank.
* Every battle, win or lose, grants experience points (XP) to both commanders.

### Leveling & Proficiency System

* As you gain experience from combat, you will level up, granting you a **Proficiency Point**.
* These points can be spent on the `Levels` page to enhance your core stats, with each point providing a +1% bonus up to a maximum of 75.
* **Stats**:
    * **Strength**: Increases Offense Power.
    * **Constitution**: Bolsters Defense Rating.
    * **Wealth**: Increases credit income.
    * **Dexterity**: Improves Sentry and Spy effectiveness.
    * **Charisma**: Reduces the credit cost for training units and purchasing items/structures.

### Alliances

* Players can create or join alliances.
* Creating an alliance costs **1,000,000 Credits**.
* Alliances feature:
    * **Role and Permission Management**: Leaders can create custom roles with specific permissions for managing members, editing the alliance profile, approving applications, and moderating the forum.
    * **Shared Bank & Resource Transfers**: Members can donate to an alliance bank. A 2% fee on member-to-member transfers of credits and units is contributed to the bank.
    * **Alliance Structures**: Purchase structures that provide passive global bonuses to all members, such as increased income, offense/defense power, and citizen growth.
    * **Forums**: A private, built-in forum for alliance members to create threads and posts for strategic discussions.
    * **Application System**: Players can apply to join alliances, and members with permission can approve or deny these applications.

### Armory & Equipment

* The `Armory` allows players to purchase tiered equipment for their units.
* Access to higher tiers of weapons and armor is unlocked by upgrading the 'Armory' structure on the `Structures` page.
* Items have prerequisites; for instance, you must own the Tier 1 item before you can purchase the Tier 2 item in the same category.
* Equipment provides direct bonuses to attack or defense power.

---

## 🚀 Deployment Guide

### Requirements

* An Apache web server.
* PHP version 7.4 or higher with the `mysqli` extension enabled.
* A MySQL or MariaDB database server.

### Step 1: Database Setup

1.  **Create a Database**: In your MySQL server, create a new database (e.g., `users`).
2.  **Create a User**: Create a dedicated database user and grant it full permissions to the new database.
3.  **Import the Schema**: Run the `config/database.sql` script in your new database. This will set up all the necessary tables (`users`, `alliances`, `battle_logs`, etc.).

### Step 2: File Configuration

1.  **Upload Files**: Upload the entire `Stellar-Dominion` directory to your web server's document root.
2.  **Configure Database Connection**: Open the `config/config.php` file and update the `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_NAME` constants with the credentials you created in Step 1.
3.  **Set Server Permissions**: The application needs to be able to write to the `public/uploads/` directory to handle avatar uploads. Ensure the web server user (e.g., `www-data`) has write permissions to this directory.

    ```bash
    mkdir -p public/uploads/avatars
    sudo chown -R www-data:www-data public/uploads
    sudo chmod -R 755 public/uploads
    ```

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

Log into your server via SSH and open the crontab editor:

crontab -e
Add the following line, replacing the path with the absolute path to your TurnProcessor.php file:

*/10 * * * * /usr/bin/php /path/to/your/Stellar-Dominion/src/Game/TurnProcessor.php >> /path/to/your/Stellar-Dominion/src/Game/cron_log.txt 2>&1
This command executes the turn processor script every 10 minutes and logs its output.

### 📂 File Structure & Purpose
/config/: Contains the database connection configuration (config.php) and the database schema (database.sql).

/public/: The web server's document root. Contains the front controller (index.php), assets (CSS, JS, images), and user-facing error pages.

/src/: Contains the core application logic.

/Controllers/: PHP scripts that handle server-side logic for attacks, training, banking, profile updates, etc.

/Game/: Core game data files (GameData.php) and the main turn processing script (TurnProcessor.php).

/template/: Contains all the HTML/PHP view files (pages and reusable includes).

/includes/: Reusable components like the navigation menu and AI advisor.

/pages/: The individual pages of the game that the user sees.

.gitignore: Specifies files and directories to be ignored by version control, such as the configuration file and user uploads.



