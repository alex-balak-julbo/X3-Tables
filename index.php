<?php
if (isset($_GET['json'])) {
  header('Content-Type: application/json; charset=utf-8');

  $jsonName = $_GET['json'];
  if (!is_string($jsonName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom de fichier JSON invalide.']);
    exit;
  }

  if ($jsonName === 'search-index') {
    $jsonPath = __DIR__ . '/x3_search_index.json';
  } elseif (preg_match('/\A[A-Za-z0-9_-]+\z/', $jsonName)) {
    $jsonPath = __DIR__ . '/x3_json/' . $jsonName . '.json';
  } else {
    http_response_code(400);
    echo json_encode(['error' => 'Nom de fichier JSON invalide.']);
    exit;
  }

  if (!is_file($jsonPath) || !is_readable($jsonPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Fichier JSON introuvable.']);
    exit;
  }

  $json = file_get_contents($jsonPath);
  if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de lire le fichier JSON.']);
    exit;
  }

  echo $json;
  exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8fafc">
  <title>Explorateur des tables Sage X3</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    button, input, select { font: inherit; }
    .result-card { transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .result-card:hover { border-color: #93c5fd; box-shadow: 0 8px 24px rgb(15 23 42 / 7%); transform: translateY(-1px); }
    .modal-backdrop { background: rgb(15 23 42 / 48%); }
    .scrollbar-thin { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    @media (max-width: 640px) {
      .mobile-stack { align-items: stretch; flex-direction: column; }
      .mobile-stack > * { width: 100%; }
    }
  </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
      <a href="./" class="flex items-center gap-3 text-inherit no-underline">
        <span class="grid h-11 w-11 place-items-center rounded-xl bg-blue-600 text-lg font-bold text-white">X3</span>
        <span>
          <span class="block text-lg font-semibold tracking-tight">Explorateur Sage X3</span>
          <span class="block text-sm text-slate-500">Tables, champs et types</span>
        </span>
      </a>
      <span id="index-status" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600" role="status">Chargement de l’index…</span>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
    <section class="mx-auto max-w-4xl text-center">
      <p class="mb-3 text-sm font-semibold uppercase tracking-[.16em] text-blue-700">Documentation locale</p>
      <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Retrouvez une table ou un champ</h1>
      <p class="mt-3 text-slate-600">Choisissez de rechercher une table, un champ ou un intitulé.</p>
    </section>

    <section class="mx-auto mt-8 max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-label="Recherche">
      <div class="flex gap-3 mobile-stack">
        <label class="relative min-w-0 flex-1">
          <span class="sr-only">Saisir le terme à rechercher</span>
          <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
          <input id="search-input" type="search" autocomplete="off" placeholder="Saisissez votre recherche…" class="w-full rounded-xl border border-slate-300 bg-white py-3.5 pl-12 pr-4 text-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100" disabled>
        </label>
        <label class="sm:w-56">
          <span class="sr-only">Choisir où chercher</span>
          <select id="search-mode" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3.5 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" disabled>
            <option value="">Rechercher dans…</option>
            <option value="table">Table</option>
            <option value="field">Champ</option>
            <option value="label">Intitulé</option>
          </select>
        </label>
        <button id="clear-button" type="button" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" disabled>Effacer</button>
      </div>
      <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <span>La recherche est effectuée dans votre navigateur.</span>
        <span id="result-count" aria-live="polite"></span>
      </div>
    </section>

    <section class="mx-auto mt-7 max-w-5xl" aria-label="Résultats">
      <div id="results" class="grid gap-3"></div>
      <div id="empty-state" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-700">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
        </div>
        <h2 id="empty-title" class="mt-4 font-semibold text-slate-900">L’index se prépare</h2>
        <p id="empty-message" class="mt-1 text-sm text-slate-500">Les données seront disponibles dès la fin du chargement.</p>
      </div>
    </section>
  </main>

  <div id="detail-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="detail-title">
    <section class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-7">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Détail de la table</p>
          <h2 id="detail-title" class="mt-1 truncate text-xl font-bold"></h2>
          <a id="detail-doc-link" class="mt-1 hidden text-sm text-blue-700 hover:underline" target="_blank" rel="noopener noreferrer">Ouvrir la documentation Sage</a>
        </div>
        <button id="close-modal" type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Fermer">✕</button>
      </header>
      <div id="detail-content" class="scrollbar-thin overflow-y-auto px-5 py-5 sm:px-7"></div>
    </section>
  </div>

  <script>
    (() => {
      const indexStatus = document.getElementById("index-status");
      const searchInput = document.getElementById("search-input");
      const searchMode = document.getElementById("search-mode");
      const clearButton = document.getElementById("clear-button");
      const results = document.getElementById("results");
      const resultCount = document.getElementById("result-count");
      const emptyState = document.getElementById("empty-state");
      const emptyTitle = document.getElementById("empty-title");
      const emptyMessage = document.getElementById("empty-message");
      const modal = document.getElementById("detail-modal");
      const detailTitle = document.getElementById("detail-title");
      const detailLink = document.getElementById("detail-doc-link");
      const detailContent = document.getElementById("detail-content");
      const tableCache = new Map();
      let fields = [];
      let tables = new Map();
      let searchTimer;

      const normalize = value => String(value || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("fr");
      const escapeHtml = value => String(value == null ? "" : value).replace(/[&<>"']/g, char => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
      })[char]);

      function setEmpty(title, message) {
        emptyTitle.textContent = title;
        emptyMessage.textContent = message;
        emptyState.classList.remove("hidden");
      }

      function updateResults() {
        const query = normalize(searchInput.value.trim());
        const mode = searchMode.value;
        if (!query || !mode) {
          results.replaceChildren();
          resultCount.textContent = "";
          if (query && !mode) {
            setEmpty("Choisissez un critère", "Sélectionnez Table, Champ ou Intitulé pour lancer la recherche.");
          } else {
            setEmpty("À vous de chercher", "Saisissez un terme et choisissez Table, Champ ou Intitulé.");
          }
          return;
        }

        const matchingFields = [];
        const tableMatches = [];
        const maxFields = 120;
        if (mode === "table") {
          for (const [tableName, tableFields] of tables) {
            if (normalize(tableName).includes(query)) {
              tableMatches.push({ table: tableName, count: tableFields.length });
            }
          }
        } else {
          for (const field of fields) {
            const value = normalize(mode === "field" ? field.name : field.label);
            if (value.includes(query) && matchingFields.length < maxFields) {
              matchingFields.push(field);
            }
          }
        }

        const tableCards = tableMatches.slice(0, 30).map(item => `
          <button type="button" data-open-table="${escapeHtml(item.table)}" class="result-card flex w-full items-center justify-between gap-4 rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-left">
            <span class="min-w-0">
              <span class="block font-semibold text-slate-900">${escapeHtml(item.table)}</span>
              <span class="mt-1 block text-sm text-slate-600">Table · ${item.count} champ${item.count > 1 ? "s" : ""}</span>
            </span>
            <span class="shrink-0 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-blue-700 shadow-sm">Voir la table →</span>
          </button>`).join("");
        const fieldCards = matchingFields.map(field => `
          <article class="result-card rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <h3 class="font-semibold text-slate-900">${escapeHtml(field.name)}</h3>
                <p class="mt-1 text-sm text-slate-600">${escapeHtml(field.label || "—")}</p>
              </div>
              <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">${escapeHtml(field.type || "Type inconnu")}${field.length ? ` · ${escapeHtml(field.length)}` : ""}</span>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
              <span class="text-xs text-slate-500">Table <span class="font-semibold text-slate-700">${escapeHtml(field.table)}</span></span>
              <button type="button" data-open-table="${escapeHtml(field.table)}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Détail de la table →</button>
            </div>
          </article>`).join("");

        results.innerHTML = tableCards + fieldCards;
        const totalShown = tableMatches.length + matchingFields.length;
        const hasMoreTables = tableMatches.length > 30;
        const hasMoreFields = matchingFields.length === maxFields;
        resultCount.textContent = `${totalShown}${hasMoreTables || hasMoreFields ? "+" : ""} résultat${totalShown > 1 ? "s" : ""}`;
        if (!totalShown) {
          setEmpty("Aucun résultat", "Essayez un autre terme ou choisissez un autre critère de recherche.");
          return;
        }
        emptyState.classList.add("hidden");
        if (hasMoreTables || hasMoreFields) {
          resultCount.textContent += " · résultats limités, précisez votre recherche";
        }
      }

      function renderTable(data) {
        const keys = Array.isArray(data.keys) ? data.keys : [];
        const tableFields = Array.isArray(data.fields) ? data.fields : [];
        const tableNames = new Set(tables.keys());
        const keyMarkup = keys.length ? keys.map(key => `
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-semibold text-slate-900">${escapeHtml(key.key)}</span>
              <span class="text-sm text-slate-600">${escapeHtml(key.description || "—")}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">${key.duplicates ? "Doublons autorisés" : "Clé sans doublons"}${key.activity ? ` · Activité ${escapeHtml(key.activity)}` : ""}</p>
          </div>`).join("") : '<p class="text-sm text-slate-500">Aucune clé renseignée.</p>';
        const fieldMarkup = tableFields.map(field => {
          const references = new Set();
          const relationText = `${field.link || ""} ${field.link_expr || ""}`;
          for (const match of relationText.matchAll(/\[([A-Z0-9_]+)\]/g)) {
            if (tableNames.has(match[1]) && match[1] !== data.table) references.add(match[1]);
          }
          const relationButtons = [...references].map(table => `<button type="button" data-open-table="${escapeHtml(table)}" class="ml-1 inline-flex rounded bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-700 hover:bg-blue-100">${escapeHtml(table)}</button>`).join("");
          return `<tr class="border-b border-slate-100 last:border-0">
            <td class="whitespace-nowrap px-3 py-3 font-medium text-slate-900">${escapeHtml(field.name)}</td>
            <td class="min-w-48 px-3 py-3 text-slate-600">${escapeHtml(field.label || "—")}</td>
            <td class="whitespace-nowrap px-3 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">${escapeHtml(field.type || "—")}</span></td>
            <td class="whitespace-nowrap px-3 py-3 text-slate-500">${escapeHtml(field.length || "—")}</td>
            <td class="min-w-32 px-3 py-3 text-xs">${relationButtons || '<span class="text-slate-400">—</span>'}</td>
          </tr>`;
        }).join("");
        detailContent.innerHTML = `
          <div class="grid gap-6">
            <section>
              <h3 class="mb-3 text-sm font-semibold text-slate-900">Clés (${keys.length})</h3>
              <div class="grid gap-2 sm:grid-cols-2">${keyMarkup}</div>
            </section>
            <section>
              <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Champs (${tableFields.length})</h3>
                <input id="detail-field-search" type="search" placeholder="Filtrer les champs…" class="w-52 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
              </div>
              <div class="scrollbar-thin overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[680px] text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Champ</th><th class="px-3 py-3">Intitulé</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Longueur</th><th class="px-3 py-3">Tables liées</th></tr></thead>
                  <tbody id="detail-fields-body">${fieldMarkup || '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Aucun champ.</td></tr>'}</tbody>
                </table>
              </div>
              <p id="detail-load-error" class="mt-3 hidden text-sm text-red-700"></p>
            </section>
          </div>`;
        const fieldSearch = document.getElementById("detail-field-search");
        fieldSearch.addEventListener("input", () => {
          const needle = normalize(fieldSearch.value);
          for (const row of document.querySelectorAll("#detail-fields-body tr")) {
            row.classList.toggle("hidden", needle && !normalize(row.textContent).includes(needle));
          }
        });
      }

      async function openTable(table) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
        detailTitle.textContent = table;
        detailLink.classList.add("hidden");
        detailContent.innerHTML = '<p class="py-12 text-center text-sm text-slate-500">Chargement du détail…</p>';
        try {
          let data = tableCache.get(table);
          if (!data) {
            const response = await fetch(`index.php?json=${encodeURIComponent(table)}`);
            if (!response.ok) throw new Error(`Le fichier x3_json/${table}.json est introuvable (HTTP ${response.status}).`);
            data = await response.json();
            if (!data || !Array.isArray(data.fields)) throw new Error("Le fichier de cette table n’a pas le format attendu.");
            tableCache.set(table, data);
          }
          renderTable(data);
          if (typeof data.url === "string" && /^https:\/\/\S+$/i.test(data.url)) {
            detailLink.href = data.url;
            detailLink.classList.remove("hidden");
          }
        } catch (error) {
          detailContent.innerHTML = `<p class="rounded-lg bg-red-50 p-4 text-sm text-red-800">${escapeHtml(error.message || "Impossible de charger le détail de cette table.")}</p>`;
        }
      }

      function closeModal() {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
      }

      results.addEventListener("click", event => {
        const button = event.target.closest("[data-open-table]");
        if (button) openTable(button.dataset.openTable);
      });
      detailContent.addEventListener("click", event => {
        const button = event.target.closest("[data-open-table]");
        if (button) openTable(button.dataset.openTable);
      });
      document.getElementById("close-modal").addEventListener("click", closeModal);
      modal.addEventListener("click", event => { if (event.target === modal) closeModal(); });
      document.addEventListener("keydown", event => { if (event.key === "Escape") closeModal(); });
      searchInput.addEventListener("input", () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(updateResults, 100);
      });
      searchMode.addEventListener("change", updateResults);
      clearButton.addEventListener("click", () => {
        searchInput.value = "";
        searchMode.value = "";
        searchInput.focus();
        updateResults();
      });

      fetch("index.php?json=search-index")
        .then(response => {
          if (!response.ok) throw new Error(`Impossible de charger x3_search_index.json (HTTP ${response.status}).`);
          return response.json();
        })
        .then(data => {
          if (!Array.isArray(data)) throw new Error("x3_search_index.json doit contenir une liste de champs.");
          fields = data;
          for (const field of fields) {
            if (!field.table) continue;
            if (!tables.has(field.table)) tables.set(field.table, []);
            tables.get(field.table).push(field);
          }
          searchInput.disabled = false;
          searchMode.disabled = false;
          clearButton.disabled = false;
          indexStatus.textContent = `${fields.length.toLocaleString("fr-FR")} champs · ${tables.size.toLocaleString("fr-FR")} tables`;
          indexStatus.className = "rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700";
          setEmpty("À vous de chercher", "Saisissez un terme et choisissez Table, Champ ou Intitulé.");
        })
        .catch(error => {
          indexStatus.textContent = "Index indisponible";
          indexStatus.className = "rounded-full bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700";
          setEmpty("Impossible de charger l’index", `${error.message || "Vérifiez que les fichiers JSON sont accessibles."} Lancez un serveur PHP dans le dossier du projet (par exemple : php -S localhost:8000), puis ouvrez http://localhost:8000.`);
        });
    })();
  </script>
</body>
</html>