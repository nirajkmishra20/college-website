
<?php
// School/admin/delete_income.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can delete income.</p>";
    header("location: ../login.php");
    exit;
}

// Check if income_id is set in the URL
if (!isset($_GET["id"]) || empty(trim($_GET["id"]))) {
    // URL doesn't contain id parameter. Redirect back to manage.
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid request. Income ID not provided.</p>";
    header("location: manage_income.php");
    exit;
}

// Get ID from URL
$income_id = trim($_GET["id"]);

// Validate ID
if (!is_numeric($income_id)) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid income ID format.</p>";
    header("location: manage_income.php");
    exit;
}
$income_id = (int)$income_id; // Cast to integer for safety

// Database connection check
if ($link === false) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Could not delete income.</p>";
     error_log("Delete Income DB connection failed: " . mysqli_connect_error());
     header("location: manage_income.php");
     exit;
}

// Prepare a delete statement
$sql = "DELETE FROM income WHERE income_id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind variables to the prepared statement as parameters
    mysqli_stmt_bind_param($stmt, "i", $param_id);

    // Set parameters
    $param_id = $income_id;

    // Attempt to execute the prepared statement
    if (mysqli_stmt_execute($stmt)) {
        // Record deleted successfully. Redirect to manage page.
        $_SESSION['operation_message'] = "<p class='text-green-600'>Income deleted successfully.</p>";
    } else {
        $_SESSION['operation_message'] = "<p class='text-red-600'>Error deleting income: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
        error_log("Error executing delete income query for ID " . $income_id . ": " . mysqli_stmt_error($stmt));
    }

    // Close statement
    mysqli_stmt_close($stmt);
} else {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Database statement preparation failed for deletion: " . htmlspecialchars(mysqli_error($link)) . "</p>";
     error_log("Error preparing delete income statement: " . mysqli_error($link));
}

// Close connection (if link was successfully established)
if (isset($link) && is_object($link) && method_exists($link, 'ping') && @mysqli_ping($link)) {
     mysqli_close($link);
}

// Redirect back to the manage page
header("location: manage_income.php");
exit;
?>