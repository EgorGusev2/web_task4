
<?php
header('Content-Type: text/html; charset=UTF-8');

// Валидация данных
$errors = [];
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'];

// ФИО
if (empty($_POST['fio'])) {
    $errors['fio'] = 'Заполните ФИО.';
} elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s]+$/u', $_POST['fio'])) {
    $errors['fio'] = 'ФИО должно содержать только буквы и пробелы.';
} elseif (mb_strlen($_POST['fio']) > 150) {
    $errors['fio'] = 'ФИО должно быть не длиннее 150 символов.';
}

// Телефон
if (empty($_POST['phone'])) {
    $errors['phone'] = 'Заполните телефон.';
} elseif (!preg_match('/^\+?\d{10,15}$/', $_POST['phone'])) {
    $errors['phone'] = 'Телефон должен содержать от 10 до 15 цифр.';
}

// Email
if (empty($_POST['email'])) {
    $errors['email'] = 'Заполните email.';
} elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Введите корректный email.';
}

// Дата рождения
if (empty($_POST['birthdate'])) {
    $errors['birthdate'] = 'Заполните дату рождения.';
} else {
    $birthdate = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
    $today = new DateTime();
    $minAge = new DateTime('-150 years');
    
    if (!$birthdate || $birthdate > $today || $birthdate < $minAge) {
        $errors['birthdate'] = 'Введите корректную дату рождения.';
    }
}

// Пол
if (empty($_POST['gender'])) {
    $errors['gender'] = 'Укажите пол.';
} elseif (!in_array($_POST['gender'], ['male', 'female'])) {
    $errors['gender'] = 'Выбран недопустимый пол.';
}

// Языки программирования
if (empty($_POST['languages'])) {
    $errors['languages'] = 'Выберите хотя бы один язык программирования.';
} else {
    foreach ($_POST['languages'] as $lang) {
        if (!in_array($lang, $allowedLanguages)) {
            $errors['languages'] = 'Выбран недопустимый язык программирования.';
            break;
        }
    }
}

// Биография
if (empty($_POST['bio'])) {
    $errors['bio'] = 'Заполните биографию.';
} elseif (strlen($_POST['bio']) > 5000) {
    $errors['bio'] = 'Биография должна быть не длиннее 5000 символов.';
}

// Контракт
if (empty($_POST['contract'])) {
    $errors['contract'] = 'Необходимо ознакомиться с контрактом.';
}

if (!empty($errors)) {
    include('form.html');
    exit();
}

// Подключение к базе данных
$user = 'u68775'; // Заменить на ваш логин
$pass = '7631071'; // Заменить на ваш пароль
$dbname = 'u68775'; // Заменить на ваш логин (имя БД)

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Начало транзакции
    $db->beginTransaction();

    // Вставка основной информации
    $stmt = $db->prepare("INSERT INTO applications (fio, phone, email, birthdate, gender, bio, contract_agreed) 
                          VALUES (:fio, :phone, :email, :birthdate, :gender, :bio, :contract)");
    $stmt->execute([
        ':fio' => $_POST['fio'],
        ':phone' => $_POST['phone'],
        ':email' => $_POST['email'],
        ':birthdate' => $_POST['birthdate'],
        ':gender' => $_POST['gender'],
        ':bio' => $_POST['bio'],
        ':contract' => isset($_POST['contract']) ? 1 : 0
    ]);

    // Получаем ID последней вставленной записи
    $applicationId = $db->lastInsertId();

    // Вставка языков программирования
    $stmt = $db->prepare("INSERT INTO application_languages (application_id, language) VALUES (:app_id, :lang)");
    foreach ($_POST['languages'] as $lang) {
        $stmt->execute([
            ':app_id' => $applicationId,
            ':lang' => $lang
        ]);
    }

    // Завершение транзакции
    $db->commit();

    // Перенаправление с сообщением об успехе
    header('Location: form.php?save=1');
    exit();
} catch (PDOException $e) {
    // Откат транзакции в случае ошибки
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    print('Ошибка: ' . $e->getMessage());
    exit();
}
