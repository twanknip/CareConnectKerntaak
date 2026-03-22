<?php
require_once 'common.php';
require_once 'dbfuncs.php';

$error   = '';
$success = '';
/*
|--------------------------------------------------------------------------
| ⚠️ KWETSBARE CODE (ACTIEF - VOOR LEERDOELEINDEN)
|--------------------------------------------------------------------------
| Probleem:
| - User input wordt direct in SQL geplakt (string concatenation)
| - Geen escaping of prepared statements
|
| Voorbeelden van aanvallen:
| username: admin' --
| password: (leeg)
|
| Resultaat query:
| SELECT * FROM users WHERE username = 'admin' -- ' AND password = ''
| → wachtwoord check wordt genegeerd
|
| Of:
| username: ' OR '1'='1
| password: ' OR '1'='1
|
| → altijd login succesvol
|
| RISICO:
| - Login bypass
| - Data leakage
| - Database manipulatie
|
*/

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (!empty($_REQUEST['username']) && !empty($_REQUEST['password'])) {

        // ❌ ONVEILIG: SQL injection mogelijk
        $authSQL = "select * from users where username = '" . $_REQUEST['username'] .
                   "' and password = '" . $_REQUEST['password'] . "'";

        $authed  = getSelect($authSQL);

        if (!$authed) {
            $error = $authSQL; // ⚠️ toont zelfs de query (extra slecht)
        } else {
            $_SESSION['authed']   = true;
            $_SESSION['userid']   = $authed[0][0];
            $_SESSION['username'] = $authed[0][1];
            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'Vul alle velden in.';
    }
}




//  if ($_SERVER['REQUEST_METHOD'] == "POST") {
//      if (!empty($_POST['username']) && !empty($_POST['password'])) {

//          global $con;
//          if ($con === null) connect();

//          $stmt = mysqli_prepare($con, 
//              "SELECT id, username, password FROM users WHERE username = ?"
//          );

//          mysqli_stmt_bind_param($stmt, "s", $_POST['username']);
//          mysqli_stmt_execute($stmt);
//          $result = mysqli_stmt_get_result($stmt);
//          $user = mysqli_fetch_assoc($result);

//          // ✅ met password hashing
//          if ($user && password_verify($_POST['password'], $user['password'])) {
//              $_SESSION['authed'] = true;
//              $_SESSION['userid'] = $user['id'];
//              $_SESSION['username'] = $user['username'];
//              header('Location: index.php');
//              exit;
//          } else {
//              $error = 'Ongeldige login.';
//          }
//      }
//  }




/*
|--------------------------------------------------------------------------
| 🔐 EXTRA BEST PRACTICES
|--------------------------------------------------------------------------
| - Gebruik password_hash() bij registratie:
|     $hash = password_hash($password, PASSWORD_DEFAULT);
|
| - Toon NOOIT SQL queries aan gebruikers
| - Gebruik $_POST i.p.v. $_REQUEST
| - Voeg rate limiting toe (tegen brute force)
| - Log mislukte loginpogingen
|
*/
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CareConnect — Inloggen</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:        #0b2a4a;
      --navy-mid:    #123a62;
      --blue:        #1a6fa8;
      --blue-light:  #2e9dd4;
      --teal:        #0d9488;
      --teal-light:  #14b8a6;
      --white:       #ffffff;
      --off-white:   #f0f7ff;
      --gray-100:    #e8f1f8;
      --gray-300:    #a8c0d4;
      --gray-500:    #5a7a96;
      --text:        #0b2a4a;
      --text-soft:   #4a6a84;
      --error:       #dc2626;
      --error-bg:    #fef2f2;
      --error-border:#fecaca;
      --radius:      14px;
      --trans:       0.22s cubic-bezier(0.4,0,0.2,1);
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
    }

    /* ── Background ── */
    .bg {
      position: fixed; inset: 0; z-index: 0;
      background: linear-gradient(145deg, #071e36 0%, #0b2a4a 45%, #0a3d55 100%);
      overflow: hidden;
    }
    .bg-blob {
      position: absolute; border-radius: 50%;
      filter: blur(90px); opacity: 0.15;
      animation: drift 16s ease-in-out infinite alternate;
    }
    .bg-blob-1 { width: 600px; height: 600px; background: #1a6fa8; top: -150px; left: -100px; animation-delay: 0s; }
    .bg-blob-2 { width: 400px; height: 400px; background: #0d9488; bottom: -100px; right: -80px; animation-delay: -7s; }
    .bg-blob-3 { width: 300px; height: 300px; background: #2e9dd4; top: 50%; left: 55%; animation-delay: -12s; }
    @keyframes drift {
      from { transform: translate(0,0) scale(1); }
      to   { transform: translate(25px, 35px) scale(1.06); }
    }
    .bg-grid {
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(46,157,212,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(46,157,212,0.05) 1px, transparent 1px);
      background-size: 52px 52px;
    }

    /* ── ECG line ── */
    .ecg-wrap {
      position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%);
      width: 560px; opacity: 0.2; pointer-events: none;
    }
    .ecg-wrap svg { width: 100%; overflow: visible; }
    .ecg-path {
      fill: none; stroke: #14b8a6; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
      stroke-dasharray: 1000; stroke-dashoffset: 1000;
      animation: ecgDraw 3s ease-in-out infinite;
    }
    @keyframes ecgDraw {
      0%  { stroke-dashoffset: 1000; opacity: 1; }
      65% { stroke-dashoffset: 0;    opacity: 1; }
      85% { stroke-dashoffset: 0;    opacity: 0; }
      100%{ stroke-dashoffset: 1000; opacity: 0; }
    }

    /* ── Layout ── */
    .page {
      position: relative; z-index: 1;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
    }

    /* ── Card ── */
    .card {
      display: flex; width: 100%; max-width: 940px; min-height: 580px;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,0.45), 0 4px 16px rgba(0,0,0,0.2);
      animation: rise 0.65s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(28px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0)    scale(1);    }
    }

    /* ── Left Panel ── */
    .panel-left {
      flex: 1;
      background: linear-gradient(155deg, #0d3d6b 0%, #0b2a4a 55%, #083344 100%);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 52px 40px; position: relative; overflow: hidden;
    }

    /* Rings */
    .rings { position: absolute; width: 500px; height: 500px; top: 50%; left: 50%; transform: translate(-50%,-50%); pointer-events: none; }
    .ring {
      position: absolute; border-radius: 50%;
      border: 1px solid rgba(46,157,212,0.1);
      top: 50%; left: 50%; transform: translate(-50%,-50%);
      animation: pulseRing 5s ease-in-out infinite;
    }
    .ring:nth-child(1){ width:150px; height:150px; animation-delay:0s;    }
    .ring:nth-child(2){ width:260px; height:260px; animation-delay:-1.4s; }
    .ring:nth-child(3){ width:370px; height:370px; animation-delay:-2.8s; }
    .ring:nth-child(4){ width:480px; height:480px; animation-delay:-4.2s; }
    @keyframes pulseRing {
      0%,100%{ opacity:0.18; transform:translate(-50%,-50%) scale(1);    }
      50%    { opacity:0.45; transform:translate(-50%,-50%) scale(1.03); }
    }

    /* Cross */
    .cross-wrap {
      position: relative; z-index: 1; margin-bottom: 32px;
      animation: glowPulse 3s ease-in-out infinite alternate;
    }
    @keyframes glowPulse {
      from { filter: drop-shadow(0 0 8px rgba(20,184,166,0.35)); }
      to   { filter: drop-shadow(0 0 22px rgba(20,184,166,0.7)); }
    }

    .panel-text {
      position: relative; z-index: 1; text-align: center; color: var(--white);
    }
    .panel-text h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.75rem; font-weight: 400;
      letter-spacing: -0.3px; margin-bottom: 10px; line-height: 1.2;
    }
    .panel-text p {
      font-size: 0.88rem; color: rgba(255,255,255,0.52);
      line-height: 1.65; max-width: 260px;
    }

    /* Feature items */
    .features { position: relative; z-index:1; margin-top:36px; width:100%; max-width:290px; display:flex; flex-direction:column; gap:11px; }
    .feat {
      display:flex; align-items:center; gap:12px;
      background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 11px; padding: 11px 15px;
      color: rgba(255,255,255,0.78); font-size: 0.82rem;
      backdrop-filter: blur(6px);
      animation: slideLeft 0.6s cubic-bezier(0.22,1,0.36,1) both;
    }
    .feat:nth-child(1){ animation-delay:0.4s; }
    .feat:nth-child(2){ animation-delay:0.55s; }
    .feat:nth-child(3){ animation-delay:0.7s; }
    @keyframes slideLeft {
      from { opacity:0; transform:translateX(-18px); }
      to   { opacity:1; transform:translateX(0); }
    }
    .feat-icon { flex-shrink:0; color: var(--teal-light); }

    /* ── Right Panel ── */
    .panel-right {
      width: 430px; flex-shrink: 0;
      background: var(--white);
      display: flex; flex-direction: column; justify-content: center;
      padding: 52px 46px;
    }

    /* Brand */
    .brand { display:flex; align-items:center; gap:11px; margin-bottom:36px; }
    .brand-logo {
      width: 40px; height: 40px; border-radius: 11px;
      background: linear-gradient(135deg, var(--navy-mid), var(--teal));
      display:flex; align-items:center; justify-content:center;
    }
    .brand-name { font-family:'DM Serif Display',serif; font-size:1.25rem; color:var(--navy); letter-spacing:-0.2px; }
    .brand-name em { font-style:normal; color:var(--teal); }

    /* Headings */
    .form-title { font-family:'DM Serif Display',serif; font-size:1.65rem; font-weight:400; color:var(--text); letter-spacing:-0.4px; margin-bottom:6px; }
    .form-sub   { font-size:0.87rem; color:var(--gray-300); margin-bottom:30px; }

    /* Error */
    .error-box {
      display:none; background:var(--error-bg); border:1px solid var(--error-border);
      border-radius:10px; padding:11px 14px; color:var(--error);
      font-size:0.84rem; margin-bottom:18px;
      animation: slideDown 0.28s ease;
    }
    @keyframes slideDown {
      from{ opacity:0; transform:translateY(-6px); }
      to  { opacity:1; transform:translateY(0); }
    }
    .error-box.show{ display:block; }

    /* Form */
    .form { display:flex; flex-direction:column; gap:18px; }
    .field { display:flex; flex-direction:column; gap:6px; }
    .label { font-size:0.78rem; font-weight:600; color:var(--text-soft); text-transform:uppercase; letter-spacing:0.4px; }
    .input-wrap { position:relative; display:flex; align-items:center; }
    .input-icon { position:absolute; left:14px; color:var(--gray-300); pointer-events:none; transition:color var(--trans); }
    .input-wrap input {
      width:100%; padding:13px 14px 13px 43px;
      border: 1.5px solid var(--gray-100); border-radius: var(--radius);
      background: var(--off-white); font-family:'DM Sans',sans-serif;
      font-size:0.93rem; color:var(--text); outline:none;
      transition: border-color var(--trans), background var(--trans), box-shadow var(--trans);
    }
    .input-wrap input::placeholder { color:var(--gray-300); }
    .input-wrap input:focus {
      border-color: var(--blue-light); background: var(--white);
      box-shadow: 0 0 0 4px rgba(46,157,212,0.12);
    }
    .input-wrap:focus-within .input-icon { color:var(--blue); }

    /* pw toggle */
    .pw-toggle {
      position:absolute; right:13px; background:none; border:none;
      cursor:pointer; color:var(--gray-300); padding:4px; border-radius:6px;
      transition:color var(--trans);
    }
    .pw-toggle:hover { color:var(--blue); }

    /* Options row */
    .form-row { display:flex; align-items:center; justify-content:space-between; margin-top:-4px; }
    .remember { display:flex; align-items:center; gap:8px; cursor:pointer; user-select:none; }
    .remember input[type=checkbox] {
      appearance:none; -webkit-appearance:none;
      width:17px; height:17px; border:1.5px solid var(--gray-300);
      border-radius:5px; background:var(--off-white); cursor:pointer;
      position:relative; transition:all var(--trans); flex-shrink:0;
    }
    .remember input:checked { background:var(--blue); border-color:var(--blue); }
    .remember input:checked::after {
      content:''; position:absolute; left:4px; top:1.5px;
      width:5px; height:9px; border:2px solid #fff;
      border-top:none; border-left:none; transform:rotate(45deg);
    }
    .remember-text { font-size:0.83rem; color:var(--gray-500); }
    .forgot { font-size:0.83rem; color:var(--blue); text-decoration:none; font-weight:500; transition:color var(--trans); }
    .forgot:hover { color:var(--teal); }

    /* Button */
    .btn {
      position:relative; padding:14px; margin-top:4px;
      background: linear-gradient(135deg, var(--navy-mid) 0%, var(--blue) 100%);
      color:var(--white); border:none; border-radius:var(--radius);
      font-family:'DM Sans',sans-serif; font-size:0.95rem; font-weight:600;
      letter-spacing:0.2px; cursor:pointer; overflow:hidden;
      transition: transform var(--trans), box-shadow var(--trans);
      box-shadow: 0 4px 20px rgba(26,111,168,0.38);
    }
    .btn:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(26,111,168,0.46); }
    .btn:active { transform:translateY(0) scale(0.98); }
    .btn-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:center; gap:8px; }
    .ripple {
      position:absolute; border-radius:50%; background:rgba(255,255,255,0.25);
      transform:scale(0); animation:rippleAnim 0.5s linear; pointer-events:none;
    }
    @keyframes rippleAnim { to{ transform:scale(4); opacity:0; } }

    /* Divider */
    .divider { display:flex; align-items:center; gap:12px; margin-top:4px; }
    .div-line { flex:1; height:1px; background:var(--gray-100); }
    .div-text  { font-size:0.74rem; color:var(--gray-300); white-space:nowrap; }

    /* Badge */
    .badge { display:flex; align-items:center; justify-content:center; gap:7px; color:var(--gray-300); font-size:0.77rem; }
    .badge svg { color:var(--teal); }

    /* Debug (intentionally visible for learning) */
    .debug-sql {
      margin-top:16px; padding:10px 12px;
      background:#fff8e1; border:1px solid #ffe082;
      border-radius:8px; font-size:0.78rem; color:#795548;
      font-family:monospace; word-break:break-all; display:none;
    }

    @media(max-width:768px){
      .panel-left  { display:none; }
      .panel-right { width:100%; padding:40px 32px; }
      .card        { max-width:480px; }
    }
    @media(max-width:420px){
      .panel-right { padding:32px 22px; }
    }
  </style>
</head>
<body>

<!-- Background -->
<div class="bg">
  <div class="bg-grid"></div>
  <div class="bg-blob bg-blob-1"></div>
  <div class="bg-blob bg-blob-2"></div>
  <div class="bg-blob bg-blob-3"></div>
  <div class="ecg-wrap">
    <svg viewBox="0 0 560 60" xmlns="http://www.w3.org/2000/svg">
      <polyline class="ecg-path"
        points="0,30 60,30 80,30 96,6 112,54 128,6 144,30 164,30
                184,30 204,30 220,10 234,50 248,10 262,30 282,30
                310,30 330,30 346,8 362,52 378,8 392,30 415,30
                435,30 455,30 470,12 483,48 496,12 509,30 560,30"/>
    </svg>
  </div>
</div>

<div class="page">
  <div class="card">

    <!-- Left -->
    <div class="panel-left">
      <div class="rings">
        <div class="ring"></div><div class="ring"></div>
        <div class="ring"></div><div class="ring"></div>
      </div>

      <div class="cross-wrap">
        <svg width="78" height="78" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="30" y="8"  width="20" height="64" rx="7" fill="url(#cg)" opacity="0.95"/>
          <rect x="8"  y="30" width="64" height="20" rx="7" fill="url(#cg)" opacity="0.95"/>
          <defs>
            <linearGradient id="cg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%"   stop-color="#14b8a6"/>
              <stop offset="100%" stop-color="#2e9dd4"/>
            </linearGradient>
          </defs>
        </svg>
      </div>

      <div class="panel-text">
        <h2>CareConnect Portaal</h2>
        <p>Veilige toegang tot patiëntendossiers en klinische systemen van de HU.</p>
      </div>

      <div class="features">
        <div class="feat">
          <svg class="feat-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          Vertrouwd door de studenten van de HU
        </div>
        <div class="feat">
          <svg class="feat-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
          </svg>
          Toegang tot dossiers en dashboards
        </div>
        <div class="feat">
          <svg class="feat-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
          24/7 beschikbaarheid van het systeem
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="panel-right">
      <div class="brand">
        <div class="brand-logo">
          <svg width="22" height="22" viewBox="0 0 80 80" fill="none">
            <rect x="30" y="8"  width="20" height="64" rx="5" fill="white" opacity="0.9"/>
            <rect x="8"  y="30" width="64" height="20" rx="5" fill="white" opacity="0.9"/>
          </svg>
        </div>
        <div class="brand-name">Care<em>Connect</em></div>
      </div>

      <h1 class="form-title">Welkom terug</h1>
      <p class="form-sub">Log in om verder te gaan</p>

      <?php if ($error): ?>
      <div class="error-box show"><?= $error ?></div>
      <?php endif; ?>

      <form class="form" method="POST" action="">

        <!-- Username -->
        <div class="field">
          <label class="label" for="username">Gebruikersnaam</label>
          <div class="input-wrap">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <input type="text" id="username" name="username" placeholder="Gebruikersnaam" required
                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
          </div>
        </div>

        <!-- Password -->
        <div class="field">
          <label class="label" for="password">Wachtwoord</label>
          <div class="input-wrap">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Wachtwoord tonen/verbergen">
              <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Options -->
        <div class="form-row">
          <label class="remember">
            <input type="checkbox" name="remember">
            <span class="remember-text">Onthoud mij</span>
          </label>
          <a href="#" class="forgot">Wachtwoord vergeten?</a>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn" id="loginBtn">
          <span class="btn-inner">
            <span>Inloggen</span>
          </span>
        </button>

        <div class="divider">
          <div class="div-line"></div>
          <span class="div-text">Veilige medische toegang</span>
          <div class="div-line"></div>
        </div>

        <div class="badge">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          Beveiligd HU-zorgportaal
        </div>

      </form>
    </div>
  </div>
</div>

<script>
  // Password toggle
  const pwToggle = document.getElementById('pwToggle');
  const pwInput  = document.getElementById('password');
  const eyeIcon  = document.getElementById('eyeIcon');
  const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
  pwToggle.addEventListener('click', () => {
    const hidden = pwInput.type === 'password';
    pwInput.type = hidden ? 'text' : 'password';
    eyeIcon.innerHTML = hidden ? eyeClosed : eyeOpen;
  });

  // Ripple
  document.getElementById('loginBtn').addEventListener('click', function(e) {
    const rect = this.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const r = document.createElement('span');
    r.className = 'ripple';
    r.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;
    this.appendChild(r);
    r.addEventListener('animationend', () => r.remove());
  });
</script>
</body>
</html>