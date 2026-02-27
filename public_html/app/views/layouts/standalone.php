<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Prologue', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <style>
        body { background: #09090b; }
    </style>
</head>
<body class="min-h-screen text-gray-200 flex items-center justify-center p-4">
<?= $content ?? '' ?>
</body>
</html>