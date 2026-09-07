<?php

$host = "localhost";
$dbname = "mbs_chatbot";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // ให้ PDO แจ้ง Error เมื่อ SQL มีปัญหา
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ให้ข้อมูลที่ SELECT ออกมาเป็น associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    http_response_code(500);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode([
        "success" => false,
        "message" => "ไม่สามารถเชื่อมต่อฐานข้อมูลได้"
    ], JSON_UNESCAPED_UNICODE);

    exit;
}