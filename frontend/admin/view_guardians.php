<?php
// View Guardian Family Profile - Requires live database connectivity and session parameters
require_once '../../backend/auth.php';
requireLogin();
requireRole('admin');

// Database configuration bridge injection
require_once '../../backend/config.php'; 

// Extract query parameters and eliminate whitespace strings
$cnic = trim($_GET['cnic'] ?? '');
$phone = trim($_GET['phone'] ?? '');
$fallback_id = trim($_GET['fallback_id'] ?? '');

// Validation barrier trap: Check if absolutely nothing was forwarded over the URL matrix
if (empty($cnic) && empty($phone) && empty($fallback_id)) {
    die("Error: Missing family identity parameters required to isolate the profile schema safely.");
}

try {
    // 1. FETCH THE GUARDIAN'S PRIMARY PROFILE DETAILS
    if (!empty($cnic)) {
        $profileStmt = $pdo->prepare("SELECT father_name, father_phone, father_cnic, mother_name, mother_phone, mother_cnic, religion FROM students WHERE father_cnic = ? LIMIT 1");
        $profileStmt->execute([$cnic]);
    } elseif (!empty($phone)) {
        $profileStmt = $pdo->prepare("SELECT father_name, father_phone, father_cnic, mother_name, mother_phone, mother_cnic, religion FROM students WHERE father_phone = ? LIMIT 1");
        $profileStmt->execute([$phone]);
    } else {
        // Safe Catch System fallback target
        $profileStmt = $pdo->prepare("SELECT father_name, father_phone, father_cnic, mother_name, mother_phone, mother_cnic, religion FROM students WHERE id = ? LIMIT 1");
        $profileStmt->execute([$fallback_id]);
    }
    
    $guardian = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardian) {
        die("Data Exception: The requested family profile could not be located inside the database matrix query loops.");
    }

    // 2. FETCH ALL ENROLLED DEPENDENTS (CHILDREN) LINKED TO THIS FAMILY
    // Pull entries targeting either matching CNIC tags or fallback phone reference blocks
    $childrenStmt = $pdo->prepare("
        SELECT s.id, s.reg_id, s.first_name, s.last_name, s.gender, s.status, c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE (s.father_cnic = ? AND ? != '') OR s.father_phone = ? OR (s.father_name = ? AND s.father_name != '')
        ORDER BY s.id ASC
    ");
    $childrenStmt->execute([$guardian['father_cnic'], $guardian['father_cnic'], $guardian['father_phone'], $guardian['father_name']]);
    $children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. FETCH FINANCIAL REVENUE SUMMARY FOR THIS GUARDIAN'S WARD ARRAY
    $studentIds = array_column($children, 'id');
    $totalPaid = 0.00;
    
    if (!empty($studentIds)) {
        $inClause = implode(',', array_fill(0, count($studentIds), '?'));
        $financeStmt = $pdo->prepare("
            SELECT SUM(amount_paid) as total 
            FROM fee_payments 
            WHERE student_id IN ($inClause) AND status = 'paid'
        ");
        $financeStmt->execute($studentIds);
        $totalPaid = $financeStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;
    }

} catch (PDOException $e) {
    error_log("Guardian Profile Engine Exception: " . $e->getMessage());
    die("Critical Error: Failed to safely aggregate the requested family dossier layout configuration rules.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Profile Dossier | Smart Choice Academy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <a href="guardians.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back Directory</a>
                <span class="fs-5 fw-medium text-dark">Family Account Dossier Card</span>
            </div>
        </header>

        <div class="dashboard-content">
            
            <div class="row g-4 mb-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded p-4 bg-white h-100">
                        <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3">
                                <i class="bi bi-person-vcard"></i>
                            </div>
                            <div>
                                <h4 class="m-0 fw-bold text-dark"><?php echo htmlspecialchars($guardian['father_name'] ?? 'Unspecified Name'); ?></h4>
                                <small class="text-muted">Primary Account Holder / Family Entity</small>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-muted small">Father's Phone Line</div>
                                <div class="fw-semibold text-dark"><i class="bi bi-telephone me-1 text-muted"></i><?php echo htmlspecialchars($guardian['father_phone'] ?: 'No Contact Provided'); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted small">Father's National ID (CNIC)</div>
                                <div class="fw-semibold text-dark"><i class="bi bi-card-text me-1 text-muted"></i><code><?php echo htmlspecialchars($guardian['father_cnic'] ?: 'No Card Index Logged'); ?></code></div>
                            </div>
                            <div class="col-sm-6 border-top pt-2">
                                <div class="text-muted small">Mother's Full Name</div>
                                <div class="fw-semibold text-dark"><i class="bi bi-person me-1 text-muted"></i><?php echo htmlspecialchars($guardian['mother_name'] ?: 'N/A'); ?></div>
                            </div>
                            <div class="col-sm-6 border-top pt-2">
                                <div class="text-muted small">Mother's Phone Matrix Details</div>
                                <div class="fw-semibold text-dark"><i class="bi bi-phone me-1 text-muted"></i><?php echo htmlspecialchars($guardian['mother_phone'] ?: '-'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded p-4 bg-white h-100 text-center d-flex flex-column justify-content-center align-items-center">
                        <div class="stat-icon green p-3 rounded-circle mb-3 bg-opacity-10" style="background-color: rgba(40, 167, 69, 0.1);">
                            <i class="bi bi-wallet2 fs-2 text-success" style="color: #28a745;"></i>
                        </div>
                        <div class="text-muted small fw-medium text-uppercase tracking-wider">Total Financial Realization</div>
                        <h2 class="fw-bold text-dark my-2">Rs. <?php echo number_format($totalPaid, 2); ?></h2>
                        <span class="badge bg-success-subtle text-success px-3 py-1.5 rounded-pill small">Accumulated Sibling Intake</span>
                    </div>
                </div>
            </div>

            <div class="content-card bg-white shadow-sm rounded overflow-hidden">
                <div class="card-header p-3 border-bottom bg-light">
                    <h5 class="card-title m-0 fw-semibold text-dark"><i class="bi bi-children me-2"></i>Registered Ward Array (Dependents)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="data-table table m-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Registration ID</th>
                                <th>Student Full Name</th>
                                <th>Assigned Grade Profile</th>
                                <th>Gender Designation</th>
                                <th class="text-center">Account Status</th>
                                <th class="text-center">Action Framework</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($children) > 0): ?>
                                <?php foreach ($children as $child): ?>
                                    <tr>
                                        <td><code class="fw-bold"><?php echo htmlspecialchars($child['reg_id']); ?></code></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium">
                                                <?php echo htmlspecialchars($child['class_name'] ?? 'Unassigned'); ?>
                                            </span>
                                            <small class="text-muted d-block" style="font-size: 11px;">Section: <?php echo htmlspecialchars($child['section_name'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($child['gender']); ?></td>
                                        <td class="text-center">
                                            <?php 
                                                $badgeClass = 'orange'; 
                                                if ($child['status'] === 'approved') $badgeClass = 'active';
                                                if ($child['status'] === 'rejected') $badgeClass = 'inactive';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst(htmlspecialchars($child['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="../students/view.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-light border action-btn">
                                                <i class="bi bi-eye"></i> View Card
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-4">No specific children tied to this structural signature string criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>