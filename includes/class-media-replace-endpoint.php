<?php
// Force WordPress to use Imagick (so we can preserve EXIF/profile data). If Imagick isn't available,
// WP will fall back, but EXIF on resized images will likely be lost.
add_filter('wp_image_editors', function () {
    return ['WP_Image_Editor_Imagick'];
});

/**
 * Helper: preserve original EXIF/profile data (e.g., GPS) into all resized variants.
 */
function hozio_preserve_exif_on_resize($attachment_id, $file_path) {
    if (!class_exists('Imagick')) {
        return;
    }

    try {
        $orig = new Imagick($file_path);
        $profiles = $orig->getImageProfiles('*', true);
    } catch (Exception $e) {
        return;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    if (empty($metadata) || empty($metadata['sizes'])) {
        return;
    }

    $base_dir = pathinfo($file_path, PATHINFO_DIRNAME);
    foreach ($metadata['sizes'] as $size_info) {
        $resized_path = trailingslashit($base_dir) . $size_info['file'];
        if (!file_exists($resized_path)) {
            continue;
        }

        try {
            $img = new Imagick($resized_path);
            foreach ($profiles as $name => $value) {
                $img->profileImage($name, $value);
            }
            $img->writeImage($resized_path);
            $img->clear();
            $img->destroy();
        } catch (Exception $e) {
            // continue silently
        }
    }

    wp_update_attachment_metadata($attachment_id, $metadata);
}

/**
 * Simple per-IP rate limiting (e.g., 30 calls per 2 minutes)
 */
function hozio_rate_limit_check() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$ip) {
        return false;
    }
    $key = "hozio_replace_limit_{$ip}";
    $attempts = (int) get_transient($key);
    if ($attempts >= 30) {
        return false;
    }
    set_transient($key, $attempts + 1, MINUTE_IN_SECONDS * 2);
    return true;
}

/**
 * Shared validation for uploaded file: type and size.
 */
function hozio_validate_upload($tmp, $original_name) {
    $max_bytes = 8 * 1024 * 1024; // 8MB
    if (filesize($tmp) > $max_bytes) {
        return new WP_Error('too_large', 'File exceeds size limit', ['status' => 413]);
    }

    $check = wp_check_filetype_and_ext($tmp, $original_name);
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (empty($check['ext']) || !in_array(strtolower($check['ext']), $allowed, true)) {
        return new WP_Error('invalid_type', 'Disallowed file type', ['status' => 400]);
    }

    return true;
}

/**
 * REST route: replace or rename existing media.
 */
add_action('rest_api_init', function () {
    register_rest_route('hozio/v1', '/replace-media', [
        'methods' => 'POST',
        'callback' => 'hozio_replace_media_in_place_or_rename',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        },
        'args' => [
            'attachment_id' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                },
            ],
            'new_filename' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_file_name',
            ],
        ],
    ]);

    register_rest_route('hozio/v1', '/add-media', [
        'methods' => 'POST',
        'callback' => 'hozio_add_new_media_with_exif',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        },
        'args' => [
            'post_title' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'alt_text'   => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);
});

function hozio_replace_media_in_place_or_rename(WP_REST_Request $request) {
    if (!hozio_rate_limit_check()) {
        return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
    }

    $attachment_id = intval($request->get_param('attachment_id'));
    if (!isset($_FILES['file']) || !get_post($attachment_id)) {
        return new WP_Error('missing', 'Attachment or file missing', ['status' => 400]);
    }

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) {
        return new WP_Error('not_found', 'Original file not found', ['status' => 404]);
    }

    $tmp = $_FILES['file']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return new WP_Error('upload', 'Invalid uploaded file', ['status' => 400]);
    }

    // Validate upload
    $validation = hozio_validate_upload($tmp, $_FILES['file']['name']);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $current_dir = dirname($file_path);
    $new_filename = $request->get_param('new_filename');
    $filename_changed = false;

    if ($new_filename) {
        $new_filename = sanitize_file_name($new_filename);
        $new_path = $current_dir . DIRECTORY_SEPARATOR . $new_filename;

        if (!move_uploaded_file($tmp, $new_path)) {
            return new WP_Error('write_failed', 'Failed to move new file', ['status' => 500]);
        }

        if ($new_path !== $file_path && file_exists($file_path)) {
            @unlink($file_path);
        }

        $relative_new_path = _wp_relative_upload_path($new_path);
        update_post_meta($attachment_id, '_wp_attached_file', $relative_new_path);

        $title_base = pathinfo($new_filename, PATHINFO_FILENAME);
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $title_base,
            'post_name' => sanitize_title_with_dashes($title_base),
        ]);

        $file_path = $new_path;
        $filename_changed = true;
    } else {
        if (!move_uploaded_file($tmp, $file_path)) {
            return new WP_Error('write_failed', 'Failed to overwrite original file', ['status' => 500]);
        }
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
    if (is_wp_error($metadata)) {
        return new WP_Error('meta_fail', 'Metadata generation failed', ['status' => 500]);
    }
    wp_update_attachment_metadata($attachment_id, $metadata);

    if (class_exists('Imagick')) {
        hozio_preserve_exif_on_resize($attachment_id, $file_path);
    }

    if (function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($file_path);
            if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude']) && !empty($exif['GPSLatitudeRef']) && !empty($exif['GPSLongitudeRef'])) {
                $convert = function ($coord, $ref) {
                    $parts = array_map(function ($v) {
                        return eval('return ' . $v . ';');
                    }, explode(',', $coord));
                    if (count($parts) === 3) {
                        list($deg, $min, $sec) = $parts;
                        $decimal = $deg + ($min / 60) + ($sec / 3600);
                        if ($ref === 'S' || $ref === 'W') {
                            $decimal *= -1;
                        }
                        return $decimal;
                    }
                    return null;
                };
                $lat = $convert($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                $lng = $convert($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
                if ($lat !== null && $lng !== null) {
                    update_post_meta($attachment_id, 'gps_lat', $lat);
                    update_post_meta($attachment_id, 'gps_lng', $lng);
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    $url = wp_get_attachment_url($attachment_id);
    return rest_ensure_response([
        'success' => true,
        'attachment_id' => $attachment_id,
        'url' => $url,
        'filename_changed' => $filename_changed,
    ]);
}

function hozio_add_new_media_with_exif(WP_REST_Request $request) {
    if (!hozio_rate_limit_check()) {
        return new WP_Error('rate_limited', 'Too many requests', ['status' => 429]);
    }

    if (!isset($_FILES['file'])) {
        return new WP_Error('missing', 'No file provided', ['status' => 400]);
    }

    $file = $_FILES['file'];
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return new WP_Error('upload', 'Invalid uploaded file', ['status' => 400]);
    }

    // Validate upload
    $validation = hozio_validate_upload($tmp, $file['name']);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $filename = sanitize_file_name($file['name']);
    $file_contents = file_get_contents($tmp);
    $upload = wp_upload_bits($filename, null, $file_contents);
    if (!empty($upload['error'])) {
        return new WP_Error('upload_failed', $upload['error'], ['status' => 500]);
    }

    $file_path = $upload['file'];
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => $request->get_param('post_title') ?: pathinfo($filename, PATHINFO_FILENAME),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $file_path);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $metadata);

    if (class_exists('Imagick')) {
        hozio_preserve_exif_on_resize($attach_id, $file_path);
    }

    if ($alt = $request->get_param('alt_text')) {
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
    }

    $url = wp_get_attachment_url($attach_id);
    return rest_ensure_response([
        'success' => true,
        'attachment_id' => $attach_id,
        'url' => $url,
    ]);
}
