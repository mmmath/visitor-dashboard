<?php
header('Content-Type: application/json');

// ============ DATABASE CONFIGURATION ============
// CHANGE THESE TO YOUR DATABASE CREDENTIALS
$db_host = 'localhost';           // Usually 'localhost'
$db_user = 'your_db_user';        // Your database username
$db_password = 'your_db_password'; // Your database password
$db_name = 'visitor_dashboard';    // Your database name

// ============ CONNECT TO DATABASE ============
try {
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Connection error: ' . $e->getMessage()
    ]));
}

// ============ GET ACTION ============
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_visitor_data') {
    getVisitorData($conn);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}

// ============ FUNCTION: GET VISITOR DATA ============
function getVisitorData($conn) {
    // Get visitor IP
    $ip = getClientIP();
    
    // Check if IP already exists in database
    $stmt = $conn->prepare("SELECT city, country, latitude, longitude FROM visitors WHERE ip = ? ORDER BY last_visit DESC LIMIT 1");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // IP found in database - use cached data
        $row = $result->fetch_assoc();
        $data = [
            'success' => true,
            'ip' => $ip,
            'city' => $row['city'],
            'country' => $row['country'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'cached' => true
        ];
        
        // Update last visit time
        $now = date('Y-m-d H:i:s');
        $update_stmt = $conn->prepare("UPDATE visitors SET last_visit = ? WHERE ip = ?");
        $update_stmt->bind_param("ss", $now, $ip);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // IP not in database - fetch from geolocation API
        $geo_data = getGeolocation($ip);
        
        if ($geo_data['success']) {
            // Insert new visitor record
            $now = date('Y-m-d H:i:s');
            $insert_stmt = $conn->prepare(
                "INSERT INTO visitors (ip, city, country, latitude, longitude, first_visit, last_visit) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $insert_stmt->bind_param(
                "sssddss",
                $ip,
                $geo_data['city'],
                $geo_data['country'],
                $geo_data['latitude'],
                $geo_data['longitude'],
                $now,
                $now
            );
            $insert_stmt->execute();
            $insert_stmt->close();
            
            $data = [
                'success' => true,
                'ip' => $ip,
                'city' => $geo_data['city'],
                'country' => $geo_data['country'],
                'latitude' => $geo_data['latitude'],
                'longitude' => $geo_data['longitude'],
                'cached' => false
            ];
        } else {
            $data = [
                'success' => false,
                'message' => 'Could not fetch geolocation data'
            ];
        }
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode($data);
}

// ============ FUNCTION: GET CLIENT IP ============
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($ip);
}

// ============ FUNCTION: GET GEOLOCATION ============
function getGeolocation($ip) {
    // Using free IP Geolocation API (no key required)
    $url = "https://ipapi.co/{$ip}/json/";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'VisitorDashboard/1.0');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'city' => $data['city'] ?? 'Unknown',
            'country' => $data['country_name'] ?? 'Unknown',
            'latitude' => floatval($data['latitude'] ?? 0),
            'longitude' => floatval($data['longitude'] ?? 0)
        ];
    }
    
    return ['success' => false];
}
?>