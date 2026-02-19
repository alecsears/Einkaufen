<?php
header('Content-Type: application/json');

$wahlFile = __DIR__ . '/../listen/rezeptwahl.json';
$zutatenFile = __DIR__ . '/../listen/aktuell.json';
$produkteFile = __DIR__ . '/../produkte/produktliste.json';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    $input = json_decode($data, true);

    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiges JSON']);
        exit;
    }

    if (isset($input['rezepte']) && is_array($input['rezepte'])) {
        $rezepte = $input['rezepte'];
    } elseif (is_array($input)) {
        $rezepte = $input;
    } else {
        $rezepte = [];
    }

    file_put_contents($wahlFile, json_encode($rezepte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Produktliste (mit Standard-Flag)
    $produktliste = [];
    if (file_exists($produkteFile)) {
        $produktliste = json_decode(file_get_contents($produkteFile), true);
        if (!is_array($produktliste)) $produktliste = [];
    }

    // Build ID → standard-status lookup map for O(1) access instead of O(n) linear search
    $produktStatusById = [];
    foreach ($produktliste as $produkt) {
        if (isset($produkt['id'])) {
            $produktStatusById[(string)$produkt['id']] = (isset($produkt['standard']) && $produkt['standard'] === "ja") ? "ja" : "nein";
        }
    }

    // Zutaten aus Rezepten sammeln (key = id|einheit)
    $alleZutaten = [];
    foreach ($rezepte as $file) {
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $pfad = __DIR__ . "/../rezeptkasten/rezepte/" . $slug . ".json";
        if (!file_exists($pfad)) continue;
        $json = file_get_contents($pfad);
        $rezept = json_decode($json, true);
        if (!$rezept || !isset($rezept['zutaten']) || !is_array($rezept['zutaten'])) continue;
        $rezeptname = isset($rezept['name']) && $rezept['name'] ? $rezept['name'] : $slug;
        foreach ($rezept['zutaten'] as $eintrag) {
            if (empty($eintrag['id']) || !isset($eintrag['rezeptmenge'])) continue;
            $id = $eintrag['id'];
            $rezeptmenge = floatval(str_replace(',', '.', $eintrag['rezeptmenge']));
            $einheit = isset($eintrag['rezepteinheit']) ? $eintrag['rezepteinheit'] : '';
            $key = $id . '|' . $einheit;
            // Standard-Flag
            if (isset($eintrag['standard'])) {
                $standard = $eintrag['standard'] === "ja" ? "ja" : "nein";
            } else {
                $standard = $produktStatusById[(string)$id] ?? "nein";
            }
            if (!isset($alleZutaten[$key])) {
                $alleZutaten[$key] = [
                    'id' => $id,
                    'rezeptmenge' => 0,
                    'rezepteinheit' => $einheit,
                    'rezeptquelle' => [],
                    'standard' => $standard
                ];
            }
            $alleZutaten[$key]['rezeptmenge'] += $rezeptmenge;
            if (!in_array($rezeptname, $alleZutaten[$key]['rezeptquelle'])) {
                $alleZutaten[$key]['rezeptquelle'][] = $rezeptname;
            }
            if ($standard === "ja") $alleZutaten[$key]['standard'] = "ja";
        }
    }

    // Build reverse map: product id => keys in $alleZutaten for O(1) lookup
    $alleZutatenByIdKeys = [];
    foreach ($alleZutaten as $key => $z) {
        $alleZutatenByIdKeys[(string)$z['id']][] = $key;
    }

    // Alle Standardprodukte aus produktliste.json sicherstellen
    foreach ($produktliste as $produkt) {
        if (isset($produkt['standard']) && $produkt['standard'] === "ja" && isset($produkt['id'])) {
            $id = $produkt['id'];
            $einheit = isset($produkt['supermarkteinheit']) ? $produkt['supermarkteinheit'] : '';
            $idStr = (string)$id;
            $istSchonDrin = isset($alleZutatenByIdKeys[$idStr]);
            if ($istSchonDrin) {
                foreach ($alleZutatenByIdKeys[$idStr] as $k) {
                    $alleZutaten[$k]['standard'] = "ja"; // Flag immer setzen!
                }
            } else {
                $key = $id . '|' . $einheit;
                $alleZutaten[$key] = [
                    'id' => $id,
                    'rezeptmenge' => 0,
                    'rezepteinheit' => $einheit,
                    'rezeptquelle' => [],
                    'standard' => "ja"
                ];
            }
        }
    }

    $ausgabe = array_values($alleZutaten);
    file_put_contents($zutatenFile, json_encode($ausgabe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => 'ok', 'anzahl' => count($ausgabe), 'rezepte' => count($rezepte)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($wahlFile)) {
        header('Content-Type: application/json');
        readfile($wahlFile);
    } else {
        header('Content-Type: application/json');
        echo '[]';
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
?>