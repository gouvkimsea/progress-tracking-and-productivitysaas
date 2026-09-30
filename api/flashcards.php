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
        $course = trim($_GET['course'] ?? '');
        $today = date('Y-m-d');

        if (!empty($course) && $course !== 'all') {
            $stmt = $db->prepare("SELECT id, user_id, course_name, question, answer, interval_days, ease_factor, due_date, created_at 
                                  FROM flashcards 
                                  WHERE user_id = :uid AND course_name = :cname 
                                  ORDER BY id ASC");
            $stmt->execute(['uid' => $userId, 'cname' => $course]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch overall metrics for user
            $stmtMetrics = $db->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN due_date <= :today THEN 1 ELSE 0 END), 0) as due_count,
                    COALESCE(SUM(CASE WHEN interval_days >= 7 THEN 1 ELSE 0 END), 0) as mastered_count
                FROM flashcards 
                WHERE user_id = :uid
            ");
            $stmtMetrics->execute(['uid' => $userId, 'today' => $today]);
            $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);
            $dueCount = (int)($metrics['due_count'] ?? 0);
            $masteredCount = (int)($metrics['mastered_count'] ?? 0);
        } else {
            $stmt = $db->prepare("SELECT id, user_id, course_name, question, answer, interval_days, ease_factor, due_date, created_at 
                                  FROM flashcards 
                                  WHERE user_id = :uid 
                                  ORDER BY id ASC");
            $stmt->execute(['uid' => $userId]);
            $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate metrics directly in PHP in 1 fast pass, avoiding a 2nd database query!
            $dueCount = 0;
            $masteredCount = 0;
            foreach ($cards as $c) {
                if (!empty($c['due_date']) && $c['due_date'] <= $today) {
                    $dueCount++;
                }
                if ((int)($c['interval_days'] ?? 0) >= 7) {
                    $masteredCount++;
                }
            }
        }

        sendJsonResponse([
            'success' => true,
            'cards' => $cards,
            'total_count' => count($cards),
            'due_count' => $dueCount,
            'mastered_count' => $masteredCount
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $courseName = trim($data['course_name'] ?? 'General');
            $question = trim($data['question'] ?? '');
            $answer = trim($data['answer'] ?? '');

            if (empty($question) || empty($answer)) {
                sendJsonResponse(['success' => false, 'message' => 'Question and answer are both required.'], 400);
            }

            $stmtInsert = $db->prepare("INSERT INTO flashcards (user_id, course_name, question, answer, interval_days, ease_factor, due_date)
                                       VALUES (:uid, :cname, :q, :a, 1, 2.50, :due)");
            $stmtInsert->execute([
                'uid' => $userId,
                'cname' => $courseName,
                'q' => $question,
                'a' => $answer,
                'due' => date('Y-m-d')
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Flashcard created successfully!',
                'card_id' => (int)$db->lastInsertId()
            ]);
        } elseif ($action === 'review') {
            $cardId = (int)($data['card_id'] ?? 0);
            $rating = strtolower(trim($data['rating'] ?? 'good')); // again, hard, good, easy

            $stmtCard = $db->prepare("SELECT interval_days FROM flashcards WHERE id = :id AND user_id = :uid");
            $stmtCard->execute(['id' => $cardId, 'uid' => $userId]);
            $card = $stmtCard->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                sendJsonResponse(['success' => false, 'message' => 'Flashcard not found.'], 404);
            }

            $curInterval = (int)($card['interval_days'] ?? 1);
            $newInterval = 1;

            if ($rating === 'again') {
                $newInterval = 1;
            } elseif ($rating === 'hard') {
                $newInterval = max(1, $curInterval + 1);
            } elseif ($rating === 'good') {
                $newInterval = max(2, $curInterval * 2);
            } elseif ($rating === 'easy') {
                $newInterval = max(4, $curInterval * 3);
            }

            $nextDueDate = date('Y-m-d', strtotime("+{$newInterval} days"));

            $stmtUpdate = $db->prepare("UPDATE flashcards SET interval_days = :interval, due_date = :due WHERE id = :id AND user_id = :uid");
            $stmtUpdate->execute([
                'interval' => $newInterval,
                'due' => $nextDueDate,
                'id' => $cardId,
                'uid' => $userId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => "Reviewed as '{$rating}'. Next review in {$newInterval} day(s).",
                'new_interval' => $newInterval,
                'next_due_date' => $nextDueDate
            ]);
        } elseif ($action === 'delete') {
            $cardId = (int)($data['card_id'] ?? 0);
            $stmtDelete = $db->prepare("DELETE FROM flashcards WHERE id = :id AND user_id = :uid");
            $stmtDelete->execute(['id' => $cardId, 'uid' => $userId]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Flashcard deleted successfully.'
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (Throwable $e) {
    error_log('Flashcards API Error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred managing flashcards.'], 500);
}
