<?php
// School/admin/manage_expenses.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can manage expenses.</p>";
    header("location: ../login.php");
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Manage Expenses";

// --- Variables for Filtering ---
$filter_year = $_GET['filter_year'] ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$current_year = date('Y'); // Get current year for default filter/options
$current_month = date('m'); // Get current month

// --- Variables for Messages ---
$operation_message = ""; // For messages from other pages (e.g., successful add/edit/delete)
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}
$fetch_expenses_message = ""; // Message for expense data fetch status


// --- Fetch Filter Options (Years and Months with Expenses) ---
$available_years = [];
$available_months = []; // For the selected year

// Database connection check
if ($link === false) {
    $fetch_expenses_message = "<p class='text-red-600'>Database connection error. Could not load expenses.</p>";
     error_log("Manage Expenses DB connection failed: " . mysqli_connect_error());
} else {
     // Query for available years
     $sql_years = "SELECT DISTINCT YEAR(expense_date) AS year FROM expenses ORDER BY year DESC";
     if ($result_years = mysqli_query($link, $sql_years)) {
          while ($row = mysqli_fetch_assoc($result_years)) {
              $available_years[] = $row['year'];
          }
          mysqli_free_result($result_years);
     } else {
          error_log("Error fetching distinct years for expenses: " . mysqli_error($link));
          // This error won't stop the page, just means filter options might be empty
     }

     // Query for available months for the selected year (or current year if none selected)
     $year_for_months = empty($filter_year) ? $current_year : (int)$filter_year;
     $sql_months = "SELECT DISTINCT MONTH(expense_date) AS month FROM expenses WHERE YEAR(expense_date) = ? ORDER BY month ASC";
      if ($stmt_months = mysqli_prepare($link, $sql_months)) {
          mysqli_stmt_bind_param($stmt_months, "i", $year_for_months);
           if (mysqli_stmt_execute($stmt_months)) {
              $result_months = mysqli_stmt_get_result($stmt_months);
               while ($row = mysqli_fetch_assoc($result_months)) {
                   // Format month number to padded string (01-12)
                   $available_months[] = sprintf("%02d", $row['month']);
               }
              mysqli_free_result($result_months);
          } else {
               error_log("Error executing fetch distinct months for expenses: " . mysqli_stmt_error($stmt_months));
          }
          mysqli_stmt_close($stmt_months);
      } else {
          error_log("Error preparing fetch distinct months statement: " . mysqli_error($link));
      }
}


// --- Build the SQL query for fetching expenses ---
$sql_fetch_expenses = "SELECT expense_id, expense_date, description, category, amount, payment_method, recorded_by_user_id, created_at FROM expenses";
$where_clauses = [];
$param_types = "";
$params = [];

// Add WHERE clauses based on filters
if (!empty($filter_year)) {
    $where_clauses[] = "YEAR(expense_date) = ?";
    $param_types .= "i";
    $params[] = (int)$filter_year;
}

if (!empty($filter_month)) {
    // Ensure month is 2 digits for comparison (optional, but good practice)
    $formatted_month = sprintf("%02d", (int)$filter_month);
    $where_clauses[] = "MONTH(expense_date) = ?";
    $param_types .= "i";
    $params[] = (int)$filter_month; // Use integer for binding
}

// Combine WHERE clauses if any exist
if (!empty($where_clauses)) {
    $sql_fetch_expenses .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add ORDER BY clause
$sql_fetch_expenses .= " ORDER BY expense_date DESC, created_at DESC";


// --- Handle CSV Download ---
// Check if the download parameter is set before executing the query for display
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    if ($link === false) {
         // Headers might already be sent if there was a DB connection error message displayed earlier
        if (!headers_sent()) {
             header('Content-Type: text/csv');
             header('Content-Disposition: attachment; filename="expenses_export_' . date('Ymd_His') . '.csv"');
             // Prevent caching
             header('Cache-Control: no-cache, no-store, must-revalidate');
             header('Pragma: no-cache');
             header('Expires: 0');
        }
         $output = fopen('php://output', 'w');
         fputcsv($output, ['Database connection error.']);
         fclose($output);
         exit; // Stop script execution after outputting error
    }

    $expenses_data = [];
    if ($stmt_download = mysqli_prepare($link, $sql_fetch_expenses)) {
         // Bind parameters if any exist
         if (!empty($params)) {
             mysqli_stmt_bind_param($stmt_download, $param_types, ...$params);
         }

         if (mysqli_stmt_execute($stmt_download)) {
             $result_download = mysqli_stmt_get_result($stmt_download);
             if ($result_download) {
                 $expenses_data = mysqli_fetch_all($result_download, MYSQLI_ASSOC);
                 mysqli_free_result($result_download);
             } else {
                 error_log("Error getting result set for download: " . mysqli_stmt_error($stmt_download));
             }
         } else {
             error_log("Error executing download query: " . mysqli_stmt_error($stmt_download));
         }
         mysqli_stmt_close($stmt_download);
    } else {
         error_log("Error preparing download statement: " . mysqli_error($link));
    }

    // Output CSV
    if (!headers_sent()) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="expenses_export_' . date('Ymd_His') . '.csv"');
        // Prevent caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    $output = fopen('php://output', 'w');

    // Output CSV headers
    $header_row = ['Expense ID', 'Date', 'Description', 'Category', 'Amount', 'Payment Method', 'Recorded By User ID', 'Created At'];
    fputcsv($output, $header_row);

    // Output data rows
    if (!empty($expenses_data)) {
        foreach ($expenses_data as $row) {
            // Format date/time for CSV if needed, handle possible nulls
            $row['expense_date'] = $row['expense_date'] ? date('Y-m-d', strtotime($row['expense_date'])) : '';
            $row['created_at'] = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '';
            // Ensure amount is formatted correctly for CSV if needed, though DECIMAL(10,2) should store correctly
             $row['amount'] = number_format($row['amount'], 2, '.', '');

             // Add other fields
            fputcsv($output, $row);
        }
    } else {
         // Output a message indicating no data if applicable
         // This message will appear as the only row in the CSV if no data matched the filter
         $message = "No expenses found for the selected filters.";
         if (!empty($filter_year) || !empty($filter_month)) {
             $message .= " (Year: {$filter_year}" . (!empty($filter_month) ? ", Month: {$filter_month}" : "") . ")";
         }
         fputcsv($output, [$message]);
    }

    fclose($output);
    exit; // Stop script execution after download
}

// --- Fetch Expenses for Display (if not downloading) ---
$expenses = [];
$total_filtered_amount = 0; // Variable to store the sum of displayed expenses

// Only execute fetch if DB connection is good and not downloading
if ($link !== false) {
    if ($stmt = mysqli_prepare($link, $sql_fetch_expenses)) {
        // Bind parameters if any exist
        if (!empty($params)) {
            // Use call_user_func_array or ...$params for dynamic binding
             mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if ($result) { // Check if get_result was successful
                 if (mysqli_num_rows($result) > 0) {
                     $expenses = mysqli_fetch_all($result, MYSQLI_ASSOC);

                     // Calculate total amount for displayed expenses
                     foreach($expenses as $expense) {
                         $total_filtered_amount += $expense['amount'];
                     }

                     $fetch_expenses_message = "Displaying " . count($expenses) . " expense record(s)." . (!empty($where_clauses) ? " (Filtered)" : "");
                 } else {
                      $fetch_expenses_message = "No expense records found" . (!empty($where_clauses) ? " for the selected filters." : " yet.");
                 }
                 mysqli_free_result($result); // Free result set
            } else {
                $fetch_expenses_message = "<p class='text-red-600'>Error getting result set: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
                error_log("Error getting result set in manage_expenses.php: " . mysqli_stmt_error($stmt));
            }
        } else {
            $fetch_expenses_message = "<p class='text-red-600'>Error executing query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
            error_log("Error executing fetch expenses query: " . mysqli_stmt_error($stmt));
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $fetch_expenses_message = "<p class='text-red-600'>Database statement preparation failed: " . htmlspecialchars(mysqli_error($link)) . "</p>";
         error_log("Error preparing fetch expenses statement: " . mysqli_error($link));
    }
}


// Close database connection at the very end (if not already closed for download)
// This check prevents errors if $link was never successfully created or was closed earlier
if (isset($link) && is_object($link) && method_exists($link, 'ping') && @mysqli_ping($link)) { // Use @ to suppress warning if ping fails
     mysqli_close($link);
}

// Include the header file.
require_once "./admin_header.php";
?>

    <!-- Custom styles specific to this page -->
     <style>
         body { padding-top: 4.5rem; transition: padding-left 0.3s ease; }
         body.sidebar-open { padding-left: 16rem; }

          /* Custom nth-child styling for table rows (striped rows) */
           .data-table tbody tr:nth-child(even) {
               background-color: #f9fafb; /* Tailwind gray-50 */
           }
            /* Hover effect for table rows */
           .data-table tbody tr:hover {
               background-color: #f3f4f6; /* Tailwind gray-100 */
           }
            /* Styles for the details modal overlay (if reused from dashboard) */
            .modal-overlay {
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background-color: rgba(0, 0, 0, 0.75); z-index: 40;
                display: flex; justify-content: center; align-items: center;
                padding: 1rem; overflow-y: auto;
                visibility: hidden; opacity: 0;
                transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
            }
             .modal-overlay.visible { visibility: visible; opacity: 1; }
             .modal-content {
                 background-color: #fff; padding: 2rem; border-radius: 0.75rem;
                 box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 32rem; width: 100%;
                 position: relative;
                 transform: scale(0.95); transition: transform 0.3s ease-in-out;
             }
             .modal-overlay.visible .modal-content { transform: scale(1); }
             #detailsCloseButton {
                position: absolute; top: 1rem; right: 1rem;
                background: none; border: none; font-size: 1.5rem;
                cursor: pointer; color: #6b7280; transition: color 0.2s ease; z-index: 50;
            }
             #detailsCloseButton:hover { color: #4b5563; }

           /* Style for the background changer buttons/container */
          .background-changer {
              margin-top: 2rem;
              text-align: center;
              font-size: 0.875rem;
              color: #4b5563;
              padding-bottom: 2rem;
          }
           .background-changer button {
              margin-left: 0.5rem;
              padding: 0.25rem 0.75rem;
              border-radius: 0.25rem;
              font-size: 0.75rem;
              font-weight: 500;
              transition: opacity 0.2s ease, background-color 0.2s ease, color 0.2s ease;
          }
           .background-changer button.gradient-background-blue-cyan { background: linear-gradient(to right, #60a5fa, #22d3ee); color: white; border: 1px solid #3b82f6; }
           .background-changer button.gradient-background-purple-pink { background: linear-gradient(to right, #a78bfa, #f472b6); color: white; border: 1px solid #8b5cf6; }
           .background-changer button.gradient-background-green-teal { background: linear-gradient(to right, #34d399, #2dd4bf); color: white; border: 1px solid #10b981; }
            .background-changer button.solid-bg-gray { background-color: #e5e7eb; color: #1f2937; border: 1px solid #d1d5db; }
             .background-changer button.solid-bg-indigo { background-color: #6366f1; color: white; border: 1px solid #4f46e5; }
           .background-changer button:hover { opacity: 0.9; }


     </style>

     <script>
         // Ensure the setBackground function is available globally or define it here
          function setBackground(className) {
              const body = document.body;
              const backgroundClasses = [
                  'gradient-background-blue-cyan', 'gradient-background-purple-pink',
                  'gradient-background-green-teal', 'solid-bg-gray', 'solid-bg-indigo'
              ];
              body.classList.remove(...backgroundClasses);
              body.classList.add(className);
              localStorage.setItem('dashboardBackground', className);
          }

         // Simple Client-Side Search for table
         function filterTableRows(tableId, searchInputId) {
             const searchInput = document.getElementById(searchInputId);
             const table = document.getElementById(tableId);
             const tableBody = table ? table.querySelector('tbody') : null;

             if (!searchInput || !tableBody) {
                 console.warn(`Search elements not found for table "${tableId}" and input "${searchInputId}". Skipping search setup.`);
                 return;
             }

             searchInput.addEventListener('input', function() {
                 const searchTerm = this.value.toLowerCase().trim();
                 const rows = tableBody.querySelectorAll('tr');

                 rows.forEach(row => {
                     let rowText = '';
                      // Concatenate text content of all cells in the row, excluding the 'Actions' column
                      const cellsToSearch = row.querySelectorAll('td:not(.actions)');
                      cellsToSearch.forEach(cell => {
                          rowText += cell.textContent ? cell.textContent.toLowerCase() + ' ' : '';
                      });

                     if (rowText.includes(searchTerm)) {
                         row.style.display = '';
                     } else {
                         row.style.display = 'none';
                     }
                 });
             });
         }

         document.addEventListener('DOMContentLoaded', function() {
             // Apply saved background preference on load
              const savedBackgroundClass = localStorage.getItem('dashboardBackground');
              if (savedBackgroundClass) {
                   const allowedBackgroundClasses = [
                       'gradient-background-blue-cyan', 'gradient-background-purple-pink',
                       'gradient-background-green-teal', 'solid-bg-gray', 'solid-bg-indigo'
                   ];
                  if (allowedBackgroundClasses.includes(savedBackgroundClass)) {
                       setBackground(savedBackgroundClass);
                  } else {
                       console.warn(`Saved background class "${savedBackgroundClass}" is not recognized.`);
                       // Optionally set a default or do nothing
                  }
              }

             // Initialize client-side search for the expense table
             filterTableRows('expenseTable', 'expenseSearchInput');

             // Add confirmation for delete links
             document.querySelectorAll('.delete-link').forEach(link => {
                 link.addEventListener('click', function(event) {
                     if (!confirm('Are you sure you want to delete this expense record? This cannot be undone!')) {
                         event.preventDefault(); // Stop the link from navigating if the user cancels
                     }
                 });
             });

              // --- Initialize Month Filter Options ---
              const yearFilter = document.getElementById('filter_year');
              const monthFilter = document.getElementById('filter_month');
              const availableMonthsData = <?php echo json_encode($available_months); ?>; // Pass available months from PHP

              // Function to update month options based on selected year (basic implementation)
              // Note: This simple version only shows months if the *specifically fetched* months
              // from PHP included that month for the *initially selected/current* year.
              // A more robust version would require an AJAX call to fetch months for the newly selected year.
               function updateMonthOptions() {
                   // For this basic implementation, we'll just reset the month filter if a year changes.
                   // A more advanced version would fetch months via AJAX.
                   // If you need the advanced version, let me know!
                    // For now, we just populate the available months initially from PHP's fetch
               }

               // Add event listener to year filter (basic reset logic)
              if(yearFilter && monthFilter) {
                   yearFilter.addEventListener('change', function() {
                        // When the year changes, you might want to clear the month filter
                        // or reload the page to get updated month options from the server.
                        // The current setup reloads the page on form submit, which fetches
                        // the correct months for the selected year from the server.
                        // No client-side month update needed for this server-side filter approach.
                   });
              }
         });

          // Function to reset filters (clear year and month)
          function resetFilters() {
              document.getElementById('filter_year').value = '';
              document.getElementById('filter_month').value = '';
              // Optionally submit the form automatically
              document.getElementById('filterForm').submit();
          }

     </script>


    <!-- Main content wrapper -->
     <div class="w-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

         <!-- Operation Message Display -->
         <?php
         if (!empty($operation_message)) {
             // Determine message type based on keywords for styling
             $message_type_class = 'info'; // Default to info
              $msg_lower = strtolower(strip_tags($operation_message));
              if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                   $message_type_class = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_type_class = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos( $msg_lower, 'not found') !== false || strpos($msg_lower, 'no records found') !== false) { // Added no records found
                   $message_type_class = 'warning';
              }
             // Use Tailwind classes for message box styling
             $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
             switch ($message_type_class) {
                 case 'success': $message_classes .= "bg-green-100 border-green-300 text-green-800"; break;
                 case 'error':   $message_classes .= "bg-red-100 border-red-300 text-red-800"; break;
                 case 'warning': $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; break;
                 case 'info':
                 default:        $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; break; // Use light blue for info
             }
             echo "<div class='{$message_classes}' role='alert'>" . $operation_message . "</div>";
         }
         ?>

         <!-- Page Title -->
         <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center"><?php echo htmlspecialchars($pageTitle); ?></h1>

         <!-- Back to Dashboard Link -->
         <div class="mb-6 text-center">
             <a href="./admin_dashboard.php" class="text-indigo-600 hover:underline text-sm">← Back to Dashboard</a>
         </div>


         <!-- Filter and Actions Section -->
         <div class="bg-white p-6 rounded-xl shadow-md mb-8">
             <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Expenses</h3>

             <form id="filterForm" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex flex-wrap items-end gap-4 mb-4">
                 <!-- Year Filter -->
                 <div class="w-full sm:w-auto flex-grow sm:flex-grow-0">
                     <label for="filter_year" class="block text-gray-700 text-sm font-medium mb-1">Year:</label>
                     <select id="filter_year" name="filter_year" class="form-select w-full">
                         <option value="">All Years</option>
                         <?php foreach ($available_years as $year): ?>
                              <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ((string)$year === $filter_year) ? 'selected' : ''; ?>>
                                 <?php echo htmlspecialchars($year); ?>
                              </option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <!-- Month Filter -->
                 <div class="w-full sm:w-auto flex-grow sm:flex-grow-0">
                     <label for="filter_month" class="block text-gray-700 text-sm font-medium mb-1">Month:</label>
                     <select id="filter_month" name="filter_month" class="form-select w-full">
                         <option value="">All Months</option>
                          <?php
                            // Map month numbers to names
                            $month_names = [
                                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                            ];
                           ?>
                          <?php
                          // Show all months if a year is selected, otherwise show only months that exist in the current default year fetch
                          // A better way for dynamic month options is via AJAX, but this simpler approach works with server-side filtering.
                          $months_to_show = !empty($filter_year) ? array_keys($month_names) : $available_months;
                          ?>
                         <?php foreach ($months_to_show as $month_num): ?>
                              <?php $month_name = $month_names[$month_num] ?? 'Unknown'; ?>
                              <option value="<?php echo htmlspecialchars($month_num); ?>" <?php echo ((string)$month_num === $filter_month) ? 'selected' : ''; ?>>
                                 <?php echo htmlspecialchars($month_name); ?>
                              </option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <!-- Filter Button -->
                 <div class="w-full sm:w-auto flex-shrink-0 mt-2 sm:mt-0">
                     <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline w-full">
                         Apply Filter
                     </button>
                 </div>

                 <!-- Reset Button -->
                 <?php if (!empty($filter_year) || !empty($filter_month)): // Show reset only if filters are applied ?>
                      <div class="w-full sm:w-auto flex-shrink-0 mt-2 sm:mt-0">
                           <button type="button" onclick="resetFilters()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline w-full">
                               Reset Filter
                           </button>
                      </div>
                 <?php endif; ?>
             </form>

             <!-- Search Input (Client-side) -->
             <div class="mb-4 mt-4">
                 <label for="expenseSearchInput" class="block text-gray-700 text-sm font-medium mb-1">Search Table:</label>
                 <input type="text" id="expenseSearchInput" placeholder="Search by description, category, method, etc." class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
             </div>


             <!-- Action Buttons (Add New, Download) -->
             <div class="flex flex-wrap justify-end gap-4 mt-6 border-t pt-4">
                  <a href="./add_expense.php" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md font-medium text-sm transition flex-shrink-0 no-underline">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                      </svg>
                      Add New Expense
                  </a>
                  <?php if (!empty($expenses)): // Only show download if there are expenses to show ?>
                       <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?filter_year=' . urlencode($filter_year) . '&filter_month=' . urlencode($filter_month) . '&download=csv'); ?>"
                          class="inline-flex items-center bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-md font-medium text-sm transition flex-shrink-0 no-underline"
                          title="Download Filtered Data as CSV">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                             <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                           </svg>
                           Download CSV
                       </a>
                  <?php endif; ?>
             </div>

         </div>


         <!-- Expense List Section -->
         <div class="bg-white p-6 rounded-xl shadow-md">
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">Expense List</h2>

              <!-- Expense Fetch Message -->
             <?php
              if (!empty($fetch_expenses_message)) {
                  // Determine message type
                  $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
                  $msg_lower = strtolower(strip_tags($fetch_expenses_message));
                  if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not load') !== false) {
                       $message_classes .= "bg-red-100 border-red-300 text-red-800"; // Error
                  } elseif (strpos($msg_lower, 'no expense records found') !== false) {
                       $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; // Warning for no records
                  } else { // Default to info for "Displaying..." message
                      $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; // Info
                  }
                   echo "<div class='{$message_classes}' role='alert'>" . htmlspecialchars($fetch_expenses_message) . "</div>";
              }
             ?>

             <?php if (!empty($expenses)): ?>
                 <!-- Display Total for Filtered Data -->
                 <div class="text-right mb-4 text-lg font-bold text-gray-800">
                      Total: ₹ <?php echo number_format($total_filtered_amount, 2); ?>
                 </div>

                 <!-- Expense Table -->
                 <div class="overflow-x-auto">
                     <table class="min-w-full divide-y divide-gray-200 data-table" id="expenseTable">
                         <thead>
                             <tr>
                                 <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 rounded-tl-lg">Date</th>
                                 <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Description</th>
                                 <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Category</th>
                                 <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Amount (₹)</th>
                                 <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Method</th>
                                 <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Recorded At</th>
                                 <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 whitespace-nowrap rounded-tr-lg actions">Actions</th>
                             </tr>
                         </thead>
                         <tbody class="divide-y divide-gray-200">
                             <?php foreach ($expenses as $expense): ?>
                                 <tr>
                                     <td class="px-4 py-3 text-sm text-gray-800"><?php echo htmlspecialchars(date('Y-m-d', strtotime($expense['expense_date']))); ?></td>
                                     <td class="px-4 py-3 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($expense['description'])); ?></td>
                                     <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($expense['category'] ?? 'N/A'); ?></td>
                                     <td class="px-4 py-3 text-right text-sm text-gray-800 font-semibold"><?php echo htmlspecialchars(number_format($expense['amount'], 2)); ?></td>
                                     <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($expense['payment_method']); ?></td>
                                      <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($expense['created_at']))); ?></td>
                                     <td class="actions px-4 py-3 text-center whitespace-nowrap text-xs md:text-sm">
                                          <?php // Only admin/principal can edit/delete ?>
                                          <a href="./edit_expense.php?id=<?php echo htmlspecialchars($expense['expense_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">Edit</a>
                                          <a href="./delete_expense.php?id=<?php echo htmlspecialchars($expense['expense_id']); ?>" class="text-red-600 hover:underline font-medium delete-link">Delete</a>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
             <?php else: ?>
                 <!-- Display if no expenses are found (after the message) -->
                  <?php if (strpos(strtolower(strip_tags($fetch_expenses_message)), 'no expense records found') !== false && strpos(strtolower(strip_tags($fetch_expenses_message)), 'error') === false): ?>
                       <div class="text-center text-gray-600 p-4 border rounded-md bg-gray-50">No expense records match the criteria.</div>
                  <?php endif; ?>
             <?php endif; ?>
         </div>

         <!-- Background Changer Buttons -->
         <div class="background-changer">
              <span class="font-medium mr-2">Choose Background:</span>
             <button type="button" class="gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
             <button type="button" class="gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
              <button type="button" class="gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
              <button type="button" class="solid-bg-gray" onclick="setBackground('solid-bg-gray')">Gray</button>
              <button type="button" class="solid-bg-indigo" onclick="setBackground('solid-bg-indigo')">Indigo</button>
         </div>

     </div> <!-- End main content wrapper -->

<?php
// Include the footer file
require_once "./admin_footer.php";
?>