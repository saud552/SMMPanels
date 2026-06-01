<?php
// =============================================
// admin/manage_blog.php - إدارة المدونة (للمشرف فقط)
// =============================================

session_start();
require_once '../config.php';

// التحقق من تسجيل دخول المشرف
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// جلب بيانات المشرف (اختياري للعرض)
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// دالة إنشاء slug
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // إضافة/تحديث مقال
    if (isset($_POST['save_post'])) {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title']);
        $content = $_POST['content'];
        $excerpt = trim($_POST['excerpt']);
        $status = $_POST['status'];
        $slug = createSlug($title);

        // معالجة الصورة
        $featured_image = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/blog/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_path)) {
                $featured_image = 'uploads/blog/' . $filename;
            }
        }

        // جلب الصورة الحالية إذا كانت موجودة
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if (!$featured_image && $current) {
                $featured_image = $current['featured_image'];
            }
        }

        // التأكد من أن slug فريد
        $original_slug = $slug;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if (!$stmt->fetch()) break;
            $slug = $original_slug . '-' . $counter++;
        }

        if ($id > 0) {
            // تحديث مقال موجود
            $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $status, $id]);
            $success = "✅ Post updated successfully!";
        } else {
            // إضافة مقال جديد
            $author_id = 0; // يمكن ربطه بمعرّف المستخدم العادي إذا أردت
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, status, author_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $status, $author_id]);
            $success = "✅ Post created successfully!";
        }
    }

    // حذف مقال
    if (isset($_POST['delete_post'])) {
        $id = intval($_POST['id']);

        // حذف الصورة المرتبطة
        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if ($post && $post['featured_image'] && file_exists('../' . $post['featured_image'])) {
            unlink('../' . $post['featured_image']);
        }

        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $success = "✅ Post deleted successfully!";
    }

    // حذف الصورة فقط
    if (isset($_POST['delete_image'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if ($post && $post['featured_image'] && file_exists('../' . $post['featured_image'])) {
            unlink('../' . $post['featured_image']);
        }
        $stmt = $pdo->prepare("UPDATE blog_posts SET featured_image = NULL WHERE id = ?");
        $stmt->execute([$id]);
        $success = "✅ Image deleted successfully!";
    }
}

// جلب جميع المقالات
$stmt = $pdo->prepare("SELECT p.*, 'Admin' as author_name FROM blog_posts p ORDER BY p.created_at DESC");
$stmt->execute();
$posts = $stmt->fetchAll();

// جلب مقال للتعديل
$edit_post = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_post = $stmt->fetch();
}
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
    }
    .admin-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .admin-header h1 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
    }
    .admin-header h1 i {
        color: #4f46e5;
        margin-right: 10px;
    }
    .admin-header .user-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .admin-header .user-info span {
        color: #475569;
        font-size: 13px;
    }
    .logout-btn {
        background: #fee2e2;
        color: #dc2626;
        padding: 8px 16px;
        border-radius: 40px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .logout-btn:hover {
        background: #fecaca;
    }
    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }
    .page-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-title h2 {
        font-size: 24px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .page-title h2 i {
        color: #4f46e5;
    }
    .editor-wrapper {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 30px;
        border: 1px solid #e2e8f0;
    }
    .ql-editor {
        min-height: 350px;
        font-size: 14px;
    }
    .ql-editor p {
        margin-bottom: 10px;
    }
    .ql-editor img {
        max-width: 100%;
        border-radius: 8px;
    }
    .posts-table {
        width: 100%;
        border-collapse: collapse;
    }
    .posts-table th,
    .posts-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    .posts-table th {
        background: #f8fafc;
        font-weight: 600;
    }
    .featured-image-preview {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-published {
        background: #d1fae5;
        color: #065f46;
    }
    .status-draft {
        background: #fed7aa;
        color: #92400e;
    }
    .btn-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    .image-preview {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .image-preview img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1e293b;
    }
    .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
        font-family: inherit;
    }
    .form-control:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
    }
    .btn {
        padding: 10px 20px;
        border-radius: 40px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    .btn-primary {
        background: #4f46e5;
        color: white;
    }
    .btn-primary:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }
    .btn-danger {
        background: #fee2e2;
        color: #dc2626;
    }
    .btn-danger:hover {
        background: #fecaca;
    }
    .btn-secondary {
        background: #e2e8f0;
        color: #475569;
    }
    .btn-secondary:hover {
        background: #cbd5e1;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        border-left: 4px solid #10b981;
    }
    .card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #e2e8f0;
    }
    .card-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<div class="admin-header">
    <h1><i class="fas fa-blog"></i> SkyLink SMM - Blog Manager</h1>
    <div class="user-info">
        <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin['username']); ?></span>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-title">
        <h2><i class="fas fa-newspaper"></i> Manage Blog Posts</h2>
        <button class="btn btn-primary" onclick="toggleEditor()">
            <i class="fas fa-plus"></i> New Post
        </button>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <!-- Editor Form -->
    <div id="editorForm" style="display: <?php echo $edit_post ? 'block' : 'none'; ?>;">
        <div class="editor-wrapper">
            <h3 style="margin-bottom: 20px;"><?php echo $edit_post ? '✏️ Edit Post' : '📝 Create New Post'; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $edit_post['id'] ?? 0; ?>">

                <div class="form-group">
                    <label>📌 Title</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($edit_post['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>📝 Excerpt (Short description)</label>
                    <textarea name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($edit_post['excerpt'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>📄 Content</label>
                    <input type="hidden" name="content" id="editorContent" value="<?php echo htmlspecialchars($edit_post['content'] ?? ''); ?>">
                    <div id="editor" style="height: 400px;"></div>
                </div>

                <div class="form-group">
                    <label>🖼️ Featured Image</label>
                    <input type="file" name="featured_image" accept="image/*" class="form-control">
                    <?php if (!empty($edit_post['featured_image'])): ?>
                    <div class="image-preview">
                        <img src="../<?php echo $edit_post['featured_image']; ?>" alt="Current">
                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm" onclick="return confirm('Delete this image?')">🗑️ Delete Image</button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>📢 Status</label>
                    <select name="status" class="form-control">
                        <option value="draft" <?php echo (isset($edit_post) && $edit_post['status'] == 'draft') ? 'selected' : ''; ?>>📄 Draft</option>
                        <option value="published" <?php echo (isset($edit_post) && $edit_post['status'] == 'published') ? 'selected' : ''; ?>>🌍 Published</option>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
                    <button type="submit" name="save_post" class="btn btn-primary">💾 Save Post</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts List -->
    <div class="card">
        <div class="card-title">
            <span><i class="fas fa-list"></i> All Blog Posts</span>
        </div>
        <div style="overflow-x: auto;">
            <table class="posts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                    <tr class="danger">
                        <td><?php echo $post['id']; ?></td>
                        <td>
                            <?php if ($post['featured_image']): ?>
                                <img src="../<?php echo $post['featured_image']; ?>" class="featured-image-preview">
                            <?php else: ?>
                                <div style="width:60px;height:60px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-image" style="color:#94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($post['title'], 0, 50)); ?></strong>
                            <br><small style="color:#94a3b8;">Slug: <?php echo $post['slug']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($post['author_name']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $post['status']; ?>">
                                <?php echo ucfirst($post['status']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($post['views']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="?edit=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete this post?')" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="delete_post" class="btn btn-sm btn-danger">🗑️ Delete</button>
                                </form>
                                <a href="../blog.php?id=<?php echo $post['id']; ?>" target="_blank" class="btn btn-sm" style="background:#f59e0b;color:white;">👁️ View</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <tr class="danger">
                        <td colspan="8" style="text-align:center;padding:60px;">
                            <i class="fas fa-newspaper" style="font-size:48px;color:#94a3b8;margin-bottom:15px;display:block;"></i>
                            No blog posts yet. Click "New Post" to create one.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Initialize Quill editor
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        },
        placeholder: 'Write your blog content here...'
    });

    // Load existing content
    const existingContent = document.getElementById('editorContent').value;
    if (existingContent) {
        quill.root.innerHTML = existingContent;
    }

    // Update hidden field before submit
    document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e) {
        document.getElementById('editorContent').value = quill.root.innerHTML;
    });

    function toggleEditor() {
        const form = document.getElementById('editorForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function cancelEdit() {
        window.location.href = 'manage_blog.php';
    }
</script>