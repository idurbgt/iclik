// Global variables
let map, modalMap;
let markers = [];
let lines = [];
let selectedLocation = null;
let selectedMarker = null;

// Center point (Jakarta, Indonesia)
const centerLat = -6.2088;
const centerLng = 106.8456;

// Initialize when document is ready
$(document).ready(function() {
    initMap();
    loadServers();
    
    // Auto refresh every 30 seconds
    setInterval(loadServers, 30000);
    
    // Event handlers
    $('#btnRefresh').click(function() {
        $(this).html('Refreshing...').prop('disabled', true);
        loadServers();
    });
    
    $('#btnAddServer').click(openAddServerModal);
    $('.close').click(closeModal);
    $('#addServerForm').submit(handleAddServer);
    
    // Close modal when clicking outside
    $(window).click(function(event) {
        if (event.target.className === 'modal') {
            closeModal();
        }
    });
});

// Initialize main map
function initMap() {
    map = L.map('map').setView([centerLat, centerLng], 5);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);
    
    // Add center marker
    L.marker([centerLat, centerLng], {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map).bindPopup('<strong>Monitoring Center</strong><br>Jakarta, Indonesia');
}

// Initialize modal map
function initModalMap() {
    if (!modalMap) {
        setTimeout(function() {
            modalMap = L.map('modalMap').setView([centerLat, centerLng], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(modalMap);
            
            // Handle map click
            modalMap.on('click', function(e) {
                if (selectedMarker) {
                    modalMap.removeLayer(selectedMarker);
                }
                
                selectedMarker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(modalMap);
                $('#latitude').val(e.latlng.lat);
                $('#longitude').val(e.latlng.lng);
                
                // Show location info
                $('#locationInfo').addClass('active').html(
                    'Location selected: ' + 
                    'Lat: ' + e.latlng.lat.toFixed(6) + ', ' +
                    'Lng: ' + e.latlng.lng.toFixed(6)
                );
            });
        }, 100);
    }
    
    setTimeout(function() {
        if (modalMap) {
            modalMap.invalidateSize();
        }
    }, 300);
}

// Load servers from API
function loadServers() {
    $.ajax({
        url: 'api/get_servers.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateMap(response.servers);
                updateTable(response.servers);
                $('#lastUpdate').text('Last update: ' + new Date().toLocaleTimeString());
            } else {
                console.error('Failed to load servers:', response.message);
            }
            $('#btnRefresh').html('Refresh').prop('disabled', false);
        },
        error: function(xhr, status, error) {
            console.error('Failed to load servers:', error);
            $('#btnRefresh').html('Refresh').prop('disabled', false);
        }
    });
}

// Update map with server markers and lines
function updateMap(servers) {
    // Clear existing markers and lines
    markers.forEach(marker => map.removeLayer(marker));
    lines.forEach(line => map.removeLayer(line));
    markers = [];
    lines = [];
    
    servers.forEach(server => {
        const isUp = server.status === 'up';
        const color = isUp ? 'green' : (server.status === 'down' ? 'red' : 'gray');
        
        // Create custom icon
        const icon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });
        
        // Add marker
        const marker = L.marker([server.latitude, server.longitude], { icon: icon }).addTo(map);
        
        // Popup content
        const popupContent = `
            <div style="min-width: 200px;">
                <strong>${server.name || 'Server'}</strong><br>
                <hr style="margin: 5px 0;">
                <b>IP:</b> ${server.ip_address}<br>
                <b>Status:</b> <span class="${isUp ? 'status-up' : (server.status === 'down' ? 'status-down' : 'status-unknown')}">${server.status.toUpperCase()}</span><br>
                <b>Response:</b> ${server.response_time || 'N/A'} ms<br>
                <b>Uptime:</b> ${server.uptime}%<br>
                <b>Last Check:</b> ${server.last_check}<br>
                ${server.description ? '<b>Description:</b> ' + server.description : ''}
            </div>
        `;
        
        marker.bindPopup(popupContent);
        markers.push(marker);
        
        // Add connection line
        const line = L.polyline([
            [centerLat, centerLng],
            [server.latitude, server.longitude]
        ], {
            color: color,
            weight: 2,
            opacity: 0.6,
            dashArray: isUp ? null : '5, 10'
        }).addTo(map);
        
        lines.push(line);
    });
}

// Update server table
function updateTable(servers) {
    const tbody = $('#serverTableBody');
    tbody.empty();
    
    if (servers.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="7" class="text-center">No servers configured. Click "Add Server" to begin.</td>
            </tr>
        `);
        return;
    }
    
    servers.forEach(server => {
        const statusClass = server.status === 'up' ? 'status-up' : (server.status === 'down' ? 'status-down' : 'status-unknown');
        const row = `
            <tr>
                <td>${server.name || '-'}</td>
                <td>${server.ip_address}</td>
                <td><span class="${statusClass}">${server.status.toUpperCase()}</span></td>
                <td>${server.response_time !== null ? server.response_time + ' ms' : '-'}</td>
                <td>${server.uptime}%</td>
                <td>${server.last_check}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="deleteServer(${server.id})">Delete</button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Open add server modal
function openAddServerModal() {
    $('#addServerModal').show();
    initModalMap();
}

// Close modal
function closeModal() {
    $('#addServerModal').hide();
    $('#addServerForm')[0].reset();
    $('#locationInfo').removeClass('active').html('');
    
    if (selectedMarker && modalMap) {
        modalMap.removeLayer(selectedMarker);
        selectedMarker = null;
    }
}

// Handle add server form submission
function handleAddServer(e) {
    e.preventDefault();
    
    const formData = {
        name: $('[name="name"]').val(),
        ip_address: $('[name="ip_address"]').val(),
        description: $('[name="description"]').val(),
        latitude: $('#latitude').val(),
        longitude: $('#longitude').val()
    };
    
    // Validate location
    if (!formData.latitude || !formData.longitude) {
        alert('Please select server location on the map');
        return;
    }
    
    // Show loading
    const submitBtn = $('#addServerForm button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'api/add_server.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                closeModal();
                loadServers();
                
                // Show success message
                const status = response.initial_status === 'up' ? 'Online' : 'Offline';
                alert('Server added successfully!\nInitial status: ' + status);
            } else {
                alert(response.message || 'Failed to add server');
            }
            submitBtn.html(originalText).prop('disabled', false);
        },
        error: function(xhr, status, error) {
            alert('Failed to add server: ' + error);
            submitBtn.html(originalText).prop('disabled', false);
        }
    });
}

// Delete server
function deleteServer(id) {
    if (!confirm('Are you sure you want to delete this server?')) {
        return;
    }
    
    $.ajax({
        url: 'api/delete_server.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadServers();
            } else {
                alert(response.message || 'Failed to delete server');
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to delete server: ' + error);
        }
    });
}