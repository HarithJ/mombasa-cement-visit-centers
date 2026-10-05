import assert from 'node:assert/strict';
import {app} from './helpers/community-app.mjs';
const site=await app({MPESA_ENABLED:'1',MPESA_ENVIRONMENT:'production',MPESA_MERCHANT_NAME:'Example Foundation',MPESA_MERCHANT_TYPE:'paybill',MPESA_SHORTCODE:'600000',MPESA_DEDICATED:'1',MPESA_DONATION_REFERENCE:'NYUMBA',MPESA_TRUSTED_CALLBACK_IPS:'127.0.0.1',MPESA_QR_ENABLED:'1',MPESA_CONSUMER_KEY:'mock',MPESA_CONSUMER_SECRET:'mock'}, `
$mpesaTransport=static function($method,$url,$headers,$body){
 if(str_contains($url,'oauth/v1/generate'))return ['access_token'=>'fixture-token'];
 if(!str_ends_with($url,'/mpesa/qrcode/v1/generate')||$body['CPI']!=='600000'||$body['Amount']!==500||$body['TrxCode']!=='PB')throw new RuntimeException('Wrong provider request');
 return ['ResponseCode'=>'00','QRCode'=>base64_encode(file_get_contents(getcwd().'/tests/fixtures/mpesa-test-qr.png'))];
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
