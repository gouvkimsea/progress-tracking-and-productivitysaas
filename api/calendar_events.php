<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/holidays.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        session_write_close();
        $db = getDbConnection();

        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $country = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'KH';
        $projectId = isset($_GET['project_id']) && $_GET['project_id'] !== 'all' ? (int)$_GET['project_id'] : null;

        if ($month < 1 || $month > 12) $month = (int)date('n');
        if ($year < 2000 || $year > 2100) $year = (int)date('Y');
        if (!in_array($country, ['KH', 'US', 'GLOBAL'], true)) $country = 'KH';

        // 1. Fetch Government and Custom Days Off
        $holidaysByDay = getMonthHolidaysByDay($db, $userId, $year, $month, $country);

        // 2. Fetch User's Projects (courses)
        $projSql = "SELECT id, code, name, category, level, total_modules, completed_modules, progress_pct, 
                           bg_gradient, ring_color, is_starred, status, start_date, due_date, created_at 
                    FROM courses 
                    WHERE user_id = :uid";
        $projParams = ['uid' => $userId];
        if ($projectId !== null) {
            $projSql .= " AND id = :pid";
            $projParams['pid'] = $projectId;
        }
        $projSql .= " ORDER BY is_starred DESC, id ASC";
        $stmtProj = $db->prepare($projSql);
        $stmtProj->execute($projParams);
        $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

        // Map projects by name and id for rapid lookup
        $projectsById = [];
        $projectsByName = [];
        foreach ($projects as $p) {
            $projectsById[$p['id']] = $p;
            $projectsByName[strtolower(trim($p['name']))] = $p;
        }

        // 3. Fetch Milestones linked to user's projects
        $milestonesByDay = [];
        if (!empty($projects)) {
            $projIds = array_keys($projectsById);
            $inClause = implode(',', array_map('intval', $projIds));
            $stmtMilestones = $db->query("SELECT m.id, m.project_id, m.name, m.due_date, m.status, m.created_at,
                                                 c.name as project_name, c.ring_color, c.code as project_code 
                                          FROM milestones m 
                                          JOIN courses c ON m.project_id = c.id 
                                          WHERE m.project_id IN ($inClause) 
                                          ORDER BY m.id ASC");
            $milestones = $stmtMilestones ? $stmtMilestones->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($milestones as $m) {
                if (!empty($m['due_date'])) {
                    $ts = strtotime(str_replace('/', '-', $m['due_date']));
                    if ($ts && (int)date('n', $ts) === $month && (int)date('Y', $ts) === $year) {
                        $dayNum = (int)date('j', $ts);
                        $m['formatted_date'] = date('d-m-Y', $ts);
                        $milestonesByDay[$dayNum][] = $m;
                    }
                }
            }
        }

        // 4. Index Project Start & Due Dates in current month
        $projectEventsByDay = [];
        foreach ($projects as $p) {
            // Check project due date
            if (!empty($p['due_date'])) {
                $tsDue = strtotime(str_replace('/', '-', $p['due_date']));
                if ($tsDue && (int)date('n', $tsDue) === $month && (int)date('Y', $tsDue) === $year) {
                    $dayNum = (int)date('j', $tsDue);
                    $projectEventsByDay[$dayNum][] = [
                        'type' => 'project_due',
                        'project_id' => $p['id'],
                        'project_name' => $p['name'],
                        'project_code' => $p['code'],
                        'ring_color' => $p['ring_color'] ?? '#6C5CE7',
                        'progress_pct' => (int)$p['progress_pct'],
                        'status' => $p['status'] ?? 'In Progress',
                        'date_str' => date('d-m-Y', $tsDue),
                        'title' => "Project Launch / Deadline: {$p['name']}"
                    ];
                }
            }

            // Check project start date
            if (!empty($p['start_date'])) {
                $tsStart = strtotime(str_replace('/', '-', $p['start_date']));
                if ($tsStart && (int)date('n', $tsStart) === $month && (int)date('Y', $tsStart) === $year) {
                    $dayNum = (int)date('j', $tsStart);
                    $projectEventsByDay[$dayNum][] = [
                        'type' => 'project_start',
                        'project_id' => $p['id'],
                        'project_name' => $p['name'],
                        'project_code' => $p['code'],
                        'ring_color' => $p['ring_color'] ?? '#6C5CE7',
                        'progress_pct' => (int)$p['progress_pct'],
                        'status' => $p['status'] ?? 'In Progress',
                        'date_str' => date('d-m-Y', $tsStart),
                        'title' => "Project Kickoff: {$p['name']}"
                    ];
                }
            }
        }

        // 5. Fetch Tasks for User in current month
        $taskSql = "SELECT id, user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log, created_at 
                    FROM tasks 
                    WHERE user_id = :uid AND deleted_at IS NULL";
        $taskParams = ['uid' => $userId];

        if ($projectId !== null && isset($projectsById[$projectId])) {
            $taskSql .= " AND project_name = :pname";
            $taskParams['pname'] = $projectsById[$projectId]['name'];
        }

        $taskSql .= " ORDER BY id ASC";
        $stmtTasks = $db->prepare($taskSql);
        $stmtTasks->execute($taskParams);
        $allTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        $tasksByDay = [];
        $upcomingDeadlines = [];

        foreach ($allTasks as $t) {
            $dStr = !empty($t['due_date']) ? $t['due_date'] : ($t['start_date'] ?? '');
            if (!empty($dStr)) {
                $ts = strtotime(str_replace('/', '-', $dStr));
                if ($ts) {
                    $pNameKey = strtolower(trim($t['project_name'] ?? ''));
                    $matchedProj = $projectsByName[$pNameKey] ?? null;
                    $t['ring_color'] = $matchedProj['ring_color'] ?? '#6C5CE7';
                    $t['project_code'] = $matchedProj['code'] ?? 'PRJ';

                    if ((int)date('n', $ts) === $month && (int)date('Y', $ts) === $year) {
                        $dayNum = (int)date('j', $ts);
                        $tasksByDay[$dayNum][] = $t;
                    }
                    if ($ts >= strtotime('today')) {
                        $upcomingDeadlines[] = [
                            'item_type' => 'task',
                            'task' => $t,
                            'timestamp' => $ts,
                            'formatted_date' => date('l, M j', $ts)
                        ];
                    }
                }
            }
        }

        // Also add project deadlines to upcoming deadlines
        foreach ($projects as $p) {
            if (!empty($p['due_date'])) {
                $ts = strtotime(str_replace('/', '-', $p['due_date']));
                if ($ts && $ts >= strtotime('today')) {
                    $upcomingDeadlines[] = [
                        'item_type' => 'project_deadline',
                        'project' => $p,
                        'timestamp' => $ts,
                        'formatted_date' => date('l, M j', $ts)
                    ];
                }
            }
        }

        usort($upcomingDeadlines, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        // 6. Check for Holiday Conflicts (tasks or project deadlines scheduled on government days off)
        $conflicts = [];
        foreach ($holidaysByDay as $dayNum => $hList) {
            $hasDayOff = false;
            $hNames = [];
            foreach ($hList as $h) {
                if ($h['is_day_off']) {
                    $hasDayOff = true;
                    $hNames[] = $h['name'];
                }
            }

            if ($hasDayOff) {
                $conflictTasks = $tasksByDay[$dayNum] ?? [];
                $conflictProjEvents = $projectEventsByDay[$dayNum] ?? [];

                if (!empty($conflictTasks) || !empty($conflictProjEvents)) {
                    $conflicts[] = [
                        'day' => $dayNum,
                        'date' => sprintf('%04d-%02d-%02d', $year, $month, $dayNum),
                        'holiday_name' => implode(', ', $hNames),
                        'tasks_count' => count($conflictTasks),
                        'projects_count' => count($conflictProjEvents),
                        'tasks' => $conflictTasks,
                        'projects' => $conflictProjEvents
                    ];
                }
            }
        }

        sendJsonResponse([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'country' => $country,
            'holidays_by_day' => $holidaysByDay,
            'project_events_by_day' => $projectEventsByDay,
            'milestones_by_day' => $milestonesByDay,
            'tasks_by_day' => $tasksByDay,
            'projects' => $projects,
            'upcoming_deadlines' => array_slice($upcomingDeadlines, 0, 10),
            'conflicts' => $conflicts,
            'stats' => [
                'days_off_count' => count($holidaysByDay),
                'active_projects_count' => count($projects),
                'tasks_count' => count($allTasks),
                'conflicts_count' => count($conflicts)
            ]
        ]);

    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();

        $action = $data['action'] ?? '';

        if ($action === 'update_project_dates') {
            $projectId = (int)($data['project_id'] ?? 0);
            $startDate = trim($data['start_date'] ?? '');
            $dueDate = trim($data['due_date'] ?? '');

            if ($projectId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid project_id is required'], 400);
            }

            $stmt = $db->prepare("UPDATE courses SET start_date = :sdate, due_date = :ddate WHERE id = :id AND user_id = :uid");
            $stmt->execute([
                'sdate' => !empty($startDate) ? $startDate : null,
                'ddate' => !empty($dueDate) ? $dueDate : null,
                'id' => $projectId,
                'uid' => $userId
            ]);

            sendJsonResponse(['success' => true, 'message' => 'Project dates updated successfully!']);

        } elseif ($action === 'create_milestone') {
            $projectId = (int)($data['project_id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $dueDate = trim($data['due_date'] ?? date('d-m-Y'));

            if ($projectId <= 0 || empty($name)) {
                sendJsonResponse(['success' => false, 'message' => 'Project and Milestone Name are required'], 400);
            }

            // Verify project belongs to user
            $stmtCheck = $db->prepare("SELECT id FROM courses WHERE id = :pid AND user_id = :uid");
            $stmtCheck->execute(['pid' => $projectId, 'uid' => $userId]);
            if (!$stmtCheck->fetch()) {
                sendJsonResponse(['success' => false, 'message' => 'Project not found or unauthorized'], 404);
            }

            $stmtIns = $db->prepare("INSERT INTO milestones (project_id, name, due_date, status) VALUES (:pid, :name, :ddate, 'Pending')");
            $stmtIns->execute(['pid' => $projectId, 'name' => $name, 'ddate' => $dueDate]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Milestone created successfully!',
                'milestone_id' => (int)$db->lastInsertId()
            ]);

        } elseif ($action === 'create_custom_holiday') {
            $holidayName = trim($data['holiday_name'] ?? '');
            $holidayDate = trim($data['holiday_date'] ?? date('d-m-Y'));
            $holidayType = trim($data['holiday_type'] ?? 'custom');
            $countryCode = strtoupper(trim($data['country_code'] ?? 'KH'));
            $isDayOff = isset($data['is_day_off']) ? (int)(bool)$data['is_day_off'] : 1;
            $notes = trim($data['notes'] ?? '');

            if (empty($holidayName)) {
                sendJsonResponse(['success' => false, 'message' => 'Holiday/Day off title is required'], 400);
            }

            $stmtIns = $db->prepare("INSERT INTO user_holidays (user_id, holiday_name, holiday_date, holiday_type, country_code, is_day_off, notes) 
                                     VALUES (:uid, :hname, :hdate, :htype, :ccode, :dayoff, :notes)");
            $stmtIns->execute([
                'uid' => $userId,
                'hname' => $holidayName,
                'hdate' => $holidayDate,
                'htype' => $holidayType,
                'ccode' => $countryCode,
                'dayoff' => $isDayOff,
                'notes' => $notes
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Custom day off recorded successfully!',
                'holiday_id' => (int)$db->lastInsertId()
            ]);

        } elseif ($action === 'delete_custom_holiday') {
            $holidayId = (int)($data['holiday_id'] ?? 0);
            $stmtDel = $db->prepare("DELETE FROM user_holidays WHERE id = :id AND user_id = :uid");
            $stmtDel->execute(['id' => $holidayId, 'uid' => $userId]);

            sendJsonResponse(['success' => true, 'message' => 'Day off removed.']);

        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Calendar Events API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred processing calendar request.'], 500);
}
