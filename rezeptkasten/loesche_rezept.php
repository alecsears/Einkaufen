<?php
session_start();

// Der Hidden-Input im Formular heißt "deletefile"
$datei = isset($_POST['deletefile']) ? basename($_POST['deletefile']) : '';

// Pfade zu Rezept und Bild (jetzt rezepte_j!)
$rezeptePfad = __DIR__ . '/rezepte/' . $datei;
$bildPfad   = __DIR__ . '/bilder/' . pathinfo($datei, PATHINFO_FILENAME) . '.jpg';

if ($datei) {
    // Rezeptdatei löschen
    if (file_exists($rezeptePfad)) {
        unlink($rezeptePfad);
    }
    // ggf. dazugehöriges Bild löschen
    if (file_exists($bildPfad)) {
        unlink($bildPfad);
    }
}

// Zurück zur Übersicht (jetzt rezeptkasten_j.php!)
header('Location: rezeptauswahl.php');
exit;