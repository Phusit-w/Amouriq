<?php
/**
 * Loads English translations into the TranslatePress dictionary of the LOCAL
 * database (Local by Flywheel, MySQL on 127.0.0.1:10005). Not for the live site.
 *
 *   php -d extension=mysqli tools/language-switcher/apply-translations.php <file.json> [status]
 *
 * <file.json> is a list of {"th": "<exact Thai text>", "en": "<English text>"}.
 * Strings are matched by their exact stored Thai text (case-sensitive, HTML
 * entities as TranslatePress stores them, e.g. &#8220;), so the same file works on
 * any copy of the site. TranslatePress adds new strings itself when a page is
 * visited; a Thai text it does not know is reported and nothing is invented.
 * status: 1 = draft (default, shown to visitors, still to be reviewed),
 *         2 = reviewed by the owner.
 */
if (PHP_SAPI !== 'cli' || $argc < 2) {
	fwrite(STDERR, "usage: apply-translations.php <file.json> [status]\n");
	exit(2);
}
$rows = json_decode(file_get_contents($argv[1]), true);
$status = isset($argv[2]) ? (int) $argv[2] : 1;
if (!is_array($rows) || !in_array($status, [1, 2], true)) {
	fwrite(STDERR, "bad input\n");
	exit(2);
}

$db = new mysqli('127.0.0.1', 'root', 'root', 'local', 10005);
$db->set_charset('utf8mb4');
$find   = $db->prepare('SELECT id FROM wp_trp_original_strings WHERE original = ? COLLATE utf8mb4_bin LIMIT 1');
$set_d  = $db->prepare('UPDATE wp_trp_dictionary_th_en_us SET translated = ?, status = ? WHERE original_id = ?');

$written = 0;
$unknown = [];
foreach ($rows as $row) {
	[$th, $en] = [$row['th'], $row['en']];
	$find->bind_param('s', $th);
	$find->execute();
	$id = $find->get_result()->fetch_row()[0] ?? null;
	if ($id === null) {
		$unknown[] = $th;
		continue;
	}
	$set_d->bind_param('sii', $en, $status, $id);
	$set_d->execute();
	$written++;
}
printf("%d translations written, status %d\n", $written, $status);
foreach ($unknown as $th) {
	fwrite(STDERR, "not in TranslatePress (visit the page in English first): " . mb_substr($th, 0, 60) . "\n");
}
exit($unknown ? 1 : 0);
