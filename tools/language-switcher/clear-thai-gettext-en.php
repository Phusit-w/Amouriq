<?php
/**
 * Empties English gettext "translations" that are really Thai text in the LOCAL
 * TranslatePress tables (Local by Flywheel, MySQL on 127.0.0.1:10005). Not for the live site.
 *
 *   php -d extension=mysqli tools/language-switcher/clear-thai-gettext-en.php clear <backup.json>
 *   php -d extension=mysqli tools/language-switcher/clear-thai-gettext-en.php restore <backup.json>
 *
 * wp_trp_gettext_en_us should hold English for WooCommerce/theme strings, but rows
 * recorded around the plugin's install carry the Thai text (e.g. "N/A" -> "ไม่ระบุ",
 * shown on English product pages). An empty translation makes TranslatePress fall back
 * to the plugin's own English.
 *
 * clear writes the affected rows ({id, translated, status}) to <backup.json> first and
 * refuses to overwrite an existing backup; only the backed-up ids are emptied.
 * restore puts those rows back. The ids belong to this database, so a backup only
 * restores the copy it was taken from.
 */
if (PHP_SAPI !== 'cli' || $argc < 3 || !in_array($argv[1], ['clear', 'restore'], true)) {
	fwrite(STDERR, "usage: clear-thai-gettext-en.php clear|restore <backup.json>\n");
	exit(2);
}
[, $mode, $file] = $argv;

$db = new mysqli('127.0.0.1', 'root', 'root', 'local', 10005);
$db->set_charset('utf8mb4');

if ($mode === 'restore') {
	$rows = json_decode((string) @file_get_contents($file), true);
	if (!is_array($rows)) {
		fwrite(STDERR, "cannot read the backup $file\n");
		exit(1);
	}
	$put = $db->prepare('UPDATE wp_trp_gettext_en_us SET translated = ?, status = ? WHERE id = ?');
	$db->begin_transaction();
	foreach ($rows as $row) {
		$put->bind_param('sii', $row['translated'], $row['status'], $row['id']);
		$put->execute();
	}
	$db->commit();
	printf("%d rows restored from %s\n", count($rows), $file);
	exit(0);
}

if (file_exists($file)) {
	fwrite(STDERR, "$file already exists; not overwriting a backup, nothing changed\n");
	exit(1);
}
// Thai letters, vowels and digits; the baht sign (U+0E3F) is not Thai text
$rows = $db->query("SELECT id, translated, status FROM wp_trp_gettext_en_us WHERE translated REGEXP '[ก-ฺเ-๛]'")->fetch_all(MYSQLI_ASSOC);
$json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false || file_put_contents($file, $json) !== strlen($json)) {
	fwrite(STDERR, "could not write the backup, nothing changed\n");
	exit(1);
}
if (!$rows) {
	echo "no Thai rows, nothing changed\n";
	exit(0);
}
$ids = implode(',', array_map('intval', array_column($rows, 'id')));
$db->query("UPDATE wp_trp_gettext_en_us SET translated = '', status = 0 WHERE id IN ($ids)");
printf("%d rows emptied, backup in %s\n", $db->affected_rows, $file);
