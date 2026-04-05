<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'teacher';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}
?>