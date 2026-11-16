// Alat rekam untuk NoirReel: membuka situs di Chrome, menggerakkan kursor seperti orang, dan merekam layarnya
// lewat CDP screencast (JPEG kualitas tinggi + timestamp), lalu dijahit jadi MP4 30 fps dengan ffmpeg.
import {chromium} from 'playwright-core';
import {execFileSync} from 'node:child_process';
import {mkdirSync, rmSync, writeFileSync, readdirSync} from 'node:fs';
import path from 'node:path';

export const BASE = process.env.NOIR_BASE ?? 'http://127.0.0.1:8081';
export const VIEW = {width: 1440, height: 936};   // 16:10, seperti layar MacBook
export const DSF = 1.5;
const OUT = path.resolve(import.meta.dirname, '..', 'rec');

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rand = (a, b) => a + Math.random() * (b - a);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

// Kursor macOS (panah putih, garis hitam) digambar di halaman supaya ikut terekam; bergerak lewat event mouse asli.
const CURSOR_JS = `
(() => {
  if (window.__cursorInstalled) return; window.__cursorInstalled = true;
  const mk = () => {
    if (document.getElementById('__cur')) return;
    const el = document.createElement('div'); el.id = '__cur';
    el.style.cssText = 'position:fixed;left:0;top:0;width:22px;height:30px;z-index:2147483647;pointer-events:none;transform:translate(-100px,-100px);transition:scale .09s ease-out;will-change:transform;filter:drop-shadow(0 1px 1.5px rgba(0,0,0,.35))';
    el.innerHTML = '<svg width="22" height="30" viewBox="0 0 22 30"><path d="M2 2 L2 23 L7.4 18.3 L11 26.6 L14.6 25 L11 17 L18.3 17 Z" fill="#fff" stroke="#000" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    const ring = document.createElement('div'); ring.id = '__ring';
    ring.style.cssText = 'position:fixed;left:0;top:0;width:34px;height:34px;margin:-17px 0 0 -17px;border-radius:50%;background:rgba(0,0,0,.14);z-index:2147483646;pointer-events:none;opacity:0;transform:translate(-100px,-100px) scale(.4)';
    (document.documentElement).append(ring, el);
  };
  const place = (x, y) => { mk(); const c = document.getElementById('__cur'); if (c) c.style.transform = 'translate(' + x + 'px,' + y + 'px)'; window.__cx = x; window.__cy = y; };
  addEventListener('mousemove', (e) => place(e.clientX, e.clientY), true);
  addEventListener('mousedown', (e) => {
    mk(); const c = document.getElementById('__cur'), r = document.getElementById('__ring');
    if (c) c.style.scale = '.86';
    if (r) { r.style.transition = 'none'; r.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px) scale(.4)'; r.style.opacity = '1';
      requestAnimationFrame(() => requestAnimationFrame(() => { r.style.transition = 'transform .45s ease-out, opacity .45s ease-out'; r.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px) scale(1.25)'; r.style.opacity = '0'; })); }
  }, true);
  addEventListener('mouseup', () => { const c = document.getElementById('__cur'); if (c) c.style.scale = '1'; }, true);
  document.addEventListener('DOMContentLoaded', () => { mk(); if (window.__cx != null) place(window.__cx, window.__cy); });
})();`;

export async function openSession({name, start = [980, 260]}) {
  const browser = await chromium.launch({executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox', '--hide-scrollbars', '--force-color-profile=srgb', '--use-gl=angle', '--use-angle=gl', '--ignore-gpu-blocklist', '--enable-gpu-rasterization']});
  const context = await browser.newContext({viewport: VIEW, deviceScaleFactor: DSF, reducedMotion: 'no-preference'});
  await context.addInitScript(CURSOR_JS);
  const page = await context.newPage();
  const framesDir = path.join(OUT, `${name}.frames`);
  rmSync(framesDir, {recursive: true, force: true});
  mkdirSync(framesDir, {recursive: true});

  const cdp = await context.newCDPSession(page);
  const frames = [];
  cdp.on('Page.screencastFrame', async ({data, metadata, sessionId}) => {
    const file = path.join(framesDir, String(frames.length).padStart(6, '0') + '.jpg');
    writeFileSync(file, Buffer.from(data, 'base64'));
    frames.push({file, t: metadata.timestamp});
    cdp.send('Page.screencastFrameAck', {sessionId}).catch(() => {});
  });

  const state = {x: start[0], y: start[1]};
  const s = {browser, context, page, cdp, frames, state, name, framesDir};
  s.startRec = () => cdp.send('Page.startScreencast', {format: 'jpeg', quality: 94, everyNthFrame: 1});
  s.stopRec = async () => { await cdp.send('Page.stopScreencast').catch(() => {}); };
  return s;
}

/** Gerak kursor ke (x,y): lengkung ringan, cepat lalu melambat, kadang lewat sedikit lalu kembali. */
export async function moveTo(s, x, y, {ms, overshoot = true} = {}) {
  const {page, state} = s;
  const dx = x - state.x, dy = y - state.y, dist = Math.hypot(dx, dy);
  if (dist < 2) return;
  const dur = ms ?? Math.min(1100, 380 + dist * 0.9);
  const nx = -dy / dist, ny = dx / dist;                    // normal untuk lengkungan
  const bend = rand(-0.12, 0.12) * dist;
  const over = overshoot && dist > 260 ? rand(6, 14) : 0;
  const tx = x + (dx / dist) * over, ty = y + (dy / dist) * over;
  const steps = Math.max(12, Math.round(dur / 16));
  const t0 = Date.now();
  for (let i = 1; i <= steps; i++) {
    const p = easeOut(i / steps);
    const wob = Math.sin(Math.PI * p) * bend;
    await page.mouse.move(state.x + (tx - state.x) * p + nx * wob, state.y + (ty - state.y) * p + ny * wob);
    const wait = t0 + (dur * i) / steps - Date.now();
    if (wait > 0) await sleep(wait);
  }
  if (over) {                                               // kembali ke target
    const sx = tx, sy = ty, n = 7;
    for (let i = 1; i <= n; i++) { const p = easeInOut(i / n); await page.mouse.move(sx + (x - sx) * p, sy + (y - sy) * p); await sleep(16); }
  }
  state.x = x; state.y = y;
}

/** Titik tengah elemen (dengan sedikit ketidaktepatan manusiawi). */
export async function centerOf(s, locator, jitter = 0.18) {
  await locator.waitFor({state: 'visible'});
  const b = await locator.boundingBox();
  return [b.x + b.width * (0.5 + rand(-jitter, jitter)), b.y + b.height * (0.5 + rand(-jitter, jitter))];
}

export async function click(s, locator, {pause = [220, 380], after = 0} = {}) {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  const [x, y] = await centerOf(s, locator);
  await moveTo(s, x, y);
  await sleep(rand(...pause));                              // jeda kecil sebelum klik
  await s.page.mouse.down(); await sleep(rand(70, 120)); await s.page.mouse.up();
  if (after) await sleep(after);
}

/** Scroll seperti trackpad: roda dengan inersia (cepat dulu, lalu melambat). */
export async function scrollBy(s, px, {ms = 1400} = {}) {
  const {page, state} = s;
  const steps = Math.round(ms / 16); let done = 0; const t0 = Date.now();
  for (let i = 1; i <= steps; i++) {
    const target = px * easeOut(i / steps);
    const d = target - done; done = target;
    if (Math.abs(d) >= 0.5) await page.mouse.wheel(0, d);
    const wait = t0 + (ms * i) / steps - Date.now();
    if (wait > 0) await sleep(wait);
  }
  await page.mouse.move(state.x, state.y);
}

export async function typeHuman(s, locator, text) {
  await click(s, locator, {pause: [120, 220]});
  for (const ch of text) { await s.page.keyboard.type(ch); await sleep(rand(45, 120) + (ch === ' ' ? rand(30, 90) : 0)); }
}

/** Jahit frame screencast (VFR) jadi MP4 30 fps. */
export async function finish(s, tailMs = 600) {
  await sleep(tailMs);
  await s.stopRec();
  await s.browser.close();
  const {frames, framesDir, name} = s;
  if (frames.length < 2) throw new Error('screencast kosong');
  const list = [];
  for (let i = 0; i < frames.length; i++) {
    const dur = i < frames.length - 1 ? frames[i + 1].t - frames[i].t : tailMs / 1000;
    list.push(`file '${frames[i].file}'`, `duration ${Math.max(dur, 0.001).toFixed(4)}`);
  }
  list.push(`file '${frames[frames.length - 1].file}'`);
  const listFile = path.join(framesDir, 'list.txt');
  writeFileSync(listFile, list.join('\n'));
  const out = path.join(OUT, `${name}.mp4`);
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', listFile, '-vf', 'fps=30,format=yuv420p',
    '-c:v', 'libx264', '-crf', '14', '-preset', 'medium', '-movflags', '+faststart', out]);
  rmSync(framesDir, {recursive: true, force: true});
  const total = frames[frames.length - 1].t - frames[0].t + tailMs / 1000;
  console.log(`${name}: ${frames.length} frame, ${total.toFixed(1)} dtk -> ${out}`);
  return out;
}

/** Login admin demo di luar rekaman. */
export async function adminLogin(s) {
  await s.page.goto(BASE + '/admin-login', {waitUntil: 'load'});
  await s.page.fill('input[name="username"]', 'demo');
  await s.page.fill('input[name="password"]', 'demo1234');
  await Promise.all([s.page.waitForURL(/admin\//), s.page.keyboard.press('Enter')]);
}

/** SQL ke database salinan rekaman (newnoir_reel), bukan database asli. */
export function sql(q) {
  return execFileSync('/opt/lampp/bin/mysql', ['-uroot', 'newnoir_reel', '-N', '-e', q], {encoding: 'utf8'});
}
