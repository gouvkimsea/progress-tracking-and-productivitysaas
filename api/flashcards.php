<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $course = trim($_GET['course'] ?? '');
        $today = date('Y-m-d');

        if (!empty($course) && $course !== 'all') {
            $stmt = $db->prepare("SELECT * FROM flashcards WHERE user_id = :uid AND course_name = :cname ORDER BY id ASC");
            $stmt->execute(['uid' => $userId, 'cname' => $course]);
        } else {
            $stmt = $db->prepare("SELECT * FROM flashcards WHERE user_id = :uid ORDER BY id ASC");
            $stmt->execute(['uid' => $userId]);
        }
        $cards = $stmt->fetchAll();

        // Calculate summary metrics
        $stmtDue = $db->prepare("SELECT COUNT(*) as cnt FROM flashcards WHERE user_id = :uid AND due_date <= :today");
        $stmtDue->execute(['uid' => $userId, 'today' => $today]);
        $dueCount = (int)$stmtDue->fetch()['cnt'];

        $stmtMastered = $db->prepare("SELECT COUNT(*) as cnt FROM flashcards WHERE user_id = :uid AND interval_days >= 7");
        $stmtMastered->execute(['uid' => $userId]);
        $masteredCount = (int)$stmtMastered->fetch()['cnt'];

        sendJsonResponse([
            'success' => true,
            'cards' => $cards,
            'total_count' => count($cards),
            'due_count' => $dueCount,
            'mastered_count' => $masteredCount
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
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
                'card_id' => $db->lastInsertId()
            ]);
        } elseif ($action === 'review') {
            $cardId = (int)($data['card_id'] ?? 0);
            $rating = strtolower(trim($data['rating'] ?? 'good')); // again, hard, good, easy

            $stmtCard = $db->prepare("SELECT * FROM flashcards WHERE id = :id AND user_id = :uid");
            $stmtCard->execute(['id' => $cardId, 'uid' => $userId]);
            $card = $stmtCard->fetch();

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
