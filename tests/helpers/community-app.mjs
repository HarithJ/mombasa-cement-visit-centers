import {mkdtemp, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
export async function app(environment = {}, routerSource = '') {
 const storage = await mkdtemp(join(tmpdir(), 'nyumba-community-'));
 const listener = createServer(); await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
 const port = listener.address().port; await new Promise(resolve => listener.close(resolve));
 const origin = `http://127.0.0.1:${port}`;
 const hash = spawnSync('php', ['-r', "echo password_hash('test-password', PASSWORD_DEFAULT);"], {encoding:'utf8'}).stdout;
 const env = {...process.env, BOOKING_DB:join(storage,'bookings.sqlite'), BOOKING_SESSION_PATH:storage, BOOKING_EMAIL_ENABLED:'0', FEEDBACK_EMAIL_ENABLED:'0', BOOKING_SMS_ENABLED:'0', SCHOOL_EMAIL_ENABLED:'0', MPESA_ENABLED:'0', ADMIN_USERNAME:'operator', ADMIN_PASSWORD_HASH:hash, ...environment};
 const router = join(storage,'router.php');
 await writeFile(router, `<?php if (is_file(getcwd().'/public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) return false; ${routerSource}\nrequire ${JSON.stringify(process.cwd()+'/public/index.php')};`);
 const server = spawn('php', ['-S',`127.0.0.1:${port}`,'-t','public',router], {env,stdio:['ignore','ignore','pipe']});
 let logs=''; server.stderr.on('data',chunk=>logs+=chunk);
 let browser;
 try {
  for(let i=0;i<60;i++) {try {await fetch(origin);break;} catch {await new Promise(r=>setTimeout(r,100));}}
  browser=await chromium.launch({executablePath:process.env.UI_BROWSER_PATH});
 } catch(error) {server.kill(); await rm(storage,{recursive:true,force:true}); throw error;}
 const context=await browser.newContext({javaScriptEnabled:false, reducedMotion:'reduce', viewport:{width:390,height:844}});
 const page=await context.newPage(); page.setDefaultTimeout(10000);
 return {origin,storage,env,page,context,logs:()=>logs,
  async login() {await page.goto(origin+'/admin/login'); await page.getByLabel('Username').fill('operator'); await page.getByLabel('Password',{exact:true}).fill('test-password'); await page.getByRole('button',{name:'Sign in'}).click();},
  async close() {await browser.close(); server.kill(); await new Promise(resolve=>server.once('exit',resolve)); await rm(storage,{recursive:true,force:true});}
 };
}
