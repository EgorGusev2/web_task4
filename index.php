<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Список допустимых языков
$allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];

// Обработка GET запроса (показ формы)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = array();
    $errors = array();
    $values = array();

    // Проверяем cookie успешного сохранения
    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', 100000);
        $messages[] = '<div class="success">Спасибо, результаты сохранены.</div>';
    }

    // Проверяем ошибки для каждого поля
    $errorFields = ['fio', 'phone', 'email', 'birthdate', 'gender', 'languages', 'bio', 'contract'];
    foreach ($errorFields as $field) {
        $errors[$field] = !empty($_COOKIE[$field . '_error']);
        if ($errors[$field]) {
            setcookie($field . '_error', '', 100000);
        }
    }

    // Сообщения об ошибках
    if ($errors['fio']) {
        $messages[] = '<div class="error">Ошибка в поле "ФИО": допустимы только буквы, пробелы и дефисы (не более 150 символов).</div>';
    }
    if ($errors['phone']) {
        $messages[] = '<div class="error">Ошибка в поле "Телефон": допустимы цифры, +, -, пробелы, скобки (10-20 символов).</div>';
    }
    if ($errors['email']) {
        $messages[] = '<div class="error">Ошибка в поле "Email": введите корректный email адрес.</div>';
    }
    if ($errors['birthdate']) {
        $messages[] = '<div class="error">Ошибка в поле "Дата рождения": используйте формат ГГГГ-ММ-ДД. Вы должны быть старше 18 лет.</div>';
    }
    if ($errors['gender']) {
        $messages[] = '<div class="error">Ошибка в поле "Пол": выберите мужской или женский.</div>';
    }
    if ($errors['languages']) {
        $messages[] = '<div class="error">Ошибка: выберите хотя бы один язык программирования.</div>';
    }
    if ($errors['bio']) {
        $messages[] = '<div class="error">Ошибка в поле "Биография": текст не должен превышать 5000 символов.</div>';
    }
    if ($errors['contract']) {
        $messages[] = '<div class="error">Ошибка: необходимо подтвердить ознакомление с контрактом.</div>';
    }

    // Получаем сохраненные значения из Cookies (на год)
    $values['fio'] = empty($_COOKIE['fio_value']) ? '' : $_COOKIE['fio_value'];
    $values['phone'] = empty($_COOKIE['phone_value']) ? '' : $_COOKIE['phone_value'];
    $values['email'] = empty($_COOKIE['email_value']) ? '' : $_COOKIE['email_value'];
    $values['birthdate'] = empty($_COOKIE['birthdate_value']) ? '' : $_COOKIE['birthdate_value'];
    $values['gender'] = empty($_COOKIE['gender_value']) ? '' : $_COOKIE['gender_value'];
    $values['bio'] = empty($_COOKIE['bio_value']) ? '' : $_COOKIE['bio_value'];
    $values['contract'] = !empty($_COOKIE['contract_value']);
    
    // Для языков (множественный выбор) - сохраняем как JSON
    $values['languages'] = empty($_COOKIE['languages_value']) ? array() : json_decode($_COOKIE['languages_value'], true);
    if (!is_array($values['languages'])) {
        $values['languages'] = array();
    }

    // Включаем форму
    include('form.html');
    exit();
}

// Обработка POST запроса (валидация)
else {
    $errors = false;

    // 1. Валидация ФИО
    if (empty($_POST['fio'])) {
        setcookie('fio_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $_POST['fio'])) {
        setcookie('fio_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (strlen($_POST['fio']) > 150) {
        setcookie('fio_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('fio_value', $_POST['fio'], time() + 365 * 24 * 60 * 60);

    // 2. Валидация Телефона
    if (empty($_POST['phone'])) {
        setcookie('phone_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (!preg_match('/^[\d\s\+\(\)\-]{10,20}$/', $_POST['phone'])) {
        setcookie('phone_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('phone_value', $_POST['phone'], time() + 365 * 24 * 60 * 60);

    // 3. Валидация Email
    if (empty($_POST['email'])) {
        setcookie('email_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        setcookie('email_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('email_value', $_POST['email'], time() + 365 * 24 * 60 * 60);

    // 4. Валидация Даты рождения
    if (empty($_POST['birthdate'])) {
        setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } else {
        $birthdate = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        if (!$birthdate) {
            setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
            $errors = true;
        } else {
            $age = $birthdate->diff(new DateTime())->y;
            if ($age < 18 || $age > 150) {
                setcookie('birthdate_error', '1', time() + 24 * 60 * 60);
                $errors = true;
            }
        }
    }
    setcookie('birthdate_value', $_POST['birthdate'], time() + 365 * 24 * 60 * 60);

    // 5. Валидация Пола
    if (empty($_POST['gender'])) {
        setcookie('gender_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (!in_array($_POST['gender'], ['male', 'female'])) {
        setcookie('gender_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('gender_value', $_POST['gender'], time() + 365 * 24 * 60 * 60);

    // 6. Валидация Языков программирования
    if (empty($_POST['languages'])) {
        setcookie('languages_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } else {
        foreach ($_POST['languages'] as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                setcookie('languages_error', '1', time() + 24 * 60 * 60);
                $errors = true;
                break;
            }
        }
    }
    $languages_json = json_encode($_POST['languages']);
    setcookie('languages_value', $languages_json, time() + 365 * 24 * 60 * 60);

    // 7. Валидация Биографии
    if (empty($_POST['bio'])) {
        setcookie('bio_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    } elseif (strlen($_POST['bio']) > 5000) {
        setcookie('bio_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('bio_value', $_POST['bio'], time() + 365 * 24 * 60 * 60);

    // 8. Валидация Контракта
    if (empty($_POST['contract'])) {
        setcookie('contract_error', '1', time() + 24 * 60 * 60);
        $errors = true;
    }
    setcookie('contract_value', $_POST['contract'], time() + 365 * 24 * 60 * 60);

    // Если есть ошибки - возвращаемся к форме
    if ($errors) {
        header('Location: index.php');
        exit();
    }

    // Если ошибок нет - удаляем все куки ошибок
    setcookie('fio_error', '', 100000);
    setcookie('phone_error', '', 100000);
    setcookie('email_error', '', 100000);
    setcookie('birthdate_error', '', 100000);
    setcookie('gender_error', '', 100000);
    setcookie('languages_error', '', 100000);
    setcookie('bio_error', '', 100000);
    setcookie('contract_error', '', 100000);

    // Сохраняем куку успешного сохранения
    setcookie('save', '1', time() + 24 * 60 * 60);

    // Перенаправляем на главную
    header('Location: index.php');
    exit();
}
?>