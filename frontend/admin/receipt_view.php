<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

$receipt_no = $_GET['receipt'] ?? '';

if (empty($receipt_no)) {
    die("Error: No receipt number provided.");
}

// Fetch payment details and student info
$stmt = $pdo->prepare("
    SELECT fp.*, s.first_name, s.last_name, s.reg_id, c.class_name, sec.section_name, ft.name as fee_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    JOIN fee_structures fs ON fp.fee_structure_id = fs.id
    JOIN fee_types ft ON fs.fee_type_id = ft.id
    WHERE fp.receipt_no = ?
");
$stmt->execute([$receipt_no]);
$payments = $stmt->fetchAll();

if (!$payments) {
    die("Error: Receipt not found.");
}

$student = $payments[0];
$total_paid = array_sum(array_column($payments, 'amount_paid'));

// Manual Word Conversion for XAMPP compatibility
function getAmountInWords($number)
{
    $no = floor($number);
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
    $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    return ucwords(($Rupees ? $Rupees : 'Zero') . 'Rupees Only');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receipt_<?= $receipt_no ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #e9ecef;
            font-family: 'Inter', sans-serif;
        }

        /* Receipt Card Styling */
        .receipt-card {
            background: #fff;
            border-top: 4px solid #764ba2;
            padding: 20px;
            position: relative;
            margin: 0 auto;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }

        /* Divider between copies */
        .receipt-divider {
            border-top: 2px dashed #bbb;
            margin: 20px auto;
            max-width: 800px;
            position: relative;
            text-align: center;
        }

        .receipt-divider i {
            position: absolute;
            top: -12px;
            left: 50%;
            background: #e9ecef;
            padding: 0 10px;
            color: #bbb;
        }

        .header-brand {
            color: #764ba2;
            font-weight: 800;
            font-size: 1.3rem;
        }

        .info-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 700;
        }

        .info-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: #212529;
        }

        .stamp {
            border: 2px double #d1d1d1;
            border-radius: 6px;
            padding: 2px 10px;
            display: inline-block;
            color: #d1d1d1;
            font-weight: 800;
            text-transform: uppercase;
            transform: rotate(-10deg);
            font-size: 0.8rem;
        }

        .copy-tag {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 9px;
            font-weight: bold;
            color: #adb5bd;
            border: 1px solid #eee;
            padding: 2px 8px;
            border-radius: 4px;
        }

        @media print {
            @page {
                size: A4;
                margin: 0.5cm;
            }

            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
            }

            .receipt-card {
                border: 1px solid #eee;
                box-shadow: none;
                margin-bottom: 0;
            }

            .receipt-divider i {
                background: white;
            }

            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="container no-print py-3">
        <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm border">
            <div>
                <a href="history_payment.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back</a>
                <!-- <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bi bi-search"></i> Find
                </button> -->
            </div>
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Print One Page</button>
        </div>
    </div>

    <div class="container py-2">

        <?php
        $copies = ["STUDENT COPY", "OFFICE COPY"];
        foreach ($copies as $index => $copy_title):
        ?>
            <div class="receipt-card">
                <div class="copy-tag"><?= $copy_title ?></div>

                <div class="row align-items-center mb-3">
                    <div class="col-7">
                        <div class="header-brand">SMART CHOICE ACADEMY</div>
                        <div class="text-muted extra-small">Main GT Road, Gujranwala | Project of <b>Softzila.com</b></div>
                    </div>
                    <div class="col-5 text-end">
                        <h5 class="text-uppercase fw-bold mb-0">Fee Receipt</h5>
                        <div class="info-value">No: <?= $receipt_no ?> | Date: <?= date('d-M-Y', strtotime($student['payment_date'])) ?></div>
                    </div>
                </div>

                <div class="row g-2 border-top border-bottom py-2 mb-3 bg-light">
                    <div class="col-4">
                        <div class="info-label">Student Name</div>
                        <div class="info-value"><?= $student['first_name'] . ' ' . $student['last_name'] ?></div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="info-label">Reg ID</div>
                        <div class="info-value"><?= $student['reg_id'] ?></div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="info-label">Class</div>
                        <div class="info-value"><?= $student['class_name'] ?> (<?= $student['section_name'] ?>)</div>
                    </div>
                </div>

                <table class="table table-sm mb-2">
                    <thead>
                        <tr class="small text-uppercase text-muted" style="font-size: 10px;">
                            <th>Description</th>
                            <th class="text-center">Method</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr style="font-size: 13px;">
                                <td class="py-1 fw-bold"><?= $p['fee_name'] ?></td>
                                <td class="text-center py-1"><?= $p['payment_method'] ?></td>
                                <td class="text-end py-1 fw-bold">Rs. <?= number_format($p['amount_paid'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-top border-dark">
                            <td colspan="2" class="text-end fw-bold py-1">Total Paid</td>
                            <td class="text-end fw-bold text-primary py-1">Rs. <?= number_format($total_paid, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="p-2 bg-light border-start border-3 border-primary mb-3">
                    <div class="info-label">Amount in Words</div>
                    <div class="fst-italic small fw-bold"><?= getAmountInWords($total_paid) ?></div>
                </div>

                <div class="row align-items-end">
                    <div class="col-6">
                        <div class="stamp">PAID</div>
                    </div>
                    <div class="col-6 text-end">
                        <div style="border-top: 1px solid #333; display:inline-block; min-width:140px;" class="pt-1 mt-3">
                            <div class="info-label">Authorized Signature</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($index == 0): ?>
                <div class="receipt-divider no-print">
                    <i class="bi bi-scissors"></i>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>

    </div>

    <!-- <div class="modal fade no-print" id="searchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Search Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="receipt_view.php" method="GET">
                    <div class="modal-body">
                        <input type="text" name="receipt" class="form-control" placeholder="Receipt Number..." required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">View</button>
                    </div>
                </form>
            </div>
        </div>
    </div> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>