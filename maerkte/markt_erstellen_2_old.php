<?php
// Pfade
$slug      = $_GET['markt'] ?? '';
$marktFile = __DIR__ . "/lokationen/{$slug}.txt";
$abtFile   = __DIR__ . '/abteilungen.txt';

// Lade alle möglichen Abteilungen
$options = [];
if (file_exists($abtFile)) {
    $options = file($abtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

// Lade bestehende Marktdaten, wenn ein Slug übergeben wurde
$marktname = '';
$zeilen    = [];
if ($slug !== '' && file_exists($marktFile)) {
    $zeilen = file($marktFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $marktname = array_shift($zeilen);
}
$optionsHtml = '';
foreach ($options as $opt) {
    $optEsc = htmlspecialchars($opt, ENT_QUOTES);
    $optionsHtml .= "<option value=\"{$optEsc}\">{$optEsc}</option>";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= $marktname ? htmlspecialchars($marktname, ENT_QUOTES).' – Abteilungen' : 'Neuer Markt – Abteilungen' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <style>
    .drag-handle { cursor: grab; }
    .dragging { opacity: 0.5; }
    .sticky-footer {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff; padding: .5rem 1rem;
      border-top: 1px solid #ddd;
    }
  </style>
</head>
<body>
  <div class="container py-4">
    <h1 class="mb-4"><?= $marktname ? 'Markt: '.htmlspecialchars($marktname, ENT_QUOTES) : 'Neuen Markt anlegen' ?></h1>
    <form id="abteilungsForm">
      <div class="mb-3">
        <label for="marktName" class="form-label">Name des Marktes:</label>
        <input type="text" id="marktName" name="marktName"
               class="form-control"
               placeholder="z. B. MeinSupermarkt"
               value="<?= htmlspecialchars($marktname, ENT_QUOTES) ?>">
      </div>
      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle" id="abteilungen">
          <thead>
            <tr>
              <th style="width:1%"></th>
              <th>Abteilung</th>
              <th style="width:1%"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($zeilen as $abteilung): ?>
            <tr draggable="true">
              <td class="text-muted drag-handle">≡</td>
              <td>
                <select class="form-select">
                  <option value="">-- Bitte wählen --</option>
                  <?php foreach ($options as $opt): ?>
                  <option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>"
                    <?= $opt === $abteilung ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt, ENT_QUOTES) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <button type="button" class="btn btn-danger btn-sm delete-row">×</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
    </form>
  </div>

  <div class="sticky-footer d-flex justify-content-between align-items-center">
    <button id="neueZeile" class="btn btn-outline-primary">
      <span class="material-symbols-outlined">add</span> Zeile
    </button>
    <button id="save" class="btn btn-outline-success">
      <span class="material-symbols-outlined">save</span> Speichern
    </button>
  </div>

  <script>
    const tbody       = document.querySelector('#abteilungen tbody');
    const optionsHtml = <?= json_encode($optionsHtml) ?>;

    // Drag & Drop
    let dragged;
    tbody.addEventListener('dragstart', e => {
      if (e.target.tagName==='TR') {
        dragged = e.target;
        e.target.classList.add('dragging');
      }
    });
    tbody.addEventListener('dragend', e => {
      e.target.classList.remove('dragging');
    });
    tbody.addEventListener('dragover', e => {
      e.preventDefault();
      const after = [...tbody.children]
        .filter(r => r!==dragged)
        .find(r => e.clientY < r.getBoundingClientRect().top + r.offsetHeight/2);
      if (after) tbody.insertBefore(dragged, after);
      else tbody.appendChild(dragged);
    });

    // Neue Zeile
    document.getElementById('neueZeile').addEventListener('click', () => {
      const tr = document.createElement('tr');
      tr.draggable = true;
      tr.innerHTML = `
        <td class="text-muted drag-handle">≡</td>
        <td>
          <select class="form-select">
            <option value="">-- Bitte wählen --</option>
            ${optionsHtml}
          </select>
        </td>
        <td>
          <button type="button" class="btn btn-danger btn-sm delete-row">×</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // Zeilen löschen
    tbody.addEventListener('click', e => {
      if (e.target.classList.contains('delete-row')) {
        e.target.closest('tr').remove();
      }
    });

    // Speichern
    document.getElementById('save').addEventListener('click', async () => {
      const marktName = document.getElementById('marktName').value.trim();
      if (!marktName) return alert('Bitte einen Markt-Namen eingeben!');
      const slug = document.querySelector('input[name=slug]').value;
      const abteilungen = [...tbody.querySelectorAll('select')]
        .map(s=>s.value).filter(v=>v);
      const res = await fetch('speichere_supermarkt.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ slug, marktName, abteilungen })
      });
      const json = await res.json();
      if (json.success) {
      //  alert('Marktdatei gespeichert als „'+json.file+'“');
        window.location.href = 'index.php?markt='+encodeURIComponent(json.slug);
      } else {
        alert('Fehler: '+json.error);
      }
    });
  </script>
</body>
</html>
