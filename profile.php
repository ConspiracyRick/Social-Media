<?php
require 'config.php';
$profile_id = (int)($_GET['id'] ?? currentUserId() ?? 1);
$user = null;
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$profile_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { echo 'User not found'; exit; }
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($user['display_name']?:$user['username'])?> - RetroSpace</title>
<link rel="stylesheet" href="styles.css">
<style><?php if ($user['custom_css']) echo $user['custom_css']; ?></style>
</head><body id="body" <?php if($user['bg_image']) echo 'style="background-image:url('.htmlspecialchars($user['bg_image']).')"'; ?>>
<div id="wrapper">
  <header id="header">
    <div class="logo">RetroSpace</div>
    <div class="nav">
      <?php if(currentUserId()): ?>
        <a href="profile.php?id=<?=currentUserId()?>">My Profile</a> |
        <a href="inbox.php">Inbox</a> |
        <a href="#" id="logoutBtn">Logout</a>
      <?php else: ?>
        <a href="index.php">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <main id="main">
    <aside id="leftcol">
      <div id="profileCard">
        <img id="profilePic" src="<?=htmlspecialchars($user['profile_pic']?:'assets/default-avatar.png')?>" alt="Profile" />
        <h3 id="displayName"><?=htmlspecialchars($user['display_name']?:$user['username'])?></h3>
        <p id="bio"><?=nl2br(htmlspecialchars($user['bio']))?></p>
        <?php if(currentUserId() && currentUserId() !== $profile_id): ?>
          <button id="friendBtn" data-to="<?=$profile_id?>">Send Friend Request</button>
          <button id="msgBtn" data-to="<?=$profile_id?>">Message</button>
        <?php endif; ?>
      </div>

      <div class="block">
        <h4>Top Friends</h4>
        <div id="topFriends" class="topfriends">Loading...</div>
      </div>

      <?php if(currentUserId() && currentUserId() === $profile_id): ?>
      <div class="block">
        <h4>Customize</h4>
        <form id="uploadForm" enctype="multipart/form-data">
          <label>Profile pic: <input type="file" name="file" accept="image/*"></label><br>
          <input type="hidden" name="type" value="profile">
          <button type="submit">Upload</button>
        </form>
        <form id="bgForm" enctype="multipart/form-data">
          <label>Background: <input type="file" name="file" accept="image/*"></label><br>
          <input type="hidden" name="type" value="bg">
          <button type="submit">Upload BG</button>
        </form>
        <h5>Custom CSS (limited)</h5>
        <textarea id="customCss" style="width:100%;height:120px;"><?=htmlspecialchars($user['custom_css'])?></textarea><br>
        <button id="saveCss">Save CSS</button>
      </div>
      <?php endif; ?>

    </aside>

    <section id="centercol">
      <div id="profileBanner">
        <h2 id="usernameBanner">@<?=htmlspecialchars($user['username'])?></h2>
      </div>

      <div id="statusComposer" class="block">
        <?php if(currentUserId()): ?>
        <textarea id="statusText" placeholder="Write something..."></textarea>
        <button id="postStatus">Post</button>
        <?php else: ?>
        <p><a href="index.php">Log in</a> to post.</p>
        <?php endif; ?>
      </div>

      <div id="feed" class="block">Loading feed...</div>
    </section>

    <aside id="rightcol">
      <div class="block">
        <h4>About</h4><p>Member since <?=htmlspecialchars($user['created_at'])?></p>
      </div>
    </aside>
  </main>

  <footer id="footer">© RetroSpace</footer>
</div>

<script>
const profileId = <?=json_encode($profile_id)?>;
const loggedIn = <?= json_encode(currentUserId() ? true : false) ?>;

async function loadProfileExtras(){
  const res = await fetch(`api.php?action=get_profile&id=${profileId}`);
  if (!res.ok) return;
  const p = await res.json();
  const tf = document.getElementById('topFriends');
  tf.innerHTML = '';
  (p.top_friends || []).forEach(f => {
    const a = document.createElement('a');
    a.href = 'profile.php?id=' + f.id;
    a.className = 'tf';
    a.innerHTML = `<img src="${f.profile_pic || 'assets/default-avatar.png'}" alt=""><span>${f.display_name||f.username}</span>`;
    tf.appendChild(a);
  });
}

async function loadFeed(){
  const res = await fetch(`api.php?action=get_feed&profile_user_id=${profileId}`);
  const feed = await res.json();
  const container = document.getElementById('feed');
  container.innerHTML = '';
  feed.forEach(s => {
    const div = document.createElement('div');
    div.className = 'status';
    div.innerHTML = `
      <div class="status-head">
        <img src="${s.profile_pic||'assets/default-avatar.png'}" />
        <div><strong>${s.display_name}</strong> <span class="meta">@${s.username} • ${s.created_at}</span></div>
      </div>
      <div class="status-body">${escapeHtml(s.content)}</div>
      <div class="comments" data-status="${s.id}">
        ${s.comments.map(c => `<div class="comment"><strong>${c.display_name}</strong> ${escapeHtml(c.comment)}</div>`).join('')}
      </div>
      <div class="comment-box">
        <input placeholder="Write a comment..." data-status="${s.id}" class="commentInput" />
        <button class="commentBtn" data-status="${s.id}">Comment</button>
      </div>
    `;
    container.appendChild(div);
  });

  document.querySelectorAll('.commentBtn').forEach(b=>{
    b.onclick = async ()=>{
      const id = b.getAttribute('data-status');
      const input = document.querySelector('.commentInput[data-status="'+id+'"]');
      const text = input.value.trim();
      if (!text) return alert('Comment empty');
      const r = await fetch('api.php?action=post_comment', {
        method:'POST', body: JSON.stringify({status_id: id, comment: text})
      });
      if (r.ok) { input.value=''; loadFeed(); }
      else { alert('Error posting'); }
    };
  });
}

document.getElementById('postStatus')?.addEventListener('click', async ()=>{
  const content = document.getElementById('statusText').value.trim();
  if (!content) return alert('Write something');
  const r = await fetch('api.php?action=post_status', {
    method: 'POST',
    body: JSON.stringify({profile_user_id: profileId, content})
  });
  if (r.ok) { document.getElementById('statusText').value=''; loadFeed(); }
  else alert('Could not post');
});

document.getElementById('friendBtn')?.addEventListener('click', async ()=>{
  const to = document.getElementById('friendBtn').getAttribute('data-to');
  const res = await fetch('api.php?action=send_friend_request', {method:'POST', body: JSON.stringify({to})});
  alert((await res.json()).ok ? 'Request sent' : 'Could not send');
});

document.getElementById('msgBtn')?.addEventListener('click', async ()=>{
  const to = document.getElementById('msgBtn').getAttribute('data-to');
  const subj = prompt('Subject:') || '';
  const body = prompt('Message body:');
  if (!body) return;
  const res = await fetch('api.php?action=send_message', {method:'POST', body: JSON.stringify({to, subject:subj, body})});
  alert((await res.json()).ok ? 'Message sent' : 'Could not send');
});

document.getElementById('uploadForm')?.addEventListener('submit', async (ev)=>{
  ev.preventDefault();
  const f = new FormData(ev.target);
  const r = await fetch('api.php?action=upload', {method:'POST', body: f});
  const j = await r.json();
  if (j.ok) location.reload(); else alert(j.error||'Upload failed');
});
document.getElementById('bgForm')?.addEventListener('submit', async (ev)=>{
  ev.preventDefault();
  const f = new FormData(ev.target);
  const r = await fetch('api.php?action=upload', {method:'POST', body: f});
  const j = await r.json();
  if (j.ok) location.reload(); else alert(j.error||'Upload failed');
});

document.getElementById('saveCss')?.addEventListener('click', async ()=>{
  const css = document.getElementById('customCss').value;
  const r = await fetch('api.php?action=save_custom_css', {method:'POST', body: JSON.stringify({css})});
  const j = await r.json();
  if (j.ok) alert('Saved'); else alert(j.error||'Could not save');
});

document.getElementById('logoutBtn')?.addEventListener('click', async (e)=>{
  e.preventDefault();
  await fetch('auth.php?action=logout'); location.href='index.php';
});

function escapeHtml(s){ return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

loadProfileExtras();
loadFeed();
</script>
</body></html>