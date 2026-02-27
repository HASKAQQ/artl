<?php
$errorMessage = '';
$serviceId = (int) ($_GET['service_id'] ?? 0);
$service = null;
$artist = null;
$artistTags = [];
$reviews = [];

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

    $userColumns = ['u.id AS artist_user_id', 'u.name', 'u.avatar_path', 'u.phone'];
    $userColumns[] = hasColumn($conn, 'users', 'about') ? 'u.about' : 'NULL AS about';
    $userColumns[] = hasColumn($conn, 'users', 'social_telegram') ? 'u.social_telegram' : 'NULL AS social_telegram';
    $userColumns[] = hasColumn($conn, 'users', 'social_whatsapp') ? 'u.social_whatsapp' : 'NULL AS social_whatsapp';
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
        'social_telegram' => trim((string) ($service['social_telegram'] ?? '')),
        'social_whatsapp' => trim((string) ($service['social_whatsapp'] ?? '')),
        'social_email' => trim((string) ($service['social_email'] ?? '')),
    ];
    $artistUserId = (int) ($service['artist_user_id'] ?? 0);
    $artistPhone = trim((string) ($service['user_phone'] ?? ''));

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
        $reviewsStmt = prepareOrFail($conn, 'SELECT id, reviews FROM reviews WHERE user_id = ? ORDER BY id DESC LIMIT 6');
        $reviewsStmt->bind_param('i', $artistUserId);
        $reviewsStmt->execute();
        $reviewsRes = $reviewsStmt->get_result();
        while ($review = $reviewsRes->fetch_assoc()) {
            $reviews[] = [
                'name' => 'Пользователь',
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
              <div class="order-page-avatar-wrapper">
                <img src="<?php echo htmlspecialchars(trim((string) ($artist['avatar_path'] ?? '')) !== '' ? (string) $artist['avatar_path'] : 'src/image/Ellipse 2.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?>" class="order-page-avatar">
              </div>

              <h2 class="order-page-artist-name"><?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></h2>

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
                  <a href="<?php echo htmlspecialchars(trim((string) ($artist['social_telegram'] ?? '')) !== '' ? (string) $artist['social_telegram'] : '#', ENT_QUOTES, 'UTF-8'); ?>" class="order-page-contact-icon" target="_blank" rel="noopener noreferrer">
                    <img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram">
                  </a>
                  <a href="<?php echo htmlspecialchars(trim((string) ($artist['social_whatsapp'] ?? '')) !== '' ? (string) $artist['social_whatsapp'] : '#', ENT_QUOTES, 'UTF-8'); ?>" class="order-page-contact-icon" target="_blank" rel="noopener noreferrer">
                    <img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp">
                  </a>
                  <a href="<?php echo htmlspecialchars(trim((string) ($artist['social_email'] ?? '')) !== '' ? 'mailto:' . (string) $artist['social_email'] : '#', ENT_QUOTES, 'UTF-8'); ?>" class="order-page-contact-icon">
                    <img src="src/image/icons/icons8-почта-100 1.svg" alt="Email">
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
            <button class="order-page-buy-btn" type="button">Купить</button>
          </div>
        </div>
      </div>

      <div class="order-page-reviews">
        <div class="order-page-reviews-header">
          <h2 class="order-page-reviews-title">Отзывы</h2>
          <button class="order-page-leave-review-btn" type="button">Оставить отзыв</button>
        </div>

        <?php if (count($reviews) > 0): ?>
          <?php foreach ($reviews as $review): ?>
            <div class="order-page-review-card">
              <div class="order-page-review-avatar-placeholder"></div>
              <div class="order-page-review-content">
                <h4 class="order-page-review-name"><?php echo htmlspecialchars((string) ($review['name'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8'); ?></h4>
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
