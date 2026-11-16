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
  await page.locator('.hero-button').click();
  await page.locator('#full-name').fill('Alex Mwangi');
  await page.locator('#phone').fill('0712 345 678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-01-15');
  await page.locator('#time-slot').selectOption('11:00');
  await page.locator('#attendees').fill('4');
  const payload = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click({ timeout: 3000 });
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  const confirmationText = await page.locator('#confirmation-view').innerText();
  assert.match(confirmationText, /Request received\./);
  assert.match(confirmationText, /We've sent your request to the Galana team\. They'll confirm by email and SMS, usually within a day\. Please don't travel until your visit is confirmed\./);
  assert.match(await page.locator('.confirmation-preview').innerText(), /^Status: Awaiting confirmation$/);
  assert.doesNotMatch(confirmationText, /Visit registered|capacity check/);
  assert.match(await page.locator('.reference-label strong').innerText(), /^NY-[A-F0-9]{24}$/);
  assert.equal(records()[0].status, 'pending');
  assert.match(await page.locator('#confirmation-details').innerText(), /Galana Farm/);
  assert.match(await page.locator('#confirmation-details').innerText(), /4/);
  console.log('PASS Galana Farm group day booking is saved as a pending request');
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
  await nativePage.locator('.hero-button').click();
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
    ['location', 'unknown', 'only be booked at Galana Farm'], ['timeSlot', '08:00', 'time offered'],
  ]) {
    await page.goto('http://127.0.0.1:8091/');
    await page.locator('.hero-button').click();
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST');
    await page.locator('form').evaluate((form, change) => {
      const values = { email: 'visitor@example.com', fullName: 'Retained Test', phone: '+44 20 7946 0958', location: 'galana', visitDate: '2099-02-15', timeSlot: '11:00', attendees: '3', ...change };
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
  console.log('PASS server rejects invalid fields, and tampered destinations/slots without saving');
  for (const destination of ['galana']) {
    await page.goto('http://127.0.0.1:8091/'); await page.locator('.hero-button').click();
    assert.equal(await page.locator('#location').inputValue(), destination);
    await page.locator('#full-name').fill('Day Visitor'); await page.locator('#phone').fill('+1 (202) 555-0123'); await page.locator('#email').fill('visitor@example.com');
    await page.locator('#visit-date').fill('2099-02-15'); await page.locator('#time-slot').selectOption('14:00');
    await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
    await page.locator('#confirmation-view').waitFor({ state: 'visible' });
    assert.match(await page.locator('#confirmation-view').innerText(), /Request received\./);
  }
  assert.equal(records().length, 3);
  console.log('PASS Galana Farm day visits are saved through the preselected form');
  await page.goto('http://127.0.0.1:8091/'); await page.locator('.hero-button').click();
  const escapedName = '<img src=x onerror="window.injected=true">';
  await page.locator('#full-name').fill(escapedName); await page.locator('#phone').fill('0712 345 678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-02-20'); await page.locator('#time-slot').selectOption('11:00');
  let rejection = page.waitForResponse(response => response.request().method() === 'POST');
  await page.locator('form').evaluate(form => { form.elements.csrf.value = 'forged'; form.submit(); });
  assert.equal((await rejection).status(), 403);
  await page.locator('#error-summary').waitFor({ state: 'visible' });
  assert.equal(await page.locator('#full-name').inputValue(), escapedName);
  assert.equal(records().length, 3);
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.match(await page.locator('#confirmation-details').innerText(), /<img src=x/);
  assert.equal(await page.locator('#confirmation-details img').count(), 0);
  assert.equal(await page.evaluate(() => !!window.injected), false);
  assert.equal(records().length, 4);
  for (const path of ['/var/bookings.sqlite', '/config/destinations.php', '/src/web.php', '/migrations/001-day-bookings.sql']) assert.equal((await page.request.get('http://127.0.0.1:8091' + path)).status(), 404);
  console.log('PASS CSRF rejection retains correctable input, submitted text is escaped, and private files are not served');
  await stop(); server = launchServer(storage); await ready();
  await page.goto('http://127.0.0.1:8091/'); await page.locator('.hero-button').click();
  await page.locator('#full-name').fill('Retry Visitor'); await page.locator('#phone').fill('+254712345678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-03-01'); await page.locator('#time-slot').selectOption('11:00');
  rejection = page.waitForResponse(response => response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  assert.equal((await rejection).status(), 503);
  await page.locator('#error-summary').waitFor({ state: 'visible' });
  assert.match(await page.locator('#error-summary').innerText(), /could not save/);
  assert.equal(await page.locator('#confirmation-view').isVisible(), false);
  assert.equal(await page.locator('#full-name').inputValue(), 'Retry Visitor');
  assert.equal(records().length, 4);
  await stop(); server = launchServer(); await ready();
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.equal(records().length, 5);
  console.log('PASS failed storage never confirms, retains details, and permits a successful retry');
  for (let i = 0; i < 2; i++) execFileSync('php', ['bin/migrate.php'], { env: { ...process.env, BOOKING_DB: join(storage, 'bookings.sqlite') } });
  assert.equal(records().length, 5);
  assert.ok(records().every(record => record.status === 'pending' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(record.created_at)));
  const config = join(storage, 'schedule.php');
  await writeFile(config, "<?php return ['galana'=>['name'=>'Galana Farm','slots'=>['14:00']]];");
  await stop(); server = launchServer(join(storage, 'bookings.sqlite'), config); await ready();
  await page.reload();
  assert.match(await page.locator('#confirmation-details').innerText(), /11:00/);
  await page.goto('http://127.0.0.1:8091/'); await page.locator('.hero-button').click();
  assert.match(await page.locator('#time-slot').innerText(), /2:00 pm/);
  assert.equal(records().at(-1).booked_time, '11:00');
  console.log('PASS repeated migrations retain records and schedule changes preserve booked times');
  await stop(); server = launchServer(); await ready();
  await mkdir('test-results', { recursive: true });
  const runtimeErrors = []; page.on('pageerror', error => runtimeErrors.push(error.message));
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 }); await page.goto('http://127.0.0.1:8091/');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: `test-results/day-${width}-home.png`, animations: 'disabled' });
    await page.locator('.hero-button').click();
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
  // Every "book" link on the page leads to the form on the page, which is set up for Galana Farm.
  await page.setViewportSize({ width: 1440, height: 900 }); await page.goto('http://127.0.0.1:8091/');
  for (const link of await page.locator('a[href="#book"], a[href="/#book"]').all()) assert.equal(await link.evaluate(a => new URL(a.href).hash), '#book');
  for (const id of ['day', 'getting-there', 'questions', 'book']) assert.equal(await page.locator(`.site-nav a[href="#${id}"]`).count(), 1, `the menu links to #${id}`);
  for (const id of ['day', 'getting-there', 'questions', 'book']) assert.equal(await page.locator(`section#${id}`).count(), 1, `#${id} exists`);
  await page.locator('.hero-button').focus(); await page.keyboard.press('Enter');
  await page.waitForFunction(() => location.hash === '#book');
  await page.waitForFunction(() => { const r = document.querySelector('#book').getBoundingClientRect(); return r.top < innerHeight && r.bottom > 0; });
  assert.equal(await page.locator('#location').getAttribute('type'), 'hidden');
  assert.equal(await page.locator('#location').inputValue(), 'galana');
  assert.match(await page.locator('#time-slot').innerText(), /4:00 pm/);
  assert.equal(await page.locator('dialog#booking-dialog').count(), 0, 'no booking pop-up any more');
  console.log('PASS menu and book links reach the on-page Galana Farm form by keyboard');
  // "Book another visit" brings back an empty form in place, without leaving the page.
  await page.locator('#full-name').fill('Second Visitor'); await page.locator('#phone').fill('0712345678');
  await page.locator('#visit-date').fill('2099-04-02'); await page.locator('#time-slot').selectOption('11:00');
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.match(page.url(), /\?confirmation=1#book$/);
  await page.locator('.confirmation-close').click();
  await page.locator('#form-view').waitFor({ state: 'visible' });
  assert.equal(await page.locator('#confirmation-view').isVisible(), false);
  assert.equal(await page.locator('#full-name').inputValue(), '');
  assert.equal(await page.locator('#full-name').evaluate(el => el === document.activeElement), true);
  assert.match(page.url(), /\/#book$/);
  assert.equal(await page.locator('#location').inputValue(), 'galana');
  console.log('PASS "Book another visit" resets the form in place');
  // With reduced motion nothing is animated: the day and the map are shown at once, the photos as soon as they're reached.
  await page.emulateMedia({ reducedMotion: 'reduce' }); await page.goto('http://127.0.0.1:8091/');
  assert.equal(await page.locator('[data-day-step]:not(.is-on)').count(), 0);
  assert.equal(await page.locator('[data-map].is-drawn').count(), 1);
  await page.locator('[data-scatter]').scrollIntoViewIfNeeded();
  await page.locator('[data-scatter].is-thrown.is-landed').waitFor({ timeout: 1000 });
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  // Real touch: the hero link, a photo from the pile and the viewer's controls.
  const touchContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });
  const touchPage = await touchContext.newPage(); await touchPage.goto('http://127.0.0.1:8091/');
  const topPrint = touchPage.locator('[data-print][data-full$="02-circular-fields-aerial.webp"]');
  await topPrint.scrollIntoViewIfNeeded(); await topPrint.tap();
  await touchPage.locator('[data-print-viewer][open]').waitFor();
  for (const control of ['.print-viewer-next', '.print-viewer-close']) {
    const box = await touchPage.locator(control).boundingBox(); assert.ok(box.width >= 44 && box.height >= 44, `${control} is big enough to tap`);
  }
  await touchPage.locator('.print-viewer-next').tap();
  await touchPage.locator('.print-viewer-close').tap();
  await touchPage.locator('[data-print-viewer]').waitFor({ state: 'hidden' });
  await touchPage.evaluate(() => scrollTo(0, 0)); await touchPage.locator('.hero-button').tap();
  await touchPage.waitForFunction(() => location.hash === '#book');
  assert.equal(await touchPage.locator('#location').inputValue(), 'galana');
  await touchContext.close();
  assert.deepEqual(runtimeErrors, []);
  console.log('PASS reduced motion and real touch controls on the photos and the booking link');
} finally {
  await browser?.close(); await stop();
  await rm(storage, { recursive: true, force: true });
}
