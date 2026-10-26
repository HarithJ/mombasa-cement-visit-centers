import assert from 'node:assert/strict';
import {mkdtemp, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage = await mkdtemp(join(tmpdir(), 'nyumba-organization-ui-'));
const origin = 'http://127.0.0.1:8099';
const hash = execFileSync('php', ['-r', "echo password_hash('test-password', PASSWORD_DEFAULT);"], {encoding:'utf8'}).trim();
const server = spawn('php', ['-S','127.0.0.1:8099','-t','public'], {env:{...process.env,BOOKING_DB:join(storage,'bookings.sqlite'),BOOKING_SESSION_PATH:storage,BOOKING_EMAIL_ENABLED:'0',FEEDBACK_EMAIL_ENABLED:'0',ADMIN_USERNAME:'operator',ADMIN_PASSWORD_HASH:hash},stdio:'ignore'});
let browser;
async function fillRequired(page, name) {
    await page.locator('#full-name').fill(name);
    await page.locator('#phone').fill('0712345678');
    await page.locator('#visit-date').fill('2099-05-01');
    await page.locator('#time-slot').selectOption({index:1});
}
try {
    let ready = false;
    for (let i=0;i<50;i++) { try { if ((await fetch(origin)).ok) { ready=true; break; } } catch {} await new Promise(r=>setTimeout(r,100)); }
    assert.ok(ready, 'Server starts');
    browser = await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
    for (const javaScriptEnabled of [true,false]) {
        const context = await browser.newContext({javaScriptEnabled,viewport:{width:390,height:844},reducedMotion:'reduce'});
        const page = await context.newPage();
        const errors=[]; page.on('pageerror',e=>errors.push(e.message));
        await page.goto(origin);
        await page.locator('[data-book="feeding"]').click();
        const field = page.getByLabel('Organization (optional)');
        assert.equal(await field.inputValue(), '');
        assert.equal(await field.getAttribute('required'), null, 'Organization is not required');
        assert.equal(await field.getAttribute('maxlength'), '120');

        // Blank organization still books and is reported as not provided.
        await fillRequired(page, 'No organization');
        await page.locator('[type="submit"]').click();
        await page.locator('#confirmation-view').waitFor({state:'visible'});
        assert.match(await page.locator('#confirmation-details').innerText(), /Organization\s+Not provided/);

        // Entered organization is saved, shown and survives a refresh.
        await page.goto(origin);
        await page.locator('[data-book="feeding"]').click();
        await fillRequired(page, 'With organization');
        await field.fill('Mombasa Primary School');
        await page.locator('[type="submit"]').click();
        await page.locator('#confirmation-view').waitFor({state:'visible'});
        await page.reload();
        assert.match(await page.locator('#confirmation-details').innerText(), /Organization\s+Mombasa Primary School/);

        // Server-side rejection retains the typed organization.
        if (javaScriptEnabled) {
            await page.goto(origin);
            await page.locator('[data-book="feeding"]').click();
            await fillRequired(page, 'Too long');
            await page.evaluate(()=>{const f=document.querySelector('#booking-form'); f.noValidate=true; f.organization.removeAttribute('maxlength'); f.organization.value='x'.repeat(121); f.submit();});
            await page.locator('#organization[aria-invalid="true"]').waitFor();
            assert.equal((await page.locator('#organization').inputValue()).length, 121);
        }
        assert.deepEqual(errors,[]);
        await context.close();
        console.log(`PASS organization form: optional, saved, shown, refresh, JS=${javaScriptEnabled}`);
    }
    const admin = await browser.newPage();
    await admin.goto(origin+'/admin');
    await admin.locator('#admin-username').fill('operator');
    await admin.locator('#admin-password').fill('test-password');
    await admin.locator('[type="submit"]').click();
    await admin.locator('a[href^="/admin/bookings/"]').first().click();
    assert.match(await admin.locator('body').innerText(), /Organization\s+(Mombasa Primary School|Not provided)/);
    console.log('PASS organization displayed in protected admin details');
} finally {
    await browser?.close();
    server.kill();
    await new Promise(r=>server.exitCode!==null?r():server.once('exit',r));
    await rm(storage,{recursive:true,force:true});
}
