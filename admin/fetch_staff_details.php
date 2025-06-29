<?php
// School/admin/fetch_staff_details.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Set JSON header
header('Content-Type: application/json');

// Turn off error reporting for production, but good to have on for debugging
// ini_set('display_errors', 0); // Set to 1 for debugging
// error_reporting(E_ALL); // Report all errors

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
     // Log access denied attempt
    error_log("Access denied to fetch_staff_details.php from IP: " . $_SERVER['REMOTE_ADDR'] . " User: " . ($_SESSION['username'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Get the staff ID from the GET request
$staff_id = $_GET['id'] ?? null;

// Validate the ID
if ($staff_id === null || !is_numeric($staff_id)) {
    error_log("Invalid or missing staff ID in fetch_staff_details.php request from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Staff ID is missing or not numeric.']);
    exit;
}

$staff_id = (int)$staff_id; // Cast to integer for safety

// Prepare a select statement
// Make sure these column names match your 'staff' table exactly.
$sql = "SELECT staff_id, unique_id, staff_name, email, mobile_number, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff WHERE staff_id = ?";

$data = null;
$message = "";
$success = false;

// Check if database connection is available from config.php
if ($link === false) {
     $message = "Database connection error.";
    error_log("fetch_staff_details.php DB connection failed: " . mysqli_connect_error());
} else {
     // Ensure no pending results from previous queries if connection is reused
    while (mysqli_more_results($link) && mysqli_next_result($link));

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "i", $param_id);

        // Set parameters
        $param_id = $staff_id;

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if ($result) { // Check if get_result was successful
                 if (mysqli_num_rows($result) == 1) {
                     // Fetch result row as an associative array
                     $data = mysqli_fetch_assoc($result);
                     $success = true;
                 } else {
                     $message = "Staff member with ID {$staff_id} not found.";
                     error_log("Staff ID not found in DB: " . $staff_id . " (fetch_staff_details.php)");
                 }
                 mysqli_free_result($result); // Free result set
            } else {
                $message = "Error getting result set from query.";
                error_log("Error getting result set in fetch_staff_details.php: " . mysqli_stmt_error($stmt));
            }
        } else {
            $message = "Error executing query.";
            error_log("Error executing staff fetch query in fetch_staff_details.php: " . mysqli_stmt_error($stmt));
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $message = "Database statement preparation failed.";
        error_log("Error preparing staff fetch statement in fetch_staff_details.php: " . mysqli_error($link));
    }
}


// Close connection (only if $link was successfully established and is still open)
if (isset($link) && is_object($link) && method_exists($link, 'ping') && mysqli_ping($link)) {
     mysqli_close($link);
}

// Output JSON response
// Ensure no output happened before this, just in case error reporting is on and outputted errors
// ob_clean(); // Removed ob_clean - header() should be sent first. Outputting errors *before* header is the issue.

echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
exit; // Ensure script stops here and nothing else is outputted
?>