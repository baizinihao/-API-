<?php
session_start();
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
error_reporting(0);
ini_set('display_errors', 0);

$width = 120;
$height = 45;
$codeLen = 4;
$fontSize = 5;

$chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
$captchaCode = '';
for ($i = 0; $i < $codeLen; $i++) {
    $captchaCode .= $chars[rand(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = strtolower($captchaCode);

$img = imagecreatetruecolor($width, $height);
$bgColor = imagecolorallocate($img, 248, 248, 248);
imagefill($img, 0, 0, $bgColor);

function getRandColor($img, $isLight = false) {
    $r = $isLight ? rand(150, 220) : rand(30, 100);
    $g = $isLight ? rand(150, 220) : rand(30, 100);
    $b = $isLight ? rand(150, 220) : rand(30, 100);
    return imagecolorallocate($img, $r, $g, $b);
}

for ($i = 0; $i < 5; $i++) {
    imageline($img, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), getRandColor($img, true));
}

for ($i = 0; $i < 200; $i++) {
    imagesetpixel($img, rand(0, $width), rand(0, $height), getRandColor($img, true));
}

$x = rand(10, 20);
for ($i = 0; $i < $codeLen; $i++) {
    $char = $captchaCode[$i];
    $color = getRandColor($img);
    imagestring($img, $fontSize, $x, rand(10, 25), $char, $color);
    $x += 25;
}

imagepng($img);
imagedestroy($img);
?>