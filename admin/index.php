<?php
declare(strict_types=1);
session_start();

$ROOT_DIR = dirname(__DIR__, 2);
$DB_DIR   = $ROOT_DIR . '';
$DB_FILE  = $DB_DIR . '';

date_default_timezone_set('Asia/Tokyo');

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

function ensure_db(string $dbDir, string $dbFile): void {
  $isNew = !file_exists($dbFile);

  if (!is_dir($dbDir)) {
    mkdir($dbDir, 0750, true);
  }
  @chmod($dbDir, 0750);

  $db = new SQLite3($dbFile);
  $db->busyTimeout(3000);
  $db->exec('PRAGMA journal_mode = WAL;');
  $db->exec('PRAGMA foreign_keys = ON;');

  $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS kswork (
  wid     INTEGER PRIMARY KEY AUTOINCREMENT,
  wname   TEXT,
  dtopen  TEXT,
  dtprsn  TEXT,
  dtlast  TEXT,
  lesson  TEXT,
  sensei  TEXT,
  wthumb  TEXT,
  infimg  TEXT,
  infproc REAL,
  inftext TEXT,
  wmemo   TEXT
);
SQL);

  $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS ksproc (
  tid     INTEGER PRIMARY KEY AUTOINCREMENT,
  tname   TEXT,
  tinfo   TEXT,
  tproc   REAL,
  tthumb  TEXT,
  tmemo   TEXT
);
SQL);

  $db->close();

  if ($isNew) {
    @chmod($dbFile, 0640);
  }
}

function open_db(string $dbFile): SQLite3 {
  $db = new SQLite3($dbFile);
  $db->busyTimeout(3000);
  $db->exec('PRAGMA foreign_keys = ON;');
  return $db;
}

function json_out(array $data, int $code = 200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function need_csrf(string $token): void {
  if (empty($_POST['csrf']) || !hash_equals($token, (string)$_POST['csrf'])) {
    json_out(['ok' => false, 'error' => 'CSRF token mismatch'], 403);
  }
}

function clamp_percent($v): float {
  $f = is_numeric($v) ? (float)$v : 0.0;
  if ($f < 0) $f = 0;
  if ($f > 100) $f = 100;
  return $f;
}

function as_text($v): string {
  if ($v === null) return '';
  return (string)$v;
}

ensure_db($DB_DIR, $DB_FILE);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api'])) {
  need_csrf($CSRF);
  $api = (string)$_POST['api'];

  try {
    $db = open_db($DB_FILE);

    switch ($api) {
      case 'list_works': {
        $res = $db->query('SELECT * FROM kswork ORDER BY wid DESC');
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
          $row['infproc'] = clamp_percent($row['infproc'] ?? 0);
          $rows[] = $row;
        }
        $db->close();
        json_out(['ok' => true, 'rows' => $rows]);
      }

      case 'get_work': {
        $wid = (int)($_POST['wid'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM kswork WHERE wid = :wid');
        $stmt->bindValue(':wid', $wid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
        if ($row) $row['infproc'] = clamp_percent($row['infproc'] ?? 0);
        $db->close();
        json_out(['ok' => true, 'row' => $row]);
      }

      case 'save_work': {
        $wid    = (int)($_POST['wid'] ?? 0);
        $wname  = as_text($_POST['wname'] ?? '');
        $dtopen = as_text($_POST['dtopen'] ?? '');
        $dtprsn = as_text($_POST['dtprsn'] ?? '');
        $lesson = as_text($_POST['lesson'] ?? '');
        $sensei = as_text($_POST['sensei'] ?? '');
        $wthumb = as_text($_POST['wthumb'] ?? '');
        $infimg = as_text($_POST['infimg'] ?? '[]');
        $infproc= clamp_percent($_POST['infproc'] ?? 0);
        $inftext= as_text($_POST['inftext'] ?? '');
        $wmemo  = as_text($_POST['wmemo'] ?? '');
        $now    = date('Y-m-d H:i:s');

        if ($wid <= 0) {
          $stmt = $db->prepare(<<<SQL
INSERT INTO kswork (wname, dtopen, dtprsn, dtlast, lesson, sensei, wthumb, infimg, infproc, inftext, wmemo)
VALUES (:wname, :dtopen, :dtprsn, :dtlast, :lesson, :sensei, :wthumb, :infimg, :infproc, :inftext, :wmemo)
SQL);
          $stmt->bindValue(':wname',  $wname,  SQLITE3_TEXT);
          $stmt->bindValue(':dtopen', $dtopen, SQLITE3_TEXT);
          $stmt->bindValue(':dtprsn', $dtprsn, SQLITE3_TEXT);
          $stmt->bindValue(':dtlast', $now,    SQLITE3_TEXT);
          $stmt->bindValue(':lesson', $lesson, SQLITE3_TEXT);
          $stmt->bindValue(':sensei', $sensei, SQLITE3_TEXT);
          $stmt->bindValue(':wthumb', $wthumb, SQLITE3_TEXT);
          $stmt->bindValue(':infimg', $infimg, SQLITE3_TEXT);
          $stmt->bindValue(':infproc',$infproc,SQLITE3_FLOAT);
          $stmt->bindValue(':inftext',$inftext,SQLITE3_TEXT);
          $stmt->bindValue(':wmemo',  $wmemo,  SQLITE3_TEXT);
          $stmt->execute();
          $newId = (int)$db->lastInsertRowID();
          $db->close();
          json_out(['ok' => true, 'wid' => $newId, 'dtlast' => $now]);
        } else {
          $stmt = $db->prepare(<<<SQL
UPDATE kswork SET
  wname=:wname, dtopen=:dtopen, dtprsn=:dtprsn, dtlast=:dtlast,
  lesson=:lesson, sensei=:sensei, wthumb=:wthumb, infimg=:infimg, infproc=:infproc, inftext=:inftext, wmemo=:wmemo
WHERE wid=:wid
SQL);
          $stmt->bindValue(':wname',  $wname,  SQLITE3_TEXT);
          $stmt->bindValue(':dtopen', $dtopen, SQLITE3_TEXT);
          $stmt->bindValue(':dtprsn', $dtprsn, SQLITE3_TEXT);
          $stmt->bindValue(':dtlast', $now,    SQLITE3_TEXT);
          $stmt->bindValue(':lesson', $lesson, SQLITE3_TEXT);
          $stmt->bindValue(':sensei', $sensei, SQLITE3_TEXT);
          $stmt->bindValue(':wthumb', $wthumb, SQLITE3_TEXT);
          $stmt->bindValue(':infimg', $infimg, SQLITE3_TEXT);
          $stmt->bindValue(':infproc',$infproc,SQLITE3_FLOAT);
          $stmt->bindValue(':inftext',$inftext,SQLITE3_TEXT);
          $stmt->bindValue(':wmemo',  $wmemo,  SQLITE3_TEXT);
          $stmt->bindValue(':wid',    $wid,    SQLITE3_INTEGER);
          $stmt->execute();
          $db->close();
          json_out(['ok' => true, 'wid' => $wid, 'dtlast' => $now]);
        }
      }

      case 'delete_work': {
        $wid = (int)($_POST['wid'] ?? 0);
        $stmt = $db->prepare('DELETE FROM kswork WHERE wid = :wid');
        $stmt->bindValue(':wid', $wid, SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
        json_out(['ok' => true]);
      }

      case 'list_proc': {
        $res = $db->query('SELECT * FROM ksproc ORDER BY tid DESC');
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
          $row['tproc'] = clamp_percent($row['tproc'] ?? 0);
          $rows[] = $row;
        }
        $db->close();
        json_out(['ok' => true, 'rows' => $rows]);
      }

      case 'get_proc': {
        $tid = (int)($_POST['tid'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM ksproc WHERE tid = :tid');
        $stmt->bindValue(':tid', $tid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
        if ($row) $row['tproc'] = clamp_percent($row['tproc'] ?? 0);
        $db->close();
        json_out(['ok' => true, 'row' => $row]);
      }

      case 'save_proc': {
        $tid   = (int)($_POST['tid'] ?? 0);
        $tname = as_text($_POST['tname'] ?? '');
        $tinfo = as_text($_POST['tinfo'] ?? '');
        $tproc = clamp_percent($_POST['tproc'] ?? 0);
        $tthumb= as_text($_POST['tthumb'] ?? '');
        $tmemo = as_text($_POST['tmemo'] ?? '');

        if ($tid <= 0) {
          $stmt = $db->prepare(<<<SQL
INSERT INTO ksproc (tname, tinfo, tproc, tthumb, tmemo)
VALUES (:tname, :tinfo, :tproc, :tthumb, :tmemo)
SQL);
          $stmt->bindValue(':tname', $tname, SQLITE3_TEXT);
          $stmt->bindValue(':tinfo', $tinfo, SQLITE3_TEXT);
          $stmt->bindValue(':tproc', $tproc, SQLITE3_FLOAT);
          $stmt->bindValue(':tthumb',$tthumb,SQLITE3_TEXT);
          $stmt->bindValue(':tmemo', $tmemo, SQLITE3_TEXT);
          $stmt->execute();
          $newId = (int)$db->lastInsertRowID();
          $db->close();
          json_out(['ok' => true, 'tid' => $newId]);
        } else {
          $stmt = $db->prepare(<<<SQL
UPDATE ksproc SET
  tname=:tname, tinfo=:tinfo, tproc=:tproc, tthumb=:tthumb, tmemo=:tmemo
WHERE tid=:tid
SQL);
          $stmt->bindValue(':tname', $tname, SQLITE3_TEXT);
          $stmt->bindValue(':tinfo', $tinfo, SQLITE3_TEXT);
          $stmt->bindValue(':tproc', $tproc, SQLITE3_FLOAT);
          $stmt->bindValue(':tthumb',$tthumb, SQLITE3_TEXT);
          $stmt->bindValue(':tmemo', $tmemo, SQLITE3_TEXT);
          $stmt->bindValue(':tid',   $tid,   SQLITE3_INTEGER);
          $stmt->execute();
          $db->close();
          json_out(['ok' => true, 'tid' => $tid]);
        }
      }

      case 'delete_proc': {
        $tid = (int)($_POST['tid'] ?? 0);
        $stmt = $db->prepare('DELETE FROM ksproc WHERE tid = :tid');
        $stmt->bindValue(':tid', $tid, SQLITE3_INTEGER);
        $stmt->execute();
        $db->close();
        json_out(['ok' => true]);
      }

      default:
        $db->close();
        json_out(['ok' => false, 'error' => 'Unknown api'], 400);
    }
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Site Manager</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Klee+One:wght@400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/css/uikit.min.css">
  <style>
    body { font-family: "Klee One", system-ui, -apple-system, "Segoe UI", sans-serif; }
    .thumb { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; background: #f3f3f3; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .small { font-size: 12px; opacity: .85; }
    .cell-memo { max-width: 240px; }
  </style>
</head>
<body>

<div class="uk-section uk-section-default uk-section-small">
  <div class="uk-container">
    <div class="uk-flex uk-flex-middle uk-flex-between uk-margin-small-bottom">
      <div>
        <h2 class="uk-margin-remove">データ管理システム</h2>
        <div class="small mono">DB: <?php echo htmlspecialchars($DB_FILE, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>

    <ul uk-tab>
      <li><a href="#">作品管理（Works）</a></li>
      <li><a href="#">任務管理（Process）</a></li>
    </ul>

    <ul class="uk-switcher uk-margin">
      <li>
        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small">
          <div class="uk-text-bold">作品一覧</div>
          <button class="uk-button uk-button-primary" id="btnNewWork">新規</button>
        </div>

        <div class="uk-overflow-auto">
          <table class="uk-table uk-table-divider uk-table-small uk-table-hover">
            <thead>
              <tr>
                <th>Thumb</th>
                <th>作品ID</th>
                <th>作品名</th>
                <th style="min-width:180px;">作品完成度</th>
                <th>課題出す日</th>
                <th>課題発表日</th>
                <th>最終更新日</th>
                <th>所属授業名</th>
                <th>教師名</th>
                <th class="cell-memo">メモ</th>
                <th style="min-width:130px;">操作</th>
              </tr>
            </thead>
            <tbody id="worksTbody"></tbody>
          </table>
        </div>
      </li>

      <li>
        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small">
          <div class="uk-text-bold">任務一覧</div>
          <button class="uk-button uk-button-primary" id="btnNewProc">新規</button>
        </div>

        <div class="uk-overflow-auto">
          <table class="uk-table uk-table-divider uk-table-small uk-table-hover">
            <thead>
              <tr>
                <th>Thumb</th>
                <th>任務ID</th>
                <th>任務名</th>
                <th style="min-width:180px;">任務完成度</th>
                <th class="cell-memo">メモ</th>
                <th style="min-width:130px;">操作</th>
              </tr>
            </thead>
            <tbody id="procTbody"></tbody>
          </table>
        </div>
      </li>
    </ul>
  </div>
</div>

<div id="modalConfirm" uk-modal>
  <div class="uk-modal-dialog uk-modal-body">
    <h3 class="uk-modal-title" id="confirmTitle">確認</h3>
    <p id="confirmMsg"></p>
    <div class="uk-text-right">
      <button class="uk-button uk-button-default uk-modal-close" type="button">キャンセル</button>
      <button class="uk-button uk-button-danger" id="confirmOk" type="button">OK</button>
    </div>
  </div>
</div>

<div id="modalWork" uk-modal="bg-close:false;esc-close:false">
  <div class="uk-modal-dialog uk-modal-body uk-width-1-1 uk-width-2-3@m">
    <h3 class="uk-modal-title">作品の修正</h3>

    <form class="uk-form-stacked" id="workForm" onsubmit="return false;">
      <input type="hidden" id="work_wid">

      <div class="uk-grid-small" uk-grid>
        <div class="uk-width-1-2@m">
          <label class="uk-form-label">作品名</label>
          <input class="uk-input" id="work_wname" type="text">
        </div>
        <div class="uk-width-1-2@m">
          <label class="uk-form-label">Thumbパス(URL)</label>
          <input class="uk-input mono" id="work_wthumb" type="text">
        </div>

        <div class="uk-width-1-3@m">
          <label class="uk-form-label">課題出す日</label>
          <input class="uk-input mono" id="work_dtopen" type="datetime-local">
        </div>
        <div class="uk-width-1-3@m">
          <label class="uk-form-label">課題発表日</label>
          <input class="uk-input mono" id="work_dtprsn" type="datetime-local">
        </div>
        <div class="uk-width-1-3@m">
          <label class="uk-form-label">作品完成度(0-100)</label>
          <input class="uk-input mono" id="work_infproc" type="number" min="0" max="100" step="0.1">
        </div>

        <div class="uk-width-1-2@m">
          <label class="uk-form-label">所属授業名</label>
          <input class="uk-input" id="work_lesson" type="text">
        </div>
        <div class="uk-width-1-2@m">
          <label class="uk-form-label">教師名</label>
          <input class="uk-input" id="work_sensei" type="text">
        </div>

        <div class="uk-width-1-1">
          <label class="uk-form-label">作品画像パスリスト（1行=1パス）</label>
          <textarea class="uk-textarea mono" id="work_infimg" rows="4" placeholder="img/a.jpg&#10;img/b.jpg"></textarea>
        </div>

        <div class="uk-width-1-1">
          <label class="uk-form-label">作品説明（HTML）</label>
          <textarea class="uk-textarea mono" id="work_inftext" rows="6"></textarea>
        </div>

        <div class="uk-width-1-1">
          <label class="uk-form-label">メモ</label>
          <textarea class="uk-textarea" id="work_wmemo" rows="3"></textarea>
        </div>
      </div>

      <div class="uk-margin-small-top uk-flex uk-flex-between uk-flex-middle">
        <div class="small mono" id="workAutosaveStatus">　</div>
        <div class="uk-button-group">
          <button class="uk-button uk-button-default" id="workBtnTemp">一時保存</button>
          <button class="uk-button uk-button-default" id="workBtnReset">初期化</button>
          <button class="uk-button uk-button-default uk-modal-close" id="workBtnCancel">キャンセル</button>
          <button class="uk-button uk-button-primary" id="workBtnSave">保存</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div id="modalProc" uk-modal="bg-close:false;esc-close:false">
  <div class="uk-modal-dialog uk-modal-body uk-width-1-1 uk-width-1-2@m">
    <h3 class="uk-modal-title">任務の修正</h3>

    <form class="uk-form-stacked" id="procForm" onsubmit="return false;">
      <input type="hidden" id="proc_tid">

      <div class="uk-grid-small" uk-grid>
        <div class="uk-width-1-2@m">
          <label class="uk-form-label">任務名</label>
          <input class="uk-input" id="proc_tname" type="text">
        </div>
        <div class="uk-width-1-2@m">
          <label class="uk-form-label">Thumbパス(URL)</label>
          <input class="uk-input mono" id="proc_tthumb" type="text">
        </div>

        <div class="uk-width-1-1">
          <label class="uk-form-label">任務説明</label>
          <textarea class="uk-textarea" id="proc_tinfo" rows="3"></textarea>
        </div>

        <div class="uk-width-1-2@m">
          <label class="uk-form-label">任務完成度(0-100)</label>
          <input class="uk-input mono" id="proc_tproc" type="number" min="0" max="100" step="0.1">
        </div>

        <div class="uk-width-1-1">
          <label class="uk-form-label">メモ</label>
          <textarea class="uk-textarea" id="proc_tmemo" rows="3"></textarea>
        </div>
      </div>

      <div class="uk-margin-small-top uk-flex uk-flex-between uk-flex-middle">
        <div class="small mono" id="procAutosaveStatus">　</div>
        <div class="uk-button-group">
          <button class="uk-button uk-button-default" id="procBtnTemp">一時保存</button>
          <button class="uk-button uk-button-default" id="procBtnReset">初期化</button>
          <button class="uk-button uk-button-default uk-modal-close" id="procBtnCancel">キャンセル</button>
          <button class="uk-button uk-button-primary" id="procBtnSave">保存</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit-icons.min.js"></script>

<script>
const CSRF = <?php echo json_encode($CSRF); ?>;
function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function nowStr(){ const d=new Date(); const pad=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; }
function dtLocalToMysql(v){ if(!v) return ''; return v.replace('T',' ') + (v.length===16?':00':''); }
function mysqlToDtLocal(v){ if(!v) return ''; return v.replace(' ','T').slice(0,16); }
let confirmOkFn=null;
function confirmModal(title,msg,okFn){ confirmOkFn=okFn; $('#confirmTitle').text(title); $('#confirmMsg').text(msg); UIkit.modal('#modalConfirm').show(); }
$('#confirmOk').on('click', function(){ UIkit.modal('#modalConfirm').hide(); if(confirmOkFn) confirmOkFn(); });

function renderWorks(rows){
  const $tb=$('#worksTbody').empty();
  rows.forEach(r=>{
    const proc=Number(r.infproc??0);
    const thumb=r.wthumb?`<img class="thumb" src="${escapeHtml(r.wthumb)}" alt="">`:`<div class="thumb"></div>`;
    const memo=escapeHtml(r.wmemo??'');
    $tb.append(`
      <tr>
        <td>${thumb}</td>
        <td class="mono">${escapeHtml(r.wid)}</td>
        <td>${escapeHtml(r.wname??'')}</td>
        <td>
          <progress class="uk-progress" value="${proc}" max="100"></progress>
          <div class="small mono">${proc.toFixed(1)}%</div>
        </td>
        <td class="mono">${escapeHtml(r.dtopen??'')}</td>
        <td class="mono">${escapeHtml(r.dtprsn??'')}</td>
        <td class="mono">${escapeHtml(r.dtlast??'')}</td>
        <td>${escapeHtml(r.lesson??'')}</td>
        <td>${escapeHtml(r.sensei??'')}</td>
        <td class="cell-memo"><div class="uk-text-truncate">${memo}</div></td>
        <td>
          <button class="uk-button uk-button-default uk-button-small" onclick="openWork(${Number(r.wid)})">修正</button>
          <button class="uk-button uk-button-danger uk-button-small" onclick="deleteWork(${Number(r.wid)})">削除</button>
        </td>
      </tr>
    `);
  });
}
function loadWorks(){
  $.post('', {api:'list_works', csrf:CSRF}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    renderWorks(res.rows||[]);
  }, 'json');
}

function renderProc(rows){
  const $tb=$('#procTbody').empty();
  rows.forEach(r=>{
    const proc=Number(r.tproc??0);
    const thumb=r.tthumb?`<img class="thumb" src="${escapeHtml(r.tthumb)}" alt="">`:`<div class="thumb"></div>`;
    const memo=escapeHtml(r.tmemo??'');
    $tb.append(`
      <tr>
        <td>${thumb}</td>
        <td class="mono">${escapeHtml(r.tid)}</td>
        <td>${escapeHtml(r.tname??'')}</td>
        <td>
          <progress class="uk-progress" value="${proc}" max="100"></progress>
          <div class="small mono">${proc.toFixed(1)}%</div>
        </td>
        <td class="cell-memo"><div class="uk-text-truncate">${memo}</div></td>
        <td>
          <button class="uk-button uk-button-default uk-button-small" onclick="openProc(${Number(r.tid)})">修正</button>
          <button class="uk-button uk-button-danger uk-button-small" onclick="deleteProc(${Number(r.tid)})">削除</button>
        </td>
      </tr>
    `);
  });
}
function loadProc(){
  $.post('', {api:'list_proc', csrf:CSRF}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    renderProc(res.rows||[]);
  }, 'json');
}

let workOriginal=null;
let workAutosaveTimer=null;
function workDraftKey(){ const wid=Number($('#work_wid').val()||0); return wid>0?`draft_work_${wid}`:'draft_work_new'; }
function workGetForm(){
  const infimgLines=($('#work_infimg').val()||'').split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  return {
    wid:Number($('#work_wid').val()||0),
    wname:$('#work_wname').val()||'',
    dtopen:dtLocalToMysql($('#work_dtopen').val()||''),
    dtprsn:dtLocalToMysql($('#work_dtprsn').val()||''),
    lesson:$('#work_lesson').val()||'',
    sensei:$('#work_sensei').val()||'',
    wthumb:$('#work_wthumb').val()||'',
    infproc:$('#work_infproc').val()||0,
    infimg:JSON.stringify(infimgLines),
    inftext:$('#work_inftext').val()||'',
    wmemo:$('#work_wmemo').val()||'',
  };
}
function workSetForm(d){
  $('#work_wid').val(d?.wid??0);
  $('#work_wname').val(d?.wname??'');
  $('#work_dtopen').val(mysqlToDtLocal(d?.dtopen??''));
  $('#work_dtprsn').val(mysqlToDtLocal(d?.dtprsn??''));
  $('#work_lesson').val(d?.lesson??'');
  $('#work_sensei').val(d?.sensei??'');
  $('#work_wthumb').val(d?.wthumb??'');
  $('#work_infproc').val(d?.infproc??0);
  try{ const arr=JSON.parse(d?.infimg??'[]'); $('#work_infimg').val(Array.isArray(arr)?arr.join('\n'):''); }catch(e){ $('#work_infimg').val(''); }
  $('#work_inftext').val(d?.inftext??'');
  $('#work_wmemo').val(d?.wmemo??'');
}
function workSaveDraft(){ const data=workGetForm(); localStorage.setItem(workDraftKey(), JSON.stringify({t:nowStr(), data})); $('#workAutosaveStatus').text(`${nowStr()} 自動保存成功`); }
function workLoadDraftIfAny(){ const raw=localStorage.getItem(workDraftKey()); if(!raw) return; try{ const o=JSON.parse(raw); if(o?.data){ workSetForm(o.data); $('#workAutosaveStatus').text(`${o.t||nowStr()} ローカル保存から復元`); } }catch(e){} }
function startWorkAutosave(){ stopWorkAutosave(); workAutosaveTimer=setInterval(()=>workSaveDraft(), 5000); }
function stopWorkAutosave(){ if(workAutosaveTimer){ clearInterval(workAutosaveTimer); workAutosaveTimer=null; } }

window.openWork=function(wid){
  $.post('', {api:'get_work', csrf:CSRF, wid:wid}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    const row=res.row||{wid:0};
    workOriginal=JSON.parse(JSON.stringify(row));
    workSetForm(row);
    workLoadDraftIfAny();
    $('#workAutosaveStatus').text('　');
    UIkit.modal('#modalWork').show();
    startWorkAutosave();
  }, 'json');
}

function newWork(){
  confirmModal('新規（Works）', '新規作品を作成しますか？', ()=>{
    const row={wid:0,wname:'',dtopen:'',dtprsn:'',dtlast:'',lesson:'',sensei:'',wthumb:'',infimg:'[]',infproc:0,inftext:'',wmemo:''};
    workOriginal=JSON.parse(JSON.stringify(row));
    workSetForm(row);
    workLoadDraftIfAny();
    $('#workAutosaveStatus').text('　');
    UIkit.modal('#modalWork').show();
    startWorkAutosave();
  });
}
$('#btnNewWork').on('click', newWork);
$('#workBtnTemp').on('click', ()=>workSaveDraft());
$('#workBtnReset').on('click', ()=>{ workSetForm(workOriginal); workSaveDraft(); });
$('#workBtnSave').on('click', ()=>{
  const d=workGetForm();
  $.post('', {...d, api:'save_work', csrf:CSRF}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    localStorage.removeItem(workDraftKey());
    UIkit.modal('#modalWork').hide();
    stopWorkAutosave();
    loadWorks();
    UIkit.notification({message:'保存しました', status:'success'});
  }, 'json');
});
$('#workBtnCancel').on('click', ()=>stopWorkAutosave());

window.deleteWork=function(wid){
  confirmModal('削除（Works）', `作品ID ${wid} を削除しますか？`, ()=>{
    $.post('', {api:'delete_work', csrf:CSRF, wid:wid}, (res)=>{
      if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
      loadWorks();
      UIkit.notification({message:'削除しました', status:'success'});
    }, 'json');
  });
}

let procOriginal=null;
let procAutosaveTimer=null;
function procDraftKey(){ const tid=Number($('#proc_tid').val()||0); return tid>0?`draft_proc_${tid}`:'draft_proc_new'; }
function procGetForm(){ return { tid:Number($('#proc_tid').val()||0), tname:$('#proc_tname').val()||'', tinfo:$('#proc_tinfo').val()||'', tproc:$('#proc_tproc').val()||0, tthumb:$('#proc_tthumb').val()||'', tmemo:$('#proc_tmemo').val()||'' }; }
function procSetForm(d){ $('#proc_tid').val(d?.tid??0); $('#proc_tname').val(d?.tname??''); $('#proc_tinfo').val(d?.tinfo??''); $('#proc_tproc').val(d?.tproc??0); $('#proc_tthumb').val(d?.tthumb??''); $('#proc_tmemo').val(d?.tmemo??''); }
function procSaveDraft(){ const data=procGetForm(); localStorage.setItem(procDraftKey(), JSON.stringify({t:nowStr(), data})); $('#procAutosaveStatus').text(`${nowStr()} 自動保存成功`); }
function procLoadDraftIfAny(){ const raw=localStorage.getItem(procDraftKey()); if(!raw) return; try{ const o=JSON.parse(raw); if(o?.data){ procSetForm(o.data); $('#procAutosaveStatus').text(`${o.t||nowStr()} ローカル保存から復元`); } }catch(e){} }
function startProcAutosave(){ stopProcAutosave(); procAutosaveTimer=setInterval(()=>procSaveDraft(), 5000); }
function stopProcAutosave(){ if(procAutosaveTimer){ clearInterval(procAutosaveTimer); procAutosaveTimer=null; } }

window.openProc=function(tid){
  $.post('', {api:'get_proc', csrf:CSRF, tid:tid}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    const row=res.row||{tid:0};
    procOriginal=JSON.parse(JSON.stringify(row));
    procSetForm(row);
    procLoadDraftIfAny();
    $('#procAutosaveStatus').text('　');
    UIkit.modal('#modalProc').show();
    startProcAutosave();
  }, 'json');
}

function newProc(){
  confirmModal('新規（Process）', '新規任務を作成しますか？', ()=>{
    const row={tid:0,tname:'',tinfo:'',tproc:0,tthumb:'',tmemo:''};
    procOriginal=JSON.parse(JSON.stringify(row));
    procSetForm(row);
    procLoadDraftIfAny();
    $('#procAutosaveStatus').text('　');
    UIkit.modal('#modalProc').show();
    startProcAutosave();
  });
}
$('#btnNewProc').on('click', newProc);
$('#procBtnTemp').on('click', ()=>procSaveDraft());
$('#procBtnReset').on('click', ()=>{ procSetForm(procOriginal); procSaveDraft(); });
$('#procBtnSave').on('click', ()=>{
  const d=procGetForm();
  $.post('', {...d, api:'save_proc', csrf:CSRF}, (res)=>{
    if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
    localStorage.removeItem(procDraftKey());
    UIkit.modal('#modalProc').hide();
    stopProcAutosave();
    loadProc();
    UIkit.notification({message:'保存しました', status:'success'});
  }, 'json');
});
$('#procBtnCancel').on('click', ()=>stopProcAutosave());

window.deleteProc=function(tid){
  confirmModal('削除（Process）', `任務ID ${tid} を削除しますか？`, ()=>{
    $.post('', {api:'delete_proc', csrf:CSRF, tid:tid}, (res)=>{
      if(!res.ok) return UIkit.notification({message: escapeHtml(res.error), status:'danger'});
      loadProc();
      UIkit.notification({message:'削除しました', status:'success'});
    }, 'json');
  });
}

loadWorks();
loadProc();
</script>
</body>
</html>
