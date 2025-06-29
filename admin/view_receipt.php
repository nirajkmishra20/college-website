<?php
// School/admin/view_receipt.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can view receipts.</p>";
    header("location: ../login.php");
    exit; // Exit after redirect
}

// Define variables and initialize
$fee_id = null;
$fee_record = null; // To hold the monthly fee data
$student_data_for_receipt = null; // To hold basic student data needed for receipt
$student_user_id = null; // The user_id from the students table (linked from fee_record)

$error = null; // General error for the page
$toast_message = ''; // For JS toast
$toast_type = '';

// School Information (Hardcoded - replace with settings if available)
$school_name = "Basic Public School";
$school_address = "Madhubani";
$school_phone = "1234567890"; // Your phone number
$school_logo_path = "../assets/images/school_logo_placeholder.png"; // *** CHANGE THIS PATH ***

// Check for operation messages set in other pages (like add_monthly_fee.php)
$session_message = null;
$session_message_class = '';
if (isset($_SESSION['operation_message'])) {
    // Determine message class based on content (simple check)
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg));
     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'generated') !== false) {
          $session_message_class = 'text-green-600 bg-green-100'; // Added bg classes
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'not found') !== false) {
          $session_message_class = 'text-red-600 bg-red-100'; // Added bg classes
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'no records found') !== false) {
          $session_message_class = 'text-yellow-600 bg-yellow-100'; // Added bg classes
     } else {
          $session_message_class = 'text-gray-700 bg-gray-100'; // Default to gray, added bg
     }
    $session_message = $msg; // Keep original HTML for now, will strip for toast
    // Note: Session message is *not* unset here. It's unset after successful display below.
}


// --- 1. Validate and Fetch Fee Record ---
// Get fee_id safely from GET
$fee_id = filter_input(INPUT_GET, 'fee_id', FILTER_VALIDATE_INT); // Use filter_input for safety

if ($fee_id === false || $fee_id <= 0) {
    $error = "Invalid or missing fee record ID provided in the URL.";
    $toast_message = $error;
    $toast_type = 'error';
} else {
    // Prepare statement to fetch the specific monthly fee record
    $sql_fetch_fee = "SELECT * FROM student_monthly_fees WHERE id = ?";

    // Check DB connection before preparing
    if ($link === false) {
        $error = "Database connection error. Could not retrieve fee record.";
        $toast_message = $error;
        $toast_type = 'error';
        error_log("View Receipt (Fee Fetch) DB connection failed: " . mysqli_connect_error());
    } elseif ($stmt_fetch_fee = mysqli_prepare($link, $sql_fetch_fee)) {
        mysqli_stmt_bind_param($stmt_fetch_fee, "i", $fee_id);
        if (mysqli_stmt_execute($stmt_fetch_fee)) {
            $result_fetch_fee = mysqli_stmt_get_result($stmt_fetch_fee);
            if (mysqli_num_rows($result_fetch_fee) == 1) {
                $fee_record = mysqli_fetch_assoc($result_fetch_fee);
                // Safely get student_id, which is user_id from the users table linked to students
                $student_user_id = $fee_record['student_id'] ?? null;
            } else {
                $error = "Fee record not found for ID " . htmlspecialchars($fee_id) . ".";
                $toast_message = $error;
                $toast_type = 'warning'; // Warning if not found
            }
            mysqli_free_result($result_fetch_fee); // Free result set
        } else {
            $error = "Error executing query to retrieve fee record.";
            $toast_message = $error;
            $toast_type = 'error';
            error_log("View Receipt (Fee Fetch) query failed for fee ID " . $fee_id . ": " . mysqli_stmt_error($stmt_fetch_fee));
        }
        mysqli_stmt_close($stmt_fetch_fee); // Close statement
    } elseif ($link !== false) { // Check if connection is still valid before logging prepare error
         $error = "Error preparing fee fetch statement.";
         $toast_message = $error;
         $toast_type = 'error';
         // Log the actual MySQL error for debugging
         error_log("View Receipt (Fee Fetch) prepare failed: " . mysqli_error($link));
    }
}

// --- 2. If Fee Record and Student ID are found, Fetch Student Details ---
// Only proceed if no error occurred during fee fetching AND we got a valid student_user_id
if ($error === null && isset($student_user_id) && $student_user_id > 0) {
     // Prepare statement to fetch just the required student details from the 'students' table
     // Assuming 'students' table has a column 'user_id' that matches student_monthly_fees.student_id
     // And columns 'full_name', 'roll_number', 'current_class'
     $sql_fetch_student_details = "SELECT full_name, roll_number AS student_code, current_class FROM students WHERE user_id = ?";

     if ($link === false) { // Re-check connection, although should be open
         $error = "Database connection error. Could not retrieve student details.";
         $toast_message = $error;
         $toast_type = 'error';
         error_log("View Receipt (Student Fetch) DB connection failed: " . mysqli_connect_error());
     } elseif ($stmt_fetch_student = mysqli_prepare($link, $sql_fetch_student_details)) {
         mysqli_stmt_bind_param($stmt_fetch_student, "i", $student_user_id);
         if (mysqli_stmt_execute($stmt_fetch_student)) {
             $result_fetch_student = mysqli_stmt_get_result($stmt_fetch_student);
             if (mysqli_num_rows($result_fetch_student) == 1) {
                 $student_data_for_receipt = mysqli_fetch_assoc($result_fetch_student);
                  // student_data_for_receipt now contains 'full_name', 'student_code', 'current_class'
             } else {
                 // Student not found for the user_id linked in the fee record
                 $error = "Student record not found for the ID linked to this fee record.";
                 $toast_message = $error;
                 $toast_type = 'error'; // Treat linked student not found as an error for the receipt
                 $student_data_for_receipt = null; // Ensure it's null if not found
             }
             mysqli_free_result($result_fetch_student); // Free result set
         } else {
             $error = "Error executing query to retrieve student details.";
             $toast_message = $error;
             $toast_type = 'error';
             error_log("View Receipt (Student Fetch) query failed for user ID " . $student_user_id . ": " . mysqli_stmt_error($stmt_fetch_student));
         }
         mysqli_stmt_close($stmt_fetch_student); // Close statement
     } elseif ($link !== false) { // Check if connection is still valid before logging prepare error
         $error = "Error preparing student fetch statement.";
         $toast_message = $error;
         $toast_type = 'error';
         // Log the actual MySQL error for debugging
         error_log("View Receipt (Student Fetch) prepare failed: " . mysqli_error($link));
     }
}
// At this point:
// - If error is null, fee_record should be set AND student_data_for_receipt should be set.
// - If error is not null, either fee_record or student_data_for_receipt (or both) will be null.


// Close connection (only if $link was successfully opened and is still open)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

// --- Function to convert number to words (Basic - English) ---
// This is a simplified function. For complex numbers and other languages, use a library.
if (!function_exists('convertNumberToWords')) {
    function convertNumberToWords($number) {
        $hyphen      = '-';
        $conjunction = ' and ';
        $separator   = ', ';
        $negative    = 'negative ';
        $dictionary  = array(
            0                   => 'zero', 1                   => 'one', 2                   => 'two',
            3                   => 'three', 4                   => 'four', 5                   => 'five',
            6                   => 'six', 7                   => 'seven', 8                   => 'eight',
            9                   => 'nine', 10                  => 'ten', 11                  => 'eleven',
            12                  => 'twelve', 13                  => 'thirteen', 14                  => 'fourteen',
            15                  => 'fifteen', 16                  => 'sixteen', 17                  => 'seventeen',
            18                  => 'eighteen', 19                  => 'nineteen', 20                  => 'twenty',
            30                  => 'thirty', 40                  => 'forty', // Standard spelling
            50                  => 'fifty', 60                  => 'sixty', 70                  => 'seventy',
            80                  => 'eighty', 90                  => 'ninety', 100                 => 'hundred',
            1000                => 'thousand', 1000000             => 'million', 1000000000          => 'billion',
            1000000000000       => 'trillion', 1000000000000000    => 'quadrillion',
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if ($number < 0) {
            return $negative . convertNumberToWords(abs($number));
        }

        $string = $thousands = null;
        $thousands = floor($number / 1000);
        $number %= 1000;

        $hundreds = floor($number / 100);
        $number %= 100;

        if ($thousands > 0) {
            $string .= convertNumberToWords($thousands) . ' ' . $dictionary[1000];
            // Add separator only if there are hundreds or a remaining number chunk
            if ($number > 0 || $hundreds > 0) {
                $string .= ($number > 0 && $hundreds == 0) ? $conjunction : $separator; // Use 'and' before remaining number if no hundreds
            }
        }

         if ($hundreds > 0) {
             $string .= ($string ? ($thousands > 0 ? $separator : '') : '') . $dictionary[$hundreds] . ' ' . $dictionary[100]; // Add conjunction only if prev part exists
             if ($number > 0) {
                 $string .= $conjunction;
             }
         } elseif ($thousands > 0 && $number > 0) { // Handle cases like 1001 -> one thousand and one
              $string .= $conjunction;
         }


        if ($number < 20) {
            $string .= $dictionary[$number];
        } else {
            $string .= $dictionary[floor($number / 10) * 10];
            if ($number % 10 > 0) {
                $string .= $hyphen . $dictionary[$number % 10];
            }
        }

        return trim($string); // Trim whitespace
    }
}

if (!function_exists('amountInWordsWithRupees')) {
    function amountInWordsWithRupees($amount) {
        // Ensure amount is a non-negative number rounded to 2 decimal places
        $amount = max(0, round((float)$amount, 2));

        $parts = explode('.', number_format($amount, 2, '.', ''));
        $rupees = (int)$parts[0];
        $paise = (int)$parts[1];

        $words = '';

        if ($rupees > 0) {
            $words .= convertNumberToWords($rupees);
        }

        if ($paise > 0) {
            if ($rupees > 0) {
                $words .= ' and ';
            }
            $words .= convertNumberToWords($paise);
        }

        if (empty($words)) {
            $words = 'Zero';
        }

        $final_string = ucwords($words); // Capitalize words

        if ($rupees > 0 || $amount == 0) { // Add "Rupees" if there were rupees or the total was 0
             $final_string .= ' Rupees';
        }

         if ($paise > 0) { // Add "Paise" if there were paise
              $final_string .= ' Paise';
         }


        return trim($final_string) . ' Only.'; // Add " Only." and trim
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($fee_record && $student_data_for_receipt) ? 'Monthly Fee Receipt - ID ' . htmlspecialchars($fee_id) : 'Error Loading Receipt'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         body {
             background-color: #f3f4f6;
             min-height: 100vh;
             display: flex;
             flex-direction: column;
             align-items: center;
             justify-content: flex-start; /* Align items to the top */
             padding: 1.5rem;
             font-family: sans-serif; /* Use a standard font */
         }

        .receipt-container {
            background-color: #fff;
            /* Removed padding, borders handled by table */
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin: auto;
            overflow: hidden; /* Hide overflowing borders */
             margin-top: 2rem; /* Add some space from the top */
             margin-bottom: 2rem; /* Add space at the bottom */
        }

         /* Styles specific for printing */
         @media print {
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
                display: block;
                 -webkit-print-color-adjust: exact; /* Ensure colors print */
                 print-color-adjust: exact; /* Ensure colors print */
            }
            .receipt-container {
                box-shadow: none;
                border-radius: 0; /* No rounded corners on print */
                border: none;
                padding: 0; /* No padding on print */
                max-width: 100%;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            /* More specific print overrides - Ensure colors print */
            .text-green-600, .text-red-600, .text-yellow-600, .value-black,
            h2, h3, .school-name, .school-address, .school-phone,
            .receipt-info-row strong, .student-details-cell strong,
            .student-details-section-title, .item-serial, .item-text,
            .receipt-table thead th, .receipt-table tfoot td,
            .summary-totals-table td:nth-child(1), .summary-totals-table td:nth-child(2),
            .terms-text, .footer-row td, .receipt-info-row p, .student-details-cell p,
             .item-row td {
                color: #000 !important; /* Force text color to black for print */
                -webkit-print-color-adjust: exact;
                 print-color-adjust: exact;
            }
             /* Ensure background colors are printed if needed (use light gray) */
             .bg-blue-200, .bg-blue-100, .receipt-header-cell,
             .student-details-cell, .summary-row td:nth-child(1),
             .footer-row td {
                 background-color: #eee !important; /* Use a light gray */
                 -webkit-print-color-adjust: exact;
                  print-color-adjust: exact;
             }
             /* Ensure borders are visible */
             table, th, td, tr {
                 border-color: #000 !important; /* Black borders for print */
                 -webkit-print-color-adjust: exact;
                  print-color-adjust: exact;
             }
             /* Ensure image is printed */
              .school-logo {
                 display: block !important;
                 filter: none; /* Remove shadow in print */
              }
         }

         /* --- Toast Notification Styles --- */
         .toast-container {
             position: fixed;
             top: 1rem; right: 1rem;
             z-index: 100;
             display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none;
         }
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto; min-width: 200px; max-width: 350px; display: flex; align-items: center; justify-content: space-between;
         }
         .toast.show { opacity: 1; transform: translateX(0); }
         .toast-success { border-left: 5px solid #10b981; color: #065f46; }
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }
         .toast .close-button {
              margin-left: 0.75rem; background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0; line-height: 1; opacity: 0.8; transition: opacity 0.2s ease-in-out;
         }
          .toast .close-button:hover { opacity: 1; }

          /* --- Receipt Table Layout Styles --- */
          .receipt-table {
               width: 100%;
               border-collapse: collapse; /* Remove space between borders */
               border: 2px solid #000; /* Outer border */
               font-size: 0.9rem;
          }

          .receipt-table th,
          .receipt-table td {
               border: 1px solid #000; /* Inner borders */
               padding: 0.5rem 0.8rem; /* Padding inside cells */
               text-align: left; /* Default text alignment */
               vertical-align: top; /* Align content to top */
          }

          /* Specific styles for Header, Info, Item, Summary, Footer rows */
          .receipt-table thead th,
          .receipt-table tfoot td {
               background-color: #e0f2f7; /* Light blue background for header/footer */
               text-align: center; /* Center text in header/footer */
               font-weight: bold;
          }
          .receipt-table thead th.bg-blue-200,
          .receipt-table tfoot td.bg-blue-200 {
               background-color: #bfdbfe; /* Tailwind blue-200 */
               color: #1f2937; /* Dark gray text */
          }
           .receipt-table th.bg-blue-100 {
               background-color: #dbeafe; /* Tailwind blue-100 */
               color: #1f2937; /* Dark gray text */
           }


          /* Receipt Header Cell */
          .receipt-header-cell {
               padding: 1rem;
               text-align: center; /* Center content */
          }
           .school-logo {
               max-width: 60px; /* Adjust size as needed */
               height: auto;
               margin: 0 auto 0.5rem;
               display: block;
               filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
           }
           .school-name {
               font-size: 1.2rem;
               font-weight: 700;
               color: #1f2937;
               margin-bottom: 0.1rem;
           }
           .school-address, .school-phone {
               font-size: 0.75rem;
               color: #4b5563;
               margin-bottom: 0.1rem;
           }

           /* Top Info Row */
           .receipt-info-row td {
               padding: 0.8rem; /* Increased padding */
               font-size: 0.8rem;
           }
           .receipt-info-cell-left {
               width: 60%; /* Adjust width */
           }
            .receipt-info-cell-right {
                width: 40%; /* Adjust width */
                 text-align: right; /* Align date/time to right */
                 /* Removed explicit padding-left/right to rely on default */
            }

            .receipt-info-row strong {
                 font-weight: 600;
                 margin-right: 0.5rem; /* Space after label */
                 color: #4b5563; /* Match other labels */
            }
             .receipt-info-row p {
                 margin-bottom: 0.3rem;
             }


          /* Bill To / Student Details Section (Using simple TDs for layout) */
           .student-details-cell {
               padding: 0.8rem; /* Consistent padding */
               background-color: #f7fafc; /* Light background */
           }
           .student-details-cell strong {
               font-weight: 600; /* semibold */
               margin-right: 0.5rem;
               color: #4b5563; /* Match other labels */
           }
            .student-details-cell p {
                 margin-bottom: 0.3rem; /* space below each detail */
                 font-size: 0.8rem;
                 color: #333; /* Default text color */
            }
            .student-details-cell p:last-child {
                 margin-bottom: 0;
            }
             .student-details-section-title {
                 font-weight: bold;
                 margin-bottom: 0.5rem;
                 display: block; /* Ensure it's on its own line */
                 color: #1f2937; /* Dark title color */
             }


           /* Item Details Table Header */
           .item-header th {
               background-color: #bfdbfe; /* Lighter blue for item header */
               font-size: 0.85rem;
               padding: 0.6rem 0.8rem;
               color: #1f2937; /* Dark text */
           }
            /* Adjusted widths for the two columns */
             .item-header th:nth-child(1) { width: 70%; } /* Combined Sl. No. / Description */
             .item-header th:nth-child(2) { width: 30%; text-align: right; } /* Amount */


           /* Item Details Rows */
            .item-row td {
                 font-size: 0.8rem;
                 padding: 0.4rem 0.8rem; /* Adjust padding here - less than header, maybe */
            }
             /* Styles for the combined cell */
             .item-row td:nth-child(1) {
                 text-align: left; /* Ensure text alignment */
             }
              /* Styles for the amount cell */
             .item-row td:nth-child(2) {
                 text-align: right; /* Align amount to the right */
                 font-weight: 500;
             }
            /* Style for the serial number inside the combined cell */
            .item-serial {
                font-weight: 600;
                color: #4b5563; /* Match label color */
                margin-right: 0.3rem; /* Space after serial number */
            }
            .item-text {
                 display: inline; /* Ensure description flows after serial */
            }

            /* Added style for empty cells to ensure height */
            .item-row td:empty::after {
                 content: "\00a0"; /* Non-breaking space to maintain height */
            }


           /* Summary Row */
            .summary-row td {
                 padding: 0.8rem; /* Consistent padding */
                 vertical-align: top;
                 background-color: #f7fafc; /* Light background */
            }
            .summary-row td:nth-child(1) {
                width: 65%; /* Increased width for Amount in Words side */
                 font-weight: bold;
                 font-size: 0.85rem;
            }
             .summary-row td:nth-child(2) {
                 width: 35%; /* Reduced width for Totals side */
                 padding: 0; /* Remove cell padding for totals sub-table */
            }

            /* Totals Sub-Table within Summary Row */
            .summary-totals-table {
                width: 100%;
                border-collapse: collapse;
                 border: none; /* No border on outer table */
            }
             .summary-totals-table td {
                 border: none; /* No border on inner td */
                 padding: 0.3rem 0.8rem;
                 font-size: 0.8rem;
             }
             .summary-totals-table td:nth-child(1) {
                  font-weight: 600; /* semibold */
                  color: #4b5563; /* gray-700 */
                  width: 60%; /* Width for label within sub-table */
                  text-align: left; /* Align label to left */
             }
              .summary-totals-table td:nth-child(2) {
                  font-weight: 700; /* bold */
                   text-align: right; /* Align amount to right */
                   width: 40%; /* Width for value within sub-table */
              }
              /* Specific colors for total values */
              .summary-totals-table .value-green { color: #059669; } /* green-600 */
              .summary-totals-table .value-red { color: #dc2626; } /* red-600 */
              .summary-totals-table .value-yellow { color: #d97706; } /* yellow-600 */
              .summary-totals-table .value-black { color: #000; } /* Black for zero balance */


            /* Footer Row */
            .footer-row td {
                 padding: 0.8rem; /* Consistent padding */
                 font-size: 0.8rem;
                 vertical-align: bottom; /* Align text to bottom */
                 background-color: #f7fafc; /* Light background */
            }
             .footer-row td:nth-child(1) {
                 width: 65%; /* Terms & Condition side - Match summary width */
                  font-weight: bold;
             }
              .footer-row td:nth-child(2) {
                  width: 35%; /* Seal & Signature side - Match summary width */
                   font-weight: bold;
                   text-align: center; /* Center signature text */
              }

              .seal-signature-line {
                  display: block;
                  margin-top: 2.5rem; /* Space for signature line */
                  text-align: center;
                   font-weight: normal; /* Not bold */
                   font-size: 0.7rem;
                  border-top: 1px dashed #718096; /* Dashed line */
                  padding-top: 0.5rem; /* Space above line */
                  width: 80%; /* Line width */
                  margin-left: auto; margin-right: auto; /* Center line */
              }
              .terms-text {
                  font-size: 0.7rem;
                  font-weight: normal;
                  margin-top: 0.5rem;
                  color: #4b5563; /* Gray text */
              }

            /* Print Button Styles */
            .print-button {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: white;
                border: none;
                padding: 0.6rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .print-button:hover {
                background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .print-button:active {
                transform: translateY(0);
            }


     </style>
     <script>
          document.addEventListener('DOMContentLoaded', function() {
              const toastContainer = document.getElementById('toastContainer');
              if (!toastContainer) {
                  console.error('Toast container #toastContainer not found.');
              }

              function showToast(message, type = 'info', duration = 5000) {
                  if (!message || !toastContainer) return;
                  const toast = document.createElement('div');
                  toast.classList.add('toast', `toast-${type}`);
                   const messageSpan = document.createElement('span');
                   messageSpan.textContent = message; // Use textContent for safety
                   toast.appendChild(messageSpan);
                  const closeButton = document.createElement('button');
                  closeButton.classList.add('close-button');
                  closeButton.innerHTML = '×'; // Use HTML entity for 'x'
                  closeButton.setAttribute('aria-label', 'Close');
                  closeButton.onclick = () => {
                       toast.classList.remove('show');
                       // Use transitionend to remove element only after animation is complete
                       toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                  };
                  toast.appendChild(closeButton);
                  toastContainer.appendChild(toast);
                  requestAnimationFrame(() => { toast.classList.add('show'); });
                  if (duration > 0) {
                      setTimeout(() => {
                          toast.classList.remove('show');
                           toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                      }, duration);
                  }
              }

              // Trigger toast display on DOM load if a message exists
              const phpToastMessage = <?php echo json_encode($toast_message ?? ''); ?>; // Handle case where it's not set
              const phpToastType = <?php echo json_encode($toast_type ?? 'info'); ?>; // Default type to info
              if (phpToastMessage) { showToast(phpToastMessage, phpToastType); }

              const printButton = document.getElementById('printReceiptButton');
              if (printButton) { printButton.addEventListener('click', function() { window.print(); }); }
          });
      </script>
</head>
<body>

    <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container no-print">
        <!-- Toasts will be dynamically added here -->
    </div>

    <div class="receipt-container">

        <!-- Back Link - Use the student_user_id variable if it was successfully set -->
        <div class="no-print mb-6 text-left px-4 py-2"> <!-- Added padding -->
             <?php
             // Safely check if $student_user_id variable is set and greater than 0
             $student_user_id_for_link = $student_user_id ?? 0; // Use the variable set during fetch
             if ($student_user_id_for_link > 0): ?>
                  <a href="view_all_receipts.php" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Student Fees</a>
             <?php else: ?>
                  <!-- Fallback to dashboard if student ID isn't available -->
                  <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Dashboard</a>
             <?php endif; ?>
        </div>

        <?php
        // --- Main Content Logic ---
        // Check if both fee record AND student data were successfully fetched
        $can_display_receipt = $fee_record && $student_data_for_receipt;

        if ($can_display_receipt):
            // If both are loaded, display the session message (e.g., success from add_monthly_fee.php)
            // AND display the receipt details.

            // Display and then clear the session message
            if ($session_message):
                 // Strip HTML tags from the session message before displaying as a basic paragraph
                 echo "<div class='mb-4 text-center p-3 rounded mx-6 " . htmlspecialchars($session_message_class) . " no-print'>"; // Use htmlspecialchars for the class name
                 echo strip_tags($session_message); // strip_tags for safety
                 echo "</div>";
                 unset($_SESSION['operation_message']); // Consume the message now that it's displayed
                 unset($_SESSION['operation_message_class']); // Consume the class too
            endif;
        ?>

            <!-- Receipt structured using tables for layout similar to the image -->
            <table class="receipt-table">
                <thead>
                    <!-- Header Row (School Branding) -->
                    <tr>
                        <th colspan="2" class="receipt-header-cell bg-blue-200"> <!-- Added background color -->
                            <!-- School Branding -->
                            <?php
                             // Safely check if logo path is set, not empty, and the file exists
                             $school_logo_path_display = $school_logo_path ?? ''; // Use the variable set earlier
                             $logo_exists = !empty($school_logo_path_display) && file_exists($school_logo_path_display);
                            ?>
                            <?php if ($logo_exists): ?>
                                <img src="<?php echo htmlspecialchars($school_logo_path_display); ?>" alt="<?php echo htmlspecialchars($school_name ?? 'School'); ?> Logo" class="school-logo">
                            <?php endif; ?>
                            <div class="school-name"><?php echo htmlspecialchars($school_name ?? 'School Name'); ?></div>
                            <div class="school-address"><?php echo htmlspecialchars($school_address ?? 'School Address'); ?></div>
                            <div class="school-phone">Phone: <?php echo htmlspecialchars($school_phone ?? 'N/A'); ?></div>
                        </th>
                    </tr>
                    <!-- Main Title Row -->
                     <tr>
                         <th colspan="2" class="bg-blue-100">Monthly Fee Receipt</th> <!-- Added background color -->
                     </tr>
                </thead>
                <tbody>
                    <!-- Receipt Info Row (No., Date, Time) -->
                    <tr class="receipt-info-row">
                        <td class="receipt-info-cell-left">
                             <?php
                               // Get creation date and time safely
                               $created_at_str = $fee_record['created_at'] ?? date('Y-m-d H:i:s'); // Default to current time if missing
                               $created_timestamp = strtotime($created_at_str);
                               $formatted_date = date('Y-m-d', $created_timestamp);
                               $formatted_time = date('H:i', $created_timestamp);
                             ?>
                            <p><strong>Receipt No.:</strong> <?php echo htmlspecialchars($fee_record['id'] ?? 'N/A'); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($formatted_date); ?></p>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($formatted_time); ?></p>
                             <!-- Assuming Payment Date/Type are not recorded for the *due* record -->
                             <!-- <p><strong>Payment Date:</strong> </p> -->
                             <!-- <p><strong>Payment Type:</strong> </p> -->
                        </td>
                        <td class="receipt-info-cell-right">
                            <!-- Space on the right, as per image -->
                        </td>
                    </tr>

                    <!-- Bill To / Student Details Section -->
                     <tr>
                         <td colspan="2" class="student-details-cell">
                             <span class="student-details-section-title">Bill To:</span>
                             <p><strong>Name:</strong> <?php echo htmlspecialchars($student_data_for_receipt['full_name'] ?? 'N/A'); ?></p>
                              <p><strong>Student Code:</strong> <?php echo htmlspecialchars($student_data_for_receipt['student_code'] ?? 'N/A'); ?></p>
                             <p><strong>Class:</strong> <?php echo htmlspecialchars($student_data_for_receipt['current_class'] ?? 'N/A'); ?></p>
                              <p><strong>Fee Period:</strong>
                                  <?php
                                      // Safely get fee month and year, default to current if missing
                                      $fee_month_int = (int)($fee_record['fee_month'] ?? date('m'));
                                      $fee_year_int = (int)($fee_record['fee_year'] ?? date('Y'));
                                      // Ensure valid month number before formatting
                                      $month_name = ($fee_month_int >= 1 && $fee_month_int <= 12) ? date('F', mktime(0, 0, 0, $fee_month_int, 1)) : 'Invalid Month';
                                      echo htmlspecialchars($month_name) . ", " . htmlspecialchars($fee_year_int);
                                  ?>
                              </p>
                              <!-- Address not fetched in this simplified query -->
                             <!-- <p><strong>Address:</strong></p> -->
                             <!-- Phone/Email not fetched in this simplified query -->
                             <!-- <p><strong>Phone No.:</strong></p> -->
                             <!-- <p><strong>Email ID:</strong></p> -->
                         </td>
                     </tr>

                    <!-- Item Details Table Header - COMBINED COLUMNS -->
                    <tr class="item-header">
                         <th>Item Description</th> <!-- Combined Header -->
                         <th style="text-align: right;">Amount (₹)</th> <!-- Amount Header -->
                    </tr>

                    <!-- Item Details Rows -->
                    <?php
                    $items = [];
                    // Only add items if the amount is greater than 0
                    $base_fee = (float)($fee_record['base_monthly_fee'] ?? 0);
                    if ($base_fee > 0) $items[] = ['description' => 'Base Fee', 'amount' => $base_fee];

                    $van_fee = (float)($fee_record['monthly_van_fee'] ?? 0);
                    if ($van_fee > 0) $items[] = ['description' => 'Van Fee', 'amount' => $van_fee];

                    $exam_fee = (float)($fee_record['monthly_exam_fee'] ?? 0);
                    if ($exam_fee > 0) $items[] = ['description' => 'Exam Fee', 'amount' => $exam_fee];

                    $electricity_fee = (float)($fee_record['monthly_electricity_fee'] ?? 0);
                    if ($electricity_fee > 0) $items[] = ['description' => 'Electricity Fee', 'amount' => $electricity_fee];

                     // If no specific breakdown items > 0 are found, show the total due as one item (if total due >= 0)
                     $amount_due = (float)($fee_record['amount_due'] ?? 0);
                     if (empty($items)) {
                          // Show 'Monthly Fee Total' even if amount_due is 0, for clarity
                          $items[] = ['description' => 'Monthly Fee Total', 'amount' => $amount_due];
                     }


                    $sl_no = 1;
                    foreach ($items as $item):
                     ?>
                         <tr class="item-row">
                             <td>
                                 <!-- Combined Sl. No. and Description -->
                                 <span class="item-serial"><?php echo $sl_no++; ?>.</span>
                                 <span class="item-text"><?php echo htmlspecialchars($item['description']); ?></span>
                             </td>
                              <td style="text-align: right;"><?php echo number_format($item['amount'], 2); ?></td> <!-- Amount -->
                         </tr>
                    <?php endforeach;

                    // Add empty rows to reach a minimum height, like in the image
                    $min_item_rows_for_height = 5; // Minimum number of rows for the item section visual height
                    $current_item_rows = count($items);
                    if ($current_item_rows < $min_item_rows_for_height) {
                         $empty_rows_to_add = $min_item_rows_for_height - $current_item_rows;
                         for ($i = 0; $i < $empty_rows_to_add; $i++) {
                            echo '<tr class="item-row">';
                            // Combined cell for empty rows - use non-breaking space for height
                            echo '<td><span class="item-serial">' . ($sl_no++) . '.</span><span class="item-text"> </span></td>'; // Continue Sl. No.
                            echo '<td> </td>'; // Empty amount cell with non-breaking space
                            echo '</tr>';
                         }
                    }
                    ?>

                    <!-- Summary Row -->
                    <tr class="summary-row">
                        <td>
                            <p><strong>Amount in Words:</strong></p>
                            <?php
                               // Safely get amount_due for words
                               $amount_due_for_words = (float)($fee_record['amount_due'] ?? 0);
                            ?>
                            <p><?php echo htmlspecialchars(amountInWordsWithRupees($amount_due_for_words)); ?></p>
                        </td>
                        <td>
                            <table class="summary-totals-table">
                                 <tr>
                                     <td>Amount Due:</td>
                                      <td><?php echo number_format($amount_due, 2); ?></td> <!-- Use $amount_due calculated earlier -->
                                 </tr>
                                  <tr>
                                      <td>Amount Paid:</td>
                                       <?php
                                         // Safely get amount_paid
                                         $amount_paid = (float)($fee_record['amount_paid'] ?? 0);
                                       ?>
                                       <td class="value-green"><?php echo number_format($amount_paid, 2); ?></td>
                                  </tr>
                                  <tr>
                                       <td>Balance Due:</td>
                                        <?php
                                          // Calculate balance safely
                                          $balance = $amount_due - $amount_paid;
                                          // Determine class based on balance value
                                          $balance_class = '';
                                          if ($balance > 0) {
                                              $balance_class = 'value-red'; // Still owes
                                          } elseif ($balance < 0) {
                                              $balance_class = 'value-yellow'; // Overpaid (credit)
                                          } else {
                                               $balance_class = 'value-black'; // Exactly paid (shows 0.00)
                                          }
                                        ?>
                                       <td class="<?php echo htmlspecialchars($balance_class); ?>">
                                           <?php echo number_format($balance, 2); // This will display 0.00 if balance is 0 ?>
                                       </td>
                                  </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer Row -->
                    <tr class="footer-row">
                        <td>
                             <p><strong>Terms & Condition:</strong></p>
                             <!-- Add your terms and conditions here -->
                             <p class="terms-text">
                                1. This receipt is valid only upon realization of payment.<br>
                                2. Please retain this receipt for future reference.<br>
                                3. Any discrepancies must be reported within 7 days.
                             </p>
                        </td>
                        <td>
                             <span class="seal-signature-line">For <?php echo htmlspecialchars($school_name ?? 'School Name'); ?></span> <!-- Signature line with school name -->
                             <p><strong>Authorized Signatory</strong></p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <!-- Print/Download Buttons -->
            <div class="no-print mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 px-6 pb-8"> <!-- Added padding -->
                <button id="printReceiptButton" class="print-button w-full sm:w-auto">
                    Print Receipt
                </button>
                 <button class="w-full sm:w-auto text-center px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 text-sm font-medium"
                         onclick="alert('To save as PDF:\n1. Click the \'Print Receipt\' button.\n2. In the print dialog, select \'Save as PDF\' or \'Microsoft Print to PDF\' (or equivalent) as the destination.\n3. Click \'Save\' or \'Print\'.\n\nTo save as an image:\n1. Load the receipt page on your screen.\n2. Use your device\'s screenshot function.\n3. Crop the image to include only the receipt content.');">
                    Download Help
                </button>
            </div>

        <?php else:
            // If either fee_record OR student_data_for_receipt is NOT available
            // Display the error message set during the fetch attempt.
            // The toast message for this error is handled by the JS.
        ?>
            <div class="text-red-600 text-center mb-4 p-4 bg-red-100 rounded mx-6"> <!-- Added padding and background -->
                <p class="font-medium"><?php echo htmlspecialchars($error ?? "Could not load complete receipt details. Please check the fee ID."); ?></p> <!-- More specific fallback -->
                <?php
                // If we failed to load the receipt, the session message (if any)
                // from the previous page is likely misleading *for this error block*.
                // Display it here if it's an error/warning type, then unset.
                 if ($session_message):
                     // Check if the session message class indicates an error or warning
                     if (strpos($session_message_class, 'red') !== false || strpos($session_message_class, 'yellow') !== false):
                     ?>
                        <p class="mt-2 text-sm <?php echo htmlspecialchars($session_message_class); ?>"><?php echo strip_tags($session_message); ?></p>
                     <?php
                     endif;
                     // Always unset the session message after attempting to display it
                     unset($_SESSION['operation_message']);
                     unset($_SESSION['operation_message_class']);
                 endif;
                ?>
            </div>
        <?php endif; ?>

    </div> <!-- End of receipt container -->

</body>
</html>