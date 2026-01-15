<?php require 'config.php'; if(!currentUserId()){ header('Location: index.php'); exit; } ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inbox - RetroSpace</title><link rel="stylesheet" href="styles.css"></head><body>
<div class="center-wrap"><div class="card">
<h2>Inbox</h2>
<div id="inboxList">Loading...</div>
<button id="newMsg">Compose</button>
<button id="back">Back</button>
</div></div>
<script>
async function loadInbox(){
  const r = await fetch('api.php?action=inbox');
  const j = await r.json();
  const el = document.getElementById('inboxList');
  el.innerHTML = j.map(m => `<div class="msg"><strong>From: ${m.from_name||m.from_username}</strong> <div>${m.subject||'(no subject)'}</div><div>${m.body}</div><div class="meta">${m.created_at}</div></div>`).join('');
}
document.getElementById('newMsg').addEventListener('click', async ()=>{
  const to = prompt('Recipient user id:'); if(!to) return;
  const subject = prompt('Subject:')||'';
  const body = prompt('Body:'); if(!body) return;
  const r = await fetch('api.php?action=send_message',{method:'POST', body: JSON.stringify({to,subject,body})});
  alert((await r.json()).ok ? 'Sent' : 'Failed');
});
document.getElementById('back').addEventListener('click', ()=>location.href='profile.php?id=<?=currentUserId()?>');
loadInbox();
</script>
</body></html>