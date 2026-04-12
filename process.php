<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Проверяем, что форма отправлена методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Валидация данных
$errors = [];
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];

// ФИО
if (empty($_POST['fio'])) {
    $errors['fio'] = 'Заполните ФИО.';
} elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $_POST['fio'])) {
    $errors['fio'] = 'ФИО должно содержать только буквы, пробелы и дефисы.';
} elseif (strlen($_POST['fio']) > 150) {
    $errors['fio'] = 'ФИО должно быть не длиннее 150 символов.';
}

// Телефон
if (empty($_POST['phone'])) {
    $errors['phone'] = 'Заполните телефон.';
} elseif (!preg_match('/^[\d\s\+\(\)\-]{10,20}$/', $_POST['phone'])) {
    $errors['phone'] = 'Телефон должен содержать от 10 до 20 символов (цифры, +, -, пробелы, скобки).';
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
    if ($birthdate && $birthdate->diff($today)->y < 18) {
        $errors['birthdate'] = 'Вы должны быть старше 18 лет.';
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

// Если есть ошибки - сохраняем в Cookies и возвращаемся
if (!empty($errors)) {
    foreach ($errors as $field => $error) {
        setcookie($field . '_error', '1', time() + 24 * 60 * 60);
    }
    // Сохраняем значения полей
    setcookie('fio_value', $_POST['fio'], time() + 365 * 24 * 60 * 60);
    setcookie('phone_value', $_POST['phone'], time() + 365 * 24 * 60 * 60);
    setcookie('email_value', $_POST['email'], time() + 365 * 24 * 60 * 60);
    setcookie('birthdate_value', $_POST['birthdate'], time() + 365 * 24 * 60 * 60);
    setcookie('gender_value', $_POST['gender'], time() + 365 * 24 * 60 * 60);
    setcookie('bio_value', $_POST['bio'], time() + 365 * 24 * 60 * 60);
    setcookie('contract_value', $_POST['contract'], time() + 365 * 24 * 60 * 60);
    $languages_json = json_encode($_POST['languages']);
    setcookie('languages_value', $languages_json, time() + 365 * 24 * 60 * 60);
    
    header('Location: index.php');
    exit();
}

// Если ошибок нет - сохраняем в базу данных
$user = 'u82361';
$pass = '9967838';
$dbname = 'u82361';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->beginTransaction();
    
    $stmt = $db->prepare("INSERT INTO application (full_name, phone, email, birth_date, gender, biography, agreed) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['fio'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['birthdate'],
        $_POST['gender'],
        $_POST['bio'],
        1
    ]);
    
    $applicationId = $db->lastInsertId();
    
    $stmt = $db->prepare("INSERT INTO application_language (application_id, language_id) 
                          VALUES (?, (SELECT id FROM programming_language WHERE name = ?))");
    foreach ($_POST['languages'] as $lang) {
        $stmt->execute([$applicationId, $lang]);
    }
    
    $db->commit();
    
    // Удаляем куки ошибок и значений
    setcookie('fio_error', '', 100000);
    setcookie('phone_error', '', 100000);
    setcookie('email_error', '', 100000);
    setcookie('birthdate_error', '', 100000);
    setcookie('gender_error', '', 100000);
    setcookie('languages_error', '', 100000);
    setcookie('bio_error', '', 100000);
    setcookie('contract_error', '', 100000);
    
    // Сохраняем куку успеха
    setcookie('save', '1', time() + 24 * 60 * 60);
    
    header('Location: index.php');
    exit();
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    setcookie('db_error', '1', time() + 24 * 60 * 60);
    header('Location: index.php');
    exit();
}
?>