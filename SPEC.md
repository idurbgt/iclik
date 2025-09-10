# Spesifikasi Aplikasi Monitoring Server

## 1. Gambaran Umum
Aplikasi web sederhana untuk monitoring status server menggunakan ICMP/ping dengan visualisasi peta interaktif.

## 2. Teknologi Stack
- **Backend**: PHP (Native/Vanilla)
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Map Library**: Leaflet.js (open source map library)
- **AJAX**: Untuk real-time update status

## 3. Fitur Utama

### 3.1 Manajemen Server
- **Tambah Server**
  - Input IP Address
  - Pilih lokasi dari peta (click to select)
  - Nama server (opsional)
  - Deskripsi (opsional)
  
- **Edit Server**
  - Update IP Address
  - Update lokasi di peta
  - Update informasi server

- **Hapus Server**
  - Konfirmasi sebelum hapus
  - Soft delete (archive)

### 3.2 Dashboard Monitoring
- **Peta Interaktif**
  - Tampilkan semua server sebagai node/marker
  - Node hijau = server online
  - Node merah = server offline
  - Garis koneksi dari titik pusat ke setiap server
  - Garis hijau = koneksi aktif
  - Garis merah = koneksi terputus
  
- **Status Real-time**
  - Auto refresh setiap 30 detik
  - Manual refresh button
  - Last check timestamp

- **Informasi Node**
  - Hover: tampilkan IP dan nama server
  - Click: tampilkan detail (IP, nama, status, response time, uptime percentage)

### 3.3 Monitoring Engine
- **ICMP Ping Check**
  - Cron job setiap 1 menit
  - Timeout: 5 detik
  - Retry: 3 kali jika gagal
  
- **Logging**
  - Simpan history status (up/down)
  - Response time
  - Timestamp

## 4. Database Schema

### Tabel: servers
```sql
CREATE TABLE servers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabel: ping_logs
```sql
CREATE TABLE ping_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    server_id INT NOT NULL,
    status ENUM('up', 'down') NOT NULL,
    response_time INT, -- dalam milliseconds
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_server_checked (server_id, checked_at)
);
```

### Tabel: server_stats
```sql
CREATE TABLE server_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    server_id INT NOT NULL UNIQUE,
    last_status ENUM('up', 'down'),
    last_check TIMESTAMP,
    last_response_time INT,
    uptime_percentage DECIMAL(5,2),
    total_checks INT DEFAULT 0,
    total_up INT DEFAULT 0,
    total_down INT DEFAULT 0,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
);
```

## 5. Struktur File

```
uptime-php/
├── index.php                 # Dashboard dengan peta
├── config/
│   └── database.php          # Konfigurasi database
├── includes/
│   ├── db_connect.php        # Koneksi database
│   └── functions.php         # Helper functions
├── api/
│   ├── get_servers.php       # API endpoint untuk data server
│   ├── add_server.php        # API tambah server
│   ├── update_server.php     # API update server
│   ├── delete_server.php     # API hapus server
│   └── check_status.php      # API manual check
├── cron/
│   └── ping_check.php        # Script cron untuk ping
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js            # JavaScript untuk peta dan AJAX
├── sql/
│   └── schema.sql            # Database schema
└── README.md
```

## 6. User Interface

### 6.1 Layout Dashboard
```
+----------------------------------+
|          HEADER                  |
|   Server Monitoring Dashboard    |
+----------------------------------+
|  [Add Server] [Refresh]          |
+----------------------------------+
|                                  |
|         MAP CONTAINER            |
|         (Full Width)             |
|          With Nodes              |
|                                  |
+----------------------------------+
| Server List Table                |
| IP | Name | Status | Response    |
+----------------------------------+
```

### 6.2 Add Server Modal
```
+-------------------------+
|     Add New Server      |
+-------------------------+
| IP Address: [_______]   |
| Server Name: [______]   |
|                         |
| Select Location on Map: |
| [    Map Preview    ]   |
|                         |
| Description: [______]   |
|                         |
| [Cancel] [Save]         |
+-------------------------+
```

## 7. Alur Kerja

### 7.1 Menambah Server
1. User klik tombol "Add Server"
2. Modal form muncul
3. User input IP address
4. User klik lokasi di peta
5. Submit form
6. Validasi IP dan koordinat
7. Simpan ke database
8. Jalankan ping check pertama
9. Update tampilan dashboard

### 7.2 Monitoring Flow
1. Cron job berjalan setiap menit
2. Query semua active servers
3. Loop setiap server:
   - Execute ping command
   - Parse hasil
   - Update status database
   - Log hasil
4. Hitung statistics
5. Dashboard auto-refresh via AJAX

### 7.3 Visualisasi Status
1. AJAX request ke API setiap 30 detik
2. Get latest status semua server
3. Update node colors di peta
4. Update connection lines
5. Update tabel status

## 8. Implementasi Ping

### PHP Ping Function
```php
function pingServer($ip) {
    $timeout = 5;
    $ping = exec("ping -c 1 -W $timeout $ip", $output, $status);
    
    if ($status === 0) {
        // Parse response time from output
        preg_match('/time=(.*)ms/', $ping, $matches);
        $responseTime = isset($matches[1]) ? (int)$matches[1] : 0;
        
        return [
            'status' => 'up',
            'response_time' => $responseTime
        ];
    }
    
    return [
        'status' => 'down',
        'response_time' => null
    ];
}
```

## 9. Keamanan
- Input validation untuk IP address
- Prepared statements untuk query database
- CSRF protection untuk form
- Rate limiting untuk API
- Escape output untuk XSS prevention
- Validasi koordinat peta

## 10. Konfigurasi Server

### Requirements
- PHP >= 7.4
- MySQL >= 5.7
- PHP extensions: mysqli, json
- Cron access untuk scheduled jobs
- exec() function enabled untuk ping

### Cron Setup
```bash
# Tambahkan ke crontab
* * * * * /usr/bin/php /path/to/uptime-php/cron/ping_check.php
```

## 11. Fitur Tambahan (Optional)
- Email notification saat server down
- SMS alert (menggunakan API pihak ketiga)
- Export data ke CSV
- Historical graph untuk uptime
- Multiple user support dengan authentication
- Dark mode theme
- Response time chart
- Grouping servers by location/category

## 12. Testing
- Unit test untuk fungsi ping
- Test various IP formats (IPv4/IPv6)
- Test timeout scenarios
- Test database operations
- UI testing untuk map interactions
- Load testing untuk concurrent pings

## 13. Deployment
1. Clone repository
2. Setup database MySQL
3. Import schema.sql
4. Configure database.php
5. Setup cron job
6. Set proper permissions
7. Test ping functionality
8. Access via browser

## 14. Maintenance
- Cleanup old logs (> 30 days)
- Optimize database indexes
- Monitor cron job execution
- Backup database regularly
- Update dependencies

## 15. Implementasi Script

### 15.1 Database Configuration (config/database.php)
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'uptime_monitor');
?>
```

### 15.2 Database Connection (includes/db_connect.php)
```php
<?php
require_once 'config/database.php';

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}
?>
```

### 15.3 SQL Schema (sql/schema.sql)
```sql
CREATE DATABASE IF NOT EXISTS uptime_monitor;
USE uptime_monitor;

CREATE TABLE servers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE ping_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    server_id INT NOT NULL,
    status ENUM('up', 'down') NOT NULL,
    response_time INT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_server_checked (server_id, checked_at)
);

CREATE TABLE server_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    server_id INT NOT NULL UNIQUE,
    last_status ENUM('up', 'down'),
    last_check TIMESTAMP,
    last_response_time INT,
    uptime_percentage DECIMAL(5,2),
    total_checks INT DEFAULT 0,
    total_up INT DEFAULT 0,
    total_down INT DEFAULT 0,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
);
```

### 15.4 Helper Functions (includes/functions.php)
```php
<?php
function pingServer($ip) {
    $timeout = 5;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ping = exec("ping -n 1 -w " . ($timeout * 1000) . " $ip", $output, $status);
    } else {
        $ping = exec("ping -c 1 -W $timeout $ip 2>&1", $output, $status);
    }
    
    if ($status === 0) {
        preg_match('/time[<=](.*)ms/', implode(' ', $output), $matches);
        $responseTime = isset($matches[1]) ? round(floatval($matches[1])) : 0;
        
        return [
            'status' => 'up',
            'response_time' => $responseTime
        ];
    }
    
    return [
        'status' => 'down',
        'response_time' => null
    ];
}

function updateServerStats($conn, $server_id, $status, $response_time) {
    $stmt = $conn->prepare("
        INSERT INTO server_stats (server_id, last_status, last_check, last_response_time, total_checks, total_up, total_down, uptime_percentage)
        VALUES (?, ?, NOW(), ?, 1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        last_status = VALUES(last_status),
        last_check = VALUES(last_check),
        last_response_time = VALUES(last_response_time),
        total_checks = total_checks + 1,
        total_up = total_up + ?,
        total_down = total_down + ?,
        uptime_percentage = (total_up + ?) / (total_checks + 1) * 100
    ");
    
    $is_up = ($status === 'up') ? 1 : 0;
    $is_down = ($status === 'down') ? 1 : 0;
    $uptime = $is_up * 100;
    
    $stmt->bind_param("isiiiiii", $server_id, $status, $response_time, $is_up, $is_down, $uptime, $is_up, $is_down, $is_up);
    $stmt->execute();
    $stmt->close();
}
?>
```

### 15.5 Main Dashboard (index.php)
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Monitoring Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Server Monitoring Dashboard</h1>
            <div class="actions">
                <button id="btnAddServer" class="btn btn-primary">Add Server</button>
                <button id="btnRefresh" class="btn btn-secondary">Refresh</button>
                <span id="lastUpdate">Last update: Never</span>
            </div>
        </header>

        <div id="map"></div>

        <div class="server-list">
            <h2>Server Status</h2>
            <table id="serverTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Response Time</th>
                        <th>Uptime</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="serverTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Server Modal -->
    <div id="addServerModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New Server</h2>
            <form id="addServerForm">
                <div class="form-group">
                    <label>Server Name:</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>IP Address:</label>
                    <input type="text" name="ip_address" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>Click on map to select location:</label>
                    <div id="modalMap"></div>
                    <input type="hidden" name="latitude" id="latitude" required>
                    <input type="hidden" name="longitude" id="longitude" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
```

### 15.6 CSS Styles (assets/css/style.css)
```css
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f5f5f5;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

header {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

h1 {
    color: #333;
    font-size: 24px;
}

.actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

#map {
    height: 500px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.server-list {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background: #f8f9fa;
    font-weight: 600;
}

.status-up {
    color: #28a745;
    font-weight: bold;
}

.status-down {
    color: #dc3545;
    font-weight: bold;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 600px;
    max-width: 90%;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

#modalMap {
    height: 300px;
    margin: 10px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

#lastUpdate {
    color: #666;
    font-size: 14px;
}
```

### 15.7 JavaScript Application (assets/js/app.js)
```javascript
let map, modalMap;
let markers = [];
let lines = [];
let selectedLocation = null;
const centerLat = -6.2088; // Jakarta
const centerLng = 106.8456;

$(document).ready(function() {
    initMap();
    loadServers();
    
    // Auto refresh every 30 seconds
    setInterval(loadServers, 30000);
    
    // Event handlers
    $('#btnRefresh').click(loadServers);
    $('#btnAddServer').click(openAddServerModal);
    $('.close').click(closeModal);
    $('#addServerForm').submit(handleAddServer);
});

function initMap() {
    // Main map
    map = L.map('map').setView([centerLat, centerLng], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
}

function initModalMap() {
    if (!modalMap) {
        modalMap = L.map('modalMap').setView([centerLat, centerLng], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(modalMap);
        
        modalMap.on('click', function(e) {
            if (selectedLocation) {
                modalMap.removeLayer(selectedLocation);
            }
            
            selectedLocation = L.marker([e.latlng.lat, e.latlng.lng]).addTo(modalMap);
            $('#latitude').val(e.latlng.lat);
            $('#longitude').val(e.latlng.lng);
        });
    }
    
    setTimeout(function() {
        modalMap.invalidateSize();
    }, 200);
}

function loadServers() {
    $.ajax({
        url: 'api/get_servers.php',
        method: 'GET',
        success: function(response) {
            updateMap(response.servers);
            updateTable(response.servers);
            $('#lastUpdate').text('Last update: ' + new Date().toLocaleTimeString());
        },
        error: function() {
            alert('Failed to load servers');
        }
    });
}

function updateMap(servers) {
    // Clear existing markers and lines
    markers.forEach(marker => map.removeLayer(marker));
    lines.forEach(line => map.removeLayer(line));
    markers = [];
    lines = [];
    
    servers.forEach(server => {
        const isUp = server.status === 'up';
        const color = isUp ? 'green' : 'red';
        
        // Add marker
        const marker = L.circleMarker([server.latitude, server.longitude], {
            color: color,
            fillColor: color,
            fillOpacity: 0.8,
            radius: 8
        }).addTo(map);
        
        marker.bindPopup(`
            <strong>${server.name || 'Server'}</strong><br>
            IP: ${server.ip_address}<br>
            Status: ${server.status}<br>
            Response: ${server.response_time || 'N/A'} ms<br>
            Uptime: ${server.uptime || '0'}%
        `);
        
        markers.push(marker);
        
        // Add connection line
        const line = L.polyline([
            [centerLat, centerLng],
            [server.latitude, server.longitude]
        ], {
            color: color,
            weight: 2,
            opacity: 0.6
        }).addTo(map);
        
        lines.push(line);
    });
}

function updateTable(servers) {
    const tbody = $('#serverTableBody');
    tbody.empty();
    
    servers.forEach(server => {
        const statusClass = server.status === 'up' ? 'status-up' : 'status-down';
        const row = `
            <tr>
                <td>${server.name || '-'}</td>
                <td>${server.ip_address}</td>
                <td class="${statusClass}">${server.status.toUpperCase()}</td>
                <td>${server.response_time || '-'} ms</td>
                <td>${server.uptime || '0'}%</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteServer(${server.id})">Delete</button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function openAddServerModal() {
    $('#addServerModal').show();
    initModalMap();
}

function closeModal() {
    $('#addServerModal').hide();
    $('#addServerForm')[0].reset();
    if (selectedLocation) {
        modalMap.removeLayer(selectedLocation);
        selectedLocation = null;
    }
}

function handleAddServer(e) {
    e.preventDefault();
    
    const formData = {
        name: $('[name="name"]').val(),
        ip_address: $('[name="ip_address"]').val(),
        description: $('[name="description"]').val(),
        latitude: $('#latitude').val(),
        longitude: $('#longitude').val()
    };
    
    if (!formData.latitude || !formData.longitude) {
        alert('Please select location on map');
        return;
    }
    
    $.ajax({
        url: 'api/add_server.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                closeModal();
                loadServers();
                alert('Server added successfully');
            } else {
                alert(response.message || 'Failed to add server');
            }
        },
        error: function() {
            alert('Failed to add server');
        }
    });
}

function deleteServer(id) {
    if (!confirm('Are you sure you want to delete this server?')) {
        return;
    }
    
    $.ajax({
        url: 'api/delete_server.php',
        method: 'POST',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                loadServers();
            } else {
                alert('Failed to delete server');
            }
        }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        closeModal();
    }
}
```

### 15.8 API Get Servers (api/get_servers.php)
```php
<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

$conn = getConnection();

$query = "
    SELECT 
        s.*,
        ss.last_status as status,
        ss.last_response_time as response_time,
        ss.uptime_percentage as uptime
    FROM servers s
    LEFT JOIN server_stats ss ON s.id = ss.server_id
    WHERE s.is_active = TRUE
    ORDER BY s.id DESC
";

$result = $conn->query($query);
$servers = [];

while ($row = $result->fetch_assoc()) {
    $servers[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'ip_address' => $row['ip_address'],
        'latitude' => floatval($row['latitude']),
        'longitude' => floatval($row['longitude']),
        'description' => $row['description'],
        'status' => $row['status'] ?: 'unknown',
        'response_time' => $row['response_time'],
        'uptime' => $row['uptime'] ? number_format($row['uptime'], 2) : '0.00'
    ];
}

echo json_encode(['success' => true, 'servers' => $servers]);
$conn->close();
?>
```

### 15.9 API Add Server (api/add_server.php)
```php
<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$conn = getConnection();

$name = $_POST['name'] ?? '';
$ip_address = $_POST['ip_address'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';
$description = $_POST['description'] ?? '';

// Validate IP
if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
    echo json_encode(['success' => false, 'message' => 'Invalid IP address']);
    exit;
}

// Insert server
$stmt = $conn->prepare("INSERT INTO servers (name, ip_address, latitude, longitude, description) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssdds", $name, $ip_address, $latitude, $longitude, $description);

if ($stmt->execute()) {
    $server_id = $conn->insert_id;
    
    // Initial ping check
    $ping_result = pingServer($ip_address);
    
    // Log ping result
    $log_stmt = $conn->prepare("INSERT INTO ping_logs (server_id, status, response_time) VALUES (?, ?, ?)");
    $log_stmt->bind_param("isi", $server_id, $ping_result['status'], $ping_result['response_time']);
    $log_stmt->execute();
    
    // Update stats
    updateServerStats($conn, $server_id, $ping_result['status'], $ping_result['response_time']);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add server']);
}

$stmt->close();
$conn->close();
?>
```

### 15.10 API Delete Server (api/delete_server.php)
```php
<?php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';

$conn = getConnection();

$id = $_POST['id'] ?? 0;

if ($id) {
    $stmt = $conn->prepare("UPDATE servers SET is_active = FALSE WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
}

$conn->close();
?>
```

### 15.11 Cron Script (cron/ping_check.php)
```php
<?php
require_once dirname(__FILE__) . '/../includes/db_connect.php';
require_once dirname(__FILE__) . '/../includes/functions.php';

$conn = getConnection();

// Get all active servers
$query = "SELECT id, ip_address FROM servers WHERE is_active = TRUE";
$result = $conn->query($query);

while ($server = $result->fetch_assoc()) {
    $server_id = $server['id'];
    $ip = $server['ip_address'];
    
    // Ping server with retry
    $max_retries = 3;
    $ping_result = null;
    
    for ($i = 0; $i < $max_retries; $i++) {
        $ping_result = pingServer($ip);
        if ($ping_result['status'] === 'up') {
            break;
        }
        sleep(1); // Wait 1 second before retry
    }
    
    // Log result
    $stmt = $conn->prepare("INSERT INTO ping_logs (server_id, status, response_time) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $server_id, $ping_result['status'], $ping_result['response_time']);
    $stmt->execute();
    $stmt->close();
    
    // Update stats
    updateServerStats($conn, $server_id, $ping_result['status'], $ping_result['response_time']);
    
    echo "Checked server $ip: " . $ping_result['status'] . "\n";
}

$conn->close();
?>
```

### 15.12 Setup Instructions
```bash
# 1. Clone atau extract ke folder web server
cd /var/www/html
mkdir uptime-php

# 2. Import database
mysql -u root -p < sql/schema.sql

# 3. Update config/database.php dengan kredensial MySQL

# 4. Set permissions
chmod 755 cron/ping_check.php

# 5. Setup cron job (jalankan setiap menit)
crontab -e
# Tambahkan:
* * * * * /usr/bin/php /var/www/html/uptime-php/cron/ping_check.php

# 6. Akses aplikasi
http://localhost/uptime-php/
```