<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>

<body>
  <div id="header-placeholder"></div>

  <!-- Профиль -->
  <section class="profile-section">
    <div class="container py-5">
      <!-- Профильная карточка -->
      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="src/image/Ellipse 2.png" alt="Avatar" class="profile-avatar" id="avatarImage">
          </div>
          <div class="profile-contacts">
            <a href=""><img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram"></a>
            <a href=""><img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp"></a>
            <a href=""><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email"></a>
          </div>

        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="d-flex align-items-center gap-3 mb-1">
            <h3 class="profile-name">Екатерина Кравчюк</h3>
            <div class="profile-role-toggle">
              <button class="role-btn active" data-role="artist">Художник <img
                  src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
            </div>
          </div>

          <p class="profile-registration">Дата регистрации</p>

          <div class="profile-tags">
            <p class="profile-tag">3D-моделирование и визуализация</p>
            <p class="profile-tag">Графический дизайн</p>
            <p class="profile-tag">Цифровая живопись</p>
          </div>

          <p class="profile-description-main">О себе...</p>
        </div>

      </div>

      <!-- Портфолио -->
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
            <div class="col-4 col-lg-3">
              <div class="portfolio-card editable">
                <img src="src/image/Rectangle 55.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card">
                <img src="src/image/Rectangle 76.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card">
                <img src="src/image/Rectangle 78.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card">
                <img src="src/image/Rectangle 76.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
            <div class="col-4 col-lg-3">
              <div class="portfolio-card">
                <img src="src/image/Rectangle 55.png" alt="Portfolio" class="portfolio-image">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Услуги -->
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
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100 editable" >
                <img src="src/image/Rectangle 55.png" alt="Service" class="service-image">
                <div class="service-info">
                  <h3 class="service-title">Название услуги</h3>
                  <p class="service-category">3D-моделирование</p>
                  <div class="service-bottom">
                    <p class="service-price">от 30 000р</p>
                    <p class="service-time">3 часа назад</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-6 col-lg-4">
              <div class="service-item card h-100" >
                <img src="src/image/Rectangle 76.png" alt="Service" class="service-image">
                <div class="service-info">
                  <h3 class="service-title">Название услуги</h3>
                  <p class="service-category">3D-моделирование</p>
                  <div class="service-bottom">
                    <p class="service-price">от 30 000р</p>
                    <p class="service-time">3 часа назад</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Отзывы -->
      <div class="section-collapsible" id="reviewsSection">
        <div class="section-header" onclick="toggleSection('reviews')">
          <h2>Отзывы</h2>
          <span class="toggle-arrow" id="reviewsArrow">▼</span>
        </div>
        <div class="section-content" id="reviewsContent">
          <div class="reviews-list">
            <div class="review-card">
              <img src="src/image/Ellipse 2.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Ермакова Мария</h4>
                <p class="review-text">Большое спасибо! Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Елько Александр</h4>
                <p class="review-text">Большое спасибо!</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 3.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Строгая Наталья</h4>
                <p class="review-text">Выполнено все быстро качественно. Буду обращаться еще.</p>
              </div>
            </div>

            <div class="review-card">
              <img src="src/image/Ellipse 4.png" alt="User" class="review-avatar">
              <div class="review-content">
                <h4 class="review-name">Лисицин Ванечка</h4>
                <p class="review-text">СУПЕР КЛАСС ЛАЙК РЕСПЕКТ. ОЧЕНЬ КРУТО СДЕЛАЛА И НЕ ДОРОГО. БЕРИТЕ НЕ
                  ПОЖАЛЕЕТЕ!!!!!!!!!!!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


    <div id="footer-placeholder"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>