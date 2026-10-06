import assert from 'node:assert/strict';
import {PNG} from 'pngjs';
import jsQR from 'jsqr';
import {app} from './helpers/community-app.mjs';
const demo=await app();
try {
 const context=await demo.context.browser().newContext({javaScriptEnabled:true,viewport:{width:390,height:844}});
 const page=await context.newPage();await page.goto(demo.origin);
 await page.locator('[data-demo-amount="1000"]').click();
 assert.equal(await page.getByLabel('Donation amount (KES)').inputValue(),'1000');
 await page.getByRole('button',{name:'Donate'}).click();
 assert.match(await page.locator('[data-demo-status]').innerText(),/KES 1,000 selected/);
 assert.equal(await page.getByAltText('Payment preview QR code').count(),1);
 await page.getByLabel('Donation amount (KES)').fill('-1');
 await page.getByRole('button',{name:'Donate'}).click();
 assert.doesNotMatch(await page.locator('[data-demo-status]').innerText(),/selected/);
 await demo.login();await demo.page.goto(demo.origin+'/admin/donations/attempts');
 assert.equal(await demo.page.locator('tbody a').count(),0);
 await context.close();
 console.log('PASS unconfigured M-Pesa demo previews amounts without recording payment attempts');
} finally {await demo.close();}
const site=await app({MPESA_ENABLED:'1',MPESA_ENVIRONMENT:'production',MPESA_MERCHANT_NAME:'Example Foundation',MPESA_MERCHANT_TYPE:'paybill',MPESA_SHORTCODE:'600000',MPESA_DEDICATED:'1',MPESA_DONATION_REFERENCE:'NYUMBA',MPESA_TRUSTED_CALLBACK_IPS:'127.0.0.1',MPESA_QR_ENABLED:'1',MPESA_CONSUMER_KEY:'mock',MPESA_CONSUMER_SECRET:'mock',COMMUNITY_TEST_NODE:process.execPath}, `
$mpesaTransport=static function($method,$url,$headers,$body){
 if(str_contains($url,'oauth/v1/generate'))return ['access_token'=>'fixture-token'];
 if(!str_ends_with($url,'/mpesa/qrcode/v1/generate')||$body['CPI']!=='600000'||$body['Amount']!==500||$body['TrxCode']!=='PB')throw new RuntimeException('Wrong provider request');
 if(!preg_match('/^NY[A-F0-9]{10}$/D',$body['RefNo']))throw new RuntimeException('Missing saved reference');
 $process=proc_open([getenv('COMMUNITY_TEST_NODE'),getcwd().'/tests/helpers/qr-provider.mjs',base64_encode(json_encode($body))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
 $png=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('QR fixture failed');
 return ['ResponseCode'=>'00','QRCode'=>$png];
};`);
const payment={TransactionType:'Pay Bill',TransID:'TESTRECEIPT01',TransTime:'20261005103000',TransAmount:'250.00',BusinessShortCode:'600000',BillRefNumber:'NYUMBA',MSISDN:'hashed-phone',FirstName:'Example'};
try {
 await site.page.goto(site.origin);assert.match(await site.page.locator('#donations').innerText(),/already enough/);
 const wrong=await site.context.request.post(site.origin+'/mpesa/confirmation',{data:{...payment,BusinessShortCode:'111111'}});assert.equal(wrong.status(),400);
 assert.equal((await site.context.request.get(site.origin+'/mpesa/confirmation')).status(),405);
 const receipt=await site.context.request.post(site.origin+'/mpesa/confirmation',{data:payment});assert.equal(receipt.status(),200);
 assert.equal((await site.context.request.post(site.origin+'/mpesa/confirmation',{data:payment})).status(),200);
 await site.login();await site.page.goto(site.origin+'/admin/donations');
 assert.match(await site.page.locator('.donation-total').innerText(),/KES 250.00/);
 assert.equal(await site.page.getByRole('link',{name:'TESTRECEIPT01',exact:true}).count(),1);
 await site.page.getByRole('link',{name:'TESTRECEIPT01',exact:true}).click();
 assert.match(await site.page.locator('main').innerText(),/Verified donation/);
 console.log('PASS direct M-Pesa receipt recorded once with verified admin total');
 await site.page.goto(site.origin+'/donate');
 await site.page.getByLabel('Donation amount (KES)').fill('500');
 await site.page.getByRole('button',{name:'Create payment QR'}).press('Enter');
 assert.equal(await site.page.getByAltText('M-Pesa payment QR').count(),1);
 assert.match(await site.page.locator('main').innerText(),/Awaiting confirmation/);
 const reference=await site.page.locator('.reference').innerText();
 const png=PNG.sync.read(Buffer.from((await site.page.getByAltText('M-Pesa payment QR').getAttribute('src')).split(',')[1],'base64'));
 const decoded=jsQR(new Uint8ClampedArray(png.data),png.width,png.height);assert.ok(decoded,'Displayed QR must decode');
 const fields=JSON.parse(decoded.data);
 assert.equal(fields.testOnly,true);assert.equal(fields.MerchantName,'Example Foundation');assert.equal(fields.CPI,'600000');assert.equal(fields.Amount,500);assert.equal(fields.TrxCode,'PB');assert.equal(fields.RefNo,reference);
 console.log('PASS displayed mock QR decodes to the expected recipient, amount and saved reference');
 await site.context.request.post(site.origin+'/mpesa/confirmation',{data:{...payment,TransID:'TESTRECEIPT02',TransAmount:'500',BillRefNumber:reference}});
 await site.page.reload();assert.match(await site.page.locator('main').innerText(),/Donation received/);
 const stranger=await site.context.browser().newContext();const other=await stranger.newPage();await other.goto(site.page.url());assert.doesNotMatch(await other.locator('main').innerText(),/Donation received/);await stranger.close();
 await site.page.goto(site.origin+'/admin/donations');assert.match(await site.page.locator('.donation-total').innerText(),/KES 750.00/);
 await site.page.goto(site.origin+'/admin/donations/attempts');
 await site.page.getByRole('link',{name:reference,exact:true}).click();
 assert.match(await site.page.locator('main').innerText(),/Donation received/);
 assert.match(await site.page.locator('main').innerText(),/TESTRECEIPT02/);
 console.log('PASS QR amount selection, private status, callback matching and admin attempt');
 await site.context.request.post(site.origin+'/mpesa/confirmation',{data:{...payment,TransAmount:'999.00'}});
 await site.page.goto(site.origin+'/admin/donations');assert.match(await site.page.locator('.donation-total').innerText(),/KES 500.00/);
 await site.page.getByRole('link',{name:'TESTRECEIPT01',exact:true}).click();assert.match(await site.page.locator('main').innerText(),/Review required/);assert.match(await site.page.locator('main').innerText(),/KES 250.00/);
 console.log('PASS conflicting callback preserves original facts and is excluded from totals');
} finally {await site.close();}

const untrusted=await app({MPESA_ENABLED:'1',MPESA_ENVIRONMENT:'production',MPESA_MERCHANT_NAME:'Example',MPESA_MERCHANT_TYPE:'paybill',MPESA_SHORTCODE:'600000',MPESA_DEDICATED:'0',MPESA_DONATION_REFERENCE:'NYUMBA',MPESA_TRUSTED_CALLBACK_IPS:''});
try {
 await untrusted.context.request.post(untrusted.origin+'/mpesa/confirmation',{data:payment,headers:{'X-Forwarded-For':'127.0.0.1','X-Nyumba-Mpesa-Verified':'forged'}});
 await untrusted.login();await untrusted.page.goto(untrusted.origin+'/admin/donations');assert.match(await untrusted.page.locator('.donation-total').innerText(),/KES 0.00/);
 await untrusted.page.getByRole('link',{name:'TESTRECEIPT01',exact:true}).click();assert.match(await untrusted.page.locator('main').innerText(),/Unverified receipt/);
 console.log('PASS untrusted callback and forged headers cannot claim funds');
}finally {await untrusted.close();}
const failedQR=await app({MPESA_ENABLED:'1',MPESA_ENVIRONMENT:'sandbox',MPESA_MERCHANT_NAME:'Example',MPESA_MERCHANT_TYPE:'paybill',MPESA_SHORTCODE:'600000',MPESA_DEDICATED:'1',MPESA_DONATION_REFERENCE:'NYUMBA',MPESA_QR_ENABLED:'1',MPESA_CONSUMER_KEY:'mock',MPESA_CONSUMER_SECRET:'mock'}, `$mpesaTransport=static function(){throw new RuntimeException('Provider outage');};`);
try{
 await failedQR.page.goto(failedQR.origin+'/donate');await failedQR.page.getByLabel('Donation amount (KES)').fill('500');await failedQR.page.getByRole('button',{name:'Create payment QR'}).press('Enter');
 const ref=await failedQR.page.locator('.reference').innerText();assert.match(await failedQR.page.locator('main').innerText(),/could not prepare your QR/);
 await failedQR.page.getByRole('button',{name:'Retry QR generation'}).press('Enter');assert.equal(await failedQR.page.locator('.reference').innerText(),ref);
 await failedQR.login();await failedQR.page.goto(failedQR.origin+'/admin/donations/attempts');assert.equal(await failedQR.page.locator('tbody tr').count(),1);assert.match(await failedQR.page.locator('tbody').innerText(),/QR generation failed/);
 assert.equal((await failedQR.page.goto(failedQR.origin+'/admin/donations?state=madeup')).status(),400);
 assert.equal((await failedQR.page.goto(failedQR.origin+'/admin/donations?from=2026-02-31')).status(),400);
 console.log('PASS QR outage/retry retains one attempt and admin rejects invalid filters');
}finally{await failedQR.close();}
const shared=await app({MPESA_ENABLED:'1',MPESA_ENVIRONMENT:'production',MPESA_MERCHANT_NAME:'Example',MPESA_MERCHANT_TYPE:'paybill',MPESA_SHORTCODE:'600000',MPESA_DEDICATED:'0',MPESA_DONATION_REFERENCE:'NYUMBA',MPESA_TRUSTED_CALLBACK_IPS:'127.0.0.1'});
try{
 await shared.context.request.post(shared.origin+'/mpesa/confirmation',{data:{...payment,BillRefNumber:'INVOICE-42'}});
 await shared.login();await shared.page.goto(shared.origin+'/admin/donations');assert.match(await shared.page.locator('.donation-total').innerText(),/KES 0.00/);assert.match(await shared.page.locator('tbody').innerText(),/Unmatched payment/);
 await shared.context.request.post(shared.origin+'/mpesa/confirmation',{data:{...payment,TransID:'TESTRECEIPT03'}});
 await shared.page.goto(shared.origin+'/admin/donations?from=2026-10-05&to=2026-10-05');assert.match(await shared.page.locator('.donation-total').innerText(),/KES 250.00/);
 await shared.page.goto(shared.origin+'/admin/donations?from=2026-10-06');assert.match(await shared.page.locator('.donation-total').innerText(),/KES 0.00/);
 console.log('PASS shared merchant attribution and date-filtered production totals');
}finally{await shared.close();}
