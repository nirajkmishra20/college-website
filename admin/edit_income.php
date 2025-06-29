<?php
// School/admin/edit_income.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can edit income.</p>";
    header("location: ../login.php");
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Edit Income";

// --- Variables for Form and Messages ---
$income_id = $income_date = $description = $category = $amount = $payment_method = "";
$income_date_err = $description_err = $category_err = $amount_err = $payment_method_err = "";
$operation_message = "";

// Get ID from URL
$income_id = $_GET['id'] ?? null;

// Validate ID
if ($income_id === null || !is_numeric($income_id)) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid income ID.</p>";
     header("location: manage_income.php");
     exit;
}
$income_id = (int)$income_id; // Cast to integer for safety

// Database connection check
if ($link === false) {
     $operation_message = "<p class='text-red-600'>Database connection error. Could not load income details.</p>";
     error_log("Edit Income DB connection failed: " . mysqli_connect_error());
} else {

    // --- Fetch existing income data ---
    if ($_SERVER["REQUEST_METHOD"] != "POST") { // Only fetch if it's a GET request (not post submission)
         $sql_fetch = "SELECT income_id, income_date, description, category, amount, payment_method FROM income WHERE income_id = ?";
         if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
             mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
             $param_id = $income_id;

             if (mysqli_stmt_execute($stmt_fetch)) {
                 $result_fetch = mysqli_stmt_get_result($stmt_fetch);
                 if (mysqli_num_rows($result_fetch) == 1) {
                     $row = mysqli_fetch_assoc($result_fetch);
                     // Populate form variables
                     $income_date = $row['income_date'];
                     $description = $row['description'];
                     $category = $row['category'];
                     $amount = $row['amount']; // Use raw value for editing
                     $payment_method = $row['payment_method'];
                 } else {
                     $_SESSION['operation_message'] = "<p class='text-red-600'>Income record not found.</p>";
                     header("location: manage_income.php");
                     exit;
                 }
                 mysqli_free_result($result_fetch);
             } else {
                 $operation_message = "<p class='text-red-600'>Error fetching income data: " . htmlspecialchars(mysqli_stmt_error($stmt_fetch)) . "</p>";
                 error_log("Error executing fetch income query: " . mysqli_stmt_error($stmt_fetch));
             }
             mysqli_stmt_close($stmt_fetch);
         } else {
             $operation_message = "<p class='text-red-600'>Database statement preparation failed for fetching: " . htmlspecialchars(mysqli_error($link)) . "</p>";
              error_log("Error preparing fetch income statement: " . mysqli_error($link));
         }
    }
}


// --- Processing form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get hidden income_id from POST
     $income_id = $_POST['income_id'] ?? null;
      if ($income_id === null || !is_numeric($income_id)) {
          $operation_message = "<p class='text-red-600'>Invalid request. Income ID missing on POST.</p>";
      } else {
          $income_id = (int)$income_id; // Ensure it's integer
      }


    // Validate income_date
    if (empty(trim($_POST["income_date"]))) {
        $income_date_err = "Please enter the income date.";
    } else {
        $income_date = trim($_POST["income_date"]);
         if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $income_date)) {
             $income_date_err = "Invalid date format. Use YYYY-MM-DD.";
         }
    }

    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description.";
    } else {
        $description = trim($_POST["description"]);
    }

    // Category is optional, but sanitize
    $category = trim($_POST["category"]);

    // Validate amount
    if (empty(trim($_POST["amount"]))) {
        $amount_err = "Please enter the amount.";
    } elseif (!is_numeric($_POST["amount"]) || $_POST["amount"] <= 0) {
        $amount_err = "Please enter a valid positive number for the amount.";
    } else {
        $amount = trim($_POST["amount"]);
         $amount = number_format($amount, 2, '.', ''); // Format to 2 decimal places
    }

    // Validate payment_method
    if (empty(trim($_POST["payment_method"]))) {
        $payment_method_err = "Please select a payment method.";
    } else {
        $payment_method = trim($_POST["payment_method"]);
    }

    // Check input errors before updating database
    if (empty($income_date_err) && empty($description_err) && empty($amount_err) && empty($payment_method_err) && !empty($income_id)) {

         // Check if database connection is available
        if ($link === false) {
             $operation_message = "<p class='text-red-600'>Database connection error. Could not update income.</p>";
             error_log("Edit Income DB connection failed during update: " . mysqli_connect_error());
        } else {
             // Prepare an update statement
             $sql_update = "UPDATE income SET income_date = ?, description = ?, category = ?, amount = ?, payment_method = ? WHERE income_id = ?";

             if ($stmt_update = mysqli_prepare($link, $sql_update)) {
                 // Bind variables to the prepared statement as parameters
                 mysqli_stmt_bind_param($stmt_update, "sssdsi",
                     $param_date,
                     $param_description,
                     $param_category,
                     $param_amount,
                     $param_payment_method,
                     $param_id_update
                 );

                 // Set parameters
                 $param_date = $income_date;
                 $param_description = $description;
                 $param_category = empty($category) ? null : $category; // Store NULL if category is empty
                 $param_amount = $amount;
                 $param_payment_method = $payment_method;
                 $param_id_update = $income_id; // Use the ID from the hidden field

                 // Attempt to execute the prepared statement
                 if (mysqli_stmt_execute($stmt_update)) {
                     // Record updated successfully. Redirect to manage income page.
                     $_SESSION['operation_message'] = "<p class='text-green-600'>Income updated successfully.</p>";
                     header("location: manage_income.php");
                     exit;
                 } else {
                     $operation_message = "<p class='text-red-600'>Error updating income: " . htmlspecialchars(mysqli_stmt_error($stmt_update)) . "</p>";
                      error_log("Error executing update income query: " . mysqli_stmt_error($stmt_update));
                 }

                 // Close statement
                 mysqli_stmt_close($stmt_update);
            } else {
                 $operation_message = "<p class='text-red-600'>Database statement preparation failed for updating: " . htmlspecialchars(mysqli_error($link)) . "</p>";
                  error_log("Error preparing update income statement: " . mysqli_error($link));
            }

            // Close connection (Moved closing here so it's only closed after the transaction or failure)
             if (isset($link) && is_object($link) && method_exists($link, 'ping') && mysqli_ping($link)) {
                 mysqli_close($link);
            }
        }
    } else {
        // If there were input errors on POST, the form variables will retain the posted values
        // and the errors will be displayed. The $income_id will also be available from $_POST.
    }
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
              display: block; width: 100%; padding: 0.625rem 1rem; font-size: 1rem; line-height: 1.5;
              color: #4b5563; background-color: #fff; background-image: none; background-clip: padding-box;
              border: 1px solid #d1d5db; border-radius: 0.375rem;
              transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
              appearance: none;
          }
           .form-input:focus, .form-select:focus, .form-textarea:focus {
                border-color: #6366f1; outline: 0; box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
           }
           .form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid {
               border-color: #ef4444; padding-right: 2.5rem;
               background-image: url("data:image/svg+xml,...error icon..."); /* Placeholder for icon */
                background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.5em 1.5em;
           }
           .invalid-feedback { display: block; width: 100%; margin-top: 0.25rem; font-size: 0.875em; color: #dc2626; }

          /* Style for the background changer buttons/container */
          .background-changer { margin-top: 2rem; text-align: center; font-size: 0.875rem; color: #4b5563; padding-bottom: 2rem; }
           .background-changer button { margin-left: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; transition: opacity 0.2s ease; }
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

          <!-- Back to Manage Income Link -->
         <div class="mb-6 text-center">
             <a href="./manage_income.php" class="text-indigo-600 hover:underline text-sm">&larr; Back to Manage Income</a>
         </div>


         <!-- Income Edit Form -->
         <div class="bg-white p-6 rounded-xl shadow-md max-w-md mx-auto">

              <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                 <!-- Hidden input for income ID -->
                 <input type="hidden" name="income_id" value="<?php echo htmlspecialchars($income_id); ?>">

                 <!-- Income Date -->
                 <div class="form-group">
                     <label for="income_date" class="form-label">Income Date</label>
                     <input type="date" id="income_date" name="income_date"
                            class="form-input <?php echo (!empty($income_date_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($income_date); ?>" required>
                     <?php if (!empty($income_date_err)): ?><span class="invalid-feedback"><?php echo $income_date_err; ?></span><?php endif; ?>
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
                            value="<?php echo htmlspecialchars($category); ?>" placeholder="e.g., Student Fees, Donation">
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
                         <option value="Online Payment" <?php echo ($payment_method === 'Online Payment') ? 'selected' : ''; ?>>Online Payment</option>
                         <option value="Check" <?php echo ($payment_method === 'Check') ? 'selected' : ''; ?>>Check</option>
                         <option value="Other" <?php echo ($payment_method === 'Other') ? 'selected' : ''; ?>>Other</option>
                     </select>
                     <?php if (!empty($payment_method_err)): ?><span class="invalid-feedback"><?php echo $payment_method_err; ?></span><?php endif; ?>
                 </div>

                 <!-- Submit Button -->
                 <div class="form-group text-center mt-6">
                     <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-200">
                         Update Income
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