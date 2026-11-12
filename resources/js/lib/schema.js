const TRANS = ['t_text', 't_textarea', 't_html', 't_rich'];

/** An empty value for a field, shaped like what the server expects. */
export function blank(field) {
    if (TRANS.includes(field.type)) return { en: '', id: '' };
    if (field.type === 'toggle') return !!field.default;
    if (['repeater', 'images', 'list', 'multi'].includes(field.type)) return [];
    return field.default ?? null;
}

export function blankRow(fields) {
    const row = {};
    fields.forEach((f) => {
        row[f.key] = f.type === 'repeater' ? [] : blank(f);
    });
    return row;
}
