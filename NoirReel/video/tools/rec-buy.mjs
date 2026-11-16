// Adegan 3: beli tiket. Detail event -> pilih tiket + add-on -> isi data -> QRIS -> lunas -> tiket.
import {openSession, BASE, sleep, moveTo, click, scrollBy, typeHuman, finish, centerOf, sql} from './lib.mjs';

const EMAIL = 'raka.pratama21@example.com';
const s = await openSession({name: 'buy', start: [1020, 300]});
const {page} = s;
await page.goto(BASE + '/detail-event?id=18', {waitUntil: 'load'});
await sleep(900);
await s.startRec();
await sleep(900);
await scrollBy(s, 640, {ms: 1500});
await sleep(700);

const block = page.locator('[data-ticket-block]').first();
const qty = block.locator('select.qty-select:not(.addon-select)').first();
await moveTo(s, ...(await centerOf(s, qty)));
await sleep(450);
await qty.selectOption('2');
await sleep(900);

const addon = block.locator('select.addon-select').first();
if (await addon.count()) {
  await addon.scrollIntoViewIfNeeded();
  await moveTo(s, ...(await centerOf(s, addon)));
  await sleep(400);
  await addon.selectOption('1');
  await sleep(800);
}
await scrollBy(s, 300, {ms: 1000});
await sleep(600);
await click(s, page.locator('#btn-order'), {pause: [350, 500]});

// halaman pembayaran
await page.waitForURL(/payment/);
await sleep(1200);
await typeHuman(s, page.locator('input[name="buyer_name"]'), 'Raka Pratama');
await sleep(200);
await typeHuman(s, page.locator('input[name="buyer_email"]'), EMAIL);
await sleep(200);
await typeHuman(s, page.locator('input[name="buyer_phone"]'), '081234567890');
await sleep(500);
await scrollBy(s, 420, {ms: 1100});
await sleep(900);
await click(s, page.locator('#submitBtn'), {pause: [350, 500]});

// QRIS tampil, lalu "dibayar"
await page.waitForURL(/order-status/);
await page.waitForSelector('#qrcode img', {state: 'visible'});
await sleep(3200);
sql(`update tickets t join orders o on o.ticket_id=t.id set t.sold=t.sold+o.qty where o.buyer_email='${EMAIL}' and o.status='pending';
     update orders set status='confirmed', confirmed_at=NOW() where buyer_email='${EMAIL}' and status='pending';`);
await page.waitForFunction(() => !document.querySelector('#qrcode'), null, {timeout: 15000});
await sleep(2600);
await scrollBy(s, 260, {ms: 900});
await sleep(1400);
await finish(s);
