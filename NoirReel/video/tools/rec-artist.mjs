// Adegan artist: grid poster -> buka popup (video) -> artist berikutnya -> tutup -> scroll sisa lineup.
const play = (page) => page.evaluate(() => { const v = document.querySelector('#ar-body video'); if (v) { v.muted = true; return v.play().then(() => v.currentTime).catch((e) => 'err ' + e.message); } return 'no video'; });
import {openSession, BASE, sleep, moveTo, click, scrollBy, finish} from './lib.mjs';

const s = await openSession({name: 'artist', start: [1000, 430]});
const {page} = s;
await page.goto(BASE + '/artist', {waitUntil: 'load'});
await sleep(1800);                                   // kartu muncul bergantian
await s.startRec();
await sleep(1000);
await moveTo(s, 760, 520, {ms: 800, overshoot: false});
await sleep(900);
await scrollBy(s, 330, {ms: 1400});
await sleep(900);

await click(s, page.locator('.ar-card[data-i="1"]'), {pause: [420, 600], after: 400});   // Strange Fruit (video)
console.log('video 1:', await play(page));
await sleep(2800);
await click(s, page.locator('#ar-next'), {pause: [300, 450], after: 400});               // White Shoes & The Couples Company
console.log('video 2:', await play(page));
await sleep(2800);
await click(s, page.locator('#ar-close'), {pause: [300, 450], after: 1100});
await scrollBy(s, 760, {ms: 1800});
await sleep(1000);
await scrollBy(s, 700, {ms: 1700});
await sleep(1600);
await finish(s);
