import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFile, stat, mkdir} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
execFileSync('node',['bin/build-preview.mjs'],{stdio:'inherit'});
const root=resolve('_site');
const requests=[];
const mime={'.html':'text/html','.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.webp':'image/webp','.ttf':'font/ttf'};
const prefix='/mombasa-cement-visit-centers';
const server=createServer(async(req,res)=>{
 requests.push({method:req.method,url:req.url});
 try{
  if(!['GET','HEAD'].includes(req.method)){res.writeHead(405).end();return;}
  let path=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(path===prefix||path.startsWith(prefix+'/'))path=path.slice(prefix.length)||'/';
  let file=resolve(root,'.'+path);
  if(!file.startsWith(root+'/')&&file!==root)throw Error('invalid path');
  if((await stat(file)).isDirectory()){
   if(!path.endsWith('/')){res.writeHead(301,{Location:req.url+'/'}).end();return;}
   file=resolve(file,'index.html');
  }
  res.writeHead(200,{'Content-Type':mime[extname(file)]||'application/octet-stream'});
  res.end(await readFile(file));
 }catch{res.writeHead(404).end('Not found');}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const origin=`http://127.0.0.1:${server.address().port}`;
let browser;
try{
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 await mkdir('test-results',{recursive:true});
 const context=await browser.newContext({reducedMotion:'reduce'});
 await context.route('https://www.google.com/maps?**',route=>route.fulfill({contentType:'text/html',body:'<p>Map preview test</p>'}));
 const page=await context.newPage();const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 page.on('response',r=>{if(r.url().startsWith(origin)&&r.status()>=400)errors.push(`${r.status()} ${r.url()}`);});
 for(const mount of ['',prefix]){
  await page.goto(origin+mount+'/');assert.equal(await page.locator('nav a').count(),3);
  for(const version of ['v1','v2','v3']){
   await page.setViewportSize({width:1440,height:1000});
   await page.goto(`${origin}${mount}/${version}/`);
   assert.equal(await page.locator('.preview-bar a[aria-current=page]').innerText(),version.toUpperCase());
   await page.evaluate(async()=>{await Promise.all([...document.querySelectorAll('.photo-primary')].map(i=>i.decode()));});
   assert.equal(await page.locator('input[name=csrf],input[name=submissionToken]').count(),0);
   await page.locator('[data-book="galana"]').click();
   await page.getByRole('button',{name:'Preview my visit',exact:true}).click();
   assert.ok(await page.locator('#error-summary').isVisible());
   assert.equal(await page.locator('#inline-map iframe').count(),0);
   await page.locator('#inline-map summary').click();
   await page.locator('#inline-map iframe').waitFor();
   assert.match(await page.locator('#inline-map iframe').getAttribute('src'),/-3.1173061/);
   await page.locator('#location').selectOption('feeding');
   assert.match(await page.locator('#inline-map iframe').getAttribute('src'),/-4.0330122/);
   await page.locator('#location').selectOption('galana');
   await page.locator('#full-name').fill('Sample Visitor');
   await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('visitor@example.com');
   await page.locator('#visit-date').fill('2099-05-01');
   await page.locator('#time-slot').selectOption('09:00');
   await page.locator('#attendees').fill('3');
   await page.locator('#overnight').check();
   await page.locator('#departure-date').fill('2099-05-02');
   await page.locator('#overnight-guests').fill('2');
   const before=requests.length;
   await page.getByRole('button',{name:'Preview my visit',exact:true}).click();
   await page.locator('#confirmation-view').waitFor({state:'visible'});
   assert.match(await page.locator('#confirmation-view').innerText(),/No booking has been made/);
   assert.match(await page.locator('#confirmation-details').innerText(),/Overnight stay/);
   assert.equal(await page.locator('.reference-label').count(),0);
   assert.equal(await page.locator('[data-contact-destination]:visible').getAttribute('data-contact-destination'),'galana');
   assert.equal(await page.locator('[data-contact-destination=galana] a[href="mailto:jaco@nyumbagri.com"]').count(),1);
   assert.ok(requests.slice(before).every(r=>r.method==='GET'));
   assert.equal(page.url(),`${origin}${mount}/${version}/`);
   await page.reload();
   await page.locator('[data-book="feeding"]').click();
   assert.equal(await page.locator('#full-name').inputValue(),'');
   await page.locator('.close-button').click();
   await page.locator('[data-destination="galana"] .photo-toggle').click();
   await page.locator('#gallery-next').click();
   await page.waitForFunction(()=>document.querySelector('#gallery-count').textContent.includes('02 / 14'));
   await page.locator('#gallery-close').click();
   for(const width of [320,390,768]){
    await page.setViewportSize({width,height:900});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   }
   await page.screenshot({path:`test-results/preview-${version}${mount?'-project':'-root'}.png`});
   // Follow the version switcher to exercise relative navigation.
   await page.locator('.preview-bar nav a').first().click();
   assert.equal(page.url(),`${origin}${mount}/v1/`);
   console.log(`PASS ${mount||'/'} ${version}: static assets, local-only form, maps, gallery and responsive layout`);
  }
 }
 assert.deepEqual(errors,[]);
 assert.ok(requests.every(r=>r.method==='GET'));
 assert.ok(requests.every(r=>!r.url.includes('Sample')&&!r.url.includes('0712345678')));
 const nojs=await browser.newContext({javaScriptEnabled:false});const p=await nojs.newPage();
 await p.goto(`${origin}${prefix}/v2/`);
 assert.ok(await p.locator('.submit-button').isDisabled());
 await p.locator('[data-book="galana"]').click();
 assert.ok(await p.locator('#preview-unavailable').isVisible());
 await nojs.close();
 for(const path of ['/src/web.php','/var/bookings.sqlite','/config/locations.php','/public/index.php'])assert.equal((await fetch(origin+path)).status,404);
 console.log('PASS no submissions, no personal data in requests, no-JS notice and no backend files served');
}finally{await browser?.close();await new Promise(r=>server.close(r));}
