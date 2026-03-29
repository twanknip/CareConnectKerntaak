<?php
require_once 'common.php';
require_once 'dbfuncs.php';

if (empty($_SESSION['authed'])) {  // Controleert of gebruiker ingelogd is
  header('Location: login.php');    // Stuur naar login pagina
  exit;                             // Stop het script
}

$currentUserRole = getSelect("SELECT role FROM users WHERE id = " . (int)$_SESSION['userid']);  // Haal rol van huidige gebruiker
$isAdmin = $currentUserRole && (int)$currentUserRole[0][0] === 1;  // Controleert of gebruiker admin is (role = 1)

$activePage = 'users';  // Markeer deze pagina als "users"

/* SQL INJECTIE KWETSBAARHEID — GEBRUIKERSZOEKOPDRACHT
     KWETSBAAR (true): Probeer ' OR '1'='1' of 1 or 1=1
     VEILIG (false): Zelfde invoer wordt als tekst behandeld */

$USE_VULNERABLE_SEARCH = false;  // Toggle: true = kwetsbaar, false = veilig

/* IDOR KWETSBAARHEID — MEDISCHE DATA OPHALEN
    KWETSBAAR (true): Elke ingelogde gebruiker kan andermans data zien
    VEILIG (false): Alleen admins mogen medische data bekijken */

$USE_VULNERABLE_IDOR = false;  // Toggle: true = kwetsbaar, false = veilig

if (isset($_GET['medical_ajax']) && isset($_GET['uid'])) {  // Controleert of AJAX medische data request is
  header('Content-Type: application/json');  // Zeg tegen browser dat het JSON is

  $currentUserRole = getSelect("SELECT role FROM users WHERE id = " . (int)$_SESSION['userid']);  // Haal rol van gebruiker
  if (!$currentUserRole || (int)$currentUserRole[0][0] !== 1) {  // Als geen rol of niet admin
    echo json_encode(['error' => 'Toegang geweigerd.']);  // Stuur fout
    exit;  // Stop script
  }

  $uid = $_GET['uid'];  // Pak user ID uit URL

  if ($USE_VULNERABLE_IDOR) {  // Als IDOR kwetsbaarheid ingeschakeld
    //   KWETSBAAR: geen rol-controle, elke gebruiker kan dit opvragen
    $medData = getSelect("SELECT * FROM medical_data WHERE user_id = " . $uid);  // Haal medische data op (GEEN bescherming tegen SQL injectie!)
    $userData = getSelect("SELECT id, username, firstname, surname, email, role FROM users WHERE id = " . $uid);  // Haal gebruikerdata op
  } else {  // VEILIGE modus
    //  VEILIG: controleer of gebruiker admin is
    $currentUser = getSelect("SELECT role FROM users WHERE id = " . (int)$_SESSION['userid']);  // Haal huidige gebruiker rol
    if (!$currentUser || $currentUser[0][0] != 1) {  // Als niet admin
      echo json_encode(['error' => 'Toegang geweigerd. Alleen beheerders mogen medische gegevens inzien.']);  // Stuur fout
      exit;  // Stop script
    }
    $safeUid = (int)$uid;  // Zet UID veilig om naar getal
    $medData  = getSelect("SELECT * FROM medical_data WHERE user_id = " . $safeUid);  // Haal medische data op (veilig)
    $userData = getSelect("SELECT id, username, firstname, surname, email, role FROM users WHERE id = " . $safeUid);  // Haal gebruikerdata op (veilig)
  }

  echo json_encode([  // Stuur JSON terug
    'user'    => $userData ? $userData[0] : null,  // Gebruikersinformatie
    'medical' => $medData  ? $medData[0]  : null,  // Medische gegevens
    'vulnerable' => $USE_VULNERABLE_IDOR,  // Is het kwetsbaar?
  ]);
  exit;  // Stop script
}

$getUser = $_REQUEST["username"] ?? '';  // Pak gebruikersnaam uit formulier, of zet op leeg
$getId   = $_REQUEST["id"] ?? '';        // Pak ID uit formulier, of zet op leeg
$query   = '';                            // Maak lege query variabele
$results = null;                          // Zet resultaten op null

if (!empty($getUser)) {  // Als gebruiker op username heeft gezocht
  if ($USE_VULNERABLE_SEARCH) {  // Als kwetsbare modus ingeschakeld
    //  KWETSBAAR: directe string samenvoeging — SQL INJECTIE MOGELIJK
    $query   = "SELECT * FROM users WHERE username = '" . $getUser . "'";  // Bouw query met invoer erin (GEEN bescherming!)
    $results = getSelect($query);  // Voer query uit
  } else {  // VEILIGE modus
    //  VEILIG: prepared statement — invoer wordt als data behandeld
    global $con;  // Gebruik globale database connectie
    if ($con === null) connect();  // Maak connectie aan als nodig
    $stmt = mysqli_prepare($con, "SELECT * FROM users WHERE username = ?");  // Bereid query voor met placeholder
    mysqli_stmt_bind_param($stmt, "s", $getUser);  // Voeg gebruikersnaam veilig in
    mysqli_stmt_execute($stmt);  // Voer query uit
    $res = mysqli_stmt_get_result($stmt);  // Haal resultaten op
    $query = "SELECT * FROM users WHERE username = ? (prepared statement)";  // Sla query op
    if ($res && mysqli_num_rows($res) > 0) {  // Als er resultaten zijn
      while ($row = mysqli_fetch_row($res))  // Voor elke rij
        $results[] = $row;  // Voeg rij toe aan array
    }
  }
} elseif (!empty($getId)) {  // Anders, als gebruiker op ID heeft gezocht
  if ($USE_VULNERABLE_SEARCH) {  // Als kwetsbare modus ingeschakeld
    //  KWETSBAAR: geen validatie op ID — SQL INJECTIE MOGELIJK
    $query   = "SELECT * FROM users WHERE id = " . $getId;  // Bouw query met invoer erin (GEEN bescherming!)
    $results = getSelect($query);  // Voer query uit
  } else {  // VEILIGE modus
    //  VEILIG: cast naar integer — alleen een getal is mogelijk
    $safeId  = (int) $getId;  // Zet ID veilig om naar getal
    $query   = "SELECT * FROM users WHERE id = {$safeId} (integer cast)";  // Sla query op
    $results = getSelect("SELECT * FROM users WHERE id = " . $safeId);  // Voer veilige query uit
  }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CareConnect — Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    @keyframes fadeUp { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }

    /* ── Modal overlay ──────────────────────────────────────── */
    .modal-overlay {
      position: fixed; inset: 0; z-index: 1000;
      background: rgba(10,37,64,0.55);
      backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.25s cubic-bezier(0.4,0,0.2,1);
    }
    .modal-overlay.open {
      opacity: 1; pointer-events: all;
    }

    /* ── Modal card ─────────────────────────────────────────── */
    .modal {
      background: #fff;
      border-radius: 20px;
      width: 100%; max-width: 580px;
      max-height: 88vh;
      overflow-y: auto;
      box-shadow: 0 24px 80px rgba(10,37,64,0.25), 0 4px 16px rgba(10,37,64,0.12);
      transform: translateY(20px) scale(0.97);
      transition: transform 0.28s cubic-bezier(0.22,1,0.36,1);
    }
    .modal-overlay.open .modal {
      transform: translateY(0) scale(1);
    }

    /* Modal header */
    .modal-head {
      padding: 22px 26px 18px;
      border-bottom: 1px solid #eef4f9;
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
      position: sticky; top: 0; background: #fff; z-index: 1;
      border-radius: 20px 20px 0 0;
    }
    .modal-head-left { display: flex; align-items: center; gap: 12px; }
    .modal-avatar {
      width: 46px; height: 46px; border-radius: 50%;
      background: linear-gradient(135deg, #0f3460, #00796b);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-family: 'Lora', serif; font-size: 1.2rem; flex-shrink: 0;
    }
    .modal-name { font-family: 'Lora', serif; font-size: 1.1rem; font-weight: 600; color: #0a2540; }
    .modal-username { font-size: .78rem; color: #6b8aa8; margin-top: 2px; }
    .modal-close {
      width: 32px; height: 32px; border-radius: 8px;
      background: #f0f6fb; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #6b8aa8; flex-shrink: 0; transition: all 0.15s;
    }
    .modal-close:hover { background: #fef2f2; color: #dc2626; }

    /* Modal body */
    .modal-body { padding: 22px 26px 26px; }

    /* IDOR warning inside modal */
    .modal-idor-strip {
      background: #fff8e1; border: 1px solid #fde68a;
      border-left: 3px solid #f59e0b;
      border-radius: 0 8px 8px 0;
      padding: 9px 13px; font-size: .78rem; color: #78350f;
      margin-bottom: 18px;
    }
    .modal-idor-strip.safe {
      background: #f0fdf4; border-color: #86efac;
      border-left-color: #22c55e; color: #14532d;
    }
    .modal-idor-strip strong { display: block; margin-bottom: 2px; font-size: .8rem; }
    .modal-idor-strip code { font-family: monospace; font-size: .73rem; background: rgba(0,0,0,.06); padding: 1px 5px; border-radius: 3px; }

    /* Section headings inside modal */
    .modal-section { margin-bottom: 20px; }
    .modal-section-title {
      font-size: .7rem; font-weight: 700; color: #9ab0c4;
      text-transform: uppercase; letter-spacing: .6px;
      margin-bottom: 10px; display: flex; align-items: center; gap: 7px;
    }
    .modal-section-title::after { content:''; flex:1; height:1px; background:#eef4f9; }

    /* Data grid */
    .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .data-item {
      background: #f8fbff; border: 1px solid #eef4f9;
      border-radius: 10px; padding: 11px 14px;
    }
    .data-item.full { grid-column: 1 / -1; }
    .data-item.danger { background: #fff5f5; border-color: #fecaca; }
    .data-label { font-size: .7rem; font-weight: 700; color: #9ab0c4; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
    .data-value { font-size: .88rem; color: #0a2540; font-weight: 500; line-height: 1.4; }
    .data-value.sensitive { color: #dc2626; font-family: monospace; font-size: .82rem; }

    /* Blood type badge */
    .blood-badge {
      display: inline-flex; align-items: center; justify-content: center;
      width: 48px; height: 48px; border-radius: 50%;
      background: linear-gradient(135deg, #dc2626, #b91c1c);
      color: #fff; font-weight: 700; font-size: 1rem;
      font-family: 'Lora', serif; margin-bottom: 4px;
    }

    /* Role badge */
    .role-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 10px; border-radius: 20px;
      font-size: .72rem; font-weight: 700;
    }
    .role-admin { background: #e0f2fe; color: #0369a1; }
    .role-user  { background: #f0fdf4; color: #15803d; }

    /* Loading state */
    .modal-loading {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; padding: 48px 24px; gap: 14px;
      color: #9ab0c4;
    }
    .spinner {
      width: 36px; height: 36px; border-radius: 50%;
      border: 3px solid #eef4f9; border-top-color: #1565c0;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* No medical data state */
    .no-medical {
      text-align: center; padding: 28px 16px; color: #9ab0c4;
    }
    .no-medical svg { margin-bottom: 10px; opacity: .4; }
    .no-medical p { font-size: .85rem; }

    /* View data button on result cards */
    .btn-view-data {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 8px;
      background: linear-gradient(135deg, #e0f2fe, #dbeafe);
      border: 1px solid #93c5fd; color: #1565c0;
      font-size: .78rem; font-weight: 600; cursor: pointer;
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: all 0.15s; white-space: nowrap;
    }
    .btn-view-data:hover {
      background: linear-gradient(135deg, #1565c0, #0f3460);
      border-color: #1565c0; color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(21,101,192,.25);
    }

    /* Result card — role indicator dot */
    .role-dot {
      width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
      display: inline-block; margin-right: 5px;
    }
  </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-wrap">

  <!-- ── Tab switcher ─────────────────────────────────────── -->
  <div class="page-tabs">
    <button class="page-tab active" id="tab-dashboard" onclick="switchPageTab('dashboard')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </button>
    <?php if ($isAdmin): ?>
    <button class="page-tab" id="tab-search" onclick="switchPageTab('search')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Gebruikerszoekopdracht
    </button>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1 — DASHBOARD
  ══════════════════════════════════════════════════════════ -->
  <div class="tab-content active" id="content-dashboard">

    <!-- Greeting -->
    <div class="dash-greeting">
      <div class="dash-greeting-left">
        <div class="dash-greeting-title">Goedemorgen, Dr. <?= htmlspecialchars($_SESSION['username'] ?? 'Arts') ?> 👋</div>
        <div class="dash-greeting-sub">Hier is uw overzicht voor vandaag — <?= date('l j F Y') ?></div>
      </div>
      <div class="dash-date-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <div>
          <div class="dash-date-badge-text"><?= date('d-m-Y') ?></div>
          <div class="dash-date-badge-sub"><?= date('H:i') ?> uur</div>
        </div>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon blue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <span class="stat-trend trend-up">↑ 3</span>
        </div>
        <div class="stat-value">24</div>
        <div class="stat-label">Patiënten vandaag</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon teal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          </div>
          <span class="stat-trend trend-down">↓ 2</span>
        </div>
        <div class="stat-value">7</div>
        <div class="stat-label">Opnames actief</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon orange">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <span class="stat-trend trend-neu">→ 0</span>
        </div>
        <div class="stat-value">12</div>
        <div class="stat-label">Verwijzingen open</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon red">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <span class="stat-trend trend-down">↓ 1</span>
        </div>
        <div class="stat-value">3</div>
        <div class="stat-label">Spoedgevallen</div>
      </div>
    </div>

    <!-- Main grid -->
    <div class="dash-grid">

      <!-- Left column -->
      <div>

        <!-- Nieuws & Aankondigingen -->
        <div class="news-card">
          <div class="news-card-head">
            <h3>📋 Nieuws & Aankondigingen</h3>
            <span class="news-new-badge">3 nieuw</span>
          </div>

          <div class="news-item">
            <div class="news-dot urgent"></div>
            <div>
              <div class="news-item-title">⚠️ Protocol-update: Antibiotica resistentie</div>
              <div class="news-item-body">Het voorschrijfprotocol voor breedspectrum-antibiotica is herzien. Raadpleeg het nieuwe formularium vóór voorschrijven van amoxicilline-combinaties.</div>
              <div class="news-item-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Vandaag, 07:45 uur — Medische staf
              </div>
            </div>
          </div>

          <div class="news-item">
            <div class="news-dot info"></div>
            <div>
              <div class="news-item-title">Nieuwe MRI-scanner operationeel — Afdeling Radiologie</div>
              <div class="news-item-body">De Siemens MAGNETOM Vida 3T is vanaf maandag beschikbaar. Verwijzingen via het KIS-portaal. Prioriteit voor neurologische en oncologische patiënten.</div>
              <div class="news-item-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Gisteren, 16:20 uur — Radiologie
              </div>
            </div>
          </div>

          <div class="news-item">
            <div class="news-dot warn"></div>
            <div>
              <div class="news-item-title">Onderhoud EPD-systeem: zondag 02:00–06:00 uur</div>
              <div class="news-item-body">Het elektronisch patiëntendossier is tijdelijk niet beschikbaar. Gebruik het noodformulier (papier) voor dringende aantekeningen. Back-up procedures zijn van toepassing.</div>
              <div class="news-item-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                2 dagen geleden — ICT Afdeling
              </div>
            </div>
          </div>

          <div class="news-item">
            <div class="news-dot success"></div>
            <div>
              <div class="news-item-title">Accreditatie JCI verlengd — CareConnect gecertificeerd t/m 2027</div>
              <div class="news-item-body">Na een succesvolle inspectie heeft CareConnect de JCI-accreditatie voor kwaliteit en patiëntveiligheid ontvangen. Hartelijk dank aan alle medewerkers.</div>
              <div class="news-item-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                3 dagen geleden — Directie
              </div>
            </div>
          </div>

          <div class="news-item">
            <div class="news-dot info"></div>
            <div>
              <div class="news-item-title">Griepvaccinatie medewerkers — aanmelden vóór vrijdag</div>
              <div class="news-item-body">De jaarlijkse griepvaccinatieronde voor medewerkers start volgende week. Meld u aan via de intranetpagina of bij de bedrijfsarts op kamer 214.</div>
              <div class="news-item-time">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                4 dagen geleden — HR & Bedrijfszorg
              </div>
            </div>
          </div>
        </div>

        <!-- Recente patiënten -->
        <div class="news-card">
          <div class="news-card-head">
            <h3>🏥 Recente patiënten</h3>
          </div>
          <?php
          $recentPatients = [
            ['M. van den Berg',   '62 jaar', 'Cardiologie',   'Stabiel',   '#0f3460,#1565c0', 'status-stabiel'],
            ['S. Jansen',         '34 jaar', 'Interne geneesk.', 'Controle', '#00796b,#00acc1', 'status-controle'],
            ['R. de Vries',       '78 jaar', 'Neurologie',    'Opname',    '#dc2626,#b91c1c', 'status-opname'],
            ['F. Bakker',         '45 jaar', 'Orthopedie',    'Ontslagen', '#6b8aa8,#9ab0c4', 'status-ontslagen'],
            ['L. Smit',           '29 jaar', 'Gynaecologie',  'Stabiel',   '#7c3aed,#9333ea', 'status-stabiel'],
          ];
          foreach ($recentPatients as $p): ?>
          <div class="patient-row">
            <div class="patient-avatar-sm" style="background:linear-gradient(135deg,<?= $p[4] ?>);">
              <?= strtoupper(substr($p[0], 0, 1)) ?>
            </div>
            <div>
              <div class="patient-name"><?= $p[0] ?></div>
              <div class="patient-detail"><?= $p[1] ?> · <?= $p[2] ?></div>
            </div>
            <span class="patient-status <?= $p[5] ?>"><?= $p[3] ?></span>
          </div>
          <?php endforeach; ?>
        </div>

      </div>

      <!-- Right column -->
      <div>

        <!-- Agenda vandaag -->
        <div class="agenda-card">
          <div class="agenda-head">
            <h3>📅 Agenda vandaag</h3>
          </div>
          <?php
          $agenda = [
            ['08:30', 'Intake consult',      'Dhr. T. Hermans',      'type-consult',  'Consult'],
            ['09:00', 'Bloeddruk controle',  'Mw. A. Pieters',       'type-controle', 'Controle'],
            ['09:30', 'Telefonisch consult', 'Dhr. K. Visser',       'type-telefo',   'Telefoon'],
            ['10:15', 'SPOED — Pijnklachten','Mw. R. Mulder',        'type-spoed',    'Spoed'],
            ['11:00', 'Nacontrole operatie', 'Dhr. P. van Dijk',     'type-controle', 'Controle'],
            ['13:30', 'Nieuwe patiënt',      'Mw. F. de Boer',       'type-consult',  'Consult'],
            ['14:00', 'Knieklachten',        'Dhr. B. Janssen',      'type-consult',  'Consult'],
            ['15:30', 'Operatie voorbereiding','Mw. C. Vermeer',     'type-operatie', 'Operatie'],
            ['16:00', 'Multidisciplinair overleg','Afd. Interne',    'type-telefo',   'Overleg'],
          ];
          foreach ($agenda as $i => $a): ?>
          <div class="agenda-item">
            <div class="agenda-time-col">
              <div class="agenda-time"><?= $a[0] ?></div>
              <?php if ($i < count($agenda)-1): ?>
              <div class="agenda-time-line"></div>
              <?php endif; ?>
            </div>
            <div class="agenda-content">
              <div class="agenda-title"><?= $a[1] ?></div>
              <div class="agenda-patient">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?= $a[2] ?>
              </div>
            </div>
            <span class="agenda-type <?= $a[3] ?>"><?= $a[4] ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Snelle acties -->
        <div class="quick-actions">
          <div class="quick-actions-head"><h3>⚡ Snelle acties</h3></div>
          <a href="messages.php" class="quick-action-btn">
            <div class="quick-action-icon" style="background:linear-gradient(135deg,#e3f0ff,#dbeafe);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1565c0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div>
              <div class="quick-action-label">Berichten</div>
              <div class="quick-action-sub">Interne communicatie</div>
            </div>
            <svg class="quick-action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
          <a href="index.php?tab=search" class="quick-action-btn" onclick="switchPageTab('search');return false;">
            <div class="quick-action-icon" style="background:linear-gradient(135deg,#e0f2f0,#ccfbf1);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00796b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div>
              <div class="quick-action-label">Patiënt zoeken</div>
              <div class="quick-action-sub">Gebruikerszoekopdracht</div>
            </div>
            <svg class="quick-action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
          <a href="editprofile.php" class="quick-action-btn">
            <div class="quick-action-icon" style="background:linear-gradient(135deg,#fff3e0,#fed7aa);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d35400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
              <div class="quick-action-label">Mijn profiel</div>
              <div class="quick-action-sub">Accountgegevens bewerken</div>
            </div>
            <svg class="quick-action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
          <a href="security.php" class="quick-action-btn">
            <div class="quick-action-icon" style="background:linear-gradient(135deg,#fff5f5,#fee2e2);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
              <div class="quick-action-label">Beveiliging</div>
              <div class="quick-action-sub">Kwetsbaarheden beheren</div>
            </div>
            <svg class="quick-action-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        </div>

      </div>
    </div>
  </div>
  <!-- end tab-content dashboard -->

  <!-- ══════════════════════════════════════════════════════════
       TAB 2 — GEBRUIKERSZOEKOPDRACHT
  ══════════════════════════════════════════════════════════ -->
  <?php if ($isAdmin): ?>
  <div class="tab-content" id="content-search">

  <div class="page-title-row">
    <div class="page-title">
      <h1>Gebruikerszoekopdracht</h1>
      <p>Zoek medewerkers en patiënten op gebruikersnaam of ID</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <span class="status-pill <?= $USE_VULNERABLE_SEARCH ? 'pill-vuln' : 'pill-safe' ?>">
      </span>
      <span class="status-pill <?= $USE_VULNERABLE_IDOR ? 'pill-vuln' : 'pill-safe' ?>">
      </span>
    </div>
  </div>

  <div class="card" style="margin-bottom:24px;">
    <div class="card-body">

      <?php if ($USE_VULNERABLE_SEARCH): ?>
      <?php else: ?>
      <div class="secure-strip">
        <strong>✅ Prepared statements actief — <code style="font-family:monospace;background:rgba(0,0,0,.06);padding:1px 5px;border-radius:3px;">$USE_VULNERABLE_SEARCH = false</code> in index.php</strong>
        Invoer wordt als data behandeld. Aanvalspayloads leveren geen resultaat op.
      </div>
      <?php endif; ?>

      <?php if ($USE_VULNERABLE_IDOR): ?>
      
      <?php else: ?>
      <div class="secure-strip" style="margin-top:8px;">
        <strong>✅ Rol-controle actief — <code style="font-family:monospace;background:rgba(0,0,0,.06);padding:1px 5px;border-radius:3px;">$USE_VULNERABLE_IDOR = false</code> in index.php</strong>
        Alleen beheerders (role=1) mogen medische gegevens inzien.
      </div>
      <?php endif; ?>

      <form method="GET" action="index.php">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:16px;">
          <div class="field" style="flex:1;min-width:200px;">
            <label class="field-label">Gebruikersnaam</label>
            <div class="input-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input class="field-input with-icon" type="text" name="username" placeholder="bijv. jared" value="<?= htmlspecialchars($getUser) ?>">
            </div>
          </div>
          <div class="field" style="flex:1;min-width:160px;">
            <label class="field-label">Gebruiker ID</label>
            <div class="input-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
              <input class="field-input with-icon" type="text" name="id" placeholder="bijv. 1" value="<?= htmlspecialchars($getId) ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Zoeken
          </button>
          <?php if ($query || $getUser || $getId): ?>
          <a href="index.php" class="btn btn-ghost" style="margin-bottom:1px;">Wissen</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($query): ?>
      <!-- <div class="sql-strip"><strong>SQL:</strong> <?= htmlspecialchars($query) ?></div> -->
      <?php endif; ?>

    </div>
  </div>

  <!-- Results -->
  <?php if ($results): ?>
    <?php foreach ($results as $i => $row):
      // role is column index 6 — default 0 if not present
      $role = isset($row[6]) ? (int)$row[6] : 0;
      $isAdmin = $role === 1;
    ?>
    <div class="card" style="margin-bottom:14px;animation:fadeUp .3s ease <?= $i*0.07 ?>s both;">
      <div class="card-body" style="display:grid;grid-template-columns:56px 1fr auto;gap:20px;align-items:center;">

        <!-- Avatar -->
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,<?= $isAdmin ? '#0f3460,#1565c0' : '#00796b,#00acc1' ?>);display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Lora',serif;font-size:1.3rem;flex-shrink:0;">
          <?= strtoupper(substr($row[1], 0, 1)) ?>
        </div>

        <!-- Info -->
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
            <span style="font-weight:600;font-size:1rem;color:#0a2540;"><?= htmlspecialchars($row[3] . ' ' . $row[4]) ?></span>
            <span class="role-badge <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
              <?= $isAdmin ? '👑 Beheerder' : '👤 Patiënt' ?>
            </span>
          </div>
          <div style="font-size:.83rem;color:#6b8aa8;margin-bottom:3px;"><?= htmlspecialchars($row[5]) ?></div>
        </div>

        <!-- Right side -->
        <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
          <div style="font-size:.8rem;color:#6b8aa8;">@<?= htmlspecialchars($row[1]) ?></div>
          <span class="badge badge-blue">#<?= $row[0] ?></span>
          <?php if (!$isAdmin): ?>
          <button
            class="btn-view-data"
            onclick="openMedicalModal(<?= $row[0] ?>, '<?= htmlspecialchars($row[3] . ' ' . $row[4], ENT_QUOTES) ?>', '<?= htmlspecialchars($row[1], ENT_QUOTES) ?>')"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Medische data
          </button>
          <?php else: ?>
          <span style="font-size:.72rem;color:#9ab0c4;font-style:italic;">Geen medische data</span>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php endforeach; ?>

  <?php elseif ($query): ?>
    <div class="card"><div class="empty-state">
      <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <p class="empty-title">Geen gebruikers gevonden</p>
      <p>Probeer een andere zoekopdracht</p>
    </div></div>

  <?php else: ?>
    <div class="card"><div class="empty-state">
      <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <p class="empty-title">Voer een zoekopdracht in</p>
      <p>Gebruik het formulier hierboven om gebruikers te zoeken</p>
    </div></div>
  <?php endif; ?>

</div>

  </div><!-- end tab-content search -->

  <?php else: ?>
<div class="tab-content" id="content-search">
  <div class="card"><div class="empty-state">
    <p class="empty-title">Toegang geweigerd</p>
    <p>Alleen beheerders mogen gebruikers zoeken.</p>
  </div></div>
</div>
<?php endif; ?>

<!-- ── Medical data modal ────────────────────────────────────────── -->
<div class="modal-overlay" id="modalOverlay" onclick="handleOverlayClick(event)">
  <div class="modal" id="modal">

    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-avatar" id="modalAvatar">?</div>
        <div>
          <div class="modal-name" id="modalName">Laden...</div>
          <div class="modal-username" id="modalUsername"></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body" id="modalBody">
      <div class="modal-loading">
        <div class="spinner"></div>
        <p>Medische gegevens ophalen...</p>
      </div>
    </div>

  </div>
</div>

<script>
  // ── Page tab switching ───────────────────────────────────
  function switchPageTab(name) {
    document.querySelectorAll('.page-tab').forEach(b => {
      b.classList.toggle('active', b.id === 'tab-' + name);
    });
    document.querySelectorAll('.tab-content').forEach(c => {
      c.classList.toggle('active', c.id === 'content-' + name);
    });
  }

  // Auto-switch to search tab if there are search results or params
  (function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('username') || params.get('id')) {
      switchPageTab('search');
    }
  })();

  // Hamburger
  document.getElementById('hamburger').addEventListener('click', () => {
    document.getElementById('mainNav').classList.toggle('open');
  });

  // Close modal on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
  });

  function handleOverlayClick(e) {
    if (e.target === document.getElementById('modalOverlay')) closeModal();
  }

  function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }

  function openMedicalModal(userId, fullName, username) {
    // Set name immediately
    document.getElementById('modalAvatar').textContent = fullName.charAt(0).toUpperCase();
    document.getElementById('modalName').textContent = fullName;
    document.getElementById('modalUsername').textContent = '@' + username;

    // Show loading
    document.getElementById('modalBody').innerHTML = `
      <div class="modal-loading">
        <div class="spinner"></div>
        <p>Medische gegevens ophalen...</p>
      </div>`;

    // Open overlay
    document.getElementById('modalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Fetch medical data
    fetch(`index.php?medical_ajax=1&uid=${userId}`)
      .then(r => r.json())
      .then(data => renderModal(data))
      .catch(() => {
        document.getElementById('modalBody').innerHTML = `
          <div class="no-medical">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Fout bij ophalen van gegevens.</p>
          </div>`;
      });
  }

  function renderModal(data) {
    if (data.error) {
      document.getElementById('modalBody').innerHTML = `
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 18px;color:#dc2626;font-size:.87rem;">
          🔒 ${data.error}
        </div>`;
      return;
    }

    const m = data.medical;
    const vuln = data.vulnerable;

    let html = '';

 

    if (!m) {
      html += `
        <div class="no-medical">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
          <p>Geen medische gegevens gevonden voor deze patiënt.</p>
        </div>`;
    } else {
      html += `
        <div class="modal-section">
          <div class="modal-section-title">Bloedgroep</div>
          <div style="display:flex;align-items:center;gap:12px;">
            <div>
              <div class="blood-badge">${m[2] || '?'}</div>
            </div>
            <div style="font-size:.85rem;color:#2c4a65;line-height:1.5;">
              Bloedgroep <strong>${m[2] || 'onbekend'}</strong><br>
              <span style="font-size:.78rem;color:#6b8aa8;">Patiënt ID #${m[1]}</span>
            </div>
          </div>
        </div>

        <div class="modal-section">
          <div class="modal-section-title">Medische gegevens</div>
          <div class="data-grid">
            <div class="data-item full">
              <div class="data-label">Allergieën</div>
              <div class="data-value">${m[3] || '—'}</div>
            </div>
            <div class="data-item full">
              <div class="data-label">Aandoeningen</div>
              <div class="data-value">${m[4] || '—'}</div>
            </div>
            <div class="data-item full">
              <div class="data-label">Medicatie</div>
              <div class="data-value">${m[5] || '—'}</div>
            </div>
          </div>
        </div>

        <div class="modal-section">
          <div class="data-grid">
             
            </div>
            </div>
          </div>
          </div>
        </div>`;
    }

    document.getElementById('modalBody').innerHTML = html;
  }
</script>
</body>
</html>