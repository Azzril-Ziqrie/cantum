// Adegan "tiket diterima": kotak masuk -> buka email tiket asli -> scroll ke QR dan kode tiket.
import {openSession, BASE, sleep, moveTo, click, scrollBy, finish} from './lib.mjs';

const s = await openSession({name: 'mail', start: [1000, 520]});
const {page} = s;
await page.goto(BASE + '/reel-mail', {waitUntil: 'load'});
await sleep(700);
await s.startRec();
await sleep(1100);
await moveTo(s, 760, 330, {ms: 800, overshoot: false});
await sleep(900);
await click(s, page.locator('#open'), {pause: [450, 650], after: 1500});
await moveTo(s, 820, 560, {ms: 700});
await sleep(500);
await scrollBy(s, 330, {ms: 1500});
await sleep(1100);
await scrollBy(s, 280, {ms: 1400});
await sleep(2200);
await finish(s);
