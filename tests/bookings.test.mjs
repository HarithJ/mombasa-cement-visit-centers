import assert from 'node:assert/strict';
import { mkdtemp, rm, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage = await mkdtemp(join(tmpdir(), 'nyumba-bookings-'));
const launchServer = (database = join(storage, 'bookings.sqlite'), schedule = '') => spawn('php', ['-S', '127.0.0.1:8091', '-t', 'public'], { env: { ...process.env, BOOKING_DB: database, BOOKING_SESSION_PATH: storage, BOOKING_SCHEDULE: schedule }, stdio: 'ignore' });
let server = launchServer();
const ready = async () => { for (let i = 0; i < 50; i++) { try { if ((await fetch('http://127.0.0.1:8091')).ok) return; } catch {} await new Promise(resolve => setTimeout(resolve, 100)); } throw new Error('PHP server did not start'); };
const stop = async () => { server.kill(); await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve)); };
const records = () => JSON.parse(execFileSync('php', ['-r', '$db=new PDO("sqlite:".$argv[1]); echo json_encode($db->query("SELECT * FROM bookings ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));', join(storage, 'bookings.sqlite')], { encoding: 'utf8' }));
let browser;
try {
  await ready();
  browser = await chromium.launch({ executablePath: process.env.UI_BROWSER_PATH });
  const page = await browser.newPage();
  await page.goto('http://127.0.0.1:8091');
  await page.locator('[data-book="feeding"]').click();
  await page.locator('#full-name').fill('Alex Mwangi');
  await page.locator('#phone').fill('0712 345 678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-01-15');
  await page.locator('#time-slot').selectOption('11:00');
  await page.locator('#attendees').fill('4');
  const payload = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click({ timeout: 3000 });
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.match(await page.locator('#confirmation-view').innerText(), /Visit registered/);
  assert.match(await page.locator('.reference-label strong').innerText(), /^NY-[A-F0-9]{24}$/);
  assert.match(await page.locator('#confirmation-details').innerText(), /Kibarani Feeding center/);
  assert.match(await page.locator('#confirmation-details').innerText(), /4/);
  console.log('PASS Kibarani Feeding center group day booking is saved and confirmed');
  const reference = await page.locator('.reference-label strong').innerText();
  await page.reload();
  assert.equal(await page.locator('.reference-label strong').innerText(), reference);
  const replay = await page.request.post('http://127.0.0.1:8091/', { form: payload, maxRedirects: 0 });
  assert.equal(replay.status(), 303);
  assert.equal(records().length, 1);
  assert.equal(records()[0].phone, '+254712345678');
  assert.equal(records()[0].booked_time, '11:00');
  await stop(); server = launchServer(); await ready();
  await page.reload();
  assert.equal(await page.locator('.reference-label strong').innerText(), reference);
  assert.equal(records().length, 1);
  console.log('PASS replay, confirmation refresh and process restart preserve one booking');
  const noScript = await browser.newContext({ javaScriptEnabled: false });
  const nativePage = await noScript.newPage();
  await nativePage.goto('http://127.0.0.1:8091/');
  await nativePage.locator('[data-book="feeding"]').click();
  await nativePage.locator('#full-name').fill('Sam Test', { timeout: 3000 });
  await nativePage.locator('#phone').fill('+44 20 7946 0958'); await nativePage.locator('#email').fill('visitor@example.com');
  await nativePage.locator('#visit-date').fill('2099-01-20');
  await nativePage.locator('#time-slot').selectOption('11:00');
  await nativePage.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await nativePage.locator('#confirmation-view').waitFor({ state: 'visible', timeout: 3000 });
  assert.match(await nativePage.locator('#confirmation-view').innerText(), /Sam Test/);
  await noScript.close();
  console.log('PASS server-rendered booking and confirmation work without JavaScript');
  for (const [field, value, message] of [
    ['fullName', '', 'full name'], ['phone', 'abc', 'phone number'],
    ['attendees', '0', 'whole number'], ['attendees', '1.5', 'whole number'],
    ['visitDate', '2000-01-01', 'future date'], ['visitDate', '2099-02-30', 'valid visit date'],
    ['location', 'unknown', 'three destinations'], ['timeSlot', '09:00', 'time offered'],
  ]) {
    await page.goto('http://127.0.0.1:8091/');
    await page.locator('[data-book="feeding"]').click();
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST');
    await page.locator('form').evaluate((form, change) => {
      const values = { email: 'visitor@example.com', fullName: 'Retained Test', phone: '+44 20 7946 0958', location: 'feeding', visitDate: '2099-02-15', timeSlot: '11:00', attendees: '3', ...change };
      for (const [key, value] of Object.entries(values)) {
        let input = form.elements[key];
        if (input?.tagName === 'SELECT') input.add(new Option(value, value));
        if (key === 'overnight') { input = document.createElement('input'); input.type = 'hidden'; input.name = key; form.append(input); }
        input.value = value;
      }
      form.submit();
    }, { [field]: value });
    assert.equal((await responsePromise).status(), 422);
    await page.locator('#error-summary').waitFor({ state: 'visible' });
    assert.match(await page.locator('#error-summary').innerText(), new RegExp(message));
    if (field !== 'fullName') assert.equal(await page.locator('#full-name').inputValue(), 'Retained Test');
    assert.equal(records().length, 2);
  }
  console.log('PASS server rejects invalid fields and tampered destinations/slots without saving');
  for (const destination of ['sahajanand', 'galana']) {
    await page.goto('http://127.0.0.1:8091/'); await page.locator(`[data-book="${destination}"]`).click();
    assert.equal(await page.locator('#location').inputValue(), destination);
    await page.locator('#full-name').fill('Day Visitor'); await page.locator('#phone').fill('+1 (202) 555-0123'); await page.locator('#email').fill('visitor@example.com');
    await page.locator('#visit-date').fill('2099-02-15'); await page.locator('#time-slot').selectOption('14:00');
    await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
    await page.locator('#confirmation-view').waitFor({ state: 'visible' });
    assert.match(await page.locator('#confirmation-view').innerText(), /Visit registered/);
  }
  assert.equal(records().length, 4);
  console.log('PASS school and Galana Farm day visits are saved through their preselected forms');
  await page.goto('http://127.0.0.1:8091/'); await page.locator('[data-book="feeding"]').click();
  const escapedName = '<img src=x onerror="window.injected=true">';
  await page.locator('#full-name').fill(escapedName); await page.locator('#phone').fill('0712 345 678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-02-20'); await page.locator('#time-slot').selectOption('11:00');
  let rejection = page.waitForResponse(response => response.request().method() === 'POST');
  await page.locator('form').evaluate(form => { form.elements.csrf.value = 'forged'; form.submit(); });
  assert.equal((await rejection).status(), 403);
  await page.locator('#error-summary').waitFor({ state: 'visible' });
  assert.equal(await page.locator('#full-name').inputValue(), escapedName);
  assert.equal(records().length, 4);
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.match(await page.locator('#confirmation-details').innerText(), /<img src=x/);
  assert.equal(await page.locator('#confirmation-details img').count(), 0);
  assert.equal(await page.evaluate(() => !!window.injected), false);
  assert.equal(records().length, 5);
  for (const path of ['/var/bookings.sqlite', '/config/destinations.php', '/src/web.php', '/migrations/001-day-bookings.sql']) assert.equal((await page.request.get('http://127.0.0.1:8091' + path)).status(), 404);
  console.log('PASS CSRF rejection retains correctable input, submitted text is escaped, and private files are not served');
  await stop(); server = launchServer(storage); await ready();
  await page.goto('http://127.0.0.1:8091/'); await page.locator('[data-book="feeding"]').click();
  await page.locator('#full-name').fill('Retry Visitor'); await page.locator('#phone').fill('+254712345678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-03-01'); await page.locator('#time-slot').selectOption('11:00');
  rejection = page.waitForResponse(response => response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  assert.equal((await rejection).status(), 503);
  await page.locator('#error-summary').waitFor({ state: 'visible' });
  assert.match(await page.locator('#error-summary').innerText(), /could not save/);
  assert.equal(await page.locator('#confirmation-view').isVisible(), false);
  assert.equal(await page.locator('#full-name').inputValue(), 'Retry Visitor');
  assert.equal(records().length, 5);
  await stop(); server = launchServer(); await ready();
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.equal(records().length, 6);
  console.log('PASS failed storage never confirms, retains details, and permits a successful retry');
  for (let i = 0; i < 2; i++) execFileSync('php', ['bin/migrate.php'], { env: { ...process.env, BOOKING_DB: join(storage, 'bookings.sqlite') } });
  assert.equal(records().length, 6);
  assert.ok(records().every(record => record.status === 'automatically_confirmed' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(record.created_at)));
  const config = join(storage, 'schedule.php');
  await writeFile(config, "<?php return ['feeding'=>['name'=>'Kibarani Feeding center','slots'=>['11:30']],'sahajanand'=>['name'=>'Sahajanand Special School','slots'=>['09:00']],'galana'=>['name'=>'Galana Farm','slots'=>['14:00']]];");
  await stop(); server = launchServer(join(storage, 'bookings.sqlite'), config); await ready();
  await page.reload();
  assert.match(await page.locator('#confirmation-details').innerText(), /11:00/);
  await page.goto('http://127.0.0.1:8091/'); await page.locator('[data-book="feeding"]').click();
  assert.match(await page.locator('#time-slot').innerText(), /11:30/);
  assert.equal(records().at(-1).booked_time, '11:00');
  console.log('PASS repeated migrations retain records and schedule changes preserve booked times');
  await stop(); server = launchServer(); await ready();
  await mkdir('test-results', { recursive: true });
  const runtimeErrors = []; page.on('pageerror', error => runtimeErrors.push(error.message));
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 }); await page.goto('http://127.0.0.1:8091/');
    assert.equal(await page.locator('header a, header nav').count(), 0);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: `test-results/day-${width}-home.png`, animations: 'disabled' });
    await page.locator('[data-book="galana"]').click();
    assert.equal(await page.locator('#overnight').isDisabled(), false);
    await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
    assert.equal(await page.locator('#error-summary').isVisible(), true);
    await page.screenshot({ path: `test-results/day-${width}-validation.png`, animations: 'disabled' });
    await page.locator('#full-name').fill('Responsive Visitor'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
    await page.locator('#visit-date').fill('2099-04-01'); await page.locator('#time-slot').selectOption('09:00');
    const count = records().length;
    await page.locator('[type="submit"]').evaluate(button => { button.click(); button.click(); });
    await page.locator('#confirmation-view').waitFor({ state: 'visible' });
    assert.equal(records().length, count + 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: `test-results/day-${width}-confirmation.png`, animations: 'disabled' });
  }
  assert.deepEqual(runtimeErrors, []);
  console.log('PASS responsive day forms, validation, confirmation and repeated clicks at four viewport sizes');
  await page.goto('http://127.0.0.1:8091/');
  await page.locator('[data-book="sahajanand"]').focus(); await page.keyboard.press('Enter');
  assert.equal(await page.locator('#full-name').evaluate(el => el === document.activeElement), true);
  await page.locator('#location').selectOption('feeding');
  assert.match(await page.locator('#time-slot').innerText(), /11:00/);
  assert.doesNotMatch(await page.locator('#time-slot').innerText(), /9:00/);
  await page.locator('.close-button').focus(); await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.closest('dialog')?.id), 'booking-dialog');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('[data-book="sahajanand"]').evaluate(el => el === document.activeElement), true);
  for (const id of ['sahajanand', 'galana', 'feeding']) {
    const photo = page.locator(`[data-destination="${id}"] .destination-photo`);
    await photo.hover(); const initial = await photo.getAttribute('data-photo-index');
    await page.waitForTimeout(2700);
    assert.notEqual(await photo.getAttribute('data-photo-index'), initial);
    await page.mouse.move(0, 0); const stopped = await photo.getAttribute('data-photo-index');
    await page.waitForTimeout(2700); assert.equal(await photo.getAttribute('data-photo-index'), stopped);
  }
  await page.emulateMedia({ reducedMotion: 'reduce' }); await page.reload();
  const stillPhoto = page.locator('[data-destination="galana"] .destination-photo');
  await stillPhoto.hover(); await page.waitForTimeout(2700);
  assert.equal(await stillPhoto.getAttribute('data-active-layer'), '0');
  const touchContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });
  const touchPage = await touchContext.newPage(); await touchPage.goto('http://127.0.0.1:8091/');
  const toggle = touchPage.locator('[data-destination="galana"] .photo-toggle');
  await toggle.tap(); await toggle.tap();
  assert.equal(await touchPage.locator('[data-destination="galana"] .destination-photo').getAttribute('data-photo-index'), '2');
  const box = await toggle.boundingBox(); assert.ok(box.width >= 44 && box.height >= 44);
  await touchPage.locator('[data-book="galana"]').tap();
  assert.equal(await touchPage.locator('#location').inputValue(), 'galana');
  await touchContext.close();
  assert.deepEqual(runtimeErrors, []);
  console.log('PASS keyboard focus, dropdown slots, hover pause, reduced motion and real touch controls');
} finally {
  await browser?.close(); await stop();
  await rm(storage, { recursive: true, force: true });
}
