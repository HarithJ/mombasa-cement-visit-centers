import assert from 'node:assert/strict';
import {existsSync} from 'node:fs';
import {mkdtemp, mkdir, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage = await mkdtemp(join(tmpdir(), 'nyumba-transport-ui-'));
const origin = 'http://127.0.0.1:8098';
const hash = execFileSync('php', ['-r', "echo password_hash('test-password', PASSWORD_DEFAULT);"], {encoding:'utf8'}).trim();
const server = spawn('php', ['-S','127.0.0.1:8098','-t','public'], {env:{...process.env,BOOKING_DB:join(storage,'bookings.sqlite'),BOOKING_SESSION_PATH:storage,BOOKING_EMAIL_ENABLED:'0',FEEDBACK_EMAIL_ENABLED:'0',ADMIN_USERNAME:'operator',ADMIN_PASSWORD_HASH:hash},stdio:'ignore'});
let browser;
try {
    let ready = false;
    for (let i=0;i<50;i++) { try { if ((await fetch(origin)).ok) { ready=true; break; } } catch {} await new Promise(r=>setTimeout(r,100)); }
    assert.ok(ready, 'Server starts');
    browser = await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
    const routes = existsSync('src/views/v1.php') ? ['/v1','/v2','/v3'] : ['/'];
    await mkdir('test-results', {recursive:true});
    for (const route of routes) for (const javaScriptEnabled of [true,false]) {
        const context = await browser.newContext({javaScriptEnabled,viewport:{width:390,height:844},reducedMotion:'reduce'});
        const page = await context.newPage();
        const errors=[]; page.on('pageerror',e=>errors.push(e.message));
        for (const destination of ['feeding','sahajanand','galana']) {
            await page.goto(origin+route);
            await page.locator(`[data-book="${destination}"]`).click();
            const checkbox = page.getByRole('checkbox',{name:'I would like the company to arrange transport for my visit.',exact:true});
            assert.equal(await checkbox.isChecked(),false);
            assert.match(await page.locator('#transport-hint').innerText(),/subject to availability/);
            const requested = destination !== 'sahajanand';
            if (requested) await checkbox.check();
            await page.locator('#full-name').fill('Transport visitor');
            await page.locator('#phone').fill('0712345678');
            await page.locator('#email').fill('visitor@example.com');
            await page.locator('#visit-date').fill('2099-05-01');
            await page.locator('#time-slot').selectOption({index:1});
            if (destination === 'galana') {
                await page.locator('#overnight').check();
                if (!javaScriptEnabled) await page.locator('#arrival-date').fill('2099-05-01');
                await page.locator('#departure-date').fill('2099-05-02');
            }
            if (javaScriptEnabled && destination === 'feeding') {
                await page.locator('#full-name').fill('');
                await page.evaluate(()=>{const f=document.querySelector('#booking-form'); f.noValidate=true; f.submit();});
                await page.waitForLoadState('load');
                await page.locator('#full-name[aria-invalid="true"]').waitFor();
                assert.equal(await checkbox.isChecked(),true, 'Server validation retains request');
                await page.locator('#full-name').fill('Transport visitor');
                await checkbox.scrollIntoViewIfNeeded();
                await page.locator('.checkbox-label').filter({has:checkbox}).screenshot({path:`test-results/transport-${route.replaceAll('/','') || 'standalone'}-mobile.png`});
                assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth), 'No page overflow');
                await page.locator('.close-button').click();
                await page.locator('[data-book="feeding"]').click();
                assert.equal(await checkbox.isChecked(), false, 'A new form resets a previously retained request');
                await checkbox.check();
                await page.locator('#full-name').fill('Transport visitor');
                await page.locator('#phone').fill('0712345678');
                await page.locator('#email').fill('visitor@example.com');
                await page.locator('#visit-date').fill('2099-05-01');
                await page.locator('#time-slot').selectOption({index:1});
            }
            await page.locator('[type="submit"]').click();
            await page.locator('#confirmation-view').waitFor({state:'visible'});
            const expected = requested ? 'Requested (subject to availability)' : 'Not requested';
            assert.ok((await page.locator('#confirmation-details').innerText()).includes(expected));
            await page.reload();
            assert.ok((await page.locator('#confirmation-details').innerText()).includes(expected), 'Refresh preserves choice');
        }
        assert.deepEqual(errors,[]);
        await context.close();
        console.log(`PASS transport ${route}: checked/unchecked, all destinations, overnight, refresh, JS=${javaScriptEnabled}`);
    }
    const admin = await browser.newPage();
    await admin.goto(origin+'/admin');
    await admin.locator('#admin-username').fill('operator');
    await admin.locator('#admin-password').fill('test-password');
    await admin.locator('[type="submit"]').click();
    await admin.locator('a[href^="/admin/bookings/1?"]').click();
    assert.match(await admin.locator('body').innerText(),/Transport\s+Requested \(subject to availability\)/);
    console.log('PASS transport displayed in protected admin details');
} finally {
    await browser?.close();
    server.kill();
    await new Promise(r=>server.exitCode!==null?r():server.once('exit',r));
    await rm(storage,{recursive:true,force:true});
}
