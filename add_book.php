<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sách mới - Quản lý sách</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1>Thêm sách mới</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <a href="../logout.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">Thống kê</a>
            <a href="add_book.php" class="nav-tab active">Thêm sách mới</a>
            <a href="book_list.php" class="nav-tab">Danh sách sách</a>
            <a href="logs.php" class="nav-tab">Nhật ký hoạt động</a>
        </div>

        <!-- Content -->
        <div class="content-area">
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Add Book Form -->
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Tên sách: <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="author">Tác giả: <span style="color: red;">*</span></label>
                    <input type="text" id="author" name="author" value="<?php echo isset($author) ? htmlspecialchars($author) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Giá (VNĐ): <span style="color: red;">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" value="<?php echo isset($price) ? htmlspecialchars($price) : ''; ?>" required>
                    <small>Giá phải lớn hơn 0</small>
                </div>
                
                <div class="form-group">
                    <label for="stock_quantity">Số lượng tồn kho: <span style="color: red;">*</span></label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo isset($stock_quantity) ? htmlspecialchars($stock_quantity) : ''; ?>" required>
                    <small>Số lượng phải từ 0 trở lên</small>
                </div>
                
                <div class="form-group">
                    <label for="category">Thể loại: <span style="color: red;">*</span></label>
                    <input type="text" id="category" name="category" value="<?php echo isset($category) ? htmlspecialchars($category) : ''; ?>" required 
                           placeholder="Nhập thể loại sách (VD: Tiểu thuyết, Lập trình, Lịch sử...)">
                    <small>Gợi ý: Văn học cổ điển, Tiểu thuyết, Phi tiểu thuyết, Khoa học viễn tưởng, Trinh thám, Lãng mạn, Lập trình, Cơ sở dữ liệu, Kinh doanh, Tự lực, Lịch sử, Tiểu sử</small>
                </div>
                
                <div class="form-group">
                    <label for="isbn">ISBN: <span style="color: red;">*</span></label>
                    <input type="text" id="isbn" name="isbn" value="<?php echo isset($isbn) ? htmlspecialchars($isbn) : ''; ?>" required>
                    <small>ISBN phải có 10 hoặc 13 chữ số</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả:</label>
                    <textarea id="description" name="description" rows="4"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image">Ảnh bìa (JPG/PNG):</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png">
                    <small>Nếu không chọn, sẽ để trống.</small>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Thêm sách</button>
                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
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
    </script>
</body>
</html>