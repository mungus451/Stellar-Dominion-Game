# Stellar Dominion

**Live Site:** [www.stellar-dominion.com](https://www.stellar-dominion.com)

A persistent, text-based, massively multiplayer online strategy game set in a futuristic sci-fi universe. Players assume the role of a commander, building their empire, developing a powerful fleet, and engaging in strategic warfare with other players.

---

## Table of Contents

- [Core Gameplay Mechanics](#core-gameplay-mechanics)
- [Key Features](#key-features)
- [Technical Implementation](#technical-implementation)
  - [Technology Stack](#technology-stack)
  - [Application Architecture](#application-architecture)
  - [Security](#security)
  - [Database Schema](#database-schema)
- [Installation and Setup](#installation-and-setup)

---

## Core Gameplay Mechanics

Stellar Dominion is a turn-based strategy game where players manage resources, construct buildings, and build a fleet to expand their power.

-   **Turn-Based System:** The game progresses in turns. Each turn, players receive resources, and their structures perform their designated functions. The core game loop is managed by the `TurnProcessor.php` script, which calculates income, applies bonuses, and updates the state of all players' empires.

-   **Resource Management:** The primary resources are **credits**. Credits are generated each turn by **Worker** units. Players must balance their spending between developing infrastructure, recruiting units, and maintaining their army.

-   **Empire Building:** Players can construct various **structures** that provide unique bonuses. These include increasing resource income, boosting defensive capabilities, or unlocking new units and technologies.

-   **Combat:** Players can attack each other to gain experience, steal resources, and weaken their rivals. The combat is resolved based on the attack and defense values of the units involved, with detailed **battle reports** generated after each conflict.

---

## Key Features

The application is built with a rich set of features to provide a deep and engaging strategic experience.

-   **User Authentication:** Secure user registration and login system with password hashing (`password_hash`) and email verification. Includes a "Forgot Password" feature using security questions for account recovery.
-   **Dashboard:** The main interface for players, providing a snapshot of their empire, including resource counts, fleet size, recent events, and an advisor with game tips.
-   **Recruitment:** Players can recruit various types of units, each with specific stats (attack, defense) and purposes.
    -   **Offensive Units:** Soldier, Guard, Sentry
    -   **Defensive Units:** Shade, Spy
    -   **Special Units:** Worker (generates income), Mutant, Cyborg
-   **Armory:** A central page to view the player's entire fleet, including the total number of units and their combined attack and defense scores.
-   **Structures:** Players can build and upgrade structures that provide passive bonuses to their empire, such as increased income or unit effectiveness.
-   **Banking:** Players can deposit and withdraw credits from a bank to protect them from being stolen during attacks. The bank charges a small fee for transactions.
-   **Player Progression:** Players gain experience (XP) from battles and other actions, allowing them to level up. Leveling up grants skill points that can be used to improve attack strength, defense, or other attributes.
-   **Attack System:** A comprehensive attack page where players can search for targets and launch attacks. The system includes a detailed battle resolution mechanism (`AttackController.php`) and logs all conflicts.
-   **Alliances:** A core feature for multiplayer interaction.
    -   **Creation & Management:** Players can create or join alliances. Alliance leaders can manage members, set roles, and edit the alliance profile.
    -   **Alliance Bank:** A shared bank for alliance members to pool resources for common goals.
    -   **Alliance Forum:** A private forum for alliance members to communicate and strategize.
    -   **Alliance Structures:** Alliances can build shared structures that provide benefits to all members.
-   **Community & Profiles:**
    -   **Player Profiles:** Each player has a public profile displaying their stats, level, race, and alliance affiliation.
    -   **Community Page:** A global ranking/leaderboard page to see top players.
-   **Settings:** Players can change their password, update their profile description, and set a new avatar.

---

## Technical Implementation

The application is built using a classic PHP and MySQL stack with a custom-built, MVC-like architecture.

### Technology Stack

-   **Backend:** PHP
-   **Database:** MySQL
-   **Frontend:** HTML, CSS, JavaScript (for client-side interactions like CSRF token fetching)

### Application Architecture

The project follows a separation of concerns, loosely based on the Model-View-Controller (MVC) pattern.

-   **`public/` (Entry Point & Assets):** This is the web server's document root.
    -   `index.php`: The main entry point for the application. It acts as a router, parsing the URL to determine which page/controller to load.
    -   `assets/`: Contains all static files like CSS stylesheets, JavaScript files, and images.
-   **`src/` (Core Logic):**
    -   `Controllers/`: Contains the business logic for each feature. For example, `AllianceController.php` handles all actions related to alliances (creating, joining, managing). These controllers process user input, interact with the database, and prepare data to be displayed.
    -   `Game/`: Holds the core game mechanics.
        -   `GameData.php`: Defines static game data like unit stats, structure costs, and level-up requirements.
        -   `GameFunctions.php`: A collection of helper functions used throughout the application for common calculations.
        -   `TurnProcessor.php`: A critical script that processes the game turns, updating all player resources and states. This is likely run on a cron job.
    -   `Security/`: Manages security-related functionality.
        -   `CSRFProtection.php`: Implements CSRF token generation and validation to prevent cross-site request forgery attacks.
-   **`template/` (Views):**
    -   `pages/`: Contains the HTML templates for each page of the application (e.g., `dashboard.php`, `attack.php`). These files are responsible for presenting the data prepared by the controllers.
    -   `includes/`: Reusable template parts like the header, footer, and navigation menu.
-   **`config/` (Configuration):**
    -   `config.php`: Stores database credentials and other site-wide settings.
    -   `database.sql`: The complete SQL schema for setting up the database.
    -   `security.php`: Contains security-related configurations.

### Security

-   **CSRF Protection:** All forms that perform state-changing actions (e.g., training units, sending money) are protected by CSRF tokens. A token is fetched via JavaScript (`csrf.js`) and submitted with the form. The server validates this token before processing the request.
-   **Password Hashing:** User passwords are not stored in plaintext. They are securely hashed using PHP's native `password_hash()` and `password_verify()` functions.
-   **Prepared Statements:** Database queries are executed using prepared statements (PDO) to prevent SQL injection vulnerabilities.

### Database Schema

The database is the backbone of the game, storing all persistent data for players, alliances, and game state. The full schema is in `config/database.sql`. Below are the key tables and their functions.

-   **`users`**: Stores all player account information.
    ```sql
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `password` varchar(255) NOT NULL,
      `email` varchar(100) NOT NULL,
      `race` varchar(50) NOT NULL,
      `credits` bigint(20) NOT NULL DEFAULT 1000,
      `xp` int(11) NOT NULL DEFAULT 0,
      `level` int(11) NOT NULL DEFAULT 1,
      /* ... and many more columns for stats, units, etc. */
      PRIMARY KEY (`id`)
    );
    ```

-   **`alliances`**: Stores information about player-created alliances.
    ```sql
    CREATE TABLE `alliances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `tag` varchar(10) NOT NULL,
      `leader_id` int(11) NOT NULL,
      `description` text,
      `credits` bigint(20) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    );
    ```

-   **`structures`**: Tracks the level of each structure for every player.
    ```sql
    CREATE TABLE `structures` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `structure_name` varchar(100) NOT NULL,
      `level` int(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `structures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    );
    ```

-   **`battle_logs`**: Records the results of every attack that occurs between players.
    ```sql
    CREATE TABLE `battle_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `attacker_id` int(11) NOT NULL,
      `defender_id` int(11) NOT NULL,
      `winner_id` int(11) NOT NULL,
      `attacker_xp_gain` int(11) NOT NULL,
      `defender_xp_gain` int(11) NOT NULL,
      `credits_stolen` bigint(20) NOT NULL,
      `log_details` text NOT NULL,
      `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    );
    ```

-   **`alliance_forum_threads`** & **`alliance_forum_posts`**: Power the alliance forum functionality.

---

## Installation and Setup

To run a local instance of Stellar Dominion, follow these steps:

1.  **Clone the Repository:**
    ```bash
    git clone [https://github.com/mungus451/stellar-dominion-game.git](https://github.com/mungus451/stellar-dominion-game.git)
    cd stellar-dominion-game/Stellar-Dominion
    ```

2.  **Web Server Setup:**
    -   Set up a local web server environment (e.g., XAMPP, WAMP, MAMP, or a standalone Apache/Nginx server) with PHP support.
    -   Configure the server's document root to point to the `/public` directory of the project.

3.  **Database Setup:**
    -   Create a new MySQL database.
    -   Import the database schema using the `config/database.sql` file. You can do this via a tool like phpMyAdmin or the command line:
        ```bash
        mysql -u your_username -p your_database_name < config/database.sql
        ```

4.  **Configure the Application:**
    -   Open `config/config.php`.
    -   Update the database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) to match your local setup.

5.  **Run the Application:**
    -   Navigate to your local server's address in a web browser. You should see the Stellar Dominion landing page.

---

## File Storage System

Stellar Dominion uses a driver-based file storage system that supports both local filesystem and Amazon S3 storage. This allows for flexibility in deployment environments.

### Configuration

The file storage system is configured via environment variables:

```bash
# Choose storage driver: 'local' or 's3'
FILE_STORAGE_DRIVER=local

# Local storage settings (when using local driver)
FILE_STORAGE_LOCAL_PATH=/path/to/uploads
FILE_STORAGE_LOCAL_URL=/uploads

# S3 storage settings (when using s3 driver)
FILE_STORAGE_S3_BUCKET=your-bucket-name
FILE_STORAGE_S3_REGION=us-east-2
FILE_STORAGE_S3_URL=https://cdn.yourdomain.com  # Optional CDN URL
```

### Supported Features

- **Avatar Uploads**: User and alliance avatar management
- **File Validation**: Automatic validation of file types, sizes, and security
- **Multiple Drivers**: Seamless switching between local and S3 storage
- **Security**: Built-in protection against malicious file uploads
- **Metadata**: File metadata storage for tracking and management

### Usage

The system automatically handles file operations based on the configured driver. You can use either configuration objects (recommended) or arrays (legacy support):

#### Using Configuration Objects (Recommended)

```php
use StellarDominion\Services\FileManager\FileManagerFactory;
use StellarDominion\Services\FileManager\Config\LocalFileManagerConfig;
use StellarDominion\Services\FileManager\Config\S3FileManagerConfig;

// Create from environment variables
$fileManager = FileManagerFactory::createFromEnvironment();

// Or create with specific configuration objects
$localConfig = new LocalFileManagerConfig('/path/to/uploads', '/uploads');
$fileManager = FileManagerFactory::createFromConfig($localConfig);

// S3 for development
$s3Config = S3FileManagerConfig::createDevelopment('my-dev-bucket');
$fileManager = FileManagerFactory::createFromConfig($s3Config);
```

#### Using Arrays (Legacy Support)

```php
// Get the configured file manager (legacy method)
$fileManager = FileManagerFactory::createFromEnvironment();

// Upload a file
$fileManager->upload($sourceFile, 'avatars/user_123.jpg');

// Get file URL
$url = $fileManager->getUrl('avatars/user_123.jpg');

// Delete a file
$fileManager->delete('avatars/user_123.jpg');
```

#### Configuration Benefits

Configuration objects provide several advantages:
- **Type Safety**: Compile-time validation of configuration parameters
- **IDE Support**: Full autocompletion and documentation
- **Validation**: Built-in validation with clear error messages
- **Immutability**: Configuration cannot be accidentally modified
- **Defaults**: Easy creation of standard configurations

### Deployment Notes

- **Local Development**: Use `local` driver with `FILE_STORAGE_LOCAL_PATH` pointing to a writable directory
- **Production with S3**: Use `s3` driver with appropriate AWS credentials and bucket configuration
- **CloudFront**: Set `FILE_STORAGE_S3_URL` to your CloudFront distribution URL for CDN benefits
