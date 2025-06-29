<?php
// index.php - School Landing Page

// No session_start() needed here as it's a public page
// No database connection needed for this simple example

// Define school details
$schoolName = "Basic Public School"; // Replace with your school name
$schoolTagline = "Madhubani"; // Replace with your school tagline

// Split the school name and tagline into words for animation
$schoolNameWords = explode(' ', $schoolName);
$schoolTaglineWords = explode(' ', $schoolTagline);

// Path to school-related background images (WEB PATHS)
// These paths should be relative to the index.php file or the web root.
// Assuming images are in a 'uploads' subdirectory relative to index.php:
$backgroundImagePaths = [
    "./uploads/10513496.jpg",
    "./uploads/7605994.jpg",
    "./uploads/11159076.jpg",
    // Add more web paths here, e.g., "./uploads/another_image.jpg"
];

// Fallback placeholder image if local images aren't found
$placeholderImage = "https://via.placeholder.com/1920x1080?text=School+Background";

// Filter out non-existing local paths (check SERVER PATH)
$validBackgroundImages = array_filter($backgroundImagePaths, function($webPath) {
    // Construct the server path from the web path and the current script's directory
    // __DIR__ gives the directory of the current PHP file (e.g., C:\xampp\htdocs\School)
    // We append the relative web path (e.g., /uploads/image.jpg)
    $serverPath = __DIR__ . '/' . ltrim($webPath, './'); // Use ltrim to remove './' or '/' if present

    // Check if the file exists on the server's filesystem
    return file_exists($serverPath);
});

// Use placeholders if no valid local images are found
if (empty($validBackgroundImages)) {
    // Create an array of placeholder URLs to cycle through if no local images
    $validBackgroundImages = [
        "https://via.placeholder.com/1920x1080?text=School+Background+1",
        "https://via.placeholder.com/1920x1080?text=School+Background+2",
        "https://via.placeholder.com/1920x1080?text=School+Background+3",
    ];
}


// Rest of the static content for 'About Us' etc.
$schoolDescription = "Welcome to {$schoolName}, where we are dedicated to fostering a nurturing and stimulating environment for students to grow academically, socially, and personally. We believe in providing quality education that prepares students for a bright future."; // Replace with your school description
$aboutUsContent = "Our school has a rich history of academic excellence and community involvement. We offer a wide range of programs, extracurricular activities, and support services designed to meet the diverse needs of our students. Our experienced faculty and staff are committed to creating a positive and engaging learning experience."; // Replace with more about your school


// --- Security Note ---
// Always use htmlspecialchars() when outputting user-provided or variable data into HTML
// to prevent XSS vulnerabilities. This is done for school name, tagline, etc.
// For image paths, ensure they are controlled by the script (like in this example)
// and not directly user-provided, although htmlspecialchars is still good practice
// when embedding them in attributes or scripts.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo htmlspecialchars($schoolName); ?></title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Optional custom styles */
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0; /* Remove default body margin */
            padding: 0; /* Remove default body padding */
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        /* Full-page background image container */
        .full-page-background {
            position: fixed; /* Fixes it to the viewport */
            top: 0;
            left: 0;
            width: 100vw; /* Viewport width */
            height: 100vh; /* Viewport height */
            background-size: cover; /* Cover the container */
            background-position: center; /* Center the image */
            background-repeat: no-repeat;
            transition: background-image 1s ease-in-out; /* Smooth transition for image change */
            z-index: -1; /* Place it behind other content */
            filter: brightness(60%); /* Darken the background for better text readability */
        }

        /* Overlay content for the hero section */
        .hero-overlay {
            position: relative; /* Allows absolute positioning of children if needed, but mostly for layout */
            min-height: 100vh; /* Ensure overlay is at least viewport height */
            display: flex; /* Use flexbox to center content */
            flex-direction: column; /* Stack items vertically */
            justify-content: center; /* Center vertically */
            align-items: center; /* Center horizontally */
            text-align: center;
            color: white; /* White text for readability over dark background */
            padding: 20px; /* Add some padding */
            /* Optional: Add a subtle overlay background */
            /* background-color: rgba(0, 0, 0, 0.3); */
        }

        /* Styling for the animated words */
        .animated-word {
            opacity: 0; /* Start hidden */
            transition: opacity 0.5s ease-in-out; /* Smooth fade-in */
            display: inline-block; /* Important to handle spacing correctly */
            margin: 0 2px; /* Small space between words */
        }

        /* Container for the main content below the hero */
        .main-content {
             max-width: 960px; /* Max width for main content */
             margin: 0 auto; /* Center the container */
             padding: 24px; /* Tailwind p-6 */
             background-color: #fff; /* White background for content */
             border-radius: 8px; /* Tailwind rounded-lg */
             box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Tailwind shadow-md */
             position: relative; /* Position relative to appear above the background */
             z-index: 1; /* Ensure it's above the background */
             margin-top: 40px; /* Add space above the content */
             margin-bottom: 40px; /* Add space below the content */
        }

    </style>
</head>
<body>

    <!-- Full-page background container -->
    <div id="hero-background" class="full-page-background"></div>

    <!-- Hero Overlay Content (animated text, button) -->
    <div class="hero-overlay">
        <!-- Animated School Name -->
        <h1 id="school-name-animated" class="text-4xl md:text-6xl font-bold mb-4">
            <?php
            // Output each word wrapped in a span for animation
            foreach ($schoolNameWords as $word) {
                 echo '<span class="animated-word">' . htmlspecialchars($word) . '</span> ';
            }
            ?>
        </h1>

        <!-- Login Call to Action (placed between name and tagline) -->
        <div class="my-8"> <!-- Add margin for spacing -->
             <a href="login.php" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-lg opacity-0" id="login-button">
                 Go to Login
             </a>
        </div>


        <!-- Animated Tagline -->
        <p id="school-tagline-animated" class="text-lg md:text-xl opacity-90">
            <?php
             // Output each word wrapped in a span for animation
             foreach ($schoolTaglineWords as $word) {
                 echo '<span class="animated-word">' . htmlspecialchars($word) . '</span> ';
             }
             ?>
        </p>

    </div>

    <!-- Main Content Area (Below the fixed hero) -->
    <div class="main-content">

        <!-- Welcome Section - Static Content -->
        <div class="text-center mb-8">
            <h2 class="text-2xl md:text-3xl font-semibold text-gray-800 mb-6">Discover Our School</h2>

            <p class="text-gray-700 mb-6">
                <?php echo nl2br(htmlspecialchars($schoolDescription)); // nl2br converts newlines to <br> ?>
            </p>
        </div>

        <hr class="my-8 border-gray-300"> <!-- Separator -->

        <!-- About Us Section -->
        <div class="mb-8">
            <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4 border-b pb-2">About Us</h3>
            <p class="text-gray-700">
                 <?php echo nl2br(htmlspecialchars($aboutUsContent)); ?>
            </p>
             <!-- Add more paragraphs or content here -->
        </div>

        <!-- Optional: More Sections -->
        <!--
        <div class="mb-8">
             <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4 border-b pb-2">Our Mission</h3>
             <p class="text-gray-700">...</p>
        </div>
        -->

    </div> <!-- End of main content container -->

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-600 text-sm bg-white">
        <div class="container mx-auto px-6">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolName); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Image Carousel ---
            const backgroundElement = document.getElementById('hero-background');
            // Get image URLs from PHP variable
            const imageUrls = <?php echo json_encode($validBackgroundImages); ?>;
            let currentImageIndex = 0;

            function changeBackground() {
                if (imageUrls.length === 0) {
                    console.warn("No background images provided or found.");
                     // If no images found, maybe set a solid background color or keep default
                     backgroundElement.style.backgroundColor = '#333'; // Example: dark gray fallback
                     backgroundElement.style.backgroundImage = 'none'; // Ensure no partial image loads
                    return; // Stop if no images
                }
                // Set the new background image URL
                // Note: The URL provided by PHP *must* be web-accessible
                backgroundElement.style.backgroundImage = `url('${imageUrls[currentImageIndex]}')`;

                // Move to the next image
                currentImageIndex = (currentImageIndex + 1) % imageUrls.length;
            }

            // Change image immediately on load
            changeBackground();
            // Set interval to change image every 2000ms (2 seconds)
            if (imageUrls.length > 1) { // Only cycle if there's more than one image
                 setInterval(changeBackground, 2000);
            }


            // --- Text Animation ---
            const nameWords = document.querySelectorAll('#school-name-animated .animated-word');
            const taglineWords = document.querySelectorAll('#school-tagline-animated .animated-word');
            const loginButton = document.getElementById('login-button');
            const wordAnimationDelay = 150; // Delay between words in milliseconds
            const loopDelay = 3000; // Delay before repeating the animation cycle in milliseconds
            const buttonAppearDelay = 500; // Delay before the button appears after name animation

            function animateText(elements, callback) {
                let delay = 0;
                elements.forEach((word, index) => {
                    setTimeout(() => {
                        word.style.opacity = 1;
                        // If it's the last word, call the callback after its transition
                        if (index === elements.length - 1 && callback) {
                           setTimeout(callback, 600); // Wait for the word's fade-in transition (0.5s + buffer)
                        }
                    }, delay);
                    delay += wordAnimationDelay;
                });
                return delay; // Return the total delay for the animation
            }

            function resetText(elements) {
                 elements.forEach(word => {
                     word.style.opacity = 0;
                 });
            }

            function animateCycle() {
                 // 1. Animate Name
                 const nameAnimationDuration = animateText(nameWords, () => {
                    // 2. After Name animation, show Button
                    setTimeout(() => {
                        loginButton.style.opacity = 1;
                        // 3. After Button appears, Animate Tagline
                        setTimeout(() => {
                            animateText(taglineWords, () => {
                                // 4. After Tagline animation, wait and reset for loop
                                setTimeout(() => {
                                     resetText(nameWords);
                                     resetText(taglineWords);
                                     loginButton.style.opacity = 0;
                                     // Start the next cycle after loopDelay
                                     setTimeout(animateCycle, loopDelay);
                                }, loopDelay); // Wait before resetting
                            });
                        }, buttonAppearDelay); // Delay before animating tagline
                    }, buttonAppearDelay); // Delay before showing button
                 });
            }

            // Start the animation cycle only if there are words to animate
            if (nameWords.length > 0 || taglineWords.length > 0) {
                 animateCycle();
            } else {
                 // If no words, just make the button visible immediately
                 loginButton.style.opacity = 1;
            }


            // Optional: Add a hover effect to the login button if needed
            // loginButton.addEventListener('mouseover', () => { loginButton.classList.add('shadow-xl'); });
            // loginButton.addEventListener('mouseout', () => { loginButton.classList.remove('shadow-xl'); });
        });
    </script>

</body>
</html>