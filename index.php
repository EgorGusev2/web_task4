<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

$errors = [];
$formData = [];

// Разрешённые языки
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'];

// Функция для сохранения ошибок в cookies (до конца сессии)
function saveErrorsToCookies($errors) {
    setcookie('form_errors', json_encode($errors), 0, '/');
}

// Функция для сохранения успешных данных в cookies на 1 год
function saveSuccessDataToCookies($data) {
    setcookie('saved_form_data', json_encode($data), time() + 365 * 24 * 3600, '/');
}

// Функция загрузки сохранённых успешных данных
function loadSavedData() {
    if (isset($_COOKIE['saved_form_data'])) {
        return json_decode($_COOKIE['saved_form_data'], true);
    }
    return [];
}

// Если POST-запрос — валидируем
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // === ФИО ===
    if (empty($_POST['fio'])) {
        $errors['fio'] = 'Заполните ФИО. Допустимы буквы (русские/латинские), пробелы, дефис.';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $_POST['fio'])) {
        $errors['fio'] = 'ФИО должно содержать только буквы, пробелы и дефис.';
    } elseif (mb_strlen($_POST['fio']) > 150) {
        $errors['fio'] = 'ФИО должно быть не длиннее 150 символов.';
    }

    // === Телефон ===
    if (empty($_POST['phone'])) {
        $errors['phone'] = 'Заполните телефон. Допустимы цифры, знак + в начале, пробелы и дефисы.';
    } elseif (!preg_match('/^\+?[\d\s\-]{10,20}$/', $_POST['phone'])) {
        $errors['phone'] = 'Телефон должен содержать от 10 до 20 цифр, знаков, пробелов или дефисов.';
    }

    // === Email ===
    if (empty($_POST['email'])) {
        $errors['email'] = 'Заполните email.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email, например, name@domain.ru';
    }

    // === Дата рождения ===
    if (empty($_POST['birthdate'])) {
        $errors['birthdate'] = 'Заполните дату рождения.';
    } else {
        $birthdate = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        $today = new DateTime();
        $minAge = new DateTime('-150 years');
        if (!$birthdate || $birthdate > $today || $birthdate < $minAge) {
            $errors['birthdate'] = 'Введите корректную дату рождения (от 150 лет назад до сегодня).';
        }
    }

    // === Пол ===
    if (empty($_POST['gender'])) {
        $errors['gender'] = 'Укажите пол.';
    } elseif (!in_array($_POST['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выбран недопустимый пол.';
    }

    // === Языки ===
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

    // === Биография ===
    if (empty($_POST['bio'])) {
        $errors['bio'] = 'Заполните биографию. Допустимы буквы, цифры, пробелы, знаки препинания.';
    } elseif (strlen($_POST['bio']) > 5000) {
        $errors['bio'] = 'Биография должна быть не длиннее 5000 символов.';
    }

    // === Контракт ===
    if (empty($_POST['contract'])) {
        $errors['contract'] = 'Необходимо ознакомиться с контрактом.';
    }

    // Если есть ошибки — сохраняем в Cookies и редиректим GET
    if (!empty($errors)) {
        saveErrorsToCookies($errors);
        // Сохраняем введённые данные в сессию или GET-параметры
        $_SESSION['form_data'] = $formData;
        header('Location: index.html');
        exit();
    }

    // === Успешное сохранение в БД ===
    $user = 'u68775';
    $pass = '7631071';
    $dbname = 'u68775';

    try {
        $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $db->beginTransaction();

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

        $applicationId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO application_languages (application_id, language) VALUES (:app_id, :lang)");
        foreach ($_POST['languages'] as $lang) {
            $stmt->execute([':app_id' => $applicationId, ':lang' => $lang]);
        }

        $db->commit();

        // Сохраняем успешные данные в Cookies на год
        saveSuccessDataToCookies($_POST);

        // Удаляем временные ошибки и данные
        setcookie('form_errors', '', 1, '/');
        unset($_SESSION['form_data']);

        header('Location: form.php?save=1');
        exit();
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $errors['db'] = 'Ошибка базы данных: ' . $e->getMessage();
        saveErrorsToCookies($errors);
        $_SESSION['form_data'] = $formData;
        header('Location: index.html');
        exit();
    }
} else {
    // Если не POST — возможно, просто показываем форму (не должно сюда попадать)
    header('Location: index.html');
    exit();
}
?>
