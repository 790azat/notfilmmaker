/*
 * Загрузка файлов в админке.
 * На Vercel файлы идут напрямую из браузера в Vercel Blob (без лимита 4,5 МБ на запрос),
 * локально — обычным POST на /admin/upload. Картинки перед загрузкой уменьшаются до 2560 px.
 */

const MAX_SIDE = 2560;

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function resizeImage(file) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif' || file.type === 'image/svg+xml') {
        return file;
    }
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 2.5 * 1024 * 1024) {
            return file;
        }
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
        if (!blob) return file;
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

function safeName(name) {
    const dot = name.lastIndexOf('.');
    const ext = dot > 0 ? name.slice(dot + 1).toLowerCase() : 'bin';
    const base = (dot > 0 ? name.slice(0, dot) : name)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40) || 'file';
    return `${base}.${ext}`;
}

function uploadLocal(file, folder, onProgress) {
    return new Promise((resolve, reject) => {
        const form = new FormData();
        form.append('file', file);
        form.append('folder', folder);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/upload');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText).path);
            } else {
                let message = `HTTP ${xhr.status}`;
                try { message = JSON.parse(xhr.responseText).message || message; } catch {}
                reject(new Error(message));
            }
        };
        xhr.onerror = () => reject(new Error('Network error'));
        xhr.send(form);
    });
}

async function uploadBlob(file, folder, onProgress) {
    const { upload } = await import('@vercel/blob/client');
    const result = await upload(`${folder}/${safeName(file.name)}`, file, {
        access: 'public',
        handleUploadUrl: '/admin/blob-token',
        headers: { 'X-CSRF-TOKEN': csrf() },
        multipart: file.size > 8 * 1024 * 1024,
        onUploadProgress: ({ percentage }) => onProgress(Math.round(percentage)),
    });
    return result.url;
}

/** Загрузка одного файла: в Vercel Blob или локально. Возвращает путь/ссылку. */
export function uploadFile(file, folder, onProgress = () => {}) {
    return window.__portfolio?.blob ? uploadBlob(file, folder, onProgress) : uploadLocal(file, folder, onProgress);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('uploader', (options = {}) => ({
        folder: options.folder ?? 'uploads',
        method: options.method,
        uploads: [],
        dragging: false,
        get busy() {
            return this.uploads.some((u) => u.status === 'uploading');
        },
        pick() {
            this.$refs.input.click();
        },
        drop(event) {
            this.dragging = false;
            this.handle(event.dataTransfer.files);
        },
        async handle(fileList) {
            const files = Array.from(fileList ?? []);
            if (this.$refs.input) this.$refs.input.value = '';
            for (const original of files) {
                const item = { name: original.name, progress: 0, status: 'uploading', error: null };
                this.uploads.push(item);
                const entry = this.uploads[this.uploads.length - 1];
                try {
                    const file = await resizeImage(original);
                    const onProgress = (p) => (entry.progress = p);
                    const path = await uploadFile(file, this.folder, onProgress);
                    entry.progress = 100;
                    await this.$wire.call(this.method, path, file.type || '');
                    entry.status = 'done';
                    setTimeout(() => (this.uploads = this.uploads.filter((u) => u !== entry)), 1500);
                } catch (e) {
                    entry.status = 'error';
                    entry.error = e?.message ?? 'Upload failed';
                }
            }
        },
    }));
});
