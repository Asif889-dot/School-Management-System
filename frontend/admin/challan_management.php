<?php
require_once '../../backend/auth.php';
require_once '../../backend/config.php';
requireLogin();

// Fetch target references for select lists
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
$all_sections = $pdo->query("SELECT id, class_id, section_name FROM sections ORDER BY section_name ASC")->fetchAll();

// NEW: Dynamically fetch active fee component types configured in the system
try {
    $fee_types = $pdo->query("SELECT id, name FROM fee_types ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $fee_types = []; // Safe fallback array array if table structure differs
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Challan Management | Smart Choice Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Inter', sans-serif;
            display: flex;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            width: 100%;
        }

        .card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1) !important;
        }

        .icon-box {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.6rem;
            margin-bottom: 20px;
        }

        #student_results {
            position: absolute;
            width: 100%;
            z-index: 1050;
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .settings-bar {
            background: #fff;
            border-radius: 15px;
            border-left: 5px solid #764ba2;
        }
    </style>
</head>

<body>

    <?php include '../../backend/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <div class="row align-items-center mb-4">
                <div class="col-md-7">
                    <h2 class="fw-bold text-dark mb-1">Challan Control Center</h2>
                    <p class="text-muted">Generate fee invoices for Smart Choice Academy.</p>
                </div>
                <div class="col-md-5 text-md-end">
                    <button onclick="location.reload()" class="btn btn-primary shadow-sm px-3">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>

            <div class="card settings-bar shadow-sm p-4 mb-5">
                <div class="row align-items-center">
                    <div class="col-md-1 text-center d-none d-md-block">
                        <i class="bi bi-gear-fill fs-2 text-primary"></i>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-uppercase">Challan Type</label>
                        <select id="global_type" class="form-select border-0 bg-light fw-bold text-primary">
                            <!-- Safe fallback value to run aggregate calculations -->
                            <option value="all">-- All Combined Fees --</option>
                            
                            <!-- Dynamic rendering from DB table configuration matrix rows -->
                            <?php foreach ($fee_types as $ft): ?>
                                <option value="<?= htmlspecialchars($ft['name']) ?>">
                                    <?= htmlspecialchars($ft['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-uppercase">Payment Month</label>
                        <input type="month" id="global_month" class="form-select border-0 bg-light fw-bold" value="<?= date('Y-m') ?>">
                    </div>
                    <div class="col-md-5">
                        <p class="mb-0 small text-muted mt-2 mt-md-0">
                            <i class="bi bi-info-circle me-1"></i> These settings will apply to <strong>all</strong> generated challans below.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100 shadow-sm p-4">
                        <div class="icon-box"><i class="bi bi-person-vcard"></i></div>
                        <h5 class="fw-bold">Student Search</h5>
                        <p class="small text-muted mb-4">Live search by Name or Registration ID.</p>

                        <form class="challan-form" action="generate_challan.php" method="GET" target="_blank" autocomplete="off">
                            <input type="hidden" name="type" value="student">
                            <input type="hidden" name="id" id="selected_student_id">

                            <div class="mb-4 position-relative">
                                <label class="form-label text-uppercase">Find Student</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
                                    <input type="text" id="student_input" class="form-control border-start-0" placeholder="Type name or ID..." required>
                                </div>
                                <div id="student_results" class="list-group shadow"></div>
                            </div>
                            <button type="submit" id="gen_btn_student" class="btn btn-primary w-100 btn-generate" disabled>Generate Now</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100 shadow-sm p-4 border-top border-4 border-primary">
                        <div class="icon-box" style="background: #eef2ff; color: #4f46e5;"><i class="bi bi-mortarboard"></i></div>
                        <h5 class="fw-bold">Class Wise Bulk</h5>
                        <p class="small text-muted mb-4">Generate challans for an entire class.</p>

                        <form class="challan-form" action="generate_challan.php" method="GET" target="_blank">
                            <input type="hidden" name="type" value="class">
                            <div class="mb-4">
                                <label class="form-label text-uppercase">Select Class</label>
                                <select name="id" class="form-select bg-light" required>
                                    <option value="">Choose Class...</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= $c['class_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 btn-generate">Create Class Bulk</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100 shadow-sm p-4">
                        <div class="icon-box" style="background: #fdf2f2; color: #dc2626;"><i class="bi bi-layers"></i></div>
                        <h5 class="fw-bold">Section Filter</h5>
                        <p class="small text-muted mb-4">Filter generation by class section.</p>

                        <form class="challan-form" action="generate_challan.php" method="GET" target="_blank">
                            <input type="hidden" name="type" value="section">
                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <label class="form-label text-uppercase">Class</label>
                                    <select id="filter_class" class="form-select bg-light">
                                        <option value="">Select...</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= $c['class_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-uppercase">Section</label>
                                    <select name="id" id="filter_section" class="form-select bg-light" required disabled>
                                        <option value="">Wait...</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary w-100 btn-generate">Generate Section</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const sectionsData = <?= json_encode($all_sections) ?>;

        // 1. Dynamic Section Filtering logic
        document.getElementById('filter_class').addEventListener('change', function() {
            const classId = this.value;
            const sectionSelect = document.getElementById('filter_section');
            sectionSelect.innerHTML = '<option value="">Choose...</option>';
            if (classId) {
                const filtered = sectionsData.filter(sec => sec.class_id == classId);
                filtered.forEach(sec => {
                    const opt = document.createElement('option');
                    opt.value = sec.id;
                    opt.textContent = sec.section_name;
                    sectionSelect.appendChild(opt); brush
                });
                sectionSelect.disabled = false;
            } else {
                sectionSelect.disabled = true;
            }
        });

        // 2. AJAX Live Search Variables
        const studentInput = document.getElementById('student_input');
        const resultsBox = document.getElementById('student_results');
        const hiddenIdInput = document.getElementById('selected_student_id');
        const genBtn = document.getElementById('gen_btn_student');

        studentInput.addEventListener('input', function() {
            const query = this.value;
            if (query.length < 2) {
                resultsBox.innerHTML = '';
                if (genBtn) genBtn.disabled = true;
                return;
            }
            fetch(`../../backend/ajax_search_students.php?q=${encodeURIComponent(query)}`)
                .then(res => res.text())
                .then(data => {
                    resultsBox.innerHTML = data;
                })
                .catch(err => console.error('Error:', err));
        });

        function selectStudent(id, displayText) {
            hiddenIdInput.value = id;
            studentInput.value = displayText;
            resultsBox.innerHTML = '';
            if (genBtn) genBtn.disabled = false;
        }

        // 3. Global Settings Sync Interceptor
        document.querySelectorAll('.challan-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                // Remove any previously appended injection nodes to prevent duplicate data params
                this.querySelectorAll('.injected-setting').forEach(el => el.remove());

                const gType = document.getElementById('global_type').value;
                const gMonth = document.getElementById('global_month').value;

                // Create input for challan type filter
                let typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.className = 'injected-setting';
                typeInput.name = 'challan_type';
                typeInput.value = gType;
                this.appendChild(typeInput);

                // Create input for billing targets month range 
                let monthInput = document.createElement('input');
                monthInput.type = 'hidden';
                monthInput.className = 'injected-setting';
                monthInput.name = 'fee_month';
                monthInput.value = gMonth;
                this.appendChild(monthInput);
            });
        });

        document.addEventListener('click', (e) => {
            if (e.target !== studentInput) resultsBox.innerHTML = '';
        });
    </script>
</body>

</html>