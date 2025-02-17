<?php
// ใส่ฟังก์ชันที่ใช้บ่อยๆ ที่นี่ (ถ้ามี)

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}


function redirect($location) {
    header("Location: /internship_logbook" . $location); // เพิ่ม /internship_logbook
    exit();
}
// ฟังก์ชันอื่นๆ ที่คุณอาจจะต้องใช้

?>