<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/controls.php';

$isAjax = (
    (string)($_GET['ajax'] ?? '') === '1' ||
    (string)($_POST['ajax'] ?? '') === '1' ||
    (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$rpId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];
$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'edit');

if ($rpId <= 0) {
    $errors[] = 'Не указан ID записи.';
}

$fname = '';
$templatePath = '';
$fileContent = '';
$origName = '';

if (empty($errors)) {
    $stmt = $conn->prepare("SELECT m.fname, m.name FROM repmenu m WHERE m.rp_id = ?");
    $stmt->bind_param('i', $rpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $errors[] = 'Запись не найдена.';
    } else {
        $origName = (string)$row['name'];
        $fname = (string)$row['fname'];
        $templatePath = __DIR__ . '/' . ltrim($fname, '/');

        if ($fname === '') {
            $errors[] = 'Файл шаблона не указан.';
        } elseif (!file_exists($templatePath)) {
            $errors[] = 'Файл не найден: ' . h($templatePath);
        } else {
            $fileContent = file_get_contents($templatePath);
            if ($fileContent === false) {
                $errors[] = 'Ошибка чтения файла.';
            }
        }
    }
}

$templateStyles = '';
$templateBody = '';
if (!empty($fileContent)) {
    preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $fileContent, $styleMatches);
    if (!empty($styleMatches[1])) {
        $templateStyles = implode("\n", array_map('trim', $styleMatches[1]));
    }
    $templateBody = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $fileContent);
    $templateBody = trim($templateBody);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $cssContent = $_POST['css_styles'] ?? '';
    $bodyContent = $_POST['content'] ?? '';
    $newContent = '';
    if (trim($cssContent) !== '') {
        $newContent .= "<style>\n" . $cssContent . "\n</style>\n";
    }
    $newContent .= $bodyContent;
    $bytes = file_put_contents($templatePath, $newContent);
    if ($bytes === false) {
        $errors[] = 'Ошибка записи файла.';
    } else {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        $success = true;
        $fileContent = $newContent;
        $templateBody = $bodyContent;
        $templateStyles = $cssContent;
    }
}

$pageTitle = 'Редактор шаблона: ' . ($origName ?: '—');

ob_start();
?>
<h2 class="page-title"><img src="img/repmenu.png" alt="" /> <?= h($pageTitle) ?></h2>
<form class="form" method="post" action="repmenu_template_edit.php" autocomplete="off">
<input type="hidden" name="id" value="<?= (int)$rpId ?>" />
<input type="hidden" name="mode" value="edit" />

<?php foreach ($errors as $e): ?>
  <div class="flash flash--error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if (empty($errors) && !empty($success)): ?>
  <div class="flash flash--success">Файл сохранён.</div>
<?php endif; ?>

<?php if (empty($errors)): ?>
  <div style="margin-bottom:10px;font-size:12px;color:var(--muted);">Файл: <?= h($fname) ?></div>
  <textarea name="content" id="template-editor" style="width:100%;height:60vh;padding:8px;box-sizing:border-box;" wrap="off"><?= h($templateBody) ?></textarea>
  <details style="margin-top:8px;">
    <summary style="cursor:pointer;font-size:13px;font-weight:bold;color:var(--accent);">CSS стили</summary>
    <textarea name="css_styles" id="template-css" style="width:100%;height:200px;padding:8px;box-sizing:border-box;font-family:Consolas,monospace;font-size:13px;margin-top:4px;" wrap="off"><?= h($templateStyles) ?></textarea>
  </details>
  <div class="form-actions" style="margin-top:12px;">
    <button type="submit" class="btn btn-primary"><img src="img/save.png" alt="" /> Сохранить</button>
    <a class="btn btn-secondary" href="repmenu.php">Закрыть</a>
  </div>
<?php else: ?>
  <div class="form-actions">
    <a class="btn btn-secondary" href="repmenu.php">Назад</a>
  </div>
<?php endif; ?>

</form>
<?php
$formHtml = ob_get_clean();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => empty($errors), 'html' => $formHtml, 'mode' => $mode]);
    exit;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="app.css" />
  <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
  <script>
    tinymce.init({
      selector: '#template-editor',
      height: '60vh',
      menubar: true,
      plugins: 'code searchreplace visualblocks charmap link table image media help',
      toolbar: 'undo redo | styles bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | outdent indent | bullist numlist | table link image | code removeformat help',
      content_style: <?= json_encode($templateStyles . ' body { margin: 20px; }') ?>,
      promotion: false,
      branding: false,
      license_key: 'gpl'
    });
  </script>
</head>
<body>
  <div class="page page--form">
    <?= $formHtml ?>
  </div>
</body>
</html>
