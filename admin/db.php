<?php
    $con = mysqli_connect("localhost","root","","Baked_by_the_Crate");

    if(mysqli_connect_errno()){
        error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    }

    // Currency configuration - Philippine Peso symbol and formatter
    if (!defined('CURRENCY_SYMBOL')) {
        define('CURRENCY_SYMBOL', '₱');
    }

    if (!function_exists('format_currency')) {
        function format_currency($amount) {
            // Accept numbers or formatted strings: sanitize by removing currency symbol and commas
            $str = (string)$amount;
            $str = str_replace([CURRENCY_SYMBOL, ',', ' '], '', $str);
            $str = preg_replace('/[^0-9.\-]/', '', $str);
            return CURRENCY_SYMBOL . number_format((float)$str, 2);
        }
    }
 