<!-- Nam -->
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require staff login
require_staff_login();

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header('Location: book_list.php');
    exit();
}

// Get book details
$stmt = $conn->prepare("SELECT b.*, u.full_name as created_by_name FROM books b LEFT JOIN users u ON b.created_by = u.id WHERE b.id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: book_list.php?error=book_not_found');
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// Log view action
log_staff_action($_SESSION['user_id'], 'VIEW_BOOK', "Viewed book details: '{$book['title']}' (ID: $book_id)");
?>
<!-- Nam end -->

<!-- Đức -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Chi tiết sách</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Chi tiết sách</h1>
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
            <a href="logs.php" class="nav-tab">Nhật ký hoạt động</a>
        </div>

        <!-- Content -->
        <div class="content-area">
            <!-- Book Detail -->
            <div class="book-detail">
                <!-- Book Image Placeholder -->
                <div class="book-image">
                    <?php if (!empty($book['image_url'])): ?>
                        <?php 
                            $img_src = '/Book_Web/' . ltrim($book['image_url'], '/');
                            $img_path = __DIR__ . '/../' . ltrim($book['image_url'], '/');
                        ?>
                        <?php if (file_exists($img_path)): ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                        <?php else: ?>
                            <span style="color:#888;font-size:14px;">Không tìm thấy ảnh</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="background: #f8f9fa; border: 2px dashed #ddd; padding: 60px 20px; border-radius: 10px; text-align: center; color: #666;">
                            <h3>Chưa có hình ảnh</h3>
                            <p>ISBN: <?php echo htmlspecialchars($book['isbn']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Book Information -->
                <div class="book-info">
                    <h2><?php echo htmlspecialchars($book['title']); ?></h2>
                    
                    <div class="info-row">
                        <div class="info-label">ID sách:</div>
                        <div class="info-value"><?php echo $book['id']; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Tác giả:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['author']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Thể loại:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['category']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Giá bán:</div>
                        <div class="info-value price"><?php echo number_format($book['price'], 0, ',', '.'); ?>đ</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Tồn kho:</div>
                        <div class="info-value">
                            <span class="stock <?php 
                                if ($book['stock_quantity'] == 0) echo 'out-of-stock';
                                elseif ($book['stock_quantity'] < 10) echo 'low-stock';
                                else echo 'in-stock';
                            ?>">
                                <?php echo $book['stock_quantity']; ?> cuốn
                                <?php if ($book['stock_quantity'] == 0): ?>
                                    (Hết hàng)
                                <?php elseif ($book['stock_quantity'] < 10): ?>
                                    (Sắp hết)
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">ISBN:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['isbn']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Trạng thái:</div>
                        <div class="info-value">
                            <span class="status-<?php echo $book['status']; ?>">
                                <?php echo $book['status'] == 'active' ? 'Hoạt động' : 'Đã xóa'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($book['description'])): ?>
                    <div class="info-row">
                        <div class="info-label">Mô tả:</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($book['description'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <div class="info-label">Người tạo:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['created_by_name'] ?? 'Không xác định'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Ngày tạo:</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($book['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Cập nhật cuối:</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($book['updated_at'])); ?></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="book_list.php" class="btn btn-secondary">← Quay lại danh sách</a>
                        <?php if ($book['status'] == 'active'): ?>
                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-warning">Chỉnh sửa</a>
                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')" class="btn btn-danger">Xóa sách</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information Cards -->
        <div class="content-area">
            <h3>Thông tin bổ sung</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <!-- Đức -->
                <!-- Financial Information -->
                <div class="card">
                    <div class="card-header">
                        <h4>Thông tin tài chính</h4>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Giá mỗi cuốn:</div>
                        <div class="info-value"><?php echo number_format($book['price'], 0, ',', '.'); ?>đ</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tổng giá trị kho:</div>
                        <div class="info-value price"><?php echo number_format($book['price'] * $book['stock_quantity'], 0, ',', '.'); ?>đ</div>
                    </div>
                </div>
                <!-- Đức end -->

                <!-- Stock Status -->
                <div class="card">
                    <div class="card-header">
                        <h4>Trạng thái kho</h4>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Số lượng hiện tại:</div>
                        <div class="info-value"><?php echo $book['stock_quantity']; ?> cuốn</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tình trạng:</div>
                        <div class="info-value">
                            <?php if ($book['stock_quantity'] > 20): ?>
                                <span style="color: #28a745; font-weight: bold;">Đầy đủ</span>
                            <?php elseif ($book['stock_quantity'] > 0): ?>
                                <span style="color: #ffc107; font-weight: bold;">Cần bổ sung</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">Hết hàng</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Publishing Information -->
                <div class="card">
                    <div class="card-header">
                        <h4>Thông tin xuất bản</h4>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ISBN:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['isbn']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Định dạng ISBN:</div>
                        <div class="info-value">
                            <?php 
                            $isbn_clean = preg_replace('/[^0-9]/', '', $book['isbn']);
                            echo strlen($isbn_clean) == 13 ? 'ISBN-13' : 'ISBN-10';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Actions -->
        <?php if ($book['status'] == 'active'): ?>
        <div class="content-area">
            <h3>Thao tác nhanh</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-warning" style="padding: 15px; text-align: center;">
                     Chỉnh sửa thông tin
                </a>
                <a href="add_book.php" class="btn btn-success" style="padding: 15px; text-align: center;">
                     Thêm sách mới
                </a>
                <a href="book_list.php?search=<?php echo urlencode($book['author']); ?>" class="btn btn-secondary" style="padding: 15px; text-align: center;">
                     Xem sách cùng tác giả
                </a>
                <a href="book_list.php?category=<?php echo urlencode($book['category']); ?>" class="btn btn-secondary" style="padding: 15px; text-align: center;">
                     Xem sách cùng thể loại
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
<!-- Đức end -->
 
 <!-- Nam -->
    <script>
        function confirmDelete(bookId, bookTitle) {
            if (confirm('Bạn có chắc chắn muốn xóa sách "' + bookTitle + '"?\n\nSách sẽ được chuyển vào trạng thái "Đã xóa" và không còn hiển thị trên website khách hàng.')) {
                window.location.href = 'book_list.php?delete=' + bookId;
            }
        }

        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add click to copy ISBN
            const isbnElement = document.querySelector('.info-row .info-value');
            if (isbnElement && isbnElement.textContent.match(/^\d+$/)) {
                isbnElement.style.cursor = 'pointer';
                isbnElement.title = 'Click để copy ISBN';
                isbnElement.addEventListener('click', function() {
                    navigator.clipboard.writeText(this.textContent).then(function() {
                        alert('ISBN đã được copy vào clipboard!');
                    });
                });
            }
        });
    </script>
    <!-- Nam end -->
</body>
</html>