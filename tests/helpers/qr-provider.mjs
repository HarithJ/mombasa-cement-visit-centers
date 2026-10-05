// Test-only provider boundary. This encodes Daraja request fields, not a live M-Pesa payload.
import QRCode from 'qrcode';
const request=JSON.parse(Buffer.from(process.argv[2],'base64').toString('utf8'));
const png=await QRCode.toBuffer(JSON.stringify({testOnly:true,...request}),{type:'png',width:300,margin:4});
process.stdout.write(png.toString('base64'));
