<?php

namespace App\Validators;

class TrafficValidator
{
    public static function validateReading(array $data): array
    {
        $errors = [];

        if (empty($data['zone_id']) || !is_numeric($data['zone_id'])) {
            $errors[] = 'zone_id wajib diisi dan harus berupa angka';
        }

        if (!isset($data['vehicle_density']) || !is_numeric($data['vehicle_density'])) {
            $errors[] = 'vehicle_density wajib diisi dan harus berupa angka';
        }

        if (!isset($data['avg_speed_kmh']) || !is_numeric($data['avg_speed_kmh'])) {
            $errors[] = 'avg_speed_kmh wajib diisi dan harus berupa angka';
        }

        return $errors;
    }
}
