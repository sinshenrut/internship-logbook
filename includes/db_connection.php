<?php
$host = "localhost";
$username = "root"; // ปกติ XAMPP ใช้ root
$password = "";     // ปกติ XAMPP ไม่มีรหัสผ่าน, *ถ้ามีให้ใส่*
$database = "internship_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>