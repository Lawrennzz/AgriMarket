<?php
/**
 * Environment variables loader
 * Loads variables from .env file
 */

class Env {
    /**
     * Load environment variables from .env file
     * 
     * @return void
     */
    public static function load() {
        $envFile = __DIR__ . '/../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse the line
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if they exist
                if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
                    $value = substr($value, 1, -1);
                }
                
                // Set the environment variable
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
    
    /**
     * Get an environment variable
     * 
     * @param string $key The environment variable name
     * @param string $default Default value if not set
     * @return mixed
     */
    public static function get($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
}

// Load environment variables
Env::load();
?> 