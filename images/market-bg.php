<?php
// Set the content type to be an image
header('Content-Type: image/jpeg');

// Create a 1920x1080 image
$width = 1920;
$height = 1080;
$image = imagecreatetruecolor($width, $height);

// Define colors
$green = imagecolorallocate($image, 76, 175, 80);
$light_green = imagecolorallocate($image, 165, 214, 167);
$dark_green = imagecolorallocate($image, 46, 125, 50);
$white = imagecolorallocate($image, 255, 255, 255);
$light_gray = imagecolorallocate($image, 240, 240, 240);
$dark_gray = imagecolorallocate($image, 100, 100, 100);

// Fill background with a gradient-like effect
imagefill($image, 0, 0, $light_gray);

// Draw some simple market elements
// Sky
imagefilledrectangle($image, 0, 0, $width, $height/3, $light_green);

// Ground
imagefilledrectangle($image, 0, $height/3, $width, $height, $green);

// Market stalls - Simple rectangles
for ($i = 0; $i < 10; $i++) {
    $x = $i * ($width/10) + 10;
    $y = $height/3 + 50;
    $stall_width = ($width/10) - 20;
    $stall_height = 200;
    
    // Stall base
    imagefilledrectangle($image, $x, $y, $x + $stall_width, $y + $stall_height, $white);
    
    // Stall roof
    imagefilledpolygon(
        $image, 
        [
            $x - 20, $y,
            $x + $stall_width + 20, $y,
            $x + $stall_width, $y - 50,
            $x, $y - 50
        ], 
        4, 
        $dark_green
    );
    
    // Draw simple products on the stall
    for ($j = 0; $j < 3; $j++) {
        $prod_x = $x + 20 + ($j * 30);
        $prod_y = $y + 50;
        imagefilledellipse($image, $prod_x, $prod_y, 20, 20, $dark_gray);
    }
}

// Draw some people (very simplified)
for ($i = 0; $i < 15; $i++) {
    $x = rand(50, $width - 50);
    $y = rand($height/2, $height - 50);
    
    // Body
    imagefilledrectangle($image, $x - 5, $y - 20, $x + 5, $y + 10, $dark_gray);
    
    // Head
    imagefilledellipse($image, $x, $y - 30, 15, 15, $dark_gray);
}

// Output the image
imagejpeg($image, null, 90);

// Free up memory
imagedestroy($image);
?> 