import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {app} from './helpers/community-app.mjs';
const site=await app();
try {
 await site.page.goto(site.origin);
 assert.equal(await site.page.getByRole('heading',{name:'Schools we have helped'}).count(),1);
 assert.match(await site.page.locator('#school-projects').innerText(),/classrooms and school walls/i);
 assert.match(await site.page.locator('#school-projects').innerText(),/Project stories will be shared here/);
 assert.equal(await site.page.locator('#school-projects img').count(),0);
 console.log('PASS honest school showcase without invented projects');
 await site.page.goto(site.origin+'/school-request');
 await site.page.getByLabel('Your name',{exact:true}).fill('Amina Example');
 await site.page.getByLabel('Relationship to the school').fill('Teacher');
 await site.page.getByLabel('Email address').fill('amina@example.com');
 await site.page.getByLabel('Phone number').fill('0712345678');
 await site.page.getByLabel('School name').fill('Example School');
 await site.page.getByLabel('County',{exact:true}).fill('Kilifi');
 await site.page.getByLabel('Town or locality').fill('Kilifi');
 await site.page.getByLabel('Support requested').selectOption('both');
 await site.page.getByLabel('Tell us what your school needs').fill('Two classrooms and a perimeter wall.');
 const submission=Object.fromEntries(await site.page.locator('form').evaluate(form=>[...new FormData(form)]));
 const badCsrf=await site.context.request.post(site.origin+'/school-request',{form:{...submission,csrf:'wrong'}});assert.equal(badCsrf.status(),403);
 const invalid=await site.context.request.post(site.origin+'/school-request',{form:{...submission,email:'bad',support:'unknown'}});assert.equal(invalid.status(),422);assert.match(await invalid.text(),/Example School/);
 await site.page.getByRole('button',{name:'Submit school request'}).press('Enter');
 assert.match(await site.page.locator('main').innerText(),/Your request has been received/);
 const reference=(await site.page.locator('.reference').innerText()).trim();
 await site.context.request.post(site.origin+'/school-request',{form:submission});
 await site.page.reload(); assert.match(await site.page.locator('main').innerText(),new RegExp(reference));
 assert.match((await site.page.goto(site.origin+'/admin/school-requests')).url(),/admin\/login/);
 await site.login(); await site.page.goto(site.origin+'/admin/school-requests');
 assert.equal(await site.page.locator('tbody tr').count(),1);
 await site.page.getByRole('link',{name:reference}).click();
 assert.match(await site.page.locator('main').innerText(),/Example School/);
 assert.match(await site.page.locator('main').innerText(),/\+254712345678/);
 assert.match(await site.page.locator('main').innerText(),/Configuration required/);
 console.log('PASS school request saved, private, and available in admin with notification intent');
 const mail=spawnSync('php',['-r',`require 'src/SchoolEmailWorker.php'; $db=CommunityStore::open(); $worker=new SchoolEmailWorker($db,fn()=>1800000000); echo json_encode($worker->run(function($payload,$key){ if($payload['to']!==['schools@example.com'] || !str_contains($payload['text'],'Example School')) throw new RuntimeException('Wrong message'); return 'mock-email-id'; }));`],{encoding:'utf8',env:{...site.env,SCHOOL_EMAIL_ENABLED:'1',SCHOOL_NOTIFICATION_TO:'schools@example.com',RESEND_FROM:'visits@example.com',RESEND_API_KEY:'mock',COMMUNITY_BASE_URL:'https://nyumba.example'}});
 assert.equal(mail.status,0,mail.stderr); assert.equal(JSON.parse(mail.stdout).accepted,1);
 await site.context.request.post(site.origin+'/school-request',{form:submission});
 await site.page.reload(); assert.match(await site.page.locator('main').innerText(),/Provider accepted/);
 console.log('PASS request notification reaches configured recipient and admin state');
 await site.page.goto(site.origin+'/school-request?received=1');
 await site.page.getByRole('button',{name:'Submit another school request'}).press('Enter');
 assert.equal(await site.page.getByLabel('Your name',{exact:true}).inputValue(),'');
 console.log('PASS requester can deliberately start a separate school request');
} finally {await site.close();}

const projects=await app({SCHOOL_PROJECTS_CONFIG:process.cwd()+'/tests/fixtures/school-projects.php'});
try {
 await projects.page.goto(projects.origin);
 assert.equal(await projects.page.getByRole('heading',{name:'Example School A',exact:true}).count(),1);
 assert.equal(await projects.page.getByRole('heading',{name:'Example School B',exact:true}).count(),1);
 assert.match(await projects.page.locator('#school-project-test-classroom').innerText(),/2 classrooms/);
 assert.doesNotMatch(await projects.page.locator('#school-project-test-wall').innerText(),/Completed/);
 assert.equal(await projects.page.locator('#school-projects img').getAttribute('alt'),'Test photograph, not evidence of this fictional project');
 console.log('PASS configured classroom/wall content and optional metadata');
}finally {await projects.close();}
