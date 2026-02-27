<?php
$services = [];
$categories = [];
$errorMessage = '';
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$initialVisibleServices = 9;

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
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
    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $categoriesRes = $conn->query("SELECT DISTINCT TRIM(category) AS category_name FROM artist_services WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category_name ASC");
    if ($categoriesRes !== false) {
        while ($categoryRow = $categoriesRes->fetch_assoc()) {
            $categoryName = trim((string) ($categoryRow['category_name'] ?? ''));
            if ($categoryName !== '') {
                $categories[] = $categoryName;
            }
        }
    }

    $sql = 'SELECT s.id, s.title, s.category, s.price, s.created_at, s.image_path, u.id AS artist_id, u.name AS artist_name
            FROM artist_services s
            LEFT JOIN users u ON u.phone = s.user_phone';
    $types = '';
    $params = [];

    if ($selectedCategory !== '') {
        $sql .= ' WHERE s.category = ?';
        $types = 's';
        $params[] = $selectedCategory;
    }

    $sql .= ' ORDER BY s.id DESC';

    $stmt = prepareOrFail($conn, $sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $servicesRes = $stmt->get_result();

    while ($service = $servicesRes->fetch_assoc()) {
        $services[] = [
            'id' => (int) ($service['id'] ?? 0),
            'title' => trim((string) ($service['title'] ?? '')),
            'category' => trim((string) ($service['category'] ?? '')),
            'price' => (float) ($service['price'] ?? 0),
            'created_at' => (string) ($service['created_at'] ?? ''),
            'image_path' => trim((string) ($service['image_path'] ?? '')),
            'artist_id' => (int) ($service['artist_id'] ?? 0),
            'artist_name' => trim((string) ($service['artist_name'] ?? '')),
        ];
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
  <title>Все категории — ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <script src="js/main.js" defer></script>
</head>
<body>

<?php include 'header.php'; ?>

  <section class="services-banner">
    <div class="container">
      <h1 class="services-page-title">Все категории</h1>
      <div class="category-buttons">
        <a href="uslugi.php" class="category-button <?php echo $selectedCategory === '' ? 'active' : ''; ?>">Все</a>
        <?php foreach ($categories as $categoryName): ?>
          <a href="uslugi.php?category=<?php echo urlencode($categoryName); ?>" class="category-button <?php echo $selectedCategory === $categoryName ? 'active' : ''; ?>"><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="services-grid-section">
    <div class="container">
      <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="row g-4" id="servicesGrid">
        <?php if (count($services) > 0): ?>
          <?php foreach ($services as $index => $service): ?>
            <div class="col-lg-4 col-md-6 service-card-column <?php echo $index >= $initialVisibleServices ? 'd-none' : ''; ?>">
              <a href="<?php echo $service['artist_id'] > 0 ? 'profile-artist.php?user_id=' . (int) $service['artist_id'] : '#'; ?>" class="text-decoration-none">
                <div class="service-card-item">
                  <img src="<?php echo htmlspecialchars($service['image_path'] !== '' ? $service['image_path'] : 'src/image/Rectangle 55.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Услуга" class="service-card-image">
                  <div class="service-card-overlay">
                    <h3 class="service-card-title"><?php echo htmlspecialchars($service['title'] !== '' ? $service['title'] : 'Услуга художника', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="service-card-category"><?php echo htmlspecialchars($service['category'] !== '' ? $service['category'] : 'Без категории', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="service-card-bottom">
                      <p class="service-card-price">от <?php echo number_format((float) $service['price'], 0, '.', ' '); ?>₽</p>
                      <p class="service-card-time"><?php echo htmlspecialchars(formatTimeAgo($service['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Услуги пока не добавлены.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (count($services) > $initialVisibleServices): ?>
    <section class="load-more-section">
      <div class="container">
        <button id="showMoreServicesBtn" class="load-more-btn" type="button">Смотреть еще</button>
      </div>
    </section>
  <?php endif; ?>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var button = document.getElementById('showMoreServicesBtn');
      if (!button) {
        return;
      }

      var hiddenItems = Array.from(document.querySelectorAll('.service-card-column.d-none'));
      var step = 9;

      button.addEventListener('click', function () {
        hiddenItems.splice(0, step).forEach(function (item) {
          item.classList.remove('d-none');
        });

        if (hiddenItems.length === 0) {
          button.classList.add('d-none');
        }
      });
    });
  </script>
</body>
</html>
