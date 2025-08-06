# Construction Camera System

A dynamic construction camera monitoring system built with PHP/Symfony 7. Migrated from Windows C#/LibVLC to Ubuntu/Apache for better scalability and maintainability.

## Features

- 🏗️ **Multi-Building Support**: Organize cameras by construction sites
- 📸 **Automated Snapshots**: Configurable interval capturing with systemd service
- 🖼️ **Gallery Interface**: Date-based navigation with modal viewer
- 📺 **Live Streaming**: RTSP stream integration
- ⚙️ **Admin Interface**: Full CRUD operations for buildings and cameras
- 🗄️ **Database-Driven**: Dynamic configuration replacing static files

## Architecture

### Backend
- **Framework**: Symfony 7
- **Database**: MySQL with Doctrine ORM
- **Web Server**: Apache on Ubuntu
- **Background Service**: systemd daemon for snapshot capture

### Database Schema
- **Buildings**: Site organization and display ordering
- **Cameras**: RTSP URLs, scheduling, feature toggles
- **Admin Users**: Authentication system
- **Snapshot Logs**: Monitoring and debugging

## Installation

### Prerequisites
- Ubuntu Server
- Apache with mod_rewrite
- PHP 8.1+ with extensions: pdo_mysql, gd, curl
- MySQL 8.0+
- Composer
- FFmpeg (for snapshot capture)

### Setup Steps

1. **Clone Repository**
   ```bash
   git clone [repository-url]
   cd construction-cameras
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Environment Configuration**
   ```bash
   cp .env .env.local
   # Edit .env.local with your database credentials
   ```

4. **Database Setup**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load  # If you have fixtures
   ```

5. **Create Admin User**
   ```bash
   php bin/console app:create-admin admin admin123
   ```

6. **Set Permissions**
   ```bash
   sudo chown -R www-data:www-data var/ public/images/
   sudo chmod -R 775 var/ public/images/
   ```

7. **Configure Systemd Service**
   ```bash
   sudo cp config/construction-camera-snapshots.service /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable construction-camera-snapshots
   sudo systemctl start construction-camera-snapshots
   ```

### Apache Virtual Host
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/construction-cameras/public
    
    <Directory /var/www/construction-cameras/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/construction_cameras_error.log
    CustomLog ${APACHE_LOG_DIR}/construction_cameras_access.log combined
</VirtualHost>
```

## Usage

### Admin Interface
- URL: `/admin`
- Default credentials: `admin` / `admin123`
- Manage buildings, cameras, and system settings

### Public Interface
- Building gallery: `/{building-slug}`
- Camera live view: `/{building-slug}/{camera-id}`
- Camera gallery: `/{building-slug}/{camera-id}/gallery`

### Snapshot Service
- Automated capture every 5 minutes (configurable)
- Images stored: `/public/images/cam{id}/YYYYMMDD/`
- Service status: `sudo systemctl status construction-camera-snapshots`

## Development

### Key Files
- **Entities**: `src/Entity/` - Building, Camera, AdminUser, SnapshotLog
- **Controllers**: `src/Controller/` - Admin, Main, Login controllers
- **Services**: `src/Service/SnapshotService.php` - Image capture logic
- **Templates**: `templates/` - Twig templates for UI
- **Service Config**: `config/construction-camera-snapshots.service`

### Adding New Cameras
1. Use admin interface at `/admin/cameras/new`
2. Configure RTSP URL, schedule, and feature flags
3. Service will automatically begin capturing snapshots

## Migration Notes

Migrated from:
- Windows Server + IIS + C# LibVLC application
- Static configuration files
- Hardcoded camera routes

To current architecture for:
- Better scalability and maintenance
- Dynamic configuration
- Cross-platform compatibility
- Modern web framework benefits

## Troubleshooting

### Snapshot Service Issues
```bash
# Check service status
sudo systemctl status construction-camera-snapshots

# View logs
sudo journalctl -u construction-camera-snapshots -f

# Restart service
sudo systemctl restart construction-camera-snapshots
```

### Permission Issues
```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/construction-cameras/
sudo chmod -R 775 var/ public/images/
```

### Database Issues
```bash
# Reset database
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## License

[Add your license here]

## Contributing

[Add contribution guidelines here]
