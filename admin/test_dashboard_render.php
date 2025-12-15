<?php
session_start();
// Give admin session for testing
$_SESSION['admin_id'] = 1;
require_once 'dashboard.php';
