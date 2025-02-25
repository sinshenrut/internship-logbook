<?php
session_start();

// ลบ session variables ทั้งหมด
session_unset();

// ทำลาย session
session_destroy();

// Redirect ไปที่หน้า login
header("Location: /internship_logbook/login.php");
exit();
?>