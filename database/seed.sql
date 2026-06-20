USE smartcity;

-- Zones
INSERT INTO zones (name, city_district, coordinates, area_km2) VALUES
('zone1', 'Jakarta Pusat', '-6.2088,106.8456', 12.50),
('zone2', 'Jakarta Selatan', '-6.2615,106.8106', 15.30),
('zone3', 'Jakarta Barat', '-6.1675,106.7636', 18.20),
('zone4', 'Jakarta Utara', '-6.1382,106.8663', 14.70),
('zone5', 'Jakarta Timur', '-6.2250,106.9004', 20.10);

-- OAuth Clients
INSERT INTO oauth_clients (client_id, client_secret, grant_types) VALUES
('smartcity_client', 'SmartCity@OAuth#2024', 'password,client_credentials,refresh_token'),
('iot_device', 'SmartCity@MQTT#2024', 'client_credentials');

-- Citizens
INSERT INTO citizens (nik, name, email, password_hash, phone, zone_id, role) VALUES
('3171234567890001', 'Budi Santoso', 'budi@email.com', '$2y$10$examplehash1', '08111111111', 1, 'citizen'),
('3171234567890002', 'Siti Rahayu', 'siti@email.com', '$2y$10$examplehash2', '08122222222', 2, 'citizen'),
('3171234567890003', 'Dewi Putri', 'dewi@email.com', '$2y$10$examplehash3', '08133333333', 3, 'citizen'),
('3171234567890004', 'Ahmad Fauzi', 'ahmad@email.com', '$2y$10$examplehash4', '08144444444', 4, 'citizen'),
('3171234567890005', 'Admin Kota', 'admin@smartcity.com', '$2y$10$examplehash5', '08155555555', 1, 'admin');

-- Traffic Readings
INSERT INTO traffic_readings (zone_id, vehicle_density, avg_speed_kmh, source) VALUES
(1, 45.5, 30.2, 'iot'),(1, 78.3, 18.5, 'iot'),(1, 92.1, 12.0, 'iot'),(1, 55.0, 25.0, 'iot'),(1, 30.0, 45.0, 'iot'),
(2, 33.0, 45.0, 'iot'),(2, 55.5, 28.0, 'iot'),(2, 88.0, 15.0, 'iot'),(2, 60.0, 22.0, 'iot'),(2, 40.0, 38.0, 'iot'),
(3, 20.0, 55.0, 'iot'),(3, 60.0, 25.0, 'iot'),(3, 95.0, 10.5, 'iot'),(3, 70.0, 20.0, 'iot'),(3, 35.0, 42.0, 'iot'),
(4, 40.0, 38.0, 'iot'),(4, 70.0, 20.0, 'iot'),(4, 85.0, 14.0, 'iot'),(4, 50.0, 30.0, 'iot'),(4, 25.0, 50.0, 'iot'),
(5, 38.0, 40.0, 'iot'),(5, 65.0, 22.0, 'iot'),(5, 90.0, 11.0, 'iot'),(5, 48.0, 32.0, 'iot'),(5, 22.0, 52.0, 'iot');

-- Parking Zones
INSERT INTO parking_zones (name, zone_id, total_slots, type) VALUES
('Parkir Monas', 1, 100, 'umum'),
('Parkir Sudirman', 2, 80, 'umum'),
('Parkir Grogol', 3, 60, 'umum'),
('Parkir Kelapa Gading', 4, 120, 'vip'),
('Parkir Cawang', 5, 90, 'umum');

-- Parking Slots
INSERT INTO parking_slots (parking_zone_id, slot_number, status) VALUES
(1, 'A01', 'available'),(1, 'A02', 'available'),(1, 'A03', 'occupied'),(1, 'A04', 'available'),(1, 'A05', 'reserved'),
(2, 'B01', 'available'),(2, 'B02', 'occupied'),(2, 'B03', 'available'),(2, 'B04', 'available'),(2, 'B05', 'occupied'),
(3, 'C01', 'available'),(3, 'C02', 'available'),(3, 'C03', 'maintenance'),(3, 'C04', 'available'),(3, 'C05', 'available'),
(4, 'D01', 'available'),(4, 'D02', 'available'),(4, 'D03', 'available'),(4, 'D04', 'occupied'),(4, 'D05', 'available'),
(5, 'E01', 'available'),(5, 'E02', 'available'),(5, 'E03', 'available'),(5, 'E04', 'available'),(5, 'E05', 'occupied');

-- Incidents
INSERT INTO incidents (zone_id, type, severity, description) VALUES
(1, 'kemacetan_parah', 'tinggi', 'Kemacetan parah di Jalan Sudirman'),
(2, 'kecelakaan', 'kritis', 'Kecelakaan di perempatan Blok M'),
(3, 'jalan_rusak', 'sedang', 'Jalan berlubang di Grogol'),
(4, 'banjir', 'tinggi', 'Banjir setinggi 30cm di Kelapa Gading'),
(5, 'kemacetan_parah', 'rendah', 'Antrian panjang di Cawang');

-- Reports
INSERT INTO reports (citizen_id, category, description, zone_id, status) VALUES
(1, 'kemacetan',   'Macet parah di bundaran HI sejak pagi',        1, 'reported'),
(2, 'kecelakaan',  'Motor vs mobil di depan Blok M Plaza',         2, 'verified'),
(3, 'jalan_rusak', 'Ada lubang besar di Jl. Daan Mogot km 12',     3, 'in_progress'),
(4, 'parkir_liar', 'Parkir liar memenuhi badan jalan Sunter',      4, 'reported'),
(1, 'lainnya',     'Lampu merah mati di persimpangan Cawang',      5, 'resolved');

-- Notifications
INSERT INTO notifications (citizen_id, title, body, type, is_read) VALUES
(1, 'Peringatan Kemacetan Zone 1', 'Kepadatan kendaraan di Zone 1 melebihi ambang normal (92 kendaraan/menit). Hindari Jalan Sudirman.', 'warning',  0),
(2, 'Alert Kritis Zone 2',         'Anomali terdeteksi di Zone 2: kepadatan 88 kendaraan/menit. Insiden mungkin terjadi.',               'critical', 0),
(3, 'Info Lalu Lintas Zone 3',     'Kondisi Zone 3 saat ini padat. Perkiraan tiba lebih lama 15 menit.',                                 'info',     1),
(4, 'Peringatan Banjir Zone 4',    'Terdeteksi potensi banjir di Zone 4. Hindari kawasan Kelapa Gading untuk sementara.',                'critical', 0),
(1, 'Slot Parkir Tersedia',        'Slot parkir A04 di Parkir Monas kini tersedia.',                                                     'info',     1);

-- Parking Reservations
INSERT INTO parking_reservations (citizen_id, slot_id, reserved_at, checked_in_at, checked_out_at, duration_minutes, status) VALUES
(1, 1, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR, 60,   'completed'),
(2, 6, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 3 HOUR, NULL,                    NULL, 'active'),
(3, 5, NOW(),                   NULL,                    NULL,                    NULL, 'reserved');