<?php
/**
 * TailorPro — Sidebar Navigation
 * Requires: $activePage variable and currentUser() available.
 * Included by includes/header.php
 */
$role  = currentUser()['role'] ?? '';
$base  = BASE_URL;

function navLink(string $href, string $icon, string $label, string $active, string $key): void {
    $cls = ($active === $key) ? 'sidebar-link active' : 'sidebar-link';
    echo '<a href="' . $href . '" class="' . $cls . '">' . $icon . '<span>' . $label . '</span></a>';
}

$icons = [
    'dashboard' => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    'customers'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    'add_customer' => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>',
    'staff'      => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
    'categories' => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>',
    'reports'    => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'companies'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
    'new_company' => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    'cloths'     => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-4.879-4.879l-4.242-4.242m4.242 4.242L19 9m-4.879 4.879L9 19m-4.242-4.242l4.242-4.242m0 0L9 5m4.242 4.242L5 9m4.242-4.242l4.242 4.242"/></svg>',
];

$ap = $activePage ?? '';
?>

<?php if ($role === 'superadmin'): ?>
    <div class="sidebar-section-label">Management</div>
    <?php navLink($base.'/superadmin/dashboard.php',  $icons['companies'],  'Companies',    $ap, 'dashboard'); ?>
    <?php navLink($base.'/superadmin/create_company.php', $icons['new_company'], 'New Company', $ap, 'create_company'); ?>

<?php else: ?>
    <div class="sidebar-section-label">Main</div>
    <?php navLink($base.'/admin/dashboard.php',      $icons['dashboard'],    'Dashboard',    $ap, 'dashboard'); ?>
    <?php navLink($base.'/admin/customers.php',      $icons['customers'],    'Customers',    $ap, 'customers'); ?>
    <?php navLink($base.'/admin/add_customer.php',   $icons['add_customer'], 'Add Customer', $ap, 'add_customer'); ?>

    <?php if ($role === 'admin'): ?>
    <div class="sidebar-section-label">Administration</div>
    <?php navLink($base.'/admin/staff.php',      $icons['staff'],      'Staff',      $ap, 'staff'); ?>
    <?php navLink($base.'/admin/categories.php', $icons['categories'], 'Categories', $ap, 'categories'); ?>
    <?php navLink($base.'/admin/cloths.php',     $icons['cloths'],     'Cloths',     $ap, 'cloths'); ?>
    <?php navLink($base.'/admin/reports.php',    $icons['reports'],    'Reports',    $ap, 'reports'); ?>
    <?php endif; ?>
<?php endif; ?>
