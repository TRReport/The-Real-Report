const express = require('express');
const bodyParser = require('body-parser');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const app = express();
const PORT = process.env.PORT || 3000;
const CHAT_PATH = path.join(__dirname, 'chat.json');

if (!fs.existsSync(CHAT_PATH)) {
  fs.writeFileSync(CHAT_PATH, JSON.stringify({ messages: [] }, null, 2), 'utf8');
}

app.use(bodyParser.json({ limit: '1mb' }));
app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.sendStatus(200);
  next();
});

const NAV_LINKS = [
  { label: 'Home', href: 'https://the-real-report.vercel.app/' },
  { label: 'Plastic City', href: 'https://the-real-report.vercel.app/plasticcity.html' },
  { label: 'DA', href: 'https://the-real-report.vercel.app/da.html' },
  { label: 'ANC', href: 'https://the-real-report.vercel.app/anc.html' },
  { label: 'VF Plus', href: 'https://the-real-report.vercel.app/vfplus.html' },
  { label: 'Government', href: 'https://the-real-report.vercel.app/government.html' },
  { label: 'Flat Earth', href: 'https://the-real-report.vercel.app/flatearth.html' },
  { label: 'About', href: 'https://the-real-report.vercel.app/about.html' },
  { label: 'Chat', href: '/chat' }
];

function ipToNumericId(ip) {
  const clean = (ip || '').replace(/^::ffff:/, '');
  const hex = crypto.createHash('sha256').update(clean).digest('hex');
  const fragment = hex.slice(0, 8);
  const numeric = parseInt(fragment, 16);
  return numeric.toString();
}

app.get('/chat', (req, res) => {
  const clientIp =
    (req.headers['x-forwarded-for'] || '').split(',')[0].trim() ||
    req.socket.remoteAddress || req.ip || '0.0.0.0';
  const userId = ipToNumericId(clientIp);
  const navHtml = NAV_LINKS.map(
    (link) => `<a class="nav-link" href="${link.href}">${link.label}</a>`
  ).join('');
  const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Real Report — Chat</title>
<meta name="description" content="Open, public chat — no login, persistent numeric IDs.">
<style>
  :root{--bg:#0c0c0c;--panel:#151515;--text:#efefef;--muted:#bbbbbb;--accent:#00c2ff;}
  html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Open Sans','Helvetica Neue',Arial,sans-serif}
  .nav{position:sticky;top:0;z-index:10;background:var(--panel);border-bottom:1px solid #222;display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem}
  .brand{font-weight:700;letter-spacing:.02em;margin-right:auto}
  .menu-toggle{display:none;background:none;border:1px solid #333;color:var(--text);padding:.35rem .6rem;border-radius:.4rem}
  .links{display:flex;flex-wrap:wrap;gap:.35rem}
  .nav-link{color:var(--text);text-decoration:none;font-size:.95rem;padding:.35rem .6rem;border-radius:.4rem;border:1px solid #333}
  .nav-link:hover{background:#1f1f1f;border-color:#444}
  @media (max-width:760px){.menu-toggle{display:block}.links{display:none;flex-direction:column;width:100%;margin-top:.5rem}.links.open{display:flex}.nav{flex-wrap:wrap}.brand{order:1}.menu-toggle{order:2}.links{order:3}}
  .container{max-width:1000px;margin:1rem auto;padding:0 1rem}
  .panel{background:var(--panel);border:1px solid #222;border-radius:.75rem;overflow:hidden}
  .panel-header{padding:.85rem 1rem;border-bottom:1px solid #222;display:flex;align-items:center;justify-content:space-between}
  .panel-title{font-weight:700;letter-spacing:.02em}
  .user-id{color:var(--muted);font-size:.9rem}
  .messages{max-height:60vh;overflow-y:auto;padding:.5rem .75rem;scroll-behavior:smooth;background:#101010}
  .msg{padding:.65rem .8rem;margin:.5rem 0;border-radius:.6rem;border:1px solid #222;background:#121212;display:flex;flex-direction:column;gap:.25rem}
  .msg-meta{font-size:.8rem;color:var(--muted);display:flex;align-items:center;gap:.5rem}
  .msg-author{color:var(--accent);font-weight:600}
  .msg-text{font-size:1rem;line-height:1.4;word-wrap:break-word;white-space:pre-wrap}
  .composer{border-top:1px solid #222;background:#101010;padding:.75rem;display:flex;gap:.5rem;flex-wrap:wrap}
  .input{flex:1 1 300px;min-height:2.75rem;border-radius:.6rem;border:1px solid #333;padding:.55rem .7rem;font-size:1rem;background:#0c0c0c;color:var(--text)}
  .btn{min-height:2.75rem;padding:.55rem .9rem;border-radius:.6rem;border:1px solid #333;background:#161616;color:var(--text);cursor:pointer;font-weight:600}
  .btn:hover{background:#1e1e1e;border-color:#444}
</style>
</head>
<body>
<nav class="nav"><div class="brand">The Real Report</div><button class="menu-toggle" aria-label="Toggle menu" onclick="toggleMenu()">Menu</button><div class="links" id="navLinks">${navHtml}</div></nav>
<main class="container">
  <div class="panel">
    <div class="panel-header"><div class="panel-title">Open Chat</div><div class="user-id">You are: <strong>User ${userId}</strong></div></div>
    <div class="messages" id="messages"></div>
    <div class="composer">
      <input id="msgInput" class="input" type="text" maxlength="1000" placeholder="Type and send">
      <button class="btn" onclick="sendMessage()">Send</button>
    </div>
  </div>
</main>
<script>
  const navEl=document.getElementById('navLinks');
  function toggleMenu(){navEl.classList.toggle('open')}
  const messagesEl=document.getElementById('messages');
  const inputEl=document.getElementById('msgInput');
  async function fetchMessages(){
    try{
      const res=await fetch('/api/chat');
      if(!res.ok)return;
      const data=await res.json();
      renderMessages(data.messages||[]);
    }catch(e){}
  }
  function renderMessages(msgs){
    messagesEl.innerHTML='';
    msgs.forEach(m=>{
      const div=document.createElement('div');
      div.className='msg';
      div.innerHTML=\`
        <div class="msg-meta">
          <span class="msg-author">User \${m.user}</span>
          <span class="msg-time">\${new Date(m.timestamp).toLocaleString()}</span>
          <span class="msg-id">#\${m.id}</span>
        </div>
        <div class="msg-text"></div>\`;
      div.querySelector('.msg-text').textContent=m.message;
      messagesEl.appendChild(div);
    });
    messagesEl.scrollTop=messagesEl.scrollHeight;
  }
  async function sendMessage(){
    const text=inputEl.value.trim();
    if(!text)return;
    try{
      const res=await fetch('/api/chat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:text})});
      if(!res.ok)return;
      inputEl.value='';
      await fetchMessages();
    }catch(e){}
  }
  fetchMessages();
  setInterval(fetchMessages,5000);
</script>
</body>
</html>`;
  res.status(200).type('html').send(html);
});

app.get('/api/chat', (req, res) => {
  try {
    const raw = fs.readFileSync(CHAT_PATH, 'utf8');
    const data = JSON.parse(raw || '{"messages":[]}');
    data.messages.sort((a, b) => (a.id || 0) - (b.id || 0));
    res.json(data);
  } catch {
    res.status(500).json({ error: 'Failed to read chat' });
  }
});

app.post('/api/chat', (req, res) => {
  try {
    let { message } = req.body || {};
    if (typeof message !== 'string') return res.status(400).json({ error: 'Invalid message' });
    message = message.trim();
    if (!message) return res.status(400).json({ error: 'Empty message' });

    const clientIp =
      (req.headers['x-forwarded-for'] || '').split(',')[0].trim() ||
      req.socket.remoteAddress || req.ip || '0.0.0.0';
    const userId = ipToNumericId(clientIp);

    const raw = fs.readFileSync(CHAT_PATH, 'utf8');
    const data = JSON.parse(raw || '{"messages":[]}');
    const nextId = (data.messages.length ? Math.max(...data.messages.map(m => m.id || 0)) : 0) + 1;

    const entry = { id: nextId, user: userId, message, timestamp: new Date().toISOString() };
    data.messages.push(entry);
    fs.writeFileSync(CHAT_PATH, JSON.stringify(data, null, 2), 'utf8');

    res.json({ ok: true, entry });
  } catch {
    res.status(500).json({ error: 'Failed to save message' });
  }
});

app.get('/', (req, res) => res.redirect('/chat'));

app.listen(PORT);
```[43dcd9a7-70db-4a1f-b0ae-981daa162054](https://github.com/nikolamarkovic98/codecounter/tree/37abe47b34564ca9bc0e482aff7a0f0a0321f75d/server.js?citationMarker=43dcd9a7-70db-4a1f-b0ae-981daa162054&citationId=1&citationId=2&citationId=3&citationId=4&citationId=5&citationId=6 "github.com")