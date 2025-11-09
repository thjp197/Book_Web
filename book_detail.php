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