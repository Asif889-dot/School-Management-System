<?php

/**
 * Improved Sidebar Navigation with Permission-based Access - Smart Choice Academy
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($link)
{
    global $currentPage;
    return $currentPage === $link ? 'active' : '';
}

function isDropdownOpen($dropdown)
{
    global $currentPage;
    foreach ($dropdown as $item) {
        if ($currentPage === $item['link']) {
            return 'show';
        }
    }
    return '';
}

function isParentActive($dropdown)
{
    global $currentPage;
    foreach ($dropdown as $item) {
        if ($currentPage === $item['link']) {
            return 'active';
        }
    }
    return '';
}

/**
 * Check if user has permission to view a menu item
 */
function hasMenuPermission($permission)
{
    if (empty($permission)) {
        return true;
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']);
    }

    require_once __DIR__ . '/auth.php';
    $role = $_SESSION['role'] ?? null;
    if ($role) {
        $defaultPerms = getDefaultPermissionsForRole($role);
        return in_array($permission, $defaultPerms);
    }

    return false;
}

// Define menu items
$menuItems = [
'Main' => [
        ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'link' => 'dashboard.php', 'permission' => null],
        ['icon' => 'bi-people-fill', 'label' => 'Students', 'permission' => 'students_view', 'dropdown' => [
            ['label' => 'All Students', 'link' => 'students.php', 'permission' => 'students_view'],
            ['label' => 'Approve Students', 'link' => 'approve_student.php', 'permission' => 'students_approve'],
            ['label' => 'Admission Form', 'link' => 'admission.php', 'permission' => 'students_add'],
            ['label' => 'ID Cards', 'link' => 'student_cards.php', 'permission' => 'students_view'],
            ['label' => 'Promote Students', 'link' => 'promote_students.php', 'permission' => 'students_edit'],
        ]],
        ['icon' => 'bi-person-badge-fill', 'label' => 'Teachers', 'permission' => 'teachers_view', 'dropdown' => [
            ['label' => 'All Teachers', 'link' => 'teachers.php', 'permission' => 'teachers_view'],
            ['label' => 'Add Teacher', 'link' => 'add_teacher.php', 'permission' => 'teachers_add'],
            ['label' => 'Salary Slips', 'link' => 'salary_slips.php', 'permission' => 'teachers_view'],
            ['label' => 'Experience Letters', 'link' => 'experience_letters.php', 'permission' => 'teachers_view'],
        ]],
        ['icon' => 'bi-person-heart', 'label' => 'Guardians', 'permission' => 'guardians_view', 'link' => 'guardians.php'],
        ['icon' => 'bi-megaphone-fill', 'label' => 'Notices', 'permission' => 'settings_view', 'link' => 'notices.php'],
        ['icon' => 'bi-chat-dots-fill', 'label' => 'Parent Messages', 'permission' => 'settings_view', 'link' => 'parent_messages.php'],
    ],

    'Account' => [
        ['icon' => 'bi-sliders', 'label' => 'Fee Settings', 'permission' => 'fee_view', 'dropdown' => [
            ['label' => 'View Fee Structures', 'link' => 'view_fees.php', 'permission' => 'fee_view'],
            ['label' => 'Fee Structure Setup', 'link' => 'fee_setup.php', 'permission' => 'fee_view'],
            ['label' => 'Set Fee Type', 'link' => 'fee_type.php', 'permission' => 'fee_view'],
        ]],
        ['icon' => 'bi-currency-exchange', 'label' => 'Fee Operations', 'permission' => 'fee_view', 'dropdown' => [
            ['label' => 'Fee Collection', 'link' => 'fee_collection.php', 'permission' => 'fee_view'],
            ['label' => 'Receipt View', 'link' => 'history_payment.php', 'permission' => 'fee_view'],
            ['label' => 'Generate Challan', 'link' => 'challan_management.php', 'permission' => 'fee_view'],
        ]],
        ['icon' => 'bi-wallet2', 'label' => 'Accounts Matrix', 'permission' => 'fee_view', 'dropdown' => [
            ['label' => 'Chart of Accounts', 'link' => 'accounts.php', 'permission' => 'fee_view'],
            ['label' => 'Add Account', 'link' => 'add_account.php', 'permission' => 'fee_view'],
            ['label' => 'Add Transaction', 'link' => 'add_transaction.php', 'permission' => 'fee_view'],
            ['label' => 'Journal Voucher', 'link' => 'journal_voucher.php', 'permission' => 'fee_view'],
            ['label' => 'General Ledger', 'link' => 'ledger.php', 'permission' => 'fee_view'],
            ['label' => 'Ledger Mapping', 'link' => 'account_map.php', 'permission' => 'fee_view'],
            ['label' => 'Account Reports', 'link' => 'account_reports.php', 'permission' => 'fee_view'],
        ]],
    ],

    'Academic' => [
        ['icon' => 'bi-mortarboard', 'label' => 'Academic Structure', 'permission' => 'sessions_view', 'dropdown' => [
            ['label' => 'Manage Sessions', 'link' => 'manage_session.php', 'permission' => 'sessions_view'],
            ['label' => 'All Courses', 'link' => 'courses.php', 'permission' => 'courses_view'],
            ['label' => 'Add Course', 'link' => 'add_course.php', 'permission' => 'courses_add'],
        ]],
        ['icon' => 'bi-diagram-3', 'label' => 'Timetable Matrix', 'permission' => 'teachers_view', 'dropdown' => [
            ['label' => 'Add Time Slots', 'link' => 'add_time_slots.php', 'permission' => 'add_time_slots.php'],
            ['label' => 'Assign Subject', 'link' => 'assign_subject.php', 'permission' => 'teachers_add'],
            ['label' => 'Workload View', 'link' => 'timetable_matrix.php', 'permission' => 'teachers_view'],
            ['label' => 'Teacher Availability', 'link' => 'timetable_teachers.php', 'permission' => 'teachers_view'],
            ['label' => 'Design Timetable', 'link' => 'timetable_create.php', 'permission' => 'teachers_add'],
        ]],
        ['icon' => 'bi-journal-check', 'label' => 'Exams Matrix', 'permission' => 'exams_view', 'dropdown' => [
            ['label' => 'Exam Terms', 'link' => 'exam_terms.php', 'permission' => 'exams_manage'],
            ['label' => 'Manage Date Sheet', 'link' => 'datesheet_manage.php', 'permission' => 'exams_manage'],
            ['label' => 'Marks Entry', 'link' => 'marks_entry.php', 'permission' => 'marks_entry'],
            ['label' => 'Academic Report Cards', 'link' => 'report_cards.php', 'permission' => 'exams_view'],
        ]],
['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'permission' => 'attendance_view', 'link' => 'attendance.php'],
        ['icon' => 'bi-calendar-event', 'label' => 'Academic Calendar', 'permission' => 'exams_view', 'link' => 'academic_calendar.php'],
        ['icon' => 'bi-journal-bookmark', 'label' => 'Daily Diary', 'permission' => 'attendance_view', 'link' => 'daily_diary.php'],
        ['icon' => 'bi-book', 'label' => 'Syllabus', 'permission' => 'courses_view', 'link' => 'syllabus.php'],
        ['icon' => 'bi-trophy', 'label' => 'Awards', 'permission' => 'exams_view', 'link' => 'awards.php'],
    ],

    'Settings' => [
        ['icon' => 'bi-gear-fill', 'label' => 'Settings', 'permission' => 'settings_view', 'dropdown' => [
            ['label' => 'General Settings', 'link' => 'settings.php', 'permission' => 'settings_view'],
            ['label' => 'Users & Roles', 'link' => 'users_roles.php', 'permission' => 'users_view'],
            ['label' => 'Add New User', 'link' => 'add_user.php', 'permission' => 'users_manage'],
        ]],
        ['icon' => 'bi-box-arrow-left', 'label' => 'Logout', 'link' => '../../backend/logout.php', 'permission' => null],
    ]
];

function filterMenuByPermission($items)
{
    $filtered = [];
    foreach ($items as $section => $sectionItems) {
        $validItems = [];
        foreach ($sectionItems as $item) {
            if (hasMenuPermission($item['permission'] ?? null)) {
                if (isset($item['dropdown']) && is_array($item['dropdown'])) {
                    $validDropdowns = array_filter($item['dropdown'], fn($sub) => hasMenuPermission($sub['permission'] ?? null));
                    if (!empty($validDropdowns)) {
                        $item['dropdown'] = $validDropdowns;
                        $validItems[] = $item;
                    }
                } else {
                    $validItems[] = $item;
                }
            }
        }
        if (!empty($validItems)) $filtered[$section] = $validItems;
    }
    return $filtered;
}

$menuItems = filterMenuByPermission($menuItems);
?>

<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-mortarboard-fill"></i>
        <span>SCA Admin</span>
    </div>
    <div class="sidebar-menu">
        <?php foreach ($menuItems as $section => $items): ?>
            <div class="menu-section"><?= htmlspecialchars($section) ?></div>
            <?php foreach ($items as $item): ?>
                <?php if (isset($item['dropdown'])): ?>
                    <?php
                    $submenuId = 'menu_' . md5($item['label']);
                    $isOpen = isDropdownOpen($item['dropdown']);
                    $isParentActive = isParentActive($item['dropdown']);
                    ?>
                    <div class="menu-item-dropdown">
                        <a href="#"
                            class="menu-item has-dropdown <?= $isParentActive ?>"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $submenuId ?>"
                            aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                            <i class="bi <?= $item['icon'] ?>"></i>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <i class="bi bi-chevron-down ms-auto dropdown-arrow"></i>
                        </a>
                        <div class="submenu collapse <?= $isOpen ?>" id="<?= $submenuId ?>">
                            <?php foreach ($item['dropdown'] as $sub): ?>
                                <a href="<?= $sub['link'] ?>" class="submenu-item <?= isActive($sub['link']) ?>">
                                    <?= htmlspecialchars($sub['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= $item['link'] ?>" class="menu-item <?= isActive($item['link']) ?>">
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</nav>