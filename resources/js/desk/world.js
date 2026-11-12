// Комната и предметы на столе. Всё собрано из примитивов three.js, без сторонних моделей.
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import * as T from './textures.js';
import { crtMaterial } from './crt.js';

export const DESK_Y = 0.76;
const WALL_Z = -2.2;

const std = (o) => new THREE.MeshStandardMaterial(o);

function mesh(geo, mat, { x = 0, y = 0, z = 0, rx = 0, ry = 0, rz = 0, cast = true, receive = true } = {}) {
    const m = new THREE.Mesh(geo, mat);
    m.position.set(x, y, z);
    m.rotation.set(rx, ry, rz);
    m.castShadow = cast;
    m.receiveShadow = receive;
    return m;
}

/** Помечаем группу как кликабельную: ключ предмета едет вниз по всем мешам. */
function interactive(group, key, extra = {}) {
    group.userData.key = key;
    Object.assign(group.userData, extra);
    group.traverse((o) => {
        o.userData.key = key;
        Object.assign(o.userData, extra);
    });
    return group;
}

export function buildWorld({ scene, data, quality }) {
    const site = data.site;
    const objects = {}; // key -> { group, focus: { pos, target } }
    const clickables = [];
    const tick = []; // функции анимации по кадрам

    // ── Свет ────────────────────────────────────────────────
    scene.background = new THREE.Color('#0d0a08');
    scene.fog = new THREE.Fog('#0d0a08', 5, 11);
    const hemi = new THREE.HemisphereLight('#d8c4ab', '#2a1c12', 1.1);
    scene.add(hemi);
    const fill = new THREE.DirectionalLight('#9fb4d4', 0.8);
    fill.position.set(-3, 3.5, 2.5);
    scene.add(fill);

    // ── Комната ─────────────────────────────────────────────
    const floorTex = T.toTexture(T.woodTexture({ base: '#3a2416', dark: '#21140b', light: '#55361f', planks: 6, seed: 21 }), { repeat: [3, 3] });
    const floor = mesh(new THREE.PlaneGeometry(14, 14), std({ map: floorTex, roughness: 0.75 }), { rx: -Math.PI / 2, cast: false });
    scene.add(floor);

    const wallTex = T.toTexture(T.plasterTexture({ base: '#2e2823' }), { repeat: [4, 2] });
    const wallMat = std({ map: wallTex, roughness: 0.95 });
    scene.add(mesh(new THREE.PlaneGeometry(14, 6), wallMat, { y: 3, z: WALL_Z, cast: false }));
    scene.add(mesh(new THREE.PlaneGeometry(10, 6), wallMat, { x: -3.6, y: 3, z: 2.8, ry: Math.PI / 2, cast: false }));
    scene.add(mesh(new THREE.PlaneGeometry(10, 6), wallMat, { x: 3.6, y: 3, z: 2.8, ry: -Math.PI / 2, cast: false }));
    // плинтус
    const trim = std({ color: '#1a1410', roughness: 0.6 });
    scene.add(mesh(new THREE.BoxGeometry(14, 0.12, 0.03), trim, { y: 0.06, z: WALL_Z + 0.015 }));

    // ── Стол ────────────────────────────────────────────────
    const deskTex = T.toTexture(T.woodTexture({ base: '#6b4428', dark: '#3d2414', light: '#8d5f3a', seed: 9 }));
    const deskMat = std({ map: deskTex, roughness: 0.55, metalness: 0.02 });
    const desk = new THREE.Group();
    desk.add(mesh(new RoundedBoxGeometry(2.7, 0.06, 1.0, 2, 0.012), deskMat, { y: DESK_Y - 0.03, z: -1.5 }));
    const legMat = std({ color: '#2a1b10', roughness: 0.6 });
    [[-1.25, -1.1], [1.25, -1.1], [-1.25, -1.9], [1.25, -1.9]].forEach(([x, z]) => {
        desk.add(mesh(new THREE.BoxGeometry(0.06, DESK_Y - 0.06, 0.06), legMat, { x, y: (DESK_Y - 0.06) / 2, z }));
    });
    desk.add(mesh(new THREE.BoxGeometry(2.5, 0.12, 0.03), legMat, { y: DESK_Y - 0.12, z: -1.04 }));
    scene.add(desk);

    // ── Лампа ───────────────────────────────────────────────
    const lamp = new THREE.Group();
    const brass = std({ color: '#b08a4a', metalness: 0.85, roughness: 0.35 });
    const lampBlack = std({ color: '#1c1c1c', metalness: 0.5, roughness: 0.4 });
    lamp.add(mesh(new THREE.CylinderGeometry(0.09, 0.11, 0.025, 32), lampBlack, { y: 0.0125 }));
    const arm1 = mesh(new THREE.CylinderGeometry(0.008, 0.008, 0.42, 12), brass, { y: 0.2, x: 0.05, rz: -0.25 });
    lamp.add(arm1);
    const arm2 = mesh(new THREE.CylinderGeometry(0.008, 0.008, 0.36, 12), brass, { y: 0.48, x: 0.22, rz: -1.15 });
    lamp.add(arm2);
    const shade = new THREE.Group();
    shade.position.set(0.38, 0.52, 0);
    shade.rotation.z = -2.2;
    shade.add(mesh(new THREE.CylinderGeometry(0.035, 0.11, 0.16, 32, 1, true), std({ color: '#2b4a3a', metalness: 0.4, roughness: 0.45, side: THREE.DoubleSide })));
    const bulbMat = std({ color: '#fff2d6', emissive: '#ffd79a', emissiveIntensity: 2.2 });
    const bulb = mesh(new THREE.SphereGeometry(0.035, 16, 16), bulbMat, { y: -0.05, cast: false });
    shade.add(bulb);
    lamp.add(shade);
    lamp.position.set(-1.18, DESK_Y, -1.82);
    lamp.rotation.y = 0.55;
    scene.add(lamp);

    const lampLight = new THREE.SpotLight('#ffcf8a', 14, 5, 0.95, 0.6, 1.2);
    lampLight.castShadow = quality.shadows;
    lampLight.shadow.mapSize.set(1024, 1024);
    lampLight.shadow.bias = -0.0008;
    lampLight.shadow.radius = 4;
    lamp.updateMatrixWorld(true);
    bulb.getWorldPosition(lampLight.position);
    lampLight.target.position.set(-0.2, DESK_Y, -1.35);
    scene.add(lampLight, lampLight.target);
    // мягкий тёплый свет на всю комнату, иначе углы проваливаются в черноту
    const room = new THREE.PointLight('#ffb36b', 4, 7, 1.4);
    room.position.set(-0.4, 1.9, -0.9);
    scene.add(room);

    let lampOn = true;
    objects.lamp = { group: lamp, focus: null };
    interactive(lamp, 'lamp');
    clickables.push(lamp);
    const setLamp = (on) => {
        lampOn = on;
        lampLight.intensity = on ? 14 : 0;
        room.intensity = on ? 4 : 0.6;
        bulbMat.emissiveIntensity = on ? 2.2 : 0;
        hemi.intensity = on ? 1.1 : 0.45;
    };

    // ── Телевизор ───────────────────────────────────────────
    const tv = new THREE.Group();
    const tvWood = T.toTexture(T.woodTexture({ base: '#5b3a22', dark: '#2e1c0f', light: '#7c5232', seed: 33 }));
    const tvBody = std({ map: tvWood, roughness: 0.5 });
    const W = 0.8;
    const H = 0.6;
    const D = 0.5;
    tv.add(mesh(new RoundedBoxGeometry(W, H, D, 3, 0.04), tvBody));
    // рамка экрана и панель с ручками
    const bezel = std({ color: '#191715', roughness: 0.5 });
    tv.add(mesh(new RoundedBoxGeometry(0.58, 0.46, 0.04, 3, 0.03), bezel, { x: -0.08, z: D / 2 - 0.005 }));
    const screenW = 0.52;
    const screenH = 0.39;
    const idle = T.toTexture(T.idleScreenTexture(site.name));
    const screenMat = crtMaterial(idle, screenW / screenH);
    const screen = mesh(new THREE.PlaneGeometry(screenW, screenH), screenMat, { x: -0.08, z: D / 2 + 0.017, cast: false, receive: false });
    tv.add(screen);
    const glass = mesh(new THREE.PlaneGeometry(screenW, screenH), new THREE.MeshPhysicalMaterial({ color: '#ffffff', transparent: true, opacity: 0.06, roughness: 0.05, metalness: 0, clearcoat: 1 }), { x: -0.08, z: D / 2 + 0.019, cast: false, receive: false });
    tv.add(glass);
    const panel = std({ color: '#2a2622', roughness: 0.45, metalness: 0.2 });
    tv.add(mesh(new THREE.BoxGeometry(0.13, 0.46, 0.02), panel, { x: 0.3, z: D / 2 + 0.005 }));
    const knobMat = std({ color: '#c9b79a', metalness: 0.6, roughness: 0.3 });
    const knobs = [];
    [0.12, -0.02].forEach((y) => {
        const k = mesh(new THREE.CylinderGeometry(0.035, 0.035, 0.03, 24), knobMat, { x: 0.3, y, z: D / 2 + 0.03, rx: Math.PI / 2 });
        knobs.push(k);
        tv.add(k);
    });
    // решётка динамика
    for (let i = 0; i < 6; i++) tv.add(mesh(new THREE.BoxGeometry(0.09, 0.006, 0.004), bezel, { x: 0.3, y: -0.12 - i * 0.018, z: D / 2 + 0.017, cast: false }));
    // ножки и антенна
    [[-0.32, 0.18], [0.32, 0.18], [-0.32, -0.18], [0.32, -0.18]].forEach(([x, z]) => tv.add(mesh(new THREE.CylinderGeometry(0.015, 0.01, 0.06, 8), legMat, { x, y: -H / 2 - 0.03, z })));
    tv.add(mesh(new THREE.SphereGeometry(0.04, 16, 12, 0, Math.PI * 2, 0, Math.PI / 2), lampBlack, { y: H / 2, z: -0.05 }));
    const rodMat = std({ color: '#d9d9d9', metalness: 0.9, roughness: 0.2 });
    tv.add(mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.34, 6), rodMat, { x: -0.07, y: H / 2 + 0.15, z: -0.05, rz: 0.6 }));
    tv.add(mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.34, 6), rodMat, { x: 0.07, y: H / 2 + 0.15, z: -0.05, rz: -0.6 }));
    tv.position.set(-0.45, DESK_Y + 0.06 + H / 2, -1.68);
    tv.rotation.y = 0.12;
    scene.add(tv);
    const tvGlow = new THREE.PointLight('#9fc4ff', 0.0, 2.2, 2);
    tvGlow.position.set(-0.43, DESK_Y + 0.4, -1.2);
    scene.add(tvGlow);
    interactive(tv, 'tv');
    clickables.push(tv);
    objects.tv = { group: tv, focus: focusOf(tv, new THREE.Vector3(-0.08, 0, 0), 0.95, 0.04) };

    // ── Проигрыватель ───────────────────────────────────────
    const tt = new THREE.Group();
    const ttWood = T.toTexture(T.woodTexture({ base: '#a0703f', dark: '#6a4422', light: '#c08a52', seed: 44 }));
    tt.add(mesh(new RoundedBoxGeometry(0.52, 0.08, 0.4, 2, 0.012), std({ map: ttWood, roughness: 0.45 }), { y: 0.04 }));
    const alu = std({ color: '#cfcfcf', metalness: 0.85, roughness: 0.3 });
    const platter = new THREE.Group();
    platter.position.set(-0.06, 0.085, 0);
    platter.add(mesh(new THREE.CylinderGeometry(0.155, 0.155, 0.012, 64), alu));
    platter.add(mesh(new THREE.CylinderGeometry(0.15, 0.15, 0.003, 64), std({ color: '#151515', roughness: 0.9 }), { y: 0.0075 }));
    platter.add(mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.03, 8), alu, { y: 0.015 }));
    tt.add(platter);
    // тонарм на шарнире
    const armPivot = new THREE.Group();
    armPivot.position.set(0.17, 0.1, -0.13);
    armPivot.add(mesh(new THREE.CylinderGeometry(0.022, 0.026, 0.035, 20), alu, { y: -0.005 }));
    const arm = new THREE.Group();
    arm.add(mesh(new THREE.CylinderGeometry(0.0045, 0.0045, 0.25, 10), alu, { z: 0.125, rx: Math.PI / 2, y: 0.012 }));
    arm.add(mesh(new THREE.BoxGeometry(0.02, 0.012, 0.035), lampBlack, { y: 0.006, z: 0.255 }));
    arm.add(mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.03, 16), lampBlack, { z: -0.03, rx: Math.PI / 2, y: 0.012 }));
    arm.rotation.y = 0.15; // в покое
    armPivot.add(arm);
    tt.add(armPivot);
    tt.add(mesh(new THREE.BoxGeometry(0.04, 0.012, 0.04), lampBlack, { x: 0.2, y: 0.086, z: 0.15 }));
    const ttLedMat = std({ color: '#300', emissive: '#ff3b2f', emissiveIntensity: 0 });
    tt.add(mesh(new THREE.SphereGeometry(0.006, 8, 8), ttLedMat, { x: 0.2, y: 0.082, z: 0.2 }));
    tt.position.set(0.5, DESK_Y, -1.5);
    tt.rotation.y = -0.18;
    scene.add(tt);
    interactive(tt, 'turntable');
    clickables.push(tt);
    objects.turntable = { group: tt, focus: focusAt(tt, new THREE.Vector3(-0.05, 0.5, 0.6)) };

    // ── Полки с пластинками ─────────────────────────────────
    const shelf = new THREE.Group();
    const shelfMat = std({ map: deskTex, roughness: 0.6 });
    const ledgeY = [1.42, 1.86];
    const ledgeX = 0.82;
    ledgeY.forEach((y) => {
        shelf.add(mesh(new THREE.BoxGeometry(1.5, 0.025, 0.14), shelfMat, { x: ledgeX, y, z: WALL_Z + 0.07 }));
        shelf.add(mesh(new THREE.BoxGeometry(1.5, 0.05, 0.012), shelfMat, { x: ledgeX, y: y + 0.03, z: WALL_Z + 0.135 }));
        [-0.65, 0.65].forEach((dx) => shelf.add(mesh(new THREE.BoxGeometry(0.015, 0.06, 0.1), legMat, { x: ledgeX + dx, y: y - 0.04, z: WALL_Z + 0.05 })));
    });
    scene.add(shelf);
    interactive(shelf, 'records');
    clickables.push(shelf);

    const albums = [];
    const SLEEVE = 0.31;
    const list = data.albums.length ? data.albums : Array.from({ length: 4 }, (_, i) => ({ id: -i - 1, title: site.name, placeholder: true }));
    const sleeveEdge = std({ color: '#d8cfbf', roughness: 0.8 });
    list.slice(0, 8).forEach((album, i) => {
        const row = i < 4 ? 0 : 1;
        const col = i % 4;
        const g = new THREE.Group();
        const coverCanvas = T.generatedCover(album.title, i);
        const coverTex = T.toTexture(coverCanvas);
        const faceMat = std({ map: coverTex, roughness: 0.55 });
        const sleeve = mesh(new THREE.BoxGeometry(SLEEVE, SLEEVE, 0.008), [sleeveEdge, sleeveEdge, sleeveEdge, sleeveEdge, faceMat, sleeveEdge]);
        sleeve.position.y = SLEEVE / 2;
        g.add(sleeve);
        g.position.set(ledgeX - 0.555 + col * 0.37, ledgeY[row] + 0.0125, WALL_Z + 0.105);
        g.rotation.x = -0.12;
        g.rotation.z = (((i * 37) % 7) - 3) * 0.006;
        scene.add(g);
        const vinylTex = T.toTexture(T.vinylTexture(coverCanvas));
        const entry = { album, group: g, home: g.position.clone(), homeRot: g.rotation.clone(), coverTex, faceMat, vinylTex, index: i };
        albums.push(entry);
        interactive(g, 'album', { album: i });
        clickables.push(g);
    });
    const shelfCenter = new THREE.Vector3(ledgeX, 1.78, WALL_Z);
    objects.records = { group: shelf, focus: { target: shelfCenter.clone(), pos: shelfCenter.clone().add(new THREE.Vector3(-0.15, -0.05, 1.75)) } };

    // пластинка, которая едет из конверта на диск проигрывателя
    const disc = mesh(new THREE.CylinderGeometry(0.145, 0.145, 0.003, 64), [std({ color: '#080808', roughness: 0.35 }), std({ map: albums[0]?.vinylTex, roughness: 0.32, metalness: 0.1 }), std({ color: '#080808' })]);
    disc.visible = false;
    scene.add(disc);

    // ── Календарь ───────────────────────────────────────────
    const cal = new THREE.Group();
    const calCanvas = document.createElement('canvas');
    const calTex = T.toTexture(T.calendarTexture({ months: data.i18n.book.months, weekdays: data.i18n.book.weekdays }), { aniso: 8 });
    const calMat = std({ map: calTex, roughness: 0.85 });
    cal.add(mesh(new THREE.PlaneGeometry(0.46, 0.63), calMat, { y: -0.33, cast: false }));
    cal.add(mesh(new THREE.BoxGeometry(0.47, 0.02, 0.012), std({ color: '#7a1d1d', roughness: 0.5 }), { y: -0.012 }));
    for (let i = 0; i < 9; i++) cal.add(mesh(new THREE.TorusGeometry(0.008, 0.002, 6, 12), rodMat, { x: -0.2 + i * 0.05, y: -0.006, z: 0.006, cast: false }));
    cal.add(mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.02, 6), rodMat, { y: 0.03, z: -0.01, rx: Math.PI / 2 }));
    cal.add(mesh(new THREE.CylinderGeometry(0.0015, 0.0015, 0.06, 4), rodMat, { y: 0.01, rz: 0.4, x: -0.012 }));
    cal.add(mesh(new THREE.CylinderGeometry(0.0015, 0.0015, 0.06, 4), rodMat, { y: 0.01, rz: -0.4, x: 0.012 }));
    cal.position.set(-1.55, 2.22, WALL_Z + 0.01);
    cal.rotation.z = 0.02;
    scene.add(cal);
    interactive(cal, 'calendar');
    clickables.push(cal);
    objects.calendar = { group: cal, focus: { target: new THREE.Vector3(-1.55, 1.9, WALL_Z), pos: new THREE.Vector3(-1.45, 1.86, WALL_Z + 1.15) } };
    const calState = { photo: null, marked: [] };
    const redrawCalendar = () => {
        const c = T.calendarTexture({ months: data.i18n.book.months, weekdays: data.i18n.book.weekdays, ...calState });
        calTex.image = c;
        calTex.needsUpdate = true;
    };
    void calCanvas;

    // ── Доска с кадрами ─────────────────────────────────────
    const board = new THREE.Group();
    const corkTex = T.toTexture(T.corkTexture());
    board.add(mesh(new THREE.BoxGeometry(0.78, 0.52, 0.02), std({ map: corkTex, roughness: 0.95 }), { cast: false }));
    const frameMat = std({ color: '#2a1b10', roughness: 0.6 });
    board.add(mesh(new THREE.BoxGeometry(0.82, 0.025, 0.03), frameMat, { y: 0.27 }));
    board.add(mesh(new THREE.BoxGeometry(0.82, 0.025, 0.03), frameMat, { y: -0.27 }));
    board.add(mesh(new THREE.BoxGeometry(0.025, 0.56, 0.03), frameMat, { x: 0.4 }));
    board.add(mesh(new THREE.BoxGeometry(0.025, 0.56, 0.03), frameMat, { x: -0.4 }));
    const polaroids = [];
    const paper = std({ color: '#f1ece2', roughness: 0.9 });
    const pinMat = std({ color: '#c0392b', roughness: 0.4 });
    const spots = [[-0.25, 0.1, 0.08], [0, 0.12, -0.06], [0.25, 0.09, 0.05], [-0.22, -0.13, -0.05], [0.03, -0.12, 0.07], [0.27, -0.14, -0.08]];
    spots.forEach(([x, y, r], i) => {
        const p = new THREE.Group();
        p.add(mesh(new THREE.PlaneGeometry(0.16, 0.19), paper, { cast: false }));
        const photoMat = std({ color: '#3a3430', roughness: 0.7 });
        p.add(mesh(new THREE.PlaneGeometry(0.14, 0.14), photoMat, { y: 0.015, z: 0.001, cast: false }));
        p.add(mesh(new THREE.SphereGeometry(0.009, 10, 8), pinMat, { y: 0.08, z: 0.01 }));
        p.position.set(x, y, 0.012 + i * 0.0005);
        p.rotation.z = r;
        board.add(p);
        polaroids.push(photoMat);
    });
    board.position.set(-0.45, 2.02, WALL_Z + 0.02);
    scene.add(board);
    interactive(board, 'board');
    clickables.push(board);
    objects.board = { group: board, focus: { target: new THREE.Vector3(-0.45, 2.0, WALL_Z), pos: new THREE.Vector3(-0.45, 1.92, WALL_Z + 1.25) } };

    // ── Кинокамера ──────────────────────────────────────────
    const cam = new THREE.Group();
    const camBody = std({ color: '#1e1e20', roughness: 0.45, metalness: 0.3 });
    cam.add(mesh(new RoundedBoxGeometry(0.16, 0.13, 0.24, 2, 0.015), camBody, { y: 0.095 }));
    cam.add(mesh(new THREE.CylinderGeometry(0.045, 0.05, 0.12, 32), lampBlack, { y: 0.095, z: 0.17, rx: Math.PI / 2 }));
    cam.add(mesh(new THREE.CylinderGeometry(0.046, 0.046, 0.012, 32), std({ color: '#c9a35c', metalness: 0.8, roughness: 0.3 }), { y: 0.095, z: 0.2, rx: Math.PI / 2 }));
    const lensGlass = mesh(new THREE.CircleGeometry(0.04, 32), std({ color: '#0a1a2a', metalness: 0.9, roughness: 0.05, emissive: '#102a40', emissiveIntensity: 0.4 }), { y: 0.095, z: 0.2305 });
    cam.add(lensGlass);
    // две катушки сверху
    const reelMat = std({ color: '#2b2b2e', metalness: 0.6, roughness: 0.35 });
    const reels = [];
    [-0.06, 0.07].forEach((z) => {
        const reel = mesh(new THREE.CylinderGeometry(0.075, 0.075, 0.02, 32), reelMat, { y: 0.23, z, rz: Math.PI / 2 });
        reels.push(reel);
        cam.add(reel);
    });
    cam.add(mesh(new THREE.BoxGeometry(0.03, 0.06, 0.03), camBody, { y: 0.18, z: 0.005 }));
    // штатив-«паук» на столе
    cam.add(mesh(new THREE.CylinderGeometry(0.06, 0.07, 0.03, 20), lampBlack, { y: 0.015 }));
    const recMat = std({ color: '#300', emissive: '#ff2a2a', emissiveIntensity: 1.5 });
    cam.add(mesh(new THREE.SphereGeometry(0.007, 8, 8), recMat, { x: 0.081, y: 0.13, z: 0.08, cast: false }));
    cam.position.set(-1.05, DESK_Y, -1.28);
    cam.rotation.y = 0.75;
    scene.add(cam);
    interactive(cam, 'camera');
    clickables.push(cam);
    objects.camera = { group: cam, focus: focusAt(cam, new THREE.Vector3(0.12, 0.28, 0.7)) };

    // ── Дисковый телефон ────────────────────────────────────
    const phone = new THREE.Group();
    const red = std({ color: '#8f1f1a', roughness: 0.32, metalness: 0.05 });
    const base = mesh(new THREE.CylinderGeometry(0.1, 0.12, 0.09, 40), red, { y: 0.045 });
    base.scale.z = 0.85;
    phone.add(base);
    phone.add(mesh(new THREE.CylinderGeometry(0.065, 0.065, 0.01, 40), std({ map: T.toTexture(T.dialTexture()), roughness: 0.4 }), { y: 0.093, z: 0.02, rx: 0.22 }));
    phone.add(mesh(new THREE.BoxGeometry(0.03, 0.03, 0.05), red, { x: -0.07, y: 0.1, z: -0.02 }));
    phone.add(mesh(new THREE.BoxGeometry(0.03, 0.03, 0.05), red, { x: 0.07, y: 0.1, z: -0.02 }));
    const handset = new THREE.Group();
    handset.add(mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.2, 16), red, { rz: Math.PI / 2 }));
    [-0.1, 0.1].forEach((x) => {
        const cup = mesh(new THREE.CylinderGeometry(0.03, 0.022, 0.035, 24), red, { x, y: -0.012 });
        handset.add(cup);
    });
    handset.position.set(0, 0.13, -0.02);
    phone.add(handset);
    const cord = new THREE.CatmullRomCurve3([new THREE.Vector3(-0.1, 0.03, 0), new THREE.Vector3(-0.17, 0.0, 0.04), new THREE.Vector3(-0.2, 0.0, -0.06), new THREE.Vector3(-0.13, 0.1, -0.02)]);
    phone.add(mesh(new THREE.TubeGeometry(cord, 40, 0.004, 6), lampBlack));
    phone.position.set(1.08, DESK_Y, -1.22);
    phone.rotation.y = -0.55;
    scene.add(phone);
    interactive(phone, 'phone');
    clickables.push(phone);
    objects.phone = { group: phone, focus: focusAt(phone, new THREE.Vector3(-0.2, 0.3, 0.6)) };

    // ── Хлопушка ────────────────────────────────────────────
    const clap = new THREE.Group();
    const slate = mesh(new THREE.BoxGeometry(0.26, 0.012, 0.2), [lampBlack, lampBlack, std({ map: T.toTexture(T.slateTexture(site.name)), roughness: 0.6 }), lampBlack, lampBlack, lampBlack], { y: 0.006 });
    clap.add(slate);
    const stickHinge = new THREE.Group();
    stickHinge.position.set(-0.13, 0.014, -0.1);
    const stripes = T.toTexture(T.stripesTexture());
    const stick = mesh(new THREE.BoxGeometry(0.26, 0.012, 0.035), [lampBlack, lampBlack, std({ map: stripes }), lampBlack, std({ map: stripes }), lampBlack], { x: 0.13, z: -0.0175 });
    stickHinge.add(stick);
    stickHinge.rotation.z = 0.0;
    stickHinge.rotation.x = 0;
    const clapOpen = 0.5;
    stickHinge.rotation.z = clapOpen;
    clap.add(stickHinge);
    clap.position.set(0.05, DESK_Y, -1.2);
    clap.rotation.y = 0.25;
    scene.add(clap);
    interactive(clap, 'clapper');
    clickables.push(clap);
    objects.clapper = { group: clap, focus: focusAt(clap, new THREE.Vector3(0, 0.45, 0.45)) };

    // ── Неоновая вывеска «выход» ────────────────────────────
    const sign = new THREE.Group();
    const signTex = T.toTexture(T.signTexture('EXIT ↗'));
    sign.add(mesh(new THREE.PlaneGeometry(0.62, 0.2), new THREE.MeshBasicMaterial({ map: signTex, transparent: true, toneMapped: false }), { cast: false, receive: false }));
    sign.add(mesh(new THREE.BoxGeometry(0.66, 0.24, 0.01), std({ color: '#111', transparent: true, opacity: 0.6 }), { z: -0.01, cast: false }));
    sign.position.set(2.05, 2.25, WALL_Z + 0.02);
    scene.add(sign);
    const signGlow = new THREE.PointLight('#ff5a3c', 0.6, 1.4, 2);
    signGlow.position.set(2.05, 2.25, WALL_Z + 0.3);
    scene.add(signGlow);
    interactive(sign, 'sign');
    clickables.push(sign);

    // ── Декор: кружка и бобина плёнки ───────────────────────
    const mug = new THREE.Group();
    const ceramic = std({ color: '#e9e2d3', roughness: 0.35 });
    mug.add(mesh(new THREE.CylinderGeometry(0.042, 0.038, 0.1, 28, 1, true), new THREE.MeshStandardMaterial({ color: '#e9e2d3', roughness: 0.35, side: THREE.DoubleSide }), { y: 0.05 }));
    mug.add(mesh(new THREE.CircleGeometry(0.04, 28), std({ color: '#2a160a', roughness: 0.2 }), { y: 0.085, rx: -Math.PI / 2 }));
    mug.add(mesh(new THREE.CylinderGeometry(0.038, 0.038, 0.006, 28), ceramic, { y: 0.003 }));
    mug.add(mesh(new THREE.TorusGeometry(0.026, 0.007, 8, 20, Math.PI), ceramic, { x: 0.042, y: 0.05, rz: -Math.PI / 2 }));
    mug.position.set(0.98, DESK_Y, -1.75);
    scene.add(mug);
    const reel = new THREE.Group();
    reel.add(mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.012, 40), reelMat));
    reel.add(mesh(new THREE.CylinderGeometry(0.075, 0.075, 0.014, 40), std({ color: '#3a2a1a', roughness: 0.4, metalness: 0.2 })));
    reel.position.set(-0.2, DESK_Y + 0.006, -1.13);
    reel.rotation.y = 0.4;
    scene.add(reel);

    // ── Анимации по кадрам ──────────────────────────────────
    const state = { playing: false, spin: 0, armTarget: 0.15, time: 0 };
    tick.push((dt, t) => {
        state.time = t;
        screenMat.uniforms.time.value = t;
        state.spin += ((state.playing ? 1 : 0) - state.spin) * Math.min(1, dt * 2.5);
        const w = state.spin * 3.49 * dt; // 33⅓ об/мин
        platter.rotation.y -= w;
        if (disc.visible && disc.userData.onPlatter) disc.rotation.y -= w;
        arm.rotation.y += (state.armTarget - arm.rotation.y) * Math.min(1, dt * 3);
        ttLedMat.emissiveIntensity = state.playing ? 2 : 0;
        reels.forEach((r, i) => (r.rotation.x += dt * (i ? 1.4 : 1.1)));
        recMat.emissiveIntensity = Math.sin(t * 3) > 0 ? 1.8 : 0.2;
        const neon = 0.94 + Math.sin(t * 40) * 0.02 + (Math.random() > 0.995 ? -0.5 : 0);
        signGlow.intensity = 0.6 * neon;
        sign.children[0].material.opacity = Math.max(0.4, neon);
        sign.children[0].material.transparent = true;
        tvGlow.intensity = state.playing ? 0.5 + Math.sin(t * 7) * 0.08 + Math.sin(t * 2.3) * 0.08 : 0.12;
    });

    return {
        objects,
        clickables,
        tick,
        albums,
        disc,
        platter,
        tt,
        screenMat,
        idle,
        state,
        polaroids,
        calState,
        redrawCalendar,
        handset,
        stickHinge,
        clapOpen,
        knobs,
        setLamp,
        get lampOn() {
            return lampOn;
        },
    };
}

/** Камера смотрит на центр предмета с заданного смещения в мировых координатах. */
function focusAt(group, offset) {
    group.updateMatrixWorld(true);
    const target = new THREE.Box3().setFromObject(group).getCenter(new THREE.Vector3());
    return { target, pos: target.clone().add(offset) };
}

/** Куда смотреть на предмет: точка интереса и позиция камеры перед ним. */
function focusOf(group, local, dist, up = 0.2, side = 0) {
    group.updateMatrixWorld(true);
    const target = local.clone().applyMatrix4(group.matrixWorld);
    const fwd = new THREE.Vector3(0, 0, 1).applyQuaternion(group.quaternion).setY(0).normalize();
    const pos = target.clone().addScaledVector(fwd, dist).add(new THREE.Vector3(side, up, 0));
    return { target, pos };
}
