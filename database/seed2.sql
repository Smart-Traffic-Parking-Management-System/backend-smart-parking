-- Cek dulu isi tabel zones
SELECT * FROM zones;

-- Kalau kosong, insert zone_id = 1
INSERT INTO zones (id, name, created_at, updated_at) 
VALUES (1, 'Zone Utama', NOW(), NOW());

-- Atau sesuaikan kolom dengan schema zones kamu
DESCRIBE zones;