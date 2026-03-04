<?php
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

function getArtistCardColor(int $artistId): string
{
  $palette = [
    '#f5c2c7',
    '#ffd6a5',
    '#fdffb6',
    '#caffbf',
    '#9bf6ff',
    '#a0c4ff',
    '#bdb2ff',
    '#ffc6ff',
  ];

  return $palette[$artistId % count($palette)];
}

$homepageCategories = [
  '3D-моделирование и визуализация',
  'Графический дизайн',
  'Живопись и графика',
  'Иллюстрация',
  'Каллиграфия и леттеринг',
  'Скульптура и 3D-печать',
  'Цифровая живопись',
  'Прочее',
];

$homepageArtists = [
  [
    'id' => 1,
    'name' => 'Екатерина Кравчюк',
    'specialty' => 'Художник',
    'avatar_path' => 'src/image/Ellipse 2.png',
    'card_color' => getArtistCardColor(1),
  ],
  [
    'id' => 2,
    'name' => 'Марина Рафт',
    'specialty' => 'Художник',
    'avatar_path' => 'src/image/Ellipse 3.png',
    'card_color' => getArtistCardColor(2),
  ],
  [
    'id' => 3,
    'name' => 'Алиса Зайцева',
    'specialty' => 'Художник',
    'avatar_path' => 'src/image/Ellipse 4.png',
    'card_color' => getArtistCardColor(3),
  ],
];
try {
  $conn = new mysqli('MySQL-8.0', 'root', '');
  if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    if ($conn->select_db('artlance')) {

      $artistsSql = 'SELECT u.id, u.name, u.phone, u.avatar_path '
        . 'FROM users u '
        . 'WHERE u.role = "Художник" AND TRIM(COALESCE(u.name, "")) <> "" '
        . 'ORDER BY u.id DESC '
        . 'LIMIT 3';
      $artistsRes = $conn->query($artistsSql);
      if ($artistsRes !== false) {
        $loadedArtists = [];
        while ($artistRow = $artistsRes->fetch_assoc()) {
          $artistId = (int) ($artistRow['id'] ?? 0);
          $artistPhone = (string) ($artistRow['phone'] ?? '');
          $specialty = 'Художник';

          $categoryOrderSql = ' ORDER BY pc.category_id DESC';
          $checkPcId = $conn->query("SHOW COLUMNS FROM profile_categories LIKE 'id'");
          $checkPcCreatedAt = $conn->query("SHOW COLUMNS FROM profile_categories LIKE 'created_at'");
          if ($checkPcId !== false && $checkPcId->num_rows > 0) {
            $categoryOrderSql = ' ORDER BY pc.id DESC';
          } elseif ($checkPcCreatedAt !== false && $checkPcCreatedAt->num_rows > 0) {
            $categoryOrderSql = ' ORDER BY pc.created_at DESC';
          }

          $safeArtistPhone = $conn->real_escape_string($artistPhone);
          $categorySql = 'SELECT c.categories AS category_name '
            . 'FROM profile_categories pc '
            . 'LEFT JOIN categories c ON c.id = pc.category_id '
            . 'WHERE pc.profile_user_id = ' . $artistId . ' OR pc.user_phone = "' . $safeArtistPhone . '"'
            . $categoryOrderSql
            . ' LIMIT 1';
          $categoryRes = $conn->query($categorySql);
          if ($categoryRes !== false) {
            $categoryRow = $categoryRes->fetch_assoc();
            $categoryName = trim((string) ($categoryRow['category_name'] ?? ''));
            if ($categoryName !== '') {
              $specialty = $categoryName;
            }
          }

          $loadedArtists[] = [
            'id' => $artistId,
            'name' => (string) ($artistRow['name'] ?? 'Художник'),
            'specialty' => $specialty,
            'avatar_path' => normalizeImagePath((string) ($artistRow['avatar_path'] ?? ''), 'src/image/Ellipse 2.png'),
            'card_color' => getArtistCardColor($artistId),
          ];
        }

        if (count($loadedArtists) > 0) {
          $homepageArtists = $loadedArtists;
        }
      }
    }
  }
} catch (Throwable $e) {
  // fallback к дефолтным категориям выше
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ARTlance — фриланс-биржа для художников</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>
<body>

<?php include 'header.php'; ?>

  <section class="banner position-relative">
    <div class="container position-relative z-2">
      <div class="row align-items-center py-5">
        <div class="col-lg-6">
          <h1 class="banner-title">Найдите <span class="fw-black">ИДЕАЛЬНОГО</span> художника для вашего шедевра за 5 минут</h1>
          <a class="btn btn-custom mt-4" href="login.php">Найти</a>
        </div>
        <div class="col-lg-6 position-relative d-none d-lg-block">
          <div class="banner-spacer-desktop"></div>
        </div>
      </div>
      
      <div class="d-lg-none text-center pb-4">
        <div class="banner-spacer-mobile"></div>
      </div>
    </div>

    <div class="semicircle-container-desktop">
      <div class="semicircle-desktop"></div>
      <img src="src/image/iPad Mini.png" alt="Планшет" class="semicircle-image-desktop">
    </div>

    <div class="semicircle-container-mobile">
      <div class="semicircle-mobile"></div>
      <img src="src/image/iPad Mini.png" alt="Планшет" class="semicircle-image-mobile">
    </div>
  </section>

  <section class="gallery-section d-none d-lg-block">
    <div class="gallery-wrapper">
      <div class="gallery-track">
        <img src="src/image/Rectangle 39.png" alt="Работа 1" class="gallery-img">
        <img src="src/image/Rectangle 40.png" alt="Работа 2" class="gallery-img">
        <img src="src/image/Rectangle 41.png" alt="Работа 3" class="gallery-img">
        <img src="src/image/Rectangle 42.png" alt="Работа 4" class="gallery-img">
        <img src="src/image/Rectangle 43.png" alt="Работа 5" class="gallery-img">
        <img src="src/image/Rectangle 44.png" alt="Работа 6" class="gallery-img">
        <img src="src/image/Rectangle 45.png" alt="Работа 7" class="gallery-img">
        <img src="src/image/Rectangle 46.png" alt="Работа 8" class="gallery-img">
        <img src="src/image/Rectangle 39.png" alt="Работа 1" class="gallery-img">
        <img src="src/image/Rectangle 40.png" alt="Работа 2" class="gallery-img">
        <img src="src/image/Rectangle 41.png" alt="Работа 3" class="gallery-img">
        <img src="src/image/Rectangle 42.png" alt="Работа 4" class="gallery-img">
        <img src="src/image/Rectangle 43.png" alt="Работа 5" class="gallery-img">
        <img src="src/image/Rectangle 44.png" alt="Работа 6" class="gallery-img">
        <img src="src/image/Rectangle 45.png" alt="Работа 7" class="gallery-img">
        <img src="src/image/Rectangle 46.png" alt="Работа 8" class="gallery-img">
      </div>
    </div>
  </section>

  <!-- О проекте -->
  <section class="about py-5">
    <a name="about"></a>
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-6">
          <h2 class="about-title mb-4">О проекте ARTlance</h2>
          <p class="about-text">– это уникальная <span class="fw-black">фриланс-биржа</span>, созданная специально для представителей мира искусства. Мы соединяем талантливых художников, иллюстраторов и других творческих профессионалов с заказчиками, которые ищут оригинальные идеи и воплощения для своих проектов.</p>
          <div class="about-quote bg-white text-dark text-center p-4 rounded mt-4">
            "То место, куда идешь за реализацией творческих идей"
          </div>
        </div>
        <div class="col-lg-6">
          <img src="src/image/Rectangle 44.png" alt="Проект 1" class="img-fluid rounded about-img">
        </div>
        
        <div class="col-lg-6 d-none d-lg-block">
          <img src="src/image/Rectangle 46.png" alt="Проект 2" class="img-fluid rounded about-img">
        </div>
        <div class="col-lg-6 d-none d-lg-block">
          <img src="src/image/Rectangle 45.png" alt="Проект 3" class="img-fluid rounded about-img">
        </div>
      </div>
    </div>
  </section>

  <!-- Категории -->
  <section class="categori py-5">
    <div class="container py-5">
      <h2 class="about-title mb-4 text-start">Категории</h2>
      <div class="d-flex flex-wrap justify-content-center gap-2 categories-container">
        <?php foreach ($homepageCategories as $categoryName): ?>
          <span class="category-tag"><?php echo htmlspecialchars((string) $categoryName, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Контакты -->
  <section class="contacts py-5 position-relative">
      <a name="contact" id="contact"></a>
    <div class="container position-relative">
      <div class="row">
        <div class="col-lg-6">
          <h2 class="about-title mb-3">Обратная связь</h2>
          <p class="mb-4">Есть вопросы или не нашли то, что нужно?<br>Напишите нам!</p>
          <form class="contacts-form" method="post" action="https://formspree.io/f/xbdanzvr">
            <input type="text" name="name" placeholder="Имя" class="form-control mb-3" required maxlength="255">
            <input type="email" name="email" placeholder="Электронная почта" class="form-control mb-3" required maxlength="255">
            <textarea name="message" placeholder="Написать сообщение" class="form-control mb-3" required rows="4" maxlength="2000"></textarea>
            <button type="submit" class="btn btn-custom">Отправить</button>
          </form>
        </div>
      </div>
    </div>
    <img src="src/image/image 28.png" alt="Контакт" class="contact-img d-none d-lg-block">
  </section>

  <!-- Художники -->
  <section class="artists py-5">
    <div class="container">
      <h2 class="artists-title mb-4">Художники</h2>
      <div class="row g-4 mb-4">
        <?php foreach ($homepageArtists as $artist): ?>
          <div class="col-lg-4 col-md-6">
            <?php $artistCardHref = (int) ($artist['id'] ?? 0) > 0 ? 'profile-artist.php?user_id=' . (int) $artist['id'] : '#'; ?>
            <a href="<?php echo htmlspecialchars($artistCardHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">
              <div class="artist-card">
                <div class="artist-card-bg" style="background-color: <?php echo htmlspecialchars((string) ($artist['card_color'] ?? '#f5c2c7'), ENT_QUOTES, 'UTF-8'); ?>;"></div>
                <div class="artist-card-overlay">
                  <img src="<?php echo htmlspecialchars((string) ($artist['avatar_path'] ?? 'src/image/Ellipse 2.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?>" class="artist-avatar">
                  <div class="artist-details">
                    <h3 class="artist-name"><?php echo htmlspecialchars((string) ($artist['name'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="artist-specialty"><?php echo htmlspecialchars((string) ($artist['specialty'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center">
        <a class="btn btn-load-more" href="login.php">Смотреть еще</a>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
