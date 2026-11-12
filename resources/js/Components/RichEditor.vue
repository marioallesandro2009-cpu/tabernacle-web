<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import Youtube from '@tiptap/extension-youtube';
import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import MediaDialog from './MediaDialog.vue';
import { remember } from '../lib/media';
import { VideoFile } from '../lib/videoNode';

const props = defineProps({ modelValue: { type: String, default: '' }, placeholder: { type: String, default: 'Mulai menulis…' }, minimal: { type: Boolean, default: false } });
const emit = defineEmits(['update:modelValue']);
const pickImage = ref(false);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({ link: false }),
        Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener' } }),
        Image,
        Youtube.configure({ nocookie: true, width: 640, height: 360 }),
        VideoFile,
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate: ({ editor }) => emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML()),
});

watch(
    () => props.modelValue,
    (v) => {
        if (editor.value && v !== (editor.value.isEmpty ? '' : editor.value.getHTML())) editor.value.commands.setContent(v || '', { emitUpdate: false });
    },
);
onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    const prev = editor.value.getAttributes('link').href || '';
    const url = window.prompt('Alamat tautan (kosongkan untuk menghapus)', prev);
    if (url === null) return;
    if (url === '') editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    else editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
function insertImage(list) {
    list.forEach((m) => {
        remember(m);
        const src = m.url;
        editor.value.chain().focus().setImage({ src, alt: m.alt || m.name }).run();
    });
    pickImage.value = false;
}
function addVideo() {
    const url = window.prompt('Tempel tautan video YouTube (contoh: https://www.youtube.com/watch?v=...)');
    if (url) editor.value.chain().focus().setYoutubeVideo({ src: url }).run();
}
const btn = (active) => ['rounded px-2 py-1 text-sm hover:bg-stone-200', active ? 'bg-stone-300 font-semibold' : ''];
</script>

<template>
    <div v-if="editor" class="overflow-hidden rounded-lg border border-stone-300 bg-white focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-stone-200 bg-stone-50 p-1.5">
            <button type="button" :class="btn(editor.isActive('bold'))" title="Tebal" @click="editor.chain().focus().toggleBold().run()"><b>B</b></button>
            <button type="button" :class="btn(editor.isActive('italic'))" title="Miring" @click="editor.chain().focus().toggleItalic().run()"><i>I</i></button>
            <template v-if="!minimal">
                <span class="mx-1 h-5 w-px bg-stone-300" />
                <button type="button" :class="btn(editor.isActive('heading', { level: 2 }))" title="Judul bagian (H2)" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
                <button type="button" :class="btn(editor.isActive('heading', { level: 3 }))" title="Sub-judul (H3)" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
                <button type="button" :class="btn(editor.isActive('heading', { level: 4 }))" title="H4" @click="editor.chain().focus().toggleHeading({ level: 4 }).run()">H4</button>
                <span class="mx-1 h-5 w-px bg-stone-300" />
                <button type="button" :class="btn(editor.isActive('bulletList'))" title="Daftar poin" @click="editor.chain().focus().toggleBulletList().run()">• Daftar</button>
                <button type="button" :class="btn(editor.isActive('orderedList'))" title="Daftar angka" @click="editor.chain().focus().toggleOrderedList().run()">1. Daftar</button>
                <button type="button" :class="btn(editor.isActive('blockquote'))" title="Kutipan" @click="editor.chain().focus().toggleBlockquote().run()">❝</button>
            </template>
            <span class="mx-1 h-5 w-px bg-stone-300" />
            <button type="button" :class="btn(editor.isActive('link'))" title="Tautan" @click="setLink">Tautan</button>
            <template v-if="!minimal">
                <button type="button" :class="btn(false)" title="Sisipkan gambar" @click="pickImage = true">Gambar</button>
                <button type="button" :class="btn(false)" title="Sisipkan video YouTube (tempel tautannya)" @click="addVideo">Video YouTube</button>
                <button type="button" :class="btn(false)" title="Sisipkan tabel" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()">Tabel</button>
                <template v-if="editor.isActive('table')">
                    <button type="button" :class="btn(false)" @click="editor.chain().focus().addRowAfter().run()">+Baris</button>
                    <button type="button" :class="btn(false)" @click="editor.chain().focus().addColumnAfter().run()">+Kolom</button>
                    <button type="button" :class="btn(false)" @click="editor.chain().focus().deleteTable().run()">Hapus tabel</button>
                </template>
                <span class="mx-1 h-5 w-px bg-stone-300" />
                <button type="button" :class="btn(false)" title="Urungkan" @click="editor.chain().focus().undo().run()">↶</button>
                <button type="button" :class="btn(false)" title="Ulangi" @click="editor.chain().focus().redo().run()">↷</button>
            </template>
        </div>
        <EditorContent :editor="editor" />
        <MediaDialog v-if="pickImage" kind="image" multiple @pick="insertImage" @close="pickImage = false" />
    </div>
</template>
