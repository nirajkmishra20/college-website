<?php
// School/admin/delete_expense.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN (or Principal if you want them to delete)
// Restricting deletion to ADMIN is generally safer.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can delete expenses.</p>";
    header("location: ../login.php");
    exit;
}

// Check if expense_id is set in the URL
if (!isset($_GET["id"]) || empty(trim($_GET["id"]))) {
    // URL doesn't contain id parameter. Redirect to error page or back to manage.
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid request. Expense ID not provided.</p>";
    header("location: manage_expenses.php");
    exit;
}

// Get ID from URL
$expense_id = trim($_GET["id"]);

// Validate ID
if (!is_numeric($expense_id)) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid expense ID format.</p>";
    header("location: manage_expenses.php");
    exit;
}
$expense_id = (int)$expense_id; // Cast to integer for safety

// Database connection check
if ($link === false) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Could not delete expense.</p>";
     error_log("Delete Expense DB connection failed: " . mysqli_connect_error());
     header("location: manage_expenses.php");
     exit;
}

// Prepare a delete statement
$sql = "DELETE FROM expenses WHERE expense_id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind variables to the prepared statement as parameters
    mysqli_stmt_bind_param($stmt, "i", $param_id);

    // Set parameters
    $param_id = $expense_id;

    // Attempt to execute the prepared statement
    if (mysqli_stmt_execute($stmt)) {
        // Record deleted successfully. Redirect to manage page.
        $_SESSION['operation_message'] = "<p class='text-green-600'>Expense deleted successfully.</p>";
    } else {
        $_SESSION['operation_message'] = "<p class='text-red-600'>Error deleting expense: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
        error_log("Error executing delete expense query for ID " . $expense_id . ": " . mysqli_stmt_error($stmt));
    }

    // Close statement
    mysqli_stmt_close($stmt);
} else {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Database statement preparation failed for deletion: " . htmlspecialchars(mysqli_error($link)) . "</p>";
     error_log("Error preparing delete expense statement: " . mysqli_error($link));
}

// Close connection (if link was successfully established)
if (isset($link) && is_object($link) && method_exists($link, 'ping') && @mysqli_ping($link)) {
     mysqli_close($link);
}

// Redirect back to the manage page
header("location: manage_expenses.php");
exit;
?>