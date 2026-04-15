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

## Setup (Canonical)

All standard setup steps now live in the root README. This file keeps WSL-specific guidance and troubleshooting.

➡️ **Start here:** `README.md` → **Setup** section

## Common Commands, Ports, and Testing

See `README.md` for the canonical command list and port mappings.

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
