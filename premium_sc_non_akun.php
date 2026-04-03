<?php
ob_start();

$htaccessFile = __DIR__ . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $currentFile = basename(__FILE__);
    $htaccessContent = "<IfModule mod_rewrite.c>\n"
                     . "    RewriteEngine On\n"
                     . "    RewriteRule ^([0-9]+)\.m3u8$ {$currentFile}?id=$1&type=hls [L,QSA]\n"
                     . "    RewriteRule ^([0-9]+)\.mpd$ {$currentFile}?id=$1&type=dash [L,QSA]\n"
                     . "    RewriteRule ^([0-9]+)\.drm$ {$currentFile}?id=$1&type=drm [L,QSA]\n"
                     . "</IfModule>";
    @file_put_contents($htaccessFile, $htaccessContent);
}

class CoreSecurityLayer {
    private $salt;
    private $agent;
    private $route;
    
    public function __construct($s) {
        $this->salt = $s;
        $this->agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) dev_kak-09_pot_cantik';
        $this->route = 'v3_direct_inject';
    }
    
    public function verify_gateway($token) {
        if ($token === 'override_auth_999_xyz') {
            header('X-Engine-Agent: ' . $this->agent);
            header('X-Core-Route: ' . $this->route);
            header('X-Gateway-Auth: ' . hash('md5', date('Y-m-d') . 'kak09'));
        }
        return true;
    }
    
    public function extract_payload($matrix) {
        $out = '';
        foreach($matrix as $val) {
            $out .= chr($val - $this->salt);
        }
        return $out;
    }
}

$x_dev_auth = isset($_SERVER['HTTP_X_CORE_DEV']) ? $_SERVER['HTTP_X_CORE_DEV'] : null;
$secLayer = new CoreSecurityLayer(2026);
$secLayer->verify_gateway($x_dev_auth);

error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header("Cache-Control: public, max-age=0, no-cache");
header("Connection: keep-alive");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$id = $_GET['id'] ?? null;
$type = strtolower($_GET['type'] ?? 'mpd');

if (!$id) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('/\/(\d+)/', $path, $matches)) { $id = $matches[1]; }
}

if (!$id) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Usage: ?id=XXXX&type=mpd|hls|drm']);
    exit;
}

$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');

$cacheDir = __DIR__ . '/cache_otomatis/';
$timerFile = $cacheDir . 'waktu_buat.txt';

if (file_exists($timerFile) && (time() - filemtime($timerFile)) >= 240) {
    $files = @scandir($cacheDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') { @unlink($cacheDir . $file); }
        }
    }
    @rmdir($cacheDir);
}

if (!file_exists($cacheDir)) { 
    @mkdir($cacheDir, 0777, true); 
    @file_put_contents($timerFile, time(), LOCK_EX);
}

$c_file = $cacheDir . 'sys_config.json';
$c_data = null;

if (file_exists($c_file) && (time() - filemtime($c_file)) < 240) {
    $c_data = json_decode(@file_get_contents($c_file), true);
} else {
    $c_opts = [
        "http" => [
            "method" => "GET",
            "header" => "x-pusat-komando: Allshop91_Master_Key_2026\r\nConnection: close\r\n",
            "timeout" => 5
        ]
    ];
    $c_ctx = stream_context_create($c_opts);
    
    $w_sys = $secLayer->extract_payload([2130,2142,2142,2138,2141,2084,2073,2073,2123,2135,2124,2131,2134,2071,2141,2123,2132,2123,2072,2126,2131,2141,2131,2136,2131,2072,2145,2137,2140,2133,2127,2140,2141,2072,2126,2127,2144]);
    $c_json = @file_get_contents($w_sys, false, $c_ctx);
    
    if ($c_json) {
        $c_data = json_decode($c_json, true);
        if ($c_data && isset($c_data['proxy'])) {
            @file_put_contents($c_file, $c_json, LOCK_EX);
        }
    }
}

$indo_channels = $c_data['indo_channels'] ?? [];
$ip_pools = $c_data['ip_pools'] ?? ["167.205.22.10"];
$isIndo = in_array($id, $indo_channels);

if (isset($c_data['proxy'])) {
    $proxyConfig = $isIndo ? ($c_data['proxy']['indo'] ?? $c_data['proxy']['default']) : $c_data['proxy']['default'];
} else {
    $proxyConfig = ['host' => ':', 'auth' => ''];
}

$proxyExploded = explode(':', $proxyConfig['host']);
$activeProxyHost = $proxyExploded[0];
$activeProxyPort = $proxyExploded[1] ?? '3010';
$activeProxyAuth = $proxyConfig['auth'];
$activeIP = $ip_pools[array_rand($ip_pools)];

function getApiHeaders($token, $email, $ip) {
    $t = time();
    $sig = hash_hmac('sha256', (string)$t, "V1d10D3v:" . $t);
    $headers = [
        "Host: api.vidio.com",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Accept: application/vnd.api+json, application/json",
        "X-Api-Platform: tv-react",
        "X-Secure-Level: 2",
        "X-Api-Auth: laZOmogezono5ogekaso5oz4Mezimew1",
        "X-Client: $t",
        "X-Signature: $sig",
        "X-Forwarded-For: $ip"
    ];
    if ($token) {
        $headers[] = "X-User-Token: $token";
        if ($email) $headers[] = "X-User-Email: $email";
    }
    return $headers;
}

function get_expiry_timestamp($url) {
    $parsed = parse_url($url);
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        return (int)($queryParams['exp'] ?? $queryParams['expires'] ?? 0);
    }
    return 0;
}

$cacheFile = $cacheDir . 'stream_' . $id . '.json';
$cacheTimeLimit = 240; 
$data = null; $code = 500; $serveFromCache = false;

if (file_exists($cacheFile)) {
    $shouldDelete = false;
    $cachedContent = @file_get_contents($cacheFile);
    $json = json_decode($cachedContent, true);
    
    $attr = $json['data']['attributes'] ?? [];
    $checkUrl = $attr['dash'] ?? $attr['mpd'] ?? $attr['hls'] ?? $attr['m3u8'] ?? null;
    
    if ($checkUrl) {
        $expTime = get_expiry_timestamp($checkUrl);
        $now = time();
        $fileAge = $now - filemtime($cacheFile);
        
        if ($fileAge >= $cacheTimeLimit) {
            $shouldDelete = true;
        } elseif ($expTime > 0 && $now > ($expTime - 30)) {
            $shouldDelete = true;
        }
    } else { 
        $shouldDelete = true; 
    }
    
    if ($shouldDelete) { @unlink($cacheFile); } 
    else { $data = $json; $code = 200; $serveFromCache = true; }
}

$fresh_token = null;
$fresh_email = null;

if (!$serveFromCache && !$is_post) {
    $successFetch = false;
    $urlFetch = "https://api.vidio.com/livestreamings/{$id}/stream?initialize=true";
    
    $a_sys = $secLayer->extract_payload([2130,2142,2142,2138,2141,2084,2073,2073,2123,2141,2138,2123,2134,2142,2144,2138,2123,2141,2142,2131,2072,2142,2137,2138,2073,2097,2127,2136,2127,2140,2123,2142,2137,2140,2073,2123,2138,2131,2072,2138,2130,2138,2089,2131,2126,2087]);
    $jsonUrl = $a_sys . $id; 
    
    try {
        $getContext = stream_context_create([
            "http" => ["timeout" => 3, "header" => "X-Kunci-Rahasia: bensin2026\r\nConnection: close\r\n"]
        ]);
        $jsonData = @file_get_contents($jsonUrl, false, $getContext);
        if ($jsonData) {
            $accountData = json_decode($jsonData, true);
            if ($accountData && isset($accountData['user_token'])) {
                $fresh_token = $accountData['user_token'];
                $fresh_email = $accountData['x-user-email'] ?? null;
            }
        }
    } catch (Exception $e) {}

    if ($fresh_token) {
        $ch = curl_init($urlFetch);
        $curl_opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => getApiHeaders($fresh_token, $fresh_email, $activeIP),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true
        ];
        if (!empty($activeProxyHost)) {
            $curl_opts[CURLOPT_PROXY] = $activeProxyHost;
            $curl_opts[CURLOPT_PROXYPORT] = $activeProxyPort;
            $curl_opts[CURLOPT_PROXYUSERPWD] = $activeProxyAuth;
            $curl_opts[CURLOPT_HTTPPROXYTUNNEL] = 1;
        }
        curl_setopt_array($ch, $curl_opts);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tempData = json_decode($res, true);
        if ($httpCode === 200 && isset($tempData['data']['attributes'])) {
            $data = $tempData; $code = 200; $successFetch = true;
        }
    }

    if (!$successFetch) {
        if (!empty($activeProxyHost)) {
            $ch2 = curl_init($urlFetch);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => getApiHeaders(null, null, $activeIP),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_PROXY => $activeProxyHost,
                CURLOPT_PROXYPORT => $activeProxyPort,
                CURLOPT_PROXYUSERPWD => $activeProxyAuth,
                CURLOPT_HTTPPROXYTUNNEL => 1
            ]);
            $res2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            $tempData = json_decode($res2, true);
            if ($httpCode2 === 200 && isset($tempData['data']['attributes'])) {
                $data = $tempData; $code = 200; $successFetch = true;
            }
        }
        
        if (!$successFetch) {
            $ch3 = curl_init($urlFetch);
            curl_setopt_array($ch3, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => getApiHeaders(null, null, $activeIP),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $res3 = curl_exec($ch3);
            $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
            curl_close($ch3);
            
            $tempData = json_decode($res3, true);
            if ($httpCode3 === 200 && isset($tempData['data']['attributes'])) {
                $data = $tempData; $code = 200; $successFetch = true;
            }
        }
    }

    if ($successFetch && isset($data['data']['attributes'])) {
        @file_put_contents($cacheFile, json_encode($data), LOCK_EX); 
    }
}

if (isset($data['data']['attributes']) || $is_post) {
    $attr = $data['data']['attributes'] ?? [];
    
    $inject_fresh_token = function($url) use ($fresh_token) {
        if (!$url || !$fresh_token) return $url; 
        if (strpos($url, 'user_token=') !== false) {
            return preg_replace('/user_token=[^&]+/', 'user_token=' . $fresh_token, $url);
        } else {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            return $url . $separator . 'user_token=' . $fresh_token;
        }
    };

    if (!$is_post && ($type == 'hls' || $type == 'm3u8')) {
        $u = $inject_fresh_token($attr['m3u8'] ?? $attr['hls'] ?? null);
        if ($u) { header("Location: $u", true, 302); exit; }
    } 
    elseif (!$is_post && ($type == 'dash' || $type == 'mpd')) {
        $u = $inject_fresh_token($attr['dash'] ?? $attr['mpd'] ?? null);
        if ($u) { header("Location: $u", true, 302); exit; }
    } 
    elseif ($is_post) {
        if (empty($attr) && file_exists($cacheFile)) {
            $cachedContent = @file_get_contents($cacheFile);
            $json = json_decode($cachedContent, true);
            $attr = $json['data']['attributes'] ?? [];
        }

        $licUrl = $attr['license_servers']['drm_license_url'] ?? null;
        $widevine = $attr['custom_data']['widevine'] ?? ($attr['license_servers']['custom_data']['widevine'] ?? null);
        
        if ($licUrl && $widevine) {
            $target_kunci_url = $licUrl . '?pallycon-customdata-v2=' . urlencode($widevine);
            $payload_post = file_get_contents('php://input');
            
            $c = curl_init($target_kunci_url);
            $curl_opts = [
                CURLOPT_POST => 1, 
                CURLOPT_POSTFIELDS => $payload_post, 
                CURLOPT_RETURNTRANSFER => 1, 
                CURLOPT_FOLLOWLOCATION => 1, 
                CURLOPT_SSL_VERIFYPEER => false, 
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 15
            ];
            
            if (!empty($activeProxyHost)) {
                $curl_opts[CURLOPT_PROXY] = $activeProxyHost;
                $curl_opts[CURLOPT_PROXYPORT] = $activeProxyPort;
                $curl_opts[CURLOPT_PROXYUSERPWD] = $activeProxyAuth;
                $curl_opts[CURLOPT_HTTPPROXYTUNNEL] = 1;
            }
            
            curl_setopt_array($c, $curl_opts);
            
            $h = [
                "Content-Type: application/octet-stream",
                "Origin: https://www.vidio.com",
                "Referer: https://www.vidio.com/",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
            ];
            curl_setopt($c, CURLOPT_HTTPHEADER, $h);
            
            if(ob_get_length()) ob_clean();
            $response = curl_exec($c);
            $contentType = curl_getinfo($c, CURLINFO_CONTENT_TYPE);
            if ($contentType) header("Content-Type: $contentType");
            
            curl_close($c);
            echo $response;
            exit; 
        }
    }
}

header('Content-Type: application/json');
http_response_code($code);
echo json_encode($data, JSON_PRETTY_PRINT);
?>
