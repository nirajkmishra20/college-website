<?php
// School/admin/fetch_student_details.php

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
    error_log("Access denied to fetch_student_details.php from IP: " . $_SERVER['REMOTE_ADDR'] . " User: " . ($_SESSION['username'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Get the student ID from the GET request
$student_id = $_GET['id'] ?? null;

// Validate the ID
if ($student_id === null || !is_numeric($student_id)) {
    error_log("Invalid or missing student ID in fetch_student_details.php request from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Student ID is missing or not numeric.']);
    exit;
}

$student_id = (int)$student_id; // Cast to integer for safety

// Initialize response data structure
$response_data = [
    'student_details' => null,
    'monthly_fees' => [],
    'exam_results' => []
];
$message = "";
$success = false;

// Check if database connection is available from config.php
if ($link === false) {
    $message = "Database connection error.";
    error_log("fetch_student_details.php DB connection failed: " . mysqli_connect_error());
} else {
    // --- 1. Fetch Student Core Details ---
    $sql_student = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, student_fees, optional_fees, address, pincode, state, is_active, created_at, photo_filename FROM students WHERE user_id = ?";

    if ($stmt_student = mysqli_prepare($link, $sql_student)) {
        mysqli_stmt_bind_param($stmt_student, "i", $param_id);
        $param_id = $student_id;

        if (mysqli_stmt_execute($stmt_student)) {
            $result_student = mysqli_stmt_get_result($stmt_student);
            if ($result_student && mysqli_num_rows($result_student) == 1) {
                $response_data['student_details'] = mysqli_fetch_assoc($result_student);
                $success = true; // Mark overall success if student details found
            } else {
                $message = "Student with ID {$student_id} not found.";
                $success = false; // Student not found means overall failure
                error_log("Student ID not found in DB: " . $student_id . " (fetch_student_details.php)");
            }
            mysqli_free_result($result_student);
        } else {
            $message = "Error executing student details query.";
            $success = false;
            error_log("Error executing student fetch query in fetch_student_details.php: " . mysqli_stmt_error($stmt_student));
        }
        mysqli_stmt_close($stmt_student);
    } else {
        $message = "Database statement preparation failed for student details.";
        $success = false;
        error_log("Error preparing student fetch statement in fetch_student_details.php: " . mysqli_error($link));
    }

    // --- 2. Fetch Monthly Fee Records (only if student details were found) ---
    // Ensure the previous statement is fully processed before the next query
    while (mysqli_more_results($link) && mysqli_next_result($link));

    if ($success) { // Only proceed if student details were found
        $sql_fees = "SELECT * FROM student_monthly_fees WHERE student_id = ? ORDER BY fee_year DESC, fee_month DESC";

        if ($stmt_fees = mysqli_prepare($link, $sql_fees)) {
             mysqli_stmt_bind_param($stmt_fees, "i", $param_id); // $param_id is still the student_id

            if (mysqli_stmt_execute($stmt_fees)) {
                $result_fees = mysqli_stmt_get_result($stmt_fees);
                if ($result_fees) {
                    $response_data['monthly_fees'] = mysqli_fetch_all($result_fees, MYSQLI_ASSOC);
                    // No error if no fees found, just an empty array
                } else {
                    error_log("Error getting result set for fees in fetch_student_details.php: " . mysqli_stmt_error($stmt_fees));
                    // Optionally add a message, but not critical to fail overall if fees query fails
                    // $message .= " Warning: Could not fetch fee details.";
                }
                mysqli_free_result($result_fees);
            } else {
                error_log("Error executing fee query in fetch_student_details.php: " . mysqli_stmt_error($stmt_fees));
                 // $message .= " Warning: Error executing fee query.";
            }
            mysqli_stmt_close($stmt_fees);
        } else {
            error_log("Database statement preparation failed for fees in fetch_student_details.php: " . mysqli_error($link));
             // $message .= " Warning: Fee statement preparation failed.";
        }
    }

    // --- 3. Fetch Exam Results Records (only if student details were found) ---
     // Ensure the previous statement is fully processed before the next query
    while (mysqli_more_results($link) && mysqli_next_result($link));

    if ($success) { // Only proceed if student details were found
        $sql_exams = "SELECT * FROM student_exam_results WHERE student_id = ? ORDER BY academic_year DESC, exam_name DESC, subject_name ASC";

        if ($stmt_exams = mysqli_prepare($link, $sql_exams)) {
            mysqli_stmt_bind_param($stmt_exams, "i", $param_id); // $param_id is still the student_id

            if (mysqli_stmt_execute($stmt_exams)) {
                $result_exams = mysqli_stmt_get_result($stmt_exams);
                 if ($result_exams) {
                    $response_data['exam_results'] = mysqli_fetch_all($result_exams, MYSQLI_ASSOC);
                     // No error if no exams found, just an empty array
                 } else {
                     error_log("Error getting result set for exams in fetch_student_details.php: " . mysqli_stmt_error($stmt_exams));
                      // Optionally add a message
                      // $message .= " Warning: Could not fetch exam results.";
                 }
                mysqli_free_result($result_exams);
            } else {
                error_log("Error executing exam query in fetch_student_details.php: " . mysqli_stmt_error($stmt_exams));
                 // $message .= " Warning: Error executing exam query.";
            }
            mysqli_stmt_close($stmt_exams);
        } else {
            error_log("Database statement preparation failed for exams in fetch_student_details.php: " . mysqli_error($link));
             // $message .= " Warning: Exam statement preparation failed.";
        }
    }
}


// Close connection (only if $link was successfully established and is still open)
if (isset($link) && is_object($link) && method_exists($link, 'ping') && mysqli_ping($link)) {
     mysqli_close($link);
}

// Output JSON response
// Ensure no output happened before this
// If errors are still happening before header(), you MUST fix them.
// Temporarily uncommenting ini_set and error_reporting at the top can help debug this.
// ob_clean(); // Use ob_clean cautiously, better to fix source of early output.

echo json_encode(['success' => $success, 'data' => $response_data, 'message' => $message]);
exit; // Ensure script stops here
?>