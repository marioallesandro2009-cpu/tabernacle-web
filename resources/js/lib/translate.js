import axios from 'axios';

// Indonesian → English draft through the server (MyMemory). items: [{ text, html }] → [string]
export async function translate(items) {
    try {
        const { data } = await axios.post('/admin/translate', { items });
        return data.items;
    } catch (e) {
        const msg = e.response?.data?.message || (e.response?.status === 429 ? 'Terlalu sering. Tunggu sebentar lalu coba lagi.' : 'Terjemahan gagal. Coba lagi.');
        window.alert(msg);
        throw e;
    }
}

const hasText = (v) => (typeof v === 'string' ? v.replace(/<[^>]+>/g, '').trim() !== '' : Array.isArray(v) ? v.some(hasText) : v && typeof v === 'object' ? Object.values(v).some(hasText) : false);
export { hasText };

// sub-fields that are names, not sentences: copied as they are
export const KEEP = ['hotel', 'n', 'meals'];
