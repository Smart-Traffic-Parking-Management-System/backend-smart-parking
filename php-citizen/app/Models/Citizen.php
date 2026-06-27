<?php
namespace App\Models;

class Citizen {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO citizens (nik, name, email, password_hash, phone, zone_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $data['nik'], $data['name'], $data['email'], 
            $hashedPassword, $data['phone'], $data['zone_id']
        ]);
        return $this->db->lastInsertId();
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM citizens WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM citizens WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id' && $key !== 'password') {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE citizens SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
}