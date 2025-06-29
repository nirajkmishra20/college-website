<?php
require 'vendor/autoload.php';
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

try {
Configuration::instance([
  'cloud' => [
    'cloud_name' => 'doa', 
    'api_key' => '2666', 
    'api_secret' => 'hEo'],
  'url' => [
    'secure' => true]]);
$cloudinary_configured = true;
} catch (\Exception $e) {
    // Log the error and set the flag to false
    error_log("Cloudinary configuration failed in cloudinary.php: " . $e->getMessage());
    $cloudinary_configured = false;
    // Optionally, you might set a global error message here if needed throughout your application
    // $GLOBALS['cloudinary_config_error'] = "Cloudinary configuration failed.";
}
?>