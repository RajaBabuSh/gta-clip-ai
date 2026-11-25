<?php
$ch = curl_init('http://127.0.0.1:8000/api/v1/videos/youtube/verify');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url'=>'https://www.youtube.com/watch?v=irNhWMG560k']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "YT VERIFY HTTP CODE: $code\nRESPONSE: $res\n";
