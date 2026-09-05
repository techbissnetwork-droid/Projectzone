<?php
require_once __DIR__ . '/../includes/db.php';

unset($_SESSION['staff_id']);
session_regenerate_id(true);

header('Location: login.php');
