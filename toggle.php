<?php
header('Content-Type: application/json');
require_once 'db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'functions.php';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$habitId = filter_input(INPUT_POST, 'habit_id', FILTER_VALIDATE_INT);
if (!$habitId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid habit_id']);
    exit;
}

// Check habit exists
$check = $pdo->prepare("SELECT id FROM habits WHERE id = ? AND user_id = ?");
$check->execute([$habitId, $userId]);
if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Toggle: if exists delete, else insert
$exists = $pdo->prepare(
    "SELECT id FROM habit_logs WHERE habit_id = ? AND log_date = CURDATE()"
);
$exists->execute([$habitId]);
$logRow = $exists->fetch();

if ($logRow) {
    $pdo->prepare("DELETE FROM habit_logs WHERE id = ?")->execute([$logRow['id']]);
    $completed = false;
} else {
    $pdo->prepare("INSERT INTO habit_logs (habit_id, log_date) VALUES (?, CURDATE())")
        ->execute([$habitId]);
    $completed = true;
}

// Return updated stats
require_once 'functions.php';
$streak    = getCurrentStreak($pdo, $habitId);
$todayStats = getTodayStats($pdo, $userId);

echo json_encode([
    'completed'  => $completed,
    'streak'     => $streak,
    'today_done' => $todayStats['done'],
    'today_pct'  => $todayStats['pct'],
    'today_total'=> $todayStats['total'],
]);
