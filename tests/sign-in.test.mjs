import assert from 'node:assert/strict';
import {mkdtemp,rm,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
// Staff sign-in pages: password, emailed sign-in links, password reset and expired links.
const storage=await mkdtemp(join(tmpdir(),'nyumba-sign-in-'));
const db=join(storage,'bookings.sqlite');
function php(args) { const result=spawnSync('php',args,{encoding:'utf8',env:{...process.env,BOOKING_DB:db}}); assert.equal(result.status,0,result.stderr+result.stdout); return result.stdout; }
php(['tests/helpers/seed-user.php',db,'Ada Admin','ada@example.test','admin','ada password 123']);
php(['tests/helpers/seed-user.php',db,'Tom Team','tom@example.test','team','tom password 123']);
const clockFile=join(storage,'clock');
let clock=1800000000;
const setClock=async value=>{clock=value;await writeFile(clockFile,String(value));};
await setClock(clock);
const router=join(storage,'router.php');
await writeFile(router,`<?php if (str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH), '/admin')) { $adminNow=(int)file_get_contents(__DIR__.'/clock'); require ${JSON.stringify(process.cwd()+'/public/index.php')}; return true; } return false;`);
const port=8096, origin=`http://127.0.0.1:${port}`;
// The retired environment-variable login is set here to prove it no longer signs anyone in.
const legacyHash=php(['-r',"echo password_hash('legacy-password', PASSWORD_DEFAULT);"]);
const server=spawn('php',['-S',`127.0.0.1:${port}`,'-t','public',router],{env:{...process.env,BOOKING_EMAIL_ENABLED:'0',FEEDBACK_EMAIL_ENABLED:'0',BOOKING_SITE_URL:'https://visits.example.com',BOOKING_DB:db,BOOKING_SESSION_PATH:storage,ADMIN_USERNAME:'operator',ADMIN_PASSWORD_HASH:legacyHash},stdio:'ignore'});
const emails=()=>JSON.parse(php(['tests/helpers/account-emails.php',db,String(clock)]));
// The emailed link, pointed at the test server instead of the canonical site.
function linkIn(email,path) {
 const match=email.text.match(new RegExp('https://visits\\.example\\.com('+path+'\\?token=[a-f0-9]{64})'));
 assert.ok(match,`Email links to ${path}`); return origin+match[1];
}
let browser;
try {
 for(let i=0;i<50;i++){try{await fetch(origin);break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 const fresh=async()=>{const context=await browser.newContext({javaScriptEnabled:false}); context.setDefaultTimeout(5000); return [context,await context.newPage()];};
 const [context,page]=await fresh();
 const signIn=async(email,password,target=page)=>{await target.goto(origin+'/admin/login'); await target.getByLabel('Email').fill(email); await target.getByLabel('Password',{exact:true}).fill(password); await target.getByRole('button',{name:'Sign in',exact:true}).click();};
 const signedIn=async(target=page)=>{await target.goto(origin+'/admin'); return !/\/admin\/login$/.test(target.url());};

 await page.goto(origin+'/admin/login');
 assert.match(await page.locator('main').innerText(),/Your workspace[\s\S]*Welcome back\.[\s\S]*Sign in to review bookings[\s\S]*and visitor feedback\./i);
 assert.equal(await page.getByRole('link',{name:'Forgot password?'}).count(),1);
 assert.equal(await page.getByRole('button',{name:'Email me a sign-in link'}).count(),1);
 const csrf=await page.locator('input[name=csrf]').inputValue();
 for(const field of ['email','username']) {
  const legacy=await context.request.post(origin+'/admin/login',{form:{csrf,[field]:'operator',password:'legacy-password'}});
  assert.match(await legacy.text(),/That email and password don&#039;t match\./);
 }
 assert.equal(await signedIn(),false);
 await signIn('ada@example.test','wrong password 1');
 assert.equal(await page.locator('[role=alert]').innerText(),"That email and password don't match.");
 assert.equal(await page.getByLabel('Email').inputValue(),'ada@example.test');
 await signIn('tom@example.test','tom password 123');
 assert.equal(await page.getByRole('heading',{name:'Bookings',exact:true}).count(),1);
 console.log('PASS password sign-in with a staff account; the old environment login no longer works');

 // Deactivating a user ends their session on the next request.
 php(['bin/users.php','deactivate','tom@example.test']);
 assert.equal(await signedIn(),false);
 php(['bin/users.php','reactivate','tom@example.test']);
 // So does changing their email.
 await signIn('tom@example.test','tom password 123'); assert.equal(await signedIn(),true);
 php(['bin/users.php','edit','tom@example.test','--email=thomas@example.test']);
 assert.equal(await signedIn(),false);
 console.log('PASS deactivation and email changes end sessions');

 await page.goto(origin+'/admin/login');
 await page.getByLabel('Email').fill('Ada@Example.test');
 await page.getByRole('button',{name:'Email me a sign-in link'}).click();
 assert.equal(await page.getByRole('heading',{name:'Check your email.'}).count(),1);
 assert.match(await page.locator('main').innerText(),/If Ada@Example\.test has an account, we've sent a sign-in link\. It works once and expires in 15 minutes\./);
 await page.getByRole('link',{name:'Back to sign in'}).click(); assert.match(page.url(),/\/admin\/login$/);
 let sent=emails();
 assert.equal(sent.length,1); assert.deepEqual(sent[0].to,['ada@example.test']);
 const link=linkIn(sent[0],'/admin/login/link');
 const [linkContext,linkPage]=await fresh();
 await linkPage.goto(link);
 assert.equal(await linkPage.getByRole('heading',{name:'Bookings',exact:true}).count(),1,'The emailed link signs in');
 assert.doesNotMatch(linkPage.url(),/token/);
 const [reuseContext,reusePage]=await fresh();
 const reused=await reusePage.goto(link);
 assert.equal(reused.status(),410);
 assert.match(await reusePage.locator('main').innerText(),/This link has expired\.[\s\S]*Links work once and only for a short time\. Request a new one below\./);
 await reusePage.getByRole('link',{name:'Back to sign in'}).click(); assert.match(reusePage.url(),/\/admin\/login$/);
 assert.equal(await signedIn(reusePage),false);
 await page.goto(origin+'/admin/login');
 await page.getByLabel('Email').fill('nobody@example.test');
 await page.getByRole('button',{name:'Email me a sign-in link'}).click();
 assert.match(await page.locator('main').innerText(),/If nobody@example\.test has an account, we've sent a sign-in link\./);
 assert.deepEqual(emails(),[],'Unknown emails get the same message and nothing is sent');
 for(let i=0;i<3;i++){await page.goto(origin+'/admin/login'); await page.getByLabel('Email').fill('ada@example.test'); await page.getByRole('button',{name:'Email me a sign-in link'}).click();}
 assert.match(await page.locator('main').innerText(),/If ada@example\.test has an account/);
 sent=emails(); assert.equal(sent.length,2,'At most 3 sign-in links per email per 15 minutes');
 await setClock(clock+900);
 await (await fresh())[1].goto(linkIn(sent[0],'/admin/login/link')).then(r=>assert.equal(r.status(),410,'Sign-in links expire after 15 minutes'));
 console.log('PASS sign-in links: non-revealing, single use, limited and expiring');

 await page.goto(origin+'/admin/login');
 await page.getByRole('link',{name:'Forgot password?'}).click();
 assert.match(await page.locator('main').innerText(),/Reset your password\.[\s\S]*Enter your email and we'll send you a link to set a new password\./);
 await page.getByLabel('Email').fill('ada@example.test');
 await page.getByRole('button',{name:'Send reset link'}).click();
 assert.match(await page.locator('main').innerText(),/If ada@example\.test has an account, we've sent a reset link\. It expires in 1 hour\./);
 sent=emails(); assert.equal(sent.length,1); assert.equal(sent[0].subject,'Reset your Visit Nyumba admin password');
 const reset=linkIn(sent[0],'/admin/reset');
 const [otherContext,otherPage]=await fresh();
 await signIn('ada@example.test','ada password 123',otherPage); assert.equal(await signedIn(otherPage),true);
 const [resetContext,resetPage]=await fresh();
 await resetPage.goto(reset);
 assert.match(await resetPage.locator('main').innerText(),/Set a new password\.[\s\S]*At least 12 characters\./);
 const save=async(password,confirm)=>{await resetPage.getByLabel('New password',{exact:true}).fill(password); await resetPage.getByLabel('Confirm new password').fill(confirm); await resetPage.getByRole('button',{name:'Save password'}).click();};
 await save('too short','too short');
 assert.equal(await resetPage.locator('[role=alert]').innerText(),'Use at least 12 characters.');
 await save('long enough pass','long enough pasz');
 assert.equal(await resetPage.locator('[role=alert]').innerText(),"The passwords don't match.");
 await save('long enough pass','long enough pass');
 assert.equal(await resetPage.getByRole('heading',{name:'Bookings',exact:true}).count(),1);
 assert.match(await resetPage.locator('[role=status]').innerText(),/Password saved\. You're signed in, and any other sessions have been signed out\./);
 assert.equal(await signedIn(otherPage),false,'Other sessions end');
 assert.equal(await signedIn(linkPage),false,'Sessions from sign-in links end too');
 assert.equal(await signedIn(resetPage),true,'The resetting browser stays signed in');
 assert.equal((await (await fresh())[1].goto(reset)).status(),410,'Reset links work once');
 await signIn('ada@example.test','long enough pass',otherPage); assert.equal(await signedIn(otherPage),true);
 await page.goto(origin+'/admin/forgot'); await page.getByLabel('Email').fill('ada@example.test'); await page.getByRole('button',{name:'Send reset link'}).click();
 const late=linkIn(emails()[0],'/admin/reset');
 await setClock(clock+3600);
 assert.equal((await (await fresh())[1].goto(late)).status(),410,'Reset links expire after 1 hour');
 console.log('PASS password reset: one hour, single use, 12 matching characters, other sessions end');

 // Every form needs the session's CSRF token, including the public sign-in forms.
 for(const path of ['/admin/login','/admin/login/link','/admin/forgot','/admin/reset']) assert.equal((await context.request.post(origin+path,{form:{email:'ada@example.test',csrf:'bad'}})).status(),403);
 const cookie=(await resetContext.cookies()).find(c=>c.name==='nyumba_admin');
 assert.equal(cookie.path,'/admin'); assert.equal(cookie.httpOnly,true); assert.equal(cookie.sameSite,'Lax');
 console.log('PASS CSRF on every sign-in form and an /admin-scoped cookie');
 for(const c of [context,linkContext,reuseContext,otherContext,resetContext]) await c.close();
} finally {await browser?.close();server.kill();await rm(storage,{recursive:true,force:true});}
