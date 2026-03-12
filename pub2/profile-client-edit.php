<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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


function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
}

function formatOrderTimeAgo(string $datetime): string
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

function getDbConnection(): mysqli
{
    $conn = new mysqli('MySQL-8.0', 'root', '');

    if ($conn->connect_error) {
        throw new RuntimeException('Не удалось подключиться к MySQL: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query('CREATE DATABASE IF NOT EXISTS artlance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    if (!$conn->select_db('artlance')) {
        throw new RuntimeException('Не удалось выбрать базу artlance: ' . $conn->error);
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(255) DEFAULT NULL,
            role VARCHAR(30) NOT NULL DEFAULT "Художник",
            avatar_path VARCHAR(255) DEFAULT NULL,
            registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $result = $conn->query('SHOW COLUMNS FROM users');
    $conn->query(
        'CREATE TABLE IF NOT EXISTS artist_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            artist_user_id INT UNSIGNED DEFAULT NULL,
            artist_phone VARCHAR(20) NOT NULL,
            buyer_phone VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "paid",
            service_title VARCHAR(255) NOT NULL,
            service_category VARCHAR(255) DEFAULT NULL,
            service_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            service_image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_artist_phone (artist_phone),
            INDEX idx_buyer_phone (buyer_phone),
            INDEX idx_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if ($result !== false) {
        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $existing[$field] = true;
            }
        }

        $columnsToAdd = [
            'social_email' => 'ALTER TABLE users ADD COLUMN social_email VARCHAR(255) DEFAULT NULL',
            'social_vk' => 'ALTER TABLE users ADD COLUMN social_vk VARCHAR(255) DEFAULT NULL',
            'about' => 'ALTER TABLE users ADD COLUMN about TEXT DEFAULT NULL',
        ];

        foreach ($columnsToAdd as $columnName => $sql) {
            if (!isset($existing[$columnName])) {
                $conn->query($sql);
            }
        }
    }

    return $conn;
}

$userPhone = (string) ($_SESSION['user_phone'] ?? '');
$userName = '';
$avatarPath = '';
$registeredAt = '';
$emailLink = '';
$vkLink = '';
$aboutText = '';
$saveMessage = '';
$errorMessage = '';
$clientOrders = [];

try {
    $conn = getDbConnection();

    if ($userPhone !== '' && isset($_GET['set_role'])) {
        $setRole = (string) $_GET['set_role'];
        if ($setRole === 'artist') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Художник" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-artist-edit.php');
            exit;
        }
        if ($setRole === 'client') {
            $stmt = prepareOrFail($conn, 'UPDATE users SET role = "Заказчик" WHERE phone = ?');
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            header('Location: profile-client-edit.php');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_social_link'])) {
        $socialType = (string) ($_POST['social_type'] ?? '');
        $socialLink = trim((string) ($_POST['social_link'] ?? ''));

        $columnMap = [
            'email' => 'social_email',
            'vk' => 'social_vk',
        ];

        if (!isset($columnMap[$socialType])) {
            $errorMessage = 'Неизвестный тип соцсети.';
        } elseif ($socialLink === '') {
            $errorMessage = 'Введите ссылку или почту.';
        } else {
            if ($socialType === 'email') {
                if (!filter_var($socialLink, FILTER_VALIDATE_EMAIL)) {
                    $errorMessage = 'Введите корректный email.';
                }
            } else {
                if (!preg_match('~^https?://~i', $socialLink)) {
                    $socialLink = 'https://' . $socialLink;
                }
                $isValidUrl = filter_var($socialLink, FILTER_VALIDATE_URL) !== false;
                $isVkLink = preg_match('~(^|\.)vk\.com$~i', (string) parse_url($socialLink, PHP_URL_HOST));
                if (!$isValidUrl || !$isVkLink) {
                    $errorMessage = 'Введите корректную ссылку на VK.';
                }
            }
        }

        if ($errorMessage === '') {
            $column = $columnMap[$socialType];
            $sql = "INSERT INTO users (phone, {$column}) VALUES (?, ?) ON DUPLICATE KEY UPDATE {$column} = VALUES({$column})";
            $stmt = prepareOrFail($conn, $sql);
            $stmt->bind_param('ss', $userPhone, $socialLink);
            $stmt->execute();
            $saveMessage = 'Ссылка сохранена.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_social_link'])) {
        $socialType = (string) ($_POST['social_type'] ?? '');
        $columnMap = [
            'email' => 'social_email',
            'vk' => 'social_vk',
        ];

        if (!isset($columnMap[$socialType])) {
            $errorMessage = 'Неизвестный тип соцсети.';
        } else {
            $column = $columnMap[$socialType];
            $sql = "UPDATE users SET {$column} = NULL WHERE phone = ?";
            $stmt = prepareOrFail($conn, $sql);
            $stmt->bind_param('s', $userPhone);
            $stmt->execute();
            $saveMessage = 'Ссылка удалена.';
        }
    }

    if ($userPhone !== '') {
        $stmt = prepareOrFail($conn, 'SELECT name, avatar_path, registered_at, social_email, social_vk, about FROM users WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $userPhone);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $userName = (string) ($existing['name'] ?? '');
            $avatarPath = (string) ($existing['avatar_path'] ?? '');
            $registeredAt = (string) ($existing['registered_at'] ?? '');
            $emailLink = (string) ($existing['social_email'] ?? '');
            $vkLink = (string) ($existing['social_vk'] ?? '');
            $aboutText = (string) ($existing['about'] ?? '');
        }
    }

    if ($userPhone !== '') {
        $ordersStmt = prepareOrFail(
            $conn,
            'SELECT ao.id, ao.service_title, ao.service_category, ao.service_price, ao.service_image_path, ao.status, ao.created_at,
                    COALESCE(NULLIF(TRIM(u.name), ""), ao.artist_phone) AS artist_name, ao.artist_phone, u.id AS artist_user_id
             FROM artist_orders ao
             LEFT JOIN users u ON u.phone = ao.artist_phone
             WHERE ao.buyer_phone = ?
             ORDER BY ao.created_at DESC, ao.id DESC'
        );
        $ordersStmt->bind_param('s', $userPhone);
        $ordersStmt->execute();
        $ordersRes = $ordersStmt->get_result();
        while ($orderRow = $ordersRes->fetch_assoc()) {
            $clientOrders[] = $orderRow;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
        $name = trim((string) ($_POST['profile_name'] ?? ''));
        $about = trim((string) ($_POST['profile_description'] ?? ''));
        if (mb_strlen($about, 'UTF-8') > 500) {
            $errorMessage = 'Поле «О себе» должно быть не длиннее 500 символов.';
        }

        if ($name === '') {
            $errorMessage = 'Вы обязаны ввести имя перед сохранением профиля.';
        } else {
            $avatarForDb = $avatarPath;

            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['avatar_file']['tmp_name'];
                $mime = mime_content_type($tmpName);
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (isset($allowed[$mime])) {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = 'avatar_' . preg_replace('/\D+/', '', $userPhone) . '_' . time() . '.' . $allowed[$mime];
                    $targetPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $avatarForDb = 'uploads/avatars/' . $fileName;
                    }
                } else {
                    $errorMessage = 'Можно загрузить только JPG, PNG или WEBP.';
                }
            }

            if ($errorMessage === '') {
                $upsert = prepareOrFail(
                    $conn,
                    'INSERT INTO users (phone, name, role, avatar_path, about) VALUES (?, ?, "Заказчик", ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), avatar_path = VALUES(avatar_path), about = VALUES(about), role = VALUES(role)'
                );
                $upsert->bind_param('ssss', $userPhone, $name, $avatarForDb, $about);
                $upsert->execute();

                $userName = $name;
                $avatarPath = $avatarForDb;
                $aboutText = $about;
                $saveMessage = 'Профиль сохранён.';
            }
        }
    }
} catch (Throwable $e) {
    $errorMessage = 'Ошибка при сохранении профиля: ' . $e->getMessage();
}

$registrationLabel = $registeredAt !== '' ? 'Дата регистрации: ' . date('d.m.Y H:i', strtotime($registeredAt)) : 'Дата регистрации: ещё не заполнена';
$avatarSrc = htmlspecialchars(normalizeImagePath($avatarPath, 'src/image/Ellipse 2.png'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - ARTlance</title>
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
      <?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <?php if ($saveMessage !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($saveMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

      <form method="post" enctype="multipart/form-data">
      <div class="profile-card bg-white row">
        <div class="col-4 col-lg-3 profile-col-wrapper">
          <div class="profile-avatar-wrapper position-relative">
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="profile-avatar" id="avatarImage">
            <div class="avatar-overlay position-absolute"><span class="avatar-overlay-text">Сменить<br>аватар</span></div>
            <input type="file" name="avatar_file" id="avatarFileInput" class="d-none" accept="image/*">
          </div>
          <div class="profile-contacts">
            <button
              type="button"
              class="contact-link-btn js-social-link-btn<?php echo trim((string) $vkLink) === '' ? ' is-empty' : ''; ?>"
              data-social-type="vk"
              data-social-link="<?php echo htmlspecialchars((string) $vkLink, ENT_QUOTES, 'UTF-8'); ?>"
            ><img src="src/image/icons/vk-icon.svg" alt="VK"></button>
            <button
              type="button"
              class="contact-link-btn js-social-link-btn<?php echo trim((string) $emailLink) === '' ? ' is-empty' : ''; ?>"
              data-social-type="email"
              data-social-link="<?php echo htmlspecialchars((string) $emailLink, ENT_QUOTES, 'UTF-8'); ?>"
            ><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email" class="contact-link-email-icon"></button>
          </div>
          <div class="profile-balance">
            <span class="balance-label">Баланс, руб</span>
            <div class="balance-amount">
              <img src="src/image/icons/icons8-карточка-в-использовании-100 (1) 1.svg" alt="Wallet">
              <span>0</span>
            </div>
            <div class="balance-buttons d-flex justify-content-between flex-wrap">
              <button class="btn-balance" type="button" id="balanceWithdrawBtn">Вывести</button>
              <button class="btn-balance" type="button" id="balanceTopUpBtn">Пополнить</button>
            </div>
          </div>
        </div>

        <div class="profile-info col-8 col-lg-9">
          <div class="d-flex align-items-center gap-3 mb-1">
            <input type="text" class="profile-name-input" value="<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>" id="profileName" name="profile_name" placeholder="Введите имя" required>
            <div class="profile-role-toggle">
              <button class="role-btn" type="button" data-switch-url="profile-artist-edit.php?set_role=artist">Художник <img src="src/image/icons/icons8-кисть-100 1.svg" alt=""></button>
              <button class="role-btn active" type="button" data-switch-url="profile-client-edit.php?set_role=client">Заказчик <img src="src/image/icons/icons8-заказ-100 1.svg" alt=""></button>
            </div>
          </div>

          <p class="profile-registration"><?php echo htmlspecialchars($registrationLabel, ENT_QUOTES, 'UTF-8'); ?></p>
          <textarea class="profile-description" name="profile_description" id="profileDescription" placeholder="О себе (до 500 символов)..." maxlength="500"><?php echo htmlspecialchars($aboutText, ENT_QUOTES, "UTF-8"); ?></textarea>
          <p class="text-muted small mb-2">Максимум 500 символов.</p>
          <button class="btn-save-profile" type="submit" name="save_profile">Сохранить</button>
        </div>
      </div>
      </form>

      <div class="section-collapsible" id="ordersSection">
        <div class="section-header" onclick="toggleSection('orders')">
          <h2>Мои заказы</h2>
          <span class="toggle-arrow" id="ordersArrow">▼</span>
        </div>
        <div class="section-content" id="ordersContent">
          <?php if (count($clientOrders) > 0): ?>
            <div class="row g-3">
              <?php foreach ($clientOrders as $order): ?>
                <div class="col-12 col-lg-6">
                  <div class="order-card">
                    <img src="<?php echo htmlspecialchars(trim((string) ($order['service_image_path'] ?? '')) !== '' ? (string) $order['service_image_path'] : 'src/image/Rectangle 55.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Service" class="order-image">
                    <div class="order-details">
                      <h3 class="order-title"><?php echo htmlspecialchars((string) ($order['service_title'] ?? 'Услуга художника'), ENT_QUOTES, 'UTF-8'); ?></h3>
                      <p class="order-category"><?php echo htmlspecialchars(trim((string) ($order['service_category'] ?? '')) !== '' ? (string) $order['service_category'] : 'Без категории', ENT_QUOTES, 'UTF-8'); ?></p>
                      <p class="order-category">Художник: <?php echo htmlspecialchars((string) ($order['artist_name'] ?? $order['artist_phone'] ?? 'Художник'), ENT_QUOTES, 'UTF-8'); ?></p>
                      <?php $artistUserId = (int) ($order['artist_user_id'] ?? 0); ?>
                      <?php if ($artistUserId > 0): ?>
                        <p class="order-category"><a href="profile-artist.php?user_id=<?php echo $artistUserId; ?>" class="text-decoration-none">Открыть профиль художника</a></p>
                      <?php endif; ?>

                      <div class="d-flex align-items-center justify-content-between gap-2">
                        <?php $currentStatus = (string) ($order['status'] ?? 'paid'); ?>
                        <?php $statusLabel = $currentStatus === 'in-progress' ? 'В работе' : ($currentStatus === 'completed' ? 'Завершено' : 'Оплачен'); ?>
                        <div class="order-status order-status-readonly"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                        <p class="order-price mb-0 text-end"><?php echo number_format((float) ($order['service_price'] ?? 0), 0, '.', ' '); ?>р</p>
                      </div>

                      <p class="order-time"><?php echo htmlspecialchars(formatOrderTimeAgo((string) ($order['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-light border">Пока нет оформленных заказов.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <div class="modal-overlay" id="socialLinkModal" onclick="closeModalOnOverlay(event, 'socialLinkModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeSocialLinkModal()"></button>
      <h3 class="modal-title" id="socialLinkTitle">Контакт</h3>
      <form method="post" class="service-modal-form">
        <input type="hidden" name="social_type" id="socialType" value="vk">
        <input type="text" class="modal-input" name="social_link" id="socialLinkInput" placeholder="Вставьте ссылку VK или почту" maxlength="255">
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit" name="save_social_link">Сохранить</button>
          <button class="btn-modal-delete" type="submit" name="delete_social_link">Удалить</button>
        </div>
      </form>
    </div>
  </div>
  <div class="dropdown-edit" id="socialLinkModalDropdown"></div>

  <div class="modal-overlay" id="balanceUnavailableModal" onclick="closeModalOnOverlay(event, 'balanceUnavailableModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeBalanceUnavailableModal()"></button>
      <h3 class="modal-title">Внимание</h3>
      <p class="balance-unavailable-message">Функция временно недоступна. Пожалуйста, попробуйте позже.</p>
    </div>
  </div>
  <div class="dropdown-edit" id="balanceUnavailableModalDropdown"></div>

  <?php include 'footer.php'; ?>
  <script>
    const avatarImageEl = document.getElementById('avatarImage');
    const avatarFileInputEl = document.getElementById('avatarFileInput');
    const avatarOverlayEl = document.querySelector('.avatar-overlay');
    const socialLinkModalEl = document.getElementById('socialLinkModal');
    const socialLinkTitleEl = document.getElementById('socialLinkTitle');
    const socialTypeEl = document.getElementById('socialType');
    const socialLinkInputEl = document.getElementById('socialLinkInput');
    const socialLinkButtonsEls = document.querySelectorAll('.js-social-link-btn');
    const balanceWithdrawBtnEl = document.getElementById('balanceWithdrawBtn');
    const balanceTopUpBtnEl = document.getElementById('balanceTopUpBtn');
    const balanceUnavailableModalEl = document.getElementById('balanceUnavailableModal');
    const balanceUnavailableModalDropdownEl = document.getElementById('balanceUnavailableModalDropdown');

    if (avatarImageEl && avatarFileInputEl) {
      avatarImageEl.addEventListener('click', () => avatarFileInputEl.click());
    }

    if (avatarOverlayEl && avatarFileInputEl) {
      avatarOverlayEl.addEventListener('click', () => avatarFileInputEl.click());
    }

    if (avatarFileInputEl && avatarImageEl) {
      avatarFileInputEl.addEventListener('change', function () {
        if (this.files && this.files[0]) {
          const reader = new FileReader();
          reader.onload = (e) => {
            if (typeof e.target?.result === 'string') {
              avatarImageEl.src = e.target.result;
            }
          };
          reader.readAsDataURL(this.files[0]);
        }
      });
    }

    function openSocialLinkModal(type, currentLink) {
      if (!socialLinkModalEl || !socialTypeEl || !socialLinkInputEl) return;
      socialTypeEl.value = String(type || 'vk');
      socialLinkInputEl.value = String(currentLink || '');

      if (socialTypeEl.value === 'vk') {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Ссылка на VK';
        socialLinkInputEl.placeholder = 'Вставьте ссылку на VK';
      } else {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Почта';
        socialLinkInputEl.placeholder = 'Введите email';
      }

      socialLinkModalEl.style.display = 'block';
      const socialLinkModalDropdownEl = document.getElementById('socialLinkModalDropdown');
      if (socialLinkModalDropdownEl) socialLinkModalDropdownEl.style.display = 'flex';
    }

    function closeSocialLinkModal() {
      if (socialLinkModalEl) socialLinkModalEl.style.display = 'none';
      const socialLinkModalDropdownEl = document.getElementById('socialLinkModalDropdown');
      if (socialLinkModalDropdownEl) socialLinkModalDropdownEl.style.display = 'none';
    }

    if (socialLinkButtonsEls && socialLinkButtonsEls.length > 0) {
      socialLinkButtonsEls.forEach((buttonEl) => {
        buttonEl.addEventListener('click', () => {
          const type = String(buttonEl.getAttribute('data-social-type') || '');
          const link = String(buttonEl.getAttribute('data-social-link') || '');
          openSocialLinkModal(type, link);
        });
      });
    }

    function openBalanceUnavailableModal() {
      if (!balanceUnavailableModalEl) return;
      balanceUnavailableModalEl.style.display = 'block';
      if (balanceUnavailableModalDropdownEl) balanceUnavailableModalDropdownEl.style.display = 'flex';
    }

    function closeBalanceUnavailableModal() {
      if (balanceUnavailableModalEl) balanceUnavailableModalEl.style.display = 'none';
      if (balanceUnavailableModalDropdownEl) balanceUnavailableModalDropdownEl.style.display = 'none';
    }

    if (balanceWithdrawBtnEl) {
      balanceWithdrawBtnEl.addEventListener('click', openBalanceUnavailableModal);
    }

    if (balanceTopUpBtnEl) {
      balanceTopUpBtnEl.addEventListener('click', openBalanceUnavailableModal);
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
