<?php
// School/admin/generate_receipt_pdf.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can generate fee receipts.</p>";
    header("location: ../login.php");
    exit;
}

// Ensure fee_id is provided
if (!isset($_GET['fee_id']) || !is_numeric($_GET['fee_id'])) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid fee record ID provided.</p>";
    header("location: view_receipts.php"); // Redirect back to the list
    exit;
}

$fee_id = (int)$_GET['fee_id'];

if ($link === false) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Cannot generate receipt.</p>";
    header("location: view_receipts.php");
    exit;
}

// --- Fetch the specific fee record and student data ---
$sql = "SELECT smf.*, s.full_name, s.current_class, s.takes_van FROM student_monthly_fees smf JOIN students s ON smf.student_id = s.user_id WHERE smf.id = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $fee_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $receipt_data = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
    } else {
         error_log("Error fetching single fee record: " . mysqli_stmt_error($stmt));
         $receipt_data = null;
    }
} else {
    error_log("Error preparing single fee record statement: " . mysqli_error($link));
    $receipt_data = null;
}

mysqli_close($link); // Close connection after fetching data

// --- Handle Receipt Generation ---
if ($receipt_data) {
    // --- PDF Generation Logic ---
    // YOU WILL NEED TO INCLUDE YOUR PDF LIBRARY HERE (e.g., TCPDF, mPDF)
    // require_once('../path/to/tcpdf/tcpdf.php'); // Example for TCPDF

    // Assume you have a function or class to generate the PDF content
    // This function would take $receipt_data as input and return the PDF content or output it directly
    // Example:
    // require_once 'ReceiptGenerator.php'; // Assuming you put generation logic in a separate file
    // $pdf_content = generateReceiptPdfContent($receipt_data); // Function to generate PDF content as a string

    // Or use a library that outputs directly:
    // generateAndOutputReceiptPdf($receipt_data, 'D'); // 'D' for download, 'I' for inline view

    // --- Placeholder PDF Output (REPLACE WITH REAL PDF GENERATION) ---
    // This is a DUMMY example assuming you want a file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Receipt_Student_' . $receipt_data['student_id'] . '_Month_' . $receipt_data['fee_month'] . '_Year_' . $receipt_data['fee_year'] . '.pdf"');
    // Output dummy content - REPLACE THIS BLOCK WITH YOUR PDF LIBRARY CALLS
    echo "%PDF-1.0\n";
    echo "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
    echo "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n";
    echo "3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Contents 4 0 R/Parent 2 0 R/Resources<>>>endobj\n";
    echo "4 0 obj<</Length 37>>stream\n";
    echo "BT\n/F1 24 Tf\n72 712 Td\n(Fee Receipt - ID " . $fee_id . ")Tj\nET\n";
    echo "endstream\n";
    echo "xref\n";
    echo "0 5\n";
    echo "0000000000 65535 f \n";
    echo "0000000010 00000 n \n";
    echo "0000000053 00000 n \n";
    echo "0000000102 00000 n \n";
    echo "0000000192 00000 n \n";
    echo "trailer<</Size 5/Root 1 0 R>>startxref\n254\n%%EOF";
    // END OF PLACEHOLDER DUMMY PDF OUTPUT

    exit; // Stop script execution after outputting PDF

} else {
    // Record not found or DB error occurred during fetch
    if (empty($_SESSION['operation_message'])) { // Don't overwrite existing DB error message
         $_SESSION['operation_message'] = "<p class='text-red-600'>Fee record with ID " . htmlspecialchars($fee_id) . " not found.</p>";
    }
    header("location: view_receipts.php"); // Redirect back with error
    exit;
}
?>