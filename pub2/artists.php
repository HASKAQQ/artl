<?php
$artists = [];
$errorMessage = '';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$initialVisibleArtists = 9;

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

try {
    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $sql = 'SELECT u.id, u.name, u.phone, u.avatar_path, u.registered_at
            FROM users u
            WHERE u.role = "Художник"';
    $types = '';
    $params = [];

    if ($searchQuery !== '') {
        $sql .= ' AND (u.name LIKE ? OR u.phone LIKE ?)';
        $search = '%' . $searchQuery . '%';
        $types = 'ss';
        $params[] = $search;
        $params[] = $search;
    }

    $sql .= ' ORDER BY u.id DESC';

    $stmt = prepareOrFail($conn, $sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $artistsRes = $stmt->get_result();

    while ($artist = $artistsRes->fetch_assoc()) {
        $artistId = (int) ($artist['id'] ?? 0);
        $specialty = 'Художник';

        $categoryOrderSql = ' ORDER BY pc.category_id DESC';
        if (hasColumn($conn, 'profile_categories', 'id')) {
            $categoryOrderSql = ' ORDER BY pc.id DESC';
        } elseif (hasColumn($conn, 'profile_categories', 'created_at')) {
            $categoryOrderSql = ' ORDER BY pc.created_at DESC';
        }

        $categoryStmt = prepareOrFail(
            $conn,
            'SELECT c.categories AS category_name
             FROM profile_categories pc
             LEFT JOIN categories c ON c.id = pc.category_id
             WHERE pc.profile_user_id = ? OR pc.user_phone = ?' . $categoryOrderSql . '
             LIMIT 1'
        );
        $artistPhone = (string) ($artist['phone'] ?? '');
        $categoryStmt->bind_param('is', $artistId, $artistPhone);
        $categoryStmt->execute();
        $categoryRow = $categoryStmt->get_result()->fetch_assoc();
        $categoryName = trim((string) ($categoryRow['category_name'] ?? ''));
        if ($categoryName !== '') {
            $specialty = $categoryName;
        }

        $artists[] = [
            'id' => $artistId,
            'name' => (string) ($artist['name'] ?: 'Художник'),
            'avatar_path' => (string) ($artist['avatar_path'] ?? ''),
            'specialty' => $specialty,
            'card_color' => getArtistCardColor($artistId),
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
                    <form class="artists-search-wrapper" method="get">
                        <input type="text" name="q" class="form-control artists-search-input" placeholder="Поиск художников" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="artists-search-btn" type="submit">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
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
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="row g-4" id="artistsGrid">
                <?php if (count($artists) > 0): ?>
                    <?php foreach ($artists as $index => $artist): ?>
                        <div class="col-lg-4 col-md-6 artist-card-item <?php echo $index >= $initialVisibleArtists ? 'd-none' : ''; ?>">
                            <a href="profile-artist.php?user_id=<?php echo (int) $artist['id']; ?>" class="text-decoration-none text-reset d-block">
                                <div class="artist-card">
                                    <div class="artist-card-bg" style="background-color: <?php echo htmlspecialchars($artist['card_color'], ENT_QUOTES, 'UTF-8'); ?>;"></div>
                                    <div class="artist-card-overlay">
                                        <img src="<?php echo htmlspecialchars($artist['avatar_path'] !== '' ? $artist['avatar_path'] : 'src/image/Ellipse 2.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?>" class="artist-avatar">
                                        <div class="artist-details">
                                            <h3 class="artist-name"><?php echo htmlspecialchars($artist['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <p class="artist-specialty"><?php echo htmlspecialchars($artist['specialty'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Художники пока не найдены.</p>
                <?php endif; ?>
            </div>

            <?php if (count($artists) > $initialVisibleArtists): ?>
                <div class="text-center mt-5">
                    <button id="showMoreArtistsBtn" class="btn btn-load-more" type="button">Смотреть еще</button>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div id="footer-placeholder"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.getElementById('showMoreArtistsBtn');
            if (!button) {
                return;
            }

            var hiddenItems = Array.from(document.querySelectorAll('.artist-card-item.d-none'));
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
