<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: mainPage.php');
    exit;
}

header('Location: mainPage.php');
exit;
