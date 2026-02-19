// rezept-modal.js


window.einheitenMap = {};
async function ladeEinheitenMap() {
  if (Object.keys(window.einheitenMap).length > 0) return window.einheitenMap;
  try {
    const res = await fetch('/einkauf-app/produkte/einheiten.json');
    if (res.ok) {
      const data = await res.json();
      if (data.Produkteinheiten) window.einheitenMap = data.Produkteinheiten;
      else window.einheitenMap = {};
    }
  } catch (e) {
    window.einheitenMap = {};
  }
  return window.einheitenMap;
}

async function showPreviewModal(slug, bild) {
  // Warten, bis Produktliste geladen ist
  if (!window.produktListe || Object.keys(window.produktListe).length === 0) {
    await ladeProduktListe();
  }
  // Warten, bis Einheiten geladen sind:
  if (!window.einheitenMap || Object.keys(window.einheitenMap).length === 0) {
    await ladeEinheitenMap();
  }
  try {
    const res = await fetch(`/einkauf-app/rezeptkasten/rezepte/${slug}.json?ts=${Date.now()}`);
    if (!res.ok) throw new Error("Rezeptdaten konnten nicht geladen werden!");
    const details = await res.json();

    // Bild (oben)
    let bildHtml = "";
    if (bild || details.bild) {
      bildHtml = `<img src="${bild || details.bild}" class="modal-img" alt="">`;
    } else {
      bildHtml = `<div class="modal-img-placeholder">
        <span class="material-symbols-outlined" style="font-size:48px; color:#815BE5;">restaurant</span>
      </div>`;
    }

    // Zutaten-Tabelle (IDs auflösen)
    let zutatenTableHtml = "";
    if (Array.isArray(details.zutaten) && details.zutaten.length > 0) {
      zutatenTableHtml = `<table class="modal-details-table mb-2"><thead>
        <tr><th>Zutat</th><th style="text-align:right;">Menge</th><th>Einheit</th></tr></thead><tbody>`;
      for (const z of details.zutaten) {
        let zName = "";
        if (z.id && window.produktListe && window.produktListe[z.id]) {
          zName = window.produktListe[z.id]._name || window.produktListe[z.id].name || "";
        } else {
          zName = z.name || "Unbekannt";
        }
        zutatenTableHtml += `<tr>
          <td>${zName}</td>
          <td style="text-align:right;">${(z.rezeptmenge !== undefined ? z.rezeptmenge : "")}</td>
         <td>${
  (z.rezepteinheit && window.einheitenMap && window.einheitenMap[z.rezepteinheit])
    ? window.einheitenMap[z.rezepteinheit].label
    : (z.rezepteinheit || "")
}</td>
        </tr>`;
      }
      zutatenTableHtml += `</tbody></table>`;
    }



    // Kalorien/Frequenz
    let metaHtml = "";
    if (details.kalorien || details.frequenz !== undefined) {
      metaHtml += `<div class="modal-meta">`;
      if (details.kalorien) {
        metaHtml += `<span>Kalorien: <b>${details.kalorien} kcal</b></span><br>`;
      }
      if (details.frequenz !== undefined) {
        let freqTxt = "Unbekannt";
        if (details.frequenz == 0) freqTxt = "Selten gekocht";
        if (details.frequenz == 1) freqTxt = "Manchmal gekocht";
        if (details.frequenz == 2) freqTxt = "Evergreen";
        metaHtml += `<span>Wie häufig gekocht: <b>${freqTxt}</b></span>`;
      }
      metaHtml += `</div>`;
    }

    // Zubereitung
    let zubereitungHtml = "";
    if (details.zubereitung) {
      zubereitungHtml = `<div class="modal-section-title">Zubereitung</div>
          <div style="white-space:pre-line">${details.zubereitung}</div>`;
    }

    document.getElementById('modalRezeptTitel').innerHTML = details.name +
      (details.vegetarisch === 'ja' ? ' <span class="modal-vegan-icon material-symbols-outlined" title="Vegetarisch">eco</span>' : '');

    document.getElementById('modalRezeptBody').innerHTML = `
      ${bildHtml}
      ${zutatenTableHtml}
      ${metaHtml}
      ${zubereitungHtml}
    `;

    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
  } catch (e) {
    alert("Fehler beim Laden der Rezeptdetails: " + e.message);
  }
}

