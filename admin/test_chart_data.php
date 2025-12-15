<?php
require_once __DIR__ . '/xml_utils.php';
header('Content-Type: application/json');
$data = get_dashboard_chart_data();
echo json_encode($data, JSON_PRETTY_PRINT);
