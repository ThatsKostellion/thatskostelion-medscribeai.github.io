<?php
// ─── Main router ──────────────────────────────────────────────────────────────
session_start();
require_once 'db.php';

$db = getDB();
$user = null;
$voom = null;

if (!empty($_SESSION['user_id'])) {
$user = getUserById($db, (int)$_SESSION['user_id']);
if ($user) {
updateLastSeen($db, $user['id']);
$room = getRoomById($db, $user['room_id']);
}
}

// Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if (!$user) {
// Unauthenticated: only allow login or signup
if (!in_array($page, array('login','signup'))) $page = 'login';
} else {
// Authenticated: map to valid pages per role
$allowed = ($user['role'] === 'owner')
? array('dashboard','members','chat')
: array('home','chat');
if (!in_array($page, $allowed)) {
$page = ($user['role'] === 'owner') ? 'dashboard' : 'home';
}
}

// Grab flash error then clear it
$authError = !empty($_SESSION['auth_error']) ? $_SESSION['auth_error'] : '';
$_SESSION['auth_error'] = null;

// All available rooms (for login/signup dropdowns)
$allRooms = getAllRooms($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Clinic Portal</title>
<style>
/* ── Reset & tokens ──────────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
--bg:#0f1117;--surface:#1a1d27;--surface2:#22263a;--border:#2e3348;
--accent:#4f8ef7;--accent2:#6c63ff;--success:#34d399;--danger:#f87171;
--text:#e8eaf0;--muted:#8b90a7;--radius:12px;--shadow:0 4px 24px rgba(0,0,0,.4);
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── Auth screens ────────────────────────────────────────────────────────── */
.auth-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.auth-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
padding:40px;width:100%;max-width:440px;box-shadow:var(--shadow)}
.auth-card h1{font-size:1.6rem;font-weight:700;margin-bottom:6px;
background:linear-gradient(135deg,var(--accent),var(--accent2));
-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.auth-card .sub{color:var(--muted);font-size:.9rem;margin-bottom:28px}

.role-tabs{display:flex;background:var(--surface2);border-radius:8px;padding:4px;margin-bottom:24px;gap:4px}
.role-tab{flex:1;padding:10px;border:none;border-radius:6px;background:transparent;
color:var(--muted);cursor:pointer;font-size:.9rem;font-weight:500;transition:all .2s}
.role-tab.active{background:var(--accent);color:#fff}

.form-group{margin-bottom:16px}
label{display:block;font-size:.8rem;color:var(--muted);margin-bottom:6px;
font-weight:600;letter-spacing:.04em;text-transform:uppercase}
input,select{width:100%;padding:11px 14px;background:var(--surface2);border:1px solid var(--border);
border-radius:8px;color:var(--text);font-size:.95rem;outline:none;transition:border-color .2s}
input:focus,select:focus{border-color:var(--accent)}
select option{background:var(--surface2)}

.btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:1rem;font-weight:600;
cursor:pointer;transition:opacity .2s,transform .1s;margin-top:8px}
.btn:active{transform:scale(.98)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border)}

.auth-toggle{text-align:center;margin-top:18px;font-size:.88rem;color:var(--muted)}
.auth-toggle a{color:var(--accent);cursor:pointer;text-decoration:none}

.error-box{background:rgba(248,113,113,.12);border:1px solid var(--danger);color:var(--danger);
border-radius:8px;padding:10px 14px;font-size:.88rem;margin-bottom:16px}

/* ── App layout ──────────────────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;height:100vh}

.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 24px;
height:60px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0}
.topbar-logo{font-weight:700;font-size:1.1rem;
background:linear-gradient(135deg,var(--accent),var(--accent2));
-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.topbar-right{display:flex;align-items:center;gap:14px}
.user-badge{display:flex;align-items:center;gap:8px;font-size:.88rem}
.avatar{width:32px;height:32px;border-radius:50%;
background:linear-gradient(135deg,var(--accent),var(--accent2));
display:flex;align-items:center;justify-content:center;
font-weight:700;font-size:.8rem;color:#fff;flex-shrink:0}
.role-pill{padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600}
.role-pill.owner{background:rgba(79,142,247,.15);color:var(--accent)}
.role-pill.member{background:rgba(52,211,153,.15);color:var(--success)}
.btn-out{padding:7px 14px;border-radius:7px;background:var(--surface2);
border:1px solid var(--border);color:var(--muted);cursor:pointer;
font-size:.83rem;transition:color .2s}
.btn-out:hover{color:var(--danger)}

.app-body{display:flex;flex:1;overflow:hidden}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.sidebar{width:220px;background:var(--surface);border-right:1px solid var(--border);
padding:16px 0;flex-shrink:0}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;
color:var(--muted);font-size:.9rem;font-weight:500;
text-decoration:none;border-left:3px solid transparent;transition:all .15s}
.nav-item:hover{background:var(--surface2);color:var(--text)}
.nav-item.active{color:var(--accent);background:rgba(79,142,247,.08);border-left-color:var(--accent)}
.nav-icon{font-size:1.1rem}

/* ── Panels ──────────────────────────────────────────────────────────────── */
.content{flex:1;overflow:hidden;display:flex;flex-direction:column}
.panel{flex:1;overflow-y:auto;padding:28px;display:flex;flex-direction:column}
.panel-title{font-size:1.3rem;font-weight:700;margin-bottom:22px}

/* ── Room banner ─────────────────────────────────────────────────────────── */
.room-banner{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);
padding:16px 20px;margin-bottom:22px;display:flex;align-items:center;gap:14px}
.room-number{font-size:2rem;font-weight:800;
background:linear-gradient(135deg,var(--accent),var(--accent2));
-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.room-label{color:var(--muted);font-size:.85rem;margin-top:2px}

/* ── Stat cards ──────────────────────────────────────────────────────────── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px}
.stat-value{font-size:2rem;font-weight:800;
background:linear-gradient(135deg,var(--accent),var(--accent2));
-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.stat-label{color:var(--muted);font-size:.82rem;margin-top:4px}

/* ── Members table ───────────────────────────────────────────────────────── */
.table-wrap{overflow:auto;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th{text-align:left;padding:10px 14px;color:var(--muted);font-size:.78rem;
text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
td{padding:13px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:var(--surface2)}
.m-avatar{width:30px;height:30px;border-radius:50%;
background:linear-gradient(135deg,var(--accent2),var(--success));
display:inline-flex;align-items:center;justify-content:center;
font-weight:700;font-size:.75rem;color:#fff;margin-right:10px;vertical-align:middle}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px;vertical-align:middle}
.dot.on{background:var(--success)}
.dot.off{background:var(--muted)}

/* ── Chat ────────────────────────────────────────────────────────────────── */
.chat-outer{display:flex;flex-direction:column;flex:1;background:var(--surface);
border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;min-height:0}
.chat-msgs{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px}
.msg{display:flex;gap:10px;align-items:flex-start}
.msg.own{flex-direction:row-reverse}
.msg-avatar{width:32px;height:32px;border-radius:50%;
background:linear-gradient(135deg,var(--accent),var(--accent2));
display:flex;align-items:center;justify-content:center;
font-weight:700;font-size:.75rem;color:#fff;flex-shrink:0}
.msg-body{max-width:68%}
.msg-meta{font-size:.72rem;color:var(--muted);margin-bottom:4px}
.msg.own .msg-meta{text-align:right}
.bubble{padding:10px 14px;border-radius:12px;font-size:.9rem;line-height:1.5;word-break:break-word}
.msg:not(.own) .bubble{background:var(--surface2);border-bottom-left-radius:4px}
.msg.own .bubble{background:linear-gradient(135deg,var(--accent),var(--accent2));
color:#fff;border-bottom-right-radius:4px}
.chat-bar{display:flex;gap:10px;padding:14px 16px;border-top:1px solid var(--border);background:var(--surface2)}
.chat-bar input{flex:1;border-radius:8px;padding:10px 14px}
.btn-send{padding:10px 18px;border-radius:8px;background:var(--accent);color:#fff;
border:none;font-weight:600;cursor:pointer;font-size:.9rem;flex-shrink:0;transition:opacity .2s}
.btn-send:hover{opacity:.85}

.empty{text-align:center;padding:48px 20px;color:var(--muted)}
.empty .icon{font-size:3rem;margin-bottom:12px}

/* info text */
.info-text{color:var(--muted);font-size:.9rem;line-height:1.6}
info-text strong{color:var(--text)}

.minfo-text strong{color:var(--text)}

.share-code{display:inline-block;margin-top:10px;padding:6px 14px;
background:rgba(79,142,247,.12);border:1px solid var(--accent);
border-radius:8px;color:var(--accent);font-weight:700;letter-spacing:.08em;font-size:1rem}
</style>
</head>
<body>

<?php if (!$user): ?>

<div class="auth-wrap">
<div class="auth-card">
<h1>Clinic Portal</h1>
<p class="sub">MedScribeAI — Sign in to your workspace</p>

<div class="role-tabs">
<button class="role-tab" id="tab-member" onclick="setRole('member')" type="button">👤 Member</button>
<button class="role-tab" id="tab-owner" onclick="setRole('owner')" type="button">🏥 Clinic Owner</button>
</div>

<?php if ($authError): ?>
<div class="error-box"><?php echo h($authError); ?></div>
<?php endif; ?>

<div id="frm-login" <?php echo ($page==='signup') ? 'style="display:none"' : ''; ?>>
<form method="POST" action="auth.php">
<input type="hidden" name="action" value="login"/>
<input type="hidden" name="role" id="login-role" value="member"/>
<div class="form-group"><label>Email</label><input type="email" name="email" placeholder="you@email.com" required/></div>
<div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" required/></div>
<div class="form-group" id="login-room-wrap">
<label>Clinic Room</label>
<select name="room_id" id="login-room-select">
<option value="">— select your room —</option>
<?php foreach ($allRooms as $r): ?>
<option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['room_number']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary" type="submit">Sign In</button>
</form>
<p class="auth-toggle">No account? <a onclick="switchTo('signup')">Create one</a></p>
</div>

<div id="frm-signup" <?php echo ($page!=='signup') ? 'style="display:none"' : ''; ?>>
<form method="POST" action="auth.php">
<input type="hidden" name="action" value="signup"/>
<input type="hidden" name="role" id="signup-role" value="member"/>
<div class="form-group"><label>Full Name</label><input type="text" name="name" placeholder="Dr. Jane Smith" required/></div>
<div class="form-group"><label>Email</label><input type="email" name="email" placeholder="you@email.com" required/></div>
<div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Min. 6 characters" required/></div>
<div class="form-group" id="owner-room-wrap" style="display:none">
<label>Room Number (owner)</label>
<input type="text" name="room_number" id="signup-room-number" placeholder="e.g. CLINIC-001" maxlength="20"/>
</div>
<div class="form-group" id="member-room-wrap">
<label>Clinic Room to Join</label>
<select name="room_id" id="signup-room-select">
<option value="">— select a room —</option>
<?php foreach ($allRooms as $r): ?>
<option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['room_number']); ?></option>
<?php endforeach; ?>
<?php if (empty($allRooms)): ?>
<option disabled>No rooms yet — sign up as owner first</option>
<?php endif; ?>
</select>
</div>
<button class="btn btn-primary" type="submit">Create Account</button>
</form>
<p class="auth-toggle">Already have an account? <a onclick="switchTo('login')">Sign in</a></p>
</div>

</div></div>

<script>
function setRole(role){
document.getElementById('login-role').value=role;
document.getElementById('signup-role').value=role;
document.getElementById('tab-member').classList.toggle('active',role==='member');
document.getElementById('tab-owner').classList.toggle('active',role==='owner');
var lrw=document.getElementById('login-room-wrap');
if(lrw)lrw.style.display=(role==='member')?'':'none';
document.getElementById('owner-room-wrap').style.display=(role==='owner')?'':'none';
document.getElementById('member-room-wrap').style.display=(role==='member')?'':'none';
var oi=document.getElementById('signup-room-number');
var mi=document.getElementById('signup-room-select');
if(oi)oi.required=(role==='owner');
if(mi)mi.required=(role==='member');
}
function switchTo(v){
document.getElementById('frm-login').style.display=(v==='login')?'':'none';
document.getElementById('frm-signup').style.display=(v==='signup')?'':'none';
}
setRole('member');
document.getElementById('tab-member').classList.add('active');
</script>

<?php else: ?>
<?php
$initials = strtoupper(mb_substr($user['name'],0,1));
$roomNumber = $room ? h($room['room_number']) : '—';
$ownerName = $room ? k($room['owner_name']) : '—';
$members = getMembersByRoom($db, $user['room_id']);
$onlineCount = count(array_filter($members, function($m){ return isOnline($m['last_seen']); }));
// fix: use h() not undefined k()
$ownerName = $room ? h($room['owner_name']) : '—';
?>
<div class="app">
<div class="topbar">
<div class="topbar-logo">🏥 Clinic Portal</div>
<div class="topbar-right">
<div class="user-badge">
<div class="avatar"><?php echo $initials; ?></div>
<div>
<div style="font-size:.88rem;font-weight:600"><?php echo h($user['name']); ?></div>
<div style="font-size:.75rem;color:var(--muted)">Room <?php echo $roomNumber; ?></div>
</div>
</div>
<span class="role-pill <?php echo h($user['role']); ?>"><?php echo $user['role']==='owner'?'🏥 Owner':'👤 Member'; ?></span>
<form method="POST" action="auth.php" style="margin:0">
<input type="hidden" name="action" value="logout"/>
<button class="btn-out" type="submit">Sign Out</button>
</form>
</div>
</div>
<div class="app-body">
<div class="sidebar">
<?php if ($user['role']==='owner'): ?>
<a class="nav-item <?php echo $page==='dashboard'?'active':''; ?>" href="?page=dashboard"><span class="nav-icon">📊</span> Dashboard</a>
<a class="nav-item <?php echo $page==='members'?'active':''; ?>" href="?page=members"><span class="nav-icon">👥</span> Members</a>
<a class="nav-item <?php echo $page==='chat'?'active':''; ?>" href="?page=chat"><span class="nav-icon">💬</span> Chat</a>
<?php else: ?>
<a class="nav-item <?php echo $page==='home'?'active':''; ?>" href="?page=home"><span class="nav-icon">🏠</span> Home</a>
<a class="nav-item <?php echo $page==='chat'?'active':''; ?>" href="?page=chat"><span class="nav-icon">💬</span> Chat</a>
<?php endif; ?>
</div>
<div class="content">

<?php if ($page==='dashboard'): ?>
<div class="panel">
<div class="panel-title">📊 Dashboard</div>
<div class="room-banner">
<div><div class="room-number"><?php echo $roomNumber; ?></div><div class="room-label">Your Clinic Room</div></div>
<div style="margin-left:auto;margin-right:16px;color:var(--muted);font-size:.85rem">Owner: <?php echo h($user['name']); ?></div>
</div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-value"><?php echo count($members); ?></div><div class="stat-label">Total Members</div></div>
<div class="stat-card"><div class="stat-value" id="stat-online"><?php echo $onlineCount; ?></div><div class="stat-label">Online Now</div></div>
</div>
<p class="info-text">Share this room code with your staff:<br/><span class="share-code"><?php echo $roomNumber; ?></span></p>
</div>

<?php elseif ($page==='members'): ?>
<div class="panel">
<div class="panel-title">👥 Clinic Members</div>
<div class="table-wrap">
<table><thead><tr><th>Member</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
<tbody id="members-tbody">
<?php if (empty($members)): ?>
<tr><td colspan="5"><div class="empty"><div class="icon">👤</div>No members yet</div></td></tr>
<?php else: foreach($members as $m): ?>
<tr>
<td><span class="m-avatar"><?php echo strtoupper(mb_substr($m['name'],0,1)); ?></span><?php echo h($m['name']); ?></td>
<td style="color:var(--muted)"><?php echo h($m['email']); ?></td>
<td><?php echo $m['role']==='owner'?'<span class="role-pill owner">🏥 Owner</span>':'<span class="role-pill member">👤 Member</span>'; ?></td>
<td><span class="dot <?php echo isOnline($m['last_seen'])?'on':'off'; ?>"></span><?php echo isOnline($m['last_seen'])?'Online':'Offline'; ?></td>
<td style="color:var(--muted)"><?php echo h(substr($m['created_at'],0,10)); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
</div>
</div>

<?php elseif ($page==='home'): ?>
<div class="panel">
<div class="panel-title">🏠 My Clinic</div>
<div class="room-banner">
<div><div class="room-number"><?php echo $roomNumber; ?></div><div class="room-label">Your Clinic Room</div></div>
<div style="margin-left:auto;margin-right:16px;color:var(--muted);font-size:.85rem">Owner: <?php echo $ownerName; ?></div>
</div>
<p class="info-text">Welcome, <strong><?php echo h($user['name']); ?></strong>!<br/>Use the <strong>Clinic Chat</strong> tab to communicate with your team.</p>
</div>

<?php elseif ($page==='chat'): ?>
<div class="panel" style="padding-bottom:0;overflow:hidden">
<div class="panel-title">💬 Clinic Chat — Room <?php echo $roomNumber; ?></div>
<div class="chat-outer">
<div class="chat-msgs" id="chat-msgs"><div class="empty"><div class="icon">💬</div>Loading...</div></div>
<div class="chat-bar">
<input type="text" id="chat-input" placeholder="Type a message and press Enter…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"/>
<button class="btn-send" onclick="sendMsg()">Send ➤</button>
</div>
</div>
</div>
<script>
var lastId=0,myUid=<?php echo (int)$user['id']; ?>;
function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br/>')}
function fmt(d){if(!d)return'';var t=new Date(d.replace(' ','T')+'Z');return t.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}
function render(ms,cuid){
var b=document.getElementById('chat-msgs'),h='';
if(!ms.length&&lastId===0){b.innerHTML='<div class="empty"><div class="icon">💬</div>No messages yet!</div>';return}
for(var i=0;i<ms.length;i++){
var m=ms[i],own=parseInt(m.user_id)===cuid;var ini=(m.sender_name||'?').charAt(0).toUpperCase();
h+='<div class="msg'+(own?' own':'')+'"><div class="msg-avatar">'+ini+'</div><div class="msg-body"><div class="msg-meta">'+(own?'You':escH(m.sender_name))+' &middot; '+fmt(m.created_at)+'</div><div class="bubble">'+escH(m.message)+'</div></div></div>';
if(parseInt(m.id)>lastId)lastId=parseInt(m.id);
}
if(lastId===0){b.innerHTML=h}else{var e=b.querySelector('.empty');if(e)e.remove();b.insertAdjacentHTML('beforeend',h)}
b.scrollTop=b.scrollHeight;
}
function poll(){var x=new XMLHttpRequest();x.open('GET','api.php?action=get_messages&last_id='+ lastId,true);x.onload=function(){if(x.status===200){try{var d=JSON.parse(x.responseText);if(d.messages&&d.messages.length)render(d.messages,d.current_uid||myUid)}catch(e){}}};x.send()}
function sendMsg(){var i=document.getElementById('chat-input'),t=i.value.trim();if(!t)return;i.value='';i.disabled=true;var f=new FormData();f.append('action','send_message');f.append('message',t);var x=new XMLHttpRequest();x.open('POST','api.php',true);x.onload=function(){i.disabled=false;i.focus();poll()};x.onerror=function(){i.disabled=false};x.send(f)}
poll();setInterval(poll,3000);
</script>

<?php endif; ?>
</div>
</div>
</div>

<?php if ($page==='members'&&$user['role']==='owner'): ?>
<script>
function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function refreshMembers(){var x=new XMLHttpRequest();x.open('GET','api.php?action=get_members',true);x.onload=function(){if(x.status!==200)return;try{var d=JSON.parse(x.responseText);var r='';if(!d.members||!d.members.length){r='<tr><td colspan="5"><div class="empty"><div class="icon">👤</div>No members yet</div></td></tr>'}else{for(var i=0;i<d.members.length;i++){var m=d.members[i];var ini=(m.name||'?').charAt(0).toUpperCase();r+='<tr><td><span class="m-avatar">'+ini+'</span>'+escH(m.name)+'</td><td style="color:var(--muted)">'+escH(m.email)+'</td><td>'+(m.role==='owner'?'<span class="role-pill owner">🏥 Owner</span>':'<span class="role-pill member">👤 Member</span>')+'</td><td>'+(m.online?'<span class="dot on"></span>Online':'<span class="dot off"></span>Offline')+'</td><td style="color:var(--muted)">'+(m.joined?m.joined.substring(0,10):'—')+'</td></tr>'}}document.getElementById('members-tbody').innerHTML=r}catch(e){}};x.send()}
setInterval(refreshMembers,15000);
</script>
<?php endif; ?>
<?php if ($page==='dashboard'&&$user['role']==='owner'): ?>
<script>
function refreshOnline(){var x=new XMLHttpRequest();x.open('GET','api.php?action=get_members',true);x.onload=function(){if(x.status!==200)return;try{var d=JSON.parse(x.responseText);var o=0;if(d.members)for(var i=0;i<d.members.length;i++)if(d.members[i].online)o++;document.getElementById('stat-online').textContent=o}catch(e){}};x.send()}
setInterval(refreshOnline,15000);
</script>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
