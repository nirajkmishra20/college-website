<?php
// School/admin/add_expense.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can add expenses.</p>";
    header("location: ../login.php");
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Add New Expense";

// Get user role for conditional display
$loggedInUserRole = $_SESSION['role'] ?? 'guest';
$recordedByUserId = $_SESSION['user_id'] ?? null; // Assuming user_id is stored in session

// --- Variables for Form and Messages ---
$expense_date = $description = $category = $amount = $payment_method = "";
$expense_date_err = $description_err = $category_err = $amount_err = $payment_method_err = "";
$operation_message = ""; // For success/failure messages

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate expense_date
    if (empty(trim($_POST["expense_date"]))) {
        $expense_date_err = "Please enter the expense date.";
    } else {
        $expense_date = trim($_POST["expense_date"]);
        // Basic date format validation (YYYY-MM-DD)
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $expense_date)) {
             $expense_date_err = "Invalid date format. Use YYYY-MM-DD.";
        }
    }

    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description.";
    } else {
        $description = trim($_POST["description"]);
    }

    // Category is optional, but sanitize if provided
    $category = trim($_POST["category"]); // No validation needed if optional

    // Validate amount
    if (empty(trim($_POST["amount"]))) {
        $amount_err = "Please enter the amount.";
    } elseif (!is_numeric($_POST["amount"]) || $_POST["amount"] <= 0) {
        $amount_err = "Please enter a valid positive number for the amount.";
    } else {
        $amount = trim($_POST["amount"]);
         // Ensure amount is a valid decimal with 2 places
        $amount = number_format($amount, 2, '.', ''); // Format to 2 decimal places
    }

    // Validate payment_method
    if (empty(trim($_POST["payment_method"]))) {
        $payment_method_err = "Please select a payment method.";
    } else {
        $payment_method = trim($_POST["payment_method"]);
    }

    // Check input errors before inserting into database
    if (empty($expense_date_err) && empty($description_err) && empty($amount_err) && empty($payment_method_err)) {

         // Check if database connection is available
        if ($link === false) {
             $operation_message = "<p class='text-red-600'>Database connection error. Could not add expense.</p>";
             error_log("Add Expense DB connection failed: " . mysqli_connect_error());
        } else {

             // Prepare an insert statement
             $sql = "INSERT INTO expenses (expense_date, description, category, amount, payment_method, recorded_by_user_id) VALUES (?, ?, ?, ?, ?, ?)";

             if ($stmt = mysqli_prepare($link, $sql)) {
                 // Bind variables to the prepared statement as parameters
                 mysqli_stmt_bind_param($stmt, "sssdis",
                     $param_date,
                     $param_description,
                     $param_category,
                     $param_amount,
                     $param_payment_method,
                     $param_recorded_by
                 );

                 // Set parameters
                 $param_date = $expense_date;
                 $param_description = $description;
                 $param_category = empty($category) ? null : $category; // Store NULL if category is empty
                 $param_amount = $amount;
                 $param_payment_method = $payment_method;
                 $param_recorded_by = $recordedByUserId; // Link to the user who recorded it

                 // Attempt to execute the prepared statement
                 if (mysqli_stmt_execute($stmt)) {
                     // Record added successfully. Redirect to manage expenses page.
                     $_SESSION['operation_message'] = "<p class='text-green-600'>Expense added successfully.</p>";
                     header("location: manage_expenses.php");
                     exit;
                 } else {
                     $operation_message = "<p class='text-red-600'>Error adding expense: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
                      error_log("Error executing add expense query: " . mysqli_stmt_error($stmt));
                 }

                 // Close statement
                 mysqli_stmt_close($stmt);
            } else {
                 $operation_message = "<p class='text-red-600'>Database statement preparation failed: " . htmlspecialchars(mysqli_error($link)) . "</p>";
                  error_log("Error preparing add expense statement: " . mysqli_error($link));
            }

            // Close connection (Moved closing here so it's only closed after the transaction or failure)
            // This check prevents errors if $link was never successfully created
            if (isset($link) && is_object($link) && method_exists($link, 'ping') && mysqli_ping($link)) {
                 mysqli_close($link);
            }
        }
    }
     // Re-open connection if it was closed due to error and you need it later in the page (e.g., for footer)
     // This is generally handled by your footer, but be mindful if the page connects/closes multiple times.
}

// Include the header file.
require_once "./admin_header.php";
?>

    <!-- Custom styles specific to this page -->
     <style>
         body { padding-top: 4.5rem; transition: padding-left 0.3s ease; }
         body.sidebar-open { padding-left: 16rem; }
          .form-group { margin-bottom: 1.5rem; }
          .form-label { display: block; color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
          .form-input, .form-select, .form-textarea {
              display: block;
              width: 100%;
              padding: 0.625rem 1rem; /* Adjust padding */
              font-size: 1rem;
              line-height: 1.5;
              color: #4b5563;
              background-color: #fff;
              background-image: none;
              background-clip: padding-box;
              border: 1px solid #d1d5db; /* gray-300 */
              border-radius: 0.375rem; /* rounded-md */
              transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
              appearance: none; /* Remove default system styles */
          }
           .form-input:focus, .form-select:focus, .form-textarea:focus {
                border-color: #6366f1; /* indigo-500 */
                outline: 0;
                box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); /* indigo-200 with opacity */
           }
           .form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid {
               border-color: #ef4444; /* red-500 */
               padding-right: 2.5rem; /* Make space for icon */
               background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ef4444'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 0.75rem center;
                background-size: 1.5em 1.5em;
           }
           .invalid-feedback { display: block; width: 100%; margin-top: 0.25rem; font-size: 0.875em; color: #dc2626; /* red-600 */ }

          /* Style for the background changer buttons/container if needed */
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
           /* Specific button styles (example, adapt to your gradient classes) */
           .background-changer button.gradient-background-blue-cyan { background: linear-gradient(to right, #60a5fa, #22d3ee); color: white; border: 1px solid #3b82f6; } /* blue-400 to cyan-400 */
           .background-changer button.gradient-background-purple-pink { background: linear-gradient(to right, #a78bfa, #f472b6); color: white; border: 1px solid #8b5cf6; } /* purple-400 to pink-400 */
           .background-changer button.gradient-background-green-teal { background: linear-gradient(to right, #34d399, #2dd4bf); color: white; border: 1px solid #10b981; } /* green-400 to teal-400 */
            .background-changer button.solid-bg-gray { background-color: #e5e7eb; color: #1f2937; border: 1px solid #d1d5db; } /* gray-200 */
             .background-changer button.solid-bg-indigo { background-color: #6366f1; color: white; border: 1px solid #4f46e5; } /* indigo-500 */
           .background-changer button:hover { opacity: 0.9; }


     </style>
     <!-- Include any necessary global JS or functions here -->
     <script>
        // Ensure the setBackground function is available globally or define it here
         function setBackground(className) {
             const body = document.body;
             // List all possible background classes to remove
             const backgroundClasses = [
                 'gradient-background-blue-cyan',
                 'gradient-background-purple-pink',
                 'gradient-background-green-teal',
                 'solid-bg-gray',
                 'solid-bg-indigo'
                 // Add any other custom background classes here
             ];
             body.classList.remove(...backgroundClasses); // Use spread syntax
             body.classList.add(className);
             localStorage.setItem('dashboardBackground', className);
         }

          document.addEventListener('DOMContentLoaded', function() {
              // Apply saved background preference on load
              const savedBackgroundClass = localStorage.getItem('dashboardBackground');
              if (savedBackgroundClass) {
                  // Add a check to ensure the saved class is valid if needed
                  setBackground(savedBackgroundClass);
              }
          });
     </script>


    <!-- Main content wrapper -->
     <div class="w-full max-w-screen-lg mx-auto px-4 sm:px-6 lg:px-8 py-8">

         <!-- Operation Message Display -->
         <?php
         if (!empty($operation_message)) {
             $message_type_class = 'error'; // Errors are the primary message type here
             $message_classes = "p-3 rounded-md border mb-6 text-center text-sm bg-red-100 border-red-300 text-red-800";
             echo "<div class='{$message_classes}' role='alert'>" . $operation_message . "</div>";
         }
         ?>

         <!-- Page Title -->
         <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 text-center"><?php echo htmlspecialchars($pageTitle); ?></h1>

         <!-- Back to Dashboard Link -->
         <div class="mb-6 text-center">
             <a href="./admin_dashboard.php" class="text-indigo-600 hover:underline text-sm">← Back to Dashboard</a>
         </div>


         <!-- Expense Add Form -->
         <div class="bg-white p-6 rounded-xl shadow-md max-w-md mx-auto">

              <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>

                 <!-- Expense Date -->
                 <div class="form-group">
                     <label for="expense_date" class="form-label">Expense Date</label>
                     <input type="date" id="expense_date" name="expense_date"
                            class="form-input <?php echo (!empty($expense_date_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($expense_date); ?>" required>
                     <?php if (!empty($expense_date_err)): ?><span class="invalid-feedback"><?php echo $expense_date_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Description -->
                 <div class="form-group">
                     <label for="description" class="form-label">Description</label>
                     <textarea id="description" name="description" rows="3"
                               class="form-textarea <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"
                               required><?php echo htmlspecialchars($description); ?></textarea>
                     <?php if (!empty($description_err)): ?><span class="invalid-feedback"><?php echo $description_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Category -->
                 <div class="form-group">
                     <label for="category" class="form-label">Category (Optional)</label>
                     <input type="text" id="category" name="category"
                            class="form-input <?php echo (!empty($category_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($category); ?>" placeholder="e.g., Stationery, Utilities">
                     <?php if (!empty($category_err)): ?><span class="invalid-feedback"><?php echo $category_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Amount -->
                 <div class="form-group">
                     <label for="amount" class="form-label">Amount (₹)</label>
                     <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                            class="form-input <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($amount); ?>" required>
                     <?php if (!empty($amount_err)): ?><span class="invalid-feedback"><?php echo $amount_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Payment Method -->
                 <div class="form-group">
                     <label for="payment_method" class="form-label">Payment Method</label>
                     <select id="payment_method" name="payment_method"
                             class="form-select <?php echo (!empty($payment_method_err)) ? 'is-invalid' : ''; ?>"
                             required>
                         <option value="">-- Select Method --</option>
                         <option value="Cash" <?php echo ($payment_method === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                         <option value="Bank Transfer" <?php echo ($payment_method === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                         <option value="Check" <?php echo ($payment_method === 'Check') ? 'selected' : ''; ?>>Check</option>
                          <option value="Online Payment" <?php echo ($payment_method === 'Online Payment') ? 'selected' : ''; ?>>Online Payment</option>
                         <option value="Other" <?php echo ($payment_method === 'Other') ? 'selected' : ''; ?>>Other</option>
                     </select>
                     <?php if (!empty($payment_method_err)): ?><span class="invalid-feedback"><?php echo $payment_method_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Submit Button -->
                 <div class="form-group text-center mt-6">
                     <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-200">
                         Add Expense
                     </button>
                 </div>

             </form>
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