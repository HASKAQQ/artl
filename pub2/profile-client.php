<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
}

$userId = (int) ($_GET['user_id'] ?? 0);
$errorMessage = '';
$client = [
    'name' => 'Заказчик',
    'avatar_path' => 'src/image/Ellipse 2.png',
    'about' => 'О себе...',
    'registered_at' => '',
];

try {
    if ($userId <= 0) {
        throw new RuntimeException('Профиль заказчика не найден.');
    }

    $conn = new mysqli('MySQL-8.0', 'root', '');
    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $stmt = prepareOrFail($conn, 'SELECT name, avatar_path, about, registered_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        throw new RuntimeException('Профиль заказчика не найден.');
    }

    $client['name'] = trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : 'Заказчик';
    $client['avatar_path'] = trim((string) ($row['avatar_path'] ?? '')) !== '' ? (string) $row['avatar_path'] : 'src/image/Ellipse 2.png';
    $client['about'] = trim((string) ($row['about'] ?? '')) !== '' ? (string) $row['about'] : 'О себе...';
    $client['registered_at'] = (string) ($row['registered_at'] ?? '');
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

$registrationLabel = trim((string) $client['registered_at']) !== ''
    ? 'Дата регистрации: ' . date('d.m.Y H:i', strtotime((string) $client['registered_at']))
    : 'Дата регистрации: не указана';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль заказчика - ARTlance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
</head>
<body>
  <?php include 'header.php'; ?>

  <section class="profile-section">
    <div class="container py-5">
      <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="<?php echo htmlspecialchars((string) $client['avatar_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="profile-avatar">
          </div>
        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="d-flex align-items-center gap-3 mb-1">
            <h3 class="profile-name"><?php echo htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="profile-role-toggle">
              <button class="role-btn" type="button" disabled>Художник <img src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn active" type="button" disabled>Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg" alt=""></button>
            </div>
          </div>

          <p class="profile-registration"><?php echo htmlspecialchars($registrationLabel, ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="profile-description-main"><?php echo htmlspecialchars((string) $client['about'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </div>
    </div>
  </section>

  <?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
