<?php
// index.php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$year = date('Y');
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($config['app']['name'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="./assets/radar.css?v=<?= time() ?>" />
</head>
<body>
  <a class="skip-link" href="#main">Salta al contenuto</a>

  <header class="app-header">
    <div class="wrap header-wrap">
      <div>
        <div class="eyebrow">Radar • Discovery assistita</div>
        <h1 class="title">Radar Local Business Prospector</h1>
        <p class="subtitle">
          MVP Fase A: trova candidati da fonti pubbliche (OSM/Overpass). Niente scraping e nessun chatbot inventa liste.
        </p>
      </div>
      <div class="header-badge" aria-hidden="true">MVP</div>
    </div>
  </header>

  <main id="main" class="wrap">
    <section class="panel">
      <form id="radarForm" class="form" autocomplete="off">
        <div class="grid">
          <label class="field">
            <span class="label">Zona / città</span>
            <input name="area" type="text" required placeholder="es. Sassari" />
            <span class="help">Usiamo geocoding server-side per ottenere coordinate.</span>
          </label>

          <label class="field">
            <span class="label">Settore / categoria</span>
            <input name="sector" type="text" required placeholder="es. parrucchieri" />
            <span class="help">Mappatura OSM (shop/amenity) in base alla categoria.</span>
          </label>

          <label class="field">
            <span class="label">Raggio (km)</span>
            <input name="radiusKm" type="number" inputmode="decimal" min="0.5" step="0.5" value="5" required />
          </label>

          <label class="field">
            <span class="label">Limite candidati</span>
            <input name="limit" type="number" min="1" max="50" step="1" value="20" required />
          </label>

          <label class="field">
            <span class="label">Provider</span>
            <select name="provider" required>
              <option value="osm_overpass" selected>OSM / Overpass</option>
            </select>
          </label>

          <div class="field">
            <span class="label">Avanzamento</span>
            <div id="jobMeta" class="job-meta" role="status" aria-live="polite">
              <div class="muted">Nessun job avviato.</div>
            </div>
          </div>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Avvia Radar</button>
          <button class="btn btn-ghost" type="button" id="btnStop" disabled>Interrompi (MVP)</button>
        </div>

        <div id="errorBox" class="error" role="alert" hidden></div>
      </form>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2 class="section-title">Risultati (candidati discovery)</h2>
        <div class="panel-submeta">
          <span class="pill" id="pillPhase">phase: idle</span>
          <span class="pill" id="pillProvider">provider: -</span>
        </div>
      </div>

      <div class="stats-row">
        <div class="stat">
          <div class="stat-label">Trovati</div>
          <div class="stat-value" id="statFound">0</div>
        </div>
        <div class="stat">
          <div class="stat-label">Deduplicati</div>
          <div class="stat-value" id="statDedup">0</div>
        </div>
        <div class="stat">
          <div class="stat-label">Stato</div>
          <div class="stat-value" id="statStatus">idle</div>
        </div>
      </div>

      <div class="cards" id="cards" aria-live="polite"></div>

      <template id="cardTemplate">
        <article class="card">
          <div class="card-top">
            <h3 class="card-title"></h3>
            <div class="card-badges"></div>
          </div>
          <p class="card-line"><strong>Categoria:</strong> <span class="card-category"></span></p>
          <p class="card-line"><strong>Fonte:</strong> <a class="card-source" href="#" target="_blank" rel="noreferrer noopener">link</a></p>
          <p class="card-line"><strong>Provider ID:</strong> <span class="card-provider-id"></span></p>
          <div class="card-foot">
            <button class="btn btn-sm btn-ghost btn-open-sources" type="button">Apri fonti</button>
          </div>
        </article>
      </template>

      <div id="emptyState" class="empty" hidden>
        <div class="empty-title">Nessun candidato ancora.</div>
        <div class="empty-sub">Avvia un job per eseguire la discovery.</div>
      </div>
    </section>
  </main>

  <footer class="app-footer">
    <div class="wrap footer-wrap">
      <div class="muted">© <?= $year ?> Radar MVP. Dati da fonti pubbliche.</div>
      <div class="muted">Nessuna chiave API lato client.</div>
    </div>
  </footer>

  <script src="./assets/radar.js?v=<?= time() ?>" defer></script>
</body>
</html>
