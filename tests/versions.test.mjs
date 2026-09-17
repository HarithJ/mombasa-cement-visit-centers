import assert from 'node:assert/strict';
import {mkdtemp, mkdir, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage = await mkdtemp(join(tmpdir(), 'nyumba-versions-'));
const database = join(storage, 'bookings.sqlite');
const origin = 'http://127.0.0.1:8093';
const server = spawn('php', ['-S', '127.0.0.1:8093', '-t', 'public'], {env:{...process.env, BOOKING_DB:database, BOOKING_SESSION_PATH:storage}, stdio:'ignore'});
let browser;
try {
  let ready=false;
  for(let i=0;i<50;i++) {try {if((await fetch(origin)).ok){ready=true;break;}}catch{} await new Promise(r=>setTimeout(r,100));}
  assert.ok(ready, 'Version server starts');
  browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
  await mkdir('test-results', {recursive:true});
  const errors=[];
  for(const version of ['v1','v2','v3']) {
    const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
    const page=await context.newPage();
    page.on('pageerror',e=>errors.push(`${version}: ${e.message}`));
    page.on('response',r=>{if(r.url().includes('/assets/') && r.status()>=400) errors.push(`${r.status()} ${r.url()}`);});
    for(const suffix of ['', '/', '/index.php']) {
      assert.equal((await page.goto(`${origin}/${version}${suffix}`)).status(),200);
      assert.equal(await page.locator('form').getAttribute('action'),`/${version}`);
      assert.equal(await page.locator('.close-button').getAttribute('href'),`/${version}`);
      assert.equal(await page.locator('script[src]').getAttribute('src'),`/assets/versions/${version}/app.js`);
      assert.equal(await page.locator('[data-book="galana"]').getAttribute('href'),`/${version}?book=galana`);
      assert.equal(await page.locator('.destination').count(),3);
    }
    const hero=version==='v1'?'.hero-intro':version==='v2'?'.travel-hero':'.explorer-hero';
    assert.equal(await page.locator(hero).count(),1);
    await page.evaluate(async()=>{await Promise.all([...document.querySelectorAll('.photo-primary')].map(i=>i.decode()));});
    await page.screenshot({path:`test-results/version-${version}-desktop.png`,animations:'disabled'});
    await page.locator('[data-destination="galana"] .photo-toggle').click();
    await page.locator('#photo-lightbox').waitFor({state:'visible'});
    await page.locator('#gallery-next').click();
    await page.waitForFunction(()=>document.querySelector('#gallery-count').textContent.includes('02 / 14'));
    await page.keyboard.press('Escape');
    await page.locator('[data-book="galana"]').click();
    await page.locator('#full-name').fill(`Visitor ${version}`);
    await page.locator('#phone').fill('0712345678');
    await page.locator('#visit-date').fill('2099-05-01');
    await page.locator('#time-slot').selectOption('09:00');
    await page.locator('#attendees').fill('3');
    await page.locator('#overnight').check();
    await page.locator('#departure-date').fill('2099-05-02');
    await page.locator('#overnight-guests').fill('2');
    await page.locator('[type="submit"]').click();
    await page.locator('#confirmation-view').waitFor({state:'visible'});
    assert.equal(new URL(page.url()).pathname,`/${version}`);
    assert.match(await page.locator('#confirmation-details').innerText(),/Overnight stay/);
    const reference=await page.locator('.reference-label strong').innerText();
    await page.reload();
    assert.equal(await page.locator('.reference-label strong').innerText(),reference);
    for(const width of [320,390,768]) {
      await page.setViewportSize({width,height:900});
      await page.goto(`${origin}/${version}`);
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      await page.locator('[data-book="feeding"]').click();
      assert.ok(await page.locator('#full-name').isVisible());
      await page.locator('.close-button').click();
      if(width===390) await page.screenshot({path:`test-results/version-${version}-mobile.png`,animations:'disabled'});
    }
    await context.close();
    const native=await browser.newContext({javaScriptEnabled:false});
    const p=await native.newPage();
    await p.goto(`${origin}/${version}/`);
    await p.locator('[data-book="feeding"]').click();
    await p.locator('#full-name').fill(`No JS ${version}`);
    await p.locator('#phone').fill('0712345678');
    await p.locator('#visit-date').fill('2099-05-03');
    await p.locator('#time-slot').selectOption('11:00');
    await p.locator('[type="submit"]').click();
    await p.locator('#confirmation-view').waitFor({state:'visible'});
    assert.equal(new URL(p.url()).pathname,`/${version}`);
    await p.locator('.close-button').click();
    assert.equal(new URL(p.url()).pathname,`/${version}`);
    await native.close();
    console.log(`PASS ${version}: routes, assets, gallery, overnight booking, refresh, responsive UI and no-JavaScript day booking`);
  }
  for(const path of ['/v4','/v1/missing','/v2/other.php','/src/views/v1.php','/var/bookings.sqlite']) assert.equal((await fetch(origin+path)).status,404,path);
  const root=await fetch(origin);assert.match(await root.text(),/class="hero-intro wrap"/);
  const rows=JSON.parse(execFileSync('php',['-r','$db=new PDO("sqlite:".$argv[1]);echo json_encode($db->query("SELECT * FROM bookings")->fetchAll(PDO::FETCH_ASSOC));',database],{encoding:'utf8'}));
  assert.equal(rows.length,6);assert.equal(rows.filter(r=>r.overnight).length,3);
  assert.deepEqual(errors,[]);
  console.log('PASS shared storage, unchanged root, unknown/private routes and no browser/asset errors');
} finally {
  await browser?.close();
  server.kill();
  await new Promise(r=>server.exitCode!==null?r():server.once('exit',r));
  await rm(storage,{recursive:true,force:true});
}
