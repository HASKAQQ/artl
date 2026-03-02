<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errorMessage = '';
$serviceId = (int) ($_GET['service_id'] ?? 0);
$service = null;
$artist = null;
$artistTags = [];
$reviews = [];
$orderSuccessMessage = '';
$orderActionError = '';
$reviewSuccessMessage = '';
$reviewActionError = '';
$canLeaveReview = false;

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

    if (preg_match('~(?:^|/)pub2/(.+)$~i', $normalized, $matches)) {
        $normalized = (string) $matches[1];
    }

    if (preg_match('~(?:^|/)(uploads/.+)$~i', $normalized, $matches)) {
        $normalized = (string) $matches[1];
    }

    return ltrim($normalized, '/');
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!hasColumn($conn, $table, $column)) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeTable !== '' && $safeColumn !== '') {
            $conn->query("ALTER TABLE {$safeTable} ADD COLUMN {$safeColumn} {$definition}");
        }
    }
}

function ensureOrdersTable(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            artist_user_id INT UNSIGNED DEFAULT NULL,
            artist_phone VARCHAR(20) NOT NULL,
            buyer_phone VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "paid",
            service_title VARCHAR(255) NOT NULL,
            service_category VARCHAR(255) DEFAULT NULL,
            service_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            service_image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_artist_phone (artist_phone),
            INDEX idx_buyer_phone (buyer_phone),
            INDEX idx_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureReviewsTable(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            reviews TEXT NOT NULL,
            reviewer_user_id INT UNSIGNED DEFAULT NULL,
            reviewer_name VARCHAR(255) DEFAULT NULL,
            reviewer_avatar_path VARCHAR(255) DEFAULT NULL,
            reviewer_role VARCHAR(100) DEFAULT NULL,
            service_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_review_user_id (user_id),
            INDEX idx_reviewer_user_id (reviewer_user_id),
            INDEX idx_review_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    ensureColumnExists($conn, 'reviews', 'reviewer_user_id', 'INT UNSIGNED DEFAULT NULL');
    ensureColumnExists($conn, 'reviews', 'reviewer_name', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'reviews', 'reviewer_avatar_path', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'reviews', 'reviewer_role', 'VARCHAR(100) DEFAULT NULL');
    ensureColumnExists($conn, 'reviews', 'service_id', 'INT UNSIGNED DEFAULT NULL');
    ensureColumnExists($conn, 'reviews', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
}

function formatTimeAgo(string $datetime): string
{
    if ($datetime === '') {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'только что';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' мин назад';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' ч назад';
    }

    return floor($diff / 86400) . ' дн назад';
}

try {
    if ($serviceId <= 0) {
        throw new RuntimeException('Услуга не найдена.');
    }

    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    ensureOrdersTable($conn);
    ensureReviewsTable($conn);

    $userColumns = ['u.id AS artist_user_id', 'u.name', 'u.avatar_path', 'u.phone'];
    $userColumns[] = hasColumn($conn, 'users', 'about') ? 'u.about' : 'NULL AS about';
    $userColumns[] = hasColumn($conn, 'users', 'social_vk') ? 'u.social_vk' : 'NULL AS social_vk';
    $userColumns[] = hasColumn($conn, 'users', 'social_email') ? 'u.social_email' : 'NULL AS social_email';

    $serviceStmt = prepareOrFail(
        $conn,
        'SELECT s.id, s.title, s.category, s.price, s.description, s.image_path, s.created_at, s.user_phone, ' . implode(', ', $userColumns) . '
         FROM artist_services s
         LEFT JOIN users u ON u.phone = s.user_phone
         WHERE s.id = ?
         LIMIT 1'
    );
    $serviceStmt->bind_param('i', $serviceId);
    $serviceStmt->execute();
    $service = $serviceStmt->get_result()->fetch_assoc();

    if (!$service) {
        throw new RuntimeException('Услуга не найдена.');
    }

    $artist = [
        'id' => (int) ($service['artist_user_id'] ?? 0),
        'user_id' => (int) ($service['artist_user_id'] ?? 0),
        'name' => trim((string) ($service['name'] ?? 'Художник')),
        'avatar_path' => trim((string) ($service['avatar_path'] ?? '')),
        'about' => trim((string) ($service['about'] ?? '')),
        'phone' => trim((string) ($service['phone'] ?? '')),
        'social_vk' => trim((string) ($service['social_vk'] ?? '')),
        'social_email' => trim((string) ($service['social_email'] ?? '')),
    ];
    $artistUserId = (int) ($service['artist_user_id'] ?? 0);
    $artistPhone = trim((string) ($service['user_phone'] ?? ''));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'buy_service') {
        $buyerPhone = trim((string) ($_SESSION['user_phone'] ?? ''));
        if ($buyerPhone === '') {
            $orderActionError = 'Чтобы оформить заказ, сначала войдите в аккаунт.';
        } elseif ($artistPhone === '') {
            $orderActionError = 'Не удалось определить художника для выбранной услуги.';
        } else {
            $status = 'paid';
            $serviceTitle = trim((string) ($service['title'] ?? 'Услуга художника'));
            $serviceCategory = trim((string) ($service['category'] ?? ''));
            $servicePrice = (float) ($service['price'] ?? 0);
            $serviceImagePath = trim((string) ($service['image_path'] ?? ''));

            $insertOrder = prepareOrFail(
                $conn,
                'INSERT INTO artist_orders (service_id, artist_user_id, artist_phone, buyer_phone, status, service_title, service_category, service_price, service_image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertOrder->bind_param(
                'iisssssds',
                $serviceId,
                $artistUserId,
                $artistPhone,
                $buyerPhone,
                $status,
                $serviceTitle,
                $serviceCategory,
                $servicePrice,
                $serviceImagePath
            );
            $insertOrder->execute();
            $orderSuccessMessage = 'Заказ оформлен. Художник увидит его в разделе «Заказы».';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'leave_review') {
        $buyerPhone = trim((string) ($_SESSION['user_phone'] ?? ''));
        $reviewText = trim((string) ($_POST['review_text'] ?? ''));

        if ($buyerPhone === '') {
            $reviewActionError = 'Чтобы оставить отзыв, сначала войдите в аккаунт.';
        } elseif ($reviewText === '') {
            $reviewActionError = 'Введите текст отзыва.';
        } elseif ($artistUserId <= 0 || !hasColumn($conn, 'reviews', 'user_id') || !hasColumn($conn, 'reviews', 'reviews')) {
            $reviewActionError = 'Сейчас оставить отзыв нельзя. Попробуйте позже.';
        } else {
            $purchaseCheckStmt = prepareOrFail(
                $conn,
                'SELECT id FROM artist_orders WHERE service_id = ? AND buyer_phone = ? LIMIT 1'
            );
            $purchaseCheckStmt->bind_param('is', $serviceId, $buyerPhone);
            $purchaseCheckStmt->execute();
            $purchaseExists = $purchaseCheckStmt->get_result()->fetch_assoc();

            if (!$purchaseExists) {
                $reviewActionError = 'Оставить отзыв можно только после покупки услуги.';
            } else {
                $reviewerInfoStmt = prepareOrFail(
                    $conn,
                    'SELECT id, name, avatar_path, role FROM users WHERE phone = ? LIMIT 1'
                );
                $reviewerInfoStmt->bind_param('s', $buyerPhone);
                $reviewerInfoStmt->execute();
                $reviewerInfo = $reviewerInfoStmt->get_result()->fetch_assoc() ?: [];

                $reviewerUserId = (int) ($reviewerInfo['id'] ?? 0);
                $reviewerName = trim((string) ($reviewerInfo['name'] ?? 'Пользователь'));
                $reviewerAvatar = trim((string) ($reviewerInfo['avatar_path'] ?? ''));
                $reviewerRole = trim((string) ($reviewerInfo['role'] ?? 'Пользователь'));

                $hasReviewMetaColumns = hasColumn($conn, 'reviews', 'reviewer_user_id')
                    && hasColumn($conn, 'reviews', 'reviewer_name')
                    && hasColumn($conn, 'reviews', 'reviewer_avatar_path')
                    && hasColumn($conn, 'reviews', 'reviewer_role')
                    && hasColumn($conn, 'reviews', 'service_id');

                if ($hasReviewMetaColumns) {
                    $insertReviewStmt = prepareOrFail(
                        $conn,
                        'INSERT INTO reviews (user_id, reviews, reviewer_user_id, reviewer_name, reviewer_avatar_path, reviewer_role, service_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insertReviewStmt->bind_param('isisssi', $artistUserId, $reviewText, $reviewerUserId, $reviewerName, $reviewerAvatar, $reviewerRole, $serviceId);
                } else {
                    $insertReviewStmt = prepareOrFail(
                        $conn,
                        'INSERT INTO reviews (user_id, reviews) VALUES (?, ?)'
                    );
                    $insertReviewStmt->bind_param('is', $artistUserId, $reviewText);
                }

                $insertReviewStmt->execute();
                $reviewSuccessMessage = 'Спасибо! Ваш отзыв опубликован.';
            }
        }
    }

    $viewerPhone = trim((string) ($_SESSION['user_phone'] ?? ''));
    if ($viewerPhone !== '') {
        $canReviewStmt = prepareOrFail(
            $conn,
            'SELECT id FROM artist_orders WHERE service_id = ? AND buyer_phone = ? LIMIT 1'
        );
        $canReviewStmt->bind_param('is', $serviceId, $viewerPhone);
        $canReviewStmt->execute();
        $canLeaveReview = $canReviewStmt->get_result()->fetch_assoc() !== null;
    }

    if ($artistUserId > 0 && hasColumn($conn, 'profile_categories', 'profile_user_id')) {
        $tagsStmt = prepareOrFail(
            $conn,
            'SELECT DISTINCT TRIM(COALESCE(c.categories, pc.custom_category)) AS tag_name
             FROM profile_categories pc
             LEFT JOIN categories c ON c.id = pc.category_id
             WHERE pc.profile_user_id = ? OR pc.user_phone = ?'
        );
        $tagsStmt->bind_param('is', $artistUserId, $artistPhone);
        $tagsStmt->execute();
        $tagsRes = $tagsStmt->get_result();
        while ($tagRow = $tagsRes->fetch_assoc()) {
            $tag = trim((string) ($tagRow['tag_name'] ?? ''));
            if ($tag !== '') {
                $artistTags[] = $tag;
            }
        }
    }

    if (count($artistTags) === 0 && trim((string) ($service['category'] ?? '')) !== '') {
        $artistTags[] = trim((string) $service['category']);
    }

    if ($artistUserId > 0 && hasColumn($conn, 'reviews', 'user_id') && hasColumn($conn, 'reviews', 'reviews')) {
        $reviewsColumns = ['id', 'reviews'];
        $reviewsColumns[] = hasColumn($conn, 'reviews', 'reviewer_name') ? 'reviewer_name' : 'NULL AS reviewer_name';
        $reviewsColumns[] = hasColumn($conn, 'reviews', 'reviewer_avatar_path') ? 'reviewer_avatar_path' : 'NULL AS reviewer_avatar_path';
        $reviewsColumns[] = hasColumn($conn, 'reviews', 'reviewer_role') ? 'reviewer_role' : 'NULL AS reviewer_role';
        $reviewsStmt = prepareOrFail($conn, 'SELECT ' . implode(', ', $reviewsColumns) . ' FROM reviews WHERE user_id = ? ORDER BY id DESC LIMIT 6');
        $reviewsStmt->bind_param('i', $artistUserId);
        $reviewsStmt->execute();
        $reviewsRes = $reviewsStmt->get_result();
        while ($review = $reviewsRes->fetch_assoc()) {
            $reviews[] = [
                'name' => trim((string) ($review['reviewer_name'] ?? '')) !== '' ? (string) $review['reviewer_name'] : 'Пользователь',
                'role' => trim((string) ($review['reviewer_role'] ?? '')) !== '' ? (string) $review['reviewer_role'] : 'Пользователь',
                'avatar_path' => normalizeImagePath((string) ($review['reviewer_avatar_path'] ?? ''), 'src/image/Ellipse 2.png'),
                'text' => trim((string) ($review['reviews'] ?? '')),
            ];
        }
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ARTlance — Оформление заказа</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>
<body>

  <?php include 'header.php'; ?>

  <section class="order-page-section">
    <div class="container">
      <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($orderActionError !== ''): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($orderActionError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($orderSuccessMessage !== ''): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($orderSuccessMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($reviewActionError !== ''): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($reviewActionError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($reviewSuccessMessage !== ''): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($reviewSuccessMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($errorMessage === '' && is_array($service)): ?>
      <div class="order-page-card">
        <div class="order-page-header">
          <h1 class="order-page-title">Оформление заказа</h1>
          <span class="order-page-published">Опубликовано <?php echo htmlspecialchars(formatTimeAgo((string) ($service['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="row g-0">
          <div class="col-lg-5">
            <div class="order-page-image" style="background: url('<?php echo htmlspecialchars(trim((string) ($service['image_path'] ?? '')) !== '' ? (string) $service['image_path'] : 'src/image/Rectangle 55.png', ENT_QUOTES, 'UTF-8'); ?>') center/cover no-repeat;"></div>
          </div>

          <div class="col-lg-7">
            <div class="order-page-artist">
              <?php if ((int) ($artist['user_id'] ?? 0) > 0): ?>
                <a href="profile-artist.php?user_id=<?php echo (int) $artist['user_id']; ?>" class="text-decoration-none text-reset d-inline-block">
              <?php endif; ?>
                  <div class="order-page-avatar-wrapper">
                    <img src="<?php echo htmlspecialchars(normalizeImagePath((string) ($artist['avatar_path'] ?? ''), 'src/image/Ellipse 2.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?>" class="order-page-avatar">
                  </div>

                  <h2 class="order-page-artist-name"><?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if ((int) ($artist['user_id'] ?? 0) > 0): ?>
                </a>
              <?php endif; ?>

              <?php if (count($artistTags) > 0): ?>
                <div class="order-page-tags">
                  <?php foreach ($artistTags as $tag): ?>
                    <span class="order-page-tag"><?php echo htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <p class="order-page-artist-desc"><?php echo htmlspecialchars(trim((string) ($artist['about'] ?? '')) !== '' ? (string) $artist['about'] : 'Описание пользователя...', ENT_QUOTES, 'UTF-8'); ?></p>

              <div class="order-page-contacts">
                <span class="order-page-contacts-label">Связаться через</span>
                <div class="order-page-contacts-icons">
                  <a href="<?php echo htmlspecialchars(trim((string) ($artist['social_vk'] ?? '')) !== '' ? (string) $artist['social_vk'] : '#', ENT_QUOTES, 'UTF-8'); ?>" class="order-page-contact-icon<?php echo trim((string) ($artist['social_vk'] ?? '')) === '' ? ' is-empty' : ''; ?>" target="_blank" rel="noopener noreferrer" <?php echo trim((string) ($artist['social_vk'] ?? '')) === '' ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                    <img src="src/image/icons/vk-icon.svg" alt="VK">
                  </a>
                  <a href="<?php echo htmlspecialchars(trim((string) ($artist['social_email'] ?? '')) !== '' ? 'mailto:' . (string) $artist['social_email'] : '#', ENT_QUOTES, 'UTF-8'); ?>" class="order-page-contact-icon<?php echo trim((string) ($artist['social_email'] ?? '')) === '' ? ' is-empty' : ''; ?>" <?php echo trim((string) ($artist['social_email'] ?? '')) === '' ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                    <img src="src/image/icons/icons8-почта-100 1.svg" alt="Email" class="contact-link-email-icon">
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="order-page-details">
          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Название услуги</h3>
            <p class="order-page-detail-value"><?php echo htmlspecialchars((string) ($service['title'] ?? 'Услуга художника'), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Категория</h3>
            <p class="order-page-detail-value"><?php echo htmlspecialchars(trim((string) ($service['category'] ?? '')) !== '' ? (string) $service['category'] : 'Без категории', ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Подробное описание</h3>
            <p class="order-page-detail-value"><?php echo htmlspecialchars(trim((string) ($service['description'] ?? '')) !== '' ? (string) $service['description'] : 'Описание услуги не указано.', ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <h3 class="order-page-detail-label">Способ оплаты</h3>
          <div class="order-page-payment">
            <span class="order-page-payment-method">Банковская карта</span>
            <span class="order-page-payment-price"><?php echo number_format((float) ($service['price'] ?? 0), 0, '.', ' '); ?>₽</span>
          </div>

          <div class="order-page-buy-wrapper">
            <form method="post" class="m-0">
              <input type="hidden" name="action" value="buy_service">
              <input type="hidden" name="service_id" value="<?php echo (int) $serviceId; ?>">
              <button class="order-page-buy-btn" type="submit">Купить</button>
            </form>
          </div>
        </div>
      </div>

      <div class="order-page-reviews">
        <div class="order-page-reviews-header">
          <h2 class="order-page-reviews-title">Отзывы</h2>
          <?php if ($canLeaveReview): ?>
            <button class="order-page-leave-review-btn" type="button" onclick="const f=document.getElementById('orderReviewForm'); if(f){f.style.display='block';}">Оставить отзыв</button>
          <?php endif; ?>
        </div>

        <?php if ($canLeaveReview): ?>
          <form method="post" class="order-page-review-card mb-3" id="orderReviewForm" style="<?php echo ($reviewActionError !== '' || $reviewSuccessMessage !== '') ? 'display:block;' : 'display:none;'; ?>">
            <div class="order-page-review-content w-100">
              <input type="hidden" name="action" value="leave_review">
              <textarea name="review_text" class="form-control mb-2" rows="3" maxlength="1000" placeholder="Напишите отзыв о работе художника..."></textarea>
              <button class="order-page-buy-btn" type="submit" style="max-width:160px;padding:8px 16px;font-size:14px;">Отправить отзыв</button>
            </div>
          </form>
        <?php endif; ?>

        <?php if (count($reviews) > 0): ?>
          <?php foreach ($reviews as $review): ?>
            <div class="order-page-review-card">
              <img src="<?php echo htmlspecialchars((string) ($review['avatar_path'] ?? 'src/image/Ellipse 2.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Аватар автора отзыва" class="review-avatar">
              <div class="order-page-review-content">
                <h4 class="order-page-review-name"><?php echo htmlspecialchars((string) ($review['name'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="mb-1 text-muted"><?php echo htmlspecialchars((string) ($review['role'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="order-page-review-text"><?php echo htmlspecialchars((string) ($review['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="order-page-review-card">
            <div class="order-page-review-content">
              <p class="order-page-review-text">Пока нет отзывов.</p>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </section>

  <div id="footer-placeholder"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
