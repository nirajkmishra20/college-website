<?php
// School/admin/download_filtered_receipts.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can download fee receipts.</p>";
    header("location: ../login.php");
    exit;
}

// Ensure ZipArchive is available
if (!class_exists('ZipArchive')) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Server configuration error: ZipArchive extension is not enabled.</p>";
     header("location: view_receipts.php");
     exit;
}

// --- PDF Library Setup ---
// YOU WILL NEED TO INCLUDE YOUR PDF LIBRARY SETUP HERE (e.g., TCPDF, mPDF)
// Example for TCPDF:
// require_once('../path/to/tcpdf/tcpdf.php');
// require_once('ReceiptGenerator.php'); // Assuming you put generation logic in a separate file

// Placeholder for the PDF generation function/method name
// You need to replace this with how your chosen library/logic works
// This example assumes a function that takes data, output type ('F' for file), and file path.
function generateReceiptPdfFile($data, $filepath) {
    // --- REPLACE THIS WITH YOUR ACTUAL PDF GENERATION CODE ---
    // Use $data['smf'] and $data['s'] to access fee and student details
    // Example using a hypothetical TCPDF helper:
    // $pdf_content = generatePdfContentFromData($data); // Generate content string
    // file_put_contents($filepath, $pdf_content); // Save content to file

    // Or using TCPDF directly (simplified):
    // $pdf = new TCPDF(...);
    // $pdf->AddPage();
    // $pdf->SetFont('helvetica', '', 12);
    // $pdf->Cell(0, 10, 'Fee Receipt for ' . $data['full_name'] . ' (' . $data['fee_month'] . '/' . $data['fee_year'] . ')', 0, 1);
    // // Add more details...
    // $pdf->Output($filepath, 'F'); // Save to file ('F' destination)

    // --- DUMMY FILE GENERATION FOR TESTING ---
    // In a real implementation, REPLACE the following lines with actual PDF generation
    $content = "This is a dummy receipt for Student: " . $data['full_name'] . "\n";
    $content .= "Month/Year: " . $data['fee_month'] . "/" . $data['fee_year'] . "\n";
    $content .= "Amount Due: ₹" . number_format($data['amount_due'], 2) . "\n";
    $content .= "Amount Paid: ₹" . number_format($data['amount_paid'], 2) . "\n";
    // Add other details from $data...
    file_put_contents($filepath, $content); // Save plain text file as dummy
    // END DUMMY GENERATION

    return true; // Return true on success, false on failure (adjust based on your logic)
}
// --- END PDF Library Setup ---


// --- Database Interaction ---
if ($link === false) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Cannot retrieve fee records for download.</p>";
    header("location: view_receipts.php");
    exit;
}

// Replicate filter logic from view_receipts.php (WITHOUT pagination LIMIT/OFFSET)
$filter_year = $_GET['filter_year'] ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$filter_class = $_GET['filter_class'] ?? '';
$filter_van = $_GET['filter_van'] ?? 'all';
$filter_status = $_GET['filter_status'] ?? 'all';

$sql_base = "FROM student_monthly_fees smf JOIN students s ON smf.student_id = s.user_id";
$where_clauses = [];
$param_types = "";
$param_values = [];

// Add WHERE clauses based on filters (Same logic as view_receipts.php)
if (!empty($filter_year)) { $where_clauses[] = "smf.fee_year = ?"; $param_types .= "i"; $param_values[] = $filter_year; }
if (!empty($filter_month)) { $where_clauses[] = "smf.fee_month = ?"; $param_types .= "i"; $param_values[] = $filter_month; }
if (!empty($filter_class)) { $where_clauses[] = "s.current_class = ?"; $param_types .= "s"; $param_values[] = $filter_class; }
if ($filter_van === 'yes') { $where_clauses[] = "s.takes_van = 1"; } elseif ($filter_van === 'no') { $where_clauses[] = "s.takes_van = 0"; }
if ($filter_status === 'paid') { $where_clauses[] = "(smf.is_paid = 1 OR smf.amount_paid >= smf.amount_due) AND smf.amount_due > 0"; }
elseif ($filter_status === 'due') { $where_clauses[] = "smf.amount_paid < smf.amount_due AND smf.amount_due > 0"; }
elseif ($filter_status === 'na') { $where_clauses[] = "smf.amount_due <= 0"; }


$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// Query to get ALL matching records (no LIMIT/OFFSET)
$sql_select_all = "SELECT smf.id, smf.student_id, smf.fee_year, smf.fee_month, smf.base_monthly_fee, smf.monthly_van_fee, smf.monthly_exam_fee, smf.monthly_electricity_fee, smf.amount_due, smf.amount_paid, smf.is_paid, smf.payment_date, smf.notes, s.full_name, s.current_class, s.takes_van "
                 . $sql_base . $sql_where
                 . " ORDER BY smf.fee_year DESC, smf.fee_month DESC, s.current_class ASC, s.full_name ASC";

$monthly_fee_records_all = [];
if ($stmt_select_all = mysqli_prepare($link, $sql_select_all)) {
    if (!empty($param_types)) {
         $bind_params = [$param_types];
         foreach ($param_values as &$value) { $bind_params[] = &$value; }
         call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_select_all], $bind_params));
         unset($value);
    }

    if (mysqli_stmt_execute($stmt_select_all)) {
        $result_select_all = mysqli_stmt_get_result($stmt_select_all);
        while ($row = mysqli_fetch_assoc($result_select_all)) {
            $monthly_fee_records_all[] = $row;
        }
        mysqli_free_result($result_select_all);
    } else {
        error_log("Error fetching all fee records for download: " . mysqli_stmt_error($stmt_select_all));
        $_SESSION['operation_message'] = "<p class='text-red-600'>Error fetching fee records for download.</p>";
        mysqli_close($link);
        header("location: view_receipts.php");
        exit;
    }
    mysqli_stmt_close($stmt_select_all);

} else {
    error_log("Error preparing all fee records download statement: " . mysqli_error($link));
     $_SESSION['operation_message'] = "<p class='text-red-600'>Error preparing query for download.</p>";
     mysqli_close($link);
     header("location: view_receipts.php");
     exit;
}

mysqli_close($link); // Close connection


// --- Generate and Zip PDFs ---
if (empty($monthly_fee_records_all)) {
    $_SESSION['operation_message'] = "<p class='text-orange-600'>No records found matching the filters to download.</p>";
    header("location: view_receipts.php");
    exit;
}

$zip = new ZipArchive();
$zip_filename = "Filtered_Fee_Receipts_" . date('Ymd_His') . ".zip";
$zip_filepath = sys_get_temp_dir() . '/' . $zip_filename;

// Check if zip file creation is successful
if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
     error_log("Cannot create zip file: " . $zip_filepath);
     $_SESSION['operation_message'] = "<p class='text-red-600'>Error creating zip file for download.</p>";
     header("location: view_receipts.php");
     exit;
}

$temp_files = []; // To keep track of temporary PDF files for cleanup

foreach ($monthly_fee_records_all as $record) {
    // Sanitize filename components
    $student_name_clean = preg_replace('/[^A-Za-z0-9_-]+/', '', str_replace(' ', '_', $record['full_name']));
    $year_clean = preg_replace('/[^0-9]+/', '', $record['fee_year']);
    $month_clean = str_pad($record['fee_month'], 2, '0', STR_PAD_LEFT); // Pad month with leading zero

    // Construct a safe filename for the PDF within the zip
    $pdf_filename_in_zip = "Receipt_{$year_clean}_{$month_clean}_{$student_name_clean}_ID{$record['id']}.pdf";

    // Create a temporary file for the PDF on the server
    $temp_pdf_filepath = tempnam(sys_get_temp_dir(), 'receipt_pdf_');
    if ($temp_pdf_filepath === false) {
         error_log("Failed to create temporary file for PDF.");
         // Skip this record or handle error appropriately
         continue; // Skip this record
    }

    // --- Call your PDF generation logic here ---
    // Pass the record data and the temporary file path
    $pdf_generation_success = generateReceiptPdfFile($record, $temp_pdf_filepath); // Assuming your function saves the PDF to the path

    if ($pdf_generation_success && file_exists($temp_pdf_filepath) && filesize($temp_pdf_filepath) > 0) {
        // Add the generated temporary PDF file to the zip archive
        if (!$zip->addFile($temp_pdf_filepath, $pdf_filename_in_zip)) {
             error_log("Failed to add file {$pdf_filename_in_zip} to zip.");
             // You might want to log which file failed
        } else {
             $temp_files[] = $temp_pdf_filepath; // Add to cleanup list only if added to zip
        }
    } else {
         error_log("Failed to generate or find PDF file for record ID " . $record['id']);
         // Log or handle generation failure for this specific record
         // Make sure to clean up the temp file if generation failed after creating the file
         if (file_exists($temp_pdf_filepath)) {
             unlink($temp_pdf_filepath);
         }
    }
}

// Close the zip archive
$zip->close();

// --- Cleanup Temporary PDF Files ---
foreach ($temp_files as $temp_file) {
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
}
// Note: The main zip file ($zip_filepath) will be unlinked after sending its content

// --- Send the ZIP file to the browser ---
if (file_exists($zip_filepath)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_filepath));
    header('Pragma: no-cache');
    header('Expires: 0');

    // Read the file and output it to the browser
    readfile($zip_filepath);

    // Clean up the temporary zip file
    unlink($zip_filepath);

    // Optional: Set a success message (though user sees download, a toast on the next page is good)
     $_SESSION['operation_message'] = "<p class='text-green-600'>" . count($monthly_fee_records_all) . " receipts generated and zipped successfully.</p>";

    exit; // Stop execution
} else {
     error_log("Zip file was not created or not found at expected path: " . $zip_filepath);
     $_SESSION['operation_message'] = "<p class='text-red-600'>Could not generate the zip file. Please try again or check server logs.</p>";
     header("location: view_receipts.php");
     exit;
}

?>