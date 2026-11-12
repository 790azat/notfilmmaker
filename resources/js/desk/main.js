// Альтернативная версия сайта: 3D-рабочий стол. Клики по предметам двигают камеру и открывают разделы.
import * as THREE from 'three';
import { buildWorld } from './world.js';
import * as T from './textures.js';

const $ = (id) => document.getElementById(id);
const data = JSON.parse($('desk-data').textContent);
const i18n = data.i18n;
const site = data.site;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const isTouch = matchMedia('(pointer: coarse)').matches;
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
const quality = { shadows: !isTouch && Math.min(innerWidth, innerHeight) > 700, dpr: Math.min(devicePixelRatio || 1, isTouch ? 1.5 : 2) };

function fail() {
    $('desk-loader').hidden = true;
    $('desk-fallback').hidden = false;
}

let renderer;
try {
    renderer = new THREE.WebGLRenderer({ canvas: $('desk-canvas'), antialias: true, powerPreference: 'high-performance' });
} catch (e) {
    fail();
    throw e;
}

start().catch((e) => {
    console.error(e);
    fail();
});

async function start() {
    renderer.setPixelRatio(quality.dpr);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;
    renderer.shadowMap.enabled = quality.shadows;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    // Шрифты нужны до того, как текстуры с надписями нарисуются на canvas.
    await Promise.race([
        Promise.all(['600 40px Oswald', '500 20px Inter'].map((f) => document.fonts?.load(f))).catch(() => {}),
        new Promise((r) => setTimeout(r, 1500)),
    ]);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, innerWidth / innerHeight, 0.05, 30);
    const world = buildWorld({ scene, data, quality });
    world.disc.userData.key = 'turntable';
    world.clickables.push(world.disc);

    // ── Камера: плавно догоняет желаемую точку ──────────────
    const view = { pos: new THREE.Vector3(0.4, 2.3, 3.6), target: new THREE.Vector3(0, 1.1, -1.6) };
    const desired = { pos: view.pos.clone(), target: view.target.clone() };
    let mode = 'overview';
    let pan = 0;
    const pointer = { x: 0, y: 0 };
    const shift = { x: 0, y: 0, tx: 0, ty: 0 };
    const portrait = () => innerWidth / innerHeight < 1;

    function overview() {
        const aspect = innerWidth / innerHeight;
        camera.fov = portrait() ? 55 : 45;
        const t = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
        const target = new THREE.Vector3(0.15, 1.32, -1.75);
        const d = Math.max(2.35 / 2 / t, (portrait() ? 2.2 : 3.6) / 2 / (t * aspect));
        return { target, pos: target.clone().add(new THREE.Vector3(0, 0.3, d)) };
    }
    const panMax = () => (portrait() ? 0.95 : 0.35);

    function focusView(key) {
        if (key === 'music') {
            return scaleFocus({ target: new THREE.Vector3(0.0, 1.05, -1.6), pos: new THREE.Vector3(0.05, 1.42, 0.2) });
        }
        const f = world.objects[key]?.focus;
        return f ? scaleFocus(f) : null;
    }
    function scaleFocus(f) {
        const aspect = innerWidth / innerHeight;
        const k = aspect < 1 ? THREE.MathUtils.clamp(1 / (aspect * 1.35), 1, 1.9) : 1;
        return { target: f.target.clone(), pos: f.target.clone().add(f.pos.clone().sub(f.target).multiplyScalar(k)) };
    }

    function setMode(next) {
        mode = next;
        $('desk-back').hidden = next === 'overview';
        $('desk-dock').classList.toggle('is-hidden', next !== 'overview' && portrait());
        if (next === 'overview') closePanel(true);
    }

    function resize() {
        camera.aspect = innerWidth / innerHeight;
        renderer.setSize(innerWidth, innerHeight, false);
        updateShiftTarget();
        camera.updateProjectionMatrix();
    }
    addEventListener('resize', resize);
    resize();

    // ── Загрузка картинок ───────────────────────────────────
    const jobs = [];
    world.albums.forEach((a) => {
        if (!a.album.cover) return;
        jobs.push(
            T.loadImage(a.album.cover).then((img) => {
                if (!img) return;
                const c = T.coverFromImage(img);
                a.coverTex.image = c;
                a.coverTex.needsUpdate = true;
                a.vinylTex.image = T.vinylTexture(c);
                a.vinylTex.needsUpdate = true;
                a.coverCanvas = c;
            }),
        );
    });
    data.photos.slice(0, world.polaroids.length).forEach((p, i) => {
        jobs.push(
            T.loadImage(p.cover).then((img) => {
                if (!img) return;
                const tex = T.toTexture(T.coverFromImage(img, 256));
                world.polaroids[i].map = tex;
                world.polaroids[i].color.set('#ffffff');
                world.polaroids[i].needsUpdate = true;
            }),
        );
    });
    jobs.push(
        T.loadImage(site.portrait || data.photos[0]?.cover).then((img) => {
            world.calState.photo = img;
            world.redrawCalendar();
        }),
    );
    let done = 0;
    const bar = $('desk-progress');
    jobs.forEach((j) => j.then(() => (bar.style.width = `${Math.round((++done / jobs.length) * 100)}%`)));
    await Promise.race([Promise.all(jobs), new Promise((r) => setTimeout(r, 8000))]);
    bar.style.width = '100%';

    // прогреваем шейдеры, чтобы первый клик не подвисал
    renderer.compile(scene, camera);

    const enter = $('desk-enter');
    $('desk-loader-text').hidden = true;
    enter.hidden = false;
    enter.focus();
    enter.addEventListener('click', () => {
        $('desk-loader').classList.add('is-done');
        Object.assign(desired, overview());
        showHint(isTouch ? i18n.hint_touch : i18n.hint, 5000);
    });

    // ── Подсказки и док ─────────────────────────────────────
    let hintTimer;
    function showHint(text, ms = 3500) {
        const h = $('desk-hint');
        h.textContent = text;
        h.classList.remove('is-hidden');
        clearTimeout(hintTimer);
        hintTimer = setTimeout(() => h.classList.add('is-hidden'), ms);
    }

    const dockItems = [
        ['records', '💿'],
        ['tv', '📺'],
        ['calendar', '📅'],
        ['camera', '🎥'],
        ['phone', '☎️'],
        ['clapper', '🎬'],
        ['board', '🖼️'],
        ['lamp', '💡'],
        ['sign', '🚪'],
    ];
    const dock = $('desk-dock');
    dockItems.forEach(([key, icon]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = icon;
        b.title = i18n.objects[key];
        b.setAttribute('aria-label', i18n.objects[key]);
        b.addEventListener('click', () => activate(key));
        dock.appendChild(b);
    });
    $('desk-back').addEventListener('click', () => back());
    addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (!$('desk-screen').hidden) closeScreen();
            else back();
        }
    });

    function back() {
        setMode('overview');
        Object.assign(desired, overview());
        world.handset.position.y = 0.13;
        world.handset.rotation.set(0, 0, 0);
    }

    // ── Боковая панель ──────────────────────────────────────
    const panel = $('desk-panel');
    let panelKey = null;
    function openPanel(key, html) {
        panelKey = key;
        panel.innerHTML = `<button type="button" class="desk-btn desk-btn--ghost desk-panel__close" data-close>✕</button>${html}`;
        panel.hidden = false;
        panel.scrollTop = 0;
        $('desk-dock').classList.add('is-hidden');
        panel.querySelector('[data-close]').addEventListener('click', () => back());
        updateShiftTarget();
    }
    function closePanel() {
        panelKey = null;
        panel.hidden = true;
        panel.innerHTML = '';
        $('desk-dock').classList.toggle('is-hidden', mode !== 'overview' && portrait());
        updateShiftTarget();
    }
    function updateShiftTarget() {
        // Сдвигаем «центр» кадра, чтобы предмет не прятался под панелью.
        const panel = $('desk-panel');
        if (panel.hidden) {
            shift.tx = shift.ty = 0;
        } else if (innerWidth <= 720) {
            shift.tx = 0;
            shift.ty = Math.min(innerHeight * 0.72, panel.offsetHeight || innerHeight * 0.6) / 2;
        } else {
            shift.tx = Math.min(440, innerWidth) / 2;
            shift.ty = 0;
        }
    }

    const btn = (href, label, primary = false, ext = false) =>
        `<a class="desk-btn ${primary ? 'desk-btn--primary' : ''}" href="${esc(href)}"${ext ? ' target="_blank" rel="noopener"' : ''}>${esc(label)}</a>`;

    function aboutHtml() {
        return `${site.portrait ? `<img class="desk-portrait" src="${esc(site.portrait)}" alt="">` : ''}
            <p class="desk-kicker">${esc(i18n.about.title)}</p>
            <h2>${esc(site.realName || site.name)}</h2>
            <p>${esc(site.role)}</p>
            ${site.about ? `<p>${esc(site.about)}</p>` : ''}
            <div class="desk-actions">${btn(site.urls.about, i18n.about.more + ' ↗', true)}${btn(site.urls.works, i18n.works.title)}</div>`;
    }

    function phoneHtml() {
        const rows = [];
        if (site.email) rows.push(`<li><a href="mailto:${esc(site.email)}">${esc(site.email)} <small>Email</small></a></li>`);
        if (site.phone) rows.push(`<li><a href="tel:${esc(String(site.phone).replace(/[^+\d]/g, ''))}">${esc(site.phone)} <small>Tel</small></a></li>`);
        Object.entries(site.socials || {}).forEach(([n, u]) => rows.push(`<li><a href="${esc(u)}" target="_blank" rel="noopener">${esc(n)} <small>↗</small></a></li>`));
        rows.push(`<li><a href="${esc(site.urls.contact)}">${esc(i18n.phone.form)} <small>→</small></a></li>`);
        return `<p class="desk-kicker">${esc(site.location)}</p><h2>${esc(i18n.phone.title)}</h2>
            <ul class="desk-list">${rows.join('')}</ul>
            <div class="desk-actions"><button type="button" class="desk-btn desk-btn--primary" data-book>📅 ${esc(i18n.book.title)}</button></div>`;
    }

    function gridHtml(items) {
        return `<div class="desk-grid">${items
            .map((w) => `<a href="${esc(w.url)}">${w.cover ? `<img src="${esc(w.cover)}" alt="" loading="lazy">` : ''}<span>${esc(w.title)}</span></a>`)
            .join('')}</div>`;
    }

    function worksHtml() {
        const seen = new Set();
        const items = [...data.albums, ...data.photos].filter((w) => !seen.has(w.id) && seen.add(w.id));
        return `<p class="desk-kicker">${esc(i18n.works.count.replace(':count', site.worksCount))}</p><h2>${esc(i18n.works.title)}</h2>
            ${gridHtml(items)}
            <div class="desk-actions">${btn(site.urls.works, i18n.works.open + ' ↗', true)}</div>`;
    }

    function boardHtml() {
        return `<p class="desk-kicker">${esc(site.name)}</p><h2>${esc(i18n.board.title)}</h2>${gridHtml(data.photos)}
            <div class="desk-actions">${btn(site.urls.works, i18n.works.open + ' ↗')}</div>`;
    }

    // ── Календарь записи ────────────────────────────────────
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const book = { y: today.getFullYear(), m: today.getMonth(), date: null, kind: 'shoot' };
    const iso = (y, m, d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

    function calendarHtml() {
        const first = new Date(book.y, book.m, 1);
        const offset = (first.getDay() + 6) % 7;
        const days = new Date(book.y, book.m + 1, 0).getDate();
        const canPrev = book.y > today.getFullYear() || book.m > today.getMonth();
        let cells = i18n.book.weekdays.map((w) => `<span class="desk-cal__wd">${esc(w)}</span>`).join('');
        cells += '<span></span>'.repeat(offset);
        for (let d = 1; d <= days; d++) {
            const date = new Date(book.y, book.m, d);
            const key = iso(book.y, book.m, d);
            const cls = ['desk-cal__day', +date === +today ? 'is-today' : '', book.date === key ? 'is-selected' : ''].join(' ');
            cells += `<button type="button" class="${cls}" data-day="${key}" ${date < today ? 'disabled' : ''}>${d}</button>`;
        }
        const kinds = Object.entries(i18n.book.kinds)
            .map(([k, label]) => `<button type="button" class="desk-btn ${book.kind === k ? 'is-active' : ''}" data-kind="${k}">${esc(label)}</button>`)
            .join('');
        return `<p class="desk-kicker">${esc(site.name)}</p><h2>${esc(i18n.book.title)}</h2><p>${esc(i18n.book.subtitle)}</p>
            <div class="desk-cal">
                <div class="desk-cal__head">
                    <button type="button" class="desk-icon-btn" data-month="-1" ${canPrev ? '' : 'disabled style="opacity:.25"'} aria-label="‹">‹</button>
                    <strong>${esc(i18n.book.months[book.m])} ${book.y}</strong>
                    <button type="button" class="desk-icon-btn" data-month="1" aria-label="›">›</button>
                </div>
                <div class="desk-cal__grid">${cells}</div>
            </div>
            <form class="desk-form" novalidate>
                <label>${esc(i18n.book.kind)}<div class="desk-chips">${kinds}</div></label>
                <label>${esc(i18n.book.name)}<input class="desk-input" name="name" autocomplete="name" required maxlength="120"></label>
                <label>${esc(i18n.book.contact)}<input class="desk-input" name="contact" autocomplete="email" required maxlength="190"></label>
                <label>${esc(i18n.book.note)}<textarea class="desk-input" name="note" maxlength="2000"></textarea></label>
                <input type="text" name="website" tabindex="-1" autocomplete="off" hidden>
                <p class="desk-msg desk-msg--error" data-error hidden></p>
                <button type="submit" class="desk-btn desk-btn--primary" data-submit>${esc(book.date ? `${i18n.book.send} · ${book.date.split('-').reverse().join('.')}` : i18n.book.pick_date)}</button>
            </form>`;
    }

    function openBooking() {
        const form = panel.querySelector('form');
        const keep = form ? Object.fromEntries(new FormData(form)) : {};
        openPanel('calendar', calendarHtml());
        const f = panel.querySelector('form');
        Object.entries(keep).forEach(([k, v]) => f.elements[k] && (f.elements[k].value = v));
        panel.querySelectorAll('[data-day]').forEach((b) =>
            b.addEventListener('click', () => {
                book.date = b.dataset.day;
                openBooking();
            }),
        );
        panel.querySelectorAll('[data-month]').forEach((b) =>
            b.addEventListener('click', () => {
                const d = new Date(book.y, book.m + Number(b.dataset.month), 1);
                book.y = d.getFullYear();
                book.m = d.getMonth();
                openBooking();
            }),
        );
        panel.querySelectorAll('[data-kind]').forEach((b) =>
            b.addEventListener('click', () => {
                book.kind = b.dataset.kind;
                openBooking();
            }),
        );
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = f.querySelector('[data-error]');
            const submit = f.querySelector('[data-submit]');
            if (!book.date) {
                err.textContent = i18n.book.pick_date;
                err.hidden = false;
                return;
            }
            if (!f.elements.name.value.trim() || !f.elements.contact.value.trim()) {
                err.textContent = i18n.book.error;
                err.hidden = false;
                return;
            }
            submit.disabled = true;
            submit.textContent = i18n.book.sending;
            try {
                const res = await fetch(site.urls.book, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ date: book.date, kind: book.kind, ...Object.fromEntries(new FormData(f)) }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.ok) throw new Error(json.message || i18n.book.error);
                const [y, m] = book.date.split('-').map(Number);
                world.calState.marked.push(book.date);
                world.calState.date = new Date(y, m - 1, 1);
                world.redrawCalendar();
                const pretty = book.date.split('-').reverse().join('.');
                openPanel('calendar', `<div class="desk-success"><span class="desk-success__icon">✓</span><h2>${esc(i18n.book.title)}</h2><p>${esc(i18n.book.sent.replace(':date', pretty))}</p></div>`);
                book.date = null;
            } catch (ex) {
                err.textContent = ex.message || i18n.book.error;
                err.hidden = false;
                submit.disabled = false;
                submit.textContent = i18n.book.send;
            }
        });
    }

    // ── Проигрыватель и телевизор ───────────────────────────
    const video = document.createElement('video');
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.preload = 'auto';
    video.crossOrigin = 'anonymous';
    const videoTex = new THREE.VideoTexture(video);
    videoTex.colorSpace = THREE.SRGBColorSpace;
    const sm = world.screenMat.uniforms;
    let current = -1;
    let busy = false;

    video.addEventListener('loadedmetadata', () => (sm.mapAspect.value = video.videoWidth / video.videoHeight || 16 / 9));
    video.addEventListener('ended', () => next(true));
    video.addEventListener('play', renderPlayer);
    video.addEventListener('pause', renderPlayer);
    video.addEventListener('error', () => {
        // чужой домен без CORS: на ТВ остаётся обложка, а видео открываем поверх
        if (current < 0 || !video.getAttribute('src')) return;
        const a = world.albums[current];
        setScreenMap(a.coverTex, 1);
        openScreen(a.album, true);
    });

    function setScreenMap(tex, aspect) {
        sm.map.value = tex;
        sm.mapAspect.value = aspect;
    }

    async function powerTv(on) {
        const from = sm.power.value;
        await tween(on ? 0.55 : 0.35, (k) => {
            sm.power.value = from + ((on ? 1 : 0.02) - from) * k;
            sm.noise.value = on ? 0.6 * (1 - k) + 0.04 : 0.06;
        });
    }

    const stage = new THREE.Vector3();
    async function playAlbum(i, auto = false) {
        const a = world.albums[i];
        if (!a || busy) return;
        if (a.album.placeholder) {
            showHint(i18n.player.empty);
            return;
        }
        busy = true;
        // следующая пластинка сама по себе не уводит камеру, если человек смотрит другой предмет
        if (!auto || ['overview', 'music', 'tv', 'turntable', 'records'].includes(mode)) {
            closePanel();
            setMode('music');
            Object.assign(desired, focusView('music'));
        }
        stopVideo();
        const disc = world.disc;

        // старая пластинка возвращается на полку
        if (disc.visible && current >= 0 && current !== i) {
            world.state.armTarget = 0.15;
            const old = world.albums[current];
            const from = disc.position.clone();
            const to = old.group.localToWorld(stage.set(0, 0.155, 0.02)).clone();
            await tween(0.7, (k) => {
                disc.position.lerpVectors(from, to, k).y += Math.sin(k * Math.PI) * 0.25;
                disc.rotation.x = (Math.PI / 2) * k;
                disc.scale.setScalar(1 - k * 0.9);
            });
            disc.visible = false;
        }
        current = i;
        renderPlayer();

        // конверт выдвигается, пластинка летит на диск
        const g = a.group;
        const home = a.home.clone();
        await tween(0.35, (k) => (g.position.z = home.z + 0.12 * k));
        disc.material[1].map = a.vinylTex;
        disc.material[1].needsUpdate = true;
        disc.userData.onPlatter = false;
        disc.scale.setScalar(1);
        const from = g.localToWorld(stage.set(0, 0.155, 0.03)).clone();
        const to = world.tt.localToWorld(stage.set(-0.06, 0.097, 0)).clone();
        disc.position.copy(from);
        disc.rotation.set(Math.PI / 2, 0, 0);
        disc.visible = true;
        tween(0.45, (k) => (g.position.z = home.z + 0.12 * (1 - k)));
        await tween(reducedMotion ? 0.3 : 1.1, (k) => {
            disc.position.lerpVectors(from, to, k);
            disc.position.y += Math.sin(k * Math.PI) * 0.35;
            disc.rotation.x = (Math.PI / 2) * (1 - k);
            disc.rotation.z = k * Math.PI * 2;
        });
        disc.rotation.set(0, 0, 0);
        disc.userData.onPlatter = true;
        world.state.armTarget = -0.6;
        world.state.playing = true;
        await wait(450);
        await startVideo(a);
        busy = false;
    }

    async function startVideo(a) {
        powerTv(true);
        if (a.album.video) {
            setScreenMap(videoTex, sm.mapAspect.value);
            video.src = a.album.video;
            video.muted = false;
            try {
                await video.play();
            } catch {
                video.muted = true;
                video.play().catch(() => {});
            }
        } else {
            // YouTube/Instagram нельзя натянуть на экран: обложка на ТВ, плеер поверх.
            setScreenMap(a.coverTex, 1);
            openScreen(a.album, false);
        }
        renderPlayer();
    }

    function stopVideo() {
        video.pause();
        video.removeAttribute('src');
        video.load();
        world.state.playing = false;
        world.state.armTarget = 0.15;
        setScreenMap(world.idle, 4 / 3);
        renderPlayer();
    }

    function stopAll() {
        stopVideo();
        closeScreen();
        powerTv(true);
        current = -1;
        world.disc.visible = false;
        renderPlayer();
    }

    function next(auto = false) {
        if (!world.albums.length || world.albums[0].album.placeholder) return;
        const real = world.albums.filter((a) => !a.album.placeholder);
        playAlbum((current + 1) % real.length, auto);
    }

    function togglePause() {
        if (!video.getAttribute('src')) return;
        if (video.paused) {
            video.play();
            world.state.playing = true;
            world.state.armTarget = -0.6;
        } else {
            video.pause();
            world.state.playing = false;
            world.state.armTarget = 0.15;
        }
    }

    const player = $('desk-player');
    function renderPlayer() {
        if (current < 0) {
            player.hidden = true;
            return;
        }
        const a = world.albums[current].album;
        const paused = video.paused;
        player.hidden = false;
        player.innerHTML = `${a.cover ? `<img src="${esc(a.cover)}" alt="">` : ''}
            <div class="desk-player__meta"><p class="desk-player__kicker">${esc(i18n.player.now_playing)}</p><p class="desk-player__title">${esc(a.title)}</p></div>
            <div class="desk-player__actions">
                ${a.video ? `<button type="button" class="desk-icon-btn" data-act="pause" aria-label="${esc(paused ? i18n.player.play : i18n.player.pause)}">${paused ? '▶' : '❚❚'}</button>` : ''}
                <button type="button" class="desk-icon-btn" data-act="next" aria-label="${esc(i18n.player.next)}">⏭</button>
                <button type="button" class="desk-icon-btn" data-act="full" aria-label="${esc(i18n.player.fullscreen)}">⛶</button>
                <a class="desk-icon-btn" href="${esc(a.url)}" aria-label="${esc(i18n.player.open)}">↗</a>
                <button type="button" class="desk-icon-btn" data-act="stop" aria-label="${esc(i18n.player.stop)}">✕</button>
            </div>`;
        player.querySelectorAll('[data-act]').forEach((b) =>
            b.addEventListener('click', () => {
                const act = b.dataset.act;
                if (act === 'pause') togglePause();
                if (act === 'next') next();
                if (act === 'full') openScreen(a, !a.video ? false : true);
                if (act === 'stop') stopAll();
            }),
        );
    }

    // Видео на весь экран: переносим тот же <video> в оверлей, текстура продолжает работать.
    const screenEl = $('desk-screen');
    function openScreen(album, useVideo) {
        screenEl.innerHTML = `<button type="button" class="desk-btn desk-btn--ghost">✕ ${esc(i18n.back)}</button>`;
        screenEl.querySelector('button').addEventListener('click', closeScreen);
        if (useVideo && album.video) {
            if (video.error) {
                const v = document.createElement('video');
                v.src = album.video;
                v.controls = v.autoplay = v.playsInline = true;
                screenEl.appendChild(v);
            } else {
                video.controls = true;
                screenEl.appendChild(video);
            }
        } else if (album.embed) {
            const f = document.createElement('iframe');
            f.src = album.embed;
            f.allow = 'autoplay; fullscreen; encrypted-media; picture-in-picture';
            f.allowFullscreen = true;
            if (/instagram\.com/.test(album.embed)) f.className = 'is-vertical';
            screenEl.appendChild(f);
        }
        screenEl.hidden = false;
    }
    function closeScreen() {
        if (screenEl.hidden) return;
        video.controls = false;
        if (video.parentNode === screenEl) screenEl.removeChild(video);
        screenEl.querySelectorAll('iframe, video').forEach((el) => el.remove());
        screenEl.hidden = true;
    }

    // ── Что делает каждый предмет ───────────────────────────
    async function activate(key, extra = {}) {
        if (key === 'album') return playAlbum(extra.album);
        if (key === 'sign') {
            location.href = site.urls.home;
            return;
        }
        if (key === 'lamp') {
            world.setLamp(!world.lampOn);
            return;
        }
        if (key === 'tv') {
            if (current >= 0 && mode === 'tv') return openScreen(world.albums[current].album, !!world.albums[current].album.video);
            focus('tv');
            if (current < 0) showHint(i18n.player.pick);
            return;
        }
        if (key === 'turntable') {
            if (current >= 0 && (mode === 'turntable' || mode === 'music')) return togglePause();
            focus('turntable');
            if (current < 0) showHint(i18n.player.pick);
            return;
        }
        if (key === 'records') {
            focus('records');
            showHint(data.albums.length ? i18n.player.pick : i18n.player.empty);
            return;
        }
        focus(key);
        if (key === 'calendar') openBooking();
        if (key === 'camera') openPanel('camera', aboutHtml());
        if (key === 'board') openPanel('board', boardHtml());
        if (key === 'phone') {
            tween(0.5, (k) => {
                world.handset.position.y = 0.13 + Math.sin(k * Math.PI * 0.5) * 0.08;
                world.handset.rotation.z = k * 0.35;
            });
            openPanel('phone', phoneHtml());
            panel.querySelector('[data-book]')?.addEventListener('click', () => activate('calendar'));
        }
        if (key === 'clapper') {
            const h = world.stickHinge;
            await tween(0.12, (k) => (h.rotation.z = world.clapOpen * (1 - k)), (k) => k * k);
            await wait(120);
            tween(0.4, (k) => (h.rotation.z = world.clapOpen * k));
            openPanel('clapper', worksHtml());
        }
    }

    function focus(key) {
        const f = focusView(key);
        if (!f) return;
        setMode(key);
        Object.assign(desired, f);
    }

    // ── Указатель: наведение, клик, перетаскивание ─────────
    const raycaster = new THREE.Raycaster();
    const ndc = new THREE.Vector2();
    const tooltip = $('desk-tooltip');
    const canvasEl = renderer.domElement;
    let hovered = null;
    let down = null;

    function pick(e) {
        ndc.set((e.clientX / innerWidth) * 2 - 1, -(e.clientY / innerHeight) * 2 + 1);
        raycaster.setFromCamera(ndc, camera);
        const hit = raycaster.intersectObjects(world.clickables, true).find((h) => h.object.userData.key);
        if (!hit) return null;
        let g = hit.object;
        while (g.parent && g.parent.userData.key === g.userData.key) g = g.parent;
        return { key: hit.object.userData.key, album: hit.object.userData.album, group: g };
    }

    canvasEl.addEventListener('pointerdown', (e) => {
        down = { x: e.clientX, y: e.clientY, pan, moved: false };
        canvasEl.setPointerCapture?.(e.pointerId);
    });
    canvasEl.addEventListener('pointermove', (e) => {
        pointer.x = (e.clientX / innerWidth) * 2 - 1;
        pointer.y = (e.clientY / innerHeight) * 2 - 1;
        if (down) {
            const dx = e.clientX - down.x;
            if (Math.abs(dx) + Math.abs(e.clientY - down.y) > 8) down.moved = true;
            if (down.moved && mode === 'overview') {
                pan = THREE.MathUtils.clamp(down.pan - (dx / innerWidth) * 2.4, -panMax(), panMax());
            }
        }
        if (e.pointerType === 'mouse') {
            const p = pick(e);
            hovered = p;
            canvasEl.style.cursor = p ? 'pointer' : down?.moved ? 'grabbing' : 'default';
            if (p) {
                const label = p.key === 'album' ? world.albums[p.album]?.album.title : i18n.objects[p.key];
                tooltip.textContent = label || '';
                tooltip.style.left = `${e.clientX}px`;
                tooltip.style.top = `${e.clientY}px`;
                tooltip.hidden = !label;
            } else {
                tooltip.hidden = true;
            }
        }
    });
    canvasEl.addEventListener('pointerup', (e) => {
        const wasMoved = down?.moved;
        down = null;
        if (wasMoved) return;
        const p = pick(e);
        if (p) {
            tooltip.hidden = true;
            activate(p.key, { album: p.album });
        } else if (mode !== 'overview') {
            back();
        }
    });
    canvasEl.addEventListener('pointerleave', () => {
        hovered = null;
        tooltip.hidden = true;
    });

    // ── Твины и цикл отрисовки ──────────────────────────────
    const tweens = [];
    const easeInOut = (k) => (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
    function tween(dur, fn, ease = easeInOut) {
        return new Promise((resolve) => tweens.push({ t: 0, dur, fn, ease, resolve }));
    }
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));

    const clock = new THREE.Clock();
    const tmp = new THREE.Vector3();
    renderer.setAnimationLoop(() => {
        const dt = Math.min(clock.getDelta(), 0.05);
        const t = clock.elapsedTime;

        for (let i = tweens.length - 1; i >= 0; i--) {
            const tw = tweens[i];
            tw.t += dt;
            const k = Math.min(1, tw.t / tw.dur);
            tw.fn(tw.ease(k));
            if (k >= 1) {
                tweens.splice(i, 1);
                tw.resolve();
            }
        }
        world.tick.forEach((f) => f(dt, t));

        // наведение: предмет чуть приподнимается, конверт выезжает с полки
        world.clickables.forEach((g) => {
            const on = hovered && hovered.group === g ? 1 : 0;
            const h = (g.userData.h ?? 0) + (on - (g.userData.h ?? 0)) * Math.min(1, dt * 10);
            g.userData.h = h;
            if (g.userData.key === 'album') {
                if (!busy || world.albums[g.userData.album] !== world.albums[current]) {
                    const a = world.albums[g.userData.album];
                    g.position.z = a.home.z + h * 0.05;
                }
            } else if (!['records', 'board', 'calendar', 'sign'].includes(g.userData.key) && g.type === 'Group') {
                g.scale.setScalar(1 + h * 0.04);
            }
        });

        if (mode === 'overview' && !$('desk-loader').classList.contains('is-done')) {
            // до входа камера медленно облетает стол
            desired.pos.set(Math.sin(t * 0.15) * 0.6 + 0.3, 2.0, 3.0);
            desired.target.set(0, 1.15, -1.6);
        } else if (mode === 'overview') {
            const o = overview();
            desired.target.copy(o.target).add(tmp.set(pan, 0, 0));
            desired.pos.copy(o.pos).add(tmp.set(pan, 0, 0));
        }
        const par = isTouch || reducedMotion ? 0 : 1;
        const damp = 1 - Math.exp(-dt * (reducedMotion ? 8 : 3.2));
        view.pos.lerp(tmp.copy(desired.pos).add(new THREE.Vector3(pointer.x * 0.07 * par, -pointer.y * 0.04 * par, 0)), damp);
        view.target.lerp(desired.target, damp);
        camera.position.copy(view.pos);
        camera.lookAt(view.target);

        shift.x += (shift.tx - shift.x) * Math.min(1, dt * 6);
        shift.y += (shift.ty - shift.y) * Math.min(1, dt * 6);
        if (Math.abs(shift.x) + Math.abs(shift.y) > 0.5) camera.setViewOffset(innerWidth, innerHeight, shift.x, shift.y, innerWidth, innerHeight);
        else camera.clearViewOffset();
        camera.updateProjectionMatrix();

        renderer.render(scene, camera);
    });

    void panelKey;
    window.__desk = { activate, back, playAlbum };
}
