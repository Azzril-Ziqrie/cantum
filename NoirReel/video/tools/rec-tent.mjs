// Adegan 4: plot tenda BONAFEST (admin). Klik tenda kosong, isi nama dari order, seret tenda, cari tamu.
import {openSession, sleep, moveTo, click, scrollBy, typeHuman, finish, adminLogin, centerOf, BASE} from './lib.mjs';

const s = await openSession({name: 'tent', start: [900, 500]});
const {page} = s;
await adminLogin(s);
await page.goto(BASE + '/admin/tent-plot', {waitUntil: 'load'});
await page.waitForSelector('#grid .tent');
await sleep(800);
await s.startRec();
await sleep(1000);

// lihat denah
await moveTo(s, 720, 520, {ms: 800, overshoot: false});
await sleep(700);
await scrollBy(s, 330, {ms: 1500});
await sleep(900);

// klik tenda kosong -> panel detail
const empty = page.locator('#grid .tent[data-id]').filter({has: page.locator('.lab', {hasText: /^40$/})}).first();
await click(s, empty, {pause: [380, 520]});
await sleep(800);
// cari pembeli dari order dan pilih
await typeHuman(s, page.locator('#fFind'), 'Bima');
await page.waitForSelector('#sugg button', {state: 'visible'});
await sleep(900);
await click(s, page.locator('#sugg button').first(), {pause: [300, 450]});
await sleep(900);
await click(s, page.locator('#fSave'), {pause: [350, 500]});
await sleep(1300);

// seret tenda ke sel kosong
const src = page.locator('#grid .tent').filter({has: page.locator('.lab', {hasText: /^41$/})}).first();
const dst = page.locator('#grid .cell[data-r="13"][data-c="3"]').first();
await src.scrollIntoViewIfNeeded();
await moveTo(s, ...(await centerOf(s, src)));
await sleep(350);
await page.mouse.down(); await sleep(160);
const [dx, dy] = await centerOf(s, dst, 0.05);
await moveTo(s, dx, dy, {ms: 900, overshoot: false});
await sleep(220);
await page.mouse.up();
await sleep(1500);

// cari tamu di denah
await scrollBy(s, -420, {ms: 1100});
await sleep(500);
await typeHuman(s, page.locator('#inFind'), 'Dewi');
await sleep(2400);
await finish(s);
