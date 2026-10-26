<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($link)
{
    global $currentPage;
    return $currentPage === $link ? 'active' : '';
}

// Define menu items
$menuItems = [
    'Main' => [
        ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'link' => 'dashboard.php'],
    ],
    'Academic' => [
        ['icon' => 'bi-book', 'label' => 'Academic Record', 'link' => 'academic_record.php'],
        ['icon' => 'bi-bar-chart', 'label' => 'Marks & Results', 'link' => 'marks.php'],
        ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'link' => 'attendance.php'],
        ['icon' => 'bi-calendar-event', 'label' => 'Datesheet', 'link' => 'datesheet.php'],
        ['icon' => 'bi-journal-bookmark', 'label' => 'Daily Diary', 'link' => 'daily_diary.php'],
    ],
    'School' => [
        ['icon' => 'bi-megaphone', 'label' => 'Notices', 'link' => 'notices.php'],
        ['icon' => 'bi-calendar-month', 'label' => 'Calendar', 'link' => 'calendar.php'],
    ],
    'Account' => [
        ['icon' => 'bi-person', 'label' => 'My Profile', 'link' => 'profile.php'],
        ['icon' => 'bi-box-arrow-left', 'label' => 'Logout', 'link' => '../../backend/logout.php'],
    ]
];
?>

<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-mortarboard-fill"></i>
        <span>SCA Student</span>
    </div>

    <div class="sidebar-menu">
        <?php foreach ($menuItems as $section => $items): ?>
            <div class="menu-section"><?= $section ?></div>

            <?php foreach ($items as $item): ?>
                <a href="<?= $item['link'] ?>"
                    class="menu-item <?= isActive($item['link']) ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</nav>
