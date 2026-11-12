// Процедурные текстуры на canvas: дерево, штукатурка, пробка, обложки, наклейки пластинок, календарь.
// Все ассеты сцены рисуются кодом, внешних 3D-моделей нет.
import * as THREE from 'three';

export function canvas(w, h) {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    return [c, c.getContext('2d')];
}

export function toTexture(c, { repeat, srgb = true, aniso = 4 } = {}) {
    const t = new THREE.CanvasTexture(c);
    if (srgb) t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = aniso;
    if (repeat) {
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        t.repeat.set(repeat[0], repeat[1]);
    }
    return t;
}

// Детерминированный шум, чтобы стол выглядел одинаково при каждой загрузке.
function rng(seed = 7) {
    let s = seed;
    return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646;
}

export function woodTexture({ base = '#5a3a22', dark = '#3b2414', light = '#7a5233', planks = 1, w = 1024, h = 512, seed = 3 } = {}) {
    const [c, g] = canvas(w, h);
    const r = rng(seed);
    g.fillStyle = base;
    g.fillRect(0, 0, w, h);
    const ph = h / planks;
    for (let p = 0; p < planks; p++) {
        const y0 = p * ph;
        const shift = r() * 0.2 - 0.1;
        g.fillStyle = shift > 0 ? `rgba(255,220,180,${shift * 0.25})` : `rgba(0,0,0,${-shift * 0.5})`;
        g.fillRect(0, y0, w, ph);
        for (let i = 0; i < 70; i++) {
            const y = y0 + r() * ph;
            const amp = 2 + r() * 6;
            const freq = 0.002 + r() * 0.01;
            const phase = r() * 10;
            g.strokeStyle = r() > 0.5 ? dark : light;
            g.globalAlpha = 0.08 + r() * 0.18;
            g.lineWidth = 0.6 + r() * 2.2;
            g.beginPath();
            for (let x = 0; x <= w; x += 8) {
                const yy = y + Math.sin(x * freq + phase) * amp + Math.sin(x * freq * 3.1) * amp * 0.3;
                x === 0 ? g.moveTo(x, yy) : g.lineTo(x, yy);
            }
            g.stroke();
        }
        g.globalAlpha = 1;
        if (planks > 1) {
            g.fillStyle = 'rgba(0,0,0,0.55)';
            g.fillRect(0, y0, w, 2);
            // стыки досок
            const joint = r() * w;
            g.fillRect(joint, y0, 2, ph);
        }
    }
    return c;
}

export function plasterTexture({ base = '#2b2622', w = 512, h = 512, seed = 11 } = {}) {
    const [c, g] = canvas(w, h);
    const r = rng(seed);
    g.fillStyle = base;
    g.fillRect(0, 0, w, h);
    for (let i = 0; i < 9000; i++) {
        const v = r();
        g.fillStyle = v > 0.5 ? `rgba(255,240,220,${r() * 0.035})` : `rgba(0,0,0,${r() * 0.06})`;
        const s = 1 + r() * 3;
        g.fillRect(r() * w, r() * h, s, s);
    }
    return c;
}

export function corkTexture({ w = 512, h = 384, seed = 5 } = {}) {
    const [c, g] = canvas(w, h);
    const r = rng(seed);
    g.fillStyle = '#9b6e43';
    g.fillRect(0, 0, w, h);
    for (let i = 0; i < 6000; i++) {
        const v = r();
        g.fillStyle = v > 0.6 ? `rgba(60,35,15,${0.2 + r() * 0.4})` : `rgba(210,160,110,${0.15 + r() * 0.3})`;
        g.beginPath();
        g.arc(r() * w, r() * h, 0.6 + r() * 2.2, 0, Math.PI * 2);
        g.fill();
    }
    return c;
}

function wrapText(g, text, x, y, maxW, lineH, maxLines = 3) {
    const words = String(text || '').split(/\s+/);
    let line = '';
    let lines = 0;
    for (let i = 0; i < words.length; i++) {
        const test = line ? line + ' ' + words[i] : words[i];
        if (g.measureText(test).width > maxW && line) {
            g.fillText(line, x, y);
            y += lineH;
            line = words[i];
            if (++lines >= maxLines - 1) {
                line = words.slice(i).join(' ');
                while (g.measureText(line + '…').width > maxW && line.length) line = line.slice(0, -1);
                if (i < words.length - 1) line += '…';
                break;
            }
        } else {
            line = test;
        }
    }
    g.fillText(line, x, y);
}

const PALETTES = [
    ['#e0a84e', '#2a1a0e'],
    ['#c0392b', '#f3efe7'],
    ['#1f4e5f', '#f2d398'],
    ['#2d2d2d', '#e0a84e'],
    ['#6b4c8a', '#f8e1c4'],
    ['#3f6b3a', '#f3efe7'],
];

/** Обложка без картинки: цвет, круг, название. */
export function generatedCover(title, i = 0, size = 512) {
    const [c, g] = canvas(size, size);
    const [bg, fg] = PALETTES[i % PALETTES.length];
    g.fillStyle = bg;
    g.fillRect(0, 0, size, size);
    g.strokeStyle = fg;
    g.globalAlpha = 0.35;
    for (let k = 0; k < 10; k++) {
        g.lineWidth = 2;
        g.beginPath();
        g.arc(size * 0.7, size * 0.35, 40 + k * 22, 0, Math.PI * 2);
        g.stroke();
    }
    g.globalAlpha = 1;
    g.fillStyle = fg;
    g.font = `600 ${size * 0.085}px Oswald, sans-serif`;
    g.textBaseline = 'top';
    wrapText(g, String(title || 'notfilmmaker').toUpperCase(), size * 0.08, size * 0.66, size * 0.84, size * 0.1, 3);
    return c;
}

/** Обложка с фото: кадрируем «cover» в квадрат и слегка старим. */
export function coverFromImage(img, size = 512) {
    const [c, g] = canvas(size, size);
    const s = Math.min(img.naturalWidth, img.naturalHeight);
    g.drawImage(img, (img.naturalWidth - s) / 2, (img.naturalHeight - s) / 2, s, s, 0, 0, size, size);
    const grad = g.createLinearGradient(0, 0, size, size);
    grad.addColorStop(0, 'rgba(255,230,190,0.08)');
    grad.addColorStop(1, 'rgba(0,0,0,0.25)');
    g.fillStyle = grad;
    g.fillRect(0, 0, size, size);
    // потёртость по краю конверта
    g.strokeStyle = 'rgba(255,255,255,0.12)';
    g.lineWidth = 6;
    g.strokeRect(3, 3, size - 6, size - 6);
    return c;
}

/** Виниловая пластинка: бороздки и круглая наклейка с обложкой. */
export function vinylTexture(labelSource, size = 512) {
    const [c, g] = canvas(size, size);
    const m = size / 2;
    g.fillStyle = '#060606';
    g.beginPath();
    g.arc(m, m, m, 0, Math.PI * 2);
    g.fill();
    for (let r = m * 0.36; r < m * 0.98; r += 1.6) {
        g.strokeStyle = `rgba(255,255,255,${0.025 + Math.random() * 0.035})`;
        g.lineWidth = 0.7;
        g.beginPath();
        g.arc(m, m, r, 0, Math.PI * 2);
        g.stroke();
    }
    // блик
    const sheen = g.createConicGradient ? g.createConicGradient(0.6, m, m) : null;
    if (sheen) {
        sheen.addColorStop(0, 'rgba(255,255,255,0)');
        sheen.addColorStop(0.08, 'rgba(255,255,255,0.10)');
        sheen.addColorStop(0.16, 'rgba(255,255,255,0)');
        sheen.addColorStop(0.5, 'rgba(255,255,255,0)');
        sheen.addColorStop(0.58, 'rgba(255,255,255,0.10)');
        sheen.addColorStop(0.66, 'rgba(255,255,255,0)');
        g.fillStyle = sheen;
        g.beginPath();
        g.arc(m, m, m * 0.98, 0, Math.PI * 2);
        g.fill();
    }
    g.save();
    g.beginPath();
    g.arc(m, m, m * 0.33, 0, Math.PI * 2);
    g.clip();
    g.drawImage(labelSource, m - m * 0.33, m - m * 0.33, m * 0.66, m * 0.66);
    g.restore();
    g.fillStyle = '#ddd';
    g.beginPath();
    g.arc(m, m, size * 0.012, 0, Math.PI * 2);
    g.fill();
    return c;
}

/** Хлопушка: доска с надписями. */
export function slateTexture(name, w = 512, h = 384) {
    const [c, g] = canvas(w, h);
    g.fillStyle = '#121212';
    g.fillRect(0, 0, w, h);
    g.strokeStyle = 'rgba(255,255,255,0.85)';
    g.lineWidth = 3;
    const rows = [0.3, 0.55, 0.78];
    rows.forEach((y) => {
        g.beginPath();
        g.moveTo(18, h * y);
        g.lineTo(w - 18, h * y);
        g.stroke();
    });
    g.beginPath();
    g.moveTo(w / 3, h * 0.3);
    g.lineTo(w / 3, h * 0.55);
    g.moveTo((w / 3) * 2, h * 0.3);
    g.lineTo((w / 3) * 2, h * 0.55);
    g.stroke();
    g.fillStyle = '#f3efe7';
    g.textBaseline = 'middle';
    g.font = '600 44px Oswald, sans-serif';
    g.fillText(String(name).toUpperCase(), 24, h * 0.15);
    g.font = '500 16px Inter, sans-serif';
    g.fillStyle = 'rgba(243,239,231,0.7)';
    g.fillText('ROLL', 24, h * 0.36);
    g.fillText('SCENE', w / 3 + 14, h * 0.36);
    g.fillText('TAKE', (w / 3) * 2 + 14, h * 0.36);
    g.fillText('DIRECTOR', 24, h * 0.62);
    g.fillText('DATE', 24, h * 0.85);
    g.font = '600 40px Oswald, sans-serif';
    g.fillStyle = '#e0a84e';
    g.fillText('A01', 24, h * 0.47);
    g.fillText('24', w / 3 + 14, h * 0.47);
    g.fillText('3', (w / 3) * 2 + 14, h * 0.47);
    g.fillStyle = '#f3efe7';
    g.font = '500 30px Oswald, sans-serif';
    g.fillText('notfilmmaker', 24, h * 0.7);
    const d = new Date();
    g.fillText(`${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}`, 120, h * 0.89);
    return c;
}

export function stripesTexture(w = 512, h = 64) {
    const [c, g] = canvas(w, h);
    g.fillStyle = '#f3efe7';
    g.fillRect(0, 0, w, h);
    g.fillStyle = '#111';
    for (let x = -h; x < w + h; x += 64) {
        g.beginPath();
        g.moveTo(x, h);
        g.lineTo(x + 32, h);
        g.lineTo(x + 32 + h, 0);
        g.lineTo(x + h, 0);
        g.fill();
    }
    return c;
}

/** Настенный календарь: фото сверху, сетка текущего месяца, отмеченные даты. */
export function calendarTexture({ months, weekdays, photo, marked = [], date = new Date() }, w = 512, h = 704) {
    const [c, g] = canvas(w, h);
    g.fillStyle = '#efe9dd';
    g.fillRect(0, 0, w, h);
    const ph = h * 0.42;
    if (photo) {
        const s = Math.min(photo.naturalWidth / w, photo.naturalHeight / ph);
        const sw = w * s;
        const sh = ph * s;
        g.drawImage(photo, (photo.naturalWidth - sw) / 2, (photo.naturalHeight - sh) / 2, sw, sh, 0, 0, w, ph);
        g.fillStyle = 'rgba(40,25,10,0.15)';
        g.fillRect(0, 0, w, ph);
    } else {
        const grad = g.createLinearGradient(0, 0, w, ph);
        grad.addColorStop(0, '#2a1a0e');
        grad.addColorStop(1, '#e0a84e');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, ph);
        g.fillStyle = 'rgba(243,239,231,0.9)';
        g.font = '600 46px Oswald, sans-serif';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillText('NOTFILMMAKER', w / 2, ph / 2);
        g.textAlign = 'left';
    }
    const y0 = ph + 20;
    g.fillStyle = '#1b1b1e';
    g.font = '600 46px Oswald, "Noto Sans Armenian", sans-serif';
    g.textBaseline = 'top';
    g.fillText(months[date.getMonth()].toUpperCase(), 28, y0);
    g.fillStyle = '#b97f28';
    g.font = '500 30px Oswald, sans-serif';
    g.textAlign = 'right';
    g.fillText(String(date.getFullYear()), w - 28, y0 + 10);
    g.textAlign = 'center';

    const gx = 24;
    const gw = w - 48;
    const cw = gw / 7;
    const gy = y0 + 74;
    g.font = '500 16px Inter, "Noto Sans Armenian", sans-serif';
    weekdays.forEach((d, i) => {
        g.fillStyle = i >= 5 ? '#b5452b' : '#6d6963';
        g.fillText(d, gx + cw * i + cw / 2, gy);
    });
    const first = new Date(date.getFullYear(), date.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const days = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    const ch = 40;
    g.font = '500 22px Inter, sans-serif';
    for (let d = 1; d <= days; d++) {
        const idx = offset + d - 1;
        const cx = gx + cw * (idx % 7) + cw / 2;
        const cy = gy + 34 + Math.floor(idx / 7) * ch;
        const isToday = d === new Date().getDate() && date.getMonth() === new Date().getMonth();
        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        g.fillStyle = idx % 7 >= 5 ? '#b5452b' : '#1b1b1e';
        g.fillText(String(d), cx, cy);
        if (isToday) {
            g.strokeStyle = '#1b1b1e';
            g.lineWidth = 2;
            g.strokeRect(cx - 17, cy - 6, 34, 34);
        }
        if (marked.includes(key)) {
            // обведено красным маркером
            g.strokeStyle = '#d0342c';
            g.lineWidth = 4;
            g.beginPath();
            g.ellipse(cx, cy + 11, 22, 18, -0.2, 0, Math.PI * 1.9);
            g.stroke();
        }
    }
    g.textAlign = 'left';
    return c;
}

/** Картинка «нет сигнала» для выключенного телевизора. */
export function idleScreenTexture(name, w = 512, h = 384) {
    const [c, g] = canvas(w, h);
    const grad = g.createRadialGradient(w / 2, h / 2, 10, w / 2, h / 2, w / 1.4);
    grad.addColorStop(0, '#1d2a2a');
    grad.addColorStop(1, '#050808');
    g.fillStyle = grad;
    g.fillRect(0, 0, w, h);
    g.fillStyle = 'rgba(224,168,78,0.9)';
    g.font = '600 40px Oswald, sans-serif';
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.fillText(String(name).toUpperCase(), w / 2, h / 2 - 10);
    g.font = '500 18px Inter, sans-serif';
    g.fillStyle = 'rgba(243,239,231,0.6)';
    g.fillText('CH 03  ·  ◉ REC', w / 2, h / 2 + 34);
    return c;
}

export function signTexture(text, w = 512, h = 160) {
    const [c, g] = canvas(w, h);
    g.clearRect(0, 0, w, h);
    g.font = '600 92px Oswald, sans-serif';
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    g.shadowColor = '#ff5a3c';
    g.shadowBlur = 28;
    g.fillStyle = '#ffd6c8';
    g.fillText(text, w / 2, h / 2 + 4);
    g.shadowBlur = 10;
    g.fillText(text, w / 2, h / 2 + 4);
    return c;
}

/** Диск номеронабирателя с отверстиями. */
export function dialTexture(size = 256) {
    const [c, g] = canvas(size, size);
    const m = size / 2;
    g.fillStyle = '#e8e2d4';
    g.beginPath();
    g.arc(m, m, m, 0, Math.PI * 2);
    g.fill();
    g.fillStyle = '#1b1b1e';
    g.font = '600 18px Inter, sans-serif';
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    for (let i = 0; i < 10; i++) {
        const a = -Math.PI * 0.35 + i * (Math.PI * 1.45) / 9;
        g.fillStyle = '#7a1d1d';
        g.beginPath();
        g.arc(m + Math.cos(a) * m * 0.72, m + Math.sin(a) * m * 0.72, m * 0.13, 0, Math.PI * 2);
        g.fill();
        g.fillStyle = '#f3efe7';
        g.fillText(String((i + 1) % 10), m + Math.cos(a) * m * 0.72, m + Math.sin(a) * m * 0.72);
    }
    g.fillStyle = '#f3efe7';
    g.beginPath();
    g.arc(m, m, m * 0.4, 0, Math.PI * 2);
    g.fill();
    g.fillStyle = '#7a1d1d';
    g.font = '600 20px Oswald, sans-serif';
    g.fillText('NFM', m, m);
    return c;
}

export function loadImage(url) {
    return new Promise((resolve) => {
        if (!url) return resolve(null);
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.decoding = 'async';
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = url;
    });
}
