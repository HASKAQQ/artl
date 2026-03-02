<?php
if (!function_exists('normalizeImagePath')) {
function normalizeImagePath(string $path, string $fallback): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return $fallback;
        }
    
        if (preg_match('~^https?://~i', $trimmed) || str_starts_with($trimmed, 'data:')) {
            return $trimmed;
        }
    
        $normalized = str_replace('\\', '/', $trimmed);
        if (str_starts_with($normalized, 'pub2/')) {
            $normalized = substr($normalized, 5);
        }
        if (str_starts_with($normalized, '/pub2/')) {
            $normalized = substr($normalized, 6);
        }
    
        return ltrim($normalized, '/');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$userPhone = $_SESSION['user_phone'] ?? '';
$avatarPath = '';
$userRole = 'Художник';

if ($isLoggedIn && $userPhone !== '') {
    try {
        $conn = @new mysqli('MySQL-8.0', 'root', '');
        if (!$conn->connect_error) {
            $conn->set_charset('utf8mb4');
            if ($conn->select_db('artlance')) {
                $stmt = $conn->prepare('SELECT avatar_path, role FROM users WHERE phone = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $userPhone);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $avatarPath = normalizeImagePath((string) ($row['avatar_path'] ?? ''), '');
                    $userRole = (string) ($row['role'] ?? 'Художник');
                }
            }
        }
    } catch (Throwable $e) {
        $avatarPath = '';
$userRole = 'Художник';
    }
}

$avatarSrc = htmlspecialchars(normalizeImagePath($avatarPath, 'src/image/Ellipse 2.png'), ENT_QUOTES, 'UTF-8');
$profileLink = ($userRole === 'Заказчик') ? 'profile-client-edit.php' : 'profile-artist-edit.php';
$profileMenuLabel = 'Профиль';
if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $profileLink = 'admin-main.php';
    $profileMenuLabel = 'Панель';
}
?>
<header class="header border-bottom border-4" id="header">
  <nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container">
      <a href="index.php" class="logo navbar-brand text-decoration-none">ARTlance</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mx-auto">
          <li class="nav-item">
            <a class="nav-link fw-semibold text-dark" href="index.php#about">О проекте</a>
          </li>
          <li class="nav-item">
            <a class="nav-link fw-semibold text-dark" href="uslugi.php">Категории</a>
          </li>
          <li class="nav-item">
            <a class="nav-link fw-semibold text-dark" href="artists.php">Художники</a>
          </li>
        </ul>

        <?php if ($isLoggedIn): ?>
          <div class="header-user-menu d-none d-lg-block" id="headerUserMenu">
            <button class="header-avatar-btn" id="headerAvatarBtn" type="button" aria-label="Меню пользователя">
              <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="header-avatar-img">
            </button>
            <div class="header-user-dropdown" id="headerUserDropdown">
              <a href="<?php echo htmlspecialchars($profileLink, ENT_QUOTES, 'UTF-8'); ?>" class="header-user-dropdown-item"><?php echo htmlspecialchars($profileMenuLabel, ENT_QUOTES, 'UTF-8'); ?></a>
              <a href="logout.php" class="header-user-dropdown-item">Выйти</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php" class="login text-decoration-none d-none d-lg-block">Вход</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
