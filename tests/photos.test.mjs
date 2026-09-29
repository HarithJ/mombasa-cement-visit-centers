import assert from 'node:assert/strict';
import {readFile,readdir,access} from 'node:fs/promises';
import {spawn} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const manifest=JSON.parse(await readFile('config/photo-manifest.json','utf8'));
for(const destination of ['sahajanand','galana','feeding']) {
 const expected=manifest.filter(r=>r.destination===destination).map(r=>r.asset).sort();
 assert.deepEqual((await readdir(`public/assets/photos/${destination}`)).filter(n=>n.endsWith('.webp')).sort(),expected);
 for(const row of manifest.filter(r=>r.destination===destination)) await access(row.source);
}
const server=spawn('php',['-S','127.0.0.1:8099','-t','public'],{stdio:'ignore'});
let browser;
try {
 for(let i=0;i<40;i++){try{await fetch('http://127.0.0.1:8099');break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const combined=await access('src/views/v1.php').then(()=>true,()=>false);
 for(const route of combined?['/v1','/v2','/v3']:['/']) {
  const page=await browser.newPage({reducedMotion:'reduce',viewport:{width:390,height:844}});page.setDefaultTimeout(6000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().includes('/assets/photos/')&&r.status()>=400)errors.push(r.url());});
  await page.goto('http://127.0.0.1:8099'+route);
  for(const destination of ['sahajanand','galana','feeding']) {
   await page.locator(`[data-destination="${destination}"] .photo-toggle`).click();
   const photos=manifest.filter(r=>r.destination===destination).sort((a,b)=>a.asset.localeCompare(b.asset));
   const thumbs=page.locator('#gallery-thumbnails button');assert.equal(await thumbs.count(),photos.length);
   for(let index=0;index<photos.length;index++) {
    await thumbs.nth(index).click();
    await page.waitForFunction(name=>document.querySelector('#gallery-image').getAttribute('src')?.endsWith('/'+name),photos[index].asset);
    await page.locator('#gallery-image').evaluate(i=>i.decode());
   }
   await page.locator('#gallery-next').click();
   await page.waitForFunction(()=>document.querySelector('#gallery-count').textContent.includes('01 /'));
   await page.locator('#gallery-close').click();
  }
  assert.deepEqual(errors,[]);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  console.log(`PASS ${route}: every gallery image, thumbnail count, wraparound and mobile layout`);await page.close();
 }
} finally {await browser?.close();server.kill();}
