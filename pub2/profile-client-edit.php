<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

function prepareOrFail(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Ошибка SQL: ' . $conn->error);
    }

    return $stmt;
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
    if ($result !== false) {
        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $field = (string) ($row['Field'] ?? '');
            if ($field !== '') {
                $existing[$field] = true;
            }
        }

        $columnsToAdd = [
            'social_telegram' => 'ALTER TABLE users ADD COLUMN social_telegram VARCHAR(255) DEFAULT NULL',
            'social_whatsapp' => 'ALTER TABLE users ADD COLUMN social_whatsapp VARCHAR(255) DEFAULT NULL',
            'social_email' => 'ALTER TABLE users ADD COLUMN social_email VARCHAR(255) DEFAULT NULL',
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
$telegramLink = '';
$whatsappLink = '';
$emailLink = '';
$saveMessage = '';
$errorMessage = '';

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
            'telegram' => 'social_telegram',
            'whatsapp' => 'social_whatsapp',
            'email' => 'social_email',
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
                if (!filter_var($socialLink, FILTER_VALIDATE_URL)) {
                    $errorMessage = 'Введите корректную ссылку.';
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
            'telegram' => 'social_telegram',
            'whatsapp' => 'social_whatsapp',
            'email' => 'social_email',
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
        $stmt = prepareOrFail($conn, 'SELECT name, avatar_path, registered_at, social_telegram, social_whatsapp, social_email FROM users WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $userPhone);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $userName = (string) ($existing['name'] ?? '');
            $avatarPath = (string) ($existing['avatar_path'] ?? '');
            $registeredAt = (string) ($existing['registered_at'] ?? '');
            $telegramLink = (string) ($existing['social_telegram'] ?? '');
            $whatsappLink = (string) ($existing['social_whatsapp'] ?? '');
            $emailLink = (string) ($existing['social_email'] ?? '');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
        $name = trim((string) ($_POST['profile_name'] ?? ''));

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
                    'INSERT INTO users (phone, name, role, avatar_path) VALUES (?, ?, "Заказчик", ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), avatar_path = VALUES(avatar_path), role = VALUES(role)'
                );
                $upsert->bind_param('sss', $userPhone, $name, $avatarForDb);
                $upsert->execute();

                $userName = $name;
                $avatarPath = $avatarForDb;
                $saveMessage = 'Профиль сохранён.';
            }
        }
    }
} catch (Throwable $e) {
    $errorMessage = 'Ошибка при сохранении профиля: ' . $e->getMessage();
}

$registrationLabel = $registeredAt !== '' ? 'Дата регистрации: ' . date('d.m.Y H:i', strtotime($registeredAt)) : 'Дата регистрации: ещё не заполнена';
$avatarSrc = $avatarPath !== '' ? htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') : 'src/image/Ellipse 2.png';
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
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('telegram', <?php echo json_encode($telegramLink, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-телеграм-100 1.svg" alt="Telegram"></button>
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('whatsapp', <?php echo json_encode($whatsappLink, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-whatsapp-100 1.svg" alt="WhatsApp"></button>
            <button type="button" class="contact-link-btn" onclick="openSocialLinkModal('email', <?php echo json_encode($emailLink, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)"><img src="src/image/icons/icons8-почта-100 1.svg" alt="Email"></button>
          </div>
          <div class="profile-balance"><span class="balance-label">Баланс, руб</span></div>
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
          <textarea class="profile-description" placeholder="О себе..."></textarea>
          <button class="btn-save-profile" type="submit" name="save_profile">Сохранить</button>
        </div>
      </div>
      </form>
    </div>
  </section>

  <div class="modal-overlay" id="socialLinkModal" onclick="closeModalOnOverlay(event, 'socialLinkModal')">
    <div class="modal-content" style="position:relative;">
      <button type="button" class="btn-close" style="position:absolute; top:10px; right:10px;" onclick="closeSocialLinkModal()"></button>
      <h3 class="modal-title" id="socialLinkTitle">Ссылка на соцсеть</h3>
      <form method="post" class="service-modal-form">
        <input type="hidden" name="social_type" id="socialType" value="telegram">
        <input type="text" class="modal-input" name="social_link" id="socialLinkInput" placeholder="Вставьте ссылку или почту" maxlength="255">
        <div class="modal-buttons d-flex gap-2">
          <button class="btn-modal-save" type="submit" name="save_social_link">Сохранить</button>
          <button class="btn-modal-delete" type="submit" name="delete_social_link">Удалить</button>
        </div>
      </form>
    </div>
  </div>
  <div class="dropdown-edit" id="socialLinkModalDropdown"></div>

  <?php include 'footer.php'; ?>
  <script>
    const avatarImageEl = document.getElementById('avatarImage');
    const avatarFileInputEl = document.getElementById('avatarFileInput');
    const avatarOverlayEl = document.querySelector('.avatar-overlay');
    const socialLinkModalEl = document.getElementById('socialLinkModal');
    const socialLinkTitleEl = document.getElementById('socialLinkTitle');
    const socialTypeEl = document.getElementById('socialType');
    const socialLinkInputEl = document.getElementById('socialLinkInput');

    if (avatarImageEl && avatarFileInputEl) {
      avatarImageEl.addEventListener('click', () => avatarFileInputEl.click());
    }

    if (avatarOverlayEl && avatarFileInputEl) {
      avatarOverlayEl.addEventListener('click', () => avatarFileInputEl.click());
    }

    function openSocialLinkModal(type, currentLink) {
      if (!socialLinkModalEl || !socialTypeEl || !socialLinkInputEl) return;
      socialTypeEl.value = String(type || 'telegram');
      socialLinkInputEl.value = String(currentLink || '');

      if (socialTypeEl.value === 'telegram') {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Ссылка на Telegram';
        socialLinkInputEl.placeholder = 'Вставьте ссылку на Telegram';
      } else if (socialTypeEl.value === 'whatsapp') {
        if (socialLinkTitleEl) socialLinkTitleEl.textContent = 'Ссылка на WhatsApp';
        socialLinkInputEl.placeholder = 'Вставьте ссылку на WhatsApp';
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
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
