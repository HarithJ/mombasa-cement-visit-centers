import assert from 'node:assert/strict';
import {mkdtemp, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage=await mkdtemp(join(tmpdir(),'nyumba-contacts-'));
const origin='http://127.0.0.1:8094';
const server=spawn('php',['-S','127.0.0.1:8094','-t','public'],{env:{...process.env,BOOKING_DB:join(storage,'bookings.sqlite'),BOOKING_SESSION_PATH:storage},stdio:'ignore'});
let browser;
try {
 for(let i=0;i<50;i++){try{if((await fetch(origin)).ok)break;}catch{} await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const emails=['jaco@nyumbagri.com','pravin@nyumbagri.com'];
 for(const javaScriptEnabled of [true,false]) {
  const context=await browser.newContext({javaScriptEnabled,viewport:{width:390,height:844}});
  const page=await context.newPage();
  await page.goto(`${origin}/`);
  // Getting there: the drawn map with its credit, drive times, Google Maps and the owner's route drawing.
  const there=page.locator('#getting-there');
  assert.equal(await there.locator('svg.map-svg').count(),1);
  assert.match(await there.locator('.map-card figcaption').innerText(),/OpenStreetMap contributors/);
  assert.match(await there.locator('.routes').innerText(),/Malindi[\s\S]*Mombasa/);
  assert.equal(await there.locator('iframe').count(),0,'no third-party map embed');
  assert.match(await there.getByRole('link',{name:/Google Maps/}).getAttribute('href'),/^https:\/\/(www\.)?google\.[a-z.]+\/maps|^https:\/\/maps\.app\.goo\.gl\//);
  const drawing=await there.getByRole('link',{name:/route drawing/}).getAttribute('href');
  assert.match(drawing,/\/routes\/galana\.png$/);
  assert.equal((await page.request.get(origin+drawing)).status(),200);
  // Questions: the team to call or email about changes.
  const team=page.locator('#questions');
  assert.deepEqual(await team.locator('a[href^="mailto:"]').evaluateAll(links=>links.map(a=>a.getAttribute('href'))),emails.map(e=>'mailto:'+e));
  assert.equal(await team.locator('a[href^="tel:+254"]').count(),emails.length);
  // After booking, the confirmation names the same team.
  await page.locator('#full-name').fill('Contact Test');
  await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
  assert.equal(await page.locator('#email').getAttribute('required'),null);
  await page.locator('#visit-date').fill('2099-05-01');
  await page.locator('#time-slot').selectOption('11:00');
  await page.locator('#attendees').fill('1');
  await page.locator('[type=submit]').click();
  await page.waitForURL(/confirmation=1/);
  assert.match(await page.locator('#confirmation-details').innerText(), /visitor@example.com/);
  assert.match(await page.locator('#confirmation-details').innerText(), /Galana Farm/);
  const contacts=page.locator('[data-contact-destination]:visible');
  assert.equal(await contacts.count(),1);
  assert.equal(await contacts.getAttribute('data-contact-destination'),'galana');
  assert.deepEqual(await contacts.locator('a[href^="mailto:"]').evaluateAll(links=>links.map(a=>a.getAttribute('href'))),emails.map(e=>'mailto:'+e));
  assert.equal(await contacts.locator('a[href^="tel:+254"]').count(),emails.length);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  console.log(`PASS Galana Farm map, route drawing, team contacts and confirmation contacts, JS=${javaScriptEnabled}`);
  await context.close();
 }
} finally {await browser?.close();server.kill();await rm(storage,{recursive:true,force:true});}
