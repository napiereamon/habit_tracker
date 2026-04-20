<?php
require_once 'db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'functions.php';
$userId = (int) $_SESSION['user_id'];

$errors  = [];
$success = '';

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6366f1';
        $icon  = mb_substr(trim($_POST['icon'] ?? '⭐'), 0, 2);

        if ($name === '') {
            $errors[] = 'Habit name cannot be empty.';
        } elseif (mb_strlen($name) > 80) {
            $errors[] = 'Habit name is too long (max 80 chars).';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO habits (name, color, icon, user_id, created_at) VALUES (?, ?, ?, ?,CURDATE())"
            );
            $stmt->execute([$name, $color, $icon, $userId]);
            $success = "Habit \"$name\" added!";
        }

    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'habit_id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
            $success = 'Habit deleted.';
        }
    }
}

$habits = getAllHabits($pdo, $userId);

$icons = ['⭐','🏃','📚','💪','🧘','🥗','💧','😴','✍️','🎯','🎸','🧹','💊','🚴','🌅'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Habits — HabitFlow</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="container">
        <div class="page-header">
            <h1>Manage Habits</h1>
            <p class="subtitle">Add, customise, or remove your habits.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Add Habit Form -->
        <div class="card">
            <h2 class="card-title">➕ Add New Habit</h2>
            <form method="POST" class="add-form">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
    <div class="form-group flex-grow">
        <label for="name">Habit Name</label>
        <input type="text" id="name" name="name"
               placeholder="e.g. Morning workout"
               maxlength="80" required>
    </div>

    <div class="form-group">
        <label for="icon">Icon</label>
        <div class="icon-picker">
            <input type="text" id="icon" name="icon"
                   value="⭐" maxlength="2" class="icon-input" readonly>
            <div class="icon-grid">
                <?php foreach ($icons as $ic): ?>
                    <span class="icon-option" onclick="pickIcon('<?= $ic ?>')"><?= $ic ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hex Colour Picker -->
<?php
$defaultColor = '#ef4444';

$colors = [
    '#fca5a5', '#fed7aa', '#fde68a',
    '#ef4444', '#f97316', '#eab308', '#bbf7d0',
    '#dc2626', '#ea580c', '#22c55e', '#93c5fd', '#e9d5ff',
    '#f9a8d4', '#f59e0b', '#16a34a', '#3b82f6',
    '#ec4899', '#a855f7', '#1d4ed8',
];

$rowPattern  = [3, 4, 5, 4, 3];
$maxPerRow   = max($rowPattern);
$swatchSize  = 38;
$gap         = 5;
$sliceOffset = 0;
?>
<div class="form-group">
    <label>Colour</label>
    <input type="hidden" id="color" name="color" value="<?= $defaultColor ?>">
    <div class="hex-picker-wrap">
        <div class="hex-color-picker" id="hex-picker">
            <?php foreach (array_keys($rowPattern) as $rowIndex):
                $rowCount   = $rowPattern[$rowIndex];
                $rowColors  = array_slice($colors, $sliceOffset, $rowCount);
                $marginLeft = ($maxPerRow - count($rowColors)) / 2 * ($swatchSize + $gap);
                $sliceOffset += $rowCount;
            ?>
                <div class="hex-row" style="margin-left: <?= $marginLeft ?>px;">
                    <?php foreach ($rowColors as $c): ?>
                        <button type="button"
                                class="hex-swatch <?= $c === $defaultColor ? 'selected' : '' ?>"
                                style="background:<?= $c ?>;"
                                data-color="<?= $c ?>"
                                onclick="selectColor('<?= $c ?>', this)"
                                title="<?= $c ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="selected-color-preview">
            <span class="color-dot" id="color-dot" style="background:<?= $defaultColor ?>;"></span>
            <span class="color-hex" id="color-hex"><?= $defaultColor ?></span>
        </div>
    </div>
</div>

                <button type="submit" class="btn btn-primary">Add Habit</button>
            </form>
        </div>

        <!-- Existing Habits -->
        <div class="card">
            <h2 class="card-title">📋 Your Habits (<?= count($habits) ?>)</h2>

            <?php if (empty($habits)): ?>
                <p class="empty-state">No habits yet — add your first one above!</p>
            <?php else: ?>
                <ul class="habit-manage-list">
                    <?php foreach ($habits as $h): ?>
                        <?php
                        require_once 'functions.php';
                        $streak  = getCurrentStreak($pdo, $h['id']);
                        $longest = getLongestStreak($pdo, $h['id']);
                        $logs    = (int) $pdo->prepare("SELECT COUNT(*) FROM habit_logs WHERE habit_id = ?")->execute([$h['id']]) ? $pdo->query("SELECT COUNT(*) FROM habit_logs WHERE habit_id = {$h['id']}")->fetchColumn() : 0;
                        ?>
                        <li class="habit-manage-item">
                            <div class="habit-manage-left">
                                <span class="habit-icon-badge" style="background: <?= htmlspecialchars($h['color']) ?>20; border-color: <?= htmlspecialchars($h['color']) ?>;">
                                    <?= htmlspecialchars($h['icon']) ?>
                                </span>
                                <div>
                                    <strong><?= htmlspecialchars($h['name']) ?></strong>
                                    <div class="habit-meta">
                                        Started <?= date('d M Y', strtotime($h['created_at'])) ?>
                                        · <?= $logs ?> completions
                                        · Best streak: <?= $longest ?> days
                                    </div>
                                </div>
                            </div>
                            <div class="habit-manage-right">
                                <span class="streak-pill" style="border-color: <?= htmlspecialchars($h['color']) ?>">
                                    🔥 <?= $streak ?>d
                                </span>
                                <form method="POST" onsubmit="return confirm('Delete \'<?= addslashes($h['name']) ?>\'? All logs will be lost.');">
                                    <input type="hidden" name="action"   value="delete">
                                    <input type="hidden" name="habit_id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function pickIcon(icon) {
        document.getElementById('icon').value = icon;
        document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
        event.target.classList.add('selected');
    }

    function selectColor(color, el) {
        document.getElementById('color').value = color;
        document.getElementById('color-dot').style.background = color;
        document.getElementById('color-hex').textContent = color;
        document.querySelectorAll('.hex-swatch').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
    }
</script>
</body>
</html>
