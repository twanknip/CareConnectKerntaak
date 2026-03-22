<?php
// nav.php - shared navigation include
// Usage: include 'nav.php'; at top of each page body
// Set $activePage before including: $activePage = 'messages';
$activePage = $activePage ?? '';
$sessionUser = $_SESSION['username'] ?? null;
$sessionId   = $_SESSION['userid']   ?? null;
?>
<header class="site-header">
  <div class="header-inner">

    <!-- Brand -->
    <a href="index.php" class="brand">
      <div class="brand-mark">
        <svg width="22" height="22" viewBox="0 0 80 80" fill="none">
          <rect x="30" y="8"  width="20" height="64" rx="6" fill="white"/>
          <rect x="8"  y="30" width="64" height="20" rx="6" fill="white"/>
        </svg>
      </div>
      <div class="brand-text">
        <span class="brand-name">Care<em>Connect</em></span>
        <span class="brand-tagline">Medisch Portaal</span>
      </div>
    </a>

    <!-- Nav links -->
    <nav class="main-nav" id="mainNav">
      <a href="index.php"        class="nav-item <?= $activePage==='users'    ? 'active':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Gebruikers
      </a>
      <a href="messages.php"     class="nav-item <?= $activePage==='messages' ? 'active':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Berichten
      </a>
      <a href="editprofile.php"  class="nav-item <?= $activePage==='profile'  ? 'active':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Mijn Profiel
      </a>

      <a href="template.php"     class="nav-item <?= $activePage==='template' ? 'active':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Sjabloon
      </a>
    </nav>

    <!-- Right side -->
    <div class="header-right">
      <?php if ($sessionUser): ?>
      <div class="user-chip">
        <div class="user-avatar-sm"><?= strtoupper(substr($sessionUser, 0, 1)) ?></div>
        <span class="user-chip-name"><?= htmlspecialchars($sessionUser) ?></span>
      </div>
      <a href="logout.php" class="btn-logout">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Uitloggen
      </a>
      <?php else: ?>
      <a href="login.php" class="btn-login-nav">Inloggen</a>
      <?php endif; ?>

      <!-- Mobile hamburger -->
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>
</header>