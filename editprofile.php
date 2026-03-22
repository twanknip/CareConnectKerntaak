<?php
require_once 'common.php';
require_once 'dbfuncs.php';

if (empty($_SESSION['authed'])) { header('Location: login.php'); exit; }

$activePage = 'profile';
$success = false;
$userId  = $_SESSION['userid'] ?? null;

/* ════════════════════════════════════════════════════════════════════
   SQL INJECTIE KWETSBAARHEID — PROFIEL BEWERKEN (UPDATE)

   ⚠️  KWETSBARE modus (true):
   Probeer in het voornaamveld: test', surname='hacked' #
   Probeer ook:                 x' WHERE id=2 #
   Resultaat: andere velden of andere gebruikers worden aangepast

   ✅  VEILIGE modus (false):
   Zelfde invoer → prepared statement, geen injectie mogelijk
════════════════════════════════════════════════════════════════════ */

$USE_VULNERABLE_PROFILE = false;  // ← VERANDER DIT OM TE SCHAKELEN
                                  // true  = KWETSBAAR (SQL injectie mogelijk)
                                  // false = VEILIG (prepared statements)

// DEBUG MODE — zet op true om de SQL query te zien voor verzending
$DEBUG_SQL = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userId) {
    $firstname = $_REQUEST['firstname'];
    $surname   = $_REQUEST['surname'];
    $email     = $_REQUEST['email'];

    if ($USE_VULNERABLE_PROFILE) {
        // ⚠️  KWETSBAAR: directe string samenvoeging — SQL INJECTIE MOGELIJK
        $query = "UPDATE users SET firstname='" . $firstname .
                 "', surname='" . $surname .
                 "', email='" . $email .
                 "' WHERE id=" . $userId;

        if ($DEBUG_SQL) {
            echo "<pre style='background:#1a1a2e;color:#00ff88;padding:20px;border-radius:8px;font-family:monospace;font-size:.85rem;margin:20px;'>";
            echo "DEBUG SQL:\n" . $query;
            echo "</pre>";
            die;
        }

        $result  = insertQuery($query);
        $success = $result !== false;
    } else {
        // ✅  VEILIG: prepared statement — invoer als data behandeld
        global $con; if ($con === null) connect();
        $stmt = mysqli_prepare($con, "UPDATE users SET firstname=?, surname=?, email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $firstname, $surname, $email, $userId);
        $success = mysqli_stmt_execute($stmt);
    }
}

$user = null;
if ($userId) {
    $rows = getSelect("SELECT * FROM users WHERE id=" . $userId);
    $user = $rows ? $rows[0] : null;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CareConnect — Mijn Profiel</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrap">

  <div class="breadcrumb">
    <a href="index.php">Home</a>
    <span class="breadcrumb-sep">›</span>
    <span>Mijn Profiel</span>
  </div>

  <div class="page-title-row">
    <div class="page-title">
      <h1>Mijn Profiel</h1>
      <p>Beheer uw accountgegevens</p>
    </div>
    <span class="status-pill <?= $USE_VULNERABLE_PROFILE ? 'pill-vuln' : 'pill-safe' ?>">
    </span>
  </div>

  <?php if (!$userId): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:52px;">
    <p style="color:#6b8aa8;margin-bottom:16px;">U bent niet ingelogd.</p>
    <a href="login.php" class="btn btn-primary">Inloggen</a>
  </div></div>

  <?php else: ?>

  <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;">

    <!-- Profile sidebar -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:32px 24px;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#0f3460,#00796b);display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Lora',serif;font-size:1.8rem;margin:0 auto 16px;">
          <?= strtoupper(substr($user[1] ?? 'U', 0, 1)) ?>
        </div>
        <div style="font-family:'Lora',serif;font-size:1.1rem;font-weight:600;color:#0a2540;margin-bottom:4px;"><?= htmlspecialchars(($user[3] ?? '') . ' ' . ($user[4] ?? '')) ?></div>
        <div style="font-size:.83rem;color:#6b8aa8;margin-bottom:12px;">@<?= htmlspecialchars($user[1] ?? '') ?></div>
        <span class="badge badge-teal">Medewerker</span>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #eef4f9;">
          <div style="font-size:.78rem;color:#9ab0c4;margin-bottom:4px;">Account ID</div>
          <span class="badge badge-blue">#<?= $user[0] ?></span>
        </div>
      </div>
    </div>

    <!-- Edit form -->
    <div class="card">
      <div class="card-header-bar">
        <h2>Gegevens bewerken</h2>
      </div>
      <div class="card-body">

        <?php if ($USE_VULNERABLE_PROFILE): ?>
       
        <?php else: ?>
        <div class="secure-strip">
        
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Profiel succesvol bijgewerkt!
        </div>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label class="field-label">Voornaam</label>
            <input class="field-input" type="text" name="firstname" value="<?= htmlspecialchars($user[3] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="field-label">Achternaam</label>
            <input class="field-input" type="text" name="surname" value="<?= htmlspecialchars($user[4] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="field-label">E-mailadres</label>
            <!-- type="text" ipv "email" zodat SQL payloads niet door de browser worden geblokkeerd -->
            <input class="field-input" type="text" name="email" value="<?= htmlspecialchars($user[5] ?? '') ?>" required>
          </div>
          <div style="display:flex;gap:12px;margin-top:24px;">
            <button type="submit" class="btn btn-primary">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Opslaan
            </button>
            <a href="index.php" class="btn btn-ghost">Annuleren</a>
          </div>
        </form>

        <?php if ($USE_VULNERABLE_PROFILE): ?>
       
        <?php endif; ?>

      </div>
    </div>

  </div>
  <?php endif; ?>

</div>

<script>
  document.getElementById('hamburger').addEventListener('click',()=>{
    document.getElementById('mainNav').classList.toggle('open');
  });
</script>
</body>
</html>