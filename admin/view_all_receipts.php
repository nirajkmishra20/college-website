<?php
// School/admin/view_all_receipts.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can view all fee receipts.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// Set the page title
$pageTitle = "All Fee Receipts";

// --- Variables ---
// Filter variables (using $_GET)
$filter_year = $_GET['academic_year'] ?? ''; // Use academic_year for consistency
$filter_month = $_GET['fee_month'] ?? '';
$filter_class = $_GET['current_class'] ?? '';
$filter_status = $_GET['paid_status'] ?? 'all'; // 'all', 'paid', 'due'

$receipt_records = []; // Array to hold fetched fee records

// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'

// Check for operation messages set in other pages (like from mark_paid, edit_monthly_fee, delete_monthly_fee)
if (isset($_SESSION['operation_message'])) {
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg));

     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false || strpos($msg_lower, 'welcome') !== false || strpos($msg_lower, 'marked as paid') !== false || strpos($msg_lower, 'payment recorded') !== false) {
          $toast_type = 'success';
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'problem') !== false || strpos($msg_lower, 'exists') !== false || strpos($msg_lower, 'duplicate') !== false) {
          $toast_type = 'error';
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false || strpos($msg_lower, 'missing') !== false || strpos($msg_lower, 'information required') !== false || strpos($msg_lower, 'skipped') !== false || strpos($msg_lower, 'partially failed') !== false) {
          $toast_type = 'warning';
     } else {
          $toast_type = 'info';
     }
    $toast_message = strip_tags($msg);
    unset($_SESSION['operation_message']);
}

// Available options for filters (fetched from DB)
$available_years = [];
$available_classes = [];

// --- Fetch Filter Options ---
if ($link === false) {
    if (empty($toast_message)) {
         $toast_message = "Database connection error. Cannot load filter options or fetch receipts.";
         $toast_type = 'error';
     }
     error_log("View All Receipts DB connection failed: " . mysqli_connect_error());
} else {
    // Fetch distinct academic years from monthly fees table
    $sql_years = "SELECT DISTINCT fee_year FROM student_monthly_fees WHERE fee_year IS NOT NULL ORDER BY fee_year DESC";
    if ($result_years = mysqli_query($link, $sql_years)) {
        while ($row = mysqli_fetch_assoc($result_years)) {
            $available_years[] = htmlspecialchars($row['fee_year']);
        }
        mysqli_free_result($result_years);
    } else {
         error_log("Error fetching years for filter: " . mysqli_error($link));
    }
     // Add current year and a few others if not present
     $current_year = (int)date('Y');
     $year_range = range($current_year + 2, $current_year - 5);
     foreach ($year_range as $yr) {
         if (!in_array($yr, $available_years)) {
             $available_years[] = $yr;
         }
     }
     rsort($available_years);


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


// --- Fetch Fee Records based on Filters ---
if ($link !== false) { // Only attempt to fetch if DB is connected

    // Base SQL query joining fees and students tables
    $sql_fetch_receipts = "SELECT
                                f.id, f.student_id, f.fee_year, f.fee_month,
                                f.base_monthly_fee, f.monthly_van_fee, f.monthly_exam_fee, f.monthly_electricity_fee,
                                f.amount_due, f.amount_paid, f.is_paid, f.payment_date, f.notes,
                                s.full_name, s.current_class, s.roll_number, s.takes_van
                           FROM student_monthly_fees f
                           JOIN students s ON f.student_id = s.user_id";

    $where_clauses = [];
    $param_types = "";
    $param_values = [];

    // Add filters to WHERE clause
    if (!empty($filter_year)) {
        $where_clauses[] = "f.fee_year = ?";
        $param_types .= "i";
        $param_values[] = (int)$filter_year; // Ensure integer type
    }

    if (!empty($filter_month)) {
        $where_clauses[] = "f.fee_month = ?";
        $param_types .= "i";
        $param_values[] = (int)$filter_month; // Ensure integer type
    }

    if (!empty($filter_class)) {
        $where_clauses[] = "s.current_class = ?";
        $param_types .= "s";
        $param_values[] = $filter_class;
    }

    if ($filter_status === 'paid') {
        // A record is considered 'paid' if amount_paid >= amount_due OR is_paid flag is 1
        $where_clauses[] = "(f.amount_paid >= f.amount_due OR f.is_paid = 1)";
        // No parameters needed for this clause alone
    } elseif ($filter_status === 'due') {
        // A record is considered 'due' if amount_paid < amount_due AND is_paid flag is 0
        $where_clauses[] = "(f.amount_paid < f.amount_due AND f.is_paid = 0)";
         // No parameters needed for this clause alone
    }
    // If $filter_status is 'all', no status clause is added.

    // Combine where clauses
    if (!empty($where_clauses)) {
        $sql_fetch_receipts .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Add ORDER BY clause
     // Order by Year DESC, Month ASC (chronological within year), then Class, then Student Name
    $sql_fetch_receipts .= " ORDER BY f.fee_year DESC, f.fee_month ASC, s.current_class ASC, s.full_name ASC";


    // Prepare and execute the statement
    if ($stmt = mysqli_prepare($link, $sql_fetch_receipts)) {
        if (!empty($param_types)) {
            // Use call_user_func_array to bind parameters dynamically
            $bind_params = [$param_types]; // Start with type string
            foreach ($param_values as &$value) {
                $bind_params[] = &$value; // Add references to values
            }
            // Call mysqli_stmt_bind_param with the statement and dynamic parameters
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind_params));
            unset($value); // Unset reference after binding
        }

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $receipt_records[] = $row;
                }
                mysqli_free_result($result); // Free result set
            } else {
                // No records found for the applied filters
                 if (!empty($_GET)) { // If filters were applied (GET request with parameters)
                      $toast_message = "No fee records found matching the selected filters.";
                      $toast_type = 'warning';
                 } else {
                      // No filters applied, but no records at all
                      $toast_message = "No monthly fee records found in the database.";
                      $toast_type = 'info';
                 }
            }
        } else {
            // Error executing query
            $db_error = mysqli_stmt_error($stmt);
            $toast_message = "Error fetching fee records. Database error: " . htmlspecialchars($db_error);
            $toast_type = 'error';
            error_log("View All Receipts query execution failed: " . $db_error);
        }
        mysqli_stmt_close($stmt);
    } else {
        // Error preparing statement
        $db_error = mysqli_error($link);
        $toast_message = "Error preparing fee records query. Database error: " . htmlspecialchars($db_error);
        $toast_type = 'error';
        error_log("View All Receipts prepare query failed: " . $db_error);
    }

    // Close connection
    if (isset($link) && is_object($link) && mysqli_ping($link)) {
         mysqli_close($link);
    }

} else {
     // DB connection failed message handled at the top
}


// Month names array for display
$month_names_display = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Admin Dashboard</title>
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
             padding-left: 16rem; /* Adjust based on your sidebar width */
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

         /* Form element styling for filters */
         .filter-form .form-control {
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
         .filter-form .form-control:focus {
             color: #495057;
             background-color: #fff;
             border-color: #80bdff;
             outline: 0;
             box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
         }


         /* --- Toast Notification Styles --- */
         .toast-container {
             position: fixed; top: 1rem; right: 1rem; z-index: 1000; display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; max-width: 90%;
         }
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto; min-width: 200px; max-width: 350px; display: flex; align-items: center; word-break: break-word; line-height: 1.4;
         }
         .toast.show { opacity: 1; transform: translateX(0); }
         .toast-success { border-left: 5px solid #10b981; color: #065f46; }
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }
         .toast .close-button {
             margin-left: auto; background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0 0.25rem; line-height: 1; font-weight: bold; opacity: 0.7; transition: opacity 0.2s ease;
         }
         .toast .close-button:hover { opacity: 1; }


          /* Receipt Table Styles */
         .receipt-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 1rem;
             font-size: 0.875rem; /* text-sm */
             box-shadow: 0 1px 3px rgba(0,0,0,0.1);
             border-radius: 0.5rem;
              overflow: hidden; /* Hides borders on rounded corners */
         }
         .receipt-table th,
         .receipt-table td {
             padding: 0.75rem 0.5rem; /* py-3 px-2 */
             border: 1px solid #e5e7eb; /* border-gray-200 */
             text-align: left;
             vertical-align: top; /* Align content to top in cells */
         }
         .receipt-table th {
             background-color: #e5e7eb; /* bg-gray-200 - slightly darker for header */
             font-weight: 600; /* semibold */
             color: #374151; /* gray-700 */
              white-space: nowrap; /* Prevent header text wrap */
         }
         .receipt-table tbody tr:nth-child(even) {
             background-color: #f9fafb; /* bg-gray-50 */
         }
          .receipt-table td {
              color: #1f2937; /* gray-900 */
          }

         .status-paid {
             color: #065f46; /* green-800 */
             font-weight: 600;
         }
         .status-due {
              color: #b91c1c; /* red-800 */
              font-weight: 600;
         }
          .status-na {
              color: #4b5563; /* gray-600 */
         }

         .receipt-table .action-link {
             color: #4f46e5; /* indigo-600 */
             text-decoration: none;
             font-weight: 500;
             display: inline-block;
             white-space: nowrap; /* Prevent link wrap */
             text-align: center; /* Center if only one link */
         }
         .receipt-table .action-link:hover {
             text-decoration: underline;
             color: #4338ca; /* indigo-700 */
         }
          /* Center the link in its cell */
          .receipt-table td:last-child {
               text-align: center;
          }


          /* Message box styles */
            .message-box {
                padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem;
            }
             .message-box p { margin: 0.5rem 0; }
             .message-box p:first-child { margin-top: 0; }
             .message-box p:last-child { margin-bottom: 0; }

             .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; }
              .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; }
              .message-box.warning { color: #92400e; background-color: #fef3c7; border-color: #fcd34d; }
              .message-box.info { color: #1e40af; background-color: #dbeafe; border-color: #93c5fd; }

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
                 toast.innerHTML = message; // Use innerHTML for messages from $_SESSION (strip_tags used in PHP)
                 toast.classList.add('toast', `toast-${type}`);

                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.innerHTML = '×'; // HTML entity for multiplication sign (×)
                 closeButton.setAttribute('aria-label', 'Close');
                 closeButton.onclick = () => {
                     toast.classList.remove('show');
                     toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                 };
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });

                 if (duration > 0) {
                     setTimeout(() => {
                         if (toast.classList.contains('show')) {
                             toast.classList.remove('show');
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
        echo "<div class='text-red-600 p-4'>Warning: admin_sidebar.php not found at " . htmlspecialchars($sidebar_path) . "</div>";
    }
    ?>

     <!-- Fixed Header -->
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

        <h2 class="text-2xl font-bold mb-6 text-gray-800">Monthly Fee Receipts</h2>

         <!-- Filter Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Filter Receipts</h3>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                <!-- Year Filter -->
                 <div>
                     <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year</label>
                     <select name="academic_year" id="academic_year" class="form-control mt-1 block w-full">
                         <option value="">All Years</option>
                         <?php foreach ($available_years as $year): ?>
                             <option value="<?php echo $year; ?>" <?php echo ((string)$filter_year === (string)$year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <!-- Month Filter -->
                 <div>
                     <label for="fee_month" class="block text-sm font-medium text-gray-700">Month</label>
                     <select name="fee_month" id="fee_month" class="form-control mt-1 block w-full">
                         <option value="">All Months</option>
                         <?php for ($m = 1; $m <= 12; $m++): ?>
                              <option value="<?php echo $m; ?>" <?php echo ((int)$filter_month === $m) ? 'selected' : ''; ?>><?php echo htmlspecialchars($month_names_display[$m] ?? 'Unknown'); ?></option>
                         <?php endfor; ?>
                     </select>
                 </div>
                <!-- Class Filter -->
                 <div>
                     <label for="current_class" class="block text-sm font-medium text-gray-700">Class</label>
                     <select name="current_class" id="current_class" class="form-control mt-1 block w-full">
                         <option value="">All Classes</option>
                         <?php foreach ($available_classes as $class): ?>
                             <option value="<?php echo $class; ?>" <?php echo ($filter_class === $class) ? 'selected' : ''; ?>><?php echo $class; ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                <!-- Status Filter -->
                 <div>
                     <label for="paid_status" class="block text-sm font-medium text-gray-700">Status</label>
                     <select name="paid_status" id="paid_status" class="form-control mt-1 block w-full">
                         <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                         <option value="paid" <?php echo ($filter_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                         <option value="due" <?php echo ($filter_status === 'due') ? 'selected' : ''; ?>>Due</option>
                     </select>
                 </div>
                <div class="md:col-span-4 flex justify-end"> <!-- Span and align right -->
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">Apply Filters</button>
                </div>
                
            </form>
        </div>


        <!-- Receipts Table -->
        <?php if (!empty($receipt_records)): ?>
            <div class="overflow-x-auto bg-white rounded-lg shadow-md"> <!-- Wrap table for responsiveness -->
                 <table class="receipt-table">
                     <thead>
                         <tr>
                             <th>Student Name</th>
                             <th>Class</th>
                             <th>Roll No.</th>
                             <th>Month</th>
                             <th>Year</th>
                             <th>Amount Due</th>
                             <th>Amount Paid</th>
                             <th>Amount Remaining</th>
                             <th>Status</th>
                             <th>Payment Date</th>
                              <th>Notes</th>
                             <th class="text-center">Actions</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($receipt_records as $record): ?>
                             <?php
                                // Calculate amount remaining
                                $amount_due = (float)($record['amount_due'] ?? 0);
                                $amount_paid = (float)($record['amount_paid'] ?? 0);
                                $amount_remaining = $amount_due - $amount_paid;

                                // Determine status text and class
                                $status_text = 'Due'; // Default
                                $status_class = 'status-due';

                                if ($amount_due <= 0 && $amount_paid <= 0) {
                                    $status_text = 'N/A'; // No fee was due or paid
                                    $status_class = 'status-na';
                                } elseif (($record['is_paid'] ?? 0) == 1 || $amount_remaining <= 0) {
                                    $status_text = 'Paid';
                                    $status_class = 'status-paid';
                                }

                                // Format Payment Date
                                $payment_date_display = (!empty($record['payment_date']) && $record['payment_date'] !== '0000-00-00' && $record['payment_date'] !== null) ? date('Y-m-d', strtotime($record['payment_date'])) : 'N/A';
                             ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></td>
                                 <td><?php echo htmlspecialchars($record['current_class'] ?? 'N/A'); ?></td>
                                  <td><?php echo htmlspecialchars($record['roll_number'] ?? 'N/A'); ?></td>
                                 <td><?php echo htmlspecialchars($month_names_display[$record['fee_month']] ?? 'N/A'); ?></td>
                                 <td><?php echo htmlspecialchars($record['fee_year'] ?? 'N/A'); ?></td>
                                 <td><?php echo '₹' . htmlspecialchars(number_format($amount_due, 2)); ?></td>
                                 <td><?php echo '₹' . htmlspecialchars(number_format($amount_paid, 2)); ?></td>
                                 <td><?php echo '₹' . htmlspecialchars(number_format(max(0, $amount_remaining), 2)); ?></td> <!-- Show 0 if negative -->
                                 <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                 <td><?php echo $payment_date_display; ?></td>
                                  <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                 <td class="text-center">
                                     <!-- Link to view individual receipt -->
                                     <a href="view_receipt.php?fee_id=<?php echo htmlspecialchars($record['id']); ?>&student_id=<?php echo htmlspecialchars($record['student_id']); ?>"
                                        class="action-link text-sm">View Receipt</a>
                                     <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): ?>
                                          <br>
                                           <a href="edit_monthly_fee.php?id=<?php echo htmlspecialchars($record['id']); ?>&student_id=<?php echo htmlspecialchars($record['student_id']); ?>"
                                            class="action-link text-sm">Edit</a>
                                     <?php endif; ?>
                                       <?php if ($_SESSION['role'] === 'admin'): ?>
                                          <br>
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        <?php else: ?>
            <!-- Message if no records found -->
            <div class="message-box <?php echo $toast_type ?: 'info'; ?>">
                <p><?php
                     // Display a specific message if a toast message was set due to no records found
                     if (!empty($toast_message) && ($toast_type === 'warning' || $toast_type === 'info')) {
                          echo htmlspecialchars($toast_message);
                     } elseif ($link === false) {
                         // DB connection error message
                         echo "Database connection failed. Could not load fee records.";
                     } else {
                         // Default message if no filters applied initially and no records exist
                         echo "No monthly fee records found in the database.";
                     }
                ?></p>
            </div>
        <?php endif; ?>

    </div> <!-- End main-content-wrapper -->

<?php
// Include the footer file.
$footer_path = "./admin_footer.php";
if (file_exists($footer_path)) {
    require_once $footer_path;
} else {
     echo '</body></html>'; // Fallback closing tags
}
?>