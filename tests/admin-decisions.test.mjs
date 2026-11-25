process.env.BRAND_INTRO_ENABLED='0'; // The brand film is covered by tests/brand-intro.test.mjs.
import assert from 'node:assert/strict';
import {mkdtemp,rm,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
// Deciding and changing decisions from the signed-in booking pages, without JavaScript.
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const storage=await mkdtemp(join(tmpdir(),'nyumba-admin-decisions-'));
const database=join(storage,'bookings.sqlite');
const port=8133, origin=`http://127.0.0.1:${port}`;
const env={...process.env,BOOKING_DB:database,BOOKING_SESSION_PATH:storage,BOOKING_EMAIL_ENABLED:'1',BOOKING_SMS_ENABLED:'1',FEEDBACK_EMAIL_ENABLED:'0',BOOKING_SITE_URL:'https://visits.example.com'};
const php=(code,...args)=>{const result=spawnSync('php',['-r',code,database,...args],{env,encoding:'utf8'}); assert.equal(result.status,0,result.stderr); return result.stdout;};
const query=(sql,...params)=>JSON.parse(php(`$db=new PDO('sqlite:'.$argv[1]); $q=$db->prepare($argv[2]); $q->execute(array_slice($argv,3)); echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));`,sql,...params));
const seedUser=(name,email)=>{const result=spawnSync('php',['tests/helpers/seed-user.php',database,name,email,'team','test-password'],{env,encoding:'utf8'}); assert.equal(result.status,0,result.stderr); return Number(result.stdout);};
const olive=seedUser('Olive Operator','operator@example.test'), sam=seedUser('Sam Second','second@example.test');
function book(name,visitDate='2099-05-01') {
 return Number(php(`require 'src/BookingStore.php'; $s=new BookingStore($argv[1]); echo $s->create(json_decode($argv[2],true),$argv[3])['id'];`,JSON.stringify({fullName:name,phone:'0712345678',email:'vera@example.test',location:'galana',visitDate,timeSlot:'10:00',attendees:'3',overnight:'',arrivalDate:null,departureDate:null,overnightGuests:null}),'token-'+name));
}
const status=id=>query('SELECT status FROM bookings WHERE id=?',id)[0].status;
const history=id=>query('SELECT user_id, decision, reason, previous_status FROM booking_decisions WHERE booking_id=? ORDER BY id',id);
const messages=id=>({
 visitor:query("SELECT COUNT(*) n FROM booking_emails WHERE booking_id=? AND kind='decision_visitor'",id)[0].n,
 team:query("SELECT user_id FROM booking_emails WHERE booking_id=? AND kind='decision_team' ORDER BY id",id).map(r=>r.user_id),
 sms:query("SELECT COUNT(*) n FROM booking_sms WHERE booking_id=? AND audience='decision'",id)[0].n,
});
// A booking made before visit approval: confirmed with no history.
const legacy=book('Lena Legacy'); php(`$db=new PDO('sqlite:'.$argv[1]); $db->exec("UPDATE bookings SET status='confirmed' WHERE id=$argv[2]");`,String(legacy));
const pending=book('Paula Pending'), second=book('Rita Reject'), past=book('Pat Past','2099-04-29');
const clockFile=join(storage,'clock');
// 30 Apr 2099, 12:00 Nairobi.
await writeFile(clockFile,String(Date.parse('2099-04-30T09:00:00Z')/1000));
const router=join(storage,'router.php');
await writeFile(router,`<?php if (str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH), '/admin')) { $adminNow=(int)file_get_contents(__DIR__.'/clock'); require ${JSON.stringify(process.cwd()+'/public/index.php')}; return true; } return false;`);
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public',router],{env,stdio:'ignore'});
let browser;
try {
 for(let i=0;i<50;i++){try{await fetch(origin+'/admin/login');break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 async function signedIn(email) {
  const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
  context.setDefaultTimeout(5000);
  const page=await context.newPage();
  await page.goto(origin+'/admin/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password',{exact:true}).fill('test-password');
  await page.getByRole('button',{name:'Sign in'}).click();
  return page;
 }
 const page=await signedIn('operator@example.test');
 const main=()=>page.locator('main').innerText();
 const panel=()=>page.locator('.decision').innerText();

 // The list shows each booking's status and can be filtered by it.
 assert.doesNotMatch(await main(),/Read-only access/);
 assert.deepEqual(await page.locator('thead th').allTextContents(),['Reference','Visitor','Destination','Visit date','Time','Guests','Status','Visitor feedback']);
 assert.deepEqual(await page.getByLabel('Status',{exact:true}).locator('option').allTextContents(),['All','Awaiting confirmation','Confirmed','Rejected']);
 const rowStatus=async name=>(await page.locator('tbody tr',{hasText:name}).locator('[data-label=Status]').innerText()).trim();
 assert.equal(await rowStatus('Lena Legacy'),'Confirmed');
 assert.equal(await rowStatus('Paula Pending'),'Awaiting confirmation');
 await page.getByLabel('Status',{exact:true}).selectOption({label:'Awaiting confirmation'});
 await page.getByRole('button',{name:'Apply filters'}).click();
 assert.equal(await page.locator('tbody tr').count(),3);
 assert.doesNotMatch(await page.locator('tbody').innerText(),/Lena Legacy/);
 assert.match(await page.locator('.csv-link a').getAttribute('href'),/status=pending/);
 await page.getByLabel('Search reference or name').fill('Paula');
 await page.getByRole('button',{name:'Apply filters'}).click();
 assert.equal(await page.locator('tbody tr').count(),1);
 await page.getByLabel('Status',{exact:true}).selectOption({label:'Confirmed'});
 await page.getByRole('button',{name:'Apply filters'}).click();
 assert.match(await main(),/No bookings found/);
 assert.equal((await page.goto(origin+'/admin?status=maybe')).status(),400);
 console.log('PASS status column and filter, combined with search and the CSV link');

 // A migrated booking says it was confirmed before approval existed.
 await page.goto(origin+'/admin/bookings/'+legacy);
 assert.match(await panel(),/^Decision\n/);
 assert.match(await page.locator('.history').innerText(),/Confirmed before visit approval was introduced\./);
 assert.equal(await page.getByRole('link',{name:'Change to rejected'}).count(),1);
 console.log('PASS migrated bookings show their history line');

 // Confirming a pending visit asks first, then saves as the signed-in user and queues the messages.
 await page.goto(origin+'/admin/bookings/'+pending+'?status=pending&page=1');
 assert.match(await panel(),/This visit is awaiting confirmation\./);
 await page.getByRole('link',{name:'Confirm visit'}).click();
 assert.equal(status(pending),'pending');
 assert.match(await panel(),/Confirm this visit\? The visitor and the rest of the team will be told\./);
 await page.getByRole('link',{name:'Cancel'}).click();
 assert.doesNotMatch(await panel(),/Confirm this visit\?/);
 await page.getByRole('link',{name:'Confirm visit'}).click();
 await page.getByRole('button',{name:'Confirm visit'}).click();
 assert.equal(await page.locator('[role=status]').innerText(),'Visit confirmed. The visitor and the team have been told.');
 assert.equal(status(pending),'confirmed');
 assert.deepEqual(history(pending),[{user_id:olive,decision:'confirmed',reason:null,previous_status:'pending'}]);
 assert.deepEqual(messages(pending),{visitor:1,team:[sam],sms:1});
 assert.match(await panel(),/Confirmed by Olive Operator on 30 Apr 2099 at 12:00 PM\./);
 assert.match(await page.locator('.history').innerText(),/30 Apr 2099, 12:00 PM: Confirmed by Olive Operator/);
 assert.equal(await page.getByRole('link',{name:'Back to bookings'}).getAttribute('href'),'/admin?status=pending&page=1');
 console.log('PASS confirm with a confirmation step, recorded under the user, messages queued');

 // Changing to rejected needs a reason; the reason form keeps what was typed.
 await page.getByRole('link',{name:'Change to rejected'}).click();
 assert.match(await panel(),/Reject this visit\? The visitor and the rest of the team will be told\./);
 assert.match(await panel(),/Only the team sees this\. The visitor will be asked to contact you\./);
 assert.equal(await page.getByLabel('Reason for rejecting (required)').getAttribute('required'),'');
 // Blank reasons get past the browser's required check as spaces, and the server still refuses them.
 await page.getByLabel('Reason for rejecting (required)').fill('   ');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.match(await panel(),/Please give a reason for rejecting this visit\./);
 await page.screenshot({path:join(tmpdir(),'nyumba-admin-decision-mobile.png'),fullPage:true});
 assert.equal(status(pending),'confirmed');
 await page.getByLabel('Reason for rejecting (required)').fill('x'.repeat(501));
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.match(await panel(),/Please keep the reason to 500 characters or fewer\./);
 assert.equal((await page.getByLabel('Reason for rejecting (required)').inputValue()).length,501);
 await page.getByLabel('Reason for rejecting (required)').fill('Farm closed for <spraying>');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.equal(await page.locator('[role=status]').innerText(),'Visit rejected. The visitor and the team have been told.');
 assert.equal(status(pending),'rejected');
 assert.deepEqual(history(pending).at(-1),{user_id:olive,decision:'rejected',reason:'Farm closed for <spraying>',previous_status:'confirmed'});
 assert.deepEqual(messages(pending),{visitor:2,team:[sam,sam],sms:2});
 assert.match(await panel(),/Rejected by Olive Operator on 30 Apr 2099 at 12:00 PM\.\s+Reason: Farm closed for <spraying>/);
 const rows=await page.locator('.history li').allInnerTexts();
 assert.deepEqual(rows,['30 Apr 2099, 12:00 PM: Changed to rejected by Olive Operator. Reason: Farm closed for <spraying>','30 Apr 2099, 12:00 PM: Confirmed by Olive Operator']);
 console.log('PASS change to rejected with reason rules and history newest first');

 // Changing back to confirmed, by another user.
 const samPage=await signedIn('second@example.test');
 await samPage.goto(origin+'/admin/bookings/'+pending);
 await samPage.getByRole('link',{name:'Change to confirmed'}).click();
 await samPage.getByRole('button',{name:'Confirm visit'}).click();
 assert.equal(status(pending),'confirmed');
 assert.deepEqual(history(pending).at(-1),{user_id:sam,decision:'confirmed',reason:null,previous_status:'rejected'});
 assert.deepEqual(messages(pending).team,[sam,sam,olive]);
 assert.match(await samPage.locator('.history li').first().innerText(),/Changed to confirmed by Sam Second$/);
 console.log('PASS change back to confirmed by another user');

 // Rejecting a pending visit, and a conflict when someone else decided first.
 await page.goto(origin+'/admin/bookings/'+second);
 await page.getByRole('link',{name:'Reject visit'}).click();
 await samPage.goto(origin+'/admin/bookings/'+second);
 await samPage.getByRole('link',{name:'Confirm visit'}).click();
 await samPage.getByRole('button',{name:'Confirm visit'}).click();
 await page.getByLabel('Reason for rejecting (required)').fill('Too late');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.equal(await page.locator('[role=alert]').innerText(),"Someone else decided this visit while you were looking at it. Here's the latest status.");
 assert.equal(status(second),'confirmed');
 assert.equal(history(second).length,1);
 assert.match(await panel(),/Confirmed by Sam Second/);
 await page.getByRole('link',{name:'Change to rejected'}).click();
 await page.getByLabel('Reason for rejecting (required)').fill('Too late');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.equal(status(second),'rejected');
 const fresh=book('Fiona Fresh');
 await page.goto(origin+'/admin/bookings/'+fresh);
 await page.getByRole('link',{name:'Reject visit'}).click();
 await page.getByLabel('Reason for rejecting (required)').fill('No space');
 await page.getByRole('button',{name:'Reject visit'}).click();
 assert.equal(status(fresh),'rejected');
 assert.match(await page.locator('.history li').first().innerText(),/: Rejected by Olive Operator\. Reason: No space$/);
 console.log('PASS reject while pending and the conflict message');

 // After the visit day there are no decision buttons, and posts are refused.
 await page.goto(origin+'/admin/bookings/'+past);
 assert.match(await panel(),/Decisions can no longer be changed, because the visit date has passed\./);
 assert.equal(await page.locator('.decision a, .decision button').count(),0);
 await page.goto(origin+'/admin/bookings/'+past+'?decide=confirmed');
 assert.equal(await page.locator('.decision button').count(),0);
 const csrf=await page.locator('input[name=csrf]').first().inputValue();
 await page.request.post(origin+'/admin/bookings/'+past+'/decision',{form:{csrf,decision:'confirmed',from:'pending'}});
 assert.equal(status(past),'pending');
 console.log('PASS cutoff after the visit day');

 // Every decision post needs the form token.
 const forged=await page.request.post(origin+'/admin/bookings/'+fresh+'/decision',{form:{csrf:'wrong',decision:'confirmed',from:'rejected'}});
 assert.equal(forged.status(),403);
 assert.equal(status(fresh),'rejected');
 assert.equal((await page.request.post(origin+'/admin/bookings/'+fresh+'/decision',{form:{csrf,decision:'pending',from:'rejected'}})).status(),400);
 assert.equal((await page.request.post(origin+'/admin/bookings/9999/decision',{form:{csrf,decision:'confirmed',from:'pending'}})).status(),404);
 assert.equal(status(fresh),'rejected');
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Decision panel fits on a phone');
 console.log('PASS CSRF and bad input on decision posts');

 // The visit day itself is still open until 23:59 Nairobi (a fresh sign-in, since the clock goes back).
 await writeFile(clockFile,String(Date.parse('2099-04-29T20:59:00Z')/1000));
 const early=await signedIn('operator@example.test');
 await early.goto(origin+'/admin/bookings/'+past);
 assert.equal(await early.getByRole('link',{name:'Confirm visit'}).count(),1);
 console.log('PASS decisions stay open through the visit day');
} finally {await browser?.close();server.kill();await rm(storage,{recursive:true,force:true});}
