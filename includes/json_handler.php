<?php
/**
 * JSON File Handler for data storage
 */

class JsonHandler {
    private $dataDir;
    
    public function __construct() {
        $this->dataDir = dirname(__DIR__) . '/data/';
        
        // Ensure data directory exists
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Read JSON file and return as array
     */
    public function readFile($filename) {
        $filepath = $this->dataDir . $filename;
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $content = file_get_contents($filepath);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Write array to JSON file
     */
    public function writeFile($filename, $data) {
        $filepath = $this->dataDir . $filename;
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        return file_put_contents($filepath, $json) !== false;
    }
    
    /**
     * Get all servers
     */
    public function getServers() {
        $servers = $this->readFile('servers.json');
        // Filter only active servers
        return array_filter($servers, function($server) {
            return isset($server['is_active']) && $server['is_active'] === true;
        });
    }
    
    /**
     * Get server by ID
     */
    public function getServerById($id) {
        $servers = $this->readFile('servers.json');
        foreach ($servers as $server) {
            if ($server['id'] == $id && $server['is_active']) {
                return $server;
            }
        }
        return null;
    }
    
    /**
     * Add new server
     */
    public function addServer($data) {
        $servers = $this->readFile('servers.json');
        
        // Generate new ID
        $maxId = 0;
        foreach ($servers as $server) {
            if ($server['id'] > $maxId) {
                $maxId = $server['id'];
            }
        }
        
        $newServer = [
            'id' => $maxId + 1,
            'name' => $data['name'] ?? '',
            'ip_address' => $data['ip_address'],
            'latitude' => floatval($data['latitude']),
            'longitude' => floatval($data['longitude']),
            'description' => $data['description'] ?? '',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_status' => 'unknown',
            'last_check' => null,
            'last_response_time' => null,
            'total_checks' => 0,
            'total_up' => 0,
            'total_down' => 0,
            'uptime_percentage' => 0
        ];
        
        // Check if IP already exists
        foreach ($servers as $server) {
            if ($server['ip_address'] === $data['ip_address'] && $server['is_active']) {
                return ['success' => false, 'message' => 'IP address already exists'];
            }
        }
        
        $servers[] = $newServer;
        
        if ($this->writeFile('servers.json', $servers)) {
            return ['success' => true, 'server_id' => $newServer['id']];
        }
        
        return ['success' => false, 'message' => 'Failed to save server'];
    }
    
    /**
     * Update server
     */
    public function updateServer($id, $data) {
        $servers = $this->readFile('servers.json');
        
        foreach ($servers as &$server) {
            if ($server['id'] == $id) {
                foreach ($data as $key => $value) {
                    $server[$key] = $value;
                }
                $server['updated_at'] = date('Y-m-d H:i:s');
                
                if ($this->writeFile('servers.json', $servers)) {
                    return ['success' => true];
                }
                break;
            }
        }
        
        return ['success' => false, 'message' => 'Server not found'];
    }
    
    /**
     * Delete server (soft delete)
     */
    public function deleteServer($id) {
        $servers = $this->readFile('servers.json');
        $found = false;
        
        foreach ($servers as &$server) {
            if ($server['id'] == $id) {
                $server['is_active'] = false;
                $server['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if ($found && $this->writeFile('servers.json', $servers)) {
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Server not found'];
    }
    
    /**
     * Add ping log
     */
    public function addPingLog($server_id, $status, $response_time) {
        $logs = $this->readFile('ping_logs.json');
        
        $newLog = [
            'id' => count($logs) + 1,
            'server_id' => $server_id,
            'status' => $status,
            'response_time' => $response_time,
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
        $logs[] = $newLog;
        
        // Keep only last 1000 logs per server to prevent file from growing too large
        $serverLogs = [];
        foreach ($logs as $log) {
            if (!isset($serverLogs[$log['server_id']])) {
                $serverLogs[$log['server_id']] = [];
            }
            $serverLogs[$log['server_id']][] = $log;
        }
        
        $filteredLogs = [];
        foreach ($serverLogs as $serverId => $serverLogList) {
            // Keep only last 100 logs per server
            $serverLogList = array_slice($serverLogList, -100);
            $filteredLogs = array_merge($filteredLogs, $serverLogList);
        }
        
        $this->writeFile('ping_logs.json', $filteredLogs);
        
        return true;
    }
    
    /**
     * Get ping logs for a server
     */
    public function getPingLogs($server_id, $limit = 100) {
        $logs = $this->readFile('ping_logs.json');
        $serverLogs = [];
        
        foreach ($logs as $log) {
            if ($log['server_id'] == $server_id) {
                $serverLogs[] = $log;
            }
        }
        
        // Sort by date descending
        usort($serverLogs, function($a, $b) {
            return strtotime($b['checked_at']) - strtotime($a['checked_at']);
        });
        
        return array_slice($serverLogs, 0, $limit);
    }
    
    /**
     * Update server statistics
     */
    public function updateServerStats($server_id, $status, $response_time) {
        $servers = $this->readFile('servers.json');
        
        foreach ($servers as &$server) {
            if ($server['id'] == $server_id) {
                $server['last_status'] = $status;
                $server['last_check'] = date('Y-m-d H:i:s');
                $server['last_response_time'] = $response_time;
                $server['total_checks'] = ($server['total_checks'] ?? 0) + 1;
                
                if ($status === 'up') {
                    $server['total_up'] = ($server['total_up'] ?? 0) + 1;
                } else {
                    $server['total_down'] = ($server['total_down'] ?? 0) + 1;
                }
                
                if ($server['total_checks'] > 0) {
                    $server['uptime_percentage'] = round(($server['total_up'] / $server['total_checks']) * 100, 2);
                }
                
                $this->writeFile('servers.json', $servers);
                break;
            }
        }
    }
}
?>