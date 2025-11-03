<!-- Hiệp -->
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require staff login
require_staff_login();

// Auto-deactivate books that have 0 stock
$auto_deactivate_stmt = $conn->prepare("UPDATE books SET status = 'inactive' WHERE stock_quantity = 0 AND status = 'active'");
$auto_deactivate_stmt->execute();
$auto_deactivate_stmt->close();

// (status toggle removed from listing — admin toggles are handled elsewhere via Logs if needed)

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $book_id = (int)$_GET['delete'];
    
    // Get book info for logging
    $book_query = $conn->prepare("SELECT title, author FROM books WHERE id = ?");
    $book_query->bind_param("i", $book_id);
    $book_query->execute();
    $book_result = $book_query->get_result();
    
    if ($book_result->num_rows > 0) {
        $book_info = $book_result->fetch_assoc();
        
        // Soft delete (set status to inactive)
        $delete_stmt = $conn->prepare("UPDATE books SET status = 'inactive' WHERE id = ?");
        $delete_stmt->bind_param("i", $book_id);

        // Debug log: prepare a debug entry before executing
        $debug_dir = __DIR__ . '/../uploads';
        if (!is_dir($debug_dir)) {
            @mkdir($debug_dir, 0755, true);
        }
        $log_file = $debug_dir . '/delete_debug.log';
        $pre_msg = date('Y-m-d H:i:s') . " | DELETE_ATTEMPT | user_id=" . ($_SESSION['user_id'] ?? 'unknown') . " | book_id={$book_id} | title=" . ($book_info['title'] ?? '') . "\n";
        @file_put_contents($log_file, $pre_msg, FILE_APPEND);

        if ($delete_stmt->execute()) {
            // Log the action
            $log_details = "Deactivated book: '{$book_info['title']}' by {$book_info['author']} (ID: $book_id)";
            log_staff_action($_SESSION['user_id'], 'DELETE_BOOK', $log_details);

            // Debug log: success
            $suc_msg = date('Y-m-d H:i:s') . " | DELETE_SUCCESS | book_id={$book_id} | affected_rows=" . $delete_stmt->affected_rows . "\n";
            @file_put_contents($log_file, $suc_msg, FILE_APPEND);

            header('Location: book_list.php?success=delete');
            exit();
        } else {
            // Debug log: failure and DB error
            $err_msg = date('Y-m-d H:i:s') . " | DELETE_FAIL | book_id={$book_id} | error=" . $conn->error . "\n";
            @file_put_contents($log_file, $err_msg, FILE_APPEND);
        }

        $delete_stmt->close();
    }
    $book_query->close();
}
// Hiệp end -->

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sách - Quản lý sách</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Danh sách sách</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">Thống kê</a>
            <a href="add_book.php" class="nav-tab">Thêm sách mới</a>
            <a href="book_list.php" class="nav-tab active">Danh sách sách</a>
            <a href="logs.php" class="nav-tab">Nhật ký hoạt động</a>
        </div>

        <!-- Content -->
        <div class="content-area">
            <!-- Success message -->
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php
                    switch($_GET['success']) {
                        case 'delete':
                            echo 'Sách đã được xóa thành công!';
                            break;
                        case 'update':
                            echo 'Sách đã được cập nhật thành công!';
                            break;
                        default:
                            echo 'Thao tác thành công!';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <form method="GET" action="" class="search-filter">
                <input type="text" name="search" placeholder="Tìm kiếm theo tên, tác giả hoặc ISBN..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="category">
                    <option value="">Tất cả thể loại</option>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($category['category']); ?>" 
                                <?php echo $category_filter == $category['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <select name="status">
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Đã xóa</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                <a href="book_list.php" class="btn btn-secondary">Xóa bộ lọc</a>
            </form>

            <!-- Results summary -->
            <p>Tìm thấy <strong><?php echo $total_records; ?></strong> sách. Trang <?php echo $page; ?> / <?php echo max(1, $total_pages); ?></p>

            <!-- Books table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên sách</th>
                            <th>Tác giả</th>
                            <th>Thể loại</th>
                            <th>Giá</th>
                            <th>Tồn kho</th>
                            <th>Tình trạng hàng</th>
                            <th>ISBN</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($books_result->num_rows > 0): ?>
                            <?php while ($book = $books_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $book['id']; ?></td>
                                    <td>
                                        <?php if (!empty($book['image_url'])): ?>
                                            <?php 
                                                $img_src = '/Book_Web/' . ltrim($book['image_url'], '/');
                                                $img_path = __DIR__ . '/../' . ltrim($book['image_url'], '/');
                                            ?>
                                            <?php if (file_exists($img_path)): ?>
                                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" style="width:50px; height:70px; object-fit:cover; margin-right:8px; vertical-align:middle; border:1px solid #ddd;">
                                            <?php else: ?>
                                                <span style="color:#888;font-size:12px;">Không tìm thấy ảnh</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" style="text-decoration: none; color: #667eea; vertical-align:middle;">
                                            <?php echo htmlspecialchars($book['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td>
                                        <?php
                                            // Show the raw stored category (what user entered). If empty, show 'Chưa phân loại'.
                                            $cat = isset($book['category']) ? trim($book['category']) : '';
                                            if ($cat === '') {
                                                echo '<em>Chưa phân loại</em>';
                                            } else {
                                                echo htmlspecialchars($cat);
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo number_format($book['price'], 0, ',', '.'); ?>đ</td>
                                    <td>
                                        <span class="stock <?php 
                                            if ($book['stock_quantity'] == 0) echo 'out-of-stock';
                                            elseif ($book['stock_quantity'] < 10) echo 'low-stock';
                                            else echo 'in-stock';
                                        ?>">
                                            <?php echo $book['stock_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                            // Inventory status logic
                                            if ((int)$book['stock_quantity'] === 0) {
                                                // Auto-deactivated earlier, show mapping to not-active
                                                echo '<span class="inventory-status out">Hết sách';
                                                if ($book['status'] !== 'inactive') {
                                                    echo ' (Không hoạt động)';
                                                }
                                                echo '</span>';
                                            } elseif ((int)$book['stock_quantity'] < 30) {
                                                echo '<span class="inventory-status low">Sắp hết</span>';
                                            } else {
                                                echo '<span class="inventory-status ok">Đủ hàng</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $book['status']; ?>">
                                            <?php echo $book['status'] == 'active' ? 'Hoạt động' : 'Đã xóa'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="btn btn-success">Xem</a>
                                        <?php if ($book['status'] == 'active'): ?>
                                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-warning">Sửa</a>
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')" class="btn btn-danger">Xóa</a>
                                        <?php endif; ?>

                                        <!-- Admin status toggle removed from list (use Logs to restore/permanent delete) -->
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center;">Không tìm thấy sách nào</td>
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
                    $base_url = 'book_list.php?' . http_build_query($query_string);
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
    </div>

    <script>
        function confirmDelete(bookId, bookTitle) {
            if (confirm('Bạn có chắc chắn muốn xóa sách "' + bookTitle + '"?\n\nSách sẽ được chuyển vào trạng thái "Đã xóa" và không còn hiển thị trên website khách hàng.')) {
                window.location.href = 'book_list.php?delete=' + bookId;
            }
        }
    </script>
</body>
</html>

<?php
$books_stmt->close();
?>