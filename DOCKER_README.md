# Stellar Dominion Game - Docker Development Environment

This Docker Compose setup provides a complete development environment for testing the Stellar Dominion Game with all the new class-based architecture and API features.

## 🚀 Quick Start

1. **Clone the repository and navigate to the project directory:**
   ```bash
   git clone <repository-url>
   cd Stellar-Dominion-Game
   ```

2. **Start the Docker environment:**
   ```bash
   docker compose up -d
   ```

3. **Wait for services to start (about 30-60 seconds, first time pulls may take longer), then access:**
   - **Game**: http://localhost:8080
   - **phpMyAdmin**: http://localhost:8081
   - **Mailhog (Email testing)**: http://localhost:8025

4. **Initialize the database (if not auto-loaded):**
   ```bash
   # The database schema should auto-load, but if needed, manually load it:
   
   # Method 1: Copy and source (most reliable)
   docker compose cp Stellar-Dominion/config/database.sql db:/tmp/database.sql
   docker compose exec db mysql -u admin -p users -e "source /tmp/database.sql"
   
   # Method 2: Using cat and pipe
   cat Stellar-Dominion/config/database.sql | docker compose exec -T db mysql -u admin -p users
   # Password: password
   
   # Method 3: Using phpMyAdmin (easiest):
   # 1. Go to http://localhost:8081
   # 2. Login with admin/password
   # 3. Select users database
   # 4. Go to Import tab
   # 5. Choose file: Stellar-Dominion/config/database.sql
   # 6. Click Go
   ```

5. **Install PHP dependencies (if not auto-installed):**
   ```bash
   docker compose exec web composer install
   ```

## 🛠️ Services Included

### Web Server (PHP 8.2 + Apache)
- **URL**: http://localhost:8080
- **Features**: 
  - PHP 8.2 with all required extensions
  - Apache with mod_rewrite enabled
  - Composer dependencies installed
  - OPcache configured for development
  - Error reporting enabled

### MySQL Database
- **Host**: localhost:3306
- **Database**: users
- **Username**: admin
- **Password**: password
- **Root Password**: root_password
- **Schema**: Auto-loaded from `Stellar-Dominion/config/database.sql`
- Includes sample test data with users and alliances

**Environment Configuration:**
The application now uses dotenv for configuration management:
- **`.env`** - Docker-specific environment variables (automatically loaded)
- **`.env.example`** - Template for environment configuration
- **`config/config.php`** - Updated to use environment variables with fallbacks

**Database Initialization:**
The database schema is automatically loaded on first startup from:
- `Stellar-Dominion/config/database.sql` - Main schema and table structure
- `docker/mysql/init/02-test-data.sql` - Sample test data

If you need to reload the schema manually:
```bash
# Method 1: Copy file into container and run mysql
docker compose cp Stellar-Dominion/config/database.sql db:/tmp/database.sql
docker compose exec db mysql -u stellar_user -p stellar_dominion -e "source /tmp/database.sql"

# Method 2: Using cat and pipe (alternative)
cat Stellar-Dominion/config/database.sql | docker compose exec -T db mysql -u stellar_user -p stellar_dominion

# Method 3: Using phpMyAdmin (easiest GUI method)
# 1. Go to http://localhost:8081
# 2. Login with stellar_user/stellar_password  
# 3. Select stellar_dominion database
# 4. Go to Import tab
# 5. Choose file: Stellar-Dominion/config/database.sql
# 6. Click Go
```

### Redis (Session Storage)
- **Host**: localhost:6379
- Used for session management and caching

### phpMyAdmin
- **URL**: http://localhost:8081
- Database management interface
- Login with admin/password

### Mailhog (Email Testing)
- **SMTP**: localhost:1025
- **Web UI**: http://localhost:8025
- Captures all outgoing emails for testing

### Node.js (Optional)
- For frontend build tools if needed
- Access: `docker compose exec node sh`

## 🗄️ Database Management

### Schema and Data Loading

The Docker environment automatically loads the database schema and test data:

1. **`Stellar-Dominion/config/database.sql`** - Main database schema
   - Contains all table structures
   - Includes foreign key constraints
   - Defines indexes and relationships

2. **`docker/mysql/init/02-test-data.sql`** - Sample test data
   - 4 test users with different roles
   - Sample alliances and memberships
   - Test structures and battle data

### Manual Database Operations

**Load/Reload Database Schema:**
```bash
# Method 1: Copy file into container and run mysql (most reliable)
docker compose cp Stellar-Dominion/config/database.sql db:/tmp/database.sql
docker compose exec db mysql -u stellar_user -p stellar_dominion -e "source /tmp/database.sql"

# Method 2: Using cat and pipe 
cat Stellar-Dominion/config/database.sql | docker compose exec -T db mysql -u stellar_user -p stellar_dominion

# Method 3: Using phpMyAdmin (GUI method):
# 1. Open http://localhost:8081
# 2. Login: stellar_user / stellar_password
# 3. Select 'stellar_dominion' database
# 4. Click 'Import' tab
# 5. Choose file: Stellar-Dominion/config/database.sql
# 6. Click 'Go'
```

**Backup Current Database:**
```bash
# Export current database to backup file
docker compose exec db mysqldump -u stellar_user -p stellar_dominion > backup.sql
```

**Reset to Fresh State:**
```bash
# Complete reset (deletes all data and containers)
docker compose down -v
docker compose up -d
# Wait 30-60 seconds for auto-initialization
```

### Database Connection Details

For external database tools (MySQL Workbench, phpStorm, etc.):
- **Host:** localhost
- **Port:** 3306  
- **Database:** users
- **Username:** admin
- **Password:** password

## 🧪 Test Data

The environment includes test users:

| Username | Email | Password | Role |
|----------|-------|----------|------|
| TestCommander1 | test1@example.com | password | Alliance Leader |
| TestCommander2 | test2@example.com | password | Alliance Leader |
| TestCommander3 | test3@example.com | password | Alliance Member |
| AdminCommander | admin@stellar-dominion.com | admin123 | Administrator |

*Note: Test passwords use default hash. Update for production.*

## 🔧 Development Features

### New Class-Based Architecture
- **BaseController**: Modern PSR-7 compliant controller
- **API Controllers**: RESTful API endpoints
- **ORM Integration**: Cycle ORM with legacy fallback
- **Dependency Injection**: Factory pattern for services

### API Endpoints Available
- `GET /api/profile` - Fetch user profile data
- `POST /api/profile` - Update user profile
- `GET /api/csrf-token.php` - Get CSRF token

### SPA Templates
- Modern JavaScript-based templates in `template/spa-pages/`
- AJAX form submissions with real-time feedback
- API-driven data loading

## 📂 Directory Structure

```
/
├── docker compose.yml          # Docker services configuration
├── Dockerfile                  # Web server container
├── .env                        # Environment variables
├── docker/                     # Docker configuration files
│   ├── apache/vhost.conf      # Apache virtual host
│   ├── php/php.ini           # PHP configuration
│   └── mysql/init/           # Database initialization
├── Stellar-Dominion/          # Application code
│   ├── src/Controllers/api/   # New API controllers
│   ├── src/Core/             # Core architecture
│   ├── template/spa-pages/   # SPA templates
│   └── config/docker-config.php # Docker-specific config
└── logs/                      # Application logs
```

## 🛠️ Docker Commands

### Start Services
```bash
docker compose up -d
```

### Stop Services
```bash
docker compose down
```

### View Logs
```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f web
docker compose logs -f db
```

### Access Containers
```bash
# Web server
docker compose exec web bash

# Database
docker compose exec db mysql -u stellar_user -p stellar_dominion

# Redis
docker compose exec redis redis-cli
```

### Restart Services
```bash
# Restart all
docker compose restart

# Restart specific service
docker compose restart web
```

## 🧪 Testing the New Architecture

### 1. Test Legacy Templates
- Visit http://localhost:8080/profile
- Traditional PHP template with mixed logic

### 2. Test API Endpoints
```bash
# Get profile data (requires authentication)
curl -X GET http://localhost:8080/api/profile \
  -H "Cookie: PHPSESSID=your_session_id"

# Update profile
curl -X POST http://localhost:8080/api/profile \
  -H "Cookie: PHPSESSID=your_session_id" \
  -F "biography=Updated bio text"
```

### 3. Test SPA Template
- Visit http://localhost:8080/profile/spa
- Modern JavaScript-based interface
- Real-time form submission and feedback

### 4. Test Migration Patterns
- Follow examples in `MIGRATION_GUIDE.md`
- Use the front controller patterns in `public/index.php`

## 🔧 Configuration

### Environment Variables (dotenv)
The application now uses the `vlucas/phpdotenv` package for configuration management:

- **`.env`** - Contains Docker-specific environment variables
- **`.env.example`** - Template showing all available configuration options
- **`config/config.php`** - Updated to load environment variables with sensible defaults

**Key Environment Variables:**
```bash
# Database
DB_HOST=db
DB_DATABASE=users  
DB_USERNAME=admin
DB_PASSWORD=password

# Application
APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=America/New_York

# Game Settings
AVATAR_SIZE_LIMIT=500000
MIN_USER_LEVEL_AVATAR=5
CSRF_TOKEN_EXPIRY=3600
```

### Legacy Configuration Files
Edit these files for advanced customization:
- Database credentials
- Redis settings
- Mail configuration
- Upload limits
- Security settings

### PHP Configuration
Modify `docker/php/php.ini` for:
- Memory limits
- File upload sizes
- Error reporting
- OPcache settings

### Apache Configuration
Edit `docker/apache/vhost.conf` for:
- Virtual host settings
- Rewrite rules
- Security headers
- Directory permissions

## 🐛 Troubleshooting

### Database Connection Issues
```bash
# Check if database is ready
docker compose exec db mysqladmin ping -h localhost

# Check if schema is loaded
docker compose exec db mysql -u stellar_user -p -e "SHOW TABLES;" stellar_dominion

# Manually load the database schema
docker compose cp Stellar-Dominion/config/database.sql db:/tmp/database.sql
docker compose exec db mysql -u stellar_user -p stellar_dominion -e "source /tmp/database.sql"

# Reset database completely (WARNING: This deletes all data)
docker compose down -v
docker compose up -d

# Access database directly
docker compose exec db mysql -u stellar_user -p stellar_dominion
```

**Common Database Issues:**

1. **"Table doesn't exist" errors:**
   ```bash
   # Reload the schema using copy and source method
   docker compose cp Stellar-Dominion/config/database.sql db:/tmp/database.sql
   docker compose exec db mysql -u stellar_user -p stellar_dominion -e "source /tmp/database.sql"
   ```

2. **Connection refused:**
   ```bash
   # Wait for database to fully start (can take 30-60 seconds)
   docker compose logs db
   ```

3. **Permission denied:**
   ```bash
   # Check if containers are running
   docker compose ps
   ```

4. **Apache module errors (Header directive not found):**
   ```bash
   # Rebuild the web container to enable required modules
   docker compose down
   docker compose build web
   docker compose up -d
   ```

5. **Missing PHP dependencies (dotenv package):**
   ```bash
   # Install new dependencies after updating composer.json
   docker compose exec web composer install
   
   # Or rebuild container to include all dependencies
   docker compose build web
   docker compose up -d
   ```

### Permission Issues
```bash
# Fix upload directory permissions
docker compose exec web chown -R www-data:www-data /var/www/html/public/uploads
docker compose exec web chmod -R 755 /var/www/html/public/uploads
```

### Clear Cache/Sessions
```bash
# Clear Redis cache
docker compose exec redis redis-cli FLUSHALL

# Clear OPcache
docker compose exec web php -r "opcache_reset();"
```

### View Application Logs
```bash
# PHP errors
docker compose exec web tail -f /var/log/stellar-dominion/php_errors.log

# Application logs
docker compose exec web tail -f /var/log/stellar-dominion/app.log

# Apache access logs
docker compose exec web tail -f /var/log/apache2/stellar_dominion_access.log
```

## 🔒 Security Notes

This Docker setup is configured for **development only**:

- Debug mode enabled
- Weak test passwords
- Open database access
- No SSL/HTTPS
- Permissive file permissions

**Do not use in production without proper security hardening.**

## 📋 Next Steps

1. **Test the new controllers** using the migration patterns
2. **Create additional API endpoints** following the ProfileController example
3. **Build more SPA templates** for other game features
4. **Implement entity models** using Cycle ORM annotations
5. **Add unit tests** using PHPUnit framework

## 🆘 Getting Help

- Check container logs: `docker compose logs [service]`
- Access container shell: `docker compose exec [service] bash`
- Reset everything: `docker compose down -v && docker compose up -d`
- Review `MIGRATION_GUIDE.md` for architecture patterns

## 📝 Development Workflow

1. Make code changes in `Stellar-Dominion/` directory
2. Changes are automatically reflected (volume mounted)
3. For new dependencies: `docker compose exec web composer install`
4. For database changes: Update `config/database.sql` and restart
5. Test APIs using curl, Postman, or the SPA templates
