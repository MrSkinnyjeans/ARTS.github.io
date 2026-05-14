<?php
// includes/layout.php

function startLayout(string $title, string $active): void {
    $user     = currentUser();
    $initials = strtoupper(substr($user['full_name'] ?? 'A', 0, 2));
    $role     = $user['role'] ?? 'admin';
    $canChat  = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> · ARTS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-row">
      <div class="logo-circle">A</div>
      <div>
        <div class="logo-text">ARTS</div>
        <div class="logo-school">ACLC Mandaue</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>

    <?php if ($role === 'principal'): ?>
      <a href="principal.php" class="nav-item <?= $active==='principal'?'active':'' ?>">
        <?= icon('shield') ?> Principal Dashboard
      </a>
    <?php else: ?>
      <a href="dashboard.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>">
        <?= icon('grid') ?> Dashboard
      </a>
    <?php endif; ?>

    <?php if ($role !== 'principal'): ?>
      <div class="nav-label" style="margin-top:12px">Records</div>
      <a href="applicants.php" class="nav-item <?= $active==='applicants'?'active':'' ?>">
        <?= icon('users') ?> Applicants
      </a>
      <a href="referrals.php" class="nav-item <?= $active==='referrals'?'active':'' ?>">
        <?= icon('link') ?> Referrals
      </a>
      <a href="results.php" class="nav-item <?= $active==='results'?'active':'' ?>">
        <?= icon('file') ?> Exam Results
      </a>
      <div class="nav-label" style="margin-top:12px">Reports</div>
      <a href="reports.php" class="nav-item <?= $active==='reports'?'active':'' ?>">
        <?= icon('bar') ?> Reports
      </a>
    <?php endif; ?>

    <?php if ($canChat): ?>
      <div class="nav-label" style="margin-top:12px">Communication</div>
      <a href="chat.php" class="nav-item <?= $active==='chat'?'active':'' ?>">
        <?= icon('chat') ?>
        Messages
        <span id="chat-unread-badge" class="chat-nav-badge" style="display:none">0</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-row">
      <div class="user-avatar"><?= $initials ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></div>
        <div class="user-role"><?= ucfirst($role) ?></div>
      </div>
      <a href="logout.php" class="logout-btn" title="Logout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>

<div class="main">
<?php
}

function endLayout(): void {
?>
</div><!-- /.main -->
<script src="assets/js/main.js"></script>
</body>
</html>
<?php
}

/* ── SVG icon helper ── */
function icon(string $name): string {
    $icons = [
        'grid'   => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'users'  => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'link'   => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'file'   => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'bar'    => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'shield' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'chat'   => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

/* ── Badge helpers ── */
function statusBadge(string $s): string {
    $map = ['Passed' => 'badge-success', 'Failed' => 'badge-danger', 'Pending' => 'badge-warning'];
    return '<span class="badge ' . ($map[$s] ?? 'badge-neutral') . '">' . htmlspecialchars($s) . '</span>';
}

function refBadge(string $t): string {
    $map = ['Teacher' => 'badge-info', 'Alumni' => 'badge-neutral', 'Partner School' => 'badge-success', 'Walk-in' => 'badge-warning', 'Online' => 'badge-info'];
    return '<span class="badge ' . ($map[$t] ?? 'badge-neutral') . '">' . htmlspecialchars($t) . '</span>';
}

function scholBadge(string $s): string {
    $map = [
        'Approved'      => 'badge-success',
        'Rejected'      => 'badge-danger',
        'For Review'    => 'badge-warning',
        'Not Evaluated' => 'badge-neutral',
    ];
    return '<span class="badge ' . ($map[$s] ?? 'badge-neutral') . '">' . htmlspecialchars($s) . '</span>';
}
