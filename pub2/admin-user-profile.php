<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }
    return $stmt;
}


function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }
    $result = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result !== false && $result->num_rows > 0;
}

function formatTimeAgo(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return floor($diff / 60) . ' мин назад';
    if ($diff < 86400) return floor($diff / 3600) . ' ч назад';
    return floor($diff / 86400) . ' дн назад';
}

function getDbConnection(): mysqli
{
    $conn = new mysqli('MySQL-8.0', 'root', '');

    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query('CREATE DATABASE IF NOT EXISTS artlance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) DEFAULT NULL,
            role VARCHAR(30) NOT NULL DEFAULT "Художник",
            avatar_path VARCHAR(255) DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );


    $columnsRes = $conn->query('SHOW COLUMNS FROM users');
    $columns = [];
    if ($columnsRes !== false) {
        while ($column = $columnsRes->fetch_assoc()) {
            $field = (string) ($column['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
    }
    if (!isset($columns['about'])) {
        $conn->query('ALTER TABLE users ADD COLUMN about TEXT DEFAULT NULL');
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categories VARCHAR(255) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 1,
            created_by_phone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS profile_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) DEFAULT NULL,
            profile_user_id INT UNSIGNED DEFAULT NULL,
            category_id INT UNSIGNED DEFAULT NULL,
            custom_category VARCHAR(32) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

$errorMessage = '';
$user = null;
$userCategories = [];
$userAbout = '';
$portfolioWorks = [];
$services = [];
$orders = [];
$reviews = [];

try {
    $conn = getDbConnection();

    $userId = (int) ($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Некорректный идентификатор пользователя.');
    }

    $stmt = prepareOrFail($conn, 'SELECT id, name, phone, role, avatar_path, is_blocked, registered_at, about FROM users WHERE id = ? LIMIT 1');

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new RuntimeException('Пользователь не найден.');
    }

    $userAbout = trim((string) ($user['about'] ?? ''));
    $userPhone = trim((string) ($user['phone'] ?? ''));

    if ($userPhone !== '') {
        $catStmt = prepareOrFail(
            $conn,
            'SELECT pc.custom_category, c.categories AS category_name, c.is_default AS category_is_default
             FROM profile_categories pc
             LEFT JOIN categories c ON c.id = pc.category_id
             WHERE pc.user_phone = ? OR pc.profile_user_id = ?'
        );
        $userIdForCategories = (int) ($user['id'] ?? 0);
        $catStmt->bind_param('si', $userPhone, $userIdForCategories);
        $catStmt->execute();
        $catRes = $catStmt->get_result();
        while ($catRow = $catRes->fetch_assoc()) {
            $customCategory = trim((string) ($catRow['custom_category'] ?? ''));
            $categoryName = trim((string) ($catRow['category_name'] ?? ''));
            if ($customCategory !== '') {
                $userCategories[$customCategory] = $customCategory;
            } elseif ($categoryName !== '') {
                $userCategories[$categoryName] = $categoryName;
            }
        }
    }


    $portfolioStmt = prepareOrFail($conn, 'SELECT id, title, created_at FROM portfolio_works WHERE user_phone = ? ORDER BY id DESC');
    $portfolioStmt->bind_param('s', $userPhone);
    $portfolioStmt->execute();
    $portfolioRes = $portfolioStmt->get_result();
    while ($work = $portfolioRes->fetch_assoc()) {
        $imgStmt = prepareOrFail($conn, 'SELECT image_path FROM portfolio_images WHERE work_id = ? ORDER BY sort_order ASC, id ASC');
        $workId = (int) ($work['id'] ?? 0);
        $imgStmt->bind_param('i', $workId);
        $imgStmt->execute();
        $imgRes = $imgStmt->get_result();
        $images = [];
        while ($img = $imgRes->fetch_assoc()) {
            $path = trim((string) ($img['image_path'] ?? ''));
            if ($path !== '') {
                $images[] = $path;
            }
        }
        $work['images'] = $images;
        $portfolioWorks[] = $work;
    }

    $servicesStmt = prepareOrFail($conn, 'SELECT id, title, category, price, description, image_path, created_at FROM artist_services WHERE user_phone = ? ORDER BY id DESC');
    $servicesStmt->bind_param('s', $userPhone);
    $servicesStmt->execute();
    $servicesRes = $servicesStmt->get_result();
    while ($service = $servicesRes->fetch_assoc()) {
        $services[] = $service;
    }

    if (hasColumn($conn, 'orders', 'artist_id')) {
        $ordersSql = 'SELECT id, status, order_date';
        if (hasColumn($conn, 'orders', 'service_id')) {
            $ordersSql .= ', service_id';
        }
        $ordersSql .= ' FROM orders WHERE artist_id = ? ORDER BY id DESC';
        $ordersStmt = prepareOrFail($conn, $ordersSql);
        $userNumericId = (int) ($user['id'] ?? 0);
        $ordersStmt->bind_param('i', $userNumericId);
        $ordersStmt->execute();
        $ordersRes = $ordersStmt->get_result();
        while ($order = $ordersRes->fetch_assoc()) {
            $orders[] = $order;
        }
    }

    if (hasColumn($conn, 'reviews', 'user_id') && hasColumn($conn, 'reviews', 'reviews')) {
        $reviewsStmt = prepareOrFail($conn, 'SELECT id, reviews FROM reviews WHERE user_id = ? ORDER BY id DESC');
        $userNumericId = (int) ($user['id'] ?? 0);
        $reviewsStmt->bind_param('i', $userNumericId);
        $reviewsStmt->execute();
        $reviewsRes = $reviewsStmt->get_result();
        while ($review = $reviewsRes->fetch_assoc()) {
            $reviews[] = $review;
        }
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

$displayName = $user ? (string) ($user['name'] ?: 'Пользователь') : 'Профиль';
$displayRole = $user ? (string) ($user['role'] ?: 'Художник') : 'Художник';
$displayDate = $user ? date('d.m.Y', strtotime((string) $user['registered_at'])) : '—';
$avatarPath = $user && !empty($user['avatar_path']) ? (string) $user['avatar_path'] : 'src/image/Ellipse 2.png';
$isBlocked = $user && (int) $user['is_blocked'] === 1;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>

<body>
  <?php include 'header.php'; ?>

  <section class="profile-section">
    <div class="container py-5">
      <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <a href="admin-users.php" class="btn btn-secondary">Назад к пользователям</a>
      <?php else: ?>
      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="<?php echo htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="profile-avatar" id="avatarImage">
          </div>
          <div class="profile-contacts">
            <a href="javascript:void(0)" aria-label="Telegram"><img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram"></a>
            <a href="javascript:void(0)" aria-label="WhatsApp"><img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp"></a>
            <a href="javascript:void(0)" aria-label="Email"><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email"></a>
          </div>

        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="profile-name-row mb-1">
            <h3 class="profile-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="profile-role-toggle">
              <button class="role-btn active" type="button" disabled>
                <?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?> <img src="src/image/icons/icons8-кисть-100 1.svg" alt="">
              </button>
            </div>
            <?php if ($isBlocked): ?>
              <span class="badge bg-danger">Заблокирован</span>
            <?php endif; ?>
          </div>

          <p class="profile-registration">Дата регистрации: <?php echo htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?></p>

          <div class="profile-tags">
            <?php if (count($userCategories) > 0): ?>
              <?php foreach ($userCategories as $categoryName): ?>
                <p class="profile-tag"><?php echo htmlspecialchars((string) $categoryName, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="profile-tag">Категории не указаны</p>
            <?php endif; ?>
          </div>

          <p class="profile-description-main"><?php echo htmlspecialchars($userAbout !== '' ? $userAbout : 'О себе не указано', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

      </div>

      <div class="section-collapsible" id="portfolioSection">
        <div class="section-header" onclick="toggleSection('portfolio')">
          <div class="section-title">
            <h2>Портфолио</h2>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="portfolioArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="portfolioContent">
          <div class="gallary-wrapper row g-3">
            <?php if (count($portfolioWorks) > 0): ?>
              <?php foreach ($portfolioWorks as $work): ?>
                <?php $preview = count($work['images']) > 0 ? $work['images'][0] : 'src/image/Rectangle 55.png'; ?>
                <div class="col-4 col-lg-3"><div class="portfolio-card"><img src="<?php echo htmlspecialchars((string) $preview, ENT_QUOTES, 'UTF-8'); ?>" alt="Portfolio" class="portfolio-image"></div></div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Портфолио пока не заполнено.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="section-collapsible" id="servicesSection">
        <div class="section-header" onclick="toggleSection('services')">
          <div class="section-title">
            <h2>Услуги</h2>
          </div>
          <div class="header-actions">
            <span class="toggle-arrow" id="servicesArrow">▼</span>
          </div>
        </div>
        <div class="section-content" id="servicesContent">
          <div class="services-grid row">
            <?php if (count($services) > 0): ?>
              <?php foreach ($services as $service): ?>
                <?php $serviceImage = trim((string) ($service['image_path'] ?? '')) !== '' ? (string) $service['image_path'] : 'src/image/Rectangle 55.png'; ?>
                <div class="col-6 col-lg-4">
                  <div class="service-item card h-100">
                    <img src="<?php echo htmlspecialchars($serviceImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Service" class="service-image">
                    <div class="service-info">
                      <h3 class="service-title"><?php echo htmlspecialchars((string) ($service['title'] ?? 'Услуга'), ENT_QUOTES, 'UTF-8'); ?></h3>
                      <p class="service-category"><?php echo htmlspecialchars((string) ($service['category'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                      <div class="service-bottom">
                        <p class="service-price">от <?php echo htmlspecialchars((string) ($service['price'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>р</p>
                        <p class="service-time"><?php echo htmlspecialchars(formatTimeAgo((string) ($service['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Услуги пока не добавлены.</p>
            <?php endif; ?>
          </div></div>
        </div>
      </div>


      <div class="section-collapsible" id="ordersSection">
        <div class="section-header" onclick="toggleSection('orders')">
          <h2>Заказы</h2>
          <span class="toggle-arrow" id="ordersArrow">▼</span>
        </div>
        <div class="section-content" id="ordersContent">
          <div class="reviews-list">
            <?php if (count($orders) > 0): ?>
              <?php foreach ($orders as $order): ?>
                <div class="review-card">
                  <img src="src/image/Ellipse 2.png" alt="Order" class="review-avatar">
                  <div class="review-content">
                    <h4 class="review-name">Заказ #<?php echo (int) ($order['id'] ?? 0); ?></h4>
                    <p class="review-text">Статус: <?php echo htmlspecialchars((string) ($order['status'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($order['order_date'])): ?> · Дата: <?php echo htmlspecialchars((string) $order['order_date'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Заказов пока нет.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="section-collapsible" id="reviewsSection">
        <div class="section-header" onclick="toggleSection('reviews')">
          <h2>Отзывы</h2>
          <span class="toggle-arrow" id="reviewsArrow">▼</span>
        </div>
        <div class="section-content" id="reviewsContent">
          <div class="reviews-list">
            <?php if (count($reviews) > 0): ?>
              <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                  <img src="src/image/Ellipse 2.png" alt="User" class="review-avatar">
                  <div class="review-content"><h4 class="review-name">Отзыв #<?php echo (int) ($review['id'] ?? 0); ?></h4><p class="review-text"><?php echo htmlspecialchars((string) ($review['reviews'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Отзывов пока нет.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
