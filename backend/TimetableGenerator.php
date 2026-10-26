<?php

/**
 * Smart Choice Academy Timetable Generation Engine
 * Heuristic Backtracking Solver Matrix Layer with Overload Protection
 */

class TimetableGenerator
{
    private $pdo;
    private $academic_year_id;
    private $days = [];
    private $slots = [];
    private $assignments = [];

    // Overload configuration
    private $max_periods_per_teacher = 20; // Maximum periods per week per teacher
    private $target_periods_per_subject = 4; // Target periods per subject per week
    private $min_periods_per_subject = 3; // Minimum periods per subject per week
    private $max_periods_per_section = 35; // Maximum periods per section per week

    // Matrix tracking maps
    private $teacher_lookup = [];
    private $section_lookup = [];
    private $teacher_weekly_count = [];
    private $section_weekly_count = [];
    private $subject_teacher_count = [];

    /**
     * Initialize Engine with System Database State Context
     */
    public function __construct(\PDO $pdo, $academic_year_id, $max_periods_per_teacher = 20)
    {
        $this->pdo = $pdo;
        $this->academic_year_id = (int)$academic_year_id;
        $this->max_periods_per_teacher = $max_periods_per_teacher;
        $this->loadStructuralMetadata();
        $this->initializeTrackingMaps();
    }

    /**
     * Cache Structural Working Framework Bounds
     */
    private function loadStructuralMetadata()
    {
        try {
            // 1. Fetch Working Days
            $result = $this->pdo->query("SELECT id FROM school_days WHERE is_working = 1 ORDER BY id");
            if ($result) {
                $this->days = $result->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                $this->days = [];
            }

            // 2. Fetch Core Lecture Slots (Filter out breaks)
            $result = $this->pdo->query("SELECT id FROM time_slots WHERE is_break = 0 ORDER BY period_number");
            if ($result) {
                $this->slots = $result->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                $this->slots = [];
            }

            // 3. Fetch Assigned Workloads - FIXED: Removed course_name reference that caused error
            $stmt = $this->pdo->prepare("
                SELECT 
                    ta.id, 
                    s.class_id, 
                    ta.teacher_id, 
                    ta.course_id, 
                    ta.section_id,
                    c.course_name as course_name,
                    u.full_name as teacher_name
                FROM teacher_assignments ta
                INNER JOIN sections s ON ta.section_id = s.id
                INNER JOIN classes cls ON s.class_id = cls.id
                INNER JOIN courses c ON ta.course_id = c.id
                INNER JOIN teachers t ON ta.teacher_id = t.teacher_id
                INNER JOIN users u ON t.user_id = u.id
                WHERE ta.academic_year_id = ? 
                  AND s.class_id IS NOT NULL 
                  AND s.class_id != 0
                  AND t.status = 'active'
                ORDER BY ta.id ASC
            ");
            $stmt->execute([$this->academic_year_id]);
            $this->assignments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error loading structural metadata: " . $e->getMessage());
            $this->days = [];
            $this->slots = [];
            $this->assignments = [];
        }
    }

    /**
     * Initialize tracking arrays
     */
    private function initializeTrackingMaps()
    {
        // Initialize teacher weekly counts
        foreach ($this->assignments as $assignment) {
            $teacher_id = $assignment['teacher_id'];
            if (!isset($this->teacher_weekly_count[$teacher_id])) {
                $this->teacher_weekly_count[$teacher_id] = 0;
            }
            if (!isset($this->subject_teacher_count[$teacher_id])) {
                $this->subject_teacher_count[$teacher_id] = [];
            }
            $course_id = $assignment['course_id'];
            if (!isset($this->subject_teacher_count[$teacher_id][$course_id])) {
                $this->subject_teacher_count[$teacher_id][$course_id] = 0;
            }
        }

        // Initialize section weekly counts
        foreach ($this->assignments as $assignment) {
            $section_id = $assignment['section_id'];
            if (!isset($this->section_weekly_count[$section_id])) {
                $this->section_weekly_count[$section_id] = 0;
            }
        }
    }

    /**
     * Check if a teacher has reached their weekly limit
     */
    private function isTeacherOverloaded($teacher_id)
    {
        $current_load = $this->teacher_weekly_count[$teacher_id] ?? 0;
        return $current_load >= $this->max_periods_per_teacher;
    }

    /**
     * Check if a section has reached its weekly limit
     */
    private function isSectionOverloaded($section_id)
    {
        $current_load = $this->section_weekly_count[$section_id] ?? 0;
        return $current_load >= $this->max_periods_per_section;
    }

    /**
     * Check if a subject has reached its target for a teacher
     */
    private function hasReachedSubjectTarget($teacher_id, $course_id)
    {
        $current_count = $this->subject_teacher_count[$teacher_id][$course_id] ?? 0;
        return $current_count >= $this->target_periods_per_subject;
    }

    /**
     * Find the best available slot for a given workload
     */
    private function findBestSlot($day_ids, $slot_ids, $teacher_id, $section_id)
    {
        // Shuffle for variety but maintain order
        $shuffled_days = $day_ids;
        $shuffled_slots = $slot_ids;
        shuffle($shuffled_days);
        shuffle($shuffled_slots);

        foreach ($shuffled_days as $day_id) {
            foreach ($shuffled_slots as $slot_id) {
                // Check constraints
                $is_teacher_busy = isset($this->teacher_lookup[$day_id][$slot_id][$teacher_id]);
                $is_section_busy = isset($this->section_lookup[$day_id][$slot_id][$section_id]);

                if (!$is_teacher_busy && !$is_section_busy) {
                    return ['day_id' => $day_id, 'slot_id' => $slot_id];
                }
            }
        }

        return null;
    }

    /**
     * Run Heuristic Core Allocation Solver Loop with Overload Protection
     */
    public function generate()
    {
        // Safety Fallback Check
        if (empty($this->days) || empty($this->slots)) {
            return [
                'success' => false,
                'message' => 'No days or slots configured in the system'
            ];
        }

        if (empty($this->assignments)) {
            return [
                'success' => false,
                'message' => 'No teacher assignments found for this academic year'
            ];
        }

        try {
            $this->pdo->beginTransaction();

            // Clear old entries for this academic frame context
            $clearStmt = $this->pdo->prepare("DELETE FROM timetable_records WHERE academic_year_id = ?");
            $clearStmt->execute([$this->academic_year_id]);

            // Reset Lookup Allocation Matrix Tracking Maps
            $this->teacher_lookup = [];
            $this->section_lookup = [];
            $this->teacher_weekly_count = [];
            $this->section_weekly_count = [];
            $this->subject_teacher_count = [];

            $this->initializeTrackingMaps();

            // Prepared Statement for fast batch insertion
            $insertStmt = $this->pdo->prepare("
                INSERT INTO timetable_records (academic_year_id, day_id, slot_id, class_id, section_id, course_id, teacher_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $total_records_inserted = 0;
            $failed_assignments = [];
            $overload_warnings = [];

            // Sort assignments by priority
            usort($this->assignments, function ($a, $b) {
                $a_count = $this->section_weekly_count[$a['section_id']] ?? 0;
                $b_count = $this->section_weekly_count[$b['section_id']] ?? 0;
                return $a_count - $b_count;
            });

            // Loop through each operational workload allocation requirement
            foreach ($this->assignments as $workload) {
                $class_id   = $workload['class_id'];
                $teacher_id = $workload['teacher_id'];
                $course_id  = $workload['course_id'];
                $section_id = $workload['section_id'];
                $course_name = $workload['course_name'] ?? 'Unknown';
                $teacher_name = $workload['teacher_name'] ?? 'Unknown';

                // Check if teacher is already overloaded
                if ($this->isTeacherOverloaded($teacher_id)) {
                    if (!in_array("Teacher '{$teacher_name}' has reached maximum weekly load", $overload_warnings)) {
                        $overload_warnings[] = "Teacher '{$teacher_name}' has reached maximum weekly load ({$this->max_periods_per_teacher} periods)";
                    }
                    continue;
                }

                // Check if section is overloaded
                if ($this->isSectionOverloaded($section_id)) {
                    $overload_warnings[] = "Section ID {$section_id} has reached maximum weekly load";
                    continue;
                }

                // Check if subject already reached target
                if ($this->hasReachedSubjectTarget($teacher_id, $course_id)) {
                    continue;
                }

                $allocated_count = 0;
                $max_attempts = count($this->days) * count($this->slots);
                $attempts = 0;

                // Try to allocate periods for this subject
                while ($allocated_count < $this->target_periods_per_subject && $attempts < $max_attempts) {
                    $attempts++;

                    // Check if teacher became overloaded
                    if ($this->isTeacherOverloaded($teacher_id)) {
                        break;
                    }

                    // Check if section became overloaded
                    if ($this->isSectionOverloaded($section_id)) {
                        break;
                    }

                    // Find best available slot
                    $best_slot = $this->findBestSlot($this->days, $this->slots, $teacher_id, $section_id);

                    if ($best_slot === null) {
                        break;
                    }

                    // Execute batch generation record save
                    $insertStmt->execute([
                        $this->academic_year_id,
                        $best_slot['day_id'],
                        $best_slot['slot_id'],
                        $class_id,
                        $section_id,
                        $course_id,
                        $teacher_id
                    ]);

                    // Flag maps to block subsequent clashing double-bookings
                    $this->teacher_lookup[$best_slot['day_id']][$best_slot['slot_id']][$teacher_id] = true;
                    $this->section_lookup[$best_slot['day_id']][$best_slot['slot_id']][$section_id] = true;

                    // Update counters
                    $this->teacher_weekly_count[$teacher_id]++;
                    $this->section_weekly_count[$section_id]++;
                    $this->subject_teacher_count[$teacher_id][$course_id]++;

                    $allocated_count++;
                    $total_records_inserted++;
                }

                // Check if we couldn't allocate minimum periods
                if ($allocated_count < $this->min_periods_per_subject) {
                    $failed_assignments[] = [
                        'course' => $course_name,
                        'teacher' => $teacher_name,
                        'section' => $section_id,
                        'allocated' => $allocated_count,
                        'required' => $this->min_periods_per_subject
                    ];
                }
            }

            if ($total_records_inserted === 0) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'No timetable records were generated. Check assignments and availability.',
                    'warnings' => $overload_warnings
                ];
            }

            $this->pdo->commit();

            // Generate summary statistics
            $summary = $this->generateSummary();

            return [
                'success' => true,
                'message' => "Successfully generated {$total_records_inserted} timetable records",
                'total_records' => $total_records_inserted,
                'failed_assignments' => $failed_assignments,
                'overload_warnings' => $overload_warnings,
                'summary' => $summary
            ];
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'error_code' => $e->errorInfo[1] ?? null
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Error generating timetable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate summary statistics after generation
     */
    private function generateSummary()
    {
        $summary = ['teachers' => [], 'sections' => []];

        // Teacher workload summary
        foreach ($this->teacher_weekly_count as $teacher_id => $count) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT u.full_name 
                    FROM teachers t 
                    JOIN users u ON t.user_id = u.id 
                    WHERE t.teacher_id = ?
                ");
                $stmt->execute([$teacher_id]);
                $teacher = $stmt->fetch(\PDO::FETCH_ASSOC);

                $status = $count > $this->max_periods_per_teacher ? 'Overloaded' : ($count < $this->max_periods_per_teacher / 2 ? 'Underloaded' : 'Balanced');

                $summary['teachers'][] = [
                    'name' => $teacher['full_name'] ?? "Teacher #{$teacher_id}",
                    'periods' => $count,
                    'max_allowed' => $this->max_periods_per_teacher,
                    'status' => $status,
                    'utilization' => round(($count / $this->max_periods_per_teacher) * 100, 1)
                ];
            } catch (\Exception $e) {
                // Skip if can't fetch teacher name
                $summary['teachers'][] = [
                    'name' => "Teacher #{$teacher_id}",
                    'periods' => $count,
                    'max_allowed' => $this->max_periods_per_teacher,
                    'status' => 'Unknown',
                    'utilization' => 0
                ];
            }
        }

        // Sort teachers by workload (highest first)
        usort($summary['teachers'], function ($a, $b) {
            return $b['periods'] - $a['periods'];
        });

        return $summary;
    }

    /**
     * Get current teacher workload status
     */
    public function getTeacherWorkloadStatus()
    {
        $status = [];
        foreach ($this->teacher_weekly_count as $teacher_id => $count) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT u.full_name, t.employee_id
                    FROM teachers t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.teacher_id = ?
                ");
                $stmt->execute([$teacher_id]);
                $teacher = $stmt->fetch(\PDO::FETCH_ASSOC);

                $status[] = [
                    'teacher_id' => $teacher_id,
                    'name' => $teacher['full_name'] ?? 'Unknown',
                    'employee_id' => $teacher['employee_id'] ?? 'N/A',
                    'periods_allocated' => $count,
                    'max_periods' => $this->max_periods_per_teacher,
                    'remaining_capacity' => max(0, $this->max_periods_per_teacher - $count),
                    'is_overloaded' => $count > $this->max_periods_per_teacher,
                    'utilization_percentage' => round(($count / $this->max_periods_per_teacher) * 100, 1)
                ];
            } catch (\Exception $e) {
                $status[] = [
                    'teacher_id' => $teacher_id,
                    'name' => "Teacher #{$teacher_id}",
                    'employee_id' => 'N/A',
                    'periods_allocated' => $count,
                    'max_periods' => $this->max_periods_per_teacher,
                    'remaining_capacity' => max(0, $this->max_periods_per_teacher - $count),
                    'is_overloaded' => $count > $this->max_periods_per_teacher,
                    'utilization_percentage' => round(($count / $this->max_periods_per_teacher) * 100, 1)
                ];
            }
        }

        usort($status, function ($a, $b) {
            return $b['periods_allocated'] - $a['periods_allocated'];
        });

        return $status;
    }

    /**
     * Set maximum periods per teacher
     */
    public function setMaxPeriodsPerTeacher($max_periods)
    {
        $this->max_periods_per_teacher = (int)$max_periods;
        return $this;
    }

    /**
     * Set target periods per subject
     */
    public function setTargetPeriodsPerSubject($target)
    {
        $this->target_periods_per_subject = (int)$target;
        return $this;
    }

    /**
     * Validate if a schedule is feasible before generation
     */
    public function validateFeasibility()
    {
        $total_required_periods = count($this->assignments) * $this->target_periods_per_subject;
        $total_available_slots = count($this->days) * count($this->slots);

        // Calculate teacher capacity
        $unique_teachers = array_unique(array_column($this->assignments, 'teacher_id'));
        $teacher_capacity = count($unique_teachers) * $this->max_periods_per_teacher;

        $warnings = [];

        if ($total_required_periods > $total_available_slots) {
            $warnings[] = "Total required periods ({$total_required_periods}) exceeds available slots ({$total_available_slots})";
        }

        if ($total_required_periods > $teacher_capacity) {
            $warnings[] = "Total required periods ({$total_required_periods}) exceeds total teacher capacity ({$teacher_capacity})";
        }

        return [
            'is_feasible' => empty($warnings),
            'warnings' => $warnings,
            'total_required_periods' => $total_required_periods,
            'total_available_slots' => $total_available_slots,
            'total_teacher_capacity' => $teacher_capacity,
            'teachers_count' => count($unique_teachers),
            'assignments_count' => count($this->assignments),
            'days_count' => count($this->days),
            'slots_per_day' => count($this->slots)
        ];
    }
}
