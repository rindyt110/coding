<?php
// index.php — Password Strength Checker (single file)
// Serves frontend (GET) and handles backend (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    function respond($data) {
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($password === '') {
        respond(['ok'=>true,'S'=>0,'L'=>0,'crack_seconds'=>0,'readable'=>'—']);
    }

    // character checks
    $checklower = preg_match('/[a-z]/', $password);
    $checkupper = preg_match('/[A-Z]/', $password);
    $checkdigit = preg_match('/[0-9]/', $password);
    $checkspecial = preg_match('/[!@#\$%\^&\*\(\)_\+\-\=\{\}\[\]:;"\'<>,\.\?\/\\|`~]/', $password);

    // total character set size
    $S = 0;
    if ($checklower) $S += 26;
    if ($checkupper) $S += 26;
    if ($checkdigit) $S += 10;
    if ($checkspecial) $S += 33;

    $L = strlen($password);
    if ($S == 0 || $L == 0) {
        respond(['ok'=>true,'S'=>$S,'L'=>$L,'crack_seconds'=>0,'readable'=>'Too short']);
    }

    // calculate crack time (same logic as before)
    $log10_time = $L * log10($S) - 9.0;
    $crack_seconds = pow(10, $log10_time);

    // readable crack time
    if ($crack_seconds < 1) $readable = round($crack_seconds,3).' sec';
    elseif ($crack_seconds < 60) $readable = round($crack_seconds,2).' sec';
    elseif ($crack_seconds < 3600) $readable = round($crack_seconds/60,2).' min';
    elseif ($crack_seconds < 86400) $readable = round($crack_seconds/3600,2).' hrs';
    elseif ($crack_seconds < 2592000) $readable = round($crack_seconds/86400,2).' days';
    elseif ($crack_seconds < 31536000) $readable = round($crack_seconds/2592000,2).' months';
    else $readable = round($crack_seconds/31536000,2).' years';

    // realistic progress score (based on log10 seconds)
    $log_sec = log10($crack_seconds);
    $score = min(100, max(5, ($log_sec / 9.5) * 100)); // 100 years → full bar

    respond([
        'ok'=>true,
        'crack_seconds'=>$crack_seconds,
        'readable'=>$readable,
        'checklower'=>(bool)$checklower,
        'checkupper'=>(bool)$checkupper,
        'checkdigit'=>(bool)$checkdigit,
        'checkspecial'=>(bool)$checkspecial,
        'score'=>$score
    ]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Password Strength Checker</title>
  <style>
    :root{
      --bg:#0f1720;--panel:#0b1220;--muted:#9aa4b2;--accent:#7b61ff;--accent2:#00d2ff;
      --success:#22c55e;--danger:#ef4444;
    }
    *{box-sizing:border-box}
    html,body{
      margin:0;padding:0;height:100%;
      font-family:Inter,Arial,sans-serif;
      background:var(--bg);color:#e6eef6;
      display:flex;align-items:center;justify-content:center
    }
    .container{width:100%;max-width:650px;padding:30px}
    .panel{
      background:rgba(255,255,255,0.03);
      border-radius:10px;
      padding:40px 35px;
      box-shadow:0 10px 40px rgba(0,0,0,0.5)
    }
    h1{text-align:center;margin-bottom:8px;font-size:2rem}
    p.lead{text-align:center;color:var(--muted);margin-top:0;margin-bottom:30px}
    textarea{
      width:100%;padding:15px;border-radius:8px;
      background:rgba(255,255,255,0.05);
      border:1px solid rgba(255,255,255,0.1);
      color:white;resize:none;font-size:1rem
    }
    textarea:focus{outline:none;border-color:var(--accent)}
    .bar{height:10px;background:rgba(255,255,255,0.1);border-radius:50px;margin-top:15px;overflow:hidden}
    .fill{height:100%;width:0%;background:linear-gradient(90deg,var(--danger),#facc15,var(--success));transition:width 0.4s ease}
    .result{margin-top:20px;font-size:1rem;text-align:center}
    .checks{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:20px}
    .check{padding:6px 12px;border-radius:20px;font-size:0.8rem}
    .ok{background:rgba(34,197,94,0.15);color:var(--success)}
    .bad{background:rgba(239,68,68,0.15);color:var(--danger)}
    @media(max-width:600px){.panel{padding:25px 20px}h1{font-size:1.6rem}}
  </style>
</head>
<body>
<div class="container">
  <div class="panel">
    <h1>Password Strength Tester</h1>
    <p class="lead">Type a password below to check its strength</p>
    <textarea id="password" rows="2" placeholder="Enter your password..."></textarea>
    <div class="bar"><div class="fill" id="fill"></div></div>
    <div class="result">Estimated crack time: <span id="time">—</span></div>
    <div class="checks">
      <div id="c1" class="check">a–z</div>
      <div id="c2" class="check">A–Z</div>
      <div id="c3" class="check">0–9</div>
      <div id="c4" class="check">#@!$%</div>
    </div>
  </div>
</div>
<script>
const password=document.getElementById('password');
const fill=document.getElementById('fill');
const timeEl=document.getElementById('time');
const c1=document.getElementById('c1'),c2=document.getElementById('c2'),c3=document.getElementById('c3'),c4=document.getElementById('c4');
function setCheck(el,ok){el.className='check '+(ok?'ok':'bad');}

async function evaluate(pwd){
  if(!pwd){fill.style.width='0%';timeEl.textContent='—';[c1,c2,c3,c4].forEach(e=>e.className='check');return;}
  try{
    const fd=new FormData();fd.append('password',pwd);
    const r=await fetch(window.location.pathname,{method:'POST',body:fd});
    const d=await r.json();
    fill.style.width=Math.min(100,Math.max(5,d.score))+'%';
    timeEl.textContent=d.readable;
    setCheck(c1,d.checklower);setCheck(c2,d.checkupper);setCheck(c3,d.checkdigit);setCheck(c4,d.checkspecial);
  }catch(e){console.error(e);}
}
let t=null;
password.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>evaluate(password.value),150);});
</script>
</body>
</html>
