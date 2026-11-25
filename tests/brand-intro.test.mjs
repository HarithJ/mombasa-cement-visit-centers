import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const port=8089, url=`http://127.0.0.1:${port}/`;
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public'],{env:{...process.env,BRAND_INTRO_ENABLED:'1'},stdio:'ignore'});
let browser;
// Hold the film's download open so the test does not depend on the browser's video codecs.
const holdFilm=page=>page.route('**/brand-video.mp4',()=>{});
try {
 for(let i=0;i<40;i++){try{await fetch(url);break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});

 const context=await browser.newContext({viewport:{width:390,height:844}});
 const page=await context.newPage();page.setDefaultTimeout(6000);
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 // Like a browser that allows sound on this site (for example after earlier visits).
 await page.addInitScript(()=>{const play=HTMLMediaElement.prototype.play;HTMLMediaElement.prototype.play=function(){play.call(this).catch(()=>{});return Promise.resolve();};});
 await holdFilm(page);
 await page.goto(url);
 const intro=page.locator('#brand-intro');
 await intro.waitFor({state:'visible'});
 assert.equal(await page.locator('#brand-intro video').getAttribute('controls'),null,'the film has no controls to skip with');
 assert.ok(await page.locator('#brand-intro video').evaluate(v=>!v.muted&&v.src.endsWith('/assets/video/brand-video.mp4')),'the film plays with sound');
 assert.equal(await page.locator('#brand-intro button:not(.brand-intro-play):not(.brand-intro-sound)').count(),0,'there is no close button');
 const sound=page.locator('.brand-intro-sound');
 assert.equal(await sound.getAttribute('aria-label'),'Mute the film');
 assert.equal((await sound.innerText()).trim(),'','the sound button is an icon only');
 await sound.click();
 assert.ok(await page.locator('#brand-intro video').evaluate(v=>v.muted),'the visitor can mute the film');
 assert.equal(await sound.getAttribute('aria-label'),'Unmute the film');
 await sound.click();
 assert.equal(await page.locator('#brand-intro video').evaluate(v=>v.muted),false,'the visitor can unmute the film');
 assert.ok(await page.locator('.brand-intro-player').evaluate(p=>p.getBoundingClientRect().width<innerWidth),'the film plays in a window, not full screen');
 assert.ok(await page.locator('main').evaluate(m=>m.inert),'the page behind the film cannot be used');
 await page.keyboard.press('Escape');
 await page.mouse.click(10,10);
 assert.ok(await intro.isVisible(),'Escape and clicks do not skip the film');
 await page.locator('#brand-intro video').evaluate(v=>v.dispatchEvent(new Event('ended')));
 assert.equal(await page.locator('main').evaluate(m=>m.inert),false,'the page is usable as soon as the film ends');
 assert.ok(await intro.evaluate(i=>i.classList.contains('is-leaving')),'the film fades into the page');
 await intro.waitFor({state:'detached'});
 assert.equal(await page.evaluate(()=>document.documentElement.classList.contains('brand-intro-active')),false);
 console.log('PASS the film covers a new visit with sound, cannot be skipped, and fades into the page when it ends');

 await page.reload();
 await intro.waitFor({state:'visible'});
 await page.keyboard.press('Escape');
 await page.waitForTimeout(300);
 assert.ok(await intro.isVisible()&&await page.locator('main').evaluate(m=>m.inert),'having watched it, the visitor still cannot skip it after a reload');
 console.log('PASS the film shows again on reload and must be watched again');
 await context.close();

 const fresh=await browser.newContext();const freshPage=await fresh.newPage();freshPage.setDefaultTimeout(6000);
 await holdFilm(freshPage);await freshPage.goto(url);
 await freshPage.locator('#brand-intro').waitFor({state:'visible'});
 console.log('PASS a new browser sees the film too');
 await fresh.close();

 // Like a real browser: refuse sound until the visitor has interacted with the page.
 const soundPolicy=()=>{const play=HTMLMediaElement.prototype.play;HTMLMediaElement.prototype.play=function(){if(!this.muted&&!navigator.userActivation.isActive)return Promise.reject(new DOMException('blocked','NotAllowedError'));play.call(this).catch(()=>{});return Promise.resolve();};};
 const muted=await browser.newContext();const mutedPage=await muted.newPage();mutedPage.setDefaultTimeout(6000);
 await mutedPage.addInitScript(soundPolicy);await holdFilm(mutedPage);await mutedPage.goto(url);
 await mutedPage.locator('.brand-intro-sound.is-muted').waitFor({state:'visible'});
 assert.ok(await mutedPage.locator('#brand-intro video').evaluate(v=>v.muted),'when sound is refused, the film autoplays muted');
 assert.equal(await mutedPage.locator('.brand-intro-play').isVisible(),false,'a muted film plays without waiting for a tap');
 assert.equal(await mutedPage.locator('.brand-intro-sound').getAttribute('aria-label'),'Unmute the film');
 await mutedPage.locator('.brand-intro-sound').click();
 assert.equal(await mutedPage.locator('#brand-intro video').evaluate(v=>v.muted),false,'one tap turns the sound on');
 console.log('PASS when sound is refused, the film autoplays muted and the speaker button turns the sound on');
 await muted.close();

 // If the browser refuses to autoplay at all, the film waits for a tap, then plays with sound.
 const refused=await browser.newContext();const refusedPage=await refused.newPage();refusedPage.setDefaultTimeout(6000);
 await refusedPage.addInitScript(()=>{const play=HTMLMediaElement.prototype.play;HTMLMediaElement.prototype.play=function(){if(!navigator.userActivation.isActive)return Promise.reject(new DOMException('blocked','NotAllowedError'));play.call(this).catch(()=>{});return Promise.resolve();};});
 await holdFilm(refusedPage);await refusedPage.goto(url);
 const playButton=refusedPage.locator('.brand-intro-play');
 await playButton.waitFor({state:'visible'});
 await playButton.click();
 await playButton.waitFor({state:'hidden'});
 assert.ok(await refusedPage.locator('#brand-intro').isVisible());
 assert.equal(await refusedPage.locator('#brand-intro video').evaluate(v=>v.muted),false,'the tap plays the film with sound');
 console.log('PASS when autoplay is refused, a tap plays the film with sound');
 await refused.close();

 const broken=await browser.newContext();const brokenPage=await broken.newPage();brokenPage.setDefaultTimeout(6000);
 await brokenPage.route('**/brand-video.mp4',route=>route.abort());
 await brokenPage.goto(url);
 await brokenPage.locator('#brand-intro').waitFor({state:'detached'});
 assert.equal(await brokenPage.locator('main').evaluate(m=>m.inert),false);
 console.log('PASS a film that fails to load does not trap the visitor');
 await broken.close();
 assert.deepEqual(errors,[]);
} finally {await browser?.close();server.kill();}
