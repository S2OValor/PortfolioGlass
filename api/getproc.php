<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never {
  http_response_code($code);
  echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$root = dirname(__DIR__);
$dbFile = $root . '/48Qn1C16CnNuMssG/dat/ksdata.sqlite3';

if (!file_exists($dbFile)) {
  out(['ok' => false, 'error' => 'DB not found'], 404);
}

try {
  $db = new SQLite3($dbFile);
  $db->busyTimeout(3000);

  $tid = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;

  if ($tid > 0) {
    $stmt = $db->prepare('SELECT * FROM ksproc WHERE tid = :tid');
    $stmt->bindValue(':tid', $tid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
    $db->close();
    out(['ok' => true, 'row' => $row]);
  } else {
    $res = $db->query('SELECT tid, tname, tproc, tthumb, tmemo FROM ksproc');
    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
    $db->close();
    out(['ok' => true, 'rows' => $rows]);
  }

} catch (Throwable $e) {
  out(['ok' => false, 'error' => $e->getMessage()], 500);
}
