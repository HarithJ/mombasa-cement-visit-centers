import {spawnSync} from 'node:child_process';
import {existsSync} from 'node:fs';
const checks = [
 [process.execPath,'tests/admin.test.mjs'],
 ['php','tests/email.test.php'],
 ['php','tests/transport.test.php'],
 [process.execPath,'tests/transport.test.mjs'],
 ['php','tests/sms.test.php'],
 ['php','tests/feedback-worker.test.php'],
 ['php','tests/feedback-migration.test.php'],
 [process.execPath,'tests/bookings.test.mjs'],
 [process.execPath,'tests/overnight.test.mjs'],
 [process.execPath,'tests/contacts.test.mjs'],
 [process.execPath,'tests/versions.test.mjs'],
 [process.execPath,'tests/feedback.test.mjs'],
 [process.execPath,'tests/preview.test.mjs'],
];
let failures=0;
for(const [command,file] of checks) {
 if(!existsSync(file)) continue;
 console.log(`\nRunning ${file}`);
 const result=spawnSync(command,[file],{stdio:'inherit',env:process.env});
 if(result.status!==0) failures++;
}
if(failures) {console.error(`${failures} test suite(s) failed.`);process.exit(1);}
console.log('All available test suites passed.');
