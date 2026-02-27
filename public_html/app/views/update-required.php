<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Required — Prologue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <style>
        body { background: #09090b; }
    </style>
</head>
<body class="min-h-screen text-gray-200 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-zinc-900 p-8 rounded-3xl border border-zinc-700 text-center">
        <div class="text-5xl mb-4">⚙️</div>
        <h1 class="text-2xl font-bold mb-2">Database Update Required</h1>
        <p class="text-zinc-400 mb-6">
            This installation has been updated to a new version. The database needs to be migrated before the app can be used.
        </p>
        <a href="<?= htmlspecialchars(base_url('/update'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block w-full bg-emerald-600 hover:bg-emerald-500 py-4 rounded-2xl font-semibold transition">
            Run Database Update
        </a>
    </div>
</body>
</html>
