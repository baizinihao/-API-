<?php require_once __DIR__ . '/../common/security/api_auth.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['code' => 200, 'message' => '新的世界新的开始', 'user_id' => $auth_user_id ?? null];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);