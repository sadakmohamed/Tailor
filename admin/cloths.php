<?php
/**
 * TailorPro — Cloths Management (Admin only)
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

$uploadDir = ROOT_PATH . '/assets/uploads/cloths/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cloth') {
        $name = trim($_POST['name'] ?? '');
        $color_code = trim($_POST['color_code'] ?? '');
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = uniqid('cloth_') . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $imagePath = '/assets/uploads/cloths/' . $filename;
                }
            }
        }

        if ($name) {
            // Check duplicate
            $chk = $db->prepare('SELECT id FROM cloths WHERE company_id = ? AND name = ?');
            $chk->execute([$cid, $name]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Cloth \"{$name}\" already exists.";
            } else {
                $db->prepare('INSERT INTO cloths (company_id, name, color_code, image_path) VALUES (?,?,?,?)')
                   ->execute([$cid, $name, $color_code, $imagePath]);
                $_SESSION['flash_success'] = "Cloth \"{$name}\" added.";
            }
        }
        header('Location: ' . BASE_URL . '/admin/cloths.php');
        exit;
    }

    if ($action === 'delete_cloth') {
        $cloth_id = (int)$_POST['cloth_id'];
        
        // Get image path to delete file
        $stmt = $db->prepare('SELECT image_path FROM cloths WHERE id = ? AND company_id = ?');
        $stmt->execute([$cloth_id, $cid]);
        $cloth = $stmt->fetch();
        
        if ($cloth) {
            // Check if used in orders
            $used = $db->prepare('SELECT COUNT(*) FROM orders WHERE cloth_id = ?');
            $used->execute([$cloth_id]);
            if ($used->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'Cannot delete: this cloth has existing orders. You can edit it instead.';
            } else {
                if ($cloth['image_path'] && file_exists(ROOT_PATH . $cloth['image_path'])) {
                    unlink(ROOT_PATH . $cloth['image_path']);
                }
                $db->prepare('DELETE FROM cloths WHERE id = ? AND company_id = ?')->execute([$cloth_id, $cid]);
                $_SESSION['flash_success'] = 'Cloth deleted.';
            }
        }
        header('Location: ' . BASE_URL . '/admin/cloths.php');
        exit;
    }

    if ($action === 'edit_cloth') {
        $cloth_id  = (int)$_POST['cloth_id'];
        $newname = trim($_POST['newname'] ?? '');
        $newcolor = trim($_POST['newcolor'] ?? '');
        
        if ($newname && $cloth_id) {
            // Handle image update if any
            $imageSql = "";
            $params = [$newname, $newcolor];
            
            if (isset($_FILES['newimage']) && $_FILES['newimage']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['newimage']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['newimage']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $filename = uniqid('cloth_') . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $imagePath = '/assets/uploads/cloths/' . $filename;
                        $imageSql = ", image_path = ?";
                        $params[] = $imagePath;
                        
                        // Delete old image
                        $stmt = $db->prepare('SELECT image_path FROM cloths WHERE id = ? AND company_id = ?');
                        $stmt->execute([$cloth_id, $cid]);
                        $old = $stmt->fetch();
                        if ($old && $old['image_path'] && file_exists(ROOT_PATH . $old['image_path'])) {
                            unlink(ROOT_PATH . $old['image_path']);
                        }
                    }
                }
            }
            
            $params[] = $cloth_id;
            $params[] = $cid;
            
            $db->prepare("UPDATE cloths SET name = ?, color_code = ? {$imageSql} WHERE id = ? AND company_id = ?")
               ->execute($params);
            $_SESSION['flash_success'] = 'Cloth updated.';
        }
        header('Location: ' . BASE_URL . '/admin/cloths.php');
        exit;
    }
}

$cloths = $db->prepare('
    SELECT c.*,
           COUNT(o.id) AS order_count
    FROM   cloths c
    LEFT   JOIN orders o ON o.cloth_id = c.id
    WHERE  c.company_id = ?
    GROUP  BY c.id
    ORDER  BY c.name
');
$cloths->execute([$cid]);
$cloths = $cloths->fetchAll();

$pageTitle  = 'Cloths Catalog';
$activePage = 'cloths';
include ROOT_PATH . '/includes/header.php';
?>

<div style="max-width:720px;">
    <div class="responsive-sidebar-layout">

        <!-- Cloths list -->
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <h2 style="font-weight:700;font-size:1rem;color:var(--text);margin:0;">
                    Fabric Catalog (<?= count($cloths) ?>)
                </h2>
            </div>
            <?php if (empty($cloths)): ?>
            <div style="padding:2rem;text-align:center;color:var(--text-light);">
                No cloths added yet.
            </div>
            <?php else: ?>
            <?php foreach ($cloths as $cloth): ?>
            <div style="padding:0.875rem 1.25rem;border-bottom:1px solid var(--border);
                         display:flex;align-items:center;gap:1rem;">
                
                <!-- Image or placeholder -->
                <?php if ($cloth['image_path']): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($cloth['image_path']) ?>" 
                         alt="Cloth Image" 
                         style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                <?php else: ?>
                    <div style="width:48px;height:48px;background:#f1f5f9;border-radius:8px;border:1px solid var(--border);
                                 display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--text-light);">
                        🧵
                    </div>
                <?php endif; ?>

                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                        <?= htmlspecialchars($cloth['name']) ?>
                        <?php if ($cloth['color_code']): ?>
                            <div title="Color: <?= htmlspecialchars($cloth['color_code']) ?>" 
                                 style="width:16px;height:16px;border-radius:50%;background-color:<?= htmlspecialchars($cloth['color_code']) ?>;border:1px solid rgba(0,0,0,0.1);"></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-mid);">
                        <?= $cloth['order_count'] ?> order<?= $cloth['order_count'] != 1 ? 's' : '' ?>
                    </div>
                </div>
                <div style="display:flex;gap:0.35rem;">
                    <button onclick="openEditModal(<?= $cloth['id'] ?>, '<?= htmlspecialchars(addslashes($cloth['name'])) ?>', '<?= htmlspecialchars(addslashes($cloth['color_code'] ?? '')) ?>')"
                            class="btn btn-outline btn-sm">Edit</button>
                    <?php if ($cloth['order_count'] == 0): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this cloth?')">
                        <input type="hidden" name="action" value="delete_cloth">
                        <input type="hidden" name="cloth_id" value="<?= $cloth['id'] ?>">
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

        <!-- Add cloth form -->
        <div style="min-width:260px;">
            <div class="card" style="position:sticky;top:80px;">
                <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                    <h3 style="font-weight:700;font-size:0.9rem;color:var(--text);margin:0;">Add Cloth</h3>
                </div>
                <div style="padding:1.25rem;">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_cloth">
                        <div class="form-group">
                            <label class="form-label">Cloth Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name" class="form-input" required
                                   placeholder="e.g. Cotton Blue, Silk Red…">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Color <span style="font-weight:normal;color:var(--text-light);">(optional)</span></label>
                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                <input type="color" name="color_code" value="#ffffff" style="width:36px;height:36px;padding:0;border:none;border-radius:4px;cursor:pointer;">
                                <span style="font-size:0.75rem;color:var(--text-mid);">Select color</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Image <span style="font-weight:normal;color:var(--text-light);">(optional)</span></label>
                            <input type="file" name="image" class="form-input" accept="image/*" style="font-size:0.8rem;">
                        </div>
                        <button type="submit" class="btn btn-gold" style="width:100%;">Add Cloth</button>
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
            ✏️ Edit Cloth
            <button onclick="document.getElementById('editModal').classList.remove('open')"
                    style="background:none;border:none;cursor:pointer;color:var(--text-mid);">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_cloth">
                <input type="hidden" name="cloth_id" id="edit_cloth_id">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="newname" id="edit_newname" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" name="newcolor" id="edit_newcolor" class="form-input" style="height:40px;padding:2px;">
                </div>
                <div class="form-group">
                    <label class="form-label">New Image (Leave blank to keep current)</label>
                    <input type="file" name="newimage" class="form-input" accept="image/*" style="font-size:0.8rem;">
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
function openEditModal(id, name, color) {
    document.getElementById('edit_cloth_id').value = id;
    document.getElementById('edit_newname').value = name;
    
    // Set color or default to white if empty
    const colorInput = document.getElementById('edit_newcolor');
    if (color && color.startsWith('#')) {
        colorInput.value = color;
    } else {
        colorInput.value = '#ffffff';
    }
    
    document.getElementById('editModal').classList.add('open');
}
</script>
JS;
include ROOT_PATH . '/includes/footer.php';
?>
