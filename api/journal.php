<?php
require_once __DIR__ . '/../config/db.php';
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
        $today = date('Y-m-d');

        // Fetch journal entries (last 60 days) with explicit columns
        $stmtEntries = $db->prepare("SELECT id, user_id, entry_date, rating, mood_label, accomplishments, challenges, learning_notes, journal_text, created_at, updated_at 
                                     FROM daily_journal 
                                     WHERE user_id = :uid 
                                     ORDER BY entry_date DESC 
                                     LIMIT 60");
        $stmtEntries->execute(['uid' => $userId]);
        $entries = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);

        // Find today's entry from $entries without running an extra duplicate query
        $todayEntry = null;
        foreach ($entries as $e) {
            if ($e['entry_date'] === $today) {
                $todayEntry = $e;
                break;
            }
        }

        // Fetch today's to-dos with explicit columns
        $stmtTodos = $db->prepare("SELECT id, user_id, task_text, is_completed, todo_date, completed_at, created_at 
                                   FROM journal_todos 
                                   WHERE user_id = :uid AND todo_date = :dt 
                                   ORDER BY is_completed ASC, id ASC");
        $stmtTodos->execute(['uid' => $userId, 'dt' => $today]);
        $todayTodos = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

        // Stats calculation in memory
        $totalEntries = count($entries);
        $avgRating = 0;
        $fiveStarCount = 0;
        if ($totalEntries > 0) {
            $sum = 0;
            foreach ($entries as $e) {
                $r = (int)$e['rating'];
                $sum += $r;
                if ($r === 5) $fiveStarCount++;
            }
            $avgRating = round($sum / $totalEntries, 1);
        }

        // To-dos completion stats for today
        $totalTodos = count($todayTodos);
        $completedTodos = 0;
        foreach ($todayTodos as $t) {
            if (!empty($t['is_completed'])) $completedTodos++;
        }

        sendJsonResponse([
            'success' => true,
            'today' => $today,
            'today_entry' => $todayEntry,
            'today_todos' => $todayTodos,
            'entries' => $entries,
            'stats' => [
                'total_entries' => $totalEntries,
                'avg_rating' => $avgRating,
                'five_star_count' => $fiveStarCount,
                'today_todos_total' => $totalTodos,
                'today_todos_completed' => $completedTodos
            ]
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();
        $action = $data['action'] ?? '';

        if ($action === 'save_entry') {
            $entryDate = trim($data['entry_date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                $entryDate = date('Y-m-d');
            }
            $rating = max(1, min(5, (int)($data['rating'] ?? 3)));
            
            $moodLabels = [
                1 => 'Rough Day',
                2 => 'Slow Going',
                3 => 'Steady & Solid',
                4 => 'Great Day',
                5 => 'Phenomenal'
            ];
            $moodLabel = trim($data['mood_label'] ?? ($moodLabels[$rating] ?? 'Okay'));
            $accomplishments = trim($data['accomplishments'] ?? '');
            $challenges = trim($data['challenges'] ?? '');
            $learningNotes = trim($data['learning_notes'] ?? '');
            $journalText = trim($data['journal_text'] ?? '');

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("INSERT INTO daily_journal (user_id, entry_date, rating, mood_label, accomplishments, challenges, learning_notes, journal_text)
                    VALUES (:uid, :dt, :rt, :ml, :acc, :chal, :ln, :jt)
                    ON CONFLICT(user_id, entry_date) DO UPDATE SET
                    rating = excluded.rating,
                    mood_label = excluded.mood_label,
                    accomplishments = excluded.accomplishments,
                    challenges = excluded.challenges,
                    learning_notes = excluded.learning_notes,
                    journal_text = excluded.journal_text,
                    updated_at = CURRENT_TIMESTAMP");
            } else {
                $stmt = $db->prepare("INSERT INTO daily_journal (user_id, entry_date, rating, mood_label, accomplishments, challenges, learning_notes, journal_text)
                    VALUES (:uid, :dt, :rt, :ml, :acc, :chal, :ln, :jt)
                    ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    mood_label = VALUES(mood_label),
                    accomplishments = VALUES(accomplishments),
                    challenges = VALUES(challenges),
                    learning_notes = VALUES(learning_notes),
                    journal_text = VALUES(journal_text)");
            }

            $stmt->execute([
                'uid' => $userId,
                'dt' => $entryDate,
                'rt' => $rating,
                'ml' => $moodLabel,
                'acc' => ($accomplishments !== '' ? $accomplishments : null),
                'chal' => ($challenges !== '' ? $challenges : null),
                'ln' => ($learningNotes !== '' ? $learningNotes : null),
                'jt' => ($journalText !== '' ? $journalText : null)
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Daily reflection journal saved successfully!',
                'rating' => $rating,
                'mood_label' => $moodLabel,
                'entry_date' => $entryDate
            ]);
        } elseif ($action === 'delete_entry') {
            $entryId = (int)($data['entry_id'] ?? 0);
            if ($entryId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid entry_id is required'], 400);
            }
            $stmt = $db->prepare("DELETE FROM daily_journal WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $entryId, 'uid' => $userId]);

            sendJsonResponse(['success' => true, 'message' => 'Journal entry removed']);
        } elseif ($action === 'add_todo') {
            $taskText = trim($data['task_text'] ?? '');
            if (empty($taskText)) {
                sendJsonResponse(['success' => false, 'message' => 'Task text cannot be empty'], 400);
            }
            $todoDate = trim($data['todo_date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $todoDate)) {
                $todoDate = date('Y-m-d');
            }

            $stmt = $db->prepare("INSERT INTO journal_todos (user_id, task_text, is_completed, todo_date) VALUES (:uid, :txt, 0, :dt)");
            $stmt->execute([
                'uid' => $userId,
                'txt' => $taskText,
                'dt' => $todoDate
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'To-do task added!',
                'todo_id' => (int)$db->lastInsertId(),
                'task_text' => $taskText
            ]);
        } elseif ($action === 'toggle_todo') {
            $todoId = (int)($data['todo_id'] ?? 0);
            $isCompleted = !empty($data['is_completed']) ? 1 : 0;
            $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;

            if ($todoId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid todo_id is required'], 400);
            }

            $stmt = $db->prepare("UPDATE journal_todos SET is_completed = :comp, completed_at = :cat WHERE id = :id AND user_id = :uid");
            $stmt->execute([
                'comp' => $isCompleted,
                'cat' => $completedAt,
                'id' => $todoId,
                'uid' => $userId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => $isCompleted ? 'Task marked complete!' : 'Task reopened',
                'is_completed' => $isCompleted
            ]);
        } elseif ($action === 'delete_todo') {
            $todoId = (int)($data['todo_id'] ?? 0);
            if ($todoId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid todo_id is required'], 400);
            }

            $stmt = $db->prepare("DELETE FROM journal_todos WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $todoId, 'uid' => $userId]);

            sendJsonResponse(['success' => true, 'message' => 'Task deleted']);
        } elseif ($action === 'clear_completed_todos') {
            $todoDate = trim($data['todo_date'] ?? date('Y-m-d'));
            $stmt = $db->prepare("DELETE FROM journal_todos WHERE user_id = :uid AND todo_date = :dt AND is_completed = 1");
            $stmt->execute(['uid' => $userId, 'dt' => $todoDate]);

            sendJsonResponse(['success' => true, 'message' => 'Completed tasks cleared']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Daily Journal API Error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred managing journal: ' . $e->getMessage()], 500);
}
