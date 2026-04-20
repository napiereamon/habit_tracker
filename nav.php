<nav class="navbar">
    <div class="nav-inner">
        <a href="index.php" class="nav-brand">
            <span class="brand-icon">🔥</span>
            HabitFlow
        </a>
        <ul class="nav-links">
            <li><a href="index.php"    class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php'    ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="calendar.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'calendar.php' ? 'active' : '' ?>">Calendar</a></li>
            <li><a href="manage.php"   class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage.php'   ? 'active' : '' ?>">Habits</a></li>
        </ul>
        <div class="nav-user">
            <span class="nav-username">👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            <a href="login.php?action=logout" class="btn btn-ghost btn-sm">Sign out</a>
        </div>
    </div>
</nav>