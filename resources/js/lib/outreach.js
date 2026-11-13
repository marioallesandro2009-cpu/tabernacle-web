// Labels and colours for the Outreach screens, in one place.
export const contactStatus = {
    valid: { label: 'Siap kirim', tone: 'green' },
    pending: { label: 'Sedang dicek', tone: 'slate' },
    invalid_format: { label: 'Format salah', tone: 'red' },
    no_mx: { label: 'Domain tak aktif', tone: 'amber' },
    empty: { label: 'Tanpa email', tone: 'slate' },
    suppressed: { label: 'Jangan dikirimi', tone: 'violet' },
};

export const importStatus = {
    uploaded: { label: 'Menunggu pemetaan', tone: 'slate' },
    processing: { label: 'Sedang diimpor', tone: 'blue' },
    done: { label: 'Selesai', tone: 'green' },
    failed: { label: 'Gagal', tone: 'red' },
};

// Mail servers that are already known, so the sender form fills itself in.
export const encryptionOptions = [
    { value: 'ssl', label: 'SSL/TLS (port 465 / 993)' },
    { value: 'tls', label: 'STARTTLS (port 587 / 143)' },
    { value: 'none', label: 'Tanpa enkripsi (tidak disarankan)' },
];

export const campaignStatus = {
    draft: { label: 'Draft', tone: 'slate' },
    running: { label: 'Berjalan', tone: 'green' },
    paused: { label: 'Dijeda', tone: 'amber' },
    completed: { label: 'Selesai', tone: 'blue' },
};

// state of one contact inside a campaign
export const sendStatus = {
    queued: { label: 'Antre', tone: 'slate' },
    sent: { label: 'Terkirim', tone: 'blue' },
    replied: { label: 'Dibalas', tone: 'green' },
    bounced: { label: 'Bounce', tone: 'red' },
    auto_replied: { label: 'Balasan otomatis', tone: 'amber' },
    unsubscribed: { label: 'Berhenti langganan', tone: 'violet' },
    invalid: { label: 'Tidak valid', tone: 'amber' },
    failed: { label: 'Gagal kirim', tone: 'red' },
};

// the CSRF cookie Laravel sets, for the few requests made with fetch()
export const xsrf = () => decodeURIComponent((document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN=')) || '').split('=')[1] || '');
