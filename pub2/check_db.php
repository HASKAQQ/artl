<?php
// check_db.php
echo "<h3>Проверка настроек OpenServer MySQL</h3>";

// Пробуем разные варианты подключения
$configs = [
    ['MySQL-8.0', 'root', ''],
    ['localhost', 'root', ''],
    ['localhost:3306', 'root', ''],
    ['127.0.0.1:3306', 'root', ''],
];

foreach ($configs as $config) {
    list($host, $user, $pass) = $config;
    
    echo "<br><strong>Пробуем подключиться к: $host</strong><br>";
    
    $conn = @new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        echo "Ошибка: " . $conn->connect_error . "<br>";
        echo "Код ошибки: " . $conn->connect_errno . "<br>";
    } else {
        echo "✅ Успешно подключились к MySQL!<br>";
        echo "Версия MySQL: " . $conn->server_version . "<br>";
        echo "Хост: " . $conn->host_info . "<br>";
        $conn->close();
        break;
    }
}

// Проверяем порт 3306
echo "<br><strong>Проверка порта 3306:</strong><br>";
$fp = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 5);
if (!$fp) {
    echo "❌ Порт 3306 закрыт или MySQL не запущен: $errstr ($errno)<br>";
} else {
    echo "✅ Порт 3306 открыт<br>";
    fclose($fp);
}
?>