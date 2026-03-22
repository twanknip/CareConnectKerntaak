<?php
require_once 'common.php';
require_once 'dbfuncs.php';

if (empty($_SESSION['authed'])) {
    header('Location: login.php');
    exit;
}

$activePage  = 'messages';
$userId      = $_SESSION['userid'];  // define this FIRST

// Handle delete all messages
if (isset($_POST['delete_all'])) {
    insertQuery("delete from messages where user_id = " . $userId);
    header('Location: messages.php');
    exit;
}

// Handle send
$sendSuccess = false;
$sendError   = false;
$activeTab   = $_GET['tab'] ?? 'inbox';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_REQUEST['user']) && !empty($_REQUEST['subject']) && !empty($_REQUEST['message'])) {
        // VULNERABLE: SQL injection + stored XSS intentional
        $msgSQL   = "insert into messages(user_id, subject, message) values('" .
                    $_REQUEST['user'] . "','" . $_REQUEST['subject'] . "','" . $_REQUEST['message'] . "')";
        $inserted = insertQuery($msgSQL);
        $sendSuccess = $inserted !== false;
        $sendError   = !$sendSuccess;
    }
    $activeTab = 'send';
}

// Fetch inbox
// VULNERABLE: SQL injection intentional
$msgQuery = "select * from messages where user_id = " . $userId;
$messages = getSelect($msgQuery);

// Fetch users for dropdown
$userList = getSelect("select id, firstname, surname from users");
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CareConnect — Berichten</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrap">

  <div class="page-title-row">
    <div class="page-title">
      <h1>Berichten</h1>
      <p>Inbox en berichtenoverzicht voor uw account</p>
    </div>
  </div>

  <div class="card">

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab-btn <?= $activeTab==='inbox' ? 'active':'' ?>" onclick="switchTab('inbox')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
        Inbox
        <?php if ($messages): ?>
        <span style="background:#1565c0;color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:2px;"><?= count($messages) ?></span>
        <?php endif; ?>
      </button>
      <button class="tab-btn <?= $activeTab==='send' ? 'active':'' ?>" onclick="switchTab('send')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Bericht versturen
      </button>
    </div>

    <!-- Inbox tab -->
    <div class="tab-panel <?= $activeTab==='inbox' ? 'active':'' ?>" id="tab-inbox">
      <div class="vuln-strip">
        <strong>⚠️</strong>
voor spoedige gevallen contact opnemen met de patient.      </div>

      <form method="POST" style="margin-bottom:16px;">
    <button type="submit" name="delete_all" class="btn btn-ghost btn-sm" 
            onclick="return confirm('Alle berichten verwijderen?')"
            style="border-color:#fecaca;color:#dc2626;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        Alle berichten verwijderen
    </button>
</form>

      <?php if ($messages): ?>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <?php foreach ($messages as $i => $msg): ?>
          <div style="border:1px solid #eef4f9;border-radius:14px;overflow:hidden;animation:fadeUp .3s ease <?= $i*0.07 ?>s both;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:#fafcff;border-bottom:1px solid #eef4f9;">
              <div style="font-weight:600;font-size:.95rem;color:#0a2540;">
                <?= $msg[2] /* VULNERABLE: no htmlspecialchars - XSS intentional */ ?>
              </div>
              <span class="badge badge-gray">Bericht #<?= $msg[0] ?></span>
            </div>
            <div style="padding:16px 20px;font-size:.88rem;color:#2c4a65;line-height:1.7;white-space:pre-wrap;">
              <?= $msg[3] /* VULNERABLE: stored XSS intentional */ ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
          <p class="empty-title">Geen berichten</p>
          <p>Uw inbox is leeg</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Send tab -->
    <div class="tab-panel <?= $activeTab==='send' ? 'active':'' ?>" id="tab-send">
      <div class="vuln-strip">
        <strong>⚠️</strong>
        Stuur bericht naar patienten
      </div>

      <?php if ($sendSuccess): ?>
      <div class="alert alert-success" style="margin-bottom:20px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Bericht succesvol verstuurd! Bekijk het in de <button onclick="switchTab('inbox')" style="background:none;border:none;color:#166534;font-weight:700;cursor:pointer;text-decoration:underline;font-family:inherit;font-size:inherit;">Inbox</button>.
      </div>
      <?php endif; ?>
      <?php if ($sendError): ?>
      <div class="alert alert-error" style="margin-bottom:20px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Kon het bericht niet versturen.
      </div>
      <?php endif; ?>

      <form method="POST" action="messages.php">
        <div class="field">
          <label class="field-label">Ontvanger</label>
          <select class="field-input" name="user">
            <?php if ($userList): foreach ($userList as $u): ?>
            <option value="<?= $u[0] ?>"><?= htmlspecialchars($u[1] . ' ' . $u[2]) ?></option>
            <?php endforeach; endif; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label">Onderwerp</label>
          <input class="field-input" type="text" name="subject" placeholder="Onderwerp van uw bericht" required>
        </div>

        <div class="field">
          <label class="field-label">Bericht</label>
          <textarea class="field-input" name="message" placeholder="Typ hier uw bericht..." required></textarea>
          <!-- <div style="background:#f0f7ff;border:1px solid #dde8f0;border-radius:10px;padding:12px 14px;margin-top:8px;font-size:.78rem;color:#6b8aa8;">
            <strong style="color:#1565c0;display:block;margin-bottom:6px;">🧪 XSS testpayloads — plak dit in het berichtveld:</strong>
            Simpele popup: <code style="background:#dde8f0;padding:2px 6px;border-radius:4px;font-size:.74rem;">&lt;script&gt;alert("XSS!")&lt;/script&gt;</code><br>
            Cookie tonen: <code style="background:#dde8f0;padding:2px 6px;border-radius:4px;font-size:.74rem;">&lt;script&gt;alert("Cookie: "+document.cookie)&lt;/script&gt;</code><br>
            Pagina vervormen: <code style="background:#dde8f0;padding:2px 6px;border-radius:4px;font-size:.74rem;">&lt;h1 style="color:red"&gt;GEHACKT&lt;/h1&gt;</code>
          </div> -->
        </div>

        <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Versturen
          </button>
          <button type="button" class="btn btn-ghost" onclick="switchTab('inbox')">← Terug naar inbox</button>
        </div>
      </form>
    </div>

  </div>
</div>

<style>
@keyframes fadeUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
</style>
<script>
  function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach((b,i)=>{
      b.classList.toggle('active', ['inbox','send'][i]===name);
    });
    document.querySelectorAll('.tab-panel').forEach(p=>{
      p.classList.toggle('active', p.id==='tab-'+name);
    });
  }
  document.getElementById('hamburger').addEventListener('click',()=>{
    document.getElementById('mainNav').classList.toggle('open');
  });
</script>
</body>
</html>
