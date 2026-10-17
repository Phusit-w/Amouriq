<?php
/**
 * Empties English gettext "translations" that are really Thai text in the LOCAL
 * TranslatePress tables (Local by Flywheel, MySQL on 127.0.0.1:10005). Not for the live site.
 *
 *   php -d extension=mysqli tools/language-switcher/clear-thai-gettext-en.php <backup.json>
 *
 * wp_trp_gettext_en_us should hold English for WooCommerce/theme strings, but rows
 * recorded around the plugin's install carry the Thai text (e.g. "N/A" -> "ไม่ระบุ",
 * shown on English product pages). An empty translation makes TranslatePress fall back
 * to the plugin's own English. The affected rows are written to <backup.json> first
 * ({id, translated, status}), so they can be put back.
 */
if (PHP_SAPI !== 'cli' || $argc < 2) {
	fwrite(STDERR, "usage: clear-thai-gettext-en.php <backup.json>\n");
	exit(2);
}

$db = new mysqli('127.0.0.1', 'root', 'root', 'local', 10005);
$db->set_charset('utf8mb4');
$thai = "translated REGEXP '[ก-๙]'";
$rows = $db->query("SELECT id, translated, status FROM wp_trp_gettext_en_us WHERE $thai")->fetch_all(MYSQLI_ASSOC);
if (file_put_contents($argv[1], json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
	fwrite(STDERR, "could not write the backup, nothing changed\n");
	exit(1);
}
$db->query("UPDATE wp_trp_gettext_en_us SET translated = '', status = 0 WHERE $thai");
printf("%d rows emptied, backup in %s\n", $db->affected_rows, $argv[1]);
