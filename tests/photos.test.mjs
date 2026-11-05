import assert from 'node:assert/strict';
import {readFile,readdir,access} from 'node:fs/promises';
import {spawn} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const manifest=JSON.parse(await readFile('config/photo-manifest.json','utf8'));
assert.deepEqual([...new Set(manifest.map(r=>r.destination))],['galana'],'Galana Farm is the only photographed destination');
assert.deepEqual((await readdir('public/assets/photos')).filter(n=>!n.startsWith('.')),['galana']);
const expected=manifest.map(r=>r.asset).sort();
assert.deepEqual((await readdir('public/assets/photos/galana')).filter(n=>n.endsWith('.webp')).sort(),expected);
for(const row of manifest) await access(row.source);
const server=spawn('php',['-S','127.0.0.1:8099','-t','public'],{stdio:'ignore'});
let browser;
try {
 for(let i=0;i<40;i++){try{await fetch('http://127.0.0.1:8099');break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 for(const viewport of [{width:390,height:844},{width:1440,height:900}]) {
  const page=await browser.newPage({reducedMotion:'reduce',viewport});page.setDefaultTimeout(6000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().includes('/assets/photos/')&&r.status()>=400)errors.push(r.url());});
  await page.goto('http://127.0.0.1:8099/');
  // Every photo on the page is one of the farm's own, and every one of them loads.
  const used=await page.locator('img[src*="assets/photos/"]').evaluateAll(imgs=>imgs.map(i=>i.getAttribute('src').split('/').pop().replace(/-\d+(?=\.webp$)/,'')));
  assert.ok(used.length>=12);
  for(const name of used) assert.ok(expected.includes(name),`${name} is in the manifest`);
  for(const img of await page.locator('img[src*="assets/photos/"]').all()) {
   await img.scrollIntoViewIfNeeded();
   await img.evaluate(i=>i.decode());
   assert.ok(await img.evaluate(i=>i.naturalWidth>0));
  }
  // The pile: the pivot-circle aerial lies on top, and every print opens in the viewer.
  const prints=page.locator('[data-print]');const count=await prints.count();
  assert.equal(await prints.evaluateAll(ps=>ps.reduce((top,p)=>Number(p.style.getPropertyValue('--i'))>Number(top.style.getPropertyValue('--i'))?p:top).dataset.full.split('/').pop()),'02-circular-fields-aerial.webp');
  const full=await prints.evaluateAll(ps=>ps.map(p=>p.dataset.full.split('/').pop()));
  await prints.first().focus();await page.keyboard.press('Enter');
  const viewer=page.locator('[data-print-viewer]');
  await viewer.locator('[data-viewer-img]').waitFor();
  assert.equal(await viewer.evaluate(d=>d.open),true);
  for(let index=0;index<=count;index++) {
   const name=full[index%count];
   await page.waitForFunction(name=>document.querySelector('[data-viewer-img]').getAttribute('src')?.endsWith('/'+name),name);
   assert.ok((await viewer.locator('[data-viewer-caption]').innerText()).length>0);
   if(index<count) await page.keyboard.press('ArrowRight');
  }
  await page.keyboard.press('ArrowLeft');
  await page.waitForFunction(name=>document.querySelector('[data-viewer-img]').getAttribute('src')?.endsWith('/'+name),full[count-1]);
  await page.keyboard.press('Escape');
  await page.waitForFunction(()=>!document.querySelector('[data-print-viewer]').open);
  assert.equal(await page.evaluate(()=>document.activeElement?.dataset.full?.split('/').pop()),full[count-1],'focus goes back to the photo that was showing');
  assert.deepEqual(errors,[]);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  console.log(`PASS ${viewport.width}px: every photo loads, the aerial tops the pile, the viewer steps and wraps through all ${count} prints`);await page.close();
 }
} finally {await browser?.close();server.kill();}
