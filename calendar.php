<?php
require_once 'db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'functions.php';
$userId = (int) $_SESSION['user_id'];
// Month navigation
$year  = filter_input(INPUT_GET, 'year',  FILTER_VALIDATE_INT) ?: (int) date('Y');
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: (int) date('m');

// Clamp
$year  = max(2020, min((int) date('Y') + 1, $year));
$month = max(1, min(12, $month));

// Prev / Next
$prevTs = mktime(0, 0, 0, $month - 1, 1, $year);
$nextTs = mktime(0, 0, 0, $month + 1, 1, $year);
$prevY  = date('Y', $prevTs); $prevM = date('m', $prevTs);
$nextY  = date('Y', $nextTs); $nextM = date('m', $nextTs);

$monthName  = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$firstDow   = (int) date('N', mktime(0, 0, 0, $month, 1, $year)); // 1=Mon..7=Sun
$completions = getMonthCompletions($pdo, $userId, $year, $month);
$habits      = getAllHabits($pdo, $userId);
$today     = date('Y-m-d');

// Per-habit monthly data
$habitMonthData = [];
foreach ($habits as $h) {
    $stmt = $pdo->prepare(
    "SELECT hl.log_date FROM habit_logs hl
     INNER JOIN habits h ON h.id = hl.habit_id
     WHERE hl.habit_id = ? AND YEAR(hl.log_date) = ? AND MONTH(hl.log_date) = ?
     AND h.user_id = ?"
);
$stmt->execute([$h['id'], $year, $month, $userId]);
    $days = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $habitMonthData[$h['id']] = array_flip($days);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar — HabitFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>📅 Calendar</h1>
            <p class="subtitle">Your habit history at a glance.</p>
        </div>

        <!-- Month Nav -->
        <div class="month-nav card">
            <a href="calendar.php?year=<?= $prevY ?>&month=<?= $prevM ?>" class="btn btn-ghost btn-sm">← Prev</a>
            <h2 class="month-title"><?= $monthName ?></h2>
            <a href="calendar.php?year=<?= $nextY ?>&month=<?= $nextM ?>"
               class="btn btn-ghost btn-sm <?= ($year == date('Y') && $month == date('m')) ? 'disabled' : '' ?>">
               Next →
            </a>
        </div>

        <!-- Calendar Grid -->
        <div class="card calendar-card">
            <div class="cal-grid">
                <!-- Day headers -->
                <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                    <div class="cal-header"><?= $d ?></div>
                <?php endforeach; ?>

                <!-- Empty cells before month start (Mon=1) -->
                <?php for ($i = 1; $i < $firstDow; $i++): ?>
                    <div class="cal-cell cal-empty"></div>
                <?php endfor; ?>

                <!-- Day cells -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $pct     = $completions[$dateStr] ?? 0;
                    $isToday = ($dateStr === $today);
                    $isFuture= ($dateStr > $today);

                    // Color intensity based on % completion
                    if ($isFuture || count($habits) === 0) {
                        $cellClass = 'cal-future';
                        $alpha = 0;
                    } elseif ($pct === 0) {
                        $cellClass = 'cal-zero';
                        $alpha = 0;
                    } elseif ($pct < 50) {
                        $cellClass = 'cal-low';
                        $alpha = 0.3;
                    } elseif ($pct < 100) {
                        $cellClass = 'cal-mid';
                        $alpha = 0.6;
                    } else {
                        $cellClass = 'cal-full';
                        $alpha = 1;
                    }
                ?>
                <div class="cal-cell <?= $cellClass ?> <?= $isToday ? 'cal-today' : '' ?>"
                     style="<?= (!$isFuture && $pct > 0) ? "background: rgba(99,102,241,{$alpha});" : '' ?>"
                     title="<?= $dateStr ?>: <?= $pct ?>% complete"
                     onclick="showDay('<?= $dateStr ?>', <?= $day ?>)">
                    <span class="cal-day-num"><?= $day ?></span>
                    <?php if (!$isFuture && count($habits) > 0): ?>
                    <span class="cal-pct"><?= $pct ?>%</span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Legend -->
            <div class="cal-legend">
                <span class="legend-item"><span class="legend-swatch" style="background:rgba(99,102,241,0.3)"></span> Partial</span>
                <span class="legend-item"><span class="legend-swatch" style="background:rgba(99,102,241,0.6)"></span> Good</span>
                <span class="legend-item"><span class="legend-swatch" style="background:rgba(99,102,241,1)"></span> Perfect</span>
                <span class="legend-item"><span class="legend-swatch cal-zero-swatch"></span> Missed</span>
            </div>
        </div>

        <!-- Day Detail Panel (hidden by default) -->
        <div class="card day-detail" id="day-detail" style="display:none">
            <h3 id="day-detail-title">Day Detail</h3>
            <ul class="day-habit-list" id="day-habit-list"></ul>
        </div>

        <!-- Per-Habit Monthly Summary -->
        <?php if (!empty($habits)): ?>
        <div class="card">
            <h2 class="card-title">Monthly Summary</h2>
            <ul class="habit-month-summary">
                <?php foreach ($habits as $h):
                    $doneDays = count($habitMonthData[$h['id']]);
                    $pct2 = round(($doneDays / $daysInMonth) * 100);
                ?>
                <li class="month-summary-item">
                    <span class="habit-icon-sm"><?= htmlspecialchars($h['icon']) ?></span>
                    <div class="month-summary-info">
                        <span class="habit-name"><?= htmlspecialchars($h['name']) ?></span>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill"
                                 style="width: <?= $pct2 ?>%; background: <?= htmlspecialchars($h['color']) ?>;">
                            </div>
                        </div>
                    </div>
                    <span class="month-pct"><?= $doneDays ?>/<?= $daysInMonth ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </main>

    <script>
    // Build per-day habit data for the detail panel
    const habitMonthData = <?= json_encode(array_map(
        function($h) use ($habitMonthData, $daysInMonth) {
            return [
                'id'    => $h['id'],
                'name'  => $h['name'],
                'icon'  => $h['icon'],
                'color' => $h['color'],
                'done'  => array_keys($habitMonthData[$h['id']]),
            ];
        },
        $habits
    )) ?>;

    function showDay(dateStr, dayNum) {
        const panel = document.getElementById('day-detail');
        const title = document.getElementById('day-detail-title');
        const list  = document.getElementById('day-habit-list');

        title.textContent = 'Habits for ' + dateStr;
        list.innerHTML = '';

        if (habitMonthData.length === 0) {
            list.innerHTML = '<li class="muted">No habits tracked yet.</li>';
        } else {
            habitMonthData.forEach(h => {
                const done = h.done.includes(dateStr);
                const li   = document.createElement('li');
                li.className = 'day-habit-item';
                li.innerHTML = `
                    <span class="habit-icon-sm">${h.icon}</span>
                    <span class="habit-name">${h.name}</span>
                    <span class="day-status ${done ? 'status-done' : 'status-miss'}">${done ? '✓ Done' : '✗ Missed'}</span>
                `;
                list.appendChild(li);
            });
        }
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    </script>
</body>
</html>
