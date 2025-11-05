<!-- Nam -->
<?php
require_once 'config.php';

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data)));
}

// Function to validate required fields
function validate_required($value, $field_name) {
    if (empty($value)) {
        return "$field_name is required";
    }
    return null;
}

// Function to validate price (must be > 0)
function validate_price($price) {
    if (!is_numeric($price) || $price <= 0) {
        return "Price must be a positive number greater than 0";
    }
    return null;
}

// Function to validate stock (must be > 0)
function validate_stock($stock) {
    if (!is_numeric($stock) || $stock < 0) {
        return "Stock quantity must be a non-negative number";
    }
    return null;
}

// Function to validate ISBN format (basic validation)
function validate_isbn($isbn) {
    // Remove hyphens and spaces
    $isbn = preg_replace('/[\-\s]/', '', $isbn);
    // Check if ISBN-10 or ISBN-13
    if (strlen($isbn) != 10 && strlen($isbn) != 13) {
        return "ISBN must be 10 or 13 digits";
    }
    if (!ctype_digit($isbn)) {
        return "ISBN must contain only digits";
    }
    return null;
}

// Function to check if user is staff or admin (has access to admin area)
function is_staff() {
    return isset($_SESSION['user_id']) && (isset($_SESSION['role']) && ($_SESSION['role'] == 'Staff' || $_SESSION['role'] == 'Admin'));
}

// Function to require staff (or admin) login
function require_staff_login() {
    if (!is_staff()) {
        header('Location: ../login.php?error=access_denied');
        exit();
    }
}

// Function to log staff actions
function log_staff_action($staff_id, $action_type, $details) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO staff_logs (staff_id, action_type, details, timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $staff_id, $action_type, $details);
    $stmt->execute();
    $stmt->close();
}

// Function to check for duplicate ISBN
function check_duplicate_isbn($isbn, $exclude_id = null) {
    global $conn;
    $sql = "SELECT id FROM books WHERE isbn = ?";
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $isbn, $exclude_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $isbn);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Function to check for duplicate title
function check_duplicate_title($title, $exclude_id = null) {
    global $conn;
    $sql = "SELECT id FROM books WHERE title = ?";
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $title, $exclude_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $title);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Function to handle image upload for books
function handle_book_image_upload($file_input_name, $existing_path = null) {
    // Returns array: ['success' => bool, 'path' => string|null, 'error' => string|null]
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $existing_path, 'error' => null];
    }

    $file = $_FILES[$file_input_name];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Lỗi khi upload file'];
    }

    // Accept common jpeg/png mime types and also fallback to getimagesize
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/tiff' => 'tiff',
        'image/x-tiff' => 'tiff'
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    // Fallback to getimagesize if finfo didn't return a mime
    if (empty($mime) && function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }

    // Also accept by extension as fallback
    $orig_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (empty($mime)) {
        // If still unknown mime, reject
        $debug_msg = date('Y-m-d H:i:s') . " | UPLOAD_REJECT | Unknown mime for file: {$file['name']}\n";
        @file_put_contents(__DIR__ . '/../uploads/upload_debug.log', $debug_msg, FILE_APPEND);
        return ['success' => false, 'path' => null, 'error' => 'Không xác định được loại file. Chỉ chấp nhận JPG, PNG, WEBP hoặc TIFF'];
    }

    // Normalize jpeg extension names
    if (isset($allowed_types[$mime])) {
        $ext = $allowed_types[$mime];
    } elseif (in_array($orig_ext, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'])) {
        // trust file extension if mime not in list but extension looks fine
        if ($orig_ext === 'jpeg') $ext = 'jpg';
        elseif ($orig_ext === 'tif') $ext = 'tiff';
        else $ext = $orig_ext;
    } else {
        $debug_msg = date('Y-m-d H:i:s') . " | UPLOAD_REJECT | mime={$mime} ext={$orig_ext} file={$file['name']}\n";
        @file_put_contents(__DIR__ . '/../uploads/upload_debug.log', $debug_msg, FILE_APPEND);
        return ['success' => false, 'path' => null, 'error' => 'Chỉ chấp nhận định dạng JPG, PNG, WEBP hoặc TIFF'];
    }

    // Check size (limit to 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'path' => null, 'error' => 'Kích thước file tối đa 5MB'];
    }

    // Create uploads dir if not exists
    $upload_dir = __DIR__ . '/../uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename (use $ext determined earlier)
    $filename = 'book_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest_path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['success' => false, 'path' => null, 'error' => 'Không thể lưu file lên server'];
    }

    // If there was an existing image path (relative), attempt to remove old file
    if (!empty($existing_path)) {
        $existing_full = __DIR__ . '/../' . ltrim($existing_path, '/');
        if (is_file($existing_full)) {
            @unlink($existing_full);
        }
    }

    // Return web-accessible path
    $web_path = 'uploads/' . $filename;
    return ['success' => true, 'path' => $web_path, 'error' => null];
}
?>