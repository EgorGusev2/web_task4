<?php
header('Content-Type: text/html; charset=UTF-8');

// Функции для работы с cookies
function getFormData($field) {
    return $_COOKIE["form_$field"] ?? '';
}

function setFormCookie($name, $value, $expire = 0) {
    setcookie("form_$name", $value, $expire, '/');
}

function setErrorCookie($name, $message) {
    setcookie("error_$name", $message, 0, '/');
}

// Обработка POST-запроса (отправка формы)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    $allowedLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'];

    // Валидация ФИО
    if (empty($_POST['fio'])) {
        $errors['fio'] = 'Заполните ФИО.';
        setErrorCookie('fio', $errors['fio']);
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s]+$/u', $_POST['fio'])) {
        $errors['fio'] = 'Допустимы только буквы и пробелы';
        setErrorCookie('fio', $errors['fio']);
    } elseif (strlen($_POST['fio']) > 150) {
        $errors['fio'] = 'Не более 150 символов';
        setErrorCookie('fio', $errors['fio']);
    }
    setFormCookie('fio', $_POST['fio']); // <-- Сохраняем всегда

    // Валидация телефона
    if (empty($_POST['phone'])) {
        $errors['phone'] = 'Заполните телефон.';
        setErrorCookie('phone', $errors['phone']);
    } elseif (!preg_match('/^\+?\d{10,15}$/', $_POST['phone'])) {
        $errors['phone'] = 'От 10 до 15 цифр, можно начинать с +';
        setErrorCookie('phone', $errors['phone']);
    }
    setFormCookie('phone', $_POST['phone']); // <-- Сохраняем всегда

    // Валидация email
    if (empty($_POST['email'])) {
        $errors['email'] = 'Заполните email.';
        setErrorCookie('email', $errors['email']);
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $_POST['email'])) {
        $errors['email'] = 'Некорректный email';
        setErrorCookie('email', $errors['email']);
    }
    setFormCookie('email', $_POST['email']); // <-- Сохраняем всегда

    // Валидация даты рождения
    if (empty($_POST['birthdate'])) {
        $errors['birthdate'] = 'Укажите дату рождения';
        setErrorCookie('birthdate', $errors['birthdate']);
    } else {
        $birthdate = DateTime::createFromFormat('Y-m-d', $_POST['birthdate']);
        $today = new DateTime();
        $minAge = new DateTime('-150 years');
        if (!$birthdate || $birthdate > $today || $birthdate < $minAge) {
            $errors['birthdate'] = 'Некорректная дата';
            setErrorCookie('birthdate', $errors['birthdate']);
        }
    }
    setFormCookie('birthdate', $_POST['birthdate']); // <-- Сохраняем всегда

    // Валидация пола
    if (empty($_POST['gender'])) {
        $errors['gender'] = 'Укажите пол';
        setErrorCookie('gender', $errors['gender']);
    } elseif (!in_array($_POST['gender'], ['male', 'female'])) {
        $errors['gender'] = 'Выберите из списка';
        setErrorCookie('gender', $errors['gender']);
    }
    setFormCookie('gender', $_POST['gender']); // <-- Сохраняем всегда

    // Валидация языков программирования
    if (empty($_POST['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык';
        setErrorCookie('languages', $errors['languages']);
    } else {
        foreach ($_POST['languages'] as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Недопустимый язык';
                setErrorCookie('languages', $errors['languages']);
                break;
            }
        }
        setFormCookie('languages', implode(',', $_POST['languages'])); // <-- Сохраняем всегда
    }

    // Валидация биографии
    if (empty($_POST['bio'])) {
        $errors['bio'] = 'Заполните биографию';
        setErrorCookie('bio', $errors['bio']);
    } elseif (strlen($_POST['bio']) > 5000) {
        $errors['bio'] = 'Не более 5000 символов';
        setErrorCookie('bio', $errors['bio']);
    }
    setFormCookie('bio', $_POST['bio']); // <-- Сохраняем всегда

    // Валидация чекбокса
    if (empty($_POST['contract'])) {
        $errors['contract'] = 'Необходимо согласие';
        setErrorCookie('contract', $errors['contract']);
    } else {
        setFormCookie('contract', '1'); // <-- Сохраняем всегда
    }

    // Если есть ошибки — перенаправляем обратно
    if (!empty($errors)) {
        header('Location: index.php');
        exit();
    }

    // Подключение к БД
    $user = 'u68775';
    $pass = '7631071';
    $dbname = 'u68775';
    try {
        $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $db->beginTransaction();

        // Сохранение основной информации
        $stmt = $db->prepare("INSERT INTO applications (fio, phone, email, birthdate, gender, bio, contract_agreed)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['fio'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            isset($_POST['contract']) ? 1 : 0
        ]);
        $applicationId = $db->lastInsertId();

        // Сохранение языков
        $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id)
                              SELECT ?, id FROM programming_languages WHERE name = ?");
        foreach ($_POST['languages'] as $lang) {
            $stmt->execute([$applicationId, $lang]);
        }

        $db->commit();

        // Очищаем cookies с данными формы и ошибками
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'form_') === 0 || strpos($name, 'error_') === 0) {
                setcookie($name, '', time() - 3600, '/');
            }
        }

        header('Location: index.php?success=1&id='.$applicationId);
        exit();
    } catch (PDOException $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        setErrorCookie('db', 'Ошибка сохранения: '.$e->getMessage());
        header('Location: index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма анкеты</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Анкета</h1>
    <?php if (isset($_GET['success'])): ?>
        <div class="success">Спасибо, результаты сохранены. ID: <?= htmlspecialchars($_GET['id']) ?></div>
    <?php endif; ?>
    <?php if (isset($_COOKIE['error_db'])): ?>
        <div class="error"><?= htmlspecialchars($_COOKIE['error_db']) ?></div>
    <?php endif; ?>
    <form action="index.php" method="POST">
        <!-- ФИО -->
        <div class="form-group">
            <label for="fio">ФИО:</label>
            <input type="text" id="fio" name="fio" value="<?= htmlspecialchars(getFormData('fio')) ?>"
                   class="<?= isset($_COOKIE['error_fio']) ? 'error-field' : '' ?>">
            <?php if (isset($_COOKIE['error_fio'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_fio']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Телефон -->
        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars(getFormData('phone')) ?>"
                   class="<?= isset($_COOKIE['error_phone']) ? 'error-field' : '' ?>">
            <?php if (isset($_COOKIE['error_phone'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_phone']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars(getFormData('email')) ?>"
                   class="<?= isset($_COOKIE['error_email']) ? 'error-field' : '' ?>">
            <?php if (isset($_COOKIE['error_email'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_email']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Дата рождения -->
        <div class="form-group">
            <label for="birthdate">Дата рождения:</label>
            <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars(getFormData('birthdate')) ?>"
                   class="<?= isset($_COOKIE['error_birthdate']) ? 'error-field' : '' ?>">
            <?php if (isset($_COOKIE['error_birthdate'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_birthdate']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Пол -->
        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <input type="radio" id="male" name="gender" value="male"
                       <?= getFormData('gender') == 'male' ? 'checked' : '' ?>
                       class="<?= isset($_COOKIE['error_gender']) ? 'error-field' : '' ?>">
                <label for="male">Мужской</label>
            </div>
            <div class="radio-group">
                <input type="radio" id="female" name="gender" value="female"
                       <?= getFormData('gender') == 'female' ? 'checked' : '' ?>
                       class="<?= isset($_COOKIE['error_gender']) ? 'error-field' : '' ?>">
                <label for="female">Женский</label>
            </div>
            <?php if (isset($_COOKIE['error_gender'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_gender']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Языки программирования -->
        <div class="form-group">
            <label for="languages">Любимый язык программирования:</label>
            <select id="languages" name="languages[]" multiple="multiple"
                    class="<?= isset($_COOKIE['error_languages']) ? 'error-field' : '' ?>">
                <?php
                $selectedLangs = explode(',', getFormData('languages'));
                $options = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'];
                foreach ($options as $lang): ?>
                    <option value="<?= $lang ?>"
                            <?= in_array($lang, $selectedLangs) ? 'selected' : '' ?>>
                        <?= $lang ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($_COOKIE['error_languages'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_languages']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Биография -->
        <div class="form-group">
            <label for="bio">Биография:</label>
            <textarea id="bio" name="bio"
                      class="<?= isset($_COOKIE['error_bio']) ? 'error-field' : '' ?>"><?= htmlspecialchars(getFormData('bio')) ?></textarea>
            <?php if (isset($_COOKIE['error_bio'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_bio']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Чекбокс -->
        <div class="form-group">
            <div class="checkbox-group">
                <input type="checkbox" id="contract" name="contract" value="1"
                       <?= getFormData('contract') ? 'checked' : '' ?>
                       class="<?= isset($_COOKIE['error_contract']) ? 'error-field' : '' ?>">
                <label for="contract">С контрактом ознакомлен</label>
            </div>
            <?php if (isset($_COOKIE['error_contract'])): ?>
                <div class="error"><?= htmlspecialchars($_COOKIE['error_contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</body>
</html>
