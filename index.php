<?php
require_once 'db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'functions.php';
$userId = (int) $_SESSION['user_id'];

$habits      = getAllHabits($pdo, $userId);
$todayStats  = getTodayStats($pdo, $userId);
$allTime     = getAllTimeStats($pdo, $userId);
$quote       = getDailyQuote();
$today       = date('l, j F Y');

// Build habit list with completion + streak data
$habitData = [];
foreach ($habits as $h) {
    $habitData[] = [
        'habit'     => $h,
        'done'      => isCompletedToday($pdo, $h['id']),
        'streak'    => getCurrentStreak($pdo, $h['id']),
        'longest'   => getLongestStreak($pdo, $h['id']),
    ];
}

// SVG circle progress values
$pct         = $todayStats['pct'];
$circumference = 2 * M_PI * 54; // r=54
$dashOffset  = $circumference * (1 - $pct / 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — HabitFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="container">

        <!-- Quote Banner -->
        <div class="quote-banner">
            <span class="quote-icon">💬</span>
            <span class="quote-text"><?= htmlspecialchars($quote) ?></span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Good <?= (date('G') < 12) ? 'morning' : ((date('G') < 18) ? 'afternoon' : 'evening') ?>! 👋</h1>
                <p class="subtitle"><?= $today ?></p>
            </div>
            <?php if (!empty($habits)): ?>
                <div style="display:flex;gap:10px;">
                    <a href="export.php" class="btn btn-ghost">⬇ Export CSV</a>
                    <a href="manage.php" class="btn btn-ghost">+ Add Habit</a>
                </div>
            <?php endif; ?>
            </div>

        <?php if (empty($habits)): ?>
        <!-- Empty state -->
        <div class="card empty-card">
            <div class="empty-icon">🌱</div>
            <h2>No habits yet</h2>
            <p>Start building your routine by adding your first habit.</p>
            <a href="manage.php" class="btn btn-primary">Add Your First Habit</a>
        </div>
        <?php else: ?>

        <!-- Top Stats Row -->
        <div class="stats-grid">

            <!-- Today's Progress Ring -->
            <div class="card stat-ring-card">
                <h3 class="stat-label">Today's Progress</h3>
                <div class="ring-wrapper">
                    <svg class="progress-ring" viewBox="0 0 120 120">
                        <circle class="ring-bg"    cx="60" cy="60" r="54" />
                        <circle class="ring-fill"  cx="60" cy="60" r="54"
                                stroke-dasharray="<?= round($circumference, 2) ?>"
                                stroke-dashoffset="<?= round($dashOffset, 2) ?>"
                                id="progress-circle" />
                    </svg>
                    <div class="ring-label">
                        <span class="ring-pct" id="ring-pct"><?= $pct ?>%</span>
                        <span class="ring-sub" id="ring-sub"><?= $todayStats['done'] ?>/<?= $todayStats['total'] ?></span>
                    </div>
                </div>
            </div>

            <!-- All-time Stats -->
            <div class="card stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= number_format($allTime['total_completions']) ?></div>
                <div class="stat-label">Total Completions</div>
            </div>

            <div class="card stat-card">
                <div class="stat-icon">🔥</div>
                <div class="stat-value"><?= $allTime['best_streak'] ?></div>
                <div class="stat-label">Best Streak (days)</div>
            </div>

            <div class="card stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?= $allTime['completion_rate'] ?>%</div>
                <div class="stat-label">All-Time Rate</div>
            </div>

        </div>

        <!-- Today's Habits Checklist -->
        <div class="card">
            <div class="card-header-row">
                <h2 class="card-title">Today's Habits</h2>
                <span class="badge" id="done-badge"><?= $todayStats['done'] ?> / <?= $todayStats['total'] ?> done</span>
            </div>

            <ul class="habit-list" id="habit-list">
                <?php foreach ($habitData as $item):
                    $h    = $item['habit'];
                    $done = $item['done'];
                    $streak = $item['streak'];
                ?>
                <li class="habit-item <?= $done ? 'is-done' : '' ?>" id="habit-<?= $h['id'] ?>">
                    <button class="habit-check"
                            onclick="toggleHabit(<?= $h['id'] ?>)"
                            style="--habit-color: <?= htmlspecialchars($h['color']) ?>;"
                            aria-label="Toggle <?= htmlspecialchars($h['name']) ?>">
                        <span class="check-icon"><?= $done ? '✓' : '' ?></span>
                    </button>

                    <span class="habit-icon-sm"><?= htmlspecialchars($h['icon']) ?></span>

                    <div class="habit-info">
                        <span class="habit-name"><?= htmlspecialchars($h['name']) ?></span>
                        <?php if ($streak > 0): ?>
                        <span class="streak-tag" id="streak-<?= $h['id'] ?>">
                            🔥 <?= $streak ?> day streak
                        </span>
                        <?php else: ?>
                        <span class="streak-tag muted" id="streak-<?= $h['id'] ?>">
                            Start your streak today!
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="habit-right">
                        <span class="done-label"><?= $done ? 'Done!' : '' ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php endif; // end empty habits check ?>

    </main>

    <script>
    const CIRCUMFERENCE = <?= round($circumference, 2) ?>;

    async function toggleHabit(habitId) {
        const item = document.getElementById('habit-' + habitId);
        item.classList.add('loading');

        try {
            const res  = await fetch('toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'habit_id=' + habitId,
            });
            const data = await res.json();

            // Update item state
            item.classList.toggle('is-done', data.completed);
            item.classList.remove('loading');

            const checkIcon = item.querySelector('.check-icon');
            checkIcon.textContent = data.completed ? '✓' : '';

            const doneLabel = item.querySelector('.done-label');
            doneLabel.textContent = data.completed ? 'Done!' : '';

            // Update streak
            const streakTag = document.getElementById('streak-' + habitId);
            if (data.streak > 0) {
                streakTag.textContent = '🔥 ' + data.streak + ' day streak';
                streakTag.classList.remove('muted');
            } else {
                streakTag.textContent = 'Start your streak today!';
                streakTag.classList.add('muted');
            }

            // Update global progress
            updateProgress(data.today_pct, data.today_done, data.today_total);

        } catch (e) {
            item.classList.remove('loading');
            console.error('Toggle failed', e);
        }
    }

    function updateProgress(pct, done, total) {
        // Ring
        const offset = CIRCUMFERENCE * (1 - pct / 100);
        document.getElementById('progress-circle').style.strokeDashoffset = offset;
        document.getElementById('ring-pct').textContent = pct + '%';
        document.getElementById('ring-sub').textContent = done + '/' + total;

        // Badge
        document.getElementById('done-badge').textContent = done + ' / ' + total + ' done';
    }
    </script>
</body>
</html>
