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

  <!-- Основная секция заказа -->
  <section class="order-page-section">
    <div class="container">

      <!-- Белая карточка заказа -->
      <div class="order-page-card">

        <!-- Заголовок -->
        <div class="order-page-header">
          <h1 class="order-page-title">Оформление заказа</h1>
          <span class="order-page-published">Опубликовано 3 часа назад</span>
        </div>

        <!-- Верхняя часть: изображение + профиль -->
        <div class="row g-0">
          <!-- Изображение услуги -->
          <div class="col-lg-5">
            <div class="order-page-image"></div>
          </div>

          <!-- Профиль художника -->
          <div class="col-lg-7">
            <div class="order-page-artist">
              <!-- Аватар -->
              <div class="order-page-avatar-wrapper">
                <img src="src/image/Ellipse 2.png" alt="Екатерина Кравчюк" class="order-page-avatar">
              </div>

              <!-- Имя -->
              <h2 class="order-page-artist-name">Екатерина Кравчюк</h2>

              <!-- Теги навыков -->
              <div class="order-page-tags">
                <span class="order-page-tag">3D-моделирование и визуализация</span>
                <span class="order-page-tag">Графический дизайн</span>
                <span class="order-page-tag">Цифровая живопись</span>
              </div>

              <!-- Описание -->
              <p class="order-page-artist-desc">Описание пользователя...</p>

              <!-- Связаться через -->
              <div class="order-page-contacts">
                <span class="order-page-contacts-label">Связаться через</span>
                <div class="order-page-contacts-icons">
                  <a href="#" class="order-page-contact-icon">
                    <img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram">
                  </a>
                  <a href="#" class="order-page-contact-icon">
                    <img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp">
                  </a>
                  <a href="#" class="order-page-contact-icon">
                    <img src="src/image/icons/icons8-почта-100 1.svg" alt="Email">
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Детали услуги -->
        <div class="order-page-details">
          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Название услуги</h3>
            <p class="order-page-detail-value">Реальное название услуги...</p>
          </div>

          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Категория</h3>
            <p class="order-page-detail-value">3D-моделирование</p>
          </div>

          <div class="order-page-detail-item">
            <h3 class="order-page-detail-label">Подробное описание</h3>
            <p class="order-page-detail-value">Описывает услугу...</p>
          </div>

          <!-- Способ оплаты -->
          <h3 class="order-page-detail-label">Способ оплаты</h3>
          <div class="order-page-payment">
            <span class="order-page-payment-method">Банковская карта</span>
            <span class="order-page-payment-price">30 000р</span>
          </div>

          <!-- Кнопка Купить -->
          <div class="order-page-buy-wrapper">
            <button class="order-page-buy-btn">Купить</button>
          </div>
        </div>

      </div>

      <!-- Секция отзывов -->
      <div class="order-page-reviews">
        <div class="order-page-reviews-header">
          <h2 class="order-page-reviews-title">Отзывы</h2>
          <button class="order-page-leave-review-btn">Оставить отзыв</button>
        </div>

        <!-- Отзыв 1 -->
        <div class="order-page-review-card">
          <img src="src/image/Ellipse 2.png" alt="Ермакова Мария" class="order-page-review-avatar">
          <div class="order-page-review-content">
            <h4 class="order-page-review-name">Ермакова Мария</h4>
            <p class="order-page-review-text">Большое спасибо! Выполнено все быстро качественно. Буду обращаться еще.</p>
          </div>
        </div>

        <!-- Отзыв 2 -->
        <div class="order-page-review-card">
          <div class="order-page-review-avatar-placeholder"></div>
          <div class="order-page-review-content">
            <h4 class="order-page-review-name">Елько Александр</h4>
            <p class="order-page-review-text">Большое спасибо!</p>
          </div>
        </div>

        <!-- Отзыв 3 -->
        <div class="order-page-review-card">
          <img src="src/image/Ellipse 3.png" alt="Строгая Наталья" class="order-page-review-avatar">
          <div class="order-page-review-content">
            <h4 class="order-page-review-name">Строгая Наталья</h4>
            <p class="order-page-review-text">Выполнено все быстро качественно. Буду обращаться еще.</p>
          </div>
        </div>

        <!-- Отзыв 4 -->
        <div class="order-page-review-card">
          <img src="src/image/Ellipse 4.png" alt="Лисицин Ванечка" class="order-page-review-avatar">
          <div class="order-page-review-content">
            <h4 class="order-page-review-name">Лисицин Ванечка</h4>
            <p class="order-page-review-text">СУПЕР КЛАСС ЛАЙК РЕСПЕКТ. ОЧЕНЬ КРУТО СДЕЛАЛА И НЕ ДОРОГО. БЕРИТЕ НЕ ПОЖАЛЕЕТЕ!!!!!!!!!!!</p>
          </div>
        </div>
      </div>

    </div>
  </section>

  <div id="footer-placeholder"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
