// Живой чат на сайте (resources/views/partials/chat.blade.php): сообщение уходит владельцу в Telegram,
// его reply приходит сюда. Vercel не держит WebSocket, поэтому окно опрашивает /chat/poll:
// часто, пока чат открыт, редко, пока закрыт, и перестаёт после получаса тишины.
// id разговора (sid) хранится в localStorage, история лежит в базе.

function store(key, value) {
    try {
        if (value === undefined) return localStorage.getItem(key);
        localStorage.setItem(key, value);
    } catch (e) {
        return null;
    }
}

function init() {
    const root = document.getElementById('site-chat');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';

    const T = JSON.parse(root.dataset.t);
    const fab = root.querySelector('[data-chat-toggle]');
    const box = root.querySelector('[data-chat-box]');
    const list = root.querySelector('[data-chat-list]');
    const form = root.querySelector('[data-chat-form]');
    const input = form.querySelector('textarea');
    const sendBtn = form.querySelector('button');
    const dot = root.querySelector('[data-chat-dot]');
    const live = root.querySelector('[data-chat-live]');
    const sub = root.querySelector('[data-chat-sub]');
    const greeting = list.firstElementChild;
    const phone = window.matchMedia('(max-width: 639px)');

    let sid = store('chat_sid');
    let seen = Number(store('chat_seen')) || 0;
    let msgs = [];
    let n = 0;
    let open = false;
    let timer = null;
    let lastActive = Date.now();
    let ready = false;
    let blink = null;
    let audio = null;
    const baseTitle = () => document.title.replace(/^💬 .*? · /, '');

    const replies = () => msgs.filter((m) => m.f === 'a').length;

    function render() {
        list.replaceChildren(greeting);
        for (const m of msgs) {
            const d = document.createElement('div');
            d.className = 'chat-msg ' + (m.f === 'v' ? 'chat-out' : 'chat-in');
            d.textContent = m.t;
            list.appendChild(d);
        }
        const answered = replies() > 0;
        live.classList.toggle('hidden', !answered);
        if (answered) sub.textContent = sub.dataset.online;
        list.scrollTop = list.scrollHeight;
    }

    function note(text) {
        const d = document.createElement('div');
        d.className = 'chat-note';
        d.textContent = text;
        list.appendChild(d);
        list.scrollTop = list.scrollHeight;
    }

    function markSeen() {
        seen = replies();
        store('chat_seen', String(seen));
        updateDot();
    }

    function updateDot() {
        dot.classList.toggle('hidden', open || replies() <= seen);
    }

    // Новый ответ: короткий звук и мигающий заголовок вкладки, пока посетитель не посмотрит
    function beep() {
        try {
            const C = window.AudioContext || window.webkitAudioContext;
            if (!C) return;
            audio = audio || new C();
            if (audio.state === 'suspended') audio.resume();
            [[880, 0], [1320, 0.12]].forEach(([freq, at]) => {
                const o = audio.createOscillator();
                const g = audio.createGain();
                const t = audio.currentTime + at;
                o.frequency.value = freq;
                o.connect(g);
                g.connect(audio.destination);
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(0.2, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.25);
                o.start(t);
                o.stop(t + 0.3);
            });
        } catch (e) {}
    }

    function stopBlink() {
        if (!blink) return;
        clearInterval(blink);
        blink = null;
        document.title = baseTitle();
    }

    function startBlink() {
        if (blink) return;
        let on = false;
        blink = setInterval(() => {
            on = !on;
            document.title = on ? `💬 ${T.new} · ${baseTitle()}` : baseTitle();
        }, 1000);
    }

    function add(j) {
        if (!j || !(j.n > n)) return;
        const fresh = j.msgs.slice(Math.max(0, j.msgs.length - (j.n - n)));
        msgs = msgs.concat(fresh);
        n = j.n;
        render();
        open ? markSeen() : updateDot();
        lastActive = Date.now();
        // первый ответ после загрузки страницы — это история, по ней не звеним
        if (ready && fresh.some((m) => m.f === 'a')) {
            beep();
            if (document.hidden || !open) startBlink();
        }
    }

    async function poll() {
        clearTimeout(timer);
        if (!sid) return;
        try {
            const r = await fetch(`${root.dataset.poll}?sid=${sid}&after=${n}`, { headers: { Accept: 'application/json' } });
            add(await r.json());
        } catch (e) {}
        ready = true;
        schedule();
    }

    function schedule() {
        clearTimeout(timer);
        if (!sid) return;
        const idle = Date.now() - lastActive;
        if (idle > 30 * 60 * 1000) return; // полчаса тишины: перестаём спрашивать
        timer = setTimeout(poll, open ? (idle > 5 * 60 * 1000 ? 10000 : 3000) : 15000);
    }

    function toggle(v) {
        open = v;
        box.classList.toggle('is-open', v);
        document.documentElement.classList.toggle('chat-open', v);
        document.body.style.overflow = v && phone.matches ? 'hidden' : '';
        if (v) {
            stopBlink();
            markSeen();
            lastActive = Date.now();
            poll();
            list.scrollTop = list.scrollHeight;
            if (!phone.matches) setTimeout(() => input.focus(), 50);
        }
    }

    fab.addEventListener('click', () => toggle(!open));
    root.querySelector('[data-chat-close]').addEventListener('click', () => toggle(false));
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = input.scrollHeight + 'px';
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        if (!sid) {
            sid = Array.from(crypto.getRandomValues(new Uint8Array(12)), (b) => b.toString(16).padStart(2, '0')).join('');
            store('chat_sid', sid);
        }
        sendBtn.disabled = true;
        try {
            const r = await fetch(root.dataset.send, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ sid, text, page: location.pathname }),
            });
            if (!r.ok) throw r.status;
            const j = await r.json();
            input.value = '';
            input.style.height = '';
            if (j.n > n) {
                msgs.push({ f: 'v', t: text });
                n = j.n;
            }
            render();
            lastActive = Date.now();
            schedule();
        } catch (status) {
            note(status === 429 ? T.slow : T.error);
        }
        sendBtn.disabled = false;
    });

    // AudioContext разрешён только после действия посетителя: готовим его при первом касании
    ['click', 'keydown', 'touchstart'].forEach((ev) =>
        document.addEventListener(ev, () => {
            try {
                const C = window.AudioContext || window.webkitAudioContext;
                if (C && !audio) audio = new C();
            } catch (e) {}
        }, { once: true, passive: true }),
    );
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        if (open) stopBlink();
        if (sid) {
            lastActive = Date.now();
            poll();
        }
    });

    // вернувшийся посетитель: подтянуть историю и новые ответы
    if (sid) poll();
    else ready = true;
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
