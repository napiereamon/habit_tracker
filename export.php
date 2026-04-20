<?php
require_once 'db.php';
require_once 'functions.php';

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Fetch every habit belonging to the logged-in user
$stmt = $pdo->prepare(
    "SELECT id, name, color, created_at FROM habits
     WHERE user_id = ?
     ORDER BY created_at ASC, id ASC"
);
$stmt->execute([$userId]);
$habits = $stmt->fetchAll();

// Total completions per habit
$totalStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM habit_logs WHERE habit_id = ?"
);

// Set download headers before any output
$filename = 'habitflow-export-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, ['Habit Name', 'Colour', 'Date Created', 'Total Completions', 'Current Streak']);

// Data rows
foreach ($habits as $h) {
    $totalStmt->execute([$h['id']]);
    $completions = (int) $totalStmt->fetchColumn();
    $streak      = getCurrentStreak($pdo, $h['id']);

    fputcsv($out, [
        $h['name'],
        $h['color'],
        $h['created_at'],
        $completions,
        $streak,
    ]);
}

fclose($out);
exit;