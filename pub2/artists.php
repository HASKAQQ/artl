<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Художники — ARTlance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>
<body>

    <?php include 'header.php'; ?>

    <section class="artists-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 col-md-12 mb-3 mb-lg-0">
                    <div class="artists-search-wrapper">
                        <input type="text" class="form-control artists-search-input" placeholder="Поиск художников">
                        <button class="artists-search-btn">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="col-lg-5 col-md-12 text-lg-end">
                    <div class="dropdown">
                        <button class="filter-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            По популярности
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">По популярности</a></li>
                            <li><a class="dropdown-item" href="#">По новизне</a></li>
                            <li><a class="dropdown-item" href="#">По рейтингу</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="artists-section">
        <div class="container">
            <div class="row g-4">
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

                <div class="col-lg-4 col-md-6">
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

                <div class="col-lg-4 col-md-6">
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

            <div class="text-center mt-5">
                <button class="btn btn-load-more">Смотреть еще</button>
            </div>
        </div>
    </section>

    <div id="footer-placeholder"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
