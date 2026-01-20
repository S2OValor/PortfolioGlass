<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never {
  http_response_code($code);
  echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$root = dirname(__DIR__); // api/ の1つ上がサイトルート
$dbFile = $root . '/48Qn1C16CnNuMssG/dat/ksdata.sqlite3';

if (!file_exists($dbFile)) {
  out(['ok' => false, 'error' => 'DB not found'], 404);
}

try {
  $db = new SQLite3($dbFile);
  $db->busyTimeout(3000);

  $wid = isset($_GET['wid']) ? (int)$_GET['wid'] : 0;

  if ($wid > 0) {
    $stmt = $db->prepare('SELECT * FROM kswork WHERE wid = :wid');
    $stmt->bindValue(':wid', $wid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
    $db->close();
    out(['ok' => true, 'row' => $row]);
  } else {
    $res = $db->query('SELECT wid, wname, dtopen, dtprsn, dtlast, lesson, sensei, wthumb, infproc, wmemo FROM kswork');
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    $db->close();
    out(['ok' => true, 'rows' => $rows]);
  }

} catch (Throwable $e) {
  out(['ok' => false, 'error' => $e->getMessage()], 500);
}
