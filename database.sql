-- Create two separate tables for visitor data
-- Table 1: IP and Geolocation
CREATE TABLE visitor_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL UNIQUE,
    city VARCHAR(100),
    country VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Weather and Visit Data (current temp, forecast, visit time in UTC)
CREATE TABLE visitor_weather (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    visit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    current_temp DECIMAL(5, 2),
    weather_code INT,
    weather_description VARCHAR(100),
    today_forecast JSON,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ip) REFERENCES visitor_ips(ip) ON DELETE CASCADE,
    INDEX idx_ip (ip),
    INDEX idx_visit_time (visit_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;