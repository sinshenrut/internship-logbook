<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function redirect($location) {
    header("Location: /internship_logbook" . $location); // Corrected path
    exit();
}