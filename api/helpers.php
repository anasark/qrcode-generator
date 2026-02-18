<?php
/* ===================================================
   QR Code Generator — Shared Helper Functions
   Used by redirect handler & tracking API
   =================================================== */

/**
 * Get real client IP behind proxies / Cloudflare
 */
function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // Proxy
        'HTTP_X_REAL_IP',           // Nginx proxy
        'HTTP_CLIENT_IP',           // Other proxy
        'REMOTE_ADDR',              // Direct
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Geo lookup via ip-api.com (free, no API key needed)
 */
function getGeoData($ip) {
    $default = ['country' => null, 'city' => null, 'region' => null, 'lat' => null, 'lon' => null];

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $default;
    }

    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon", false, $ctx);
        if (!$json) return $default;

        $data = json_decode($json, true);
        if (!$data || ($data['status'] ?? '') !== 'success') return $default;

        return [
            'country' => $data['country'] ?? null,
            'city'    => $data['city'] ?? null,
            'region'  => $data['regionName'] ?? null,
            'lat'     => $data['lat'] ?? null,
            'lon'     => $data['lon'] ?? null,
        ];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Parse browser name & version from User-Agent
 */
function parseBrowser($ua) {
    $browsers = [
        'Edge'       => '/Edg[e\/]?([\d.]+)/i',
        'Opera'      => '/(?:OPR|Opera)[\/\s]([\d.]+)/i',
        'Samsung'    => '/SamsungBrowser\/([\d.]+)/i',
        'UC Browser' => '/UCBrowser\/([\d.]+)/i',
        'Firefox'    => '/Firefox\/([\d.]+)/i',
        'Chrome'     => '/Chrome\/([\d.]+)/i',
        'Safari'     => '/Version\/([\d.]+).*Safari/i',
        'IE'         => '/(?:MSIE |rv:)([\d.]+)/i',
    ];

    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $ua, $m)) {
            return ['name' => $name, 'version' => $m[1] ?? ''];
        }
    }
    return ['name' => 'Other', 'version' => ''];
}

/**
 * Parse OS name & version from User-Agent
 */
function parseOS($ua) {
    $patterns = [
        'iOS'       => '/(?:iPhone|iPad|iPod).*OS ([\d_]+)/i',
        'Android'   => '/Android ([\d.]+)/i',
        'Windows'   => '/Windows NT ([\d.]+)/i',
        'macOS'     => '/Mac OS X ([\d_.]+)/i',
        'Linux'     => '/Linux/i',
        'Chrome OS' => '/CrOS/i',
    ];

    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $ua, $m)) {
            $version = isset($m[1]) ? str_replace('_', '.', $m[1]) : '';
            if ($name === 'Windows') {
                $winMap = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista'];
                $version = $winMap[$version] ?? $version;
            }
            return ['name' => $name, 'version' => $version];
        }
    }
    return ['name' => 'Other', 'version' => ''];
}

/**
 * Detect device type from User-Agent
 */
function parseDeviceType($ua) {
    if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod/i', $ua)) return 'Mobile';
    if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) return 'Tablet';
    if (preg_match('/Bot|Crawler|Spider|Slurp|Googlebot/i', $ua)) return 'Bot';
    return 'Desktop';
}
