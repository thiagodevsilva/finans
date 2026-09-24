import { onBeforeUnmount, ref } from 'vue';

const MAX_FILES = 5;
const MAX_BYTES = 6 * 1024 * 1024;
const ACCEPTED = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

function formatSize(bytes) {
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Gerencia anexos de suporte (file picker + Ctrl+V de imagens).
 * @param {(files: File[]) => void} setFormAttachments
 */
export function useSupportAttachments(setFormAttachments) {
    const previews = ref([]);
    const pasteHint = ref('');
    let files = [];

    const applyFiles = (incoming, { append = false } = {}) => {
        pasteHint.value = '';
        const next = append ? [...files] : [];

        for (const file of incoming) {
            if (!ACCEPTED.has(file.type)) {
                pasteHint.value = 'Use JPG, PNG, WEBP ou GIF.';
                continue;
            }
            if (file.size > MAX_BYTES) {
                pasteHint.value = 'Cada imagem deve ter no máximo 6 MB.';
                continue;
            }
            if (next.length >= MAX_FILES) {
                pasteHint.value = 'Máximo de 5 imagens.';
                break;
            }
            next.push(file);
        }

        files = next;
        setFormAttachments(files);
        previews.value.forEach((p) => URL.revokeObjectURL(p.url));
        previews.value = files.map((file) => ({
            name: file.name,
            size: file.size,
            url: URL.createObjectURL(file),
        }));
    };

    const onFilesChange = (event) => {
        applyFiles(Array.from(event.target.files || []), { append: false });
        if (event.target) {
            event.target.value = '';
        }
    };

    const onPaste = (event) => {
        const items = Array.from(event.clipboardData?.items || []);
        const images = items
            .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
            .map((item, index) => {
                const file = item.getAsFile();
                if (!file) return null;
                const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                const name = file.name && file.name !== 'image.png'
                    ? file.name
                    : `print-clipboard-${Date.now()}-${index}.${ext}`;
                return new File([file], name, { type: file.type });
            })
            .filter(Boolean);

        if (!images.length) return;

        event.preventDefault();
        applyFiles(images, { append: true });
    };

    const clear = () => {
        files = [];
        setFormAttachments([]);
        previews.value.forEach((p) => URL.revokeObjectURL(p.url));
        previews.value = [];
        pasteHint.value = '';
    };

    const removeAt = (index) => {
        files = files.filter((_, i) => i !== index);
        setFormAttachments(files);
        const removed = previews.value[index];
        if (removed) URL.revokeObjectURL(removed.url);
        previews.value = previews.value.filter((_, i) => i !== index);
    };

    onBeforeUnmount(() => {
        previews.value.forEach((p) => URL.revokeObjectURL(p.url));
    });

    return {
        previews,
        pasteHint,
        onFilesChange,
        onPaste,
        clear,
        removeAt,
        formatSize,
        maxFiles: MAX_FILES,
    };
}
