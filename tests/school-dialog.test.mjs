import assert from 'node:assert/strict';
import {app} from './helpers/community-app.mjs';
const site=await app();
try {
 const context=await site.context.browser().newContext({javaScriptEnabled:true,viewport:{width:390,height:844}});
 const page=await context.newPage();await page.goto(site.origin);
 const opener=page.getByRole('link',{name:'Request school support'});
 await opener.click();
 const dialog=page.getByRole('dialog',{name:'School support'});
 await dialog.getByLabel('Your name',{exact:true}).fill('Amina Example');
 for(const [label,value] of [['Relationship to the school','Teacher'],['Email address','amina@example.com'],['Phone number','0712345678'],['School name','Example School'],['County','Kilifi'],['Town or locality','Kilifi'],['Tell us what your school needs','Two classrooms and a perimeter wall.']]) await dialog.getByLabel(label,{exact:true}).fill(value);
 await dialog.getByLabel('Support requested').selectOption('both');
 await dialog.getByRole('button',{name:'Submit school request',exact:true}).click();
 await dialog.getByRole('heading',{name:'Your request has been received'}).waitFor();
 assert.equal(new URL(page.url()).pathname,'/');
 await dialog.getByRole('button',{name:'Submit another school request'}).click();
 await dialog.getByLabel('Your name',{exact:true}).waitFor();
 await page.keyboard.press('Escape');
 assert.equal(await dialog.isVisible(),false);
 assert.equal(await opener.evaluate(el=>el===document.activeElement),true);
 await opener.click();await dialog.getByLabel('Your name',{exact:true}).waitFor();
 await dialog.getByRole('button',{name:'Close school request form'}).click();
 assert.equal(await dialog.isVisible(),false);
 await context.close();
 console.log('PASS school modal submission, confirmation, new request, Escape, close and focus restoration');
} finally {await site.close();}
