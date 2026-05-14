/**
 * TailorPro — Core JavaScript
 * Handles: sidebar, modals, form helpers
 */

// ── Sidebar ───────────────────────────────────────────────
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Show hamburger on small screens
(function setupResponsive() {
    const btn = document.getElementById('menuBtn');
    if (!btn) return;

    function check() {
        btn.style.display = window.innerWidth < 1024 ? 'flex' : 'none';
    }
    check();
    window.addEventListener('resize', check);
})();

// ── Keyboard shortcuts ────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
});

// ── Auto-dismiss flash messages ───────────────────────────
(function autoDismissFlash() {
    const flashes = document.querySelectorAll('.flash-success, .flash-error');
    flashes.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 5000);
    });
})();
