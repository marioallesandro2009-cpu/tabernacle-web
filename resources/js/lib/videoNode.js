import { Node, mergeAttributes } from '@tiptap/core';

/**
 * A video file from the media library (MP4/WebM), stored as
 * <video src="/uploads/..." controls preload="metadata">. The server only keeps
 * videos that point at this site's own uploads.
 */
export const VideoFile = Node.create({
    name: 'videoFile',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            src: { default: null },
            poster: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'video[src]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['video', mergeAttributes({ controls: 'controls', preload: 'metadata', playsinline: 'playsinline' }, HTMLAttributes)];
    },

    addCommands() {
        return {
            setVideoFile:
                (attrs) =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name, attrs }),
        };
    },
});
