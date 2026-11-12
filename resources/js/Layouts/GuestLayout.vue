<script setup>
import { usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-deep to-brand px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center text-white">
                <div class="text-2xl font-bold tracking-tight">{{ page.props.app.name }}</div>
                <div class="mt-1 text-sm text-white/70">Panel admin</div>
            </div>
            <div class="card p-6">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ page.props.flash.error }}</div>
                <slot />
            </div>
        </div>
    </div>
</template>
