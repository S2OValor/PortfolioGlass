// ./lib/pdfout/pdfout.js
// Typst.ts (WASM) を使ってPDFを生成するラッパ
// - 作品説明HTMLは「簡易HTML→Typst」変換（太字/斜体/リンク/改行/見出し/リスト）
// - 画像が多い時は「表紙 → 基本情報 → ギャラリー（改ページ）」

let _inited = false;
let _initPromise = null;

function safeFileName(name){
  return String(name || 'work')
    .replace(/[\\\/:*?"<>|]/g, '_')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 80) || 'work';
}

function typstEscapeText(s){
  // Typstの[]内テキスト用の最低限エスケープ
  return String(s ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/\[/g, '\\[')
    .replace(/\]/g, '\\]')
    .replace(/\*/g, '\\*')
    .replace(/#/g, '\\#');
}

function guessExt(path){
  const m = String(path||'').toLowerCase().match(/\.([a-z0-9]+)(\?|#|$)/);
  if (!m) return 'png';
  const ext = m[1];
  if (['png','jpg','jpeg','webp','gif','svg'].includes(ext)) return ext;
  return 'png';
}

async function mapImageToShadow(url, shadowPath){
  const resp = await fetch(url, { cache: 'no-store' });
  if (!resp.ok) throw new Error(`image fetch failed: ${url}`);
  const buf = await resp.arrayBuffer();
  window.$typst.mapShadow(shadowPath, new Uint8Array(buf));
  return shadowPath;
}

function clampPercent(v){
  const f = Number(v);
  if (!isFinite(f)) return 0;
  return Math.max(0, Math.min(100, f));
}

function htmlToTypst(html){
  // 安全＆軽量な簡易変換（完璧なHTML→Typstではないが、体裁を残す）
  const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');

  function walk(node, ctx){
    let out = '';

    if (node.nodeType === Node.TEXT_NODE) {
      return typstEscapeText(node.nodeValue || '');
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
      return '';
    }

    const tag = node.tagName.toLowerCase();

    // ブロック要素
    if (tag === 'br') return '\n';

    if (tag === 'p') {
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return out.trim() ? out + '\n\n' : '\n';
    }

    if (/^h[1-6]$/.test(tag)) {
      const lv = Number(tag.slice(1));
      const eq = '='.repeat(Math.max(1, Math.min(3, lv))); // Typstの見出しは =, ==, === 程度で十分
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return `${eq} [${out.trim()}]\n\n`;
    }

    if (tag === 'ul' || tag === 'ol') {
      const isOl = (tag === 'ol');
      let i = 1;
      node.childNodes.forEach(li => {
        if (li.nodeType === Node.ELEMENT_NODE && li.tagName.toLowerCase() === 'li') {
          let line = '';
          li.childNodes.forEach(ch => line += walk(ch, ctx));
          line = line.trim();
          if (!line) return;
          if (isOl) out += `${i}. [${line}]\n`;
          else out += `- [${line}]\n`;
          i++;
        }
      });
      return out + '\n';
    }

    if (tag === 'li') {
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return out;
    }

    // インライン装飾
    if (tag === 'strong' || tag === 'b') {
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return `*${out}*`;
    }

    if (tag === 'em' || tag === 'i') {
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return `_${out}_`;
    }

    if (tag === 'code') {
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      return `\`${out}\``;
    }

    if (tag === 'a') {
      const href = node.getAttribute('href') || '';
      node.childNodes.forEach(ch => out += walk(ch, ctx));
      const text = out.trim() || href;
      if (!href) return text;
      // Typst link: #link("url")[text]
      return `#link("${href.replace(/"/g,'')}")[${text}]`;
    }

    // その他は子を連結
    node.childNodes.forEach(ch => out += walk(ch, ctx));
    return out;
  }

  let result = '';
  doc.body.childNodes.forEach(n => result += walk(n, {}));
  result = result.replace(/\n{3,}/g, '\n\n').trim();
  return result || '（説明なし）';
}

async function ensureInit(){
  if (_inited) return;
  if (_initPromise) return _initPromise;

  _initPromise = (async () => {
    // 同フォルダに置いた Typst.ts バンドルを読み込む（placeholderから置き換え前提）
    await import('./all-in-one-lite.bundle.js');

    if (!window.$typst) {
      throw new Error('window.$typst が見つかりません。lib/pdfout の Typst.ts ファイルを実物に置き換えてください。');
    }

    const base = new URL('.', import.meta.url);

    window.$typst.setCompilerInitOptions({
      getModule: () => new URL('./typst_ts_web_compiler_bg.wasm', base).toString()
    });

    window.$typst.setRendererInitOptions({
      getModule: () => new URL('./typst_ts_renderer_bg.wasm', base).toString()
    });

    // ウォームアップ
    try { await window.$typst.vector({ mainContent: ' ' }); } catch(e) {}

    _inited = true;
  })();

  return _initPromise;
}

async function buildPdfForWork(work){
  await ensureInit();

  window.$typst.resetShadow();

  const wid   = work?.wid ?? '';
  const title = work?.wname ?? '';
  const proc  = clampPercent(work?.infproc ?? 0);

  // infimg: JSON文字列 or array
  let infimg = work?.infimg ?? [];
  if (typeof infimg === 'string') {
    try { infimg = JSON.parse(infimg); } catch { infimg = []; }
  }
  if (!Array.isArray(infimg)) infimg = [];

  // 画像をshadowに登録
  const shadowImgs = [];
  for (let i = 0; i < infimg.length; i++){
    const url = infimg[i];
    if (!url) continue;
    const ext = guessExt(url);
    const shadowPath = `/assets/work_${wid}_${i}.${ext}`;
    try{
      await mapImageToShadow(url, shadowPath);
      shadowImgs.push(shadowPath);
    }catch(e){
      console.warn(e);
    }
  }

  // 作品説明（HTML→Typst）
  const descTypst = htmlToTypst(work?.inftext || '');

  // ギャラリー（画像が多い場合は1枚1ページ）
  let gallery = '';
  if (shadowImgs.length === 0) {
    gallery = '[No Image]';
  } else {
    gallery = shadowImgs.map((p, idx) => {
      const header = `=== 画像 ${idx+1}`;
      const body = `image("${p}", width: 100%, height: 18cm, fit: "contain")`;
      const br = (idx < shadowImgs.length - 1) ? '\n#pagebreak()\n' : '';
      return `${header}\n#${body}${br}`;
    }).join('\n\n');
  }

  // Typst本文（表紙→基本情報→説明→ギャラリー）
  const mainContent = `
#set page(margin: (top: 1.2cm, bottom: 1.2cm, left: 1.4cm, right: 1.4cm))
#set text(size: 11pt)

= [${typstEscapeText(title)}]
#text(size: 9pt)[作品ID: ${typstEscapeText(wid)}]

== 任務完成度
#let p = ${proc} / 100
#stack(
  rect(width: 100%, height: 0.35cm, fill: luma(90%)),
  rect(width: (100% * p), height: 0.35cm, fill: luma(35%)),
  place(center, text(size: 9pt)[${proc.toFixed(1)}%])
)
#v(0.25cm)

== 基本情報
#table(
  columns: (auto, 1fr),
  [*作品名*], [${typstEscapeText(work?.wname || '')}],
  [*所属授業名*], [${typstEscapeText(work?.lesson || '')}],
  [*教師名*], [${typstEscapeText(work?.sensei || '')}],
  [*課題出す日*], [${typstEscapeText(work?.dtopen || '')}],
  [*課題発表日*], [${typstEscapeText(work?.dtprsn || '')}],
  [*最終更新日*], [${typstEscapeText(work?.dtlast || '')}],
  [*メモ*], [${typstEscapeText(work?.wmemo || '')}],
)

== 作品説明
${descTypst}

#pagebreak()
== 作品画像
${gallery}
`.trim();

  const pdfData = await window.$typst.pdf({ mainContent });
  window.$typst.resetShadow();
  return pdfData;
}

async function exportWorkPdf(work, opts = {}){
  const pdfData = await buildPdfForWork(work);
  const blob = new Blob([pdfData], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);

  const fileName = safeFileName(opts.fileName || work?.wname || `work_${work?.wid || ''}`) + '.pdf';

  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  a.click();

  setTimeout(() => URL.revokeObjectURL(url), 60_000);
  return { blob, url, fileName };
}

window.KS_PDFOUT = {
  init: ensureInit,
  exportWorkPdf
};
