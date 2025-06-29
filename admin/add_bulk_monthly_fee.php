<?php
// School/admin/add_bulk_monthly_fee.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
// Allowing Principal as they often manage finances/fees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can add bulk fee records.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Add Bulk Monthly Fee Records";


// --- Variables ---
// Filter variables (used for both GET and POST to repopulate form)
$selected_year = $_REQUEST['academic_year'] ?? ''; // Use $_REQUEST to get from either GET or POST
$selected_class = $_REQUEST['current_class'] ?? '';
$selected_van_filter = $_REQUEST['van_filter'] ?? 'all'; // Default to 'all'

// Fee input variables (for POST submission and repopulation)
$fee_month_input = $_POST['fee_month'] ?? '';
$fee_year_input = $_POST['fee_year'] ?? '';
$base_monthly_fee_input = $_POST['base_monthly_fee'] ?? ''; // Allow empty string for validation check
$monthly_van_fee_input = $_POST['monthly_van_fee'] ?? '';
$monthly_exam_fee_input = $_POST['monthly_exam_fee'] ?? '';
$monthly_electricity_fee_input = $_POST['monthly_electricity_fee'] ?? '';

// Display variables (retain POST values on error, use defaults on GET/initial load)
// Repopulate form inputs with either POST values or initial GET values/defaults
$fee_month_display = $fee_month_input ?: ($_GET['fee_month'] ?? ''); // Repopulate from POST on error, else GET
$fee_year_display = $fee_year_input ?: ($_GET['fee_year'] ?? date('Y')); // Repopulate from POST on error, else GET, default to current year
$base_monthly_fee_display = $base_monthly_fee_input ?: ($_GET['base_monthly_fee'] ?? '');
$monthly_van_fee_display = $monthly_van_fee_input ?: ($_GET['monthly_van_fee'] ?? '');
$monthly_exam_fee_display = $monthly_exam_fee_input ?: ($_GET['monthly_exam_fee'] ?? '');
$monthly_electricity_fee_display = $monthly_electricity_fee_input ?: ($_GET['monthly_electricity_fee'] ?? '');


// Error variables
$fee_input_errors = [];
$processing_message = ''; // To store summary of bulk insert operation (plain text or simple HTML)
$students_list_message = "Select criteria above and click 'Apply Filters' to preview students."; // Default message for student list area
$students_list_message_type = 'info'; // Default style for the list message


// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'

// Check for operation messages set in other pages or previous requests (like redirects)
// This should be done early to load any redirect messages into the toast variables
if (isset($_SESSION['operation_message'])) {
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg)); // Use strip_tags for safety

     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false || strpos($msg_lower, 'welcome') !== false || strpos($msg_lower, 'marked as paid') !== false || strpos($msg_lower, 'payment recorded') !== false) {
          $toast_type = 'success';
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'problem') !== false || strpos($msg_lower, 'exists') !== false || strpos($msg_lower, 'duplicate') !== false) {
          $toast_type = 'error';
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false || strpos($msg_lower, 'missing') !== false || strpos($msg_lower, 'information required') !== false || strpos($msg_lower, 'skipped') !== false || strpos($msg_lower, 'partially failed') !== false) { // Added skipped, partially failed
          $toast_type = 'warning';
     } else {
          $toast_type = 'info';
     }
    $toast_message = strip_tags($msg); // Pass the stripped message to JS
    unset($_SESSION['operation_message']); // Clear the session message
}


// --- Fetch Filter Options ---
$available_years = [];
$available_classes = [];

if ($link === false) {
    // Database connection failed. Toast message already set above if it was a redirect.
    // If not, set a toast now.
     if (empty($toast_message)) {
         $toast_message = "Database connection error. Cannot load filter options or process requests.";
         $toast_type = 'error';
     }
     error_log("Add Bulk Fee DB connection failed: " . mysqli_connect_error());
} else {
    // Fetch distinct academic years (using student_monthly_fees table for years where fees exist)
     // If student_exam_results has more years, use that. Let's stick to fees for relevance.
    $sql_years = "SELECT DISTINCT fee_year FROM student_monthly_fees WHERE fee_year IS NOT NULL ORDER BY fee_year DESC";
    if ($result_years = mysqli_query($link, $sql_years)) {
        while ($row = mysqli_fetch_assoc($result_years)) {
            $available_years[] = htmlspecialchars($row['fee_year']);
        }
        mysqli_free_result($result_years);
    } else {
         error_log("Error fetching years for filter: " . mysqli_error($link));
    }

    // Add current year and maybe a few future/past years if not already present
     $current_year = (int)date('Y');
     $year_range = range($current_year + 2, $current_year - 5); // Add years +/- range
     foreach ($year_range as $yr) {
         if (!in_array($yr, $available_years)) {
             $available_years[] = $yr;
         }
     }
     rsort($available_years); // Ensure years are sorted descending


    // Fetch distinct classes from students table
    $sql_classes = "SELECT DISTINCT current_class FROM students WHERE current_class IS NOT NULL AND current_class != '' ORDER BY current_class ASC";
     if ($result_classes = mysqli_query($link, $sql_classes)) {
         while ($row = mysqli_fetch_assoc($result_classes)) {
             $available_classes[] = htmlspecialchars($row['current_class']);
         }
         mysqli_free_result($result_classes);
     } else {
          error_log("Error fetching classes for filter: " . mysqli_error($link));
     }
}


// --- Handle POST Request (Bulk Fee Addition) ---
$students_for_fee_processing = []; // List of students fetched based on filters for POST processing

if ($_SERVER["REQUEST_METHOD"] == "POST" && $link !== false) { // Only process POST if DB is connected

    // Re-validate fee inputs from POST
    $fee_month_for_db = null;
    $fee_year_for_db = null;
    $base_monthly_fee_for_db = 0.0; // Default 0 if validation fails or input is 0/empty allowed
    $monthly_van_fee_for_db = 0.0;
    $monthly_exam_fee_for_db = 0.0;
    $monthly_electricity_fee_for_db = 0.0;

    // Validate Month
    if (empty($fee_month_input)) {
        $fee_input_errors['fee_month'] = "Please select a month.";
    } else {
        $month_int = filter_var($fee_month_input, FILTER_VALIDATE_INT);
        if ($month_int === false || $month_int < 1 || $month_int > 12) {
            $fee_input_errors['fee_month'] = "Invalid month selected.";
        } else {
            $fee_month_for_db = $month_int;
        }
    }

    // Validate Year
    if (empty($fee_year_input)) {
        $fee_input_errors['fee_year'] = "Please enter a year.";
    } else {
         $year_int = filter_var($fee_year_input, FILTER_VALIDATE_INT);
         // Add a basic range check
         if ($year_int === false || $year_int < 1900 || $year_int > (date('Y') + 10) ) {
              $fee_input_errors['fee_year'] = "Invalid year (must be a valid year).";
         } else {
              $fee_year_for_db = $year_int;
         }
    }

    // Validate Base Fee - Required field
    if ($base_monthly_fee_input === '') {
        $fee_input_errors['base_monthly_fee'] = "Base fee is required.";
    } else {
         $base_fee_float = filter_var($base_monthly_fee_input, FILTER_VALIDATE_FLOAT);
         if ($base_fee_float === false || $base_fee_float < 0 ) {
              $fee_input_errors['base_monthly_fee'] = "Please enter a valid non-negative number for base fee.";
         } else {
              $base_monthly_fee_for_db = $base_fee_float;
         }
    }

    // Validate Optional Fee fields (can be empty, treated as 0)
    if (!empty($monthly_van_fee_input)) {
        $van_fee_float = filter_var($monthly_van_fee_input, FILTER_VALIDATE_FLOAT);
         if ($van_fee_float === false || $van_fee_float < 0) {
             $fee_input_errors['monthly_van_fee'] = "Invalid Van fee.";
         } else {
             $monthly_van_fee_for_db = $van_fee_float;
         }
    } else {
        $monthly_van_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }

     if (!empty($monthly_exam_fee_input)) {
        $exam_fee_float = filter_var($monthly_exam_fee_input, FILTER_VALIDATE_FLOAT);
         if ($exam_fee_float === false || $exam_fee_float < 0) {
             $fee_input_errors['monthly_exam_fee'] = "Invalid Exam fee.";
         } else {
             $monthly_exam_fee_for_db = $exam_fee_float;
         }
    } else {
        $monthly_exam_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }

     if (!empty($monthly_electricity_fee_input)) {
        $elec_fee_float = filter_var($monthly_electricity_fee_input, FILTER_VALIDATE_FLOAT);
         if ($elec_fee_float === false || $elec_fee_float < 0) {
             $fee_input_errors['monthly_electricity_fee'] = "Invalid Electricity fee.";
         } else {
             $monthly_electricity_fee_for_db = $elec_fee_float;
         }
    } else {
         $monthly_electricity_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }


    // Proceed if fee inputs are valid
    if (empty($fee_input_errors)) {

         // --- 1. Fetch Existing Fee Records for the target month/year ---
         // This is the optimization for duplicate check
         $existing_fee_student_ids = []; // Array to store student_ids that already have a fee record
         $sql_fetch_existing = "SELECT student_id FROM student_monthly_fees WHERE fee_year = ? AND fee_month = ?";
         if ($stmt_existing = mysqli_prepare($link, $sql_fetch_existing)) {
             mysqli_stmt_bind_param($stmt_existing, "ii", $fee_year_for_db, $fee_month_for_db);
             if (mysqli_stmt_execute($stmt_existing)) {
                 $result_existing = mysqli_stmt_get_result($stmt_existing);
                 while ($row = mysqli_fetch_assoc($result_existing)) {
                     // Store student_id in the array, key it by student_id for O(1) lookup
                     $existing_fee_student_ids[$row['student_id']] = true;
                 }
                 mysqli_free_result($result_existing);
             } else {
                 // Error fetching existing records - critical for duplicate check accuracy
                 $db_error = mysqli_stmt_error($stmt_existing);
                 $processing_message = "Error checking for existing fee records. Database error: " . htmlspecialchars($db_error);
                 $toast_type = 'error';
                 error_log("Add Bulk Fee fetch existing records failed: " . $db_error);
             }
             mysqli_stmt_close($stmt_existing);
         } else {
             // Error preparing fetch existing statement - critical
             $db_error = mysqli_error($link);
             $processing_message = "Error preparing statement to check existing fee records. Database error: " . htmlspecialchars($db_error);
             $toast_type = 'error';
             error_log("Add Bulk Fee prepare fetch existing failed: " . $db_error);
         }


         // --- 2. Fetch Students based on Filters (Using POSTed filter values) ---
         // Only fetch students if no critical error occurred fetching existing records
         if(empty($processing_message)) {
             $sql_select_students = "SELECT user_id, full_name, current_class, takes_van FROM students"; // Select necessary columns
             $student_where_clauses = [];
             $student_param_types = "";
             $student_param_values = [];

             if (!empty($selected_class)) {
                 $student_where_clauses[] = "current_class = ?";
                 $student_param_types .= "s";
                 $student_param_values[] = $selected_class;
             }

              if ($selected_van_filter === 'yes') {
                  $student_where_clauses[] = "takes_van = 1";
              } elseif ($selected_van_filter === 'no') {
                  $student_where_clauses[] = "takes_van = 0";
              }

             if (!empty($student_where_clauses)) {
                 $sql_select_students .= " WHERE " . implode(" AND ", $student_where_clauses);
             }
             $sql_select_students .= " ORDER BY current_class ASC, full_name ASC";

             if ($stmt_select = mysqli_prepare($link, $sql_select_students)) {
                  if (!empty($student_param_types)) {
                      $bind_params = [$student_param_types];
                      foreach ($student_param_values as &$value) { $bind_params[] = &$value; }
                      call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_select], $bind_params));
                      unset($value);
                  }

                 if (mysqli_stmt_execute($stmt_select)) {
                     $result_select = mysqli_stmt_get_result($stmt_select);
                      $students_for_fee_processing = mysqli_fetch_all($result_select, MYSQLI_ASSOC);
                     mysqli_free_result($result_select);

                      if (empty($students_for_fee_processing)) {
                           $processing_message = "No students found matching the selected filters. Cannot add fees.";
                           $toast_type = 'warning'; // Use warning for no students found
                      }

                 } else {
                      $db_error = mysqli_stmt_error($stmt_select);
                      $processing_message = "Error fetching students for processing. Database error: " . htmlspecialchars($db_error);
                      $toast_type = 'error';
                      error_log("Add Bulk Fee student select query failed for processing: " . $db_error);
                 }
                 mysqli_stmt_close($stmt_select);
             } else {
                  $db_error = mysqli_error($link);
                  $processing_message = "Error preparing student select statement. Database error: " . htmlspecialchars($db_error);
                  $toast_type = 'error';
                  error_log("Add Bulk Fee prepare student select failed: " . $db_error);
             }
         } // end if(empty($processing_message)) check after fetching existing fees


        // --- 3. Process Bulk Insert if Students Found AND No Processing Message Set ---
        if (!empty($students_for_fee_processing) && empty($processing_message)) {

            $added_count = 0;
            $skipped_duplicate_count = 0;
            $failed_insert_count = 0;

            mysqli_begin_transaction($link);
            $transaction_success = true;


             // Prepare the insert statement ONCE outside the loop
            $sql_insert_fee = "INSERT INTO student_monthly_fees (student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())"; // Added created_at

            if ($stmt_insert = mysqli_prepare($link, $sql_insert_fee)) {

                foreach ($students_for_fee_processing as $student) {
                    $student_id = $student['user_id'];

                    // Check for duplicate using the pre-fetched list (O(1) lookup)
                    if (isset($existing_fee_student_ids[$student_id])) {
                        $skipped_duplicate_count++;
                        continue; // Skip to the next student
                    }

                    // Calculate amount_due for THIS student
                    $current_student_amount_due = (float)$base_monthly_fee_for_db;

                    // Check if the student uses van service AND a non-zero van fee amount was provided in the form
                    $is_van_user = isset($student['takes_van']) && (($student['takes_van'] ?? 0) == 1);

                    // The actual van fee amount stored: apply only if the student takes van
                    $applied_van_fee = ($is_van_user) ? (float)$monthly_van_fee_for_db : 0.0;


                    $current_student_amount_due += $applied_van_fee; // Add applied van fee
                    $current_student_amount_due += (float)$monthly_exam_fee_for_db; // Always add exam fee if entered
                    $current_student_amount_due += (float)$monthly_electricity_fee_for_db; // Always add electricity fee if entered


                    // Bind parameters for the insert statement and execute for this student
                    $bind_types_fee = "iiiddddd"; // student_id, year, month (int), base, van, exam, elec, amount_due (double)
                     // Using temporary variables to bind by reference
                     $tmp_student_id = $student_id;
                     $tmp_fee_year = $fee_year_for_db;
                     $tmp_fee_month = $fee_month_for_db;
                     $tmp_base_fee = $base_monthly_fee_for_db;
                     $tmp_van_fee = $applied_van_fee; // Use the calculated applied van fee
                     $tmp_exam_fee = $monthly_exam_fee_for_db;
                     $tmp_elec_fee = $monthly_electricity_fee_for_db;
                     $tmp_amount_due = $current_student_amount_due;

                     mysqli_stmt_bind_param($stmt_insert, $bind_types_fee,
                          $tmp_student_id,
                          $tmp_fee_year,
                          $tmp_fee_month,
                          $tmp_base_fee,
                          $tmp_van_fee,
                          $tmp_exam_fee,
                          $tmp_elec_fee,
                          $tmp_amount_due
                     );

                    if (mysqli_stmt_execute($stmt_insert)) {
                        $added_count++;
                    } else {
                        // Handle insert execution error - log and fail this student's insert
                         $db_error = mysqli_stmt_error($stmt_insert);
                         error_log("Add Bulk Fee insert failed for student ID " . $student_id . " Year " . $fee_year_for_db . " Month " . $fee_month_for_db . ": " . $db_error);
                        $failed_insert_count++;
                        $transaction_success = false; // Mark transaction for potential rollback
                    }
                } // end foreach student

                 mysqli_stmt_close($stmt_insert); // Close the insert statement after the loop

                 // Decide whether to commit or rollback the entire batch
                 if ($transaction_success) {
                      mysqli_commit($link);
                      // Set success/summary message for toast/display
                       $processing_message = "Bulk fee addition complete. " . $added_count . " records added. " . $skipped_duplicate_count . " skipped (duplicate).";
                       if ($failed_insert_count > 0) {
                            // If there were *any* failures during insert execution, mark as warning
                             $processing_message .= " However, " . $failed_insert_count . " record(s) failed to insert.";
                             $toast_type = 'warning';
                       } else {
                            // Only set success if ALL attempts passed (excluding duplicates)
                            $toast_type = 'success';
                       }
                        // Set a session message for toast display on next page load if redirected (though we stay here)
                        // $_SESSION['operation_message'] = "<p class='text-" . ($toast_type === 'success' ? 'green' : 'orange') . "-600'>" . htmlspecialchars($processing_message) . "</p>";

                 } else {
                      // Transaction was NOT successful due to an insert execution error
                     mysqli_rollback($link);
                     $processing_message = "Bulk fee addition failed during transaction. " . $failed_insert_count . " record(s) failed to insert. Transaction rolled back.";
                     $toast_type = 'error';
                     error_log("Bulk fee addition transaction rolled back due to " . $failed_insert_count . " failures.");
                     // Set a session message for toast display on next page load if redirected (though we stay here)
                      // $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($processing_message) . "</p>";
                 }

            } else {
                 // ERROR PREPARING INSERT STATEMENT (DURING POST) - Critical error
                 $db_error = mysqli_error($link);
                 $processing_message = "Error preparing fee insert statement for bulk operation. Database error: " . htmlspecialchars($db_error);
                 $toast_type = 'error';
                 error_log("Add Bulk Fee prepare insert failed: " . $db_error);
                 // Attempt rollback just in case any inserts happened before prepare failed
                 mysqli_rollback($link);
            }

        } // End if !empty($students_for_fee_processing) && empty($processing_message)
        // If students list was empty, $processing_message was set above (e.g., "No students found matching filters.")
        // If a critical error occurred fetching existing fees or students, $processing_message was set above.


    } elseif (!empty($fee_input_errors)) {
        // Fee input validation failed on POST, errors are in $fee_input_errors
         $processing_message = "Please correct the errors in the fee amount fields."; // Plain text message for processing summary area
         $toast_type = 'error'; // Use error type for toast
         // The student list for display will be fetched in the block below using the POSTed filter values ($_REQUEST).
    }
    // If DB connection failed, $processing_message and $toast_message were set earlier.

} // --- End POST Request Handling ---


// --- Handle GET Request OR Display after POST ---
// This block runs for initial GET or after POST processing (successful or with errors)
// It fetches and displays the list of students based on the current filters.
$students_list_for_display = []; // Re-initialize or keep empty if already processed in POST
$should_fetch_list = ($link !== false) && (!empty($selected_class) || $selected_van_filter !== 'all' || $_SERVER["REQUEST_METHOD"] == "POST");


if ($should_fetch_list) {
     // Only fetch students if filters are set OR if it was a POST request (to show the list that was targeted)
     // AND DB is connected AND no critical errors fetching student list for POST processing happened already

    // Build the SQL query to select students based on filters (using $selected_* variables from $_REQUEST)
    $sql_select_students_display = "SELECT user_id, full_name, current_class, takes_van FROM students"; // Select necessary columns
    $student_where_clauses_display = [];
    $student_param_types_display = "";
    $student_param_values_display = [];

    if (!empty($selected_class)) {
        $student_where_clauses_display[] = "current_class = ?";
        $student_param_types_display .= "s";
        $student_param_values_display[] = $selected_class;
    }

     if ($selected_van_filter === 'yes') {
         $student_where_clauses_display[] = "takes_van = 1";
     } elseif ($selected_van_filter === 'no') {
         $student_where_clauses_display[] = "takes_van = 0";
     }

    if (!empty($student_where_clauses_display)) {
        $sql_select_students_display .= " WHERE " . implode(" AND ", $student_where_clauses_display);
    }
    $sql_select_students_display .= " ORDER BY current_class ASC, full_name ASC"; // Order for display

    // Prepare and execute the student select statement for display
    if ($stmt_select_display = mysqli_prepare($link, $sql_select_students_display)) {
         if (!empty($student_param_types_display)) {
             $bind_params_display = [$student_param_types_display];
             foreach ($student_param_values_display as &$value) { $bind_params_display[] = &$value; }
             call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_select_display], $bind_params_display));
             unset($value);
         }

        if (mysqli_stmt_execute($stmt_select_display)) {
            $result_select_display = mysqli_stmt_get_result($stmt_select_display);
             $students_list_for_display = mysqli_fetch_all($result_select_display, MYSQLI_ASSOC);
            mysqli_free_result($result_select_display);

            if (empty($students_list_for_display)) {
                $students_list_message = "No students found matching the selected filters.";
                $students_list_message_type = 'warning';
            } else {
                 $students_list_message = "Found " . count($students_list_for_display) . " students matching filters:";
                 $students_list_message_type = 'info';
            }

        } else {
             $db_error = mysqli_stmt_error($stmt_select_display);
             $students_list_message = "Error fetching students list for display. Database error: " . htmlspecialchars($db_error);
             $students_list_message_type = 'error';
              if (empty($toast_message)) { // Set toast only if no other toast is pending
                  $toast_message = $students_list_message;
                  $toast_type = 'error'; // Force error type for toast
              }
             error_log("Add Bulk Fee student select query failed for display: " . $db_error);
        }
        mysqli_stmt_close($stmt_select_display);
    } else {
         $db_error = mysqli_error($link);
         $students_list_message = "Error preparing student list statement for display. Database error: " . htmlspecialchars($db_error);
         $students_list_message_type = 'error';
          if (empty($toast_message)) { // Set toast only if no other toast is pending
             $toast_message = $students_list_message;
             $toast_type = 'error'; // Force error type for toast
         }
         error_log("Add Bulk Fee prepare student list failed for display: " . $db_error);
    }
} elseif ($link === false) {
     // DB connection failed - message handled at the top
     $students_list_message = "Database connection failed. Cannot fetch student list.";
     $students_list_message_type = 'error';
}
// If !$should_fetch_list (i.e., initial GET with no filters applied), $students_list_message remains the default "Select criteria..."


// Close connection (if it was successfully opened and not already closed/failed)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

?>

<?php
// Include the header file.
// Assuming admin_header.php contains the start of HTML, <head>, and possibly the fixed header structure.
// If admin_sidebar.php is meant to contain the *entire* fixed header, then remove the fixed-header div from here.
// If admin_header.php contains <head> and <body> tag opening, adjust accordingly.
// Based on the previous code, admin_sidebar seems to be a separate include *within* the body.
// Let's include admin_header first.
// require_once "./admin_header.php"; // Removed this include as admin_sidebar.php might contain the header parts
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         /* Base styles */
         body {
             padding-top: 4.5rem; /* Space for fixed header */
             background-color: #f3f4f6;
             min-height: 100vh;
              padding-left: 0;
              transition: padding-left 0.3s ease; /* Smooth transition for padding when sidebar opens/closes */
         }
         body.sidebar-open {
             padding-left: 16rem; /* Adjust based on your sidebar width (md:w-64) */
         }
         /* Fixed Header */
         .fixed-header {
              position: fixed;
              top: 0;
              left: 0;
              right: 0;
              height: 4.5rem;
              background-color: #ffffff;
              box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
              padding: 1rem;
              display: flex;
              align-items: center;
              z-index: 20; /* Higher than sidebar */
               transition: left 0.3s ease;
         }
          body.sidebar-open .fixed-header {
              left: 16rem;
          }
         /* Main content wrapper */
         .main-content-wrapper {
             width: 100%;
             max-width: 1280px;
             margin-left: auto;
             margin-right: auto;
             padding: 2rem 1rem; /* py-8 px-4 */
         }
          @media (min-width: 768px) { /* md breakpoint */
               .main-content-wrapper {
                   padding-left: 2rem; /* md:px-8 */
                   padding-right: 2rem; /* md:px-8 */
               }
          }

         /* Form element styling */
         .form-control {
             display: block;
             width: 100%;
             padding: 0.5rem 0.75rem; /* py-2 px-3 */
             font-size: 1rem;
             line-height: 1.5;
             color: #495057;
             background-color: #fff;
             background-clip: padding-box;
             border: 1px solid #ced4da;
             border-radius: 0.25rem;
             transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
         }
         .form-control:focus {
             color: #495057;
             background-color: #fff;
             border-color: #80bdff;
             outline: 0;
             box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
         }
         .form-control.is-invalid {
             border-color: #e3342f; /* Tailwind red-600-ish */
             padding-right: 2.3rem; /* Space for icon */
             background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23e3342f' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3.2 3.2l5.6 5.6M8.8 3.2l-5.6 5.6'/%3e%3c/svg%3e");
             background-repeat: no-repeat;
             background-position: right 0.5rem center;
             background-size: 0.875em 0.875em;
         }
         .form-control.is-invalid:focus {
             border-color: #e3342f;
             box-shadow: 0 0 0 0.2rem rgba(227, 52, 47, 0.25);
         }

         .form-error {
            color: #e3342f; /* Tailwind red-600-ish */
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25em;
            display: block;
         }

         /* Specific number input placeholder color */
         input[type="number"]::placeholder {
               color: #9ca3af; /* gray-400 */
           }

         /* --- Toast Notification Styles --- */
         .toast-container {
             position: fixed; top: 1rem; right: 1rem; z-index: 1000; /* Increased z-index */ display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; max-width: 90%;
         }
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto; min-width: 200px; max-width: 350px; display: flex; align-items: center; word-break: break-word; line-height: 1.4; /* Improved readability */
         }
         .toast.show { opacity: 1; transform: translateX(0); }
         .toast-success { border-left: 5px solid #10b981; color: #065f46; }
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }
         .toast .close-button {
             margin-left: auto; background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0 0.25rem; line-height: 1; font-weight: bold; opacity: 0.7; /* Subtle */ transition: opacity 0.2s ease;
         }
         .toast .close-button:hover { opacity: 1; }


          /* Specific styles for student list preview area */
          .student-list-container {
               background-color: #ffffff;
               padding: 1.5rem;
               border-radius: 0.5rem;
               box-shadow: 0 1px 3px rgba(0,0,0,0.1);
               margin-bottom: 2rem;
               border: 1px solid #e5e7eb; /* Added subtle border */
          }
           .student-list-container h3 {
                font-size: 1.125rem; /* text-lg */
                font-weight: 600;
                color: #374151; /* gray-700 */
                margin-bottom: 1rem;
                 padding-bottom: 0.5rem;
                 border-bottom: 1px solid #e5e7eb; /* separator */
           }
            /* Style for the message box inside the list container */
           .student-list-container .message-box {
                margin-bottom: 1rem; /* Add some space below message box */
           }
          .student-list {
               list-style: none;
               padding: 0;
               margin: 0;
               display: flex;
               flex-wrap: wrap; /* Allow items to wrap */
               gap: 0.5rem; /* Space between list items */
               max-height: 300px; /* Limit height */
               overflow-y: auto; /* Add scroll if needed */
               border: 1px solid #e5e7eb; /* Border for the scrollable area */
               padding: 0.75rem; /* Padding inside scroll area */
               background-color: #fafafa; /* Very light background */
          }
           .student-list li {
               background-color: #eef2ff; /* indigo-50, lighter than gray-50 */
               border: 1px solid #c7d2fe; /* indigo-200 */
               padding: 0.3rem 0.6rem; /* py-1 px-2.5 - slightly less padding */
               border-radius: 0.25rem; /* rounded-sm */
               font-size: 0.8rem; /* Smaller font */
               color: #374151; /* gray-700 */
               white-space: nowrap; /* Prevent list items from wrapping internally */
                max-width: 100%; /* Prevent overflow */
                overflow: hidden; /* Hide overflowing text */
                text-overflow: ellipsis; /* Add ellipsis for truncated text */
           }

           /* Message styles (reused for general messages and specific ones) */
            .message-box {
                padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem;
                /* Remove text-align: center; unless specifically desired for a box */
            }
             .message-box p { margin: 0.5rem 0; } /* Ensure margins within p tags */
             .message-box p:first-child { margin-top: 0; }
             .message-box p:last-child { margin-bottom: 0; }

             /* Colors from Tailwind classes for message boxes */
             .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; } /* green */
              .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; } /* red */
              .message-box.warning { color: #92400e; background-color: #fef3c7; border-color: #fcd34d; } /* amber */
              .message-box.info { color: #1e40af; background-color: #dbeafe; border-color: #93c5fd; } /* blue */


     </style>
     <script>
          // --- Sidebar Toggle JS ---
         document.addEventListener('DOMContentLoaded', (event) => {
             const sidebarToggleOpen = document.getElementById('admin-sidebar-toggle-open');
             const body = document.body;

             function toggleSidebar() {
                 body.classList.toggle('sidebar-open');
                 // Optional: Save state to localStorage
                 // const isSidebarOpen = body.classList.contains('sidebar-open');
                 // localStorage.setItem('sidebar-open', isSidebarOpen);
             }

             // Attach event listener to the toggle button
             if (sidebarToggleOpen) {
                 sidebarToggleOpen.addEventListener('click', toggleSidebar);
             } else {
                 console.warn("Sidebar toggle button '#admin-sidebar-toggle-open' not found.");
             }

             // Optional: Check localStorage on load to set initial state
             // const savedSidebarState = localStorage.getItem('sidebar-open');
             // if (savedSidebarState === 'true') {
             //    body.classList.add('sidebar-open');
             // } else if (savedSidebarState === 'false') {
             //    body.classList.remove('sidebar-open');
             // }
             // If no state is saved, let CSS handle the default (closed)
         });


          // --- Toast Notification JS ---
         document.addEventListener('DOMContentLoaded', function() {
             const toastContainer = document.getElementById('toastContainer');
             if (!toastContainer) {
                 console.error('Toast container #toastContainer not found.');
             }

             /**
              * Displays a toast notification.
              * @param {string} message The message to display (can contain simple HTML like <p>).
              * @param {'success'|'error'|'warning'|'info'} type The type of toast.
              * @param {number} duration The duration in milliseconds. 0 means no auto-hide.
              */
             function showToast(message, type = 'info', duration = 5000) {
                 if (!message || !toastContainer) return;

                 const toast = document.createElement('div');
                 // Use innerHTML because PHP message might contain <p> tags from $_SESSION
                 // We are relying on strip_tags in PHP to remove dangerous HTML.
                 toast.innerHTML = message;
                 toast.classList.add('toast', `toast-${type}`);

                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.innerHTML = '×'; // HTML entity for multiplication sign (×)
                 closeButton.setAttribute('aria-label', 'Close');
                 closeButton.onclick = () => {
                     toast.classList.remove('show');
                     // Remove the element after the transition
                     toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                 };
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 // Trigger the fade-in transition
                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });

                 // Auto-hide after duration if set
                 if (duration > 0) {
                     setTimeout(() => {
                         if (toast.classList.contains('show')) {
                             toast.classList.remove('show');
                             // Add transitionend listener only if it needs to be removed by timeout
                             toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                         }
                     }, duration);
                 }
             }

             // Trigger toast display on DOM load if a message exists
             const phpMessage = <?php echo json_encode($toast_message); ?>;
             const messageType = <?php echo json_encode($toast_type); ?>;

             if (phpMessage) {
                 showToast(phpMessage, messageType);
             }
         });
     </script>
</head>
<body class="bg-gray-100">

    <?php
    // Include the admin sidebar. It should contain the toggle button.
    $sidebar_path = "./admin_sidebar.php";
    if (file_exists($sidebar_path)) {
        require_once $sidebar_path;
    } else {
        // Fallback message if sidebar file is missing
        echo "<div class='text-red-600 p-4'>Warning: admin_sidebar.php not found at " . htmlspecialchars($sidebar_path) . "</div>";
    }
    ?>

     <!-- Fixed Header (Assuming admin_sidebar.php doesn't render the fixed header itself,
          but only the sidebar content. If it *does* render the fixed header, remove this div.) -->
     <div class="fixed-header bg-white shadow-md p-4 flex items-center top-0 right: 0; z-20 transition-left duration-300 ease-in-out">
         <!-- Sidebar toggle button (for mobile) - place inside fixed header -->
         <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:hidden" aria-label="Toggle sidebar">
               <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
               </svg>
          </button>
         <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow"><?php echo htmlspecialchars($pageTitle); ?></h1>
         <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
         <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
     </div>


    <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here by JS -->
    </div>

    <!-- Main content wrapper -->
    <div class="main-content-wrapper">

        <h2 class="text-2xl font-bold mb-6 text-gray-800">Bulk Monthly Fee Addition</h2> <!-- Updated heading -->

         <!-- Filter Form -->
         <!-- Uses GET method to update the student list preview -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="bg-white p-6 rounded-lg shadow-md mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end border border-gray-200"> <!-- Added border -->
             <!-- Academic Year Filter - Not used for student selection SQL, but can be for context -->
             <div>
                 <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year (For Context)</label>
                 <select name="academic_year" id="academic_year" class="form-control mt-1 block w-full">
                     <option value="">All Years</option>
                     <?php foreach ($available_years as $year): ?>
                         <option value="<?php echo $year; ?>" <?php echo ((string)$selected_year === (string)$year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
             <!-- Class Filter -->
             <div>
                 <label for="current_class" class="block text-sm font-medium text-gray-700">Filter by Class</label>
                 <select name="current_class" id="current_class" class="form-control mt-1 block w-full">
                     <option value="">All Classes</option>
                     <?php foreach ($available_classes as $class): ?>
                         <option value="<?php echo $class; ?>" <?php echo ($selected_class === $class) ? 'selected' : ''; ?>><?php echo $class; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
             <!-- Van Service Filter -->
             <div>
                 <label for="van_filter" class="block text-sm font-medium text-gray-700">Filter by Van Service</label>
                 <select name="van_filter" id="van_filter" class="form-control mt-1 block w-full">
                     <option value="all" <?php echo ($selected_van_filter === 'all') ? 'selected' : ''; ?>>All Students</option>
                     <option value="yes" <?php echo ($selected_van_filter === 'yes') ? 'selected' : ''; ?>>Uses Van Service</option>
                     <option value="no" <?php echo ($selected_van_filter === 'no') ? 'selected' : ''; ?>>Does NOT Use Van Service</option>
                 </select>
             </div>
             <div class="md:col-span-3 flex justify-end"> <!-- Span across columns and align right -->
                  <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">Apply Filters</button>
             </div>
        </form>

         <!-- Students List Preview -->
         <div class="student-list-container">
              <?php
               // Determine message style based on list message type
               $list_message_box_class = ''; // Default, no message box
               if ($students_list_message_type === 'error') $list_message_box_class = 'error';
               elseif ($students_list_message_type === 'warning') $list_message_box_class = 'warning';
               elseif ($students_list_message_type === 'info' && $should_fetch_list) $list_message_box_class = 'info'; // Show info message box if filters applied

              // Display the message box if applicable
               if (!empty($list_message_box_class)) {
                   echo "<div class='message-box " . htmlspecialchars($list_message_box_class) . " mb-4'>" . htmlspecialchars($students_list_message) . "</div>";
               } else {
                   // If no message box is needed (e.g., initial state with no filters), show the default text
                   echo "<h3>Students List Preview</h3>";
                   echo "<p class='text-gray-600 italic text-sm'>" . htmlspecialchars($students_list_message) . "</p>";
               }
              ?>

               <?php if (!empty($students_list_for_display)): ?>
                    <ul class="student-list mt-4"> <!-- Added mt-4 -->
                         <?php foreach($students_list_for_display as $student): ?>
                             <li>
                                 <?php echo htmlspecialchars($student['full_name']) . " (" . htmlspecialchars($student['current_class']) . ")"; ?>
                                 <?php
                                 // Show Van User indicator based on 'takes_van' column
                                 if (($student['takes_van'] ?? 0) == 1) {
                                     echo ' - Van User';
                                 }
                                 ?>
                             </li>
                         <?php endforeach; ?>
                    </ul>
               <?php elseif ($should_fetch_list && $students_list_message_type !== 'error' && $students_list_message_type !== 'warning'): ?>
                    <!-- This shows the 'No students found...' message only if filters were applied and no error occurred, when the list is empty -->
                    <p class="text-gray-600 italic text-sm mt-4">No students matched the selected criteria.</p> <!-- Added mt-4 -->
               <?php endif; ?>
         </div>

        <!-- Fee Input Form (only shown if students are found matching filters OR if it was a POST request with errors) -->
        <?php if (!empty($students_list_for_display) || ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($fee_input_errors)) || ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($processing_message)) ): ?>
             <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200"> <!-- Added border -->
                 <h3 class="text-xl font-semibold mb-4 text-gray-800">Enter Monthly Fee Details</h3>

                 <?php
                 // Display processing results/errors from POST
                  if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($processing_message)) {
                       // Determine message type based on the $toast_type set during POST processing
                       $message_type = $toast_type ?: 'info'; // Use toast_type if set, default to info
                       echo "<div class='message-box " . htmlspecialchars($message_type) . " mb-4'><p>" . htmlspecialchars($processing_message) . "</p></div>";
                   }
                 ?>

                 <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">

                     <!-- Hidden inputs to carry filter values through POST -->
                     <!-- Use $_REQUEST values which will be populated from either GET or POST -->
                     <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($selected_year); ?>">
                     <input type="hidden" name="current_class" value="<?php echo htmlspecialchars($selected_class); ?>">
                     <input type="hidden" name="van_filter" value="<?php echo htmlspecialchars($selected_van_filter); ?>">


                     <!-- Month and Year for the Fee Record -->
                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"> <!-- Use sm:grid-cols-2 -->
                         <div>
                             <label for="fee_month_post" class="block text-sm font-medium text-gray-700 mb-1">Month <span class="text-red-500">*</span></label>
                             <select name="fee_month" id="fee_month_post" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['fee_month'])) ? 'is-invalid' : ''; ?>">
                                 <option value="">Select Month</option>
                                 <?php
                                 $month_names_select = [
                                     1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                     5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                     9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                 ];
                                 for ($m = 1; $m <= 12; $m++) {
                                     // Cast display value to int for comparison
                                     $selected = ((int)($fee_month_display) === $m) ? 'selected' : '';
                                     echo "<option value='" . $m . "'" . $selected . ">" . htmlspecialchars($month_names_select[$m]) . "</option>";
                                 }
                                 ?>
                             </select>
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['fee_month'] ?? ''); ?></span>
                         </div>
                         <div>
                             <label for="fee_year_post" class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                             <input type="number" name="fee_year" id="fee_year_post" step="1" min="1900" max="<?php echo date('Y') + 10; ?>" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['fee_year'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($fee_year_display); ?>" placeholder="e.g., 2024">
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['fee_year'] ?? ''); ?></span>
                         </div>
                     </div>

                     <!-- Fee Breakdown Inputs -->
                     <div><h4 class="text-md font-semibold text-gray-700 border-b pb-1 mb-3 mt-3">Fee Breakdown for ALL Selected Students</h4></div>

                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"> <!-- Use sm:grid-cols-2 -->
                          <div>
                              <label for="base_monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Base Fee <span class="text-red-500">*</span></label>
                              <input type="number" name="base_monthly_fee" id="base_monthly_fee" step="0.01" min="0" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['base_monthly_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($base_monthly_fee_display); ?>" placeholder="e.g., 1200.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['base_monthly_fee'] ?? ''); ?></span>
                          </div>
                          <div>
                              <label for="monthly_van_fee" class="block text-sm font-medium text-gray-700 mb-1">Van Fee <span class="text-gray-500 text-xs italic">(Only applied to students who use van)</span></label>
                              <input type="number" name="monthly_van_fee" id="monthly_van_fee" step="0.01" min="0" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['monthly_van_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_van_fee_display); ?>" placeholder="e.g., 300.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_van_fee'] ?? ''); ?></span>
                          </div>
                      </div>

                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"> <!-- Use sm:grid-cols-2 -->
                         <div>
                             <label for="monthly_exam_fee" class="block text-sm font-medium text-gray-700 mb-1">Exam Fee (Optional)</label>
                             <input type="number" name="monthly_exam_fee" id="monthly_exam_fee" step="0.01" min="0" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['monthly_exam_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_exam_fee_display); ?>" placeholder="e.g., 100.00">
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_exam_fee'] ?? ''); ?></span>
                         </div>
                          <div>
                              <label for="monthly_electricity_fee" class="block text-sm font-medium text-gray-700 mb-1">Electricity Fee (Optional)</label>
                              <input type="number" name="monthly_electricity_fee" id="monthly_electricity_fee" step="0.01" min="0" class="form-control mt-1 block w-full <?php echo (!empty($fee_input_errors['monthly_electricity_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_electricity_fee_display); ?>" placeholder="e.g., 50.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_electricity_fee'] ?? ''); ?></span>
                          </div>
                     </div>


                     <div class="flex items-center justify-end gap-4 mt-6">
                          <!-- Disable button if no students are listed -->
                          <button type="submit"
                                  class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                                  <?php echo empty($students_list_for_display) ? 'disabled' : ''; ?>
                          >Add Fee Records for Selected Students</button>
                          <a href="admin_dashboard.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">Cancel</a>
                     </div>
                 </form>
             </div>
        <?php endif; ?>


    </div> <!-- End main-content-wrapper -->

<?php
// Include the footer file.
// Assuming admin_footer.php contains the closing </body> and </html> tags and possibly closing scripts.
$footer_path = "./admin_footer.php";
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
     // Fallback closing tags if footer file is missing
     echo '</body></html>';
}
?>