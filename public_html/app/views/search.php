<div class="p-8 overflow-auto">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold">Search</h1>
        <a href="<?= htmlspecialchars(base_url('/'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-zinc-700 text-zinc-300 hover:bg-zinc-800 transition">
            <i class="fa fa-arrow-left text-xs"></i>
            Dashboard
        </a>
    </div>

    <section>
        <form id="user-search-form" class="flex items-center gap-3 w-full mb-4">
            <input type="text" id="user-search-input" placeholder="Search by username or user number" class="bg-zinc-900 border border-zinc-700 rounded-xl px-5 py-2.5 w-full" pattern="[A-Za-z0-9-]+" title="Use only letters, numbers, and dashes." required>
            <button type="submit" class="bg-zinc-700 hover:bg-zinc-600 border border-zinc-700 px-5 py-2.5 rounded-xl">Search</button>
        </form>

        <p id="user-search-help" class="text-sm text-zinc-400 mb-4">Press Enter/Return or click the Search button to find users.</p>

        <div id="user-search-results" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
    </section>
</div>
