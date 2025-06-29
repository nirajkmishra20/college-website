<?php
// School/teacher/manage_principles.php

// Start the session
session_start();

// Include database configuration file
// Assuming config.php is in the directory *above* the admin folder
require_once "../config.php";

// Check if the user is logged in and is ADMIN
// Only users with role 'admin' can manage principle records (assuming this permission structure)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    // Store the message in session before redirecting
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can manage principle records.</p>";
    // Path from 'admin/' up to 'School/' is '../'
    header("location: ../login.php"); // Redirect to login
    exit;
}

// Define the role this page manages
$managed_role = 'principle'; // Filter for this specific role

// --- Handle CSV Download Request ---
// This block MUST come before any HTML output
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    // Re-check admin privilege for the download action
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
         header("HTTP/1.1 403 Forbidden");
         echo "Access Denied.";
         exit;
    }

    // Fetch principle data for the CSV - FILTERED BY ROLE
    $sql_fetch_principles_csv = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff WHERE role = ? ORDER BY staff_name ASC"; // Order by name within the single 'principle' role
    $principle_data_csv = []; // Renamed variable
    if ($link !== false && $stmt_csv = mysqli_prepare($link, $sql_fetch_principles_csv)) {
        // Bind the role parameter
        mysqli_stmt_bind_param($stmt_csv, "s", $managed_role);

        if (mysqli_stmt_execute($stmt_csv)) {
            $result_csv = mysqli_stmt_get_result($stmt_csv);
            $principle_data_csv = mysqli_fetch_all($result_csv, MYSQLI_ASSOC); // Renamed variable
            mysqli_free_result($result_csv);
        } else {
            error_log("CSV Download fetch principles query failed: " . mysqli_stmt_error($stmt_csv));
            header("Content-Type: text/plain");
            echo "Error fetching data for download.";
            exit;
        }
        mysqli_stmt_close($stmt_csv);
    } else {
         error_log("CSV Download prepare fetch principles statement failed: " . mysqli_error($link));
         header("Content-Type: text/plain");
         echo "Error preparing data query for download.";
         exit;
    }

    // Set CSV Headers
    header('Content-Type: text/csv');
    // Renamed filename
    header('Content-Disposition: attachment; filename="principle_data_' . date('Ymd') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV Header Row - Kept same structure as staff for consistency
    fputcsv($output, ['ID', 'Unique ID', 'Name', 'Mobile', 'Email', 'Role', 'Salary', 'Subject(s) Taught', 'Class(es) Taught', 'Created At', 'Photo URL']);

    // Write Data Rows - Loop through principle data
    foreach ($principle_data_csv as $row) { // Renamed variable
         // fputcsv automatically handles quoting fields with commas, etc.
         // Ensure the order matches the header row
         fputcsv($output, [
             $row['staff_id'],
             $row['unique_id'],
             $row['staff_name'],
             $row['mobile_number'],
             $row['email'],
             $row['role'],
             $row['salary'] ?? '', // Use ?? '' to handle potential NULLs gracefully in CSV
             $row['subject_taught'] ?? '', // These might be empty for a principle
             $row['classes_taught'] ?? '', // These might be empty for a principle
             $row['created_at'],
             $row['photo_filename'] ?? '' // Added Photo URL here
         ]);
    }

    // Close output stream
    fclose($output);

    // Prevent the rest of the HTML from loading
    exit;
}


// --- PHP Code to Fetch Principle Data for Display (Runs if not a download request) ---
$principles = []; // Renamed variable
$fetch_principles_message = ""; // Renamed variable

// Prepare a select statement to fetch principle data - FILTERED BY ROLE
$sql_fetch_principles = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff WHERE role = ? ORDER BY staff_name ASC"; // Order by name within role


if ($link === false) {
    $fetch_principles_message = "<p class='text-red-600'>Database connection error. Could not load principle list.</p>"; // Renamed message
     error_log("Manage Principles DB connection failed: " . mysqli_connect_error()); // Log the actual error
} else {
    if ($stmt_principles = mysqli_prepare($link, $sql_fetch_principles)) { // Renamed statement variable

        // Bind the role parameter
        mysqli_stmt_bind_param($stmt_principles, "s", $managed_role);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt_principles)) {
            $result_principles = mysqli_stmt_get_result($stmt_principles); // Renamed result variable

            // Check if there are results
            if ($result_principles && mysqli_num_rows($result_principles) > 0) { // Check if result is valid and has rows
                $principles = mysqli_fetch_all($result_principles, MYSQLI_ASSOC); // Renamed variable
                 // $fetch_principles_message = "Displaying all principle records."; // Optional success message
            } else {
                $fetch_principles_message = "No principle records found yet."; // Renamed message
            }

            // Free result set
            if ($result_principles) mysqli_free_result($result_principles); // Check if result is valid before freeing

        } else {
             $fetch_principles_message = "<p class='text-red-600'>Error fetching principle data: " . mysqli_stmt_error($stmt_principles) . "</p>"; // Renamed message
             error_log("Manage Principles fetch query failed: " . mysqli_stmt_error($stmt_principles)); // Log the actual error
        }

        // Close statement
        if ($stmt_principles) mysqli_stmt_close($stmt_principles); // Check if statement is valid before closing
    } else {
         $fetch_principles_message = "<p class='text-red-600'>Error preparing principle fetch statement: " . mysqli_error($link) . "</p>"; // Renamed message
         error_log("Manage Principles prepare fetch statement failed: " . mysqli_error($link)); // Log the actual error
    }
}


// Check for and display messages from create/edit/delete operations (stored in session)
$operation_message = "";
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear the message after displaying
}


// Close database connection at the very end
// Ensure $link is a valid connection resource before closing
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Principles</title> <!-- Updated Title -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
         /* Keep existing styles from manage_staff.php */
         /* Custom styles for subtle error message appearance */
         .form-error {
             color: #dc3545; /* Red */
             font-size: 0.875em; /* text-xs */
             margin-top: 0.25em;
             display: block; /* Ensure it takes its own line */
         }
          .form-control.is-invalid {
              border-color: #dc3545; /* Red border */
          }

            /* Custom background style - Define multiple options */
            .gradient-background-blue-cyan {
                background: linear-gradient(to right, #4facfe, #00f2fe); /* Blue to Cyan gradient */
            }
             .gradient-background-purple-pink {
                 background: linear-gradient(to right, #a18cd1, #fbc2eb); /* Purple to Pink */
             }
              .gradient-background-green-teal {
                 background: linear-gradient(to right, #a8edea, #fed6e3); /* Green to Teal */
              }
              .solid-bg-gray {
                  background-color: #f3f4f6; /* Light Gray */
              }
              .solid-bg-indigo {
                  background-color: #4f46e5; /* Indigo */
              }
              /* Default gradient if none saved or set */
              body {
                  background: linear-gradient(to right, #4facfe, #00f2fe); /* Default background */
              }

         /* Style for general messages */
         .message-box {
             padding: 1rem;
             border-radius: 0.5rem;
             border: 1px solid transparent;
             margin-bottom: 1.5rem; /* mb-6 */
             text-align: center;
         }
         .message-box.success {
             color: #065f46; /* green-700 */
             background-color: #d1fae5; /* green-100 */
             border-color: #a7f3d0; /* green-300 */
         }
          .message-box.error {
             color: #b91c1c; /* red-700 */
             background-color: #fee2e2; /* red-100 */
             border-color: #fca5a5; /* red-300 */
         }
          .message-box.warning {
             color: #b45309; /* yellow-700 */
             background-color: #fffce0; /* yellow-100 */
             border-color: #fde68a; /* yellow-300 */
         }
          .message-box.info { /* Added info style */
             color: #0c5460; /* cyan-700 */
             background-color: #d1ecf1; /* cyan-100 */
             border-color: #bee5eb; /* cyan-300 */
         }


         /* Fixed Header and body padding to prevent content overlap */
         .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20; /* Below sidebar (z-index 30), above content */
            background-color: #ffffff; /* White background */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0.75rem 1.5rem; /* Adjust padding */
            display: flex; /* Use flexbox */
            align-items: center; /* Vertically align items */
            justify-content: space-between; /* Space out items */
         }
         body {
             /* Add padding-top to clear the fixed header. Adjust value if needed. */
             padding-top: 4.5rem; /* Increased padding slightly */
             transition: padding-left 0.3s ease; /* Smooth transition for body padding when sidebar opens/closes */
         }
         /* Adjust body padding when sidebar is open - assumes sidebar width is ~16rem (64 Tailwind units) */
         body.sidebar-open {
             padding-left: 16rem; /* Match sidebar width */
         }


         /* Table styles */
         .principle-table th, .principle-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; font-size: 0.875rem; } /* Renamed table class */
          .principle-table th { background-color: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; color: #374151; letter-spacing: 0.05em; } /* Renamed table class */
           .principle-table tbody tr:nth-child(even) { background-color: #f9fafb; /* Subtle alternating row color */ } /* Renamed table class */
           .principle-table tbody tr:hover { background-color: #e5e7eb; /* Darker hover color */ } /* Renamed table class */
            .principle-table .actions { white-space: nowrap; min-width: 120px; } /* Prevent buttons/links from wrapping, give min-width */ /* Renamed table class */
            .principle-table td { color: #4b5563; /* Default text color */ } /* Renamed table class */
            .principle-table td a { font-weight: 500; } /* Slightly bolder links */ /* Renamed table class */

         /* Custom styles for action links */
          .action-link-edit { color: #3b82f6; /* blue-600 */ }
          .action-link-edit:hover { color: #2563eb; /* blue-700 */ text-decoration: underline; }
          .action-link-delete { color: #ef4444; /* red-600 */ margin-left: 0.75rem; /* ml-3 */ }
          .action-link-delete:hover { color: #dc2626; /* red-700 */ text-decoration: underline; }

        /* Style for the search input container */
        .search-container {
            margin-bottom: 1.5rem; /* mb-6 */
            padding: 1rem;
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex; /* Use flexbox */
            flex-direction: column; /* Stack label and input vertically */
            gap: 0.5rem; /* Add space between label and input */
        }
         .search-container label {
             /* margin-bottom: 0.5rem; Removed margin-bottom due to gap */
             display: block;
             font-size: 0.875rem; /* text-sm */
             font-weight: 500; /* font-medium */
             color: #374151; /* gray-700 */
         }
          .search-container input[type="text"] {
              width: 100%;
              padding: 0.5rem 0.75rem;
              border: 1px solid #d1d5db; /* gray-300 */
              border-radius: 0.375rem; /* rounded-md */
              box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
              font-size: 0.875rem; /* text-sm */
              color: #374151; /* gray-700 */
              transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
          }
           .search-container input[type="text"]:focus {
                outline: none;
                border-color: #4f46e5; /* indigo-500 */
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); /* indigo-500 with opacity */
           }

           /* Style for the Download button */
           .download-button {
               display: inline-flex; /* Use flexbox to align icon and text */
               align-items: center;
               background-color: #22c55e; /* green-500 */
               color: white;
               padding: 0.5rem 1rem;
               border-radius: 0.375rem;
               font-weight: 500;
               font-size: 0.875rem;
               transition: background-color 0.15s ease-in-out;
               margin-bottom: 1.5rem; /* mb-6 */
               text-decoration: none; /* Prevent underline */
           }
           .download-button:hover {
               background-color: #16a34a; /* green-600 */
               text-decoration: none;
           }
           .download-button svg {
                margin-right: 0.5rem; /* mr-2 */
                height: 1.25rem; /* h-5 */
                width: 1.25rem; /* w-5 */
           }


           /* Staff Photo in Table */
           .circular-photo-sm {
                width: 40px; /* Increased size slightly */
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
                /* margin-right: 0.5rem; Removed, apply margin if needed in a flex container */
                /* vertical-align: middle; Removed, use flexbox for vertical alignment */
                border: 1px solid #cbd5e1;
                flex-shrink: 0; /* Prevent shrinking */
           }
            td .flex-center-y {
                 display: flex;
                 align-items: center; /* Center photo vertically with text */
                 /* gap: 0.5rem; Optional: Add space between photo and text */
            }


    </style>
     <script>
        // JavaScript for dynamic background changes (Optional, adapted from previous)
        function setBackground(className) {
            const body = document.body;
            // Remove all existing background classes that start with gradient-background- or solid-bg-
            body.classList.forEach(cls => {
                if (cls.startsWith('gradient-background-') || cls.startsWith('solid-bg-')) {
                    body.classList.remove(cls);
                }
            });
            // Add the selected class
            body.classList.add(className);

            // Optional: Save preference to local storage
            localStorage.setItem('backgroundPreference', className);
        }

        // Optional: Apply background preference on page load
        document.addEventListener('DOMContentLoaded', (event) => {
            const savedBackground = localStorage.getItem('backgroundPreference');
            if (savedBackground) {
                 // Check if the saved class looks like a valid background class name
                if (savedBackground.startsWith('gradient-background-') || savedBackground.startsWith('solid-bg-')) {
                     setBackground(savedBackground);
                 } else {
                     // Clear invalid saved preference
                     localStorage.removeItem('backgroundPreference');
                     // Optionally set a default here if needed
                 }
            } else {
                 // Default background is set by CSS body rule, no action needed here
            }

            // --- Client-Side Search JavaScript ---
            const searchInput = document.getElementById('principleSearchInput'); // Updated ID
            const tableBody = document.querySelector('.principle-table tbody'); // Updated selector

            if (searchInput && tableBody) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim(); // Get search term and convert to lowercase, trim whitespace
                    const rows = tableBody.querySelectorAll('tr'); // Get all table rows

                    rows.forEach(row => {
                        let rowText = '';
                        // Concatenate text content of all cells in the row (excluding Actions column)
                         const cellsToSearch = row.querySelectorAll('td:not(.actions)'); // Exclude the actions cell
                         cellsToSearch.forEach(cell => {
                             // Get text content and add a space to separate values
                             rowText += cell.textContent ? cell.textContent.toLowerCase() + ' ' : '';
                         });


                        // Check if the concatenated row text contains the search term
                        if (rowText.includes(searchTerm)) {
                            row.style.display = ''; // Show the row
                        } else {
                            row.style.display = 'none'; // Hide the row
                        }
                    });
                });
            }
            // --- End Client-Side Search JS ---


        });

        // --- Sidebar Toggle JS (Assumes admin_sidebar.js is NOT included in this specific file) ---
        // If admin_sidebar.js IS included, this can be removed or the toggle button logic moved there.
        // Assuming admin_sidebar.php includes the button with id="admin-sidebar-toggle-open"
        // and that JS file handles the 'sidebar-open' class on the body.
        document.getElementById('admin-sidebar-toggle-open')?.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-open');
        });
         // --- End Sidebar Toggle JS ---

    </script>
</head>
<!-- Apply an enhanced background class - Default background set in CSS body rule -->
<body class="min-h-screen">
    <?php
    // Assuming admin_sidebar.php is in the SAME directory as this file (admin/)
    // Path from 'admin/' to 'admin/' is './'
    $sidebar_path = "./admin_sidebar.php";
     if (file_exists($sidebar_path)) {
         require_once $sidebar_path;
     } else {
         // Display a warning if the sidebar file is missing
         echo '<div class="message-box error" role="alert">Admin sidebar file not found! Check path: `' . htmlspecialchars($sidebar_path) . '`</div>';
         // Decide if you want to halt execution here (exit;) or just display a warning.
         // exit; // Uncomment if missing sidebar is a fatal error
     }
    ?>

    <!-- Fixed Header for Toggle Button and Page Title -->
    <div class="fixed-header">
         <!-- Open Sidebar Button (Hamburger) -->
         <!-- This button is likely handled by admin_sidebar.js -->
         <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:mr-6" aria-label="Toggle sidebar">
             <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
             </svg>
         </button>

         <!-- Back to Dashboard Link -->
         <!-- Path from 'admin/' up to 'School/' is '../' -->
         <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline transition duration-150 ease-in-out text-sm font-medium flex items-center mr-4 md:mr-6">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
             </svg>
             Back
         </a>

         <!-- Page Title -->
         <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">Manage Principles</h1> <!-- Updated Title -->

         <!-- Optional: Add logged-in user info or other header elements -->
          <!-- User info from session, assume 'name' holds the display name after successful login -->
          <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
           <!-- Logout link in header for quick access -->
           <!-- Path from 'admin/' up to 'School/' is '../' -->
            <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
    </div>


    <!-- Main content wrapper -->
     <!-- Adjusted margin-top and width, added horizontal padding -->
     <!-- The padding-top in the body CSS handles clearance for the fixed header -->
     <div class="w-full max-w-screen-xl mx-auto px-4 py-8">

         <!-- Operation Message Display -->
         <?php
         // Display operation messages (e.g., from successful create/edit/delete or access denied from other pages)
         if (!empty($operation_message)) {
             $message_type = 'info'; // Default
              // Check message content for type - make checks case-insensitive and include common phrases
              $msg_lower = strtolower(strip_tags($operation_message)); // Convert to lower and remove HTML tags for checks
              if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                   $message_type = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_type = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false) {
                   $message_type = 'warning';
              }
             // Use the defined CSS classes - $operation_message contains the HTML <p> tag
             echo "<div class='message-box " . $message_type . "' role='alert'>" . $operation_message . "</div>"; // Output the raw message HTML (it contains <p> tags)
         }
         ?>

         <!-- Search Input and Download Button Row -->
         <?php if (!empty($principles) || !empty($fetch_principles_message)): // Show search/download if data exists OR if there's a message about no data/error ?>
         <!-- Combined Search and Download controls in a flex container -->
         <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
             <!-- Search Input Container -->
             <div class="search-container flex-grow w-full md:w-auto p-4 bg-white rounded-lg shadow-md"> <!-- Use flex-grow and w-full -->
                 <label for="principleSearchInput">Search Principle Records:</label> <!-- Updated label and ID -->
                 <input type="text" id="principleSearchInput" placeholder="Search by name, ID, email, etc."> <!-- Updated placeholder -->
             </div>

             <!-- Download Button -->
             <?php if (!empty($principles)): // Only show download if there is data ?>
                 <!-- Link to THIS page with ?download=csv -->
                 <!-- Path from 'admin/' to 'admin/' is './' -->
                 <a href="./manage_principles.php?download=csv" class="download-button flex-shrink-0"> <!-- Updated href -->
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                       <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                     </svg>
                     Download CSV
                 </a>
             <?php endif; ?>
         </div>
         <?php endif; ?>


        <!-- Principles List Display Card --> <!-- Updated heading -->
        <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full"> <!-- Increased padding on larger screens -->
             <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">Principle Records</h2> <!-- Updated heading -->

             <!-- Message when no records are found or there's an error fetching -->
             <?php
             // Only show the message if the principles list is empty or there was a fetch error message set
             if (empty($principles)) { // Updated variable
                 $alert_class = 'alert-info'; // Default style if message is just about no records
                 // Updated variable for message check
                 if (strpos(strip_tags($fetch_principles_message), 'Error') !== false || strpos(strip_tags($fetch_principles_message), 'Could not') !== false || strpos(strip_tags($fetch_principles_message), 'Database connection') !== false) {
                      $alert_class = 'alert-danger'; // Use error style for fetch errors
                 } elseif (strpos(strip_tags($fetch_principles_message), 'No principle records found') !== false || strpos(strip_tags($fetch_principles_message), 'yet') !== false) { // Updated message text check
                     $alert_class = 'alert-warning'; // Use warning for empty state
                 }
                 // Display the message. htmlspecialchars is applied because $fetch_principles_message might contain HTML from PHP errors.
                 echo "<div class='mb-4 text-center alert " . $alert_class . "' role='alert'>" . htmlspecialchars($fetch_principles_message) . "</div>"; // Updated variable
             }
             // Note: If principles is NOT empty, we don't show $fetch_principles_message here, as the table is the primary display.
             ?>

             <?php if (!empty($principles)): ?> <!-- Updated variable -->
                 <!-- Added overflow-x-auto for horizontal scrolling on small screens -->
                 <div class="overflow-x-auto">
                     <table class="min-w-full divide-y divide-gray-200 principle-table"> <!-- Updated table class -->
                         <thead>
                             <tr>
                                 <th scope="col" class="w-auto">Photo</th>
                                 <th scope="col" class="w-auto">ID</th>
                                 <th scope="col" class="w-auto">Unique ID</th>
                                 <th scope="col" class="w-auto">Name</th>
                                 <th scope="col" class="w-auto">Mobile</th>
                                 <th scope="col" class="w-auto">Email</th>
                                 <th scope="col" class="w-auto">Role</th>
                                 <th scope="col" class="w-auto">Salary</th>
                                 <!-- Kept Subject(s) and Class(es) for consistency, they might be empty -->
                                 <th scope="col" class="w-auto">Subject(s)</th>
                                 <th scope="col" class="w-auto">Class(es)</th>
                                 <th scope="col" class="w-auto">Created At</th>
                                 <th scope="col" class="w-auto text-center">Actions</th> <!-- Center actions column header -->
                             </tr>
                         </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                             <?php foreach ($principles as $principle): ?> <!-- Loop through principles -->
                                 <tr>
                                     <td>
                                         <?php
                                             // Get the Cloudinary URL from the database row
                                             $cloudinary_url = $principle['photo_filename'] ?? ''; // Use ?? ''
                                             // Path to a local default staff avatar relative to THIS file (admin/manage_principles.php)
                                             // Assuming default_staff_avatar.png is in School/assets/images/
                                             $default_avatar_path = '../../assets/images/default_staff_avatar.png'; // Path from School/admin/ to School/assets/images/

                                             // Determine the final image URL to display
                                             $display_photo_url = !empty($cloudinary_url) ? $cloudinary_url : $default_avatar_path;
                                         ?>
                                         <img src="<?php echo htmlspecialchars($display_photo_url); ?>" alt="<?php echo htmlspecialchars($principle['staff_name'] . ' Photo'); ?>" class="circular-photo-sm">
                                     </td>
                                    <td><?php echo htmlspecialchars($principle['staff_id']); ?></td>
                                    <td><?php echo htmlspecialchars($principle['unique_id']); ?></td>
                                    <td><?php echo htmlspecialchars($principle['staff_name']); ?></td>
                                    <td><?php echo htmlspecialchars($principle['mobile_number']); ?></td>
                                    <td><?php echo htmlspecialchars($principle['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($principle['role'])); ?></td>
                                    <td><?php echo htmlspecialchars($principle['salary'] ?? ''); ?></td> <!-- Use ?? '' for display -->
                                    <td><?php echo htmlspecialchars($principle['subject_taught'] ?? ''); ?></td> <!-- Use ?? '' for display -->
                                    <td><?php echo htmlspecialchars($principle['classes_taught'] ?? ''); ?></td> <!-- Use ?? '' for display -->
                                    <td><?php echo htmlspecialchars($principle['created_at']); ?></td>
                                    <td class="actions text-center"> <!-- Center actions cell content -->
                                         <!-- Edit Link - Links to the generic edit staff page -->
                                         <!-- Path from 'admin/' to 'admin/' is './' -->
                                        <a href="./edit_staff.php?id=<?php echo htmlspecialchars($principle['staff_id']); ?>" class="action-link-edit">Edit</a>
                                          <!-- Assign Timetable Link - Kept for consistency, might not be applicable -->
                                          <!-- Path from 'admin/' to 'admin/' is './' -->
                                          <!-- NOTE: Check if assign_staff_timetable.php correctly uses the staff ID -->
                                        <a href="./assign_staff_timetable.php?id=<?php echo htmlspecialchars($principle['staff_id']); ?>" class="action-link-edit mr-2">Timetable</a>

                                        <!-- Delete Link - Links to the generic delete staff page -->
                                         <!-- Path from 'admin/' to 'admin/' is './' -->
                                        <a href="./delete_staff.php?id=<?php echo htmlspecialchars($principle['staff_id']); ?>" class="action-link-delete" onclick="return confirm('Are you sure you want to delete this principle record? This cannot be undone!');">Delete</a> <!-- Updated confirmation text -->
                                    </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div> <!-- End overflow-x-auto -->
             <?php endif; ?>
         </div> <!-- End bg-white panel -->

     </div> <!-- End main content wrapper -->

     <!-- Navigation Links / Background Picker (Footer) -->
     <footer class="mt-8 text-center text-sm text-gray-700">
         <nav class="flex flex-wrap justify-center gap-4">
             <!-- Path from 'admin/' up to 'School/' is '../' -->
             <a href="../admin_dashboard.php" class="text-indigo-700 hover:text-indigo-900 hover:underline transition duration-150 ease-in-out">
                 ← Back to Dashboard
             </a>
             <span class="text-gray-400">|</span>
              <!-- Link to create staff page. Can potentially add ?role=principle -->
              <!-- Path from 'admin/' to 'admin/' is './' -->
             <a href="./create_staff.php" class="text-indigo-700 hover:text-indigo-900 hover:underline transition duration-150 ease-in-out">
                 Create New Staff (Incl. Principle) <!-- Updated text -->
             </a>
              <span class="text-gray-400">|</span>
               <!-- Path from 'admin/' up to 'School/' is '../' -->
              <a href="../logout.php" class="text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out">
                 Logout
             </a>
         </nav>
          <!-- Optional: Add buttons for background change here -->
          <div class="mt-6 text-center text-gray-700 text-xs">
              Choose Background:
              <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
              <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
               <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
               <button class="ml-2 px-2 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" onclick="setBackground('solid-bg-gray')">Gray</button>
               <button class="ml-2 px-2 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs" onclick="setBackground('solid-bg-indigo')">Indigo</button>
          </div>
     </footer>


</body>
</html>