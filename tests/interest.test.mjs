import assert from 'node:assert/strict';
import {mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const storage=await mkdtemp(join(tmpdir(),'nyumba-interest-'));
const database=join(storage,'bookings.sqlite');
const quiet=join(storage,'quiet.sqlite');
const hash=spawnSync('php',['-r',"echo password_hash('test-password', PASSWORD_DEFAULT);"],{encoding:'utf8'}).stdout;
const origin='http://127.0.0.1:8093', quietOrigin='http://127.0.0.1:8096';
const launch=(port,db,email)=>spawn('php',['-S','127.0.0.1:'+port,'-t','public'],{env:{...process.env,BOOKING_DB:db,BOOKING_SESSION_PATH:storage,BOOKING_EMAIL_ENABLED:email,BOOKING_SMS_ENABLED:'1',FEEDBACK_EMAIL_ENABLED:'0',BOOKING_SITE_URL:'https://visits.example.com',ADMIN_USERNAME:'operator',ADMIN_PASSWORD_HASH:hash},stdio:'ignore'});
const servers=[launch(8093,database,'1'),launch(8096,quiet,'0')];
const query=(sql,db=database)=>{const result=spawnSync('php',['-r','$db=new PDO("sqlite:".$argv[1]); echo json_encode($db->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));',db,sql],{encoding:'utf8'}); assert.equal(result.status,0,result.stderr); return JSON.parse(result.stdout);};
// Preferred dates are tomorrow to twelve months ahead, in Nairobi.
const [year,month,day]=new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Nairobi',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date()).split('-').map(Number);
const iso=date=>date.toISOString().slice(0,10);
const today=iso(new Date(Date.UTC(year,month-1,day))), tomorrow=iso(new Date(Date.UTC(year,month-1,day+1)));
const lastDay=iso(new Date(Date.UTC(year+1,month-1,day))), tooLate=iso(new Date(Date.UTC(year+1,month-1,day+1)));
let browser;
async function submitWith(page,values) {
 const response=page.waitForResponse(r=>r.request().method()==='POST');
 await page.locator('form').evaluate((form,values)=>{for(const [key,value] of Object.entries(values)){let input=form.elements[key]; if(!input){input=document.createElement('input'); input.type='hidden'; input.name=key; form.append(input);} input.disabled=false; input.value=value;} form.noValidate=true; form.submit();},values);
 return (await response).status();
}
try {
 for(const target of [origin,quietOrigin]) for(let i=0;i<50;i++){try{if((await fetch(target)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const context=await browser.newContext({viewport:{width:1280,height:900},reducedMotion:'reduce'});
 const page=await context.newPage();
 const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 await page.goto(origin);
 for(const id of ['sahajanand','feeding']) {
  assert.equal(await page.locator(`[data-destination="${id}"] .card-status`).innerText(),'Visits opening soon');
  assert.match(await page.locator(`[data-book="${id}"]`).innerText(),/Tell us you’re interested/);
 }
 assert.match(await page.locator('[data-book="galana"]').innerText(),/Let’s plan a visit/);
 assert.equal(await page.locator('[data-destination="galana"] .card-status').count(),0);
 console.log('PASS Kibarani and Sahajanand cards invite interest; Galana Farm still invites bookings');

 await page.locator('[data-book="feeding"]').click();
 assert.match(await page.locator('#booking-title').innerText(),/Tell us you’re interested/);
 assert.match(await page.locator('.prototype-notice').innerText(),/Not a booking/);
 assert.equal(await page.locator('#time-slot').isVisible(),false);
 assert.equal(await page.getByRole('checkbox',{name:'I would like the company to arrange transport for my visit.'}).isVisible(),false);
 assert.equal(await page.locator('#overnight').isVisible(),false);
 assert.equal(await page.getByLabel('Preferred visit date').getAttribute('min'),tomorrow);
 assert.equal(await page.getByLabel('Preferred visit date').getAttribute('max'),lastDay);
 await page.locator('#full-name').fill('Amina Otieno');
 await page.locator('#organization').fill('Mombasa Primary School');
 await page.locator('#phone').fill('0712 345 678');
 await page.getByLabel('Preferred visit date').fill(tomorrow);
 await page.locator('#attendees').fill('12');
 const payload=await page.locator('form').evaluate(form=>Object.fromEntries(new FormData(form)));
 await page.getByRole('button',{name:'Register my interest',exact:true}).click();
 await page.locator('#interest-confirmation-view').waitFor({state:'visible'});
 const thanks=await page.locator('#interest-confirmation-view').innerText();
 assert.match(thanks,/Thanks — we’ve noted your interest/);
 assert.match(thanks,/Visits to Kibarani Feeding center aren’t open yet. We’ll contact you if they reopen./);
 assert.match(thanks,/Amina Otieno/); assert.match(thanks,/Mombasa Primary School/); assert.match(thanks,/12/);
 assert.match(thanks,/••• ••• 678/); assert.doesNotMatch(thanks,/712 345|NY-/);
 assert.equal(await page.locator('#confirmation-view').isVisible(),false);
 await page.reload();
 assert.match(await page.locator('#interest-confirmation-view').innerText(),/Amina Otieno/);
 assert.equal((await page.request.post(origin+'/',{form:payload,maxRedirects:0})).status(),303);
 const saved=query('SELECT * FROM interest_registrations');
 assert.equal(saved.length,1,'Replay and refresh keep one registration');
 assert.deepEqual([saved[0].full_name,saved[0].organization,saved[0].phone,saved[0].email,saved[0].destination,saved[0].preferred_date,saved[0].attendees],['Amina Otieno','Mombasa Primary School','+254712345678',null,'feeding',tomorrow,12]);
 assert.equal(query('SELECT COUNT(*) AS n FROM bookings')[0].n,0,'No booking is created');
 console.log('PASS interest form registers interest with an on-screen summary and no booking reference');

 const emails=query('SELECT * FROM booking_emails');
 assert.equal(emails.length,1,'One email for the group managers');
 const email=JSON.parse(emails[0].payload);
 assert.equal(emails[0].kind,'interest'); assert.equal(emails[0].booking_id,null);
 assert.deepEqual(email.to,['harithjaved@gmail.com','apoorvchit@gmail.com']);
 const preferred=new Date(tomorrow+'T00:00:00Z');
 assert.equal(email.subject,`New interest: Kibarani Feeding Center — 12 people, ${preferred.getUTCDate()} ${preferred.toLocaleString('en-GB',{month:'short',timeZone:'UTC'})}`);
 for(const line of ['Name: Amina Otieno','Organization: Mombasa Primary School','Phone: +254712345678','Email: Not provided','Attendees: 12','https://visits.example.com/admin/interest']) assert.ok(email.text.includes(line),line);
 assert.equal(query('SELECT COUNT(*) AS n FROM booking_sms')[0].n,0,'No SMS to anyone');
 console.log('PASS each registration queues one email to the group managers only, and no SMS');

 await page.goto(origin+'/?book=sahajanand');
 for(const [field,value,message] of [['fullName','','full name'],['phone','abc','phone number'],['attendees','0','whole number'],['email','not-an-email','valid email'],['visitDate',today,'from tomorrow'],['visitDate',tooLate,'from tomorrow'],['visitDate','2099-02-30','valid preferred'],['location','galana','opening soon']]) {
  await page.goto(origin+'/?book=sahajanand');
  const status=await submitWith(page,{fullName:'Retained Person',phone:'0712345678',visitDate:tomorrow,attendees:'2',[field]:value});
  assert.equal(status,422,field+' '+value);
  assert.match(await page.locator('#error-summary').innerText(),new RegExp(message));
  if(field!=='fullName') assert.equal(await page.locator('#full-name').inputValue(),'Retained Person');
 }
 for(const date of [tomorrow,lastDay]) {
  await page.goto(origin+'/?book=sahajanand');
  assert.equal(await submitWith(page,{fullName:'Boundary '+date,phone:'0712345678',email:'boundary@example.com',visitDate:date,attendees:'1'}),303);
  await page.locator('#interest-confirmation-view').waitFor({state:'visible'});
 }
 assert.equal(query('SELECT COUNT(*) AS n FROM interest_registrations')[0].n,3);
 await page.goto(origin+'/?book=feeding');
 assert.equal(await submitWith(page,{fullName:'Sneaky Booker',phone:'0712345678',visitDate:tomorrow,attendees:'1',intent:'',timeSlot:'11:00'}),422);
 assert.match(await page.locator('#error-summary').innerText(),/not open for booking/);
 assert.equal(query('SELECT COUNT(*) AS n FROM bookings')[0].n,0);
 console.log('PASS server validation, date boundaries and rejection of bookings for interest-only places');

 const native=await browser.newContext({javaScriptEnabled:false});
 const nativePage=await native.newPage();
 await nativePage.goto(origin+'/');
 await nativePage.locator('[data-book="sahajanand"]').click();
 assert.equal(await nativePage.locator('#time-slot').isVisible(),false);
 await nativePage.locator('#full-name').fill('No Script'); await nativePage.locator('#phone').fill('+44 20 7946 0958');
 await nativePage.getByLabel('Preferred visit date').fill(tomorrow);
 await nativePage.getByRole('button',{name:'Register my interest',exact:true}).click();
 await nativePage.locator('#interest-confirmation-view').waitFor({state:'visible'});
 assert.match(await nativePage.locator('#interest-confirmation-view').innerText(),/Sahajanand Special School aren’t open yet/);
 await native.close();
 await page.goto(origin+'/?book=galana');
 assert.equal(await page.locator('#time-slot').isVisible(),true);
 assert.match(await page.locator('#booking-title').innerText(),/Make it a date/);
 assert.deepEqual(errors,[]);
 console.log('PASS interest form works without JavaScript and Galana Farm keeps its booking form');

 const quietPage=await browser.newPage();
 await quietPage.goto(quietOrigin+'/?book=feeding');
 await quietPage.locator('#full-name').fill('Quiet Person'); await quietPage.locator('#phone').fill('0712345678');
 await quietPage.getByLabel('Preferred visit date').fill(tomorrow);
 await quietPage.getByRole('button',{name:'Register my interest',exact:true}).click();
 await quietPage.locator('#interest-confirmation-view').waitFor({state:'visible'});
 assert.equal(query('SELECT COUNT(*) AS n FROM interest_registrations',quiet)[0].n,1);
 assert.equal(query('SELECT COUNT(*) AS n FROM booking_emails',quiet)[0].n,0);
 console.log('PASS nothing is queued while booking email is switched off');

 // Admin: seed 27 more registrations spread over past months, with one formula-like name.
 const seed=spawnSync('php',['-r',`require 'src/BookingStore.php'; $s=new BookingStore($argv[1]); $db=new PDO('sqlite:'.$argv[1]); for($i=0;$i<27;$i++){ $s->createInterest(['fullName'=>$i===0?'=HYPERLINK("http://x")':'Seed '.$i,'organization'=>$i===1?'Coast Scouts':'','phone'=>'+2547000000'.sprintf('%02d',$i),'email'=>'','location'=>$i<9?'sahajanand':'feeding','visitDate'=>'2099-01-01','attendees'=>'2'],'seed'.$i); $db->prepare('UPDATE interest_registrations SET created_at=? WHERE submission_token=?')->execute([gmdate('Y-m-d\\TH:i:s\\Z', strtotime('-'.(40+$i*20).' days')), 'seed'.$i]); }`,database],{encoding:'utf8',env:{...process.env,BOOKING_EMAIL_ENABLED:'0'}});
 assert.equal(seed.status,0,seed.stderr);
 const before=query('SELECT * FROM interest_registrations ORDER BY id');
 const outsider=await browser.newContext();
 const outsiderPage=await outsider.newPage();
 for(const path of ['/admin/interest','/admin/interest.csv']) { await outsiderPage.goto(origin+path); assert.match(outsiderPage.url(),/\/admin\/login$/); }
 await outsider.close();
 const admin=await browser.newPage();
 await admin.goto(origin+'/admin/login');
 await admin.locator('#admin-username').fill('operator'); await admin.locator('#admin-password').fill('test-password');
 await admin.locator('[type="submit"]').click(); await admin.waitForURL(/\/admin$/);
 await admin.getByRole('navigation',{name:'Administration'}).getByRole('link',{name:'Interest'}).click();
 await admin.waitForURL(/\/admin\/interest$/);
 const cards=await admin.locator('.summary-cards').innerText();
 // Sahajanand: 2 boundary + 1 no-script + 9 seeded; Kibarani: Amina (12 people) + 18 seeded.
 assert.match(cards,/Sahajanand Special School\s+12 registrations\s+21 people/);
 assert.match(cards,/Kibarani Feeding Center\s+19 registrations\s+48 people/);
 const rows=admin.locator('tbody tr[role=row]');
 assert.equal(await rows.count(),25);
 assert.match(await rows.first().innerText(),/No Script/,'Newest first');
 await admin.getByRole('link',{name:'Next'}).click();
 assert.equal(await rows.count(),6);
 console.log('PASS admin Interest page shows totals, newest-first list and pagination');

 await admin.goto(origin+'/admin/interest?destination=sahajanand');
 assert.equal(await rows.count(),12);
 assert.doesNotMatch(await admin.locator('.summary-cards').innerText(),/Kibarani/);
 await admin.goto(origin+'/admin/interest?q=coast');
 assert.match(await rows.innerText(),/Seed 1/); assert.equal(await rows.count(),1);
 await admin.goto(origin+'/admin/interest?q=%2B254700000005');
 assert.match(await rows.innerText(),/Seed 5/); assert.equal(await rows.count(),1);
 await admin.goto(origin+'/admin/interest?q=amina');
 assert.equal(await rows.count(),1);
 assert.match(await admin.locator('.summary-cards').innerText(),/Sahajanand Special School\s+0 registrations\s+0 people[\s\S]*Kibarani Feeding Center\s+1 registration\s+12 people/,'Cards follow the filters');
 await admin.goto(origin+`/admin/interest?from=${today}&to=${today}`);
 assert.equal(await rows.count(),4,'Registrations made today');
 for(const bad of ['destination=galana','from=2099-02-30','from=2099-02-02&to=2099-02-01']) assert.equal((await admin.goto(origin+'/admin/interest?'+bad)).status(),400,bad);
 assert.equal((await admin.goto(origin+'/admin/interest.csv?destination=galana')).status(),400);
 console.log('PASS admin filters by place, search (name, organization, phone) and registration date, rejecting invalid filters');

 const csv=await admin.request.get(origin+'/admin/interest.csv?destination=sahajanand');
 assert.equal(csv.status(),200);
 assert.match(csv.headers()['content-type'],/^text\/csv/);
 assert.match(csv.headers()['content-disposition'],/attachment; filename="interest-registrations-\d{4}-\d{2}-\d{2}\.csv"/);
 const lines=(await csv.text()).replace(/^﻿/,'').trim().split('\n');
 assert.equal(lines[0],'"Registered at (Nairobi)",Destination,Visitor,Organization,Phone,Email,"Preferred visit date",Guests');
 assert.equal(lines.length,13,'Header and twelve filtered rows, without pagination');
 assert.ok(lines.every((line,i)=>i===0||line.includes('Sahajanand Special School')));
 const formula=lines.find(line=>line.includes('HYPERLINK'));
 assert.ok(formula.includes(`"'=HYPERLINK(""http://x"")"`),'Formula-like values are neutralised');
 assert.ok(formula.includes(',+254700000000,'),'Phone numbers stay as entered');
 const all=(await (await admin.request.get(origin+'/admin/interest.csv')).text()).trim().split('\n');
 assert.equal(all.length,32,'Unfiltered export includes every registration');
 await admin.goto(origin+'/admin/interest?destination=sahajanand');
 assert.equal(await admin.getByRole('link',{name:'Download CSV'}).getAttribute('href'),'/admin/interest.csv?destination=sahajanand');
 assert.deepEqual(query('SELECT * FROM interest_registrations ORDER BY id'),before,'Admin use changes nothing');
 console.log('PASS CSV export matches the filters, neutralises formulas and keeps admin read-only');
} finally {
 await browser?.close();
 for(const server of servers) server.kill();
 await Promise.all(servers.map(server=>new Promise(r=>server.exitCode!==null?r():server.once('exit',r))));
 await rm(storage,{recursive:true,force:true});
}
