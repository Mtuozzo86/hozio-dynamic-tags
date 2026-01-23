<?php
/**
 * Hozio Pro Custom Logger
 *
 * Provides debug logging to a custom log file without requiring WP_DEBUG.
 * Log file location: wp-content/hozio-debug.log
 *
 * To enable logging, add this to wp-config.php:
 *     define('HOZIO_DEBUG', true);
 *
 * To disable logging (default):
 *     define('HOZIO_DEBUG', false);
 *     or simply don't define it
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if Hozio debug logging is enabled
 *
 * @return bool
 */
function hozio_debug_enabled() {
    // Check wp-config.php constant first (takes priority)
    if (defined('HOZIO_DEBUG')) {
        return HOZIO_DEBUG === true;
    }
    // Fall back to database option (from Settings page)
    return get_option('hozio_debug_enabled', '0') === '1';
}

/**
 * Log a message to the Hozio debug log file
 *
 * @param mixed $message String message or data to log
 * @param string $context Optional context label (e.g., 'CountyQuery', 'Permalink')
 * @return void
 */
function hozio_log($message, $context = '') {
    // Only log if HOZIO_DEBUG is enabled
    if (!hozio_debug_enabled()) {
        return;
    }

    // Log file path in wp-content directory
    $log_file = WP_CONTENT_DIR . '/hozio-debug.log';

    // Format timestamp
    $timestamp = current_time('Y-m-d H:i:s');

    // Format context prefix
    $prefix = $context ? "[{$context}] " : '';

    // Convert arrays/objects to string
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    // Build log entry
    $log_entry = "[{$timestamp}] {$prefix}{$message}" . PHP_EOL;

    // Append to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log debug data to browser console (only when HOZIO_DEBUG is enabled)
 * This queues the data to be output in wp_footer
 *
 * @param mixed $data Data to output to console
 * @param string $label Optional label for the console output
 * @return void
 */
function hozio_console_log($data, $label = 'Hozio Debug') {
    // Only output if HOZIO_DEBUG is enabled
    if (!hozio_debug_enabled()) {
        return;
    }

    add_action('wp_footer', function() use ($data, $label) {
        echo '<script>';
        echo 'console.log("=== ' . esc_js($label) . ' ===");';
        echo 'console.log(' . json_encode($data, JSON_PRETTY_PRINT) . ');';
        echo '</script>';
    }, 9999);
}

/**
 * Clear the Hozio debug log file
 *
 * @return bool True on success, false on failure
 */
function hozio_clear_log() {
    $log_file = WP_CONTENT_DIR . '/hozio-debug.log';

    if (file_exists($log_file)) {
        return file_put_contents($log_file, '') !== false;
    }

    return true;
}

/**
 * Get the path to the Hozio debug log file
 *
 * @return string
 */
function hozio_get_log_path() {
    return WP_CONTENT_DIR . '/hozio-debug.log';
}

/**
 * Get the size of the Hozio debug log file
 *
 * @return string Human-readable file size
 */
function hozio_get_log_size() {
    $log_file = WP_CONTENT_DIR . '/hozio-debug.log';

    if (!file_exists($log_file)) {
        return '0 KB';
    }

    $size = filesize($log_file);

    if ($size < 1024) {
        return $size . ' bytes';
    } elseif ($size < 1048576) {
        return round($size / 1024, 2) . ' KB';
    } else {
        return round($size / 1048576, 2) . ' MB';
    }
}
