process.env.BRAND_INTRO_ENABLED='0'; // The brand film is covered by tests/brand-intro.test.mjs.
import assert from 'node:assert/strict';
import {mkdtemp,rm,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
// The Accept/Reject pages behind the buttons in the team email: no sign-in, one booking, the link owner's decision.
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const storage=await mkdtemp(join(tmpdir(),'nyumba-decide-'));
const database=join(storage,'bookings.sqlite');
const port=8131, origin=`http://127.0.0.1:${port}`;
const env={...process.env,BOOKING_DB:database,BOOKING_SESSION_PATH:storage,BOOKING_EMAIL_ENABLED:'1',BOOKING_SMS_ENABLED:'1',FEEDBACK_EMAIL_ENABLED:'1',BOOKING_SITE_URL:'https://visits.example.com',ADMIN_TRUSTED_PROXIES:'127.0.0.1'};
const php=(code,...args)=>{const result=spawnSync('php',['-r',code,database,...args],{env,encoding:'utf8'}); assert.equal(result.status,0,result.stderr); return result.stdout;};
const query=(sql,...params)=>JSON.parse(php(`$db=new PDO('sqlite:'.$argv[1]); $q=$db->prepare($argv[2]); $q->execute(array_slice($argv,3)); echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));`,sql,...params));
const seedUser=(name,email)=>{const result=spawnSync('php',['tests/helpers/seed-user.php',database,name,email,'team','test-password'],{env,encoding:'utf8'}); assert.equal(result.status,0,result.stderr); return Number(result.stdout);};
const priya=seedUser('Priya Planner','priya@example.test'), tom=seedUser('Tom Team','tom@example.test'), gina=seedUser('Gina Gone','gina@example.test'), eve=seedUser('Eve Email','eve@example.test');
// Books a visit and returns each user's two links, rewritten to the test server.
function book(name,extra={}) {
 const id=Number(php(`require 'src/BookingStore.php'; $s=new BookingStore($argv[1]); echo $s->create(json_decode($argv[2],true),$argv[3])['id'];`,JSON.stringify({fullName:name,phone:'0712345678',email:'vera@example.test',location:'galana',visitDate:'2099-05-01',timeSlot:'10:00',attendees:'3',overnight:'',arrivalDate:null,departureDate:null,overnightGuests:null,organization:'Acme School',...extra}),'token-'+name));
 const links={};
 for(const row of query("SELECT user_id, payload FROM booking_emails WHERE kind='decision_request' AND booking_id=?",id)) {
  const [accept,reject]=[...JSON.parse(row.payload).text.matchAll(/https:\/\/visits\.example\.com(\/decide\?token=[a-f0-9]{64})/g)].map(m=>origin+m[1]);
  links[row.user_id]={accept,reject};
 }
 return {id,links,reference:query('SELECT reference FROM bookings WHERE id=?',id)[0].reference};
}
const status=id=>query('SELECT status FROM bookings WHERE id=?',id)[0].status;
const history=id=>query('SELECT user_id, decision, reason FROM booking_decisions WHERE booking_id=? ORDER BY id',id);
const clockFile=join(storage,'clock');
await writeFile(clockFile,String(Date.parse('2099-04-30T09:00:00Z')/1000));
const router=join(storage,'router.php');
await writeFile(router,`<?php if (parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) === '/decide') { $decisionNow=(int)file_get_contents(__DIR__.'/clock'); require ${JSON.stringify(process.cwd()+'/public/index.php')}; return true; } return false;`);
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public',router],{env,stdio:'ignore'});
let browser;
try {
 for(let i=0;i<50;i++){try{await fetch(origin+'/decide');break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const context=await browser.newContext({viewport:{width:390,height:844}});
 context.setDefaultTimeout(5000);
 const page=await context.newPage();

 // Opening a link changes nothing and shows the summary.
 const first=book('Vera Visitor',{transportRequested:'on'});
 for(const link of [first.links[priya].accept,first.links[priya].reject,first.links[tom].accept]) { const response=await page.goto(link); assert.equal(response.status(),200); }
 const response=await page.goto(first.links[priya].accept);
 assert.equal(response.headers()['cache-control'],'no-store, private');
 assert.equal(response.headers()['referrer-policy'],'no-referrer');
 // Behind the trusted proxy, the session cookie is Secure when the proxy says the request was HTTPS.
 for(const [proto,secure] of [['https',true],['http',false]]) {
  const cookie=(await fetch(first.links[priya].accept,{headers:{'X-Forwarded-Proto':proto}})).headers.get('set-cookie');
  assert.equal(/;\s*secure/i.test(cookie),secure,`Secure cookie over ${proto}: ${cookie}`);
 }
 assert.equal(await page.title(),`Visit ${first.reference} · Galana Farm`);
 assert.equal(await page.locator('h1').innerText(),'Accept this visit?');
 const summary=await page.locator('.summary').innerText();
 for(const text of ['Vera Visitor (Acme School)','Fri 1 May 2099 at 10:00 am EAT · 3 people','Transport: Requested','Phone 0712345678 · Email vera@example.test']) assert.ok(summary.includes(text),text);
 assert.ok(!summary.includes('Overnight stay'));
 assert.ok((await page.locator('main').innerText()).includes("Vera Visitor will be told by email and SMS that their visit is confirmed. The rest of the team will be told you accepted it."));
 assert.equal(status(first.id),'pending'); assert.deepEqual(history(first.id),[]);
 assert.equal(query("SELECT COUNT(*) n FROM booking_emails WHERE kind IN ('decision_visitor','decision_team')")[0].n,0);
 console.log('PASS opening a link shows the summary and changes nothing');

 // CSRF is enforced.
 const forged=await page.request.post(first.links[priya].accept,{form:{csrf:'wrong',decision:'confirmed'}});
 assert.equal(forged.status(),403); assert.equal(status(first.id),'pending');
 console.log('PASS decisions need the form token');

 // Tom opens his Reject page before Priya decides; his later submission must lose.
 const tomPage=await context.newPage();
 await tomPage.goto(first.links[tom].reject);
 await tomPage.getByLabel('Reason for rejecting (required)').fill('No space');
 // Accepting records the decision under the link's user and tells everyone.
 await page.getByRole('button',{name:'Yes, accept visit'}).click();
 assert.equal(await page.locator('h1').innerText(),'Visit confirmed.');
 assert.ok((await page.locator('main').innerText()).includes("We've told Vera Visitor and the rest of the team. You can close this page."));
 assert.equal(status(first.id),'confirmed');
 assert.deepEqual(history(first.id),[{user_id:priya,decision:'confirmed',reason:null}]);
 assert.equal(query("SELECT COUNT(*) n FROM booking_emails WHERE kind='decision_visitor' AND booking_id=?",first.id)[0].n,1);
 assert.deepEqual(query("SELECT user_id FROM booking_emails WHERE kind='decision_team' AND booking_id=? ORDER BY user_id",first.id).map(r=>r.user_id),[tom,gina,eve]);
 assert.equal(query("SELECT COUNT(*) n FROM booking_sms WHERE audience='decision' AND booking_id=?",first.id)[0].n,1);
 console.log('PASS accepting confirms the visit as the link owner and queues the messages');

 // First decision wins: every link now shows who decided, with no buttons.
 for(const link of [first.links[priya].accept,first.links[tom].reject]) {
  await page.goto(link);
  assert.equal(await page.locator('h1').innerText(),'Already confirmed.');
  const text=await page.locator('main').innerText();
  assert.match(text,/Confirmed by Priya Planner on 30 Apr at 12:00\./);
  assert.ok(text.includes('To change a decision, sign in to the admin panel.'));
  assert.equal(await page.locator('main a[href="/admin/bookings/'+first.id+'"]').count(),1);
  assert.equal(await page.locator('button').count(),0);
 }
 const [lost]=await Promise.all([tomPage.waitForResponse(r=>r.request().method()==='POST'),tomPage.getByRole('button',{name:'Reject visit'}).click()]);
 assert.equal(lost.status(),409);
 assert.equal(await tomPage.locator('h1').innerText(),'Already confirmed.');
 assert.equal(status(first.id),'confirmed'); assert.equal(history(first.id).length,1);
 await tomPage.close();
 console.log('PASS the first decision wins and later links show the status');

 // Rejecting needs a reason the visitor never sees.
 const other=book('Rex Rejected');
 await page.goto(other.links[tom].accept);
 await page.getByRole('link',{name:'Reject instead'}).click();
 assert.equal(await page.locator('h1').innerText(),'Reject this visit?');
 assert.ok((await page.locator('main').innerText()).includes("Only the team sees this. The visitor won't see it. They'll be asked to contact you, Tom Team, on "));
 assert.equal(await page.locator('.counter').innerText(),'0/500');
 await page.getByLabel('Reason for rejecting (required)').fill('Flooded');
 assert.equal(await page.locator('.counter').innerText(),'7/500');
 await page.getByLabel('Reason for rejecting (required)').fill('   ');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.ok((await page.locator('main').innerText()).includes('Please give a reason for rejecting this visit.'));
 await page.getByLabel('Reason for rejecting (required)').fill('x'.repeat(501));
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.ok((await page.locator('main').innerText()).includes('Please keep the reason to 500 characters or fewer.'));
 assert.equal(await page.getByLabel('Reason for rejecting (required)').inputValue(),'x'.repeat(501));
 assert.equal(status(other.id),'pending');
 await page.getByRole('link',{name:'Accept instead'}).click();
 assert.equal(await page.locator('h1').innerText(),'Accept this visit?');
 await page.goto(other.links[tom].reject);
 await page.getByLabel('Reason for rejecting (required)').fill('  Flooded access road  ');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.equal(await page.locator('h1').innerText(),'Visit rejected.');
 assert.ok((await page.locator('main').innerText()).includes("We've told Rex Rejected to contact you, and shared your reason with the rest of the team. You can close this page."));
 assert.deepEqual(history(other.id),[{user_id:tom,decision:'rejected',reason:'Flooded access road'}]);
 const visitorMail=JSON.parse(query("SELECT payload FROM booking_emails WHERE kind='decision_visitor' AND booking_id=?",other.id)[0].payload);
 assert.ok(visitorMail.text.includes('Please contact Tom Team') && !visitorMail.text.includes('Flooded'));
 assert.ok(JSON.parse(query("SELECT payload FROM booking_emails WHERE kind='decision_team' AND booking_id=? AND user_id=?",other.id,priya)[0].payload).text.includes('Reason: Flooded access road'));
 await page.goto(other.links[priya].accept);
 assert.equal(await page.locator('h1').innerText(),'Already rejected.');
 assert.match(await page.locator('main').innerText(),/Rejected by Tom Team on 30 Apr at 12:00\./);
 console.log('PASS rejecting needs a 1-500 character reason that only the team sees');

 // Links stop at 23:59 Nairobi on the visit date.
 const late=book('Lena Late',{overnight:'on',arrivalDate:'2099-05-01',departureDate:'2099-05-02',overnightGuests:'2'});
 await page.goto(late.links[priya].accept);
 assert.ok((await page.locator('.summary').innerText()).includes('Overnight stay: Requested'));
 await writeFile(clockFile,String(Date.parse('2099-05-01T20:59:59Z')/1000));
 await page.goto(late.links[priya].accept);
 assert.equal(await page.locator('h1').innerText(),'Accept this visit?');
 await writeFile(clockFile,String(Date.parse('2099-05-01T21:00:00Z')/1000));
 await page.getByRole('button',{name:'Yes, accept visit'}).click();
 assert.equal(await page.locator('h1').innerText(),'Still awaiting a decision.');
 assert.ok((await page.locator('main').innerText()).includes('This link stopped working at 23:59 on the visit date.'));
 assert.equal(await page.locator('button').count(),0);
 assert.equal(status(late.id),'pending');
 await page.goto(first.links[tom].accept);
 assert.equal(await page.locator('h1').innerText(),'Already confirmed.');
 assert.ok((await page.locator('main').innerText()).includes('This link stopped working at 23:59 on the visit date.'));
 await writeFile(clockFile,String(Date.parse('2099-04-30T09:00:00Z')/1000));
 console.log('PASS links stop working after the visit day');

 // Tampered, unknown, deactivated-user and changed-email links are refused.
 const fresh=book('Ivy Invalid');
 const tampered=fresh.links[priya].accept.replace(/.$/,c=>c==='0'?'1':'0');
 spawnSync('php',['bin/users.php','deactivate','gina@example.test'],{env,encoding:'utf8'});
 php(`require 'src/UserStore.php'; UserStore::open($argv[1])->edit((int)$argv[2], ['email'=>'eve.new@example.test']);`,String(eve));
 assert.equal(query('SELECT active FROM users WHERE id=?',gina)[0].active,0);
 for(const link of [tampered,origin+'/decide?token=nope',origin+'/decide',origin+'/decide?token[]=x',fresh.links[gina].accept,fresh.links[eve].reject]) {
  const result=await page.goto(link);
  assert.equal(result.status(),404,link);
  assert.equal(await page.locator('h1').innerText(),"This link doesn't work.");
  assert.ok((await page.locator('main').innerText()).includes("It may be incomplete, or it's no longer valid. Sign in to the admin panel to see the booking."));
 }
 const blocked=await page.request.post(fresh.links[gina].accept,{form:{decision:'confirmed'}});
 assert.equal(blocked.status(),404); assert.equal(status(fresh.id),'pending');
 console.log('PASS tampered, unknown, deactivated and changed-email links are refused');
} finally {
 await browser?.close();
 server.kill();
 await rm(storage,{recursive:true,force:true});
}
