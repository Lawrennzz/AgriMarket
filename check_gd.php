<?php
// Check if GD extension is loaded
if (extension_loaded('gd')) {
    echo "GD is installed and enabled.<br>";
    
    // Get GD info
    $gd_info = gd_info();
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
} else {
    echo "GD is NOT installed or enabled. Please enable the GD extension in your PHP configuration.";
}
?> 