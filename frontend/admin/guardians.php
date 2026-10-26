<?php
// Guardian Management Console - Requires live database connectivity and session parameters
require_once '../../backend/auth.php';
requireLogin();
requireRole('admin');

// Database configuration bridge injection
require_once '../../backend/config.php'; 

try {
    // Advanced SQL Aggregation: Group students by father/guardian metrics to create a clean profile list
    // It calculates the total number of children linked to that specific guardian name/phone pair.
    $guardiansQuery = $pdo->query("
        SELECT 
            MIN(s.id) as primary_id,
            s.father_name as guardian_name,
            s.father_phone as guardian_phone,
            s.father_cnic as guardian_cnic,
            s.mother_name,
            s.mother_phone,
            COUNT(s.id) as total_children,
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as children_list
        FROM students s
        WHERE s.father_name IS NOT NULL AND s.father_name != ''
        GROUP BY s.father_name, s.father_phone, s.father_cnic
        ORDER BY guardian_name ASC
    ");
    $guardians = $guardiansQuery->fetchAll(PDO::FETCH_ASSOC);

    // Dynamic Top Counter Metrics
    $totalGuardiansCount = count($guardians);
    
} catch (PDOException $e) {
    error_log("Guardian Module Runtime Exception: " . $e->getMessage());
    die("Critical Error: Core tracking metadata streams failed to run safely. Details: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardian Management | Smart Choice Academy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>

<body>
    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-5 fw-medium text-dark"><i class="bi bi-shield-check me-2"></i>Guardian Records Console</span>
            </div>
            
            <div class="user-menu">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?></div>
                    <small class="text-muted">System Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'AD', 0, 2)); ?>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card d-flex align-items-center gap-3 p-3 bg-white shadow-sm rounded">
                        <div class="stat-icon purple p-3 rounded-circle text-white bg-opacity-10" style="background-color: rgba(111, 66, 193, 0.1);">
                            <i class="bi bi-person-bounding-box fs-3 text-purple" style="color: #6f42c1;"></i>
                        </div>
                        <div>
                            <div class="stat-value fw-bold fs-4"><?php echo number_format($totalGuardiansCount); ?></div>
                            <div class="stat-label text-muted small">Unique Registered Guardians</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card bg-white shadow-sm rounded overflow-hidden">
                <div class="card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                    <h5 class="card-title m-0 fw-semibold text-dark">Active Parents & Family Entities</h5>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print Directory
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="data-table table m-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Primary Guardian (Father)</th>
                                <th>CNIC Record</th>
                                <th>Contact Line</th>
                                <th>Secondary Contact (Mother)</th>
                                <th class="text-center">Enrolled Dependents</th>
                                <th>Linked Child Directory</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($guardians) > 0): ?>
                                <?php foreach ($guardians as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['guardian_name'] ?? 'N/A'); ?></div>
                                            <small class="text-muted text-uppercase" style="font-size: 11px;">Primary Custodian</small>
                                        </td>
                                        <td>
                                            <code class="text-dark bg-light px-2 py-1 rounded small">
                                                <?php echo htmlspecialchars($row['guardian_cnic'] ?: 'No CNIC Logged'); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <a href="tel:<?php echo $row['guardian_phone']; ?>" class="text-decoration-none text-primary">
                                                <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($row['guardian_phone'] ?: 'N/A'); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="small fw-medium text-secondary"><?php echo htmlspecialchars($row['mother_name'] ?: 'Not Specified'); ?></div>
                                            <small class="text-muted d-block" style="font-size: 12px;"><?php echo htmlspecialchars($row['mother_phone'] ?: '-'); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-2.5">
                                                <?php echo $row['total_children']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block text-muted style-italic" style="max-width: 240px;" title="<?php echo htmlspecialchars($row['children_list']); ?>">
                                                <i class="bi bi-children me-1 small"></i><?php echo htmlspecialchars($row['children_list']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group gap-1">
                                                <?php 
                                                // Dynamic string injection with safety fallbacks
                                                $urlParams = 'cnic=' . urlencode($row['guardian_cnic'] ?? '') . 
                                                             '&phone=' . urlencode($row['guardian_phone'] ?? '') . 
                                                             '&fallback_id=' . urlencode($row['primary_id'] ?? '');
                                                ?>
                                                <a href="view_guardians.php?<?php echo $urlParams; ?>" class="btn btn-sm btn-light border action-btn" data-bs-toggle="tooltip" title="View Student Family Record">
                                                    <i class="bi bi-eye"></i> View Family
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center p-5 text-muted">
                                        <i class="bi bi-person-x fs-2 d-block mb-2 text-secondary"></i>
                                        No structural guardian data records discovered inside the database pipeline.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
</body>

</html>