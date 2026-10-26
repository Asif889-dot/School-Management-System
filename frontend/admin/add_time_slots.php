<?php
session_start();
require_once '../../backend/auth.php';
require_once '../../backend/config.php';

// Access Control Protection Layer
requireLogin();
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && !in_array('timetable_manage', $_SESSION['permissions'] ?? [])) {
    header("Location: ../dashboard.php");
    exit();
}

$message = "";

// Processing Form Submission Pipeline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_slot'])) {
    $period_number = filter_var($_POST['period_number'], FILTER_VALIDATE_INT);
    $slot_name     = trim($_POST['slot_name']);
    $start_time    = $_POST['start_time'];
    $end_time      = $_POST['end_time'];
    $is_break      = isset($_POST['is_break']) ? 1 : 0;

    if ($period_number === false || empty($slot_name) || empty($start_time) || empty($end_time)) {
        $message = "
        <div class='alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
            <i class='bi bi-exclamation-triangle-fill me-3 fs-4 text-warning'></i>
            <div><strong>Validation Error:</strong> All structural fields are mandatory.</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $message = "
        <div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
            <i class='bi bi-clock-fill me-3 fs-4 text-danger'></i>
            <div><strong>Timeline Error:</strong> The period end time must be later than the start time.</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    } else {
        try {
            // Check for duplicate sequence slots or clashing parameters
            $checkStmt = $pdo->prepare("SELECT id FROM time_slots WHERE period_number = ? AND is_break = ? LIMIT 1");
            $checkStmt->execute([$period_number, $is_break]);

            if ($checkStmt->fetch()) {
                $message = "
                <div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                    <i class='bi bi-shield-slash-fill me-3 fs-4 text-danger'></i>
                    <div><strong>Constraint Conflict:</strong> Period number <code>{$period_number}</code> is already registered under this slot classification framework.</div>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
            } else {
                // Persist the slot details cleanly into your structural engine map
                $insStmt = $pdo->prepare("INSERT INTO time_slots (period_number, slot_name, start_time, end_time, is_break) VALUES (?, ?, ?, ?, ?)");
                $insStmt->execute([$period_number, $slot_name, $start_time, $end_time, $is_break]);

                $message = "
                <div class='alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                    <i class='bi bi-check-circle-fill me-3 fs-4 text-success'></i>
                    <div><strong>Success!</strong> Time slot configuration item saved safely inside systemic parameters.</div>
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
            }
        } catch (\Exception $e) {
            $message = "
            <div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center' role='alert'>
                <i class='bi bi-exclamation-octagon-fill me-3 fs-4 text-danger'></i>
                <div><strong>System Error:</strong> Execution failure. Details: <code>" . htmlspecialchars($e->getMessage()) . "</code></div>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
        }
    }
}

// Fetch active slots for real-time list reference rendering
$activeSlots = $pdo->query("SELECT * FROM time_slots ORDER BY period_number ASC, start_time ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCA Timetable Config - Add Time Slots</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/sidebar.css" />
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --sca-primary: #2c3e50;
            --sca-accent: #1abc9c;
            --sca-bg: #f8f9fa;
        }

        body {
            background-color: var(--sca-bg);
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
        }

        .ui-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .table-header th {
            background-color: var(--sca-primary) !important;
            color: #fff !important;
            font-weight: 500;
        }

        .badge-break {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .badge-period {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="card page-header-card shadow-sm mb-4">
            <div class="card-body p-4">
                <h3 class="m-0 fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-info"></i> Scheduler Infrastructure Configuration
                </h3>
                <small class="opacity-75">Define structural timeline slots, break segments, and chronological bounds for the smart allocation loop engine.</small>
            </div>
        </div>

        <?= $message ?>

        <div class="row g-4">
            <div class="col-xl-5">
                <div class="card ui-card p-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-plus-circle-fill text-primary me-1"></i> Register New Slot
                    </h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Chronological Sequence (Period #)</label>
                            <input type="number" name="period_number" class="form-select border-2" min="1" placeholder="e.g. 1" required style="border-radius: 8px;">
                            <div class="form-text">Used by the engine to order daily scheduling flows chronologically.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Slot Display Label</label>
                            <input type="text" name="slot_name" class="form-control border-2" placeholder="e.g. First Period / Morning Recess" required style="border-radius: 8px;">
                        </div>

                        <div class="row mb-3 g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Time (24h)</label>
                                <input type="time" name="start_time" class="form-control border-2" required style="border-radius: 8px;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Time (24h)</label>
                                <input type="time" name="end_time" class="form-control border-2" required style="border-radius: 8px;">
                            </div>
                        </div>

                        <div class="mb-4 bg-light p-3 rounded border border-dashed">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input border-secondary" type="checkbox" role="switch" id="is_break" name="is_break" value="1">
                                <label class="form-check-input-label fw-semibold ms-2 text-dark" for="is_break">Mark as Intermission / Break Segment</label>
                            </div>
                            <div class="form-text ms-4 ps-2">The solver engine automatically skips structural workload distributions on periods designated as breaks.</div>
                        </div>

                        <button type="submit" name="action_save_slot" class="btn btn-primary w-100 fw-semibold py-2 shadow-sm" style="border-radius: 8px;">
                            <i class="bi bi-save2-fill me-1"></i> Persist Slot Parameter
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card ui-card p-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                        <i class="bi bi-list-stars text-secondary me-1"></i> Active Structural Frame Slots
                    </h5>

                    <div class="table-responsive rounded border">
                        <table class="table table-hover align-middle text-center m-0">
                            <thead class="table-header">
                                <tr>
                                    <th>Sequence #</th>
                                    <th class="text-start">Display Label</th>
                                    <th>Time Frame Bounds</th>
                                    <th>Classification Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activeSlots)): ?>
                                    <tr>
                                        <td colspan="4" class="text-muted py-4 font-italic">No time slot parameters configured inside system database maps.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeSlots as $slot): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold text-secondary">#<?= $slot['period_number'] ?></td>
                                            <td class="text-start fw-semibold text-dark"><?= htmlspecialchars($slot['slot_name']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace border px-2 py-1">
                                                    <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($slot['is_break']): ?>
                                                    <span class="badge badge-break px-3 py-1 rounded-pill"><i class="bi bi-cup-hot-fill me-1"></i>Break</span>
                                                <?php else: ?>
                                                    <span class="badge badge-period px-3 py-1 rounded-pill"><i class="bi bi-book-half me-1"></i>Lecture</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>