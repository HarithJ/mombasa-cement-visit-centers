import assert from 'node:assert/strict';
import {mkdtemp, rm, access} from 'node:fs/promises';
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
 const combined=await access('src/views/v1.php').then(()=>true,()=>false);
 const expected={sahajanand:['Prafulmukhi1065@gmail.com','Maryangie237@gmail.com','Aslam.shakur1@gmail.com'],galana:['jaco@nyumbagri.com','pravin@nyumbagri.com'],feeding:['ghulamsalim@yahoo.com','karthik@nyumba.com']};
 for(const route of combined?['/v1','/v2','/v3']:['/']) for(const javaScriptEnabled of [true,false]) {
 const context=await browser.newContext({javaScriptEnabled,viewport:{width:390,height:844}});
 const page=await context.newPage();
 for(const [destination,emails] of Object.entries(expected)) {
  await page.goto(`${origin}${route}?book=${destination}`);
  await page.locator('#inline-map summary').click();
  assert.equal(await page.locator('#inline-map iframe').count(),0);
  assert.match(await page.locator('#route-image').getAttribute('src'),new RegExp(`/routes/${destination}\\.png$`));
  await page.locator('#route-image').evaluate(image=>image.decode());
  assert.equal(await page.locator('#route-image-link').getAttribute('href'),await page.locator('#route-image').getAttribute('src'));
  assert.match(await page.locator('#route-image').getAttribute('alt'),/Not to scale/);
  const destinationName = {sahajanand:'Sahajanand Special School',galana:'Galana Farm',feeding:'Kibarani Feeding center'}[destination];
  assert.equal(await page.locator('#aside-destination').textContent(),destinationName);
  await page.locator('#full-name').fill('Contact Test');
  await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
  await page.locator('#visit-date').fill('2099-05-01');
  await page.locator('#time-slot').selectOption('11:00');
  await page.locator('#attendees').fill('1');
  await page.locator('[type=submit]').click();
  await page.waitForURL(/confirmation=1/);
  assert.equal(await page.locator('#aside-destination').textContent(),destinationName);
  assert.match(await page.locator('#confirmation-details').innerText(), /visitor@example.com/);
  const team=page.locator('[data-contact-destination]:visible');
  assert.equal(await team.count(),1);
  assert.equal(await team.getAttribute('data-contact-destination'),destination);
  assert.deepEqual(await team.locator('a[href^="mailto:"]').evaluateAll(links=>links.map(a=>a.getAttribute('href'))),emails.map(e=>'mailto:'+e));
  assert.equal(await team.locator('a[href^="tel:+254"]').count(),emails.length);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 }
 console.log(`PASS ${route}: all destination contacts, JS=${javaScriptEnabled}`);
 await context.close();
 }
} finally {await browser?.close();server.kill();await rm(storage,{recursive:true,force:true});}
