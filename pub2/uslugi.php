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
        <button class="category-button active">Все</button>
        <button class="category-button">Графический дизайн</button>
        <button class="category-button">Иллюстрация</button>
        <button class="category-button">Живопись и графика</button>
        <button class="category-button">3D-моделирование и визуализация</button>
        <button class="category-button">Цифровая живопись</button>
        <button class="category-button">Скульптура и 3D-печать</button>
        <button class="category-button">Каллиграфия и леттеринг</button>
      </div>
    </div>
  </section>

  <section class="filters-section">
    <div class="container">
      <div class="filters-wrapper">
        <div class="dropdown">
          <button class="filter-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            По популярности
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">По популярности</a></li>
            <li><a class="dropdown-item" href="#">По новизне</a></li>
            <li><a class="dropdown-item" href="#">По цене (возр.)</a></li>
            <li><a class="dropdown-item" href="#">По цене (убыв.)</a></li>
          </ul>
        </div>
        
        <div class="dropdown">
          <button class="filter-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Цена, ₽
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">0 - 10 000</a></li>
            <li><a class="dropdown-item" href="#">10 000 - 30 000</a></li>
            <li><a class="dropdown-item" href="#">30 000 - 50 000</a></li>
            <li><a class="dropdown-item" href="#">50 000+</a></li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Сетка услуг -->
  <section class="services-grid-section">
    <div class="container">
      <div class="row g-4">
        <!-- Карточка 1 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 55.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 2 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 76.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 3 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 78.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 4 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 55.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 5 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 76.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 6 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 78.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 7 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 55.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 8 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 76.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Карточка 9 -->
        <div class="col-lg-4 col-md-6">
          <div class="service-card-item">
            <img src="src/image/Rectangle 78.png" alt="Услуга" class="service-card-image">
            <div class="service-card-overlay">
              <h3 class="service-card-title">Название услуги</h3>
              <p class="service-card-category">3D-моделирование</p>
              <div class="service-card-bottom">
                <p class="service-card-price">от 30 000₽</p>
                <p class="service-card-time">3 часа назад</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="load-more-section">
    <div class="container">
      <button class="load-more-btn">Смотреть еще</button>
    </div>
  </section>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
