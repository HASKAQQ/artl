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
        <span class="category-tag">Цифровая живопись</span>
        <span class="category-tag">Графический дизайн</span>
        <span class="category-tag">Иллюстрация</span>
        <span class="category-tag">Живопись и графика</span>
        <span class="category-tag">3D-моделирование и визуализация</span>
        <span class="category-tag">Скульптура и 3D-печать</span>
        <span class="category-tag">Каллиграфия и леттеринг</span>
        <span class="category-tag">Прочее</span>
      </div>
    </div>
  </section>

  <!-- Контакты -->
  <section class="contacts py-5 position-relative">
      <a name="contact"></a>
    <div class="container position-relative">
      <div class="row">
        <div class="col-lg-6">
          <h2 class="about-title mb-3">Обратная связь</h2>
          <p class="mb-4">Есть вопросы или не нашли то, что нужно?<br>Напишите нам!</p>
          <form class="contacts-form">
            <input type="text" placeholder="Имя" class="form-control mb-3">
            <input type="email" placeholder="Электронная почта" class="form-control mb-3">
            <input type="text" placeholder="Написать сообщение" class="form-control mb-3">
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
