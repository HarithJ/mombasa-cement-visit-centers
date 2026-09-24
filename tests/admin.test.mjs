import assert from 'node:assert/strict';
import {mkdtemp,rm,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage=await mkdtemp(join(tmpdir(),'nyumba-admin-'));
const hash=spawnSync('php',['-r',"echo password_hash('test-password', PASSWORD_DEFAULT);"],{encoding:'utf8'}).stdout;
const seed=spawnSync('php',['-r', `require 'src/BookingStore.php'; $s=new BookingStore($argv[1]); for($i=0;$i<27;$i++) $s->create(['fullName'=>$i===0?'Special Visitor':'Visitor '.$i,'phone'=>'+254712345678','location'=>$i===0?'galana':'sahajanand','visitDate'=>'2099-05-01','timeSlot'=>'11:00','attendees'=>3,'overnight'=>'','arrivalDate'=>null,'departureDate'=>null,'overnightGuests'=>null,'email'=>'visitor@example.com'], 'seed-'.$i);`,join(storage,'bookings.sqlite')],{encoding:'utf8',env:{...process.env,BOOKING_EMAIL_ENABLED:'0',FEEDBACK_EMAIL_ENABLED:'0'}});
assert.equal(seed.status,0,seed.stderr);
const fixture=spawnSync('php',['-r', `$db=new PDO('sqlite:'.$argv[1]); $db->exec("UPDATE bookings SET reference='NY-AAAAAAAAAAAAAAAAAAAAAAA' || char(64+id)"); $db->exec("UPDATE bookings SET overnight=1, arrival_date='2099-05-01', departure_date='2099-05-03', overnight_guests=2 WHERE id=1"); $db->exec("UPDATE bookings SET email=NULL WHERE id=2"); $q=$db->prepare('INSERT INTO feedback_responses VALUES (?,?,?,?,?,?,?)'); $q->execute([1,'attended',5,'The farm','More signs','<script>alert(1)</script>','2026-09-24T09:00:00Z']); $q->execute([2,'not_attended',null,'','','','2026-09-24T09:00:00Z']);`,join(storage,'bookings.sqlite')],{encoding:'utf8'});
assert.equal(fixture.status,0,fixture.stderr);
// Reproduce a populated pre-admin schema, including private invitation and queue data.
const legacy=spawnSync('php',['-r', `$db=new PDO('sqlite:'.$argv[1]); $db->exec("INSERT INTO feedback_invitations VALUES (1,'fixture-token-hash','v1')"); $db->exec("INSERT INTO booking_emails (booking_id,payload,idempotency_key) VALUES (1,'{}','fixture-email')"); $db->exec('DROP TABLE admin_login_attempts'); $db->exec("DELETE FROM schema_migrations WHERE version='006-admin-throttle.sql'");`,join(storage,'bookings.sqlite')],{encoding:'utf8'});
assert.equal(legacy.status,0,legacy.stderr);
function domainSnapshot() {
 const result=spawnSync('php',['-r', `$db=new PDO('sqlite:'.$argv[1]); $out=[]; foreach(['bookings','feedback_responses','feedback_invitations','booking_emails'] as $table) $out[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC); echo json_encode($out);`,join(storage,'bookings.sqlite')],{encoding:'utf8'});
 assert.equal(result.status,0,result.stderr); return JSON.parse(result.stdout);
}
const beforeAdmin=domainSnapshot();
const clockFile=join(storage,'clock');
const hashFile=join(storage,'hash');
await writeFile(clockFile,'1800000000'); await writeFile(hashFile,hash);
const router=join(storage,'router.php');
await writeFile(router,`<?php if (str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH), '/admin')) { $adminNow=(int)file_get_contents(__DIR__.'/clock'); putenv('ADMIN_PASSWORD_HASH='.file_get_contents(__DIR__.'/hash')); require ${JSON.stringify(process.cwd()+'/public/index.php')}; return true; } return false;`);
const origin='http://127.0.0.1:8097';
const server=spawn('php',['-S','127.0.0.1:8097','-t','public',router],{env:{...process.env,BOOKING_EMAIL_ENABLED:'0',FEEDBACK_EMAIL_ENABLED:'0',BOOKING_DB:join(storage,'bookings.sqlite'),BOOKING_SESSION_PATH:storage,ADMIN_USERNAME:'operator',ADMIN_PASSWORD_HASH:hash},stdio:'ignore'});
let browser;
try {
 for(let i=0;i<50;i++){try{await fetch(origin);break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
 context.setDefaultTimeout(5000);
 const page=await context.newPage();
 await page.goto(origin+'/admin');
 assert.match(page.url(),/\/admin\/login$/);
 const preLoginCookie=(await context.cookies()).find(c=>c.name==='nyumba_admin')?.value;
 await page.getByLabel('Username').fill('operator');
 await page.getByLabel('Password',{exact:true}).fill('test-password');
 await page.getByRole('button',{name:'Sign in'}).click();
 assert.notEqual((await context.cookies()).find(c=>c.name==='nyumba_admin')?.value,preLoginCookie);
 assert.equal(await page.getByRole('heading',{name:'Bookings',exact:true}).count(),1);
 await page.getByLabel('Search reference or name').fill('Special');
 await page.getByLabel('Destination',{exact:true}).selectOption('galana');
 await page.getByRole('button',{name:'Apply filters'}).click();
 assert.match(await page.locator('tbody').innerText(),/Special Visitor/);
 assert.equal(await page.locator('tbody tr').count(),1);
 await page.locator('tbody a').click();
 assert.match(await page.locator('main').innerText(),/visitor@example.com/);
 assert.match(await page.locator('main').innerText(),/5 \/ 5/);
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile booking reference must wrap');
 await page.screenshot({path:join(tmpdir(),'nyumba-admin-mobile.png'),fullPage:true});
 assert.match(await page.locator('main').innerText(),/3 May 2099/);
 assert.match(await page.locator('main').innerText(),/<script>alert\(1\)<\/script>/);
 assert.equal(await page.locator('main script').count(),0);
 await page.getByRole('link',{name:'Back to bookings'}).click();
 assert.equal(await page.getByLabel('Search reference or name').inputValue(),'Special');
 await page.getByRole('button',{name:'Sign out'}).click();
 await page.goto(origin+'/admin');
 assert.match(page.url(),/\/admin\/login$/);
 console.log('PASS admin login, filters, overnight details, escaped feedback and logout without JavaScript');
 async function login(target=page) {
  await target.goto(origin+'/admin/login');
  await target.getByLabel('Username').fill('operator');
  await target.getByLabel('Password',{exact:true}).fill('test-password');
  await target.getByRole('button',{name:'Sign in'}).click();
 }
 await login();
 assert.equal(await page.locator('tbody tr').count(),25);
 await page.getByRole('link',{name:'Next',exact:true}).click();
 assert.equal(await page.locator('tbody tr').count(),2);
 await page.goto(origin+'/admin/bookings/2');
 assert.match(await page.locator('main').innerText(),/Not provided/);
 assert.match(await page.locator('main').innerText(),/Did not attend/);
 assert.doesNotMatch(await page.locator('main').innerText(),/Rating/);
 await page.goto(origin+'/admin/bookings/3');
 assert.match(await page.locator('main').innerText(),/No feedback received/);
 assert.equal((await page.goto(origin+'/admin/bookings/9999')).status(),404);
 assert.equal((await page.goto(origin+'/admin?from=2099-05-03&to=2099-05-01')).status(),400);
 assert.equal((await page.goto(origin+'/admin?destination=unknown')).status(),400);
 await page.goto(origin+'/admin?from=2099-05-01&to=2099-05-01&q=Special');
 assert.equal(await page.locator('tbody tr').count(),1);
 await page.goto(origin+'/admin?q=0');
 assert.equal(await page.locator('tbody tr').count(),2);
 await page.goto(origin+'/admin?q=not-found');
 assert.match(await page.locator('main').innerText(),/No bookings found/);
 const protectedResponse=await page.goto(origin+'/admin');
 assert.match(protectedResponse.headers()['cache-control'],/no-store/);
 assert.match(protectedResponse.headers()['x-robots-tag'],/noindex/);
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 const csrf=await page.locator('input[name=csrf]').first().inputValue();
 assert.equal((await context.request.post(origin+'/admin/logout',{form:{csrf:'bad'}})).status(),403);
 await page.goto(origin+'/admin'); assert.match(await page.locator('h1').innerText(),/Bookings/);
 assert.equal((await context.request.get(origin+'/admin/logout')).status(),404);
 await writeFile(clockFile,'1800001800');
 await page.goto(origin+'/admin'); assert.match(page.url(),/login$/);
 await login();
 // Stay active periodically, but the absolute lifetime still ends at eight hours.
 for(let delta=1200;delta<28800;delta+=1200){ await writeFile(clockFile,String(1800001800+delta)); await page.goto(origin+'/admin'); assert.match(await page.locator('h1').innerText(),/Bookings/); }
 await writeFile(clockFile,String(1800001800+28800));
 await page.goto(origin+'/admin'); assert.match(page.url(),/login$/);
 await login();
 await writeFile(hashFile,spawnSync('php',['-r',"echo password_hash('new-password',PASSWORD_DEFAULT);"],{encoding:'utf8'}).stdout);
 await page.goto(origin+'/admin'); assert.match(page.url(),/login$/);
 await writeFile(hashFile,hash);
 // Invalid CSRF never authenticates.
 assert.equal((await context.request.post(origin+'/admin/login',{form:{username:'operator',password:'test-password',csrf:'invalid'}})).status(),403);
 await page.goto(origin+'/admin/bookings/1'); assert.match(page.url(),/login$/);
 // Cookie resets cannot bypass persistent rate limiting.
 for(let attempt=0;attempt<5;attempt++) {
  await context.clearCookies(); await page.goto(origin+'/admin/login');
  await page.getByLabel('Username').fill('operator'); await page.getByLabel('Password',{exact:true}).fill('wrong');
  await page.getByRole('button',{name:'Sign in'}).click(); assert.match(await page.locator('[role=alert]').innerText(),/Unable to sign in/);
 }
 await context.clearCookies(); await login(); assert.match(page.url(),/login$/);
 await writeFile(clockFile,String(1800001800+28800+901));
 await login(); assert.match(await page.locator('h1').innerText(),/Bookings/);
 const authCookie=(await context.cookies()).find(c=>c.name==='nyumba_admin');
 assert.equal(authCookie.httpOnly,true); assert.equal(authCookie.sameSite,'Lax');
 await writeFile(hashFile,''); assert.equal((await page.goto(origin+'/admin')).status(),503); await writeFile(hashFile,hash);
 const outsider=await browser.newContext();
 const outsidePage=await outsider.newPage();
 await outsidePage.goto(origin+'/admin/bookings/1'); assert.match(outsidePage.url(),/login$/);
 await outsider.close();
 assert.deepEqual(domainSnapshot(),beforeAdmin,'Populated migration and admin navigation preserve all domain records');
 // A public booking remains separate from the authenticated admin session.
 await page.goto(origin+'/?book=feeding');
 await page.locator('#full-name').fill('Public Visitor'); await page.locator('#phone').fill('0712345678'); await page.locator('#email').fill('public@example.com');
 await page.locator('#visit-date').fill('2099-05-02'); await page.locator('#time-slot').selectOption('11:00'); await page.locator('#attendees').fill('2');
 await page.locator('[type=submit]').click(); await page.waitForURL(/confirmation=1/);
 await page.goto(origin+'/admin?q=Public'); assert.match(await page.locator('tbody').innerText(),/Public Visitor/);
 const jsContext=await browser.newContext({viewport:{width:1440,height:1000}});
 const jsPage=await jsContext.newPage(); await login(jsPage);
 await jsPage.getByLabel('Search reference or name').fill('Visitor');
 await jsPage.getByRole('button',{name:'Apply filters'}).click();
 await jsPage.getByRole('link',{name:'Next',exact:true}).click();
 await jsPage.locator('tbody a').first().click();
 await jsPage.getByRole('link',{name:'Back to bookings'}).click();
 assert.match(jsPage.url(),/page=2/); assert.equal(await jsPage.getByLabel('Search reference or name').inputValue(),'Visitor');
 await jsPage.screenshot({path:join(tmpdir(),'nyumba-admin-desktop.png'),fullPage:true});
 await jsContext.close();
 console.log('PASS pagination, feedback states, validation, CSRF, expiry, rotation, throttling and public booking isolation');
} finally {await browser?.close();server.kill();await rm(storage,{recursive:true,force:true});}
