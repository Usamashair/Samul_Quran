<?php
// Load Mushaf Layout SQLite DB
try {
    $mushafDBPath = __DIR__ . '/data/qudratullah-indopak-15-lines.db';
    $mushafDB = new PDO('sqlite:' . $mushafDBPath);
    $mushafDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Mushaf DB Connection failed: " . $e->getMessage());
}

// Load data files
$quranWords = json_decode(file_get_contents('data/indopak.json'), true); // this is your word-level JSON
$translationData = json_decode(file_get_contents('data/translations.json'), true);
$metaData = json_decode(file_get_contents('data/metadata.json'), true);
$audioData = json_decode(file_get_contents('audio/ayah-recitation-abdur-rahman-as-sudais-recitation.json'), true);

// Group words by Surah & Ayah
$groupedAyahs = [];
foreach ($quranWords as $entry) {
    $surah = intval($entry['surah']);
    $ayah = intval($entry['ayah']);
    $groupedAyahs[$surah][$ayah][] = $entry;
}

// Function to get layout info for an ayah using the first word_id
function getMushafLayout($db, $wordId) {
    $stmt = $db->prepare("
        SELECT * FROM pages
        WHERE line_type = 'ayah'
        AND first_word_id <= :wordId
        AND last_word_id >= :wordId
        LIMIT 1
    ");
    $stmt->execute(['wordId' => $wordId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get selected Surah
$selectedSurah = isset($_GET['surah']) ? intval($_GET['surah']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📖 Quran Viewer</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .ayah { background: #fff; padding: 15px; margin: 15px 0; border-left: 5px solid #0f7c90; }
        audio { width: 100%; margin-top: 10px; }
        a { color: #0f7c90; text-decoration: none; }
    </style>
</head>
<body>

<h1>📖 Quran Viewer</h1>

<?php if (!$selectedSurah): ?>
    <h2>Select a Surah</h2>
    <ul>
        <?php
        foreach ($metaData as $meta) {
    $num = $meta['surah_number'] ?? '?';
    $name = $meta['surah_name'] ?? 'Unknown';
    echo "<li><a href='?surah={$num}'>{$num}. {$name}</a></li>";
}

        ?>
    </ul>
<?php else: ?>
    <h2>Surah <?= $selectedSurah ?> - <?= $metaData[$selectedSurah]['surah_name'] ?? '' ?></h2>


    <?php
    foreach ($groupedAyahs[$selectedSurah] as $ayahNum => $words) {
        $text = implode(' ', array_column($words, 'text'));
        $wordId = $words[0]['id'] ?? null;
        $translation = $translationData["$selectedSurah:$ayahNum"]['t'] ?? '🔸 Translation unavailable';
        $audioUrl = $audioData["$selectedSurah:$ayahNum"]['audio_url'] ?? null;

        $layout = $wordId ? getMushafLayout($mushafDB, $wordId) : null;
        $pageInfo = $layout ? "📄 Page: {$layout['page_number']} | Line: {$layout['line_number']}" : "📄 Layout unavailable";

        echo "<div class='ayah'>";
        echo "<strong>{$selectedSurah}:{$ayahNum}</strong><br>";
        echo "<p style='font-size: 24px;'>$text</p>";
        echo "<p><em>$translation</em></p>";

        if ($audioUrl) {
            echo "<audio controls><source src='$audioUrl' type='audio/mpeg'></audio>";
        } else {
            echo "<p><small>🎵 Audio not available</small></p>";
        }

        echo "<p><small>$pageInfo</small></p>";

        if ($layout && isset($layout['page_number'])) {
            $pageNum = str_pad($layout['page_number'], 3, '0', STR_PAD_LEFT);
            echo "<img src='pages/{$pageNum}.png' alt='Page image' style='width:100%; margin-top:10px;'>";
        }

        echo "</div>";
    }
    ?>

    <p><a href="index.php">⬅ Back to Surah List</a></p>
<?php endif; ?>

</body>
</html>