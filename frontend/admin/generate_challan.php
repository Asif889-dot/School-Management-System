<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// 1. Get Parameters safely from URL
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';
$challan_type = $_GET['challan_type'] ?? 'all'; 
$fee_month = $_GET['fee_month'] ?? date('Y-m');

if (empty($type) || empty($id)) {
    die("Error: Missing Parameters. Please go back and select a student/class.");
}

// Normalize the challan type parameter for robust matching
$challan_type = trim(str_replace('%20', ' ', $challan_type));

// 2. Data Fetching Logic using Named Placeholders
$students_data = [];
$is_filtered = (!empty($challan_type) && strtolower($challan_type) !== 'all');

try {
    /* Base relational query structure */
    $base_query = "SELECT 
                    s.id as student_uniq_id,
                    s.reg_id,
                    s.first_name,
                    s.last_name,
                    s.class_id,
                    s.section_id,
                    c.class_name, 
                    sec.section_name,
                    ft.name as fee_component_name,
                    fs.amount as fee_component_amount
                   FROM students s 
                   JOIN classes c ON s.class_id = c.id 
                   LEFT JOIN sections sec ON s.section_id = sec.id
                   JOIN fee_structures fs ON fs.class_id = s.class_id AND fs.academic_year_id = s.academic_year_id
                   JOIN fee_types ft ON fs.fee_type_id = ft.id";

    $params = [];
    $where_clauses = [];

    // Apply main scope filter
    if ($type === 'student') {
        $where_clauses[] = "(s.id = :student_id OR s.reg_id = :reg_id)";
        $params[':student_id'] = $id;
        $params[':reg_id'] = $id;
    } elseif ($type === 'class') {
        $where_clauses[] = "s.class_id = :class_id";
        $params[':class_id'] = $id;
    } elseif ($type === 'section') {
        $where_clauses[] = "s.section_id = :section_id";
        $params[':section_id'] = $id;
    } else {
        die("Error: Invalid collection target context type specified.");
    }

    // Save base statements before adding dynamic filters
    $base_where = $where_clauses;
    $base_params = $params;

    // Apply smart mapping for filtering fee types
    if ($is_filtered) {
        if (strtolower($challan_type) === 'monthly fee') {
            // If "Monthly Fee" is passed, filter by either "Tuition Fee" OR "Monthly Fee"
            $where_clauses[] = "(LOWER(ft.name) LIKE :type_exact OR LOWER(ft.name) LIKE :type_alt)";
            $params[':type_exact'] = '%monthly fee%';
            $params[':type_alt'] = '%tuition fee%';
        } elseif (strtolower($challan_type) === 'exam fee' || strtolower($challan_type) === 'exams fee') {
            // Consolidate singular/plural exam name variants
            $where_clauses[] = "(LOWER(ft.name) LIKE :type_exam)";
            $params[':type_exam'] = '%exam%';
        } else {
            // General fuzzy string mapping rule for everything else
            $where_clauses[] = "LOWER(ft.name) LIKE :challan_type";
            $params[':challan_type'] = '%' . strtolower($challan_type) . '%';
        }
    }

    // Attempt the filtered query run
    $sql = $base_query . " WHERE " . implode(" AND ", $where_clauses) . " ORDER BY s.id ASC, ft.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_records = $stmt->fetchAll();

    // SMART AUTOMATIC FALLBACK: Only runs if the filtered component still returns 0 items
    if (empty($raw_records) && $is_filtered) {
        $is_filtered = false; 
        $sql = $base_query . " WHERE " . implode(" AND ", $base_where) . " ORDER BY s.id ASC, ft.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($base_params);
        $raw_records = $stmt->fetchAll();
    }

    // Group items by unique student matrix rows
    foreach ($raw_records as $row) {
        $st_id = $row['student_uniq_id'];
        if (!isset($students_data[$st_id])) {
            $students_data[$st_id] = [
                'id'           => $row['student_uniq_id'],
                'reg_id'       => $row['reg_id'],
                'first_name'   => $row['first_name'],
                'last_name'    => $row['last_name'],
                'class_name'   => $row['class_name'],
                'section_name' => $row['section_name'],
                'fees'         => []
            ];
        }
        if ($row['fee_component_amount'] > 0) {
            $students_data[$st_id]['fees'][] = [
                'name'   => $row['fee_component_name'],
                'amount' => $row['fee_component_amount']
            ];
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if (empty($students_data)) {
    die("No active student configurations or base fee structures exist in the database for this selection.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Fee Challans - Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .challan-page {
                page-break-after: always;
            }
        }

        body {
            background: #f0f0f0;
            font-size: 11px;
        }

        .challan-container {
            display: flex;
            background: white;
            padding: 15px;
            border-bottom: 1px dashed #000;
            margin-bottom: 20px;
        }

        .challan-copy {
            flex: 1;
            padding: 10px;
            border: 1px solid #000;
            margin: 2px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .academy-header {
            text-align: center;
            border-bottom: 2px solid #000;
            margin-bottom: 10px;
        }

        .fee-table {
            width: 100%;
            margin-top: 10px;
        }

        .fee-table th,
        .fee-table td {
            border-bottom: 1px solid #ddd;
            padding: 4px 0;
        }

        .total-row {
            font-weight: bold;
            font-size: 13px;
        }

        .footer-sign {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>

<body>

    <div class="container mt-3 no-print">
        <div class="alert alert-dark d-flex justify-content-between align-items-center">
            <span>
                Active Target Component: <strong><?= $is_filtered ? htmlspecialchars(ucfirst($challan_type)) : "All Fees" ?></strong> | 
                Ready to print <strong><?= count($students_data) ?></strong> challan card(s).
            </span>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Click to Print / Save PDF</button>
        </div>
    </div>

    <?php foreach ($students_data as $student): ?>
        <div class="challan-page">
            <div class="challan-container">
                <?php
                $copies = ['Bank Copy', 'Office Copy', 'Student Copy'];
                foreach ($copies as $copy_name):
                ?>
                    <div class="challan-copy">
                        <div>
                            <div class="academy-header">
                                <h6 class="mb-0 fw-bold">SMART CHOICE ACADEMY</h6>
                                <small>Gujranwala | 03xx-xxxxxxx</small><br>
                                <span class="badge bg-dark text-white my-1"><?= $copy_name ?></span>
                            </div>

                            <div class="mt-2">
                                <strong>Challan #:</strong> <?= date('Ym') . $student['id'] ?><br>
                                <strong>Date:</strong> <?= date('d-M-Y') ?><br>
                                <strong>Fee Month:</strong> <?= date('F-Y', strtotime($fee_month . '-01')) ?><br>
                                <strong>Reg ID:</strong> <?= htmlspecialchars($student['reg_id']) ?><br>
                                <strong>Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?><br>
                                <strong>Class:</strong> <?= htmlspecialchars($student['class_name']) ?> (<?= htmlspecialchars($student['section_name'] ?? 'N/A') ?>)
                            </div>

                            <table class="fee-table">
                                <thead>
                                    <tr class="text-secondary small">
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $grand_total = 0;
                                    foreach ($student['fees'] as $fee): 
                                        $grand_total += $fee['amount'];
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($fee['name']) ?></td>
                                            <td class="text-end">Rs. <?= number_format($fee['amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if(empty($student['fees'])): ?>
                                        <tr>
                                            <td class="text-muted fst-italic">No active fee rows found</td>
                                            <td class="text-end">Rs. 0.00</td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr class="total-row">
                                        <td class="pt-2 border-top border-dark text-uppercase">Total Due</td>
                                        <td class="text-end pt-2 border-top border-dark text-primary">Rs. <?= number_format($grand_total, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <div class="mt-3 small text-muted" style="font-size: 10px; line-height: 1.2;">
                                <p class="mb-0">Note: Please pay dues before 10th of every month. Late fine Rs. 100 applies after due date.</p>
                            </div>

                            <div class="footer-sign">
                                <small>________________<br>Depositor</small>
                                <small>________________<br>Authorized Sign</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

</body>

</html>