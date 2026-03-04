<?php
$feedbackSuccessMessage = '';
$feedbackErrorMessage = '';
$feedbackName = '';
$feedbackEmail = '';
$feedbackMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'send_feedback') {
  $feedbackName = trim((string) ($_POST['feedback_name'] ?? ''));
  $feedbackEmail = trim((string) ($_POST['feedback_email'] ?? ''));
  $feedbackMessage = trim((string) ($_POST['feedback_message'] ?? ''));

  if ($feedbackName === '' || $feedbackEmail === '' || $feedbackMessage === '') {
    $feedbackErrorMessage = 'Пожалуйста, заполните все поля формы.';
  } elseif (!filter_var($feedbackEmail, FILTER_VALIDATE_EMAIL)) {
    $feedbackErrorMessage = 'Введите корректный email.';
  } else {
    $mailTo = 'chudova0908@gmail.com';
    $mailSubject = 'Новая заявка с формы обратной связи ARTlance';
    $mailBody = "Имя: {$feedbackName}
"
      . "Email: {$feedbackEmail}

"
      . "Сообщение:
{$feedbackMessage}
";

    $headers = [
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'From: ARTlance <noreply@artlance.local>',
      'Reply-To: ' . $feedbackEmail,
      'X-Mailer: PHP/' . phpversion(),
    ];

    $encodedSubject = '=?UTF-8?B?' . base64_encode($mailSubject) . '?=';

    $mailSent = @mail($mailTo, $encodedSubject, $mailBody, implode("
", $headers));
    if ($mailSent) {
      $feedbackSuccessMessage = 'Спасибо! Ваш запрос отправлен, мы свяжемся с вами.';
      $feedbackName = '';
      $feedbackEmail = '';
      $feedbackMessage = '';
    } else {
      $feedbackErrorMessage = 'Не удалось отправить сообщение. Попробуйте позже.';
    }
  }
}

$homepageCategories = [
  'Цифровая живопись',
  'Графический дизайн',
  'Иллюстрация',
  'Живопись и графика',
  '3D-моделирование и визуализация',
  'Скульптура и 3D-печать',
  'Каллиграфия и леттеринг',
  'Все',
];

try {
  $conn = new mysqli('MySQL-8.0', 'root', '');
  if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    if ($conn->select_db('artlance')) {
      $sql = 'SELECT TRIM(categories) AS categories, MAX(is_default) AS is_default FROM categories WHERE TRIM(categories) <> "" GROUP BY TRIM(categories) ORDER BY MAX(is_default) DESC, TRIM(categories) ASC';
      $res = $conn->query($sql);
      if ($res !== false) {
        $loaded = [];
        while ($row = $res->fetch_assoc()) {
          $name = trim((string) ($row['categories'] ?? ''));
          if ($name === '' || mb_strtolower($name) === 'прочее') {
            continue;
          }
          $loaded[$name] = $name;
        }
        if (count($loaded) > 0) {
          $homepageCategories = array_values($loaded);
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
          <?php if ($feedbackSuccessMessage !== ''): ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($feedbackSuccessMessage, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if ($feedbackErrorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($feedbackErrorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <form class="contacts-form" method="post" action="#contact">
            <input type="hidden" name="action" value="send_feedback">
            <input type="text" name="feedback_name" placeholder="Имя" class="form-control mb-3" required maxlength="255" value="<?php echo htmlspecialchars($feedbackName, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="email" name="feedback_email" placeholder="Электронная почта" class="form-control mb-3" required maxlength="255" value="<?php echo htmlspecialchars($feedbackEmail, ENT_QUOTES, 'UTF-8'); ?>">
            <textarea name="feedback_message" placeholder="Написать сообщение" class="form-control mb-3" required rows="4" maxlength="2000"><?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
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
        <div class="col-lg-4 col-md-6">
          <div class="artist-card">
            <img src="src/image/Rectangle 55.png" alt="Работа художника" class="artist-card-bg">
            <div class="artist-card-overlay">
              <img src="src/image/Ellipse 2.png" alt="Екатерина Кравчюк" class="artist-avatar">
              <div class="artist-details">
                <h3 class="artist-name">Екатерина Кравчюк</h3>
                <p class="artist-specialty">3D-моделирование</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="artist-card">
            <img src="src/image/Rectangle 76.png" alt="Работа художника" class="artist-card-bg">
            <div class="artist-card-overlay">
              <img src="src/image/Ellipse 3.png" alt="Марина Рафт" class="artist-avatar">
              <div class="artist-details">
                <h3 class="artist-name">Марина Рафт</h3>
                <p class="artist-specialty">Иллюстрация</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 d-none d-lg-block">
          <div class="artist-card">
            <img src="src/image/Rectangle 78.png" alt="Работа художника" class="artist-card-bg">
            <div class="artist-card-overlay">
              <img src="src/image/Ellipse 4.png" alt="Алиса Зайцева" class="artist-avatar">
              <div class="artist-details">
                <h3 class="artist-name">Алиса Зайцева</h3>
                <p class="artist-specialty">Цифровая живопись</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center">
        <a class="btn btn-load-more" href="uslugi.php">Смотреть еще</a>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
