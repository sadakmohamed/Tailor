<?php
/**
 * TailorPro — Add New Customer
 * Handles: Customer info + multiple orders/measurements + appointment + payment
 * Accessible by: admin + staff
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin', 'staff']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

// Fetch this company's categories for dropdown
$cats_stmt = $db->prepare('SELECT id, name, standard_price FROM categories WHERE company_id = ? ORDER BY name');
$cats_stmt->execute([$cid]);
$categories = $cats_stmt->fetchAll();

// Fetch this company's cloths
$cloths_stmt = $db->prepare('SELECT id, name, color_code, image_path FROM cloths WHERE company_id = ? ORDER BY name');
$cloths_stmt->execute([$cid]);
$cloths = $cloths_stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Customer fields
    $name     = trim($_POST['cust_name']  ?? '');
    $phone    = trim($_POST['cust_phone'] ?? '');
    $email    = trim($_POST['cust_email'] ?? '');
    $date_reg = trim($_POST['date_registered'] ?? date('Y-m-d'));
    $cust_notes = trim($_POST['cust_notes'] ?? '');

    if (!$name)  $errors[] = 'Customer name is required.';
    if (!$phone) $errors[] = 'Phone number is required.';

    // ── Orders
    $order_cats  = $_POST['order_cat']   ?? [];
    $order_cloths= $_POST['order_cloth'] ?? [];
    // Measurements arrays (9 fields)
    $m_L   = $_POST['m_L']   ?? [];
    $m_S   = $_POST['m_S']   ?? [];
    $m_M1  = $_POST['m_M1']  ?? [];
    $m_M2  = $_POST['m_M2']  ?? [];
    $m_B   = $_POST['m_B']   ?? [];
    $m_K   = $_POST['m_K']   ?? [];
    $m_P   = $_POST['m_P']   ?? [];
    $m_C   = $_POST['m_C']   ?? [];
    $m_T   = $_POST['m_T']   ?? [];
    
    // Fabric images (base64)
    $fabric_images = $_POST['fabric_image_data'] ?? [];

    // Filter out empty category values
    $order_cats_filtered = array_filter($order_cats, fn($v) => trim((string)$v) !== '');
    if (empty($order_cats_filtered)) {
        $errors[] = 'At least one order/category is required.';
    }

    // ── Appointment
    $appt_date  = trim($_POST['appt_date']  ?? '');
    $appt_time  = trim($_POST['appt_time']  ?? '');
    $appt_notes = trim($_POST['appt_notes'] ?? '');

    // ── Payment
    $pay_amount  = (float)($_POST['pay_amount']  ?? 0);
    $pay_method  = $_POST['pay_method']  ?? 'cash';
    $pay_date    = trim($_POST['pay_date'] ?? date('Y-m-d'));

    if (!$errors) {
        try {
            $db->beginTransaction();

            // 1. Insert customer
            $stmt = $db->prepare('
                INSERT INTO customers (company_id, name, phone, email, date_registered, notes, created_by)
                VALUES (?,?,?,?,?,?,?)
            ');
            $stmt->execute([$cid, $name, $phone, $email, $date_reg, $cust_notes, $user['id']]);
            $customer_id = (int)$db->lastInsertId();

            $first_order_id = null;

            // 2. Insert each order
            foreach ($order_cats as $idx => $cat_val) {
                if (!$cat_val) continue;

                // cat_val can be a category ID (numeric) or custom name (string prefixed with 'custom:')
                $cat_id   = null;
                $cat_name = '';
                if (is_numeric($cat_val)) {
                    $cat_id   = (int)$cat_val;
                    // Look up name
                    foreach ($categories as $c) {
                        if ($c['id'] == $cat_id) { $cat_name = $c['name']; break; }
                    }
                } else {
                    $cat_name = trim($cat_val);
                }

                $price = (float)($order_price[$idx] ?? 0);
                $onote = trim($order_notes[$idx] ?? '');

                // Fabric image processing
                $fabric_path = null;
                $b64 = $fabric_images[$idx] ?? '';
                if ($b64 && strpos($b64, 'data:image') === 0) {
                    $data = explode(',', $b64);
                    if (isset($data[1])) {
                        $decoded = base64_decode($data[1]);
                        if ($decoded) {
                            $fname = 'fabric_' . time() . '_' . $idx . '.jpg';
                            $fdir  = ROOT_PATH . '/assets/uploads/orders/';
                            if (!is_dir($fdir)) mkdir($fdir, 0777, true);
                            file_put_contents($fdir . $fname, $decoded);
                            $fabric_path = '/assets/uploads/orders/' . $fname;
                        }
                    }
                }

                $ostmt = $db->prepare('
                    INSERT INTO orders (customer_id, company_id, category_id, category_name, cloth_name, fabric_image, total_price, notes, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?)
                ');
                $ostmt->execute([$customer_id, $cid, $cat_id, $cat_name, $cat_name, $fabric_path, $price, $onote, $user['id']]);
                $order_id = (int)$db->lastInsertId();

                if ($first_order_id === null) $first_order_id = $order_id;

                // 3. Insert measurements (9 fields)
                $db->prepare('
                    INSERT INTO order_measurements (order_id, L, S, M1, M2, B, K, P, C, T)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ')->execute([
                    $order_id,
                    ($m_L[$idx]  ?? '') !== '' ? (float)$m_L[$idx]  : null,
                    ($m_S[$idx]  ?? '') !== '' ? (float)$m_S[$idx]  : null,
                    ($m_M1[$idx] ?? '') !== '' ? (float)$m_M1[$idx] : null,
                    ($m_M2[$idx] ?? '') !== '' ? (float)$m_M2[$idx] : null,
                    ($m_B[$idx]  ?? '') !== '' ? (float)$m_B[$idx]  : null,
                    ($m_K[$idx]  ?? '') !== '' ? (float)$m_K[$idx]  : null,
                    ($m_P[$idx]  ?? '') !== '' ? (float)$m_P[$idx]  : null,
                    ($m_C[$idx]  ?? '') !== '' ? (float)$m_C[$idx]  : null,
                    ($m_T[$idx]  ?? '') !== '' ? (float)$m_T[$idx]  : null,
                ]);

                // 4. Insert appointment for each order if date provided
                if ($appt_date && $first_order_id === $order_id) {
                    $db->prepare('
                        INSERT INTO appointments (order_id, customer_id, company_id, appointment_date, appointment_time, notes)
                        VALUES (?,?,?,?,?,?)
                    ')->execute([
                        $order_id, $customer_id, $cid,
                        $appt_date,
                        $appt_time ?: null,
                        $appt_notes
                    ]);
                }
            }

            // 5. Insert payment if amount > 0
            if ($pay_amount > 0 && $first_order_id) {
                $db->prepare('
                    INSERT INTO payments (order_id, customer_id, company_id, amount, payment_date, payment_method, received_by)
                    VALUES (?,?,?,?,?,?,?)
                ')->execute([
                    $first_order_id, $customer_id, $cid,
                    $pay_amount, $pay_date, $pay_method, $user['id']
                ]);
            }

            $db->commit();
            $_SESSION['flash_success'] = "Customer \"{$name}\" registered successfully!";
            header('Location: ' . BASE_URL . '/admin/view_customer.php?id=' . $customer_id);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error saving data: ' . $e->getMessage();
        }
    }
}

$currency   = $user['currency'] ?? CURRENCY;
$pageTitle  = 'Add New Customer';
$activePage = 'add_customer';

$extraHead = <<<HTML
<style>
.fabric-camera-container { width: 100%; max-width: 100%; }
.camera-preview-box { height: 200px; transition: all 0.3s ease; }
.camera-preview-box:hover { border-color: var(--gold); background: #f8fafc; }
.measurement-input-group { background: #fff; border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem; }
.measurement-field { display: flex; flex-direction: column; gap: 0.35rem; }
.measurement-field label { font-size: 0.75rem; font-weight: 700; color: var(--navy); }
.measurement-field .form-input { height: 48px; font-size: 1.1rem; text-align: center; font-weight: 700; color: var(--navy); }
.order-header-main { display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
@media (max-width: 640px) { .order-header-main { grid-template-columns: 1fr; } }
</style>
HTML;

include ROOT_PATH . '/includes/header.php';
?>

<div style="max-width:900px;">

    <!-- Breadcrumb -->
    <div style="font-size:0.8rem;color:var(--text-mid);margin-bottom:1.25rem;">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" style="color:var(--gold);text-decoration:none;">Dashboard</a>
        &rsaquo; Add New Customer
    </div>

    <?php if ($errors): ?>
    <div class="flash-error" style="margin-bottom:1rem;flex-direction:column;gap:0.25rem;align-items:flex-start;">
        <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="addCustomerForm">

        <!-- ══ Section 1: Customer Info ══ -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
                <span class="section-title">Customer Information</span>
            </div>
            <div class="responsive-grid-2" style="padding:1.25rem;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="cust_name" class="form-input" required
                           value="<?= htmlspecialchars($_POST['cust_name'] ?? '') ?>"
                           placeholder="e.g. Ahmed Mohammed" id="cust_name">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number <span style="color:var(--danger)">*</span></label>
                    <input type="tel" name="cust_phone" class="form-input" required
                           value="<?= htmlspecialchars($_POST['cust_phone'] ?? '') ?>"
                           placeholder="+251 91 234 5678">
                </div>
                <div class="form-group">
                    <label class="form-label">Date Registered</label>
                    <input type="date" name="date_registered" class="form-input"
                           value="<?= htmlspecialchars($_POST['date_registered'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email (optional)</label>
                    <input type="email" name="cust_email" class="form-input"
                           value="<?= htmlspecialchars($_POST['cust_email'] ?? '') ?>"
                           placeholder="customer@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <input type="text" name="cust_notes" class="form-input"
                           value="<?= htmlspecialchars($_POST['cust_notes'] ?? '') ?>"
                           placeholder="Any notes about the customer…">
                </div>
            </div>
        </div>

        <!-- ══ Section 2: Orders ══ -->
        <div style="margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <span class="section-title" style="margin-bottom:0;">Orders & Measurements</span>
                <button type="button" onclick="addOrderBlock()" class="btn btn-outline btn-sm">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Another Category
                </button>
            </div>

            <div id="ordersContainer">
                <!-- Order blocks are rendered by buildOrderBlock() in JS below -->
            </div>
        </div>

        <!-- ══ Section 3: Appointment ══ -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
                <span class="section-title">Pickup Appointment</span>
            </div>
            <div class="responsive-grid-2" style="padding:1.25rem;">
                <div class="form-group">
                    <label class="form-label">Pickup Date</label>
                    <input type="date" name="appt_date" class="form-input" id="appt_date"
                           value="<?= htmlspecialchars($_POST['appt_date'] ?? '') ?>"
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pickup Time (optional)</label>
                    <input type="time" name="appt_time" class="form-input"
                           value="<?= htmlspecialchars($_POST['appt_time'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Appointment Notes</label>
                    <input type="text" name="appt_notes" class="form-input"
                           value="<?= htmlspecialchars($_POST['appt_notes'] ?? '') ?>"
                           placeholder="e.g. Come after 2 days, call before pickup…">
                </div>
                <!-- Quick date shortcuts -->
                <div style="grid-column:1/-1;display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <span style="font-size:0.75rem;color:var(--text-mid);align-self:center;">Quick set:</span>
                    <?php
                    $shortcuts = [
                        '1 day'   => date('Y-m-d', strtotime('+1 day')),
                        '2 days'  => date('Y-m-d', strtotime('+2 days')),
                        '3 days'  => date('Y-m-d', strtotime('+3 days')),
                        '1 week'  => date('Y-m-d', strtotime('+1 week')),
                    ];
                    foreach ($shortcuts as $label => $val):
                    ?>
                    <button type="button" onclick="setApptDate('<?= $val ?>')"
                            class="btn btn-outline btn-sm">+<?= $label ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ══ Section 4: Payment ══ -->
        <div class="card" style="margin-bottom:1.5rem;">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
                <span class="section-title">Payment (Optional)</span>
            </div>
            <div class="responsive-grid-3" style="padding:1.25rem;">
                <div class="form-group">
                    <label class="form-label">Total Price (all orders)</label>
                    <input type="number" step="0.01" min="0" name="pay_total_display"
                           id="payTotalDisplay" class="form-input" disabled
                           placeholder="Auto-calculated" style="background:#f8fafc;">
                </div>
                <div class="form-group">
                    <label class="form-label">Amount Paid Now</label>
                    <input type="number" step="0.01" min="0" name="pay_amount"
                           id="payAmount" class="form-input"
                           value="<?= htmlspecialchars($_POST['pay_amount'] ?? '') ?>"
                           placeholder="0.00" oninput="updateBalance()">
                </div>
                <div class="form-group">
                    <label class="form-label">Balance Remaining</label>
                    <input type="number" step="0.01" name="pay_balance_display"
                           id="payBalanceDisplay" class="form-input" disabled
                           placeholder="0.00" style="background:#f8fafc;color:var(--danger);font-weight:600;">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="pay_method" class="form-input">
                        <option value="cash">💵 Cash</option>
                        <option value="card">💳 Card</option>
                        <option value="mobile">📱 Mobile</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="pay_date" class="form-input"
                           value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div style="padding:0 1.25rem 1.25rem;">
                <div class="payment-bar">
                    <div class="payment-bar-fill" id="payBar" style="width:0%"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-mid);">
                    <span id="payPct">0% paid</span>
                    <span id="payStatus" style="font-weight:600;"></span>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <button type="submit" class="btn btn-gold btn-lg" id="submitBtn">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Save Customer
            </button>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">Cancel</a>
            <span style="font-size:0.8rem;color:var(--text-light);">All orders will be saved together.</span>
        </div>

    </form>
</div>

<?php
// Build JS JSONs
$cats_json = json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name'], 'price' => (float)$c['standard_price']], $categories));
$cloths_json = json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name'], 'color_code' => $c['color_code'], 'image_path' => $c['image_path']], $cloths));
$currency_js = json_encode($currency);
$base_url_js = json_encode(BASE_URL);

$extraScripts = <<<JS
<script>
const CATEGORIES = {$cats_json};
const CLOTHS = {$cloths_json};
const CURRENCY = {$currency_js};
const BASE_URL = {$base_url_js};
let orderCount = 1;

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select-container')) {
        document.querySelectorAll('.custom-select-dropdown').forEach(d => d.classList.remove('open'));
    }
});

// ── Category select HTML ──────────────────────────────────
function buildCatSelect(idx) {
    let opts = '<option value="">— Select category —</option>';
    CATEGORIES.forEach(c => {
        opts += '<option value="' + c.id + '">' + c.name + '</option>';
    });
    opts += '<option value="__custom__">+ Custom category</option>';
    return opts;
}

// ── Cloth select HTML ─────────────────────────────────────
function buildClothCameraUI(idx) {
    return `
    <div class="fabric-camera-container" id="camera_container_\${idx}">
        <div id="camera_preview_\${idx}" class="camera-preview-box" style="background:#f8fafc;border:2px dashed var(--border);border-radius:1rem;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;cursor:pointer;" onclick="startCamera(\${idx})">
            <div id="camera_placeholder_\${idx}" style="text-align:center;padding:2rem;">
                <div style="width:64px;height:64px;background:var(--gold-muted);color:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div style="font-weight:700;color:var(--navy);font-size:0.9rem;">Click to capture fabric</div>
                <div style="font-size:0.75rem;color:var(--text-light);margin-top:4px;">High quality photo recommended</div>
            </div>
            <video id="video_\${idx}" style="display:none;width:100%;height:100%;object-fit:cover;" autoplay playsinline></video>
            <img id="photo_preview_\${idx}" style="display:none;width:100%;height:100%;object-fit:cover;">
            <div id="camera_controls_\${idx}" style="display:none;position:absolute;bottom:1rem;left:0;right:0;text-align:center;z-index:10;">
                <button type="button" class="btn btn-gold btn-sm" style="box-shadow:0 4px 12px rgba(212,160,23,0.4);" onclick="capturePhoto(\${idx}, event)">📸 Capture Now</button>
            </div>
            <div id="retake_controls_\${idx}" style="display:none;position:absolute;top:0.75rem;right:0.75rem;z-index:10;">
                <button type="button" class="btn btn-danger btn-sm" style="padding:4px 8px;font-size:0.65rem;" onclick="retakePhoto(\${idx}, event)">Retake</button>
            </div>
        </div>
        <input type="hidden" name="fabric_image_data[\${idx}]" id="fabric_data_\${idx}">
        <canvas id="canvas_\${idx}" style="display:none;"></canvas>
    </div>
    `;
}

function toggleClothDropdown(idx) {
    // Close others
    document.querySelectorAll('.custom-select-dropdown').forEach(d => {
        if (d.id !== 'cloth_dropdown_' + idx) d.classList.remove('open');
    });
    document.getElementById('cloth_dropdown_' + idx).classList.toggle('open');
}

function filterCloths(idx, val) {
    const term = val.toLowerCase();
    document.querySelectorAll('.cloth-item-' + idx).forEach(el => {
        if (el.dataset.name.includes(term)) el.style.display = 'flex';
        else el.style.display = 'none';
    });
}

function selectCloth(idx, id, name, imagePath, colorCode) {
    document.getElementById('cloth_input_' + idx).value = id;
    document.getElementById('cloth_input_' + idx).name = 'order_cloth[' + idx + ']';
    
    let imgHtml = (imagePath && imagePath !== 'null' && imagePath !== '') ? `<img src="\${BASE_URL}\${imagePath}" style="width:20px;height:20px;border-radius:4px;object-fit:cover;">` : 
                              `<div style="width:20px;height:20px;border-radius:4px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:10px;">🧵</div>`;
    let colorHtml = (colorCode && colorCode !== 'null' && colorCode !== '') ? `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:\${colorCode};border:1px solid rgba(0,0,0,0.1);"></span>` : '';
    
    document.getElementById('cloth_selected_text_' + idx).innerHTML = `\${imgHtml} \${colorHtml} <span style="color:var(--text);font-weight:500;">\${name}</span>`;
    document.getElementById('cloth_selected_text_' + idx).style.color = 'var(--text)';
    
    document.getElementById('cloth_dropdown_' + idx).classList.remove('open');
    
    const customInput = document.getElementById('cloth_custom_' + idx);
    customInput.style.display = 'none';
    customInput.disabled = true;
}

function selectCustomCloth(idx) {
    document.getElementById('cloth_input_' + idx).value = '';
    document.getElementById('cloth_input_' + idx).removeAttribute('name');
    
    document.getElementById('cloth_selected_text_' + idx).innerHTML = `<strong style="color:var(--gold);">Custom Cloth</strong>`;
    document.getElementById('cloth_dropdown_' + idx).classList.remove('open');
    
    const customInput = document.getElementById('cloth_custom_' + idx);
    customInput.style.display = 'block';
    customInput.disabled = false;
    customInput.name = 'order_cloth[' + idx + ']';
    customInput.required = true;
    customInput.focus();
}

function buildOrderBlock(idx) {
    return `
    <div class="order-block card" data-order="\${idx}" id="order_block_\${idx}" style="padding:1.5rem;border:2px solid var(--border);border-left:5px solid var(--gold);">
      <div class="order-block-header" style="border-bottom:1px solid var(--border);padding-bottom:1rem;margin-bottom:1.5rem;">
        <span class="order-number" style="color:var(--navy);font-size:1rem;">Order Details #\${idx + 1}</span>
        <button type="button" class="remove-order-btn" onclick="removeOrderBlock(\${idx})">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
          Remove This Order
        </button>
      </div>

      <div class="order-header-main">
        <!-- Left: Fabric & Basic Info -->
        <div>
            <label class="form-label" style="text-transform:uppercase;font-size:0.7rem;letter-spacing:0.05em;color:var(--text-mid);">Fabric Snapshot</label>
            \${buildClothCameraUI(idx)}
            
            <div style="margin-top:1.25rem;">
                <label class="form-label">Category <span style="color:var(--danger)">*</span></label>
                <select name="order_cat[\${idx}]" class="form-input cat-select" required
                        onchange="handleCatChange(this, \${idx})" id="cat_sel_\${idx}">
                  \${buildCatSelect(idx)}
                </select>
                <input type="text" id="cat_custom_\${idx}"
                       class="form-input" placeholder="Enter category name…"
                       style="display:none;margin-top:0.5rem;" disabled>
            </div>
            <div style="margin-top:1rem;">
                <label class="form-label">Price (\${CURRENCY})</label>
                <input type="number" step="0.01" min="0" name="order_price[\${idx}]"
                       class="form-input price-input" placeholder="0.00" oninput="recalcTotal()" style="font-weight:700;color:var(--success);">
            </div>
        </div>

        <!-- Right: Measurements -->
        <div class="measurement-input-group">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.75rem;">
                <span style="font-weight:800;color:var(--navy);font-size:0.9rem;">MEASUREMENTS (INCH)</span>
                <span style="font-size:0.7rem;color:var(--text-light);background:var(--bg);padding:2px 8px;border-radius:12px;">Standard Units</span>
            </div>
            <div class="measurements-grid" style="grid-template-columns: repeat(3, 1fr); gap:1.25rem;">
              <div class="measurement-field">
                <label>L</label>
                <input type="number" step="0.1" name="m_L[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>S</label>
                <input type="number" step="0.1" name="m_S[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>M</label>
                <input type="number" step="0.1" name="m_M1[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>M</label>
                <input type="number" step="0.1" name="m_M2[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>B</label>
                <input type="number" step="0.1" name="m_B[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>K</label>
                <input type="number" step="0.1" name="m_K[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>P</label>
                <input type="number" step="0.1" name="m_P[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>C</label>
                <input type="number" step="0.1" name="m_C[\${idx}]" class="form-input" placeholder="0.0">
              </div>
              <div class="measurement-field">
                <label>T</label>
                <input type="number" step="0.1" name="m_T[\${idx}]" class="form-input" placeholder="0.0">
              </div>
            </div>
            
            <div style="margin-top:1.5rem;border-top:1px solid #f1f5f9;padding-top:1.25rem;">
                <label class="form-label">Special Order Notes</label>
                <textarea name="order_notes[\${idx}]" class="form-input" rows="2" placeholder="e.g. double stitching, specific pocket style…" style="resize:none;"></textarea>
            </div>
        </div>
      </div>
    </div>`;
}

async function startCamera(idx) {
    const video = document.getElementById('video_' + idx);
    const placeholder = document.getElementById('camera_placeholder_' + idx);
    const controls = document.getElementById('camera_controls_' + idx);
    const photoPreview = document.getElementById('photo_preview_' + idx);

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        video.srcObject = stream;
        video.style.display = 'block';
        placeholder.style.display = 'none';
        controls.style.display = 'block';
        photoPreview.style.display = 'none';
    } catch (err) {
        console.error("Camera error:", err);
        alert("Could not access camera. Please ensure you are using HTTPS and have given permission.");
    }
}

function capturePhoto(idx, event) {
    event.stopPropagation();
    const video = document.getElementById('video_' + idx);
    const canvas = document.getElementById('canvas_' + idx);
    const photoPreview = document.getElementById('photo_preview_' + idx);
    const hiddenInput = document.getElementById('fabric_data_' + idx);
    const controls = document.getElementById('camera_controls_' + idx);

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
    hiddenInput.value = dataUrl;
    photoPreview.src = dataUrl;
    photoPreview.style.display = 'block';
    video.style.display = 'none';
    controls.style.display = 'none';
    document.getElementById('retake_controls_' + idx).style.display = 'block';
    
    // Stop stream
    const stream = video.srcObject;
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
}

function retakePhoto(idx, event) {
    event.stopPropagation();
    document.getElementById('fabric_data_' + idx).value = '';
    document.getElementById('photo_preview_' + idx).style.display = 'none';
    document.getElementById('retake_controls_' + idx).style.display = 'none';
    startCamera(idx);
}

function addOrderBlock() {
    document.getElementById('ordersContainer').insertAdjacentHTML('beforeend', buildOrderBlock(orderCount));
    orderCount++;
}

function removeOrderBlock(idx) {
    const el = document.getElementById('order_block_' + idx);
    if (el) {
        el.remove();
        recalcTotal();
    }
}

function handleCatChange(sel, idx) {
    const customInput = document.getElementById('cat_custom_' + idx);
    const priceInput = document.querySelector('input[name="order_price[' + idx + ']"]');
    
    if (sel.value === '__custom__') {
        sel.style.display = 'none';
        sel.removeAttribute('name');
        sel.removeAttribute('required');
        customInput.style.display = 'block';
        customInput.disabled = false;
        customInput.name = 'order_cat[' + idx + ']';
        customInput.required = true;
        customInput.focus();
        if (priceInput) priceInput.value = '0.00';
    } else if (sel.value) {
        const cat = CATEGORIES.find(c => c.id == sel.value);
        if (cat && priceInput) {
            priceInput.value = cat.price.toFixed(2);
        }
    } else {
        if (priceInput) priceInput.value = '';
    }
    recalcTotal();
}

// Build first block inline
document.getElementById('ordersContainer').innerHTML = buildOrderBlock(0);
orderCount = 1;

// ── Payment calculator ────────────────────────────────────
function recalcTotal() {
    const prices = document.querySelectorAll('.price-input');
    let total = 0;
    prices.forEach(p => { total += parseFloat(p.value) || 0; });
    document.getElementById('payTotalDisplay').value  = total.toFixed(2);
    updateBalance();
}

function updateBalance() {
    const total   = parseFloat(document.getElementById('payTotalDisplay').value) || 0;
    const paid    = parseFloat(document.getElementById('payAmount').value) || 0;
    const balance = Math.max(0, total - paid);
    const pct     = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : 0;

    document.getElementById('payBalanceDisplay').value = balance.toFixed(2);
    document.getElementById('payBar').style.width = pct + '%';
    document.getElementById('payPct').textContent = pct + '% paid';

    let status = '';
    if (total === 0) status = '';
    else if (paid >= total) status = '✅ Fully Paid';
    else if (paid > 0) status = '⚠️ Partial Payment';
    else status = '❌ Unpaid';
    document.getElementById('payStatus').textContent = status;
}

// ── Appointment shortcuts ─────────────────────────────────
function setApptDate(val) {
    document.getElementById('appt_date').value = val;
}
</script>
JS;

include ROOT_PATH . '/includes/footer.php';
?>
