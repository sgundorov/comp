<?php
$file = __DIR__ . '/../client_form.php';
$t = file_get_contents($file);
$lines = explode("\n", $t);
$fixes = 0;

// Build a map of known field labels from other form files
$labels = [
    // From client schema: ФИО, название, телефон, email, etc.
    395 => 'Телефон',
    396 => 'E-mail',
    405 => 'Наименование',
    406 => 'Юр. адрес',
    407 => 'ИНН. КПП',
    416 => 'Вид деятельности',
    425 => 'Город',
    426 => 'Реквизиты',
    427 => 'Примечание',
    434 => 'Флаги',
    451 => 'Фамилия Имя Отчество',
    466 => 'Фамилия Имя Отчество',
    472 => 'Телефон',
    478 => 'Контактное лицо',
    482 => 'Название организации',
    494 => 'Добавить',
    495 => 'Изменить',
    496 => 'Удалить',
    513 => 'Товар',
    514 => 'Рег. код',
    515 => 'Кол-во',
    516 => 'Дата',
    517 => 'Город',
    518 => 'Подпись',
    519 => 'Дни',
    520 => 'Примечание',
    550 => 'Выберите вид деятельности',
    595 => 'Применить',
];

foreach ($lines as $i => &$line) {
    $ln = $i + 1;
    if (strpos($line, "\xEF\xBF\xBD") !== false && isset($labels[$ln])) {
        $line = str_replace("\xEF\xBF\xBD", '', $line);
        // Replace the label
        $line = preg_replace('/class="form-label">[^<]+</', 'class="form-label">' . $labels[$ln] . '<', $line);
        $line = preg_replace('/title="[^"]*"/', 'title="' . $labels[$ln] . '"', $line);
        $line = preg_replace('/search-cond-empty">[^<]+</', 'search-cond-empty">' . $labels[$ln] . '<', $line);
        $line = preg_replace('/textContent\s*=\s*\'[^\']*\'/', "textContent = '" . $labels[$ln] . "'", $line);
        $fixes++;
    }
}
file_put_contents($file, implode("\n", $lines));
echo "Fixed $fixes lines\n";
$check = file_get_contents($file);
echo "FFFD remaining: " . substr_count($check, "\xEF\xBF\xBD") . "\n";
