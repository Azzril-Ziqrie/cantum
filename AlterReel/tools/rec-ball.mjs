// Rekam alter-that.com: bola foto berputar, scroll masuk ke dalam bola, lalu scroll keluar lagi.
import {openSession, sleep, moveTo, scrollBy, finish} from './lib.mjs';
const s = await openSession({name: 'ball', start: [900, 300]});
const {page} = s;
await page.goto('https://alter-that.com/', {waitUntil: 'load'});
await sleep(7000);                       // tunggu foto termuat
await moveTo(s, 720, 440, {ms: 500, overshoot: false});
await s.startRec();
await sleep(1800);                       // bola berputar
await scrollBy(s, -2600, {ms: 3200});    // masuk ke bola
await sleep(1400);
await scrollBy(s, 2600, {ms: 3200});     // keluar dari bola
await sleep(1800);
await finish(s);
