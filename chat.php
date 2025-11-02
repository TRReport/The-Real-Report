<?php
// Single-file PHP chat: saves to chat.json in the same folder, no login, numeric IDs from IP hash.
// Endpoints:
// - GET /chat.php?action=list       -> returns JSON {messages: [...]}
// - POST /chat.php?action=post      -> body: message=... -> appends to chat.json
// - GET /chat.php                   -> serves HTML chat UI (mobile + desktop)

declare(strict_types=1);

// Path to storage JSON next to this file
$CHAT_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'chat.json';

// Ensure chat.json exists
if (!file_exists($CHAT_PATH)) {
    file_put_contents($CHAT_PATH, json_encode(['messages' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Get client IP (best effort)
function client_ip(): string {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

// Numeric ID from IP hash (no dots shown)
function ip_to_numeric_id(string $ip): string {
    $hex = hash('sha256', $ip);
    $fragment = substr($hex, 0, 8);
    $num = hexdec($fragment); // up to ~4.29e9
    return (string)$num;
}

// Read messages
function read_messages(string $path): array {
    $fp = fopen($path, 'r');
    if (!$fp) return ['messages' => []];
    $data = stream_get_contents($fp);
    fclose($fp);
    $json = json_decode($data, true);
    if (!$json || !isset($json['messages']) || !is_array($json['messages'])) {
        return ['messages' => []];
    }
    return $json;
}

// Write messages (with lock)
function write_messages(string $path, array $data): bool {
    $fp = fopen($path, 'c+');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    ftruncate($fp, 0);
    $ok = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

// API: list messages
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $data = read_messages($CHAT_PATH);
    usort($data['messages'], function ($a, $b) {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// API: post message
if (isset($_GET['action']) && $_GET['action'] === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
    if ($message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Empty message']);
        exit;
    }

    $ip = client_ip();
    $userId = ip_to_numeric_id($ip);

    $data = read_messages($CHAT_PATH);
    $nextId = (count($data['messages']) > 0)
        ? (max(array_map(fn($m) => $m['id'] ?? 0, $data['messages'])) + 1)
        : 1;

    $entry = [
        'id' => $nextId,
        'user' => $userId,
        'message' => $message,
        'timestamp' => gmdate('c')
    ];

    $data['messages'][] = $entry;
    if (!write_messages($CHAT_PATH, $data)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save']);
        exit;
    }

    echo json_encode(['ok' => true, 'entry' => $entry], JSON_UNESCAPED_UNICODE);
    exit;
}

// HTML UI
$userId = ip_to_numeric_id(client_ip());
$navLinks = [
    ['Home', 'https://the-real-report.vercel.app/'],
    ['Plastic City', 'https://the-real-report.vercel.app/plasticcity.html'],
    ['DA', 'https://the-real-report.vercel.app/da.html'],
    ['ANC', 'https://the-real-report.vercel.app/anc.html'],
    ['VF Plus', 'https://the-real-report.vercel.app/vfplus.html'],
    ['Government', 'https://the-real-report.vercel.app/government.html'],
    ['Flat Earth', 'https://the-real-report.vercel.app/flatearth.html'],
    ['About', 'https://the-real-report.vercel.app/about.html'],
    ['Chat', 'chat.php']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>The Real Report — Chat</title>
<meta name="description" content="Open, public chat for The Real Report — no login, numeric IDs, JSON storage.">
<style>
  :root { --bg:#0c0c0c; --panel:#151515; --text:#efefef; --muted:#bbbbbb; --accent:#00c2ff; --border:#222; }
  html, body { margin:0; padding:0; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Open Sans','Helvetica Neue',Arial,sans-serif; }
  .nav { position:sticky; top:0; z-index:10; background:var(--panel); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.5rem; padding:.5rem 1rem; }
  .brand { font-weight:700; letter-spacing:.02em; margin-right:auto; }
  .menu-toggle { display:none; background:none; border:1px solid #333; color:var(--text); padding:.35rem .6rem; border-radius:.4rem; }
  .links { display:flex; flex-wrap:wrap; gap:.35rem; }
  .nav-link { color:var(--text); text-decoration:none; font-size:.95rem; padding:.35rem .6rem; border-radius:.4rem; border:1px solid #333; }
  .nav-link:hover { background:#1f1f1f; border-color:#444; }
  @media (max-width:760px) {
    .menu-toggle { display:block; }
    .links { display:none; flex-direction:column; width:100%; margin-top:.5rem; }
    .links.open { display:flex; }
    .nav { flex-wrap:wrap; }
    .brand { order:1; }
    .menu-toggle { order:2; }
    .links { order:3; }
  }
  .container { max-width:1000px; margin:1rem auto; padding:0 1rem; }
  .panel { background:var(--panel); border:1px solid var(--border); border-radius:.75rem; overflow:hidden; }
  .panel-header { padding:.85rem 1rem; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .panel-title { font-weight:700; letter-spacing:.02em; }
  .user-id { color:var(--muted); font-size:.9rem; }
  .messages { max-height:60vh; overflow-y:auto; padding:.5rem .75rem; scroll-behavior:smooth; background:#101010; }
  .msg { padding:.65rem .8rem; margin:.5rem 0; border-radius:.6rem; border:1px solid var(--border); background:#121212; display:flex; flex-direction:column; gap:.25rem; }
  .msg-meta { font-size:.8rem; color:var(--muted); display:flex; align-items:center; gap:.5rem; }
  .msg-author { color:var(--accent); font-weight:600; }
  .msg-text { font-size:1rem; line-height:1.4; word-wrap:break-word; white-space:pre-wrap; }
  .composer { border-top:1px solid var(--border); background:#101010; padding:.75rem; display:flex; gap:.5rem; flex-wrap:wrap; }
  .input { flex:1 1 300px; min-height:2.75rem; border-radius:.6rem; border:1px solid #333; padding:.55rem .7rem; font-size:1rem; background:#0c0c0c; color:var(--text); }
  .btn { min-height:2.75rem; padding:.55rem .9rem; border-radius:.6rem; border:1px solid #333; background:#161616; color:var(--text); cursor:pointer; font-weight:600; }
  .btn:hover { background:#1e1e1e; border-color:#444; }
</style>
</head>
<body>
<nav class="nav">
  <div class="brand">The Real Report</div>
  <button class="menu-toggle" aria-label="Toggle menu" onclick="toggleMenu()">Menu</button>
  <div class="links" id="navLinks">
    <?php foreach ($navLinks as [$label, $href]): ?>
      <a class="nav-link" href="<?php echo htmlspecialchars($href); ?>"><?php echo htmlspecialchars($label); ?></a>
    <?php endforeach; ?>
  </div>
</nav>

<main class="container">
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">Open Chat</div>
      <div class="user-id">You are: <strong>User <?php echo htmlspecialchars($userId); ?></strong></div>
    </div>
    <div class="messages" id="messages"></div>
    <div class="composer">
      <input id="msgInput" class="input" type="text" maxlength="1000" placeholder="Type and send">
      <button class="btn" onclick="sendMessage()">Send</button>
    </div>
  </div>
</main>

<script>
  const navEl = document.getElementById('navLinks');
  function toggleMenu(){ navEl.classList.toggle('open'); }

  const messagesEl = document.getElementById('messages');
  const inputEl = document.getElementById('msgInput');

  async function fetchMessages() {
    try {
      const res = await fetch('chat.php?action=list');
      if (!res.ok) return;
      const data = await res.json();
      renderMessages(data.messages || []);
    } catch (e) {}
  }

  function renderMessages(msgs) {
    messagesEl.innerHTML = '';
    msgs.forEach(m => {
      const div = document.createElement('div');
      div.className = 'msg';
      div.innerHTML = `
        <div class="msg-meta">
          <span class="msg-author">User ${m.user}</span>
          <span class="msg-time">${new Date(m.timestamp).toLocaleString()}</span>
          <span class="msg-id">#${m.id}</span>
        </div>
        <div class="msg-text"></div>`;
      div.querySelector('.msg-text').textContent = m.message;
      messagesEl.appendChild(div);
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  async function sendMessage() {
    const text = inputEl.value.trim();
    if (!text) return;
    const form = new FormData();
    form.append('message', text);
    try {
      const res = await fetch('chat.php?action=post', { method: 'POST', body: form });
      if (!res.ok) { alert('Failed to send'); return; }
      inputEl.value = '';
      await fetchMessages();
    } catch (e) {}
  }

  fetchMessages();
  setInterval(fetchMessages, 5000);
</script>
</body>
</html>
```[43dcd9a7-70db-4a1f-b0ae-981daa162054](https://github.com/csrrmzn/depo-yonetimi/tree/caa26a1a9c96d68915865667b0d2e0417056ddc7/transaction%2FNewPassword.php?citationMarker=43dcd9a7-70db-4a1f-b0ae-981daa162054&citationId=1 "github.com")
