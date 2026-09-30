<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();

        $action = $data['action'] ?? 'breakdown';

        if ($action === 'breakdown') {
            $goal = trim($data['goal'] ?? '');
            if (empty($goal)) {
                sendJsonResponse(['success' => false, 'message' => 'Please provide a goal to break down.'], 400);
            }

            // Sanitize goal input
            $safeGoal = htmlspecialchars(substr($goal, 0, 100), ENT_QUOTES, 'UTF-8');
            $gLower = strtolower($goal);

            if (str_contains($gLower, 'auth') || str_contains($gLower, 'login') || str_contains($gLower, 'security')) {
                $subtasks = [
                    ['task_name' => "Design {$safeGoal} DB Schema & Tables", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 1],
                    ['task_name' => "Implement Password Hashing & Rate Limiting", 'priority' => 'Urgent', 'time_log' => 3, 'due_offset' => 2],
                    ['task_name' => "Wire CSRF Tokens & Session Protections", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 3],
                    ['task_name' => "Build Login & Registration UI Validation", 'priority' => 'Medium', 'time_log' => 2, 'due_offset' => 4],
                    ['task_name' => "Write Unit & Integration Security Tests", 'priority' => 'Medium', 'time_log' => 2, 'due_offset' => 5],
                ];
            } elseif (str_contains($gLower, 'api') || str_contains($gLower, 'backend') || str_contains($gLower, 'server')) {
                $subtasks = [
                    ['task_name' => "Define RESTful Endpoints & Request Contracts", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 1],
                    ['task_name' => "Setup Database Migrations & Prepared Statements", 'priority' => 'Urgent', 'time_log' => 3, 'due_offset' => 2],
                    ['task_name' => "Build CRUD Business Logic & Error Handling", 'priority' => 'High', 'time_log' => 4, 'due_offset' => 3],
                    ['task_name' => "Configure Content-Security-Policy & Headers", 'priority' => 'Medium', 'time_log' => 1, 'due_offset' => 4],
                    ['task_name' => "Document API Endpoints in Swagger/Markdown", 'priority' => 'Low', 'time_log' => 2, 'due_offset' => 5],
                ];
            } elseif (str_contains($gLower, 'design') || str_contains($gLower, 'ui') || str_contains($gLower, 'landing')) {
                $subtasks = [
                    ['task_name' => "Create Wireframes & Typography Hierarchy", 'priority' => 'Medium', 'time_log' => 3, 'due_offset' => 1],
                    ['task_name' => "Establish Design System Color Tokens & Themes", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 2],
                    ['task_name' => "Code Responsive HTML5 Layout & CSS Grid", 'priority' => 'Urgent', 'time_log' => 4, 'due_offset' => 3],
                    ['task_name' => "Add Micro-Interactions & Hover Animations", 'priority' => 'Medium', 'time_log' => 2, 'due_offset' => 4],
                    ['task_name' => "Test Mobile Navigation & Dark Mode Contrast", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 5],
                ];
            } else {
                $subtasks = [
                    ['task_name' => "Define Requirements & Scope for: {$safeGoal}", 'priority' => 'Urgent', 'time_log' => 2, 'due_offset' => 1],
                    ['task_name' => "Core Implementation & Architecture: {$safeGoal}", 'priority' => 'High', 'time_log' => 4, 'due_offset' => 2],
                    ['task_name' => "Component Integration & Quality Review", 'priority' => 'Medium', 'time_log' => 3, 'due_offset' => 3],
                    ['task_name' => "Testing, Validation & Edge-Case Handling", 'priority' => 'High', 'time_log' => 2, 'due_offset' => 4],
                    ['task_name' => "Documentation & Deployment Finalization", 'priority' => 'Low', 'time_log' => 1, 'due_offset' => 5],
                ];
            }

            foreach ($subtasks as &$st) {
                $st['due_date'] = date('d-m-Y', strtotime("+{$st['due_offset']} days"));
            }

            sendJsonResponse([
                'success' => true,
                'goal' => $safeGoal,
                'subtasks' => $subtasks
            ]);
        } elseif ($action === 'add_subtasks') {
            $tasksList = $data['tasks'] ?? [];
            $projectName = substr(trim($data['project_name'] ?? 'Mindrift Project'), 0, 100);
            if (empty($tasksList) || !is_array($tasksList)) {
                sendJsonResponse(['success' => false, 'message' => 'No tasks provided'], 400);
            }

            $db = getDbConnection();
            $insertedCount = 0;
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO tasks (user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log)
                                      VALUES (:uid, :tname, :pname, :sdate, :ddate, :prio, :assigned, 'Open', :tlog)");

                $startDate = date('d-m-Y');
                $defaultDue = date('d-m-Y', strtotime('+3 days'));

                foreach ($tasksList as $t) {
                    if (empty($t['task_name'])) continue;
                    $taskName = substr(trim($t['task_name']), 0, 190);
                    $priority = trim($t['priority'] ?? 'Medium');
                    if (!in_array($priority, ['Urgent', 'High', 'Medium', 'Low'], true)) {
                        $priority = 'Medium';
                    }
                    $timeLog = max(0, min(100, (int)($t['time_log'] ?? 0)));

                    $stmt->execute([
                        'uid' => $userId,
                        'tname' => $taskName,
                        'pname' => $projectName,
                        'sdate' => $startDate,
                        'ddate' => $t['due_date'] ?? $defaultDue,
                        'prio' => $priority,
                        'assigned' => $userName,
                        'tlog' => $timeLog
                    ]);
                    $insertedCount++;
                }
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $ex;
            }

            sendJsonResponse([
                'success' => true,
                'message' => "Successfully created {$insertedCount} task(s) on your task board!",
                'count' => $insertedCount
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Copilot API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred while processing Copilot request.'], 500);
}
