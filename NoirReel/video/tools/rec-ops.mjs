// Adegan 6: dashboard penjualan lalu scan tiket di gerbang (admin).
import {openSession, sleep, moveTo, click, scrollBy, typeHuman, finish, adminLogin, BASE} from './lib.mjs';

const CODE = process.env.TICKET_CODE ?? 'NL-CPCCDP';
const s = await openSession({name: 'ops', start: [860, 300]});
const {page} = s;
await adminLogin(s);
await page.goto(BASE + '/admin/', {waitUntil: 'load'});
await sleep(700);
await s.startRec();
await sleep(900);
await moveTo(s, 620, 250, {ms: 700, overshoot: false});
await sleep(700);
await scrollBy(s, 180, {ms: 1100});
await sleep(500);
await click(s, page.getByRole('link', {name: 'Semua'}).first(), {after: 1800});
await moveTo(s, 700, 650, {ms: 900});
await sleep(1500);
// pindah ke scan tiket lewat sidebar
await scrollBy(s, -180, {ms: 900});
await click(s, page.locator('a', {hasText: /^Scan Ticket$/}).first(), {after: 1200});
await page.waitForSelector('#codeInput');
await typeHuman(s, page.locator('#codeInput'), CODE);
await sleep(400);
await click(s, page.locator('#searchForm button[type="submit"], #searchForm button').first(), {after: 1500});
const btns = await page.locator('#scanResult button').allInnerTexts();
console.log('scanResult buttons:', JSON.stringify(btns));
const enter = page.locator('#scanResult button').filter({hasText: /enter|let|confirm|masuk/i}).first();
if (await enter.count()) { await click(s, enter, {after: 2200}); }
await sleep(1200);
await finish(s);
