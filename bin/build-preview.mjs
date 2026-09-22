import {execFileSync} from 'node:child_process';
import {cp, mkdir, readFile, readdir, rm, writeFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {join} from 'node:path';

const root=fileURLToPath(new URL('../',import.meta.url));
const output=join(root,'_site');
await rm(output,{recursive:true,force:true});
await mkdir(output,{recursive:true});
// An explicit asset allowlist excludes databases, sessions, PHP and repository files.
for(const name of ['style.css','manrope.ttf','manrope-OFL.txt','nyumba-group.svg','nyumba-foundation.svg','nyumba-agri.svg','galana-placeholder.svg','photos','versions']){
  await cp(join(root,'public/assets',name),join(output,'assets',name),{recursive:true});
}
for(const version of ['v1','v2','v3']){
  let html=execFileSync('php',[join(root,'bin/render-preview.php'),version],{encoding:'utf8'});
  // Relative paths work under both username.github.io/repository/ and custom domains.
  html=html.replaceAll('/assets/','../assets/').replaceAll('\\/assets\\/','..\\/assets\\/');
  html=html.replace(/<input type="hidden" name="(?:csrf|submissionToken)"[^>]*>/g,'');
  html=html.replace('method="post" action="./"','method="dialog"');
  html=html.replace('class="button submit-button" type="submit"','class="button submit-button" type="submit" disabled');
  html=html.replaceAll('Book my visit','Preview my visit');
  html=html.replace('Your name, phone number and visit details are stored to record your visit and may be emailed to the destination team to coordinate your visit.','Design preview only. Use sample details. Nothing entered here is submitted or saved.');
  html=html.replace('<strong>Visit bookings</strong> · Your visit is registered after submission.','<strong>Design preview</strong> · This form does not make a booking.');
  html=html.replace('Visit registered.</h2>','Your visit preview.</h2>');
  html=html.replace('Your visit has been recorded. Please keep your booking reference.','Sample summary only. No booking has been made.');
  html=html.replace('Registration acknowledges your visit; it does not imply a capacity check.','This is an interactive design preview. These details have not been saved.');
  html=html.replace(/<div class="reference-label">[\s\S]*?<\/div>/,'');
  html=html.replace(/<noscript><div class="noscript-notice">[\s\S]*?<\/div><\/noscript>/,'<noscript><div id="preview-unavailable" class="noscript-notice">Design preview only. Enable JavaScript to try the form, galleries and inline maps. No bookings can be made on this site.</div></noscript>');
  html=html.replace(/href="\.\/\?book=[^"]+"/g,'href="#preview-unavailable"');
  const nav=['v1','v2','v3'].map(v=>`<a href="../${v}/"${v===version?' aria-current="page"':''}>${v.toUpperCase()}</a>`).join('');
  html=html.replace('<body>',`<body><aside class="preview-bar" aria-label="Design preview"><a href="../">Design previews</a><span>No bookings are saved</span><nav aria-label="Preview versions">${nav}</nav></aside>`);
  html=html.replace('</head>','<link rel="stylesheet" href="../assets/preview.css">\n</head>');
  let js=await readFile(join(root,`public/assets/versions/${version}/app.js`),'utf8');
  const old=`form.addEventListener('submit', event => {\n  if (busy || !validate()) { event.preventDefault(); return; }\n  busy = true; submit.disabled = true;\n  document.querySelector('#submit-label').textContent = 'Saving your visit…';\n});`;
  if(!js.includes(old))throw Error(`Submission handler changed in ${version}; review preview conversion.`);
  js=js.replace(old,`form.addEventListener('submit', event => {\n  event.preventDefault();\n  if (!validate()) return;\n  showConfirmation(Object.fromEntries(new FormData(form)));\n});`);
  js=js.replaceAll('Book my visit','Preview my visit').replaceAll('Saving your visit…','Preparing preview…');
  js+=`\n// Enabled only after the local-only preview handler is attached.\nsubmit.disabled = false;\n`;
  await mkdir(join(output,version),{recursive:true});
  await writeFile(join(output,version,'index.html'),html);
  await writeFile(join(output,`assets/versions/${version}/app.js`),js);
}
await writeFile(join(output,'assets/preview.css'),`
.preview-bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:9px max(20px,calc((100% - 1320px)/2));background:#173f35;color:#fffbed;font-family:Manrope,Arial,sans-serif;font-size:11px;line-height:1.5}
.preview-bar>a{font-weight:750}.preview-bar nav{display:flex;gap:6px}.preview-bar nav a{display:grid;place-items:center;min-width:44px;min-height:32px;border:1px solid #ffffff55;border-radius:5px}.preview-bar a[aria-current=page]{background:#fffbed;color:#173f35}.preview-bar a:focus-visible{outline:2px solid #fffbed;outline-offset:3px}
@media(max-width:600px){.preview-bar{flex-wrap:wrap;gap:5px 12px;padding:8px 20px}.preview-bar>span{font-size:10px;order:3;width:100%}.preview-bar nav a{min-height:36px}.travel-hero{height:calc(100svh - 165px)}.explorer-hero{height:calc(100svh - 163px)}}
`);
await writeFile(join(output,'index.html'),`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Visit Nyumba — Design previews</title><link rel="icon" href="assets/nyumba-group.svg"><style>@font-face{font-family:Manrope;src:url('assets/manrope.ttf')}*{box-sizing:border-box}body{margin:0;background:#f4f4ec;color:#173f35;font-family:Manrope,Arial,sans-serif}main{max-width:850px;margin:auto;padding:60px 24px}img{height:82px;width:60px;object-fit:contain}h1{font-size:clamp(36px,6vw,58px);line-height:1.1;letter-spacing:-2px;margin:28px 0 16px}p{line-height:1.8;color:#52685c}nav{margin-top:40px}nav a{display:flex;justify-content:space-between;gap:20px;padding:25px 0;border-top:1px solid #bdc9bc;color:inherit;text-decoration:none}nav a:last-child{border-bottom:1px solid #bdc9bc}strong{font-size:21px}small{display:block;line-height:1.8;margin-top:5px}a:hover{color:#387750}a:focus-visible{outline:3px solid #387750;outline-offset:5px}.note{font-size:12px;margin-top:28px}</style></head><body><main><img src="assets/nyumba-group.svg" alt="Nyumba Group"><h1>Three ways to<br>Visit Nyumba.</h1><p>Explore the designs, browse the photographs and try the visit-planning forms.</p><nav aria-label="Choose a design"><a href="v1/"><span><strong>Version 1</strong><small>The original destination collection</small></span><span aria-hidden="true">↗</span></a><a href="v2/"><span><strong>Version 2</strong><small>A warm travel journal</small></span><span aria-hidden="true">↗</span></a><a href="v3/"><span><strong>Version 3</strong><small>A bold outdoor explorer</small></span><span aria-hidden="true">↗</span></a></nav><p class="note">Design previews only. No bookings are made and no form details are saved. Use sample details when exploring the forms.</p></main></body></html>`);
await writeFile(join(output,'.nojekyll'),'');
// Guard the deployed artifact, not just the repository ignore patterns.
async function audit(dir){for(const e of await readdir(dir,{withFileTypes:true})){const path=join(dir,e.name);if(e.isDirectory())await audit(path);else if(/\.(php|sqlite|db|toml|env)$/i.test(e.name))throw Error(`Unsafe preview artifact: ${path}`);}}
await audit(output);
console.log('Built static previews in _site/ (v1, v2, v3). No backend files exported.');
