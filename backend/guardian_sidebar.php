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
        ['icon' => 'bi-people-fill', 'label' => 'My Children', 'link' => 'children.php'],
    ],
    'Information' => [
        ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'link' => 'attendance.php'],
        ['icon' => 'bi-bar-chart', 'label' => 'Marks & Results', 'link' => 'marks.php'],
        ['icon' => 'bi-cash-coin', 'label' => 'Fee Records', 'link' => 'fees.php'],
        ['icon' => 'bi-journal-bookmark', 'label' => 'Daily Diary', 'link' => 'daily_diary.php'],
        ['icon' => 'bi-calendar-event', 'label' => 'Datesheet', 'link' => 'datesheet.php'],
        ['icon' => 'bi-megaphone', 'label' => 'Notices', 'link' => 'notices.php'],
    ],
    'Communication' => [
        ['icon' => 'bi-envelope-paper', 'label' => 'Apply Leave', 'link' => 'apply_leave.php'],
        ['icon' => 'bi-chat-dots', 'label' => 'Message Teachers', 'link' => 'messages.php'],
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
        <span>SCA Parent</span>
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
