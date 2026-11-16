// Adegan 5: rekap peralatan (admin).
import {openSession, sleep, moveTo, scrollBy, typeHuman, finish, adminLogin, BASE} from './lib.mjs';

const s = await openSession({name: 'gear', start: [860, 420]});
const {page} = s;
await adminLogin(s);
await page.goto(BASE + '/admin/gear-recap', {waitUntil: 'load'});
await sleep(700);
await s.startRec();
await sleep(900);
await moveTo(s, 700, 290, {ms: 800, overshoot: false});
await sleep(1100);
await moveTo(s, 760, 470, {ms: 700});
await sleep(800);
await scrollBy(s, 360, {ms: 1500});
await sleep(900);
await typeHuman(s, page.locator('input[placeholder^="Cari nama"]').first(), 'Bima');
await sleep(2000);
await finish(s);
