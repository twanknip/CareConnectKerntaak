<?php
require_once 'common.php';

if (empty($_SESSION['authed'])) { header('Location: login.php'); exit; }

$activePage = 'template';

/* ════════════════════════════════════════════════════════════════════
   PATH TRAVERSAL KWETSBAARHEID — VOOR ONDERWIJSDOELEINDEN

   ⚠️  KWETSBARE modus (true):
   Probeer: ../../../../etc/passwd
   Of:      php://filter/convert.base64-encode/resource=consts.php
   Resultaat: leest bestanden buiten de webroot

   ✅  VEILIGE modus (false):
   Alleen bestanden uit de whitelist mogen worden geladen
════════════════════════════════════════════════════════════════════ */

$USE_VULNERABLE_PATH = false;   // ← VERANDER DIT OM TE SCHAKELEN
                                // true  = KWETSBAAR (path traversal mogelijk)
                                // false = VEILIG (whitelist)

$load      = $_GET['load'] ?? '';
$content   = '';
$err       = '';
$WHITELIST = ['loadme.php', 'common.php'];

if (!empty($load)) {
    if ($USE_VULNERABLE_PATH) {
        // ⚠️  KWETSBAAR: geen padvalidatie — PATH TRAVERSAL MOGELIJK
        $result = @file_get_contents($load);
        if ($result === false) $err = 'Bestand niet gevonden: ' . htmlspecialchars($load);
        else $content = $result;
    } else {
        // ✅  VEILIG: whitelist — alleen goedgekeurde bestanden
        if (!in_array($load, $WHITELIST)) {
            $err = 'Toegang geweigerd. Alleen bestanden uit de whitelist zijn toegestaan.';
        } else {
            $content = file_get_contents($load);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CareConnect — Sjablonen laden</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background: #f0f4f8;
      background-image:
        radial-gradient(circle at 20% 10%, rgba(0,121,107,0.04) 0%, transparent 50%),
        radial-gradient(circle at 80% 90%, rgba(21,101,192,0.04) 0%, transparent 50%);
    }

    /* Breadcrumb */
    .breadcrumb { display:flex; align-items:center; gap:8px; margin-bottom:28px; font-size:.8rem; color:#8fa8bf; letter-spacing:.2px; }
    .breadcrumb a { color:#5a8aa8; text-decoration:none; transition:color .2s; }
    .breadcrumb a:hover { color:#1565c0; }
    .breadcrumb-sep { color:#c5d8e8; }

    /* Page header */
    .pg-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; margin-bottom:32px; flex-wrap:wrap; }
    .pg-title { font-family:'Lora',serif; font-size:1.65rem; font-weight:600; color:#0a2540; letter-spacing:-.3px; line-height:1.2; margin-bottom:5px; }
    .pg-subtitle { font-size:.86rem; color:#6b8aa8; line-height:1.5; }

    /* Status badge */
    .status-badge { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border-radius:24px; font-size:.775rem; font-weight:600; letter-spacing:.2px; white-space:nowrap; flex-shrink:0; margin-top:4px; }
    .status-badge.vuln { background:#fff5f5; border:1.5px solid #fecaca; color:#c0392b; }
    .status-badge.safe { background:#f0fdf6; border:1.5px solid #86efac; color:#15803d; }
    .status-dot { width:7px; height:7px; border-radius:50%; }
    .vuln .status-dot { background:#ef4444; animation:sPulse 2s ease-in-out infinite; }
    .safe .status-dot { background:#22c55e; }
    @keyframes sPulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.5;transform:scale(.8);} }

    /* Main card */
    .main-card { background:#fff; border:1px solid #e2edf5; border-radius:20px; box-shadow:0 1px 3px rgba(10,37,64,.04),0 8px 32px rgba(10,37,64,.06); overflow:hidden; margin-bottom:20px; }

    /* Card header */
    .card-head { padding:20px 28px; border-bottom:1px solid #f0f6fb; display:flex; align-items:center; justify-content:space-between; gap:12px; background:linear-gradient(180deg,#fafcff 0%,#ffffff 100%); }
    .card-head-left { display:flex; align-items:center; gap:12px; }
    .card-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .card-icon.red   { background:linear-gradient(135deg,#fff0f0,#fee2e2); color:#dc2626; }
    .card-icon.green { background:linear-gradient(135deg,#f0fdf4,#dcfce7); color:#16a34a; }
    .card-head-title { font-family:'Lora',serif; font-size:1rem; font-weight:600; color:#0d2137; }
    .card-head-sub   { font-size:.76rem; color:#8fa8bf; margin-top:2px; }

    /* Alert strips */
    .alert-strip { display:flex; gap:14px; align-items:flex-start; padding:16px 20px; border-radius:12px; margin-bottom:20px; font-size:.82rem; line-height:1.65; }
    .strip-icon { flex-shrink:0; width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:1rem; margin-top:1px; }
    .strip-body strong { display:block; margin-bottom:4px; font-size:.84rem; font-weight:700; }
    .strip-body code { font-family:'JetBrains Mono',monospace; font-size:.74rem; padding:1px 6px; border-radius:4px; font-weight:500; }

    .strip-vuln { background:#fffbf0; border:1px solid #fde68a; border-left:3px solid #f59e0b; }
    .strip-vuln .strip-icon { background:#fef3c7; color:#b45309; }
    .strip-vuln .strip-body { color:#78350f; }
    .strip-vuln code { background:rgba(180,83,9,.08); color:#92400e; }

    .strip-safe { background:#f7fdf9; border:1px solid #bbf7d0; border-left:3px solid #22c55e; }
    .strip-safe .strip-icon { background:#dcfce7; color:#15803d; }
    .strip-safe .strip-body { color:#14532d; }
    .strip-safe code { background:rgba(21,128,61,.08); color:#166534; }

    /* Input section */
    .input-section { padding:24px 28px; }
    .input-lbl { display:block; font-size:.73rem; font-weight:700; color:#4a6a84; text-transform:uppercase; letter-spacing:.6px; margin-bottom:9px; }
    .input-row { display:flex; gap:10px; align-items:stretch; }
    .input-field-wrap { flex:1; position:relative; display:flex; align-items:center; }
    .input-field-icon { position:absolute; left:15px; color:#9ab0c4; pointer-events:none; transition:color .2s; }
    .file-input {
      width:100%; padding:12px 16px 12px 44px;
      border:1.5px solid #dde8f2; border-radius:12px;
      background:#f8fbff;
      font-family:'JetBrains Mono',monospace;
      font-size:.84rem; color:#0d2137; outline:none;
      transition:all .2s cubic-bezier(.4,0,.2,1);
    }
    .file-input::placeholder { color:#b0c4d4; font-family:'Plus Jakarta Sans',sans-serif; font-size:.83rem; }
    .file-input:focus { border-color:#1e88e5; background:#fff; box-shadow:0 0 0 4px rgba(30,136,229,.09); }
    .input-field-wrap:focus-within .input-field-icon { color:#1565c0; }

    /* Buttons */
    .btn-load {
      display:inline-flex; align-items:center; gap:8px; padding:12px 22px;
      background:linear-gradient(135deg,#0f3460 0%,#1565c0 100%);
      color:#fff; border:none; border-radius:12px;
      font-family:'Plus Jakarta Sans',sans-serif; font-size:.875rem; font-weight:600;
      cursor:pointer; white-space:nowrap;
      box-shadow:0 3px 12px rgba(21,101,192,.28);
      transition:all .2s cubic-bezier(.4,0,.2,1);
    }
    .btn-load:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(21,101,192,.38); }
    .btn-load:active { transform:translateY(0) scale(.98); }
    .btn-clear {
      display:inline-flex; align-items:center; gap:7px; padding:12px 18px;
      background:transparent; color:#6b8aa8; border:1.5px solid #dde8f2; border-radius:12px;
      font-family:'Plus Jakarta Sans',sans-serif; font-size:.875rem; font-weight:500;
      cursor:pointer; text-decoration:none; white-space:nowrap; transition:all .2s;
    }
    .btn-clear:hover { background:#f0f6fb; border-color:#c5d8e8; color:#4a6a84; }

    /* Example chips */
    .chips-row { display:flex; gap:7px; flex-wrap:wrap; margin-top:14px; align-items:center; }
    .chips-label { font-size:.71rem; color:#9ab0c4; font-weight:700; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
    .chip {
      padding:4px 11px; border-radius:20px;
      background:#f0f6fb; border:1px solid #dde8f2;
      font-family:'JetBrains Mono',monospace;
      font-size:.71rem; color:#4a6a84;
      cursor:pointer; transition:all .15s; white-space:nowrap;
    }
    .chip:hover { background:#e3f0ff; border-color:#93c5fd; color:#1565c0; }
    .chip.danger:hover { background:#fff5f5; border-color:#fca5a5; color:#dc2626; }

    /* Whitelist pills */
    .wl-pills { display:flex; gap:7px; flex-wrap:wrap; margin-top:7px; }
    .wl-pill { padding:3px 11px; border-radius:20px; background:#f0fdf4; border:1px solid #bbf7d0; font-family:'JetBrains Mono',monospace; font-size:.71rem; color:#15803d; font-weight:500; }

    /* Error box */
    .error-box { display:flex; align-items:flex-start; gap:12px; padding:15px 20px; border-radius:12px; background:#fff8f8; border:1px solid #fecaca; border-left:3px solid #ef4444; margin-bottom:16px; }
    .error-box svg { color:#ef4444; flex-shrink:0; margin-top:1px; }
    .error-box p { font-size:.84rem; color:#7f1d1d; line-height:1.5; }

    /* Output card */
    .output-card { background:#fff; border:1px solid #e2edf5; border-radius:20px; box-shadow:0 1px 3px rgba(10,37,64,.04),0 8px 32px rgba(10,37,64,.06); overflow:hidden; animation:slideUp .3s cubic-bezier(.22,1,.36,1) both; }
    @keyframes slideUp { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:translateY(0);} }

    .output-head { padding:14px 22px; background:#0a1f35; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .output-dots { display:flex; gap:5px; }
    .dot { width:11px; height:11px; border-radius:50%; }
    .dot-r{background:#ff5f57;} .dot-y{background:#febc2e;} .dot-g{background:#28c840;}
    .output-fname { font-family:'JetBrains Mono',monospace; font-size:.77rem; color:#7ec8c0; font-weight:500; margin-left:6px; }
    .output-size { font-size:.71rem; color:#4a6a84; background:rgba(255,255,255,.06); padding:3px 10px; border-radius:20px; }
    .output-pre { background:#0d2137; color:#c8dce8; padding:24px 28px; margin:0; font-family:'JetBrains Mono',monospace; font-size:.77rem; line-height:1.8; overflow:auto; max-height:500px; white-space:pre-wrap; word-break:break-word; }

    .divider { height:1px; background:#f0f6fb; }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrap">

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="index.php">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    </a>
    <span class="breadcrumb-sep">›</span>
    <span>Sjablonen</span>
  </div>

  <!-- Page header -->
  <div class="pg-header">
    <div>
      <h1 class="pg-title">Sjablonen laden</h1>
      <p class="pg-subtitle">Laad documentsjablonen voor klinisch gebruik binnen CareConnect</p>
    </div>

  </div>

  <!-- Main card -->
  <div class="main-card">



    <div class="divider"></div>

    <div class="input-section">

      <!-- Alert strip -->
      <!-- <?php if ($USE_VULNERABLE_PATH): ?>
      <div class="alert-strip strip-vuln">
        <div class="strip-icon">⚠️</div>
        <div class="strip-body">
          <strong>Path Traversal kwetsbaarheid actief — <code>$USE_VULNERABLE_PATH = true</code> in template.php</strong>
          Geen padvalidatie aanwezig. Probeer <code>../../../../etc/passwd</code> of lees broncode via
          <code>php://filter/convert.base64-encode/resource=consts.php</code>.
          Verander naar <strong>false</strong> om de whitelist te activeren.
        </div>
      </div>
      <?php else: ?> -->
     
      <?php endif; ?>

      <!-- Form -->
      <form method="GET" action="template.php">
        <label class="input-lbl" for="fileload">Bestandspad of stream wrapper</label>
        <div class="input-row">
          <div class="input-field-wrap">
            <svg class="input-field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <input
              class="file-input"
              type="text"
              id="fileload"
              name="load"
              value="<?= htmlspecialchars($load) ?>"
              placeholder="<?= $USE_VULNERABLE_PATH ? 'bijv. loadme.php  of  ../../../../etc/passwd' : 'Kies uit whitelist: '.implode(', ', $WHITELIST) ?>"
              autocomplete="off"
            >
          </div>
          <button type="submit" class="btn-load">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Laden
          </button>
          <?php if ($load): ?>
          <a href="template.php" class="btn-clear">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Wissen
          </a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Quick chips -->
      <?php if ($USE_VULNERABLE_PATH): ?>
      <!-- <div class="chips-row">
        <span class="chips-label">Voorbeelden:</span>
        <span class="chip" onclick="setInput('loadme.php')">loadme.php</span>
        <span class="chip" onclick="setInput('common.php')">common.php</span>
        <span class="chip" onclick="setInput('consts.php')">consts.php</span>
        <span class="chip danger" onclick="setInput('php://filter/convert.base64-encode/resource=consts.php')">php://filter base64</span>
        <span class="chip danger" onclick="setInput('../../../../etc/passwd')">/etc/passwd</span>
      </div> -->
      <?php else: ?>

        
      <div class="chips-row">
        <span class="chips-label">Toegestaan:</span>
        <?php foreach ($WHITELIST as $w): ?>
        <span class="chip" onclick="setInput('<?= htmlspecialchars($w) ?>')"><?= htmlspecialchars($w) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Error -->
  <?php if ($err): ?>
  <div class="error-box">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <p><?= $err ?></p>
  </div>
  <?php endif; ?>

  <!-- Output -->
  <?php if ($content !== ''): ?>
  <div class="output-card">
    <div class="output-head">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="output-dots">
          <div class="dot dot-r"></div>
          <div class="dot dot-y"></div>
          <div class="dot dot-g"></div>
        </div>
        <span class="output-fname"><?= htmlspecialchars($load) ?></span>
      </div>
      <span class="output-size"><?= number_format(strlen($content)) ?> bytes</span>
    </div>
    <pre class="output-pre"><?= htmlspecialchars($content) ?></pre>
  </div>
  <?php endif; ?>

</div>

<script>
  function setInput(val) {
    document.getElementById('fileload').value = val;
    document.getElementById('fileload').focus();
  }
  document.getElementById('hamburger').addEventListener('click', () => {
    document.getElementById('mainNav').classList.toggle('open');
  });
</script>
</body>
</html>