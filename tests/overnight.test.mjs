import assert from 'node:assert/strict';
import { mkdtemp, rm, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage = await mkdtemp(join(tmpdir(), 'nyumba-overnight-'));
const database = join(storage, 'bookings.sqlite');
const server = spawn('php', ['-S', '127.0.0.1:8092', '-t', 'public'], { env: { ...process.env, BOOKING_DB: database, BOOKING_SESSION_PATH: storage }, stdio: 'ignore' });
const records = () => JSON.parse(execFileSync('php', ['-r', '$db=new PDO("sqlite:".$argv[1]);echo json_encode($db->query("SELECT * FROM bookings ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));', database], { encoding: 'utf8' }));
let browser;
try {
  for (let i = 0; i < 50; i++) { try { if ((await fetch('http://127.0.0.1:8092/')).ok) break; } catch {} await new Promise(resolve => setTimeout(resolve, 100)); }
  browser = await chromium.launch({ executablePath: process.env.UI_BROWSER_PATH });
  const page = await browser.newPage(); page.setDefaultTimeout(4000);
  const noStayFields = async target => {
    for (const selector of ['#arrival-date', '#departure-date', '#overnight-guests', '#overnight-fields']) assert.equal(await target.locator(selector).count(), 0, `${selector} is gone`);
    assert.doesNotMatch(await target.locator('#overnight-option').innerText(), /preferred dates|staying guests/i);
  };
  const oneNight = text => { assert.match(text, /Overnight stay\s+Requested/); assert.doesNotMatch(text, /Staying guests|Arrival|Departure|–/); };
  await page.goto('http://127.0.0.1:8092/'); await page.locator('[data-book="galana"]').click();
  await page.locator('#full-name').fill('Overnight Visitor'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-05-01'); await page.locator('#time-slot').selectOption('09:00');
  await page.locator('#attendees').fill('4'); await page.locator('#overnight').check();
  await noStayFields(page);
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  oneNight(await page.locator('#confirmation-details').innerText());
  assert.doesNotMatch(await page.locator('#confirmation-details').innerText(), /2 May 2099/);
  assert.equal(records().length, 1);
  assert.equal(records()[0].overnight, 1);
  assert.equal(records()[0].arrival_date, '2099-05-01');
  assert.equal(records()[0].departure_date, '2099-05-02');
  assert.equal(records()[0].overnight_guests, 4);
  console.log('PASS a ticked checkbox is the whole request and stores one night for the group');
  await page.goto('http://127.0.0.1:8092/'); await page.locator('[data-book="galana"]').click();
  await page.locator('form').evaluate(form => {
    const values = { email: 'visitor@example.com', fullName: 'Tampered Overnight', phone: '0712345678', location: 'galana', visitDate: '2099-12-31', timeSlot: '11:00', attendees: '6', overnight: 'on', arrivalDate: '2099-04-30', departureDate: '2100-02-15', overnightGuests: '99' };
    for (const [key, value] of Object.entries(values)) {
      let input = form.elements[key];
      if (!input || key === 'overnight') { input = document.createElement('input'); input.name = key; input.type = 'hidden'; form.append(input); }
      else if (input.tagName === 'INPUT') input.type = 'text';
      input.disabled = false; input.value = value;
    }
    form.submit();
  });
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  await page.reload(); oneNight(await page.locator('#confirmation-details').innerText());
  assert.equal(records().length, 2);
  const tampered = records().at(-1);
  assert.equal(tampered.arrival_date, '2099-12-31'); assert.equal(tampered.departure_date, '2100-01-01'); assert.equal(tampered.overnight_guests, 6);
  console.log('PASS submitted dates and guest counts are ignored; departure is the next calendar day');
  for (const [value, message] of [['invalid', 'whether you would like']]) {
    await page.goto('http://127.0.0.1:8092/'); await page.locator('[data-book="galana"]').click();
    const response = page.waitForResponse(response => response.request().method() === 'POST');
    await page.locator('form').evaluate((form, value) => {
      for (const [key, v] of Object.entries({ email: 'visitor@example.com', fullName: 'Retained Overnight', phone: '0712345678', location: 'galana', visitDate: '2099-05-01', timeSlot: '09:00', attendees: '4', overnight: value })) {
        let input = form.elements[key];
        if (key === 'overnight') { input = document.createElement('input'); input.name = key; input.type = 'hidden'; form.append(input); }
        else if (input.tagName === 'INPUT') input.type = 'text';
        input.disabled = false; input.value = v;
      }
      form.submit();
    }, value);
    assert.equal((await response).status(), 422);
    await page.locator('#error-summary').waitFor({ state: 'visible' });
    assert.match(await page.locator('#error-summary').innerText(), new RegExp(message));
    assert.equal(await page.locator('#full-name').inputValue(), 'Retained Overnight');
    assert.equal(await page.locator('#overnight').isChecked(), true);
    assert.match(await page.locator('#overnight-error').innerText(), new RegExp(message));
    assert.equal(records().length, 2);
  }
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' });
  assert.equal(records().length, 3);
  console.log('PASS an invalid overnight value preserves entries without saving and a correction saves exactly once');
  await page.goto('http://127.0.0.1:8092/'); await page.locator('[data-book="galana"]').click();
  await page.locator('#full-name').fill('Replay Night'); await page.locator('#phone').fill('+44 20 7946 0958'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-05-01'); await page.locator('#time-slot').selectOption('11:00');
  await page.locator('#attendees').fill('4'); await page.locator('#overnight').check();
  const replay = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
  await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await page.locator('#confirmation-view').waitFor({ state: 'visible' }); await page.reload();
  oneNight(await page.locator('#confirmation-details').innerText());
  assert.equal((await page.request.post('http://127.0.0.1:8092/', { form: replay, maxRedirects: 0 })).status(), 303);
  assert.equal(records().length, 4); assert.equal(records().at(-1).departure_date, '2099-05-02');
  console.log('PASS replay and refresh preserve one overnight request');
  // Kibarani and Sahajanand take interest registrations only, so Galana Farm is the one bookable destination.
  for (const destination of ['galana']) {
    await page.goto('http://127.0.0.1:8092/'); await page.locator('[data-book="galana"]').click();
    await page.locator('#full-name').fill('Day Only'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
    await page.locator('#visit-date').fill('2099-05-01'); await page.locator('#overnight').check();
    if (destination === 'galana') await page.locator('#overnight').uncheck();
    else {
      await page.keyboard.press('Escape');
      await page.locator(`[data-book="${destination}"]`).click();
      await page.locator('#full-name').fill('Day Only'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
      await page.locator('#visit-date').fill('2099-05-01');
    }
    assert.equal(await page.locator('#location').inputValue(), destination);
    assert.equal(await page.locator('#location').getAttribute('type'), 'hidden');
    assert.equal(await page.locator('#overnight').isChecked(), false);
    await page.locator('#time-slot').selectOption(destination === 'feeding' ? '11:00' : '09:00');
    const count = records().length;
    await page.locator('form').evaluate((form, location) => {
      for (const [name, value] of Object.entries({ arrivalDate: 'bad stale arrival', departureDate: 'bad stale departure', overnightGuests: '999.5', ...(location === 'galana' ? {} : { overnight: 'on' }) })) {
        const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
      }
      form.submit();
    }, destination);
    await page.locator('#confirmation-view').waitFor({ state: 'visible' });
    assert.equal(records().length, count + 1);
    const saved = records().at(-1);
    assert.equal(saved.overnight, 0); assert.equal(saved.arrival_date, null);
    assert.equal(saved.departure_date, null); assert.equal(saved.overnight_guests, null);
    assert.doesNotMatch(await page.locator('#confirmation-details').innerText(), /Overnight stay|Staying guests/);
  }
  console.log('PASS unchecked day bookings discard even tampered stale overnight fields');
  const legacy = join(storage, 'legacy.sqlite');
  execFileSync('php', ['-r', '$db=new PDO("sqlite:".$argv[1]);$db->exec(file_get_contents("migrations/001-day-bookings.sql"));$db->prepare("INSERT INTO bookings(reference,submission_token,full_name,phone,destination,visit_date,booked_time,attendees,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute(json_decode($argv[2],true));', legacy, JSON.stringify(['NY-LEGACY','legacy-token','Existing Day','+254712345678','feeding','2099-05-01','11:00',2,'automatically_confirmed','2026-09-15T12:00:00Z'])]);
  for (let i = 0; i < 2; i++) execFileSync('php', ['bin/migrate.php'], { env: { ...process.env, BOOKING_DB: legacy } });
  const migrated = JSON.parse(execFileSync('php', ['-r', '$db=new PDO("sqlite:".$argv[1]);echo json_encode($db->query("SELECT * FROM bookings")->fetchAll(PDO::FETCH_ASSOC));', legacy], { encoding: 'utf8' }));
  assert.equal(migrated.length, 1); assert.equal(migrated[0].reference, 'NY-LEGACY');
  assert.equal(migrated[0].full_name, 'Existing Day'); assert.equal(migrated[0].booked_time, '11:00');
  assert.equal(migrated[0].overnight, 0); assert.equal(migrated[0].arrival_date, null);
  assert.equal(migrated[0].departure_date, null); assert.equal(migrated[0].overnight_guests, null);
  console.log('PASS repeatable migration preserves a pre-existing day booking with null overnight details');
  const native = await browser.newContext({ javaScriptEnabled: false });
  const nativePage = await native.newPage(); nativePage.setDefaultTimeout(4000);
  await nativePage.goto('http://127.0.0.1:8092/?book=galana');
  await nativePage.locator('#full-name').fill('Native Overnight'); await nativePage.locator('#phone').fill('0712345678'); await nativePage.locator('#email').fill('visitor@example.com');
  await nativePage.locator('#visit-date').fill('2099-06-01'); await nativePage.locator('#time-slot').selectOption('11:00');
  await nativePage.locator('#attendees').fill('3'); await noStayFields(nativePage); await nativePage.locator('#overnight').check();
  await nativePage.getByRole('button', { name: 'Book my visit', exact: true }).click();
  await nativePage.locator('#confirmation-view').waitFor({ state: 'visible' });
  oneNight(await nativePage.locator('#confirmation-details').innerText());
  assert.equal(records().at(-1).departure_date, '2099-06-02'); assert.equal(records().at(-1).overnight_guests, 3);
  await native.close();
  console.log('PASS overnight booking and saved summary work without JavaScript');
  await mkdir('test-results', { recursive: true });
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 }); await page.goto('http://127.0.0.1:8092/');
    await page.locator('[data-book="galana"]').click();
    await page.locator('#full-name').fill('Visual Overnight'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
    await page.locator('#visit-date').fill('2099-07-01'); await page.locator('#time-slot').selectOption('14:00');
    await page.locator('#attendees').fill('4'); await page.locator('#overnight').check();
    await page.locator('#full-name').fill('');
    await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
    assert.equal(await page.locator('#full-name').getAttribute('aria-invalid'), 'true');
    const closeBox = await page.locator('.close-button').boundingBox();
    const shellBox = await page.locator('.dialog-shell').boundingBox();
    assert.ok(closeBox.y + closeBox.height <= shellBox.y, 'close control never overlays scrolling form fields');
    await page.screenshot({ path: `test-results/overnight-${width}-validation.png`, animations: 'disabled' });
    await page.locator('#full-name').fill('Visual Overnight');
    await page.locator('#booking-title').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `test-results/overnight-${width}-form.png`, animations: 'disabled' });
    const count = records().length;
    await page.getByRole('button', { name: 'Book my visit', exact: true }).click();
    await page.locator('#confirmation-view').waitFor({ state: 'visible' });
    assert.equal(records().length, count + 1);
    oneNight(await page.locator('#confirmation-details').innerText());
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: `test-results/overnight-${width}-confirmation.png`, animations: 'disabled' });
  }
  assert.deepEqual(errors, []);
  console.log('PASS overnight validation, corrected save and confirmation fit mobile, tablet and desktop');
} finally {
  await browser?.close(); server.kill();
  await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve));
  await rm(storage, { recursive: true, force: true });
}
