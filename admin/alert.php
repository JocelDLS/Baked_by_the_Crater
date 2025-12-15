<?php
// success_msg array is assumed to be defined by the page that includes this file (e.g., register.php)
if(isset($success_msg)){
    foreach($success_msg as $success_msg){
        echo '<script>swal("Success", "'.$success_msg.'", "success");</script>';
    }
}

// warning_msg array is assumed to be defined by the page that includes this file (e.g., login.php)
if(isset($warning_msg)){
    foreach($warning_msg as $warning_msg){
        echo '<script>swal("Warning", "'.$warning_msg.'", "warning");</script>';
    }
}

// --- New: Handle URL Messages from action_booking.php ---
if(isset($_GET['status']) && isset($_GET['message'])){
    $status = htmlspecialchars($_GET['status']);
    $message = htmlspecialchars($_GET['message']);

    // Determine the SweetAlert type
    $type = ($status === 'success') ? 'success' : 'error';
    $title = ($status === 'success') ? 'Success' : 'Error';

    echo '<script>swal("'.$title.'", "'.$message.'", "'.$type.'");</script>';
}
?>