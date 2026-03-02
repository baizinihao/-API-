<?php require_once __DIR__ . '/../common/security/api_auth.php';

@error_reporting(0);
$remote_url = 'https://api.qrtool.cn/';
$method = 'GET';
$params = array_merge($_GET, $_POST);
$ch = curl_init();
if ($method === 'GET' && !empty($params)) { $remote_url .= (strpos($remote_url, '?') === false ? '?' : '&') . http_build_query($params); }
curl_setopt($ch, CURLOPT_URL, $remote_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); }
$headers = []; foreach (getallheaders() as $h_name => $h_value) { if (in_array(strtolower($h_name), ['user-agent', 'accept', 'accept-language'])) { $headers[] = $h_name . ': ' . $h_value; } } curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);
http_response_code($http_code);
if($content_type) { header('Content-Type: ' . $content_type); }
echo $response;