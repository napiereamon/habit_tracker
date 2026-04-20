<?php
// ─── Motivational Quotes ─────────────────────────────────────────────────────
function getDailyQuote(): string {
    $quotes = [
        "Keep going — you've got this!",
        "Every day you show up is a win.",
        "Small steps every day lead to big change.",
        "Discipline is just doing it when you don't feel like it.",
        "You're building the version of yourself you'll thank later.",
        "Consistency beats perfection every single time.",
        "One more day. One more win.",
        "The hard part is starting. You already did that.",
        "Progress, not perfection.",
        "Your future self is watching — make them proud.",
        "Don't break the chain.",
        "Show up today. That's enough.",
        "Momentum is built one day at a time.",
        "You chose discipline today. That matters.",
        "Quiet consistency is the most powerful force there is.",
        "The streak doesn't build the habit. The habit builds the streak.",
        "You're doing better than you think.",
        "Brick by brick. Day by day.",
        "It doesn't have to be perfect — it just has to happen.",
        "Champions are built in the moments nobody sees.",
    ];
    $index = (int) date('z') % count($quotes); // changes daily
    return $quotes[$index];
}

// ─── Streak Calculation ───────────────────────────────────────────────────────
function getCurrentStreak(PDO $pdo, int $habitId): int {
    $stmt = $pdo->prepare(
        "SELECT log_date FROM habit_logs WHERE habit_id = ? ORDER BY log_date DESC"
    );
    $stmt->execute([$habitId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) return 0;

    $today     = new DateTime('today');
    $yesterday = new DateTime('yesterday');
    $latest    = new DateTime($dates[0]);

    // Streak is only live if completed today or yesterday
    if ($latest < $yesterday) return 0;

    $streak  = 0;
    $current = clone $latest;

    foreach ($dates as $dateStr) {
        $d = new DateTime($dateStr);
        if ($d->format('Y-m-d') === $current->format('Y-m-d')) {
            $streak++;
            $current->modify('-1 day');
        } else {
            break;
        }
    }
    return $streak;
}

function getLongestStreak(PDO $pdo, int $habitId): int {
    $stmt = $pdo->prepare(
        "SELECT log_date FROM habit_logs WHERE habit_id = ? ORDER BY log_date ASC"
    );
    $stmt->execute([$habitId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) return 0;

    $longest = 1;
    $current = 1;

    for ($i = 1; $i < count($dates); $i++) {
        $prev = new DateTime($dates[$i - 1]);
        $curr = new DateTime($dates[$i]);
        $diff = (int) $prev->diff($curr)->days;

        if ($diff === 1) {
            $current++;
            $longest = max($longest, $current);
        } else {
            $current = 1;
        }
    }
    return $longest;
}

// ─── Today's Completion ───────────────────────────────────────────────────────
function isCompletedToday(PDO $pdo, int $habitId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM habit_logs WHERE habit_id = ? AND log_date = CURDATE() LIMIT 1"
    );
    $stmt->execute([$habitId]);
    return (bool) $stmt->fetchColumn();
}

// ─── All Habits ───────────────────────────────────────────────────────────────
function getAllHabits(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM habits WHERE user_id = ? ORDER BY created_at ASC, id ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ─── Dashboard Stats ─────────────────────────────────────────────────────────
function getTodayStats(PDO $pdo, int $userId): array {
    $habits = getAllHabits($pdo, $userId);
    $total  = count($habits);
    if ($total === 0) return ['total' => 0, 'done' => 0, 'pct' => 0];

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM habit_logs hl
         INNER JOIN habits h ON h.id = hl.habit_id
         WHERE hl.log_date = CURDATE() AND h.user_id = ?"
    );
    $stmt->execute([$userId]);
    $done = (int) $stmt->fetchColumn();
    return [
        'total' => $total,
        'done'  => $done,
        'pct'   => round(($done / $total) * 100),
    ];
}

function getAllTimeStats(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM habit_logs hl
         INNER JOIN habits h ON h.id = hl.habit_id
         WHERE h.user_id = ?"
    );
    $stmt->execute([$userId]);
    $total = (int) $stmt->fetchColumn();

 // Best single streak across all habits
    $habits      = getAllHabits($pdo, $userId);
    $bestStreak  = 0;
    foreach ($habits as $h) {
        $bestStreak = max($bestStreak, getLongestStreak($pdo, $h['id']));
    }

    // Overall completion rate: completions / (sum of days each habit has been alive)
    $possible = 0;
    $today    = new DateTime('today');
    foreach ($habits as $h) {
        $created   = new DateTime($h['created_at']);
        $days      = (int) $created->diff($today)->days + 1;
        $possible += $days;
    }
    $rate = $possible > 0 ? round(($total / $possible) * 100) : 0;

    return [
        'total_completions' => $total,
        'best_streak'       => $bestStreak,
        'completion_rate'   => $rate,
        'habit_count'       => count($habits),
    ];
}

// ─── Calendar Data ────────────────────────────────────────────────────────────
function getMonthCompletions(PDO $pdo, int $userId, int $year, int $month): array {
    $habits = getAllHabits($pdo, $userId);
    $total  = count($habits);
    if ($total === 0) return [];

    $stmt = $pdo->prepare(
        "SELECT hl.log_date, COUNT(*) as cnt
         FROM habit_logs hl
         INNER JOIN habits h ON h.id = hl.habit_id
         WHERE YEAR(hl.log_date) = ? AND MONTH(hl.log_date) = ? AND h.user_id = ?
         GROUP BY hl.log_date"
    );
    $stmt->execute([$year, $month, $userId]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $pct       = round(($row['cnt'] / $total) * 100);
        $map[$row['log_date']] = $pct;
    }
    return $map;
}
