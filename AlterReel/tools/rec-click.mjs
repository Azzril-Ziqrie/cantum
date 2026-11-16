// Rekam alter-that.com: klik foto di bola (kamera mendekat ke foto), klik lagi untuk kembali, lalu foto lain.
import {openSession, sleep, moveTo, finish} from './lib.mjs';
const s = await openSession({name: 'click', start: [900, 300]});
const {page} = s;
await page.goto('https://alter-that.com/', {waitUntil: 'load'});
await sleep(7000);
await moveTo(s, 720, 440, {ms: 500, overshoot: false});
await s.startRec();
const t0 = Date.now(); const mark = (n) => console.log(((Date.now() - t0) / 1000).toFixed(1), n);
const tap = async (x, y) => { await moveTo(s, x, y); await sleep(250); await page.mouse.down(); await sleep(90); await page.mouse.up(); };
await sleep(1500); mark('start');
for (const [x, y] of [[700, 600], [860, 340], [560, 430], [900, 560]]) {
  await tap(x, y); mark('zoomed ' + x); await sleep(2600);
  await tap(720, 450); mark('back'); await sleep(2400);
}
await finish(s);
