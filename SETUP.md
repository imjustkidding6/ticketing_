# Developer Setup Guide

This guide will help you set up the Ticketing application on your local machine.

## WSL (Windows Subsystem for Linux) Users

This project is developed in WSL. If you're on Windows, we recommend using WSL2 for the best experience.

### WSL Prerequisites

1. **Install WSL2** (if not already installed):
   ```powershell
   # Run in PowerShell as Administrator
   wsl --install
   ```

2. **Install Docker Desktop for Windows**:
   - Download from [docker.com](https://www.docker.com/products/docker-desktop/)
   - Enable "Use WSL 2 based engine" in Docker Desktop settings
   - Enable integration with your WSL distro under Settings > Resources > WSL Integration

3. **Clone the repo inside WSL** (important for performance):
   ```bash
   # Inside WSL terminal, NOT in /mnt/c/
   cd ~
   git clone <repository-url>
   cd ticketing
   ```

4. **Run Docker containers from WSL**:
   ```bash
   # Copy environment file
   cp .env.example .env

   # Start Docker containers
   make up

   # Install PHP dependencies
   make composer-install

   # Generate application key
   make artisan cmd="key:generate"

   # Run database migrations
   make migrate

   # Install and build frontend (in WSL)
   npm install
   npm run build

   # Visit http://localhost:8080 in your Windows browser
   ```

### WSL Tips

- **Keep project files in the Linux filesystem** (`~/` or `/home/`), not in `/mnt/c/`. This dramatically improves performance.
- **Access the app** from your Windows browser at `http://localhost:8080` (Docker) or `http://localhost:8000` (local).
- **VS Code**: Use the "Remote - WSL" extension to open the project directly in WSL.
- **File permissions**: If you encounter permission issues, run:
  ```bash
  sudo chown -R $USER:$USER .
  ```

## Prerequisites

### For Docker Development (Recommended)
- [Docker](https://docs.docker.com/get-docker/) (v20.10+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2.0+)
- [Node.js](https://nodejs.org/) (v20+) - for running Vite on the host
- [Git](https://git-scm.com/)

### For Local Development (Without Docker)
- PHP 8.2+
- Composer 2.x
- Node.js 20+
- npm 10+
- MySQL 8.0+ or SQLite
- Redis (optional)

## Quick Start

### Option 1: Docker (Recommended)

```bash
# Clone the repository
git clone <repository-url>
cd ticketing

# Copy environment file
cp .env.example .env

# Start Docker containers
make up

# Install PHP dependencies
make composer-install

# Generate application key
make artisan cmd="key:generate"

# Run database migrations
make migrate

# Install and build frontend assets (run on host machine)
npm install
npm run build

# Visit http://localhost:8080
```

### Option 2: Local Development

```bash
# Clone the repository
git clone <repository-url>
cd ticketing

# Run the full setup (installs deps, generates key, migrates, builds frontend)
composer run setup

# Start development servers
composer run dev

# Visit http://localhost:8000
```

## Docker Development Setup (Detailed)

### 1. Clone and Configure

```bash
git clone <repository-url>
cd ticketing
cp .env.example .env
```

### 2. Environment Configuration

Edit `.env` and ensure these settings are correct for Docker:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ticketing
DB_USERNAME=ticketing
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
```

### 3. Start Containers

```bash
# Start all services in background
make up

# Or using docker-compose directly
docker-compose up -d
```

### 4. Install Dependencies

```bash
# Install PHP dependencies
make composer-install

# Install Node dependencies (on host)
npm install
```

### 5. Application Setup

```bash
# Generate application key
make artisan cmd="key:generate"

# Run database migrations
make migrate

# (Optional) Seed the database
make seed

# Build frontend assets
npm run build
```

### 6. Verify Installation

Visit [http://localhost:8080](http://localhost:8080) in your browser.

## Local Development Setup (Detailed)

### 1. Install PHP Dependencies

Ensure you have the following PHP extensions:
- pdo_mysql / pdo_sqlite
- mbstring
- exif
- pcntl
- bcmath
- gd
- zip

### 2. Clone and Configure

```bash
git clone <repository-url>
cd ticketing
cp .env.example .env
```

### 3. Environment Configuration

Edit `.env` for local development:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ticketing
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Or use SQLite
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database.sqlite

REDIS_HOST=127.0.0.1
```

### 4. Run Setup Command

```bash
composer run setup
```

This will:
- Install Composer dependencies
- Copy `.env.example` to `.env` (if not exists)
- Generate application key
- Run database migrations
- Install npm dependencies
- Build frontend assets

### 5. Start Development Servers

```bash
composer run dev
```

This starts concurrently:
- Laravel development server (http://localhost:8000)
- Queue worker
- Log viewer (Pail)
- Vite dev server (with HMR)

## Common Commands

### Docker Commands

| Command | Description |
|---------|-------------|
| `make up` | Start all containers |
| `make down` | Stop all containers |
| `make logs` | View container logs |
| `make shell` | Open shell in app container |
| `make mysql` | Open MySQL CLI |
| `make redis` | Open Redis CLI |
| `make migrate` | Run migrations |
| `make fresh` | Fresh migrate with seeders |
| `make test` | Run tests |
| `make tinker` | Open Laravel Tinker |
| `make artisan cmd="..."` | Run any Artisan command |

### Local Commands

| Command | Description |
|---------|-------------|
| `composer run dev` | Start all dev servers |
| `composer test` | Run tests |
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Seed database |
| `npm run dev` | Vite dev server |
| `npm run build` | Build for production |

## Services & Ports

### Docker Services

| Service | Container Name | Port |
|---------|----------------|------|
| Web (Nginx) | ticketing-nginx | 8080 |
| MySQL | ticketing-mysql | 3310 (mapped from 3306) |
| Redis | ticketing-redis | 6379 |
| PHP-FPM | ticketing-app | 9000 (internal) |

### Local Development

| Service | Port |
|---------|------|
| Laravel Server | 8000 |
| Vite Dev Server | 5173 |

## Testing

```bash
# Docker
make test

# Local
composer test
# or
php artisan test --compact
```

## Troubleshooting

### WSL Issues

**Docker commands not working:**
- Ensure Docker Desktop is running on Windows
- Check WSL integration: Docker Desktop > Settings > Resources > WSL Integration

**Slow file operations:**
- Move the project to the Linux filesystem (`~/projects/`) instead of `/mnt/c/`

**Cannot access localhost from Windows browser:**
- WSL2 should forward ports automatically. If not, check Windows firewall settings
- Try `localhost` instead of `127.0.0.1`

**Permission denied errors:**
```bash
sudo chown -R $USER:$USER .
chmod -R 755 storage bootstrap/cache
```

**Node/npm issues in WSL:**
```bash
# Install Node via nvm (recommended for WSL)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 20
nvm use 20
```

### Docker Issues

**Containers won't start:**
```bash
# Check for port conflicts
docker-compose down
make rebuild
make up
```

**Permission errors:**
```bash
make shell
chown -R www-data:www-data storage bootstrap/cache
chmod -R 755 storage bootstrap/cache
```

**Database connection refused:**
```bash
# Wait for MySQL to be ready, then:
make migrate
```

### Local Development Issues

**Vite manifest not found:**
```bash
npm run build
# or run dev server
npm run dev
```

**Class not found errors:**
```bash
composer dump-autoload
```

**Cache issues:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Frontend Not Updating

Make sure Vite is running:
```bash
# Docker: run on host machine
npm run dev

# Local: included in composer run dev
```

## IDE Setup

### VS Code Recommended Extensions
- PHP Intelephense
- Laravel Blade Snippets
- Tailwind CSS IntelliSense
- ESLint
- Prettier

### PHPStorm
- Enable Laravel plugin
- Configure PHP interpreter (Docker or local)
- Set up Xdebug for debugging

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Livewire Documentation](https://livewire.laravel.com/docs)
