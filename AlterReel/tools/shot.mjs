import {chromium} from 'playwright-core';
const b = await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox','--hide-scrollbars']});
const p = await (await b.newContext({viewport:{width:1440,height:936}})).newPage();
await p.goto('https://alter-that.com/',{waitUntil:'load'}); await p.waitForTimeout(5000);
await p.screenshot({path:'/tmp/claude-1000/-opt-lampp-htdocs-cantum/401c0203-4ade-4e49-87be-34e5409675e3/scratchpad/a1.png'});
console.log(await p.evaluate(()=>document.body.scrollHeight));
await p.mouse.wheel(0,1200); await p.waitForTimeout(2000);
await p.screenshot({path:'/tmp/claude-1000/-opt-lampp-htdocs-cantum/401c0203-4ade-4e49-87be-34e5409675e3/scratchpad/a2.png'});
await b.close();
