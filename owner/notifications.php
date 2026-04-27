<?php
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'notifications';
$owner_id = (int)($_SESSION['user_id'] ?? 0);

if ($owner_id <= 0) {
    header('Location: ../login.php');
    exit();
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mark_action = trim($_POST['mark_action'] ?? '');

    if ($mark_action === 'mark_one') {
        $notif_id = (int)($_POST['notif_id'] ?? 0);
        if ($notif_id > 0) {
            $s = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            if ($s) { mysqli_stmt_bind_param($s, 'ii', $notif_id, $owner_id); mysqli_stmt_execute($s); mysqli_stmt_close($s); }
        }
    } elseif ($mark_action === 'mark_all') {
        $s = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($s) { mysqli_stmt_bind_param($s, 'i', $owner_id); mysqli_stmt_execute($s); mysqli_stmt_close($s); }
    }

    header('Location: notifications.php');
    exit();
}

$search          = trim($_GET['search'] ?? '');
$selected_pet_id = (int)($_GET['pet_id'] ?? 0);
$type_filter     = trim($_GET['type'] ?? 'all');

// Fetch from notifications table (new - has is_read)
$notifications = [];
$conditions = ['n.user_id = ?'];
$params     = [$owner_id];
$ptypes     = 'i';

if ($search !== '') {
    $conditions[] = "(n.title LIKE ? OR n.message LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
    $ptypes .= 'ss';
}
if ($selected_pet_id > 0) {
    $conditions[] = 'n.pet_id = ?';
    $params[] = $selected_pet_id;
    $ptypes .= 'i';
}
if ($type_filter !== 'all' && $type_filter !== '') {
    $conditions[] = 'n.type = ?';
    $params[] = $type_filter;
    $ptypes .= 's';
}

$sql = "SELECT n.id, n.pet_id, n.title, n.message, n.type, n.is_read, n.created_at,
               p.name AS pet_name
        FROM notifications n
        LEFT JOIN pets p ON p.id = n.pet_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY n.is_read ASC, n.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $ptypes, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = $result ? mysqli_fetch_assoc($result) : null) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Also fetch from reminders table (older records not in notifications table)
$reminder_notifs = [];
$r_conditions = ['r.owner_id = ?', "r.status = 'sent'"];
$r_params     = [$owner_id];
$r_ptypes     = 'i';

if ($search !== '') {
    $r_conditions[] = "(p.name LIKE ? OR r.message LIKE ?)";
    $like = '%' . $search . '%';
    $r_params[] = $like; $r_params[] = $like;
    $r_ptypes .= 'ss';
}
if ($selected_pet_id > 0) {
    $r_conditions[] = 'r.pet_id = ?';
    $r_params[] = $selected_pet_id;
    $r_ptypes .= 'i';
}

$r_sql = "SELECT r.id, r.pet_id, r.reminder_type, r.message, r.created_at,
                 p.name AS pet_name,
                 CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS vet_name
          FROM reminders r
          JOIN pets p ON p.id = r.pet_id
          LEFT JOIN users u ON u.id = r.vet_id
          WHERE " . implode(' AND ', $r_conditions) . "
          ORDER BY r.created_at DESC";

$r_stmt = mysqli_prepare($conn, $r_sql);
if ($r_stmt) {
    mysqli_stmt_bind_param($r_stmt, $r_ptypes, ...$r_params);
    mysqli_stmt_execute($r_stmt);
    $r_result = mysqli_stmt_get_result($r_stmt);
    while ($row = $r_result ? mysqli_fetch_assoc($r_result) : null) {
        $reminder_notifs[] = $row;
    }
    mysqli_stmt_close($r_stmt);
}

// Unread count
$unread_count = 0;
$ust = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
if ($ust) {
    mysqli_stmt_bind_param($ust, 'i', $owner_id);
    mysqli_stmt_execute($ust);
    $ur = mysqli_fetch_assoc(mysqli_stmt_get_result($ust));
    $unread_count = (int)($ur['c'] ?? 0);
    mysqli_stmt_close($ust);
}

// Pets for filter
$pets_for_filter = [];
$pst = mysqli_prepare($conn, "SELECT id, name FROM pets WHERE owner_id = ? ORDER BY name ASC");
if ($pst) {
    mysqli_stmt_bind_param($pst, 'i', $owner_id);
    mysqli_stmt_execute($pst);
    $pr = mysqli_stmt_get_result($pst);
    while ($row = $pr ? mysqli_fetch_assoc($pr) : null) $pets_for_filter[] = $row;
    mysqli_stmt_close($pst);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications - PetCura</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="../assets/css/owner.css" rel="stylesheet"/>
  <style>
    .notif-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 16px 18px;
      margin-bottom: 10px;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: #fff;
      cursor: pointer;
      transition: box-shadow 0.2s;
      text-decoration: none;
      color: inherit;
    }
    .notif-item:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.09); color: inherit; }
    /* UNREAD = green highlight */
    .notif-item.unread {
      background: #f0fdf4;
      border-left: 4px solid #16a34a;
    }
    /* READ = plain white, no color */
    .notif-item.read {
      background: #fff;
      border-left: 1px solid #e5e7eb;
    }
    .notif-icon {
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem; flex-shrink: 0;
      background: #f3f4f6; color: #6b7280;
    }
    .notif-item.unread .notif-icon { background: #16a34a; color: #fff; }
    .notif-item.read .notif-icon   { background: #f3f4f6; color: #9ca3af; }
    .notif-title {
      font-weight: 700; font-size: 0.95rem; color: #1f2937; margin-bottom: 3px;
    }
    .notif-item.unread .notif-title { color: #15803d; font-weight: 800; }
    .notif-item.read   .notif-title { color: #6b7280; font-weight: 500; }
    .notif-msg { font-size: 0.87rem; color: #6b7280; margin-bottom: 7px; line-height: 1.5; }
    .notif-time { font-size: 0.76rem; color: #9ca3af; font-weight: 500; }
    .notif-meta { display: flex; gap: 8px; align-items: center; font-size: 0.76rem; color: #9ca3af; flex-wrap: wrap; }
    .notif-tag { background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-weight: 600; color: #6b7280; }
    .unread-dot { width: 8px; height: 8px; background: #16a34a; border-radius: 50%; display: inline-block; margin-right: 5px; flex-shrink: 0; margin-top: 6px; }
    .mark-read-btn {
      background: none; border: 1px solid #d1d5db; border-radius: 5px;
      padding: 3px 10px; font-size: 0.75rem; color: #6b7280; cursor: pointer;
    }
    .mark-read-btn:hover { border-color: #16a34a; color: #16a34a; }
    .mark-all-btn {
      background: #fff; border: 1px solid #e5e7eb; padding: 8px 16px;
      border-radius: 7px; color: #16a34a; font-weight: 700;
      cursor: pointer; font-size: 0.88rem;
    }
    .mark-all-btn:hover { background: #16a34a; color: #fff; }
    .mark-all-btn:disabled { opacity: 0.45; cursor: default; }
    .section-label {
      font-size: 0.78rem; font-weight: 700; color: #9ca3af;
      text-transform: uppercase; letter-spacing: 0.05em;
      padding: 8px 0 6px; border-bottom: 1px solid #f3f4f6; margin-bottom: 10px;
    }
  </style>
</head>
<body>
<div class="owner-layout">
  <?php include 'includes/sidebar.php'; ?>
  <main class="owner-main">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
      <div>
        <h1 style="font-size:1.7rem; font-weight:700; font-family:'Sora',sans-serif; margin:0;">
          Notifications
          <?php if ($unread_count > 0): ?>
          <span style="background:#0d9488;color:#fff;font-size:0.68rem;padding:3px 9px;border-radius:12px;margin-left:8px;font-weight:700;vertical-align:middle;">
            <?= $unread_count ?> unread
          </span>
          <?php endif; ?>
        </h1>
      </div>
      <?php if ($unread_count > 0): ?>
      <form method="POST">
        <input type="hidden" name="mark_action" value="mark_all">
        <button type="submit" class="mark-all-btn"><i class="bi bi-check2-all me-1"></i>Mark all as read</button>
      </form>
      <?php else: ?>
      <button class="mark-all-btn" disabled>All caught up ✓</button>
      <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search notifications..."
             style="flex:1; min-width:200px; padding:9px 13px; border:1px solid #e5e7eb; border-radius:7px; font-size:0.88rem;">
      <select name="pet_id" style="padding:9px 13px; border:1px solid #e5e7eb; border-radius:7px; font-size:0.88rem; min-width:150px;">
        <option value="0">All pets</option>
        <?php foreach ($pets_for_filter as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $selected_pet_id === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" style="padding:9px 13px; border:1px solid #e5e7eb; border-radius:7px; font-size:0.88rem; min-width:140px;">
        <option value="all">All types</option>
        <option value="reminder" <?= $type_filter==='reminder'?'selected':'' ?>>Reminder</option>
        <option value="followup" <?= $type_filter==='followup'?'selected':'' ?>>Follow-up</option>
        <option value="info"     <?= $type_filter==='info'    ?'selected':'' ?>>Info</option>
      </select>
      <button type="submit" style="padding:9px 18px; background:#0d9488; color:#fff; border:none; border-radius:7px; font-weight:700; font-size:0.88rem; cursor:pointer;">Filter</button>
      <?php if ($search !== '' || $selected_pet_id > 0 || $type_filter !== 'all'): ?>
      <a href="notifications.php" style="padding:9px 14px; border:1px solid #e5e7eb; border-radius:7px; color:#6b7280; font-size:0.88rem; text-decoration:none; display:flex; align-items:center;">Clear</a>
      <?php endif; ?>
    </form>

    <!-- Notifications from notifications table (new, has is_read) -->
    <?php if (!empty($notifications)): ?>
    <div class="section-label">Notifications from your vet</div>
    <?php foreach ($notifications as $n): ?>
    <?php $unread = !(bool)$n['is_read']; ?>
    <div class="notif-item <?= $unread ? 'unread' : 'read' ?>" onclick="showDetail(
        '<?= htmlspecialchars(addslashes($n['title']), ENT_QUOTES) ?>',
        '<?= htmlspecialchars(addslashes($n['message']), ENT_QUOTES) ?>',
        '<?= htmlspecialchars(addslashes($n['pet_name'] ?? ''), ENT_QUOTES) ?>',
        '<?= date('M d, Y · g:i a', strtotime($n['created_at'])) ?>',
        '<?= htmlspecialchars(addslashes(ucfirst($n['type'])), ENT_QUOTES) ?>'
    )">
      <?php if ($unread): ?><div class="unread-dot"></div><?php endif; ?>
      <div class="notif-icon">
        <?php if ($n['type'] === 'reminder'): ?><i class="bi bi-bell-fill"></i>
        <?php elseif ($n['type'] === 'followup'): ?><i class="bi bi-arrow-counterclockwise"></i>
        <?php else: ?><i class="bi bi-info-circle-fill"></i>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
        <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
        <div class="notif-time" style="margin-bottom:6px;">
          <i class="bi bi-clock" style="font-size:0.7rem;"></i>
          <?= date('M d, Y · g:i a', strtotime($n['created_at'])) ?>
        </div>
        <div class="notif-meta">
          <?php if (!empty($n['pet_name'])): ?>
          <span class="notif-tag"><i class="bi bi-heart-fill" style="color:#f87171;font-size:0.65rem;"></i> <?= htmlspecialchars($n['pet_name']) ?></span>
          <?php endif; ?>
          <span class="notif-tag"><?= htmlspecialchars(ucfirst($n['type'])) ?></span>
          <?php if ($unread): ?>
          <form method="POST" style="margin:0;" onclick="event.stopPropagation()">
            <input type="hidden" name="mark_action" value="mark_one">
            <input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>">
            <button type="submit" class="mark-read-btn">Mark as read</button>
          </form>
          <?php else: ?>
          <span style="color:#9ca3af;font-size:0.76rem;"><i class="bi bi-check2"></i> Read</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Older reminders from reminders table -->
    <?php if (!empty($reminder_notifs)): ?>
    <div class="section-label" style="margin-top:20px;">Reminder history</div>
    <?php foreach ($reminder_notifs as $r): ?>
    <?php
      $r_title = ($r['pet_name'] ?? 'Pet') . ' — ' . ucfirst($r['reminder_type']) . ' reminder';
      $r_msg   = $r['message'] ?: 'Reminder sent for ' . ($r['pet_name'] ?? 'your pet');
      $r_vet   = trim($r['vet_name'] ?? '') !== '' ? 'Dr. ' . trim($r['vet_name']) : 'Your vet';
    ?>
    <div class="notif-item">
      <div class="notif-icon">
        <?php if ($r['reminder_type'] === 'vaccination'): ?><i class="bi bi-shield-check"></i>
        <?php elseif ($r['reminder_type'] === 'deworming'): ?><i class="bi bi-capsule"></i>
        <?php else: ?><i class="bi bi-bell"></i>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div class="notif-title"><?= htmlspecialchars($r_title) ?></div>
        <div class="notif-msg"><?= htmlspecialchars($r_msg) ?></div>
        <div class="notif-meta">
          <span><?= date('M d, Y · g:i a', strtotime($r['created_at'])) ?></span>
          <?php if (!empty($r['pet_name'])): ?>
          <span class="notif-tag"><?= htmlspecialchars($r['pet_name']) ?></span>
          <?php endif; ?>
          <span class="notif-tag"><?= htmlspecialchars($r_vet) ?></span>
          <span style="color:#10b981;font-size:0.76rem;font-weight:600;"><i class="bi bi-check-circle"></i> Sent</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($notifications) && empty($reminder_notifs)): ?>
    <div style="text-align:center; padding:60px 20px; color:#9ca3af;">
      <i class="bi bi-bell-slash" style="font-size:2.5rem; display:block; margin-bottom:12px;"></i>
      <p style="font-size:1rem; font-weight:600; margin:0;">No notifications yet</p>
    </div>
    <?php endif; ?>

  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Detail popup -->
<div id="notifOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;" onclick="closeOverlay(event)">
  <div style="background:#fff;border-radius:14px;padding:28px;max-width:460px;width:90%;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.2);">
    <button onclick="document.getElementById('notifOverlay').style.display='none'" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.3rem;color:#9ca3af;cursor:pointer;"><i class="bi bi-x-lg"></i></button>
    <div id="detailBadge" style="display:inline-block;background:#f0fdf4;color:#16a34a;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;margin-bottom:12px;"></div>
    <div id="detailTitle" style="font-size:1.1rem;font-weight:800;color:#1f2937;margin-bottom:10px;font-family:'Sora',sans-serif;"></div>
    <div id="detailMsg" style="font-size:0.9rem;color:#4b5563;line-height:1.6;margin-bottom:14px;"></div>
    <div style="font-size:0.83rem;color:#6b7280;display:flex;flex-direction:column;gap:6px;">
      <div><i class="bi bi-heart-fill" style="color:#f87171;margin-right:6px;"></i><strong>Pet:</strong> <span id="detailPet"></span></div>
      <div><i class="bi bi-clock" style="margin-right:6px;color:#9ca3af;"></i><strong>Sent at:</strong> <span id="detailTime"></span></div>
    </div>
  </div>
</div>

<script>
function showDetail(title, msg, pet, time, type) {
    document.getElementById('detailTitle').textContent = title;
    document.getElementById('detailMsg').textContent   = msg;
    document.getElementById('detailPet').textContent   = pet || '—';
    document.getElementById('detailTime').textContent  = time;
    document.getElementById('detailBadge').textContent = type;
    document.getElementById('notifOverlay').style.display = 'flex';
}
function closeOverlay(e) {
    if (e.target === document.getElementById('notifOverlay')) {
        document.getElementById('notifOverlay').style.display = 'none';
    }
}
</script>
</body>
</html>