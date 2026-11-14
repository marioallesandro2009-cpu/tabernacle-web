<script setup>
import { au } from '../lib/admin';
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';

const page = usePage();
const open = ref(false);
const menu = ref(false);
const toast = ref(null);
let timer;

const user = computed(() => page.props.auth);
const path = computed(() => page.url.split('?')[0]);
const isActive = (url) => (url === au('/admin') ? path.value === au('/admin') : path.value === url || path.value.startsWith(url + '/'));

watch(
    () => page.props.flash,
    (f) => {
        const msg = f?.error ? { type: 'error', text: f.error } : f?.success ? { type: 'success', text: f.success } : null;
        if (msg) {
            toast.value = msg;
            clearTimeout(timer);
            timer = setTimeout(() => (toast.value = null), 5000);
        }
    },
    { immediate: true, deep: true },
);
watch(path, () => (open.value = false));

const logout = () => router.post(au('/admin/logout'));
</script>

<template>
    <div class="min-h-screen lg:flex">
        <!-- mobile top bar -->
        <div class="sticky top-0 z-30 flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3 lg:hidden">
            <button class="btn btn-ghost btn-sm" aria-label="Buka menu" @click="open = true"><Icon name="menu" /></button>
            <span class="font-semibold text-brand">{{ page.props.app.name }}</span>
            <a :href="page.props.app.url" target="_blank" class="btn btn-ghost btn-sm" aria-label="Lihat situs"><Icon name="external" /></a>
        </div>

        <div v-if="open" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="open = false" />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overflow-y-auto bg-brand-deep text-white transition-transform lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': open }"
        >
            <div class="px-5 py-5">
                <div class="text-lg font-bold leading-tight">{{ page.props.app.name }}</div>
                <div class="text-xs text-white/60">Panel admin</div>
            </div>
            <nav class="flex-1 space-y-4 px-3 pb-6">
                <div v-for="(g, gi) in page.props.nav" :key="gi">
                    <div v-if="g.label" class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/40">{{ g.label }}</div>
                    <Link
                        v-for="i in g.items"
                        :key="i.url"
                        :href="i.url"
                        class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition"
                        :class="isActive(i.url) ? 'bg-white/15 font-medium text-white' : 'text-white/75 hover:bg-white/10 hover:text-white'"
                    >
                        <Icon :name="i.icon" class="h-4 w-4 shrink-0" />
                        <span class="flex-1">{{ i.label }}</span>
                        <span v-if="i.badge === 'leads' && page.props.newLeads" class="rounded-full bg-accent px-1.5 text-[11px] font-semibold text-brand-deep">{{ page.props.newLeads }}</span>
                    </Link>
                </div>
            </nav>
            <div class="border-t border-white/10 p-3">
                <a :href="page.props.app.url" target="_blank" class="mb-2 flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-white/75 hover:bg-white/10 hover:text-white">
                    <Icon name="external" class="h-4 w-4" /> Lihat situs
                </a>
                <div class="relative">
                    <button class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left hover:bg-white/10" @click="menu = !menu">
                        <img v-if="user.avatar" :src="user.avatar" class="h-8 w-8 rounded-full object-cover" alt="">
                        <span v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-accent text-sm font-bold text-brand-deep">{{ user.name.slice(0, 1).toUpperCase() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                            <span class="block truncate text-xs text-white/50">{{ user.roles[0] }}</span>
                        </span>
                    </button>
                    <div v-if="menu" class="absolute bottom-full left-0 mb-1 w-full rounded-lg bg-white py-1 text-sm text-stone-800 shadow-lg">
                        <Link :href="au('/admin/profile')" class="block px-3 py-2 hover:bg-stone-100" @click="menu = false">Profil & keamanan</Link>
                        <button class="block w-full px-3 py-2 text-left hover:bg-stone-100" @click="logout">Keluar</button>
                    </div>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
                <slot />
            </div>
        </main>

        <Transition name="fade">
            <div
                v-if="toast"
                class="fixed bottom-4 right-4 z-[60] max-w-sm rounded-lg px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'"
                role="status"
            >{{ toast.text }}</div>
        </Transition>
    </div>
</template>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
