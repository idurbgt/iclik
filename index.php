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
            <h1>Server Monitoring System</h1>
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
                        <th>Last Check</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="serverTableBody">
                    <tr>
                        <td colspan="7" class="text-center">No servers configured. Click "Add Server" to begin.</td>
                    </tr>
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
                    <input type="text" name="name" placeholder="e.g., Web Server 1" required>
                </div>
                <div class="form-group">
                    <label>IP Address:</label>
                    <input type="text" name="ip_address" placeholder="e.g., 192.168.1.1" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" placeholder="Optional description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Server Location (click on map to select):</label>
                    <div id="modalMap"></div>
                    <input type="hidden" name="latitude" id="latitude" required>
                    <input type="hidden" name="longitude" id="longitude" required>
                    <div id="locationInfo" class="location-info"></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Server</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>