/*
 * Импорт архива Instagram (ZIP с постами) прямо из браузера.
 * Архив читается по частям (zip.js), каждый файл уходит в хранилище,
 * затем сервер создаёт работу. Уже импортированные посты пропускаются.
 */
import { resizeImage, uploadFile } from './uploader';

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
const MEDIA = /\.(jpe?g|png|webp|heic|mp4|mov|m4v)$/i;

/** Дата публикации зашита в код поста: id >> 23 — миллисекунды от эпохи Instagram. */
export function shortcodeTime(code) {
    let id = 0n;
    for (const ch of code.slice(0, 11)) {
        const i = ALPHABET.indexOf(ch);
        if (i < 0) return Date.now();
        id = id * 64n + BigInt(i);
    }
    const ms = Number(id >> 23n) + 1314220021721;
    return ms > Date.UTC(2010, 0, 1) && ms < Date.now() + 86400000 ? ms : Date.now();
}

/** Группирует файлы архива по постам. */
export function groupPosts(entries) {
    const posts = new Map();
    for (const entry of entries) {
        if (entry.directory || !MEDIA.test(entry.filename) || /(^|\/)(__MACOSX|\.)/.test(entry.filename)) continue;
        const parts = entry.filename.split('/');
        const base = parts.pop().replace(MEDIA, '');
        const dir = parts[parts.length - 1] ?? '';
        let code;
        let order = 0;
        if (parts.length >= 2 && /^[\w-]{8,}$/.test(dir) && base.includes(dir)) {
            code = dir;
            order = parseInt(base.slice(base.lastIndexOf(dir) + dir.length).replace(/\D/g, '') || '0', 10);
        } else {
            code = base.includes('.com_') ? base.slice(base.indexOf('.com_') + 5) : base;
            const numbered = code.match(/^(.+?)_(\d{1,2})$/);
            if (numbered && numbered[1].length >= 10) {
                code = numbered[1];
                order = parseInt(numbered[2], 10);
            }
        }
        if (!/^[\w-]{5,40}$/.test(code)) continue;
        if (!posts.has(code)) posts.set(code, { code, time: shortcodeTime(code), files: [] });
        posts.get(code).files.push({ entry, order, video: /\.(mp4|mov|m4v)$/i.test(entry.filename) });
    }
    return [...posts.values()]
        .map((p) => ({ ...p, files: p.files.sort((a, b) => a.order - b.order) }))
        .sort((a, b) => b.time - a.time);
}

/** Кадр из видео для обложки. */
function videoPoster(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        const done = (blob) => {
            URL.revokeObjectURL(url);
            resolve(blob ? new File([blob], 'poster.jpg', { type: 'image/jpeg' }) : null);
        };
        video.onloadedmetadata = () => {
            video.currentTime = Math.min(1, (video.duration || 2) / 3);
        };
        video.onseeked = () => {
            const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(done, 'image/jpeg', 0.85);
        };
        video.onerror = () => done(null);
        setTimeout(() => done(null), 15000);
        video.src = url;
    });
}

const MIME = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', heic: 'image/heic', mp4: 'video/mp4', m4v: 'video/mp4', mov: 'video/quicktime' };

async function readFile({ entry }, BlobWriter) {
    const name = entry.filename.split('/').pop();
    const ext = name.split('.').pop().toLowerCase();
    const blob = await entry.getData(new BlobWriter(MIME[ext] ?? 'application/octet-stream'));
    return new File([blob], name.toLowerCase().replace(/[^a-z0-9.]+/g, '-'), { type: MIME[ext] ?? blob.type });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('instagramArchive', () => ({
        state: 'idle', // idle | reading | uploading | done
        total: 0,
        done: 0,
        added: 0,
        skipped: 0,
        current: '',
        errors: [],
        pick() {
            this.$refs.zip.click();
        },
        async run(file) {
            if (!file) return;
            this.$refs.zip.value = '';
            Object.assign(this, { state: 'reading', total: 0, done: 0, added: 0, skipped: 0, current: '', errors: [] });
            const { ZipReader, BlobReader, BlobWriter } = await import('@zip.js/zip.js');
            const reader = new ZipReader(new BlobReader(file));
            try {
                const posts = groupPosts(await reader.getEntries());
                const existing = new Set(await this.$wire.instagramCodes());
                const todo = posts.filter((p) => !existing.has(p.code));
                this.skipped = posts.length - todo.length;
                this.total = todo.length;
                this.state = 'uploading';
                if (!posts.length) this.errors.push(this.$el.dataset.empty);

                for (const post of todo) {
                    this.current = post.code;
                    try {
                        await this.importPost(post, BlobWriter);
                        this.added++;
                    } catch (e) {
                        this.errors.push(`${post.code}: ${e?.message ?? e}`);
                    }
                    this.done++;
                }
            } finally {
                await reader.close();
                this.state = 'done';
                this.current = '';
                this.$wire.$refresh();
            }
        },
        async importPost(post, BlobWriter) {
            const videos = post.files.filter((f) => f.video);
            const kind = post.files.length > 1 ? 'carousel' : videos.length ? 'video' : 'photo';
            const items = [];
            let cover = null;
            for (const f of post.files) {
                let file = await readFile(f, BlobWriter);
                if (f.video) {
                    if (!cover) {
                        const poster = await videoPoster(file);
                        if (poster) cover = await uploadFile(poster, 'covers');
                    }
                    items.push({ type: 'video', path: await uploadFile(file, 'videos') });
                } else {
                    file = await resizeImage(file);
                    items.push({ type: 'image', path: await uploadFile(file, kind === 'photo' ? 'covers' : 'gallery') });
                }
            }
            await this.$wire.importArchivePost(post.code, kind, items, cover, post.time);
        },
    }));
});
