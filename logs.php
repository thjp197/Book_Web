<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require staff login
require_staff_login();

// Handle permanent delete request from Logs (only Admin allowed)
if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $del_book_id = (int)$_GET['permanent_delete'];

    // Only Admin can perform permanent delete
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        header('Location: logs.php?error=permission_denied');
        exit();
    }

    // Get book info (title, image_url) for logging and file removal
    $img_stmt = $conn->prepare("SELECT title, image_url FROM books WHERE id = ?");
    $img_stmt->bind_param('i', $del_book_id);
    $img_stmt->execute();
    $img_res = $img_stmt->get_result();
    if ($img_res->num_rows > 0) {
        $img_row = $img_res->fetch_assoc();

        // Delete record from books table
        $delete_stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $delete_stmt->bind_param('i', $del_book_id);
        if ($delete_stmt->execute()) {
            // Remove image file if exists
            if (!empty($img_row['image_url'])) {
                $img_full = __DIR__ . '/../' . ltrim($img_row['image_url'], '/');
                if (is_file($img_full)) {
                    @unlink($img_full);
                }
            }

            // Log permanent deletion
            $log_details = "Permanently deleted book: '{$img_row['title']}' (ID: $del_book_id)";
            log_staff_action($_SESSION['user_id'], 'PERMANENT_DELETE_BOOK', $log_details);

            header('Location: logs.php?success=permanent_delete');
            exit();
        } else {
            header('Location: logs.php?error=delete_failed');
            exit();
        }
        $delete_stmt->close();
    } else {
        header('Location: logs.php?error=book_not_found');
        exit();
    }
    $img_stmt->close();
}

// Handle restore request from Logs (only Admin allowed)
if (isset($_GET['restore_book']) && is_numeric($_GET['restore_book'])) {
    $restore_book_id = (int)$_GET['restore_book'];

    // Only Admin can perform restore
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        header('Location: logs.php?error=permission_denied');
        exit();
    }

    // Get book info for logging
    $r_stmt = $conn->prepare("SELECT title, status FROM books WHERE id = ?");
    $r_stmt->bind_param('i', $restore_book_id);
    $r_stmt->execute();
    $r_res = $r_stmt->get_result();
    if ($r_res->num_rows > 0) {
        $r_row = $r_res->fetch_assoc();

        // If already active, redirect with a message
        if ($r_row['status'] === 'active') {
            header('Location: logs.php?success=already_active');
            exit();
        }

        $u_stmt = $conn->prepare("UPDATE books SET status = 'active' WHERE id = ?");
        $u_stmt->bind_param('i', $restore_book_id);
        if ($u_stmt->execute()) {
            $log_details = "Restored book: '{$r_row['title']}' (ID: $restore_book_id)";
            log_staff_action($_SESSION['user_id'], 'RESTORE_BOOK', $log_details);
            header('Location: logs.php?success=restored');
            exit();
        } else {
            header('Location: logs.php?error=restore_failed');
            exit();
        }
        $u_stmt->close();
    } else {
        header('Location: logs.php?error=book_not_found');
        exit();
    }
    $r_stmt->close();
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Filter parameters
$staff_filter = isset($_GET['staff']) ? (int)$_GET['staff'] : 0;
$action_filter = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($staff_filter > 0) {
    $where_conditions[] = "sl.staff_id = ?";
    $params[] = $staff_filter;
    $param_types .= 'i';
}

if (!empty($action_filter)) {
    $where_conditions[] = "sl.action_type = ?";
    $params[] = $action_filter;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(sl.timestamp) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(sl.timestamp) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total records
$count_query = "SELECT COUNT(*) as total FROM staff_logs sl JOIN users u ON sl.staff_id = u.id $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// Get logs
$logs_query = "SELECT sl.*, u.full_name, u.username 
               FROM staff_logs sl 
               JOIN users u ON sl.staff_id = u.id 
               $where_clause 
               ORDER BY sl.timestamp DESC 
               LIMIT ? OFFSET ?";

$logs_params = array_merge($params, [$records_per_page, $offset]);
$logs_param_types = $param_types . 'ii';

$logs_stmt = $conn->prepare($logs_query);
$logs_stmt->bind_param($logs_param_types, ...$logs_params);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

// Get staff list for filter
$staff_result = $conn->query("SELECT id, full_name, username FROM users WHERE role = 'Staff' ORDER BY full_name");

// Get action types for filter
$actions_result = $conn->query("SELECT DISTINCT action_type FROM staff_logs ORDER BY action_type");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhật ký hoạt động - Quản lý sách</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Nhật ký hoạt động</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">Thống kê</a>
            <a href="add_book.php" class="nav-tab">Thêm sách mới</a>
            <a href="book_list.php" class="nav-tab">Danh sách sách</a>
            <a href="logs.php" class="nav-tab active">Nhật ký hoạt động</a>
        </div>

        <!-- Content -->
        <div class="content-area">
            <h3>Lịch sử hoạt động của nhân viên</h3>
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php
                        switch ($_GET['success']) {
                            case 'permanent_delete':
                                echo 'Sách đã được xóa vĩnh viễn khỏi cơ sở dữ liệu.';
                                break;
                            case 'restored':
                                echo 'Sách đã được khôi phục và sẽ hiển thị lại trong danh sách.';
                                break;
                            case 'already_active':
                                echo 'Sách đã ở trạng thái hoạt động.';
                                break;
                        }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <?php
                        switch($_GET['error']) {
                            case 'permission_denied': echo 'Bạn không có quyền thực hiện hành động này.'; break;
                            case 'delete_failed': echo 'Xóa thất bại. Vui lòng thử lại.'; break;
                            case 'book_not_found': echo 'Không tìm thấy sách để xóa.'; break;
                            default: echo 'Có lỗi xảy ra.';
                        }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Form -->
            <form method="GET" action="" class="search-filter">
                <select name="staff">
                    <option value="">Tất cả nhân viên</option>
                    <?php while ($staff = $staff_result->fetch_assoc()): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <select name="action">
                    <option value="">Tất cả hành động</option>
                    <?php while ($action = $actions_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($action['action_type']); ?>" 
                                <?php echo $action_filter == $action['action_type'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action['action_type']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="Từ ngày">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="Đến ngày">
                
                <button type="submit" class="btn btn-primary">Lọc</button>
                <a href="logs.php" class="btn btn-secondary">Xóa bộ lọc</a>
            </form>

            <!-- Results summary -->
            <p>Tìm thấy <strong><?php echo $total_records; ?></strong> hoạt động. Trang <?php echo $page; ?> / <?php echo max(1, $total_pages); ?></p>

            <!-- Logs table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Thời gian</th>
                            <th>Nhân viên</th>
                            <th>Hành động</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs_result->num_rows > 0): ?>
                            <?php while ($log = $logs_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br>
                                        <small>(<?php echo htmlspecialchars($log['username']); ?>)</small>
                                    </td>
                                    <td>
                                        <span class="action-badge action-<?php echo strtolower($log['action_type']); ?>">
                                            <?php echo htmlspecialchars($log['action_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 400px; word-wrap: break-word;">
                                            <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                        </div>
                                        <?php
                                            // Try to extract Book ID from details text (patterns like '(ID: 8)' or 'ID: 8')
                                            $linked_book_id = null;
                                            if (preg_match('/ID\s*[:\(]?\s*(\d+)\)?/i', $log['details'], $m)) {
                                                $linked_book_id = (int)$m[1];
                                            }
                                        ?>
                                        <?php if ($linked_book_id): ?>
                                            <?php
                                                // Check book status so we can show a restore button when appropriate
                                                $book_status = null;
                                                $b_stmt = $conn->prepare("SELECT status FROM books WHERE id = ?");
                                                $b_stmt->bind_param('i', $linked_book_id);
                                                $b_stmt->execute();
                                                $b_res = $b_stmt->get_result();
                                                if ($b_res && $b_res->num_rows > 0) {
                                                    $book_status = $b_res->fetch_assoc()['status'];
                                                }
                                                $b_stmt->close();
                                            ?>
                                            <div style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                                                    <?php if ($book_status === 'inactive'): ?>
                                                        <a href="javascript:void(0);" onclick="if(confirm('Bạn có chắc chắn muốn khôi phục sách này và hiển thị lại trên trang danh sách?')){ window.location.href='logs.php?restore_book=<?php echo $linked_book_id; ?>&log_id=<?php echo $log['id']; ?>'; }" class="btn btn-success" style="padding:6px 8px; font-size:12px;">Khôi phục</a>
                                                    <?php endif; ?>
                                                    <a href="javascript:void(0);" onclick="if(confirm('Hành động này sẽ xóa hoàn toàn sách khỏi dữ liệu. Bạn có chắc chắn muốn xóa không?')){ window.location.href='logs.php?permanent_delete=<?php echo $linked_book_id; ?>&log_id=<?php echo $log['id']; ?>'; }" class="btn btn-danger" style="padding:6px 8px; font-size:12px;">Xóa vĩnh viễn sách</a>
                                                <?php else: ?>
                                                    <?php if ($book_status === 'inactive'): ?>
                                                        <button class="btn btn-success" disabled style="padding:6px 8px; font-size:12px;">Khôi phục (Admin)</button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-danger" disabled style="padding:6px 8px; font-size:12px;">Xóa vĩnh viễn (Admin)</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Không tìm thấy hoạt động nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_string = $_GET;
                    unset($query_string['page']);
                    $base_url = 'logs.php?' . http_build_query($query_string);
                    $base_url .= empty($query_string) ? 'page=' : '&page=';
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url . ($page - 1); ?>">« Trước</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $base_url . $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url . ($page + 1); ?>">Sau »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <div class="content-area">
            <h3>Thống kê hoạt động</h3>
            <div class="stats-grid">
                <?php
                // Get today's activities
                $today_activities = $conn->query("SELECT COUNT(*) as count FROM staff_logs WHERE DATE(timestamp) = CURDATE()")->fetch_assoc()['count'];
                
                // Get this week's activities
                $week_activities = $conn->query("SELECT COUNT(*) as count FROM staff_logs WHERE YEARWEEK(timestamp) = YEARWEEK(NOW())")->fetch_assoc()['count'];
                
                // Get most active staff today
                $active_staff_today = $conn->query("
                    SELECT u.full_name, COUNT(*) as activity_count 
                    FROM staff_logs sl 
                    JOIN users u ON sl.staff_id = u.id 
                    WHERE DATE(sl.timestamp) = CURDATE() 
                    GROUP BY sl.staff_id 
                    ORDER BY activity_count DESC 
                    LIMIT 1
                ")->fetch_assoc();
                
                // Get most common action today
                $common_action = $conn->query("
                    SELECT action_type, COUNT(*) as action_count 
                    FROM staff_logs 
                    WHERE DATE(timestamp) = CURDATE() 
                    GROUP BY action_type 
                    ORDER BY action_count DESC 
                    LIMIT 1
                ")->fetch_assoc();
                ?>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $today_activities; ?></div>
                    <div class="stat-label">Hoạt động hôm nay</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $week_activities; ?></div>
                    <div class="stat-label">Hoạt động tuần này</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_staff_today['full_name'] ?? 'N/A'; ?></div>
                    <div class="stat-label">Nhân viên tích cực nhất</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $common_action['action_type'] ?? 'N/A'; ?></div>
                    <div class="stat-label">Hành động phổ biến nhất</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .action-login { background: #d4edda; color: #155724; }
        .action-add_book { background: #d1ecf1; color: #0c5460; }
        .action-update_book { background: #fff3cd; color: #856404; }
        .action-delete_book { background: #f8d7da; color: #721c24; }
        .action-view_book { background: #e2e3e5; color: #383d41; }
    </style>
</body>
</html>

<?php
$logs_stmt->close();
?>