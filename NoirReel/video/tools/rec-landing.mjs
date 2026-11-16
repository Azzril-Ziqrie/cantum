// Adegan 1+2: landing Noirlab -> klik Bonafest -> "tap to enter" -> scroll landing Bonafest.
import {openSession, BASE, sleep, moveTo, click, scrollBy, finish} from './lib.mjs';

const s = await openSession({name: 'landing'});
const {page} = s;
await page.goto(BASE + '/', {waitUntil: 'load'});
await sleep(900);
await s.startRec();
await moveTo(s, 760, 430, {ms: 700, overshoot: false});
await sleep(900);
await scrollBy(s, 520, {ms: 1500});
await sleep(900);
await scrollBy(s, -520, {ms: 1100});
await sleep(500);
await click(s, page.locator('a', {hasText: /^Bonafest$/}).first(), {after: 1400});
// intro "TAP TO ENTER"
await click(s, page.getByText(/tap to enter/i).first(), {pause: [500, 800], after: 2200});
await scrollBy(s, 700, {ms: 1600});
await sleep(1000);
await scrollBy(s, 800, {ms: 1700});
await sleep(1000);
await finish(s);
