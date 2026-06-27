CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

CREATE TABLE zones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  city_district VARCHAR(100),
  coordinates VARCHAR(100),
  area_km2 DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE citizens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nik VARCHAR(16) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  zone_id INT,
  role ENUM('citizen','admin') DEFAULT 'citizen',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_email (email),
  INDEX idx_zone_id (zone_id)
);

CREATE TABLE reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  category ENUM('kemacetan','kecelakaan','jalan_rusak','parkir_liar','lainnya'),
  description TEXT,
  zone_id INT,
  latitude DECIMAL(10,7),
  longitude DECIMAL(10,7),
  status ENUM('reported','verified','in_progress','resolved') DEFAULT 'reported',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_status (status),
  INDEX idx_zone_id (zone_id)
);

CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT,
  type ENUM('info','warning','critical') DEFAULT 'info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  INDEX idx_citizen_id (citizen_id),
  INDEX idx_is_read (is_read)
);

CREATE TABLE traffic_readings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT NOT NULL,
  vehicle_density DECIMAL(8,2),
  avg_speed_kmh DECIMAL(5,2),
  incident_flag TINYINT(1) DEFAULT 0,
  source ENUM('sensor','manual','iot') DEFAULT 'iot',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_zone_id (zone_id),
  INDEX idx_recorded_at (recorded_at)
);

CREATE TABLE incidents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_id INT NOT NULL,
  type ENUM('kecelakaan','kemacetan_parah','jalan_rusak','banjir','lainnya'),
  severity ENUM('rendah','sedang','tinggi','kritis') DEFAULT 'sedang',
  description TEXT,
  reported_by INT,
  resolved_at TIMESTAMP NULL,
  reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id),
  INDEX idx_zone_id (zone_id),
  INDEX idx_severity (severity)
);

CREATE TABLE parking_zones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  zone_id INT NOT NULL,
  total_slots INT DEFAULT 0,
  type ENUM('umum','khusus','vip') DEFAULT 'umum',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE parking_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parking_zone_id INT NOT NULL,
  slot_number VARCHAR(10) NOT NULL,
  status ENUM('available','occupied','reserved','maintenance') DEFAULT 'available',
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parking_zone_id) REFERENCES parking_zones(id),
  INDEX idx_status (status),
  INDEX idx_parking_zone_id (parking_zone_id)
);

CREATE TABLE parking_reservations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citizen_id INT NOT NULL,
  slot_id INT NOT NULL,
  reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  checked_in_at TIMESTAMP NULL,
  checked_out_at TIMESTAMP NULL,
  duration_minutes INT NULL,
  status ENUM('reserved','active','completed','cancelled') DEFAULT 'reserved',
  FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  FOREIGN KEY (slot_id) REFERENCES parking_slots(id),
  INDEX idx_citizen_id (citizen_id),
  INDEX idx_status (status)
);

CREATE TABLE oauth_clients (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100) UNIQUE NOT NULL,
  client_secret VARCHAR(255) NOT NULL,
  grant_types VARCHAR(200) DEFAULT 'password,client_credentials,refresh_token',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE oauth_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(100) NOT NULL,
  user_id INT NULL COMMENT 'citizens.id — NULL untuk client_credentials',
  access_token VARCHAR(512) UNIQUE NOT NULL,
  refresh_token VARCHAR(512) NULL,
  scope VARCHAR(100) DEFAULT 'read write',
  expires_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizens(id) ON DELETE SET NULL,
  INDEX idx_access_token (access_token),
  INDEX idx_refresh_token (refresh_token),
  INDEX idx_expires_at (expires_at),
  INDEX idx_revoked_at (revoked_at)
);