// The admin panel's address comes from ADMIN_PATH on the server (see the meta tag in admin.blade.php).
// au('/admin/leads') → '/panel-rahasia/leads'. Every admin link and request goes through it.
const meta = typeof document !== 'undefined' ? document.querySelector('meta[name="admin-base"]') : null;
export const adminBase = (meta?.getAttribute('content') || '/admin').replace(/\/+$/, '');

export function au(path) {
    const p = String(path ?? '');
    if (p === '/admin' || p.startsWith('/admin/') || p.startsWith('/admin?') || p.startsWith('/admin#')) return adminBase + p.slice(6);
    return p;
}
