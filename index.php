<?php require 'config.php'; ?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RetroSpace - Login</title>
<link rel="stylesheet" href="styles.css">
</head><body>
<div class="center-wrap">
  <div class="card">
    <h1>RetroSpace</h1>
    <?php if(currentUserId()): ?>
      <p>You're logged in as <?=htmlspecialchars(currentUser()['display_name'])?></p>
      <p><a href="profile.php?id=<?=currentUserId()?>">Go to profile</a> | <a href="#" id="logout">Logout</a></p>
    <?php else: ?>
      <div id="forms">
        <div>
          <h3>Login</h3>
          <input id="login_user" placeholder="username or email"><br>
          <input id="login_pass" type="password" placeholder="password"><br>
          <button id="btnLogin">Login</button>
        </div>
        <hr>
        <div>
          <h3>Register</h3>
          <input id="reg_user" placeholder="username"><br>
          <input id="reg_email" placeholder="email"><br>
          <input id="reg_pass" type="password" placeholder="password"><br>
          <button id="btnRegister">Register</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
async function postJSON(url, data){
  const res = await fetch(url, {method:'POST', body: JSON.stringify(data)});
  const j = await res.json().catch(()=>null);
  return {ok:res.ok, json:j};
}
document.getElementById('btnLogin')?.addEventListener('click', async ()=>{
  const u = document.getElementById('login_user').value;
  const p = document.getElementById('login_pass').value;
  const r = await postJSON('auth.php?action=login', {username:u, password:p});
  if (r.ok) location.href = 'profile.php?id=' + r.json.user_id;
  else alert(r.json?.error || 'Login failed');
});
document.getElementById('btnRegister')?.addEventListener('click', async ()=>{
  const u = document.getElementById('reg_user').value;
  const e = document.getElementById('reg_email').value;
  const p = document.getElementById('reg_pass').value;
  const r = await postJSON('auth.php?action=register', {username:u, email:e, password:p});
  if (r.ok) location.href = 'profile.php?id=' + r.json.user_id;
  else alert(r.json?.error || 'Register failed');
});
document.getElementById('logout')?.addEventListener('click', async (ev)=>{
  ev.preventDefault();
  await fetch('auth.php?action=logout'); location.reload();
});
</script>
</body></html>