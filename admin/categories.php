<?php
/**
 * TailorPro — Category Management (Admin only)
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['standard_price'] ?? 0);
        if ($name) {
            // Check duplicate
            $chk = $db->prepare('SELECT id FROM categories WHERE company_id = ? AND name = ?');
            $chk->execute([$cid, $name]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Category \"{$name}\" already exists.";
            } else {
                $db->prepare('INSERT INTO categories (company_id, name, standard_price) VALUES (?,?,?)')->execute([$cid, $name, $price]);
                $_SESSION['flash_success'] = "Category \"{$name}\" added.";
            }
        }
        header('Location: ' . BASE_URL . '/admin/categories.php');
        exit;
    }

    if ($action === 'delete_category') {
        $cat_id = (int)$_POST['cat_id'];
        // Check if used
        $used = $db->prepare('SELECT COUNT(*) FROM orders WHERE category_id = ?');
        $used->execute([$cat_id]);
        if ($used->fetchColumn() > 0) {
            $_SESSION['flash_error'] = 'Cannot delete: this category has existing orders. You can rename it instead.';
        } else {
            $db->prepare('DELETE FROM categories WHERE id = ? AND company_id = ?')->execute([$cat_id, $cid]);
            $_SESSION['flash_success'] = 'Category deleted.';
        }
        header('Location: ' . BASE_URL . '/admin/categories.php');
        exit;
    }

    if ($action === 'edit_category') {
        $cat_id  = (int)$_POST['cat_id'];
        $newname = trim($_POST['newname'] ?? '');
        $newprice = (float)($_POST['newprice'] ?? 0);
        if ($newname && $cat_id) {
            $db->prepare('UPDATE categories SET name = ?, standard_price = ? WHERE id = ? AND company_id = ?')
               ->execute([$newname, $newprice, $cat_id, $cid]);
            $_SESSION['flash_success'] = 'Category updated.';
        }
        header('Location: ' . BASE_URL . '/admin/categories.php');
        exit;
    }
}

$categories = $db->prepare('
    SELECT c.*,
           COUNT(o.id) AS order_count
    FROM   categories c
    LEFT   JOIN orders o ON o.category_id = c.id
    WHERE  c.company_id = ?
    GROUP  BY c.id
    ORDER  BY c.name
');
$categories->execute([$cid]);
$categories = $categories->fetchAll();

$pageTitle  = 'Categories';
$activePage = 'categories';
include ROOT_PATH . '/includes/header.php';
?>

<div style="max-width:680px;">
    <div class="responsive-sidebar-layout">

        <!-- Categories list -->
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <h2 style="font-weight:700;font-size:1rem;color:var(--text);margin:0;">
                    Garment Categories (<?= count($categories) ?>)
                </h2>
            </div>
            <?php if (empty($categories)): ?>
            <div style="padding:2rem;text-align:center;color:var(--text-light);">
                No categories yet.
            </div>
            <?php else: ?>
            <?php foreach ($categories as $cat): ?>
            <div style="padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);
                         display:flex;align-items:center;gap:0.75rem;">
                <div style="width:36px;height:36px;background:#e0e7ff;border-radius:8px;
                             display:flex;align-items:center;justify-content:center;font-size:1rem;">
                    👕
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;">
                        <?= htmlspecialchars($cat['name']) ?>
                        <span class="badge badge-paid" style="margin-left: 0.5rem;"><?= htmlspecialchars($user['currency'] ?? '$') ?><?= number_format($cat['standard_price'], 2) ?></span>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-mid);">
                        <?= $cat['order_count'] ?> order<?= $cat['order_count'] != 1 ? 's' : '' ?>
                    </div>
                </div>
                <div style="display:flex;gap:0.35rem;">
                    <button onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', <?= $cat['standard_price'] ?>)"
                            class="btn btn-outline btn-sm">Edit</button>
                    <?php if ($cat['order_count'] == 0): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?')">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-outline btn-sm" disabled title="Has orders — cannot delete">Delete</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add category form -->
        <div style="min-width:220px;">
            <div class="card" style="position:sticky;top:80px;">
                <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                    <h3 style="font-weight:700;font-size:0.9rem;color:var(--text);margin:0;">Add Category</h3>
                </div>
                <div style="padding:1.25rem;">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" class="form-input" required
                                   placeholder="e.g. Suit, Abaya…">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Standard Price</label>
                            <input type="number" step="0.01" min="0" name="standard_price" class="form-input" required value="0.00">
                        </div>
                        <button type="submit" class="btn btn-gold" style="width:100%;">Add</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            ✏️ Edit Category
            <button onclick="document.getElementById('editModal').classList.remove('open')"
                    style="background:none;border:none;cursor:pointer;color:var(--text-mid);">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="cat_id" id="edit_cat_id">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="newname" id="edit_newname" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Standard Price</label>
                    <input type="number" step="0.01" min="0" name="newprice" id="edit_newprice" required class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('editModal').classList.remove('open')" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function openEditModal(id, name, price) {
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_newname').value = name;
    document.getElementById('edit_newprice').value = price;
    document.getElementById('editModal').classList.add('open');
}
</script>
JS;
include ROOT_PATH . '/includes/footer.php';
?>
