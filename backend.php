<?php
header('Content-Type: application/json');
date_default_timezone_set('UTC');

// ============ DATABASE CONFIGURATION ============
// CHANGE THESE TO YOUR DATABASE CREDENTIALS
$db_host = 'localhost';           // Usually 'localhost'
$db_user = 'your_db_user';        // Your database username
$db_password = 'your_db_password'; // Your database password
$db_name = 'visitor_dashboard';    // Your database name

// ============ CONNECT TO DATABASE ============
try {
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    $conn->set_charset("utf8mb4");
    
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
    $now_utc = gmdate('Y-m-d H:i:s');
    
    // Step 1: Check if IP exists in visitor_ips table
    $stmt = $conn->prepare("SELECT city, country, latitude, longitude FROM visitor_ips WHERE ip = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ip_exists = false;
    $city = null;
    $country = null;
    $latitude = null;
    $longitude = null;
    
    if ($result->num_rows > 0) {
        $ip_exists = true;
        $row = $result->fetch_assoc();
        $city = $row['city'];
        $country = $row['country'];
        $latitude = $row['latitude'];
        $longitude = $row['longitude'];
    } else {
        // IP not found - fetch from geolocation API
        $geo_data = getGeolocation($ip);
        
        if (!$geo_data['success']) {
            echo json_encode([
                'success' => false,
                'message' => 'Could not fetch geolocation data'
            ]);
            $stmt->close();
            $conn->close();
            return;
        }
        
        // Insert new IP record
        $city = $geo_data['city'];
        $country = $geo_data['country'];
        $latitude = $geo_data['latitude'];
        $longitude = $geo_data['longitude'];
        
        $insert_stmt = $conn->prepare(
            "INSERT INTO visitor_ips (ip, city, country, latitude, longitude) VALUES (?, ?, ?, ?, ?)"
        );
        $insert_stmt->bind_param("sssdd", $ip, $city, $country, $latitude, $longitude);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Step 2: Check if recent weather data exists (within 3 minutes)
    $use_cached_weather = false;
    $weather_data = null;
    $last_weather_time = null;
    
    $weather_stmt = $conn->prepare(
        "SELECT current_temp, weather_code, weather_description, today_forecast, visit_time 
         FROM visitor_weather WHERE ip = ? ORDER BY visit_time DESC LIMIT 1"
    );
    $weather_stmt->bind_param("s", $ip);
    $weather_stmt->execute();
    $weather_result = $weather_stmt->get_result();
    
    if ($weather_result->num_rows > 0) {
        $weather_row = $weather_result->fetch_assoc();
        $last_weather_time = strtotime($weather_row['visit_time']);
        $current_time = strtotime($now_utc);
        $time_diff_minutes = ($current_time - $last_weather_time) / 60;
        
        if ($time_diff_minutes < 3) {
            // Use cached weather
            $use_cached_weather = true;
            $weather_data = [
                'current_temp' => $weather_row['current_temp'],
                'weather_code' => $weather_row['weather_code'],
                'weather_description' => $weather_row['weather_description'],
                'today_forecast' => json_decode($weather_row['today_forecast'], true),
                'cached' => true
            ];
        }
    }
    
    $weather_stmt->close();
    
    // Step 3: If no cached weather, fetch from API
    if (!$use_cached_weather) {
        $weather_data = getWeatherData($latitude, $longitude);
        
        if ($weather_data['success']) {
            // Insert weather record
            $current_temp = $weather_data['current_temp'];
            $weather_code = $weather_data['weather_code'];
            $weather_desc = $weather_data['weather_description'];
            $forecast_json = json_encode($weather_data['today_forecast']);
            
            $insert_weather = $conn->prepare(
                "INSERT INTO visitor_weather (ip, visit_time, current_temp, weather_code, weather_description, today_forecast, latitude, longitude) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insert_weather->bind_param(
                "ssiisdd",
                $ip,
                $now_utc,
                $current_temp,
                $weather_code,
                $weather_desc,
                $forecast_json,
                $latitude,
                $longitude
            );
            $insert_weather->execute();
            $insert_weather->close();
            
            $weather_data['cached'] = false;
        } else {
            $weather_data['cached'] = false;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    // Prepare response
    $response = [
        'success' => true,
        'ip' => $ip,
        'city' => $city,
        'country' => $country,
        'latitude' => floatval($latitude),
        'longitude' => floatval($longitude),
        'ip_cached' => $ip_exists,
        'weather' => $weather_data
    ];
    
    echo json_encode($response);
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

// ============ FUNCTION: GET WEATHER DATA ============
function getWeatherData($lat, $lon) {
    // Using Open-Meteo free API (no key required)
    $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,weather_code,is_day&daily=weather_code,temperature_2m_max,temperature_2m_min,weather_code,precipitation_sum&timezone=UTC";
    
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
        
        if ($data && isset($data['current'])) {
            $current = $data['current'];
            $temp = round($current['temperature_2m'], 2);
            $weather_code = $current['weather_code'];
            $description = getWeatherDescription($weather_code);
            
            // Build today's forecast
            $today_forecast = [];
            if (isset($data['daily'])) {
                $daily = $data['daily'];
                $today_forecast = [
                    'temp_max' => $daily['temperature_2m_max'][0] ?? null,
                    'temp_min' => $daily['temperature_2m_min'][0] ?? null,
                    'precipitation' => $daily['precipitation_sum'][0] ?? 0,
                    'weather_code' => $daily['weather_code'][0] ?? $weather_code
                ];
            }
            
            return [
                'success' => true,
                'current_temp' => $temp,
                'weather_code' => $weather_code,
                'weather_description' => $description,
                'today_forecast' => $today_forecast
            ];
        }
    }
    
    return ['success' => false];
}

// ============ FUNCTION: GET WEATHER DESCRIPTION ============
function getWeatherDescription($code) {
    // WMO Weather codes
    $descriptions = [
        0 => 'Clear sky',
        1 => 'Mainly clear',
        2 => 'Partly cloudy',
        3 => 'Overcast',
        45 => 'Foggy',
        48 => 'Foggy',
        51 => 'Light drizzle',
        53 => 'Moderate drizzle',
        55 => 'Dense drizzle',
        61 => 'Slight rain',
        63 => 'Moderate rain',
        65 => 'Heavy rain',
        71 => 'Slight snow',
        73 => 'Moderate snow',
        75 => 'Heavy snow',
        80 => 'Slight showers',
        81 => 'Moderate showers',
        82 => 'Heavy showers',
        85 => 'Slight snow showers',
        86 => 'Heavy snow showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with hail',
        99 => 'Thunderstorm with hail'
    ];
    
    return $descriptions[$code] ?? 'Unknown';
}
?>