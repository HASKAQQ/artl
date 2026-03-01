<?php
session_start();

$errorMessage = '';
$saveMessage = '';
$serviceId = (int) ($_GET['service_id'] ?? 0);
$userPhone = (string) ($_SESSION['user_phone'] ?? '');
$service = null;
$artist = null;

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
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
            social_email VARCHAR(255) DEFAULT NULL,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_services (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_phone VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            artist_phone VARCHAR(20) NOT NULL,
            client_phone VARCHAR(20) NOT NULL,
            service_title VARCHAR(255) NOT NULL,
            service_category VARCHAR(255) NOT NULL,
            service_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            service_image_path VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "Оплачен",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_artist_phone (artist_phone),
            INDEX idx_client_phone (client_phone),
            INDEX idx_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $conn;
}

try {
    if ($serviceId <= 0) {
        throw new RuntimeException('Услуга не найдена.');
    }

    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_service'])) {
        if ($userPhone === '') {
            header('Location: login.php');
            exit;
        }

        $serviceQuery = prepareOrFail($conn, 'SELECT id, user_phone, title, category, price, image_path FROM artist_services WHERE id = ? LIMIT 1');
        $serviceQuery->bind_param('i', $serviceId);
        $serviceQuery->execute();
        $serviceRow = $serviceQuery->get_result()->fetch_assoc();

        if (!$serviceRow) {
            throw new RuntimeException('Услуга не найдена.');
        }

        $artistPhone = (string) ($serviceRow['user_phone'] ?? '');
        if ($artistPhone === $userPhone) {
            throw new RuntimeException('Нельзя оформить заказ на свою услугу.');
        }

        $orderInsert = prepareOrFail(
            $conn,
            'INSERT INTO artist_orders (service_id, artist_phone, client_phone, service_title, service_category, service_price, service_image_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "Оплачен")'
        );

        $title = (string) ($serviceRow['title'] ?? '');
        $category = (string) ($serviceRow['category'] ?? '');
        $price = (float) ($serviceRow['price'] ?? 0);
        $imagePath = (string) ($serviceRow['image_path'] ?? '');
        $serviceIdForInsert = (int) ($serviceRow['id'] ?? 0);

        $orderInsert->bind_param('issssds', $serviceIdForInsert, $artistPhone, $userPhone, $title, $category, $price, $imagePath);
        $orderInsert->execute();

        $saveMessage = 'Заказ успешно оформлен.';
    }

    $stmt = prepareOrFail(
        $conn,
        'SELECT s.id, s.title, s.category, s.price, s.description, s.image_path, s.created_at, s.user_phone,
                u.id AS artist_user_id, u.name AS artist_name, u.avatar_path AS artist_avatar, u.social_email
         FROM artist_services s
         LEFT JOIN users u ON u.phone = s.user_phone
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();

    if (!$service) {
        throw new RuntimeException('Услуга не найдена.');
    }

    $artist = [
        'id' => (int) ($service['artist_user_id'] ?? 0),
        'name' => (string) (($service['artist_name'] ?? '') ?: 'Художник'),
        'avatar' => (string) ($service['artist_avatar'] ?? ''),
        'email' => (string) ($service['social_email'] ?? ''),
    ];
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
      <?php if ($saveMessage !== ''): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($saveMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($errorMessage === '' && is_array($service)): ?>
      <div class="order-page-card">
        <div class="order-page-header">
          <h1 class="order-page-title">Оформление заказа</h1>
          <span class="order-page-published">Опубликовано: <?php echo htmlspecialchars((string) ($service['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="row g-0">
          <div class="col-lg-5">
            <div class="order-page-image" style="background: url('<?php echo htmlspecialchars(trim((string) ($service['image_path'] ?? '')) !== '' ? (string) $service['image_path'] : 'src/image/Rectangle 55.png', ENT_QUOTES, 'UTF-8'); ?>') center/cover no-repeat;"></div>
          </div>

          <div class="col-lg-7">
            <div class="order-page-artist">
              <div class="order-page-avatar-wrapper">
                <img src="<?php echo htmlspecialchars(trim((string) ($artist['avatar'] ?? '')) !== '' ? (string) $artist['avatar'] : 'src/image/Ellipse 2.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?>" class="order-page-avatar">
              </div>
              <h2 class="order-page-artist-name"><?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <p class="order-page-artist-desc"><?php echo htmlspecialchars(trim((string) ($service['description'] ?? '')) !== '' ? (string) $service['description'] : 'Описание услуги...', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
        </div>

        <div class="order-page-details">
          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Название услуги</h3>
            <p class="order-page-detail-value"><?php echo htmlspecialchars((string) ($service['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Категория</h3>
            <p class="order-page-detail-value"><?php echo htmlspecialchars((string) ($service['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <h3 class="order-page-detail-label">Способ оплаты</h3>
          <div class="order-page-payment">
            <span class="order-page-payment-method">Банковская карта</span>
            <span class="order-page-payment-price">от <?php echo number_format((float) ($service['price'] ?? 0), 0, '.', ' '); ?>р</span>
          </div>

          <div class="order-page-buy-wrapper">
            <form method="post">
              <button class="order-page-buy-btn" type="submit" name="buy_service">Купить</button>
            </form>
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
            <?php
            $serviceImagePath = trim((string) ($service['image_path'] ?? ''));
            $serviceImagePath = $serviceImagePath !== '' ? $serviceImagePath : 'src/image/Rectangle 55.png';
          ?>
            <div class="order-page-image">
              <img src="<?php echo htmlspecialchars($serviceImagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="Изображение услуги" class="order-page-image-img">
            </div>
          </div>

          <div class="col-lg-7">
            <div class="order-page-artist">
              <?php if ((int) ($artist['user_id'] ?? 0) > 0): ?>
                <a href="profile-artist.php?user_id=<?php echo (int) $artist['user_id']; ?>" class="text-decoration-none text-reset d-inline-block">
              <?php endif; ?>
                  <div class="order-page-avatar-wrapper">
                    <img src="<?php echo htmlspecialchars(trim((string) ($artist['avatar_path'] ?? '')) !== '' ? (string) $artist['avatar_path'] : 'src/image/Ellipse 2.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?>" class="order-page-avatar">
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
