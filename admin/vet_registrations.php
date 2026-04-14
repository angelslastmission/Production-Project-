<?php
// ═══════════════════════════════════════════
// BACKEND — Admin Vet Registrations
// ═══════════════════════════════════════════
session_start();
include '../config.php';
include 'includes/auth.php';

$active_page = 'vet_registrations';
$success     = '';
$error       = '';

// ── Handle Approve ───────────────────────
if (isset($_POST['approve_vet'])) {
    $vet_id = (int)$_POST['vet_id'];

    $stmt = mysqli_prepare($conn,
        "UPDATE users
         SET status = 'approved', is_active = 1
         WHERE id = ? AND role = 'vet'");
    mysqli_stmt_bind_param($stmt, 'i', $vet_id);

    if (mysqli_stmt_execute($stmt)) {
        $success = 'Vet approved successfully!';
    } else {
        $error = 'Failed to approve vet.';
    }
}

// ── Handle Reject ────────────────────────
if (isset($_POST['reject_vet'])) {
    $vet_id = (int)$_POST['vet_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($reason)) {
        $error = 'Please provide a rejection reason.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE users
             SET status = 'rejected',
                 is_active = 0,
                 rejection_reason = ?
             WHERE id = ? AND role = 'vet'");
        mysqli_stmt_bind_param($stmt, 'si', $reason, $vet_id);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Vet rejected successfully.';
        } else {
            $error = 'Failed to reject vet.';
        }
    }
}

// ── Get all vet applications ─────────────
$all_vets = mysqli_query($conn,
    "SELECT id, first_name, last_name, email,
            clinic_name, phone, status, created_at,
            license_doc, citizenship_doc,
            clinic_address, rejection_reason
     FROM users
     WHERE role = 'vet'
     ORDER BY
        CASE status
            WHEN 'pending'  THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        created_at DESC");

// ── Get selected vet for review ──────────
$review_vet = null;
if (isset($_GET['id'])) {
    $review_id = (int)$_GET['id'];
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM users
         WHERE id = ? AND role = 'vet'");
    mysqli_stmt_bind_param($stmt, 'i', $review_id);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $review_vet = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vet Registrations — PetCura</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <!-- Admin CSS -->
  <link href="../assets/css/admin.css" rel="stylesheet"/>
</head>
<body>

<div class="admin-wrapper">

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main">

        <!-- Topbar -->
        <div class="admin-topbar">
            <div>
                <h1 class="admin-page-title">Vet registrations</h1>
                <p class="admin-page-sub">
                    Review and approve new vet applications
                </p>
            </div>
            <div class="topbar-right">
                <span class="topbar-user">
                    <i class="bi bi-person-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </span>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
        <div class="admin-alert-success mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="admin-alert-error mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Vet Applications Table -->
        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>CLINIC NAME</th>
                            <th>PHONE</th>
                            <th>SUBMITTED</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($all_vets) > 0): ?>
                        <?php while ($vet = mysqli_fetch_assoc($all_vets)): ?>
                        <tr>
                            <td>
                                <strong>
                                    Dr. <?= htmlspecialchars($vet['first_name']) ?>
                                    <?= htmlspecialchars($vet['last_name']) ?>
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($vet['email']) ?></td>
                            <td><?= htmlspecialchars($vet['clinic_name']) ?></td>
                            <td><?= htmlspecialchars($vet['phone']) ?></td>
                            <td>
                                <?= date('M j, Y',
                                    strtotime($vet['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($vet['status'] === 'pending'): ?>
                                    <span class="badge-pending-vet">
                                        Pending
                                    </span>
                                <?php elseif ($vet['status'] === 'approved'): ?>
                                    <span class="badge-approved-vet">
                                        Approved
                                    </span>
                                <?php else: ?>
                                    <span class="badge-rejected-vet">
                                        Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vet['status'] === 'pending'): ?>
                                    <a href="vet_registrations.php?id=<?= $vet['id'] ?>"
                                       class="btn-review">
                                        Review
                                    </a>
                                <?php else: ?>
                                    <a href="vet_registrations.php?id=<?= $vet['id'] ?>"
                                       class="btn-view-vet">
                                        View
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7"
                                class="text-center text-muted py-4">
                                No vet applications yet
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Review Panel — shows when vet selected -->
        <?php if ($review_vet): ?>
        <div class="admin-card mt-4">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    Review application —
                    Dr. <?= htmlspecialchars($review_vet['first_name']) ?>
                    <?= htmlspecialchars($review_vet['last_name']) ?>
                </h5>
            </div>

            <div class="review-panel">

                <!-- Left: Applicant Details -->
                <div class="review-details">
                    <h6 class="review-section-title">Applicant details</h6>

                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value">
                            Dr. <?= htmlspecialchars($review_vet['first_name']) ?>
                            <?= htmlspecialchars($review_vet['last_name']) ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($review_vet['email']) ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($review_vet['phone']) ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Clinic:</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($review_vet['clinic_name']) ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($review_vet['clinic_address']) ?>
                        </span>
                    </div>

                    <?php if ($review_vet['status'] === 'rejected' &&
                              $review_vet['rejection_reason']): ?>
                    <div class="detail-row mt-3">
                        <span class="detail-label">Rejection reason:</span>
                        <span class="detail-value text-danger">
                            <?= htmlspecialchars($review_vet['rejection_reason']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Approve/Reject buttons — only for pending -->
                    <?php if ($review_vet['status'] === 'pending'): ?>
                    <div class="review-actions mt-4">

                        <!-- Approve Form -->
                        <form method="POST"
                              action="vet_registrations.php?id=<?= $review_vet['id'] ?>"
                              style="display:inline;">
                            <input type="hidden"
                                   name="vet_id"
                                   value="<?= $review_vet['id'] ?>"/>
                            <button type="submit"
                                    name="approve_vet"
                                    class="btn-approve-vet"
                                    onclick="return confirm(
                                        'Approve Dr. <?= htmlspecialchars($review_vet['first_name']) ?>?')">
                                <i class="bi bi-check-circle me-1"></i>
                                Approve vet
                            </button>
                        </form>

                        <!-- Reject Button — toggles form -->
                        <button type="button"
                                class="btn-reject-vet"
                                onclick="toggleRejectForm()">
                            <i class="bi bi-x-circle me-1"></i>
                            Reject with reason
                        </button>

                        <!-- Reject Form — hidden by default -->
                        <div id="reject_form"
                             style="display:none; margin-top:16px;">
                            <form method="POST"
                                  action="vet_registrations.php?id=<?= $review_vet['id'] ?>">
                                <input type="hidden"
                                       name="vet_id"
                                       value="<?= $review_vet['id'] ?>"/>
                                <textarea name="rejection_reason"
                                          class="reject-textarea"
                                          placeholder="Enter rejection reason..."
                                          rows="3"
                                          required></textarea>
                                <div class="mt-2">
                                    <button type="submit"
                                            name="reject_vet"
                                            class="btn-reject-confirm">
                                        Confirm rejection
                                    </button>
                                    <button type="button"
                                            class="btn-cancel"
                                            onclick="toggleRejectForm()">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                    <?php endif; ?>

                </div>

                <!-- Right: Uploaded Documents -->
                <div class="review-docs">
                    <h6 class="review-section-title">Uploaded documents</h6>

                    <!-- License Document -->
                    <div class="doc-box">
                        <div class="doc-info">
                            <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                            <div>
                                <div class="doc-title">Veterinary License</div>
                                <div class="doc-filename">
                                    <?= htmlspecialchars(
                                        $review_vet['license_doc'] ?? 'Not uploaded') ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($review_vet['license_doc']): ?>
                        <a href="../uploads/licenses/<?= htmlspecialchars($review_vet['license_doc']) ?>"
                           target="_blank"
                           class="btn-view-file">
                            View file
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Citizenship Document -->
                    <div class="doc-box mt-3">
                        <div class="doc-info">
                            <i class="bi bi-file-earmark-person text-primary me-2"></i>
                            <div>
                                <div class="doc-title">Citizenship / ID</div>
                                <div class="doc-filename">
                                    <?= htmlspecialchars(
                                        $review_vet['citizenship_doc'] ?? 'Not uploaded') ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($review_vet['citizenship_doc']): ?>
                        <a href="../uploads/citizenships/<?= htmlspecialchars($review_vet['citizenship_doc']) ?>"
                           target="_blank"
                           class="btn-view-file">
                            View file
                        </a>
                        <?php endif; ?>
                    </div>

                </div>

            </div><!-- /review-panel -->
        </div>
        <?php endif; ?>

    </div><!-- /admin-main -->

</div><!-- /admin-wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleRejectForm() {
    var form = document.getElementById('reject_form');
    form.style.display =
        form.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>