# Stellar Dominion

Stellar Dominion is a browser-based grand strategy game focused on intergalactic conquest, economic management, and intricate alliance dynamics. Players build their empires, train formidable fleets, and engage in strategic warfare, all while navigating complex political landscapes through a robust alliance system.

## Key Features

*   **Empire Management:** Oversee your planet's economy, manage resources, and develop your infrastructure to generate wealth and power.
*   **Military & Combat:** Recruit and train various unit types, equip them in the armory, and engage in tactical battles against other players. Track your combat record and strategize for dominance.
*   **Alliance System:** Form or join powerful alliances to collaborate with other commanders. Alliances feature:
    *   **Shared Bank & Structures:** Contribute to and benefit from collective resources and defensive structures.
    *   **Hierarchical Roles & Permissions:** Leaders can define custom roles with granular permissions, allowing for sophisticated internal management.
    *   **Alliance Forum:** A private communication hub for strategic discussions and coordination.
    *   **Alliance-to-Alliance Interactions:** Declare wars with specific Casus Belli and establish rivalries with other alliances, adding a new layer of diplomatic and military strategy.
*   **Player Progression:** Advance your commander's level and unlock powerful upgrades across economic, military, and population sectors.
*   **Community & Leaderboards:** Engage with the broader player base, track your standing on global leaderboards, and participate in community discussions.
*   **Realm War Hub:** A centralized page to monitor all ongoing wars and rivalries across the galaxy, serving as a strategic overview of galactic conflicts.

## Technologies Used

*   **Backend:** PHP (with `mysqli` for database interaction)
*   **Database:** MySQL / MariaDB
*   **Frontend:** HTML, CSS (leveraging Tailwind CSS for rapid styling), JavaScript
*   **Icons:** Lucide Icons
*   **Fonts:** Orbitron (for titles) and Roboto (for general text)

## Installation Guide

This guide will walk you through setting up Stellar Dominion on your local development environment. It assumes you have a basic understanding of web servers (Apache/Nginx), PHP, and MySQL/MariaDB.

### Prerequisites

Before you begin, ensure you have the following installed:

*   **Web Server:** Apache or Nginx
*   **PHP:** Version 7.4 or higher (with `mysqli` extension enabled)
*   **Database Server:** MySQL or MariaDB

### Step 1: Clone the Repository

```bash
git clone [repository_url]
cd Stellar-Dominion
```

### Step 2: Web Server Configuration

Configure your web server to point its document root to the `public/` directory of the cloned repository. 

**Example for Apache (httpd.conf or a new .conf file):**

```apache
<VirtualHost *:80>
    DocumentRoot "/path/to/Stellar-Dominion/public"
    <Directory "/path/to/Stellar-Dominion/public">
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "${APACHE_LOG_DIR}/stellar_dominion_error.log"
    CustomLog "${APACHE_LOG_DIR}/stellar_dominion_access.log" combined
</VirtualHost>
```

**Example for Nginx (nginx.conf or a new .conf file):**

```nginx
server {
    listen 80;
    server_name your_domain_or_ip;
    root /path/to/Stellar-Dominion/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Adjust PHP-FPM socket path
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Remember to replace `/path/to/Stellar-Dominion` with the actual absolute path to your project directory.

### Step 3: Database Setup

1.  **Create a MySQL/MariaDB Database:**

    ```sql
    CREATE DATABASE users; -- Or choose a different name
    CREATE USER 'admin'@'localhost' IDENTIFIED BY 'password'; -- Or your preferred username/password
    GRANT ALL PRIVILEGES ON users.* TO 'admin'@'localhost';
    FLUSH PRIVILEGES;
    ```

2.  **Configure Database Connection:**

    Open `config/config.php` and update the database credentials to match your setup:

    ```php
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'admin'); // Your database username
    define('DB_PASSWORD', 'password'); // Your database password
    define('DB_NAME', 'users'); // Your database name
    ```

3.  **Import Database Schema:**

    You will need to create the necessary tables. The core tables are `users`, `alliances`, `alliance_roles`, `wars`, and `rivalries`. You will need to obtain the full schema for the `users` and `alliances` tables from the project maintainer or an existing database dump. Below are the schemas for the tables created during development:

    **`wars` table:**

    ```sql
    CREATE TABLE wars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        declarer_alliance_id INT NOT NULL,
        declared_against_alliance_id INT NOT NULL,
        casus_belli TEXT,
        start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_date DATETIME NULL,
        status VARCHAR(255) DEFAULT 'active',
        FOREIGN KEY (declarer_alliance_id) REFERENCES alliances(id),
        FOREIGN KEY (declared_against_alliance_id) REFERENCES alliances(id)
    );
    ```

    **`rivalries` table:**

    ```sql
    CREATE TABLE rivalries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alliance1_id INT NOT NULL,
        alliance2_id INT NOT NULL,
        start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_date DATETIME NULL,
        FOREIGN KEY (alliance1_id) REFERENCES alliances(id),
        FOREIGN KEY (alliance2_id) REFERENCES alliances(id)
    );
    ```

    **`alliance_roles` table (partial schema, based on usage):**

    ```sql
    CREATE TABLE alliance_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alliance_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        `order` INT NOT NULL, -- Used for hierarchy
        -- Add other permission columns as observed in the code (e.g., can_manage_roles, can_approve_membership)
        FOREIGN KEY (alliance_id) REFERENCES alliances(id)
    );
    ```

    *(Note: The full schema for `users` and `alliances` tables, and all columns for `alliance_roles` would need to be provided by the project maintainer or extracted from a full database dump.)*

### Step 4: Access the Application

Once all steps are completed, open your web browser and navigate to the configured domain or IP address (e.g., `http://localhost/`).

## Contributing

(Section to be added if applicable)

## License

(Section to be added if applicable)
