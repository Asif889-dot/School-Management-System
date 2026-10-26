<?php

/**
 * Teacher Sidebar Navigation with Permission-based Access
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

/**
 * Check if user has permission to view a menu item
 * @param string|null $permission The permission key required
 * @return bool True if user has permission or no permission required
 */
function hasMenuPermission($permission)
{
    // If no permission required, allow access
    if (empty($permission)) {
        return true;
    }
    
    // Admin has all permissions
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Check if user has the specific permission
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']);
    }
    
    return false;
}

// Define menu items with permission requirements
$menuItems = [
    'Main' => [
        [
            'icon' => 'bi-grid-1x2-fill',
            'label' => 'Dashboard',
            'link'  => 'dashboard.php',
            'permission' => null
        ],
        [
            'icon' => 'bi-people-fill',
            'label' => 'Students',
            'link'  => 'students.php',
            'permission' => 'students_view'
        ],
        [
            'icon' => 'bi-book-fill',
            'label' => 'Courses',
            'link'  => 'courses.php',
            'permission' => 'courses_view'
        ],
        [
            'icon' => 'bi-calendar-check',
            'label' => 'Attendance',
            'link'  => 'attendance.php',
            'permission' => 'attendance_view'
        ],
[
            'icon' => 'bi-award',
            'label' => 'Grades',
            'permission' => 'marks_entry',
            'dropdown' => [
                ['label' => 'Enter Marks', 'link' => 'marks_entry.php', 'permission' => 'marks_entry'],
                ['label' => 'My Students', 'link' => 'students.php', 'permission' => 'students_view'],
            ]
        ],
[
            'icon' => 'bi-clock',
            'label' => 'Timetable',
            'link'  => 'timetable.php',
            'permission' => 'courses_view'
        ],
        [
            'icon' => 'bi-journal-bookmark',
            'label' => 'Study Plans',
            'link'  => 'upload_study_plan.php',
            'permission' => 'courses_edit'
        ],
        [
            'icon' => 'bi-journal-text',
            'label' => 'Daily Diary',
            'link'  => 'upload_diary.php',
            'permission' => 'courses_edit'
        ],
        [
            'icon' => 'bi-megaphone',
            'label' => 'Notices',
            'link'  => 'view_notices.php',
            'permission' => null
        ],
        [
            'icon' => 'bi-chat-dots',
            'label' => 'Parent Messages',
            'link'  => 'reply_messages.php',
            'permission' => null
        ],
        [
            'icon' => 'bi-trophy',
            'label' => 'Award Lists',
            'link'  => 'download_awards.php',
            'permission' => 'courses_view'
        ],
    ],

    'Account' => [
        [
            'icon' => 'bi-person',
            'label' => 'My Profile',
            'link'  => 'profile.php',
            'permission' => null
        ],
        [
            'icon' => 'bi-box-arrow-left',
            'label' => 'Logout',
            'link'  => '../../backend/logout.php',
            'permission' => null
        ],
    ]
];

/**
 * Filter menu items based on user permissions
 * @param array $items Menu items array
 * @return array Filtered menu items
 */
function filterMenuByPermission($items)
{
    $filtered = [];
    
    foreach ($items as $section => $sectionItems) {
        $validItems = [];
        
        foreach ($sectionItems as $item) {
            // Check main item permission
            $hasPermission = hasMenuPermission($item['permission'] ?? null);
            
            if (!$hasPermission) {
                continue;
            }
            
            // Check dropdown items permissions
            if (isset($item['dropdown']) && is_array($item['dropdown'])) {
                $validDropdowns = [];
                foreach ($item['dropdown'] as $dropdown) {
                    $dropdownPerm = $dropdown['permission'] ?? null;
                    if (hasMenuPermission($dropdownPerm)) {
                        $validDropdowns[] = $dropdown;
                    }
                }
                
                // Only add dropdown if at least one item is visible
                if (!empty($validDropdowns)) {
                    $item['dropdown'] = $validDropdowns;
                    $validItems[] = $item;
                }
            } else {
                $validItems[] = $item;
            }
        }
        
        // Only add section if it has at least one item
        if (!empty($validItems)) {
            $filtered[$section] = $validItems;
        }
    }
    
    return $filtered;
}

// Filter menu items based on user permissions
$menuItems = filterMenuByPermission($menuItems);
?>

<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-mortarboard-fill"></i>
        <span>SMS Teacher</span>
    </div>

    <div class="sidebar-menu">

        <?php foreach ($menuItems as $section => $items): ?>
            <div class="menu-section"><?= $section ?></div>

            <?php foreach ($items as $item): ?>

                <?php if (isset($item['dropdown'])): ?>
                    <?php $submenuId = md5($item['label']); ?>
                    <div class="menu-item-dropdown">

                        <a href="#"
                            class="menu-item has-dropdown"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $submenuId ?>">

                            <i class="bi <?= $item['icon'] ?>"></i>
                            <span><?= $item['label'] ?></span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>

                        <div class="submenu collapse <?= isDropdownOpen($item['dropdown']) ?>"
                            id="<?= $submenuId ?>">

                            <?php foreach ($item['dropdown'] as $sub): ?>
                                <a href="<?= $sub['link'] ?>"
                                    class="submenu-item <?= isActive($sub['link']) ?>">
                                    <?= $sub['label'] ?>
                                </a>
                            <?php endforeach; ?>

                        </div>
                    </div>

                <?php else: ?>

                    <a href="<?= $item['link'] ?>"
                        class="menu-item <?= isActive($item['link']) ?>">
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

        <?php endforeach; ?>

    </div>
</nav>
