import { reactive } from 'vue';

/** Details of every media item seen on the current page (id -> {url, thumb, name, alt, kind}). */
export const mediaStore = reactive({});

export const remember = (m) => {
    if (m && m.id) mediaStore[m.id] = m;
};

export const rememberAll = (obj) => {
    Object.values(obj || {}).forEach(remember);
};
