<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Cloudflare Turnstile. Emits the token; the token is sent as "cf-turnstile-response".
const props = defineProps({ siteKey: { type: String, required: true } });
const emit = defineEmits(['token']);
const box = ref(null);
let widgetId = null;

function render() {
    if (!window.turnstile || !box.value) return;
    widgetId = window.turnstile.render(box.value, {
        sitekey: props.siteKey,
        language: 'id',
        callback: (t) => emit('token', t),
        'expired-callback': () => emit('token', ''),
        'error-callback': () => emit('token', ''),
    });
}
// after a failed submit the token is used up: the form asks for a fresh one
function reset() {
    emit('token', '');
    if (window.turnstile && widgetId !== null) window.turnstile.reset(widgetId);
}
defineExpose({ reset });

onMounted(() => {
    if (window.turnstile) return render();
    let s = document.querySelector('script[data-turnstile]');
    if (!s) {
        s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.async = true;
        s.defer = true;
        s.dataset.turnstile = '1';
        document.head.appendChild(s);
    }
    s.addEventListener('load', render);
});
onBeforeUnmount(() => {
    if (window.turnstile && widgetId !== null) window.turnstile.remove(widgetId);
});
</script>

<template>
    <div ref="box" class="min-h-[65px]" />
</template>
