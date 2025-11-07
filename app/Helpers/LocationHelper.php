<?php

namespace App\Helpers;

class LocationHelper
{
    /**
     * حساب المسافة بين نقطتين باستخدام Haversine Formula
     * 
     * @param float $lat1 خط عرض النقطة الأولى
     * @param float $lon1 خط طول النقطة الأولى
     * @param float $lat2 خط عرض النقطة الثانية
     * @param float $lon2 خط طول النقطة الثانية
     * @param string $unit وحدة القياس: 'km' أو 'miles'
     * @return float المسافة
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'km')
    {
        // نصف قطر الأرض
        $earthRadius = ($unit === 'km') ? 6371 : 3959;

        // تحويل الدرجات إلى Radians
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // الفرق بين النقطتين
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        // Haversine Formula (العملية الحسابية اللي أستاذك قصدها)
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1) * cos($lat2) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }
}
