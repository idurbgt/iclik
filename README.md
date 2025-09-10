# Server Monitoring System

Aplikasi web sederhana untuk monitoring server menggunakan ICMP/ping dengan visualisasi peta interaktif.

## Features

- Real-time server monitoring dengan ICMP/ping
- Interactive map visualization (Leaflet.js)
- Click-to-select server location
- Auto refresh setiap 30 detik
- Uptime percentage statistics
- Modern dan responsive UI
- JSON file-based storage (no database required)

## Requirements

- PHP >= 7.4
- Web Server (Apache/Nginx)
- PHP extensions: json
- Cron access untuk scheduled jobs

## Installation

### 1. Setup Project
```bash
cd /path/to/webserver
# Download atau extract project files
```

### 2. Set Permissions
```bash
chmod 755 cron/ping_check.php
chmod 755 data/
```

### 3. Setup Cron Job
```bash
# Edit crontab
crontab -e

# Add line (run every minute):
* * * * * /usr/bin/php /path/to/uptime-php/cron/ping_check.php
```

### 4. Access Application
```
http://localhost/uptime-php/
```

## Project Structure

```
uptime-php/
├── index.php              # Main dashboard
├── includes/
│   ├── json_handler.php   # JSON file operations
│   └── functions.php      # Helper functions
├── api/
│   ├── get_servers.php    # Get servers API
│   ├── add_server.php     # Add server API
│   ├── delete_server.php  # Delete server API
│   └── check_status.php   # Manual check API
├── cron/
│   └── ping_check.php     # Cron script for ping
├── assets/
│   ├── css/
│   │   └── style.css      # Styles
│   └── js/
│       └── app.js         # JavaScript app
├── data/
│   ├── servers.json       # Server data
│   ├── ping_logs.json     # Ping logs
│   └── logs/             # Log files
└── README.md
```

## Usage

### Adding New Server
1. Click "Add Server" button
2. Fill form:
   - Server Name
   - IP Address
   - Description (optional)
3. Click on map to select location
4. Click "Save Server"

### Server Monitoring
- Dashboard auto-refreshes every 30 seconds
- Server status colors:
  - Green = Server Online
  - Red = Server Offline  
  - Gray = Status Unknown
- Connection lines from center to servers:
  - Solid green = Active connection
  - Dashed red = Connection lost

### Delete Server
- Click "Delete" button in server table
- Confirm deletion (soft delete)

## Data Storage

The application uses JSON files for data storage:

- `data/servers.json` - Server configurations and statistics
- `data/ping_logs.json` - Ping history (last 100 logs per server)
- `data/logs/` - Application logs organized by date

## Troubleshooting

### Ping Not Working
- Check if PHP `exec()` function is enabled
- Ensure user has permission to run ping command
- Test manually: `php cron/ping_check.php`

### Cron Job Not Running
- Check crontab: `crontab -l`
- Check cron logs: `/var/log/cron` or `/var/log/syslog`
- Test script manually: `php /path/to/uptime-php/cron/ping_check.php`

### File Permissions
```bash
# Fix permissions if needed
chmod 755 data/
chmod 644 data/*.json
chmod 755 cron/ping_check.php
```

## Notes

- Application uses soft delete (servers marked as inactive)
- Ping timeout: 5 seconds with 3 retries
- Automatic log rotation (keeps last 100 logs per server)
- All data stored in JSON files (no database required)

## Security

- Input validation untuk IP addresses
- Sanitized user inputs
- Escaped shell arguments
- File-based storage with proper permissions

## License

MIT License