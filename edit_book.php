<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require staff login
require_staff_login();

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header('Location: book_list.php');
    exit();
}

// Get current book data
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: book_list.php?error=book_not_found');
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $price = sanitize_input($_POST['price']);
    $stock_quantity = sanitize_input($_POST['stock_quantity']);
    $category = sanitize_input($_POST['category']);
    $isbn = sanitize_input($_POST['isbn']);
    $description = sanitize_input($_POST['description']);
    

    
    // Validation
    $errors[] = validate_required($title, 'Tên sách');
    $errors[] = validate_required($author, 'Tác giả');
    $errors[] = validate_required($category, 'Thể loại');
    $errors[] = validate_required($isbn, 'ISBN');
    
    $errors[] = validate_price($price);
    $errors[] = validate_stock($stock_quantity);
    $errors[] = validate_isbn($isbn);
    
    // Check for duplicates (excluding current book)
    if (check_duplicate_isbn($isbn, $book_id)) {
        $errors[] = "ISBN này đã tồn tại trong hệ thống";
    }
    
    if (check_duplicate_title($title, $book_id)) {
        $errors[] = "Tên sách này đã tồn tại trong hệ thống";
    }
    
    // Handle image upload first (allow replacing existing)
    $upload_result = handle_book_image_upload('image', $book['image_url']);
    if (!$upload_result['success']) {
        $errors[] = $upload_result['error'];
    }
    $new_image_path = $upload_result['path'] ?? $book['image_url'];
    
    // Remove null errors
    $errors = array_filter($errors);

    // If no errors, update the book
    if (empty($errors)) {
    $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, price = ?, stock_quantity = ?, category = ?, isbn = ?, description = ?, image_url = ?, updated_at = NOW() WHERE id = ?");
    // Types: s=title, s=author, d=price, i=stock_quantity, s=category, s=isbn, s=description, s=image_url, i=id
    $stmt->bind_param("ssdissssi", $title, $author, $price, $stock_quantity, $category, $isbn, $description, $new_image_path, $book_id);
        
        if ($stmt->execute()) {
            // Log the changes
            $changes = [];
            if ($book['title'] != $title) $changes[] = "Title: '{$book['title']}' → '$title'";
            if ($book['author'] != $author) $changes[] = "Author: '{$book['author']}' → '$author'";
            if ($book['price'] != $price) $changes[] = "Price: {$book['price']} → $price";
            if ($book['stock_quantity'] != $stock_quantity) $changes[] = "Stock: {$book['stock_quantity']} → $stock_quantity";
            if ($book['category'] != $category) $changes[] = "Category: '{$book['category']}' → '$category'";
            if ($book['isbn'] != $isbn) $changes[] = "ISBN: '{$book['isbn']}' → '$isbn'";
            if ($book['description'] != $description) $changes[] = "Description updated";
            
            $log_details = "Updated book ID $book_id: " . implode(', ', $changes);
            log_staff_action($_SESSION['user_id'], 'UPDATE_BOOK', $log_details);
            
            header('Location: book_list.php?success=update');
            exit();
        } else {
            $errors[] = "Có lỗi xảy ra khi cập nhật sách. Vui lòng thử lại.";
        }
        
        $stmt->close();
    }
} else {
    // Pre-fill form with current data
    $title = $book['title'];
    $author = $book['author'];
    $price = $book['price'];
    $stock_quantity = $book['stock_quantity'];
    $category = $book['category'];
    $isbn = $book['isbn'];
    $description = $book['description'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa sách - <?php echo htmlspecialchars($book['title']); ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Chỉnh sửa sách</h1>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Chỉnh sửa: <?php echo htmlspecialchars($book['title']); ?></h3>
                <a href="book_detail.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Xem chi tiết</a>
            </div>
            
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Edit Form -->
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Tên sách: <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="author">Tác giả: <span style="color: red;">*</span></label>
                    <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($author); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Giá (VNĐ): <span style="color: red;">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($price); ?>" required>
                    <small>Giá phải lớn hơn 0</small>
                </div>
                
                <div class="form-group">
                    <label for="stock_quantity">Số lượng tồn kho: <span style="color: red;">*</span></label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo htmlspecialchars($stock_quantity); ?>" required>
                    <small>Số lượng phải từ 0 trở lên</small>
                </div>
                
                <div class="form-group">
                    <label for="category">Thể loại: <span style="color: red;">*</span></label>
                    <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>" required 
                           placeholder="Nhập thể loại sách (VD: Tiểu thuyết, Lập trình, Lịch sử...)">
                    <small>Gợi ý: Văn học cổ điển, Tiểu thuyết, Phi tiểu thuyết, Khoa học viễn tưởng, Trinh thám, Lãng mạn, Lập trình, Cơ sở dữ liệu, Kinh doanh, Tự lực, Lịch sử, Tiểu sử</small>
                </div>
                
                <div class="form-group">
                    <label for="isbn">ISBN: <span style="color: red;">*</span></label>
                    <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($isbn); ?>" required>
                    <small>ISBN phải có 10 hoặc 13 chữ số</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả:</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Cập nhật ảnh bìa (JPG/PNG):</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png">
                    <small>Bỏ trống nếu không muốn thay đổi ảnh.</small>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Cập nhật sách</button>
                    <a href="book_detail.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Hủy</a>
                    <a href="book_list.php" class="btn btn-secondary">Quay lại danh sách</a>
                </div>
            </form>
        </div>

        <!-- Current vs New Comparison -->
        <div class="content-area" style="background: #f8f9fa;">
            <h4>Thông tin hiện tại</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div>
                    <strong>Tên sách:</strong> <?php echo htmlspecialchars($book['title']); ?><br>
                    <strong>Tác giả:</strong> <?php echo htmlspecialchars($book['author']); ?><br>
                    <strong>Giá:</strong> <?php echo number_format($book['price'], 0, ',', '.'); ?>đ<br>
                </div>
                <div>
                    <strong>Tồn kho:</strong> <?php echo $book['stock_quantity']; ?> cuốn<br>
                    <strong>Thể loại:</strong> <?php echo htmlspecialchars($book['category']); ?><br>
                    <strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?><br>
                </div>
            </div>
                <div style="margin-top: 15px;">
                    <strong>Mô tả:</strong> <?php echo !empty($book['description']) ? nl2br(htmlspecialchars($book['description'])) : '<em>Chưa có mô tả</em>'; ?>
                </div>
                <div style="margin-top: 15px;">
                    <strong>Ảnh bìa hiện tại:</strong>
                    <div style="margin-top:10px;">
                        <?php if (!empty($book['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($book['image_url']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" style="max-width:200px; border:1px solid #ddd; padding:4px;">
                        <?php else: ?>
                            <div style="background: #f8f9fa; border: 2px dashed #ddd; padding: 30px 20px; border-radius: 6px; text-align: center; color: #666; max-width:200px;">
                                <p>Chưa có hình ảnh</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </div>

    <script>
        // Auto-format ISBN input
        document.getElementById('isbn').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length <= 13) {
                e.target.value = value;
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock_quantity').value);
            const category = document.getElementById('category').value;
            

            
            if (!category || category.trim() === '') {
                alert('Vui lòng nhập thể loại sách!');
                e.preventDefault();
                return;
            }
            
            // Validate minimum length
            if (category.trim().length < 2) {
                alert('Tên thể loại phải có ít nhất 2 ký tự!');
                e.preventDefault();
                return;
            }
            
            if (price <= 0) {
                alert('Giá phải lớn hơn 0!');
                e.preventDefault();
                return;
            }
            
            if (stock < 0) {
                alert('Số lượng tồn kho không được âm!');
                e.preventDefault();
                return;
            }
            
            const isbn = document.getElementById('isbn').value.replace(/[^0-9]/g, '');
            if (isbn.length !== 10 && isbn.length !== 13) {
                alert('ISBN phải có đúng 10 hoặc 13 chữ số!');
                e.preventDefault();
                return;
            }
        });

        // Highlight changed fields
        document.addEventListener('DOMContentLoaded', function() {
            const originalValues = {
                title: <?php echo json_encode($book['title']); ?>,
                author: <?php echo json_encode($book['author']); ?>,
                price: <?php echo json_encode($book['price']); ?>,
                stock_quantity: <?php echo json_encode($book['stock_quantity']); ?>,
                category: <?php echo json_encode($book['category']); ?>,
                isbn: <?php echo json_encode($book['isbn']); ?>,
                description: <?php echo json_encode($book['description']); ?>
            };

            Object.keys(originalValues).forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.addEventListener('input', function() {
                        if (this.value != originalValues[field]) {
                            this.style.borderColor = '#ffc107';
                            this.style.backgroundColor = '#fff3cd';
                        } else {
                            this.style.borderColor = '#ddd';
                            this.style.backgroundColor = 'white';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>