<div id="mainCont">
  <div class="mainWorksTools uk-flex uk-flex-between uk-flex-middle uk-margin-small">
    <div class="uk-button-group">
      <button class="uk-button uk-button-default" id="btnLayoutGrid">GRID</button>
      <button class="uk-button uk-button-default" id="btnLayoutTable">TABLE</button>
    </div>

    <div class="uk-flex uk-flex-middle uk-grid-small" uk-grid>
      <div>
        <select class="uk-select" id="selSortField">
          <option value="dtprsn">課題発表日</option>
          <option value="dtopen">課題出す日</option>
          <option value="dtlast">最終更新日</option>
          <option value="infproc">任務完成度</option>
        </select>
      </div>
      <div>
        <select class="uk-select" id="selSortDir">
          <option value="desc">降順</option>
          <option value="asc">昇順</option>
        </select>
      </div>
      <div>
        <select class="uk-select" id="selLesson">
          <option value="">所属授業名：全部</option>
        </select>
      </div>
      <div>
        <select class="uk-select" id="selSensei">
          <option value="">教師名：全部</option>
        </select>
      </div>
    </div>
  </div>

  <div id="worksEmpty" class="uk-alert uk-alert-warning uk-hidden">
    表示できる作品がありません。
  </div>

  <div id="worksGridWrap">
    <div class="uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l" uk-grid id="worksGrid"></div>
  </div>

  <div id="worksTableWrap" class="uk-hidden">
    <div class="uk-overflow-auto">
      <table class="uk-table uk-table-divider uk-table-small uk-table-hover">
        <thead>
          <tr>
            <th>作品名</th>
            <th>所属授業名</th>
            <th>教師名</th>
            <th>任務完成度(%)</th>
            <th>課題出す日</th>
            <th>課題発表日</th>
            <th>最終更新日</th>
            <th class="memo-trunc">メモ</th>
            <th style="min-width:92px;">詳細</th>
          </tr>
        </thead>
        <tbody id="worksTbody"></tbody>
      </table>
    </div>
  </div>

  <script>
  (function(){
    // HTMX差し替え対策
    if(window.WorksPage && window.WorksPage.destroy) window.WorksPage.destroy();

    const esc = (s)=> String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const apiUrl = 'api/getworks.php';

    const parseDT = (s)=>{
      if(!s) return NaN;
      const t = Date.parse(String(s).replace(' ','T'));
      return isNaN(t) ? NaN : t;
    };
    const num = (v)=> (isFinite(Number(v)) ? Number(v) : 0);

    const state = {
      all: [],
      view: (localStorage.getItem('works_view') || 'grid'),
      sortField: 'dtprsn',
      sortDir: 'desc',
      lesson: '',
      sensei: '',
      currentWid: null,
      currentDetail: null,
      originalFooter: null,
    };

    // 初期：課題発表日 降順
    $('#selSortField').val('dtprsn');
    $('#selSortDir').val('desc');

    function setView(v){
      state.view = v;
      localStorage.setItem('works_view', v);
      if(v === 'grid'){
        $('#worksGridWrap').removeClass('uk-hidden');
        $('#worksTableWrap').addClass('uk-hidden');
        $('#btnLayoutGrid').addClass('uk-button-primary').removeClass('uk-button-default');
        $('#btnLayoutTable').addClass('uk-button-default').removeClass('uk-button-primary');
      }else{
        $('#worksGridWrap').addClass('uk-hidden');
        $('#worksTableWrap').removeClass('uk-hidden');
        $('#btnLayoutTable').addClass('uk-button-primary').removeClass('uk-button-default');
        $('#btnLayoutGrid').addClass('uk-button-default').removeClass('uk-button-primary');
      }
    }

    function getOrderedWorks(){
      let rows = state.all.slice();
      if(state.lesson) rows = rows.filter(r => (r.lesson ?? '') === state.lesson);
      if(state.sensei) rows = rows.filter(r => (r.sensei ?? '') === state.sensei);

      const f = state.sortField;
      const dir = state.sortDir === 'asc' ? 1 : -1;

      rows.sort((a,b)=>{
        let va, vb;
        if(f === 'infproc'){
          va = num(a.infproc); vb = num(b.infproc);
        } else {
          va = parseDT(a[f]); vb = parseDT(b[f]);
          // 空日時は末尾へ
          if(isNaN(va) && isNaN(vb)) { va = vb = 0; }
          else if(isNaN(va)) va = -Infinity;
          else if(isNaN(vb)) vb = -Infinity;
        }
        if(va < vb) return -1 * dir;
        if(va > vb) return  1 * dir;
        return (num(a.wid) - num(b.wid)) * dir;
      });

      return rows;
    }

    function getPrevNextWid(currentWid){
      const ordered = getOrderedWorks();
      const idx = ordered.findIndex(x => String(x.wid) === String(currentWid));
      const prev = (idx > 0) ? Number(ordered[idx-1].wid) : null;
      const next = (idx >= 0 && idx < ordered.length-1) ? Number(ordered[idx+1].wid) : null;
      return {prev, next};
    }

    function rebuildFilterOptions(){
      const lessons = Array.from(new Set(state.all.map(r=> (r.lesson ?? '')).filter(Boolean))).sort();
      const senseis = Array.from(new Set(state.all.map(r=> (r.sensei ?? '')).filter(Boolean))).sort();

      const $l = $('#selLesson').empty().append('<option value="">所属授業名：全部</option>');
      lessons.forEach(x => $l.append(`<option value="${esc(x)}">${esc(x)}</option>`));

      const $s = $('#selSensei').empty().append('<option value="">教師名：全部</option>');
      senseis.forEach(x => $s.append(`<option value="${esc(x)}">${esc(x)}</option>`));
    }

    function render(){
      const rows = getOrderedWorks();
      $('#worksEmpty').toggleClass('uk-hidden', rows.length !== 0);

      const $g = $('#worksGrid').empty();
      rows.forEach(r=>{
        const p = Math.max(0, Math.min(100, num(r.infproc)));
        const thumb = r.wthumb ? `<img src="${esc(r.wthumb)}" alt="">` : `<div class="small mono">No Image</div>`;
        const memo = esc(r.wmemo ?? '');
        $g.append(`
          <div>
            <div class="uk-card uk-card-default uk-card-small uk-card-hover uk-border-rounded">
              <div class="uk-card-body">
                <div class="works-thumbbox uk-margin-small-bottom">${thumb}</div>

                <div class="card-line">
                  <progress class="uk-progress" value="${p}" max="100"></progress>
                  <div class="small mono">${p.toFixed(1)}%</div>
                </div>

                <div class="card-line uk-text-bold">${esc(r.wname ?? '')}</div>
                <div class="card-line small">所属授業名：${esc(r.lesson ?? '')}</div>
                <div class="card-line small">教師名：${esc(r.sensei ?? '')}</div>
                <div class="card-line small uk-text-truncate">メモ：${memo}</div>

                <div class="uk-margin-small-top uk-text-center">
                  <button class="uk-button uk-button-primary uk-button-small" data-wid="${esc(r.wid)}">詳細</button>
                </div>
              </div>
            </div>
          </div>
        `);
      });

      const $tb = $('#worksTbody').empty();
      rows.forEach(r=>{
        const p = Math.max(0, Math.min(100, num(r.infproc)));
        const memo = esc(r.wmemo ?? '');
        $tb.append(`
          <tr>
            <td>${esc(r.wname ?? '')}</td>
            <td>${esc(r.lesson ?? '')}</td>
            <td>${esc(r.sensei ?? '')}</td>
            <td class="mono">${p.toFixed(1)}</td>
            <td class="mono">${esc(r.dtopen ?? '')}</td>
            <td class="mono">${esc(r.dtprsn ?? '')}</td>
            <td class="mono">${esc(r.dtlast ?? '')}</td>
            <td class="uk-text-truncate memo-trunc">${memo}</td>
            <td><button class="uk-button uk-button-primary uk-button-small" data-wid="${esc(r.wid)}">詳細</button></td>
          </tr>
        `);
      });
    }

    function setModalFooterButtons(currentWid){
      const $footer = $('#modal-footer');
      if(!state.originalFooter) state.originalFooter = $footer.html();

      const {prev, next} = getPrevNextWid(currentWid);
      const prevDisabled = prev ? '' : 'disabled';
      const nextDisabled = next ? '' : 'disabled';

      $footer.html(`
        <button class="uk-button uk-button-primary" id="btnWorkPrev" type="button" ${prevDisabled} data-wid="${prev||''}">＜前の作品</button>
        <button class="uk-button uk-button-default" id="btnWorkPrint" type="button">印刷</button>
        <button class="uk-button uk-button-danger uk-modal-close" id="btnWorkClose" type="button">閉じる</button>
        <button class="uk-button uk-button-default" id="btnWorkPdf" type="button">PDF出力</button>
        <button class="uk-button uk-button-primary" id="btnWorkNext" type="button" ${nextDisabled} data-wid="${next||''}">次の作品＞</button>
      `);

      $('#btnWorkPrev').off('click').on('click', function(){
        const wid = $(this).data('wid');
        if(wid) openDetail(wid);
      });
      $('#btnWorkNext').off('click').on('click', function(){
        const wid = $(this).data('wid');
        if(wid) openDetail(wid);
      });
      $('#btnWorkPrint').off('click').on('click', ()=> window.print());
      $('#btnWorkPdf').off('click').on('click', async ()=>{
        if(!state.currentDetail){
          UIkit.notification({message:'詳細情報がありません', status:'warning'});
          return;
        }
        try{
          if(window.KS_PDFOUT && typeof window.KS_PDFOUT.exportWorkPdf === 'function'){
            await window.KS_PDFOUT.exportWorkPdf(state.currentDetail, {download:true});
          }else{
            UIkit.notification({message:'PDF出力（lib/pdfout）が未導入です', status:'warning'});
          }
        }catch(e){
          console.error(e);
          UIkit.notification({message:'PDF出力に失敗しました', status:'danger'});
        }
      });

      // モーダルを閉じたら、フッターを元に戻す（他ページへの影響を避ける）
      UIkit.util.off('#modal-overflow', 'hidden');
      UIkit.util.on('#modal-overflow', 'hidden', function(){
        if(state.originalFooter) $('#modal-footer').html(state.originalFooter);
      });
    }

    function buildModalBody(row){
      let imgs = [];
      try{
        const a = JSON.parse(row.infimg || '[]');
        if(Array.isArray(a)) imgs = a.filter(Boolean);
      }catch(e){ imgs = []; }

      let slideshowHtml = '';
      if(imgs.length === 0){
        slideshowHtml = `
          <div class="uk-flex uk-flex-middle uk-flex-center uk-background-muted uk-border-rounded" style="height:320px;">
            <div class="mono small">No Image</div>
          </div>`;
      }else{
        const items = imgs.map(p=>`
          <li>
            <img src="${esc(p)}" alt="" style="width:100%;height:320px;object-fit:contain;">
          </li>`).join('');
        slideshowHtml = `
          <div uk-slideshow="ratio: 16:9; animation: fade">
            <ul class="uk-slideshow-items">${items}</ul>
            <ul class="uk-slideshow-nav uk-dotnav uk-flex-center uk-margin-small-top"></ul>
          </div>`;
      }

      const p = Math.max(0, Math.min(100, num(row.infproc)));

      const infoTable = `
        <table class="uk-table uk-table-divider uk-table-small">
          <tbody>
            <tr><th style="width:180px;">作品名</th><td>${esc(row.wname ?? '')}</td></tr>
            <tr><th>所属授業名</th><td>${esc(row.lesson ?? '')}</td></tr>
            <tr><th>教師名</th><td>${esc(row.sensei ?? '')}</td></tr>
            <tr><th>課題出す日</th><td class="mono">${esc(row.dtopen ?? '')}</td></tr>
            <tr><th>課題発表日</th><td class="mono">${esc(row.dtprsn ?? '')}</td></tr>
            <tr><th>最終更新日</th><td class="mono">${esc(row.dtlast ?? '')}</td></tr>
            <tr><th>メモ</th><td>${esc(row.wmemo ?? '')}</td></tr>
          </tbody>
        </table>`;

      const desc = (row.inftext ?? '').trim();
      const descHtml = desc ? desc : '<div class="mono small">（説明なし）</div>';

      return `
        ${slideshowHtml}
        <div class="uk-margin">
          <progress class="uk-progress" value="${p}" max="100"></progress>
          <div class="small mono">${p.toFixed(1)}%</div>
        </div>

        <h3 class="uk-heading-bullet">基本情報</h3>
        ${infoTable}

        <h3 class="uk-heading-bullet">作品説明</h3>
        <div class="uk-card uk-card-default uk-card-body uk-border-rounded">
          ${descHtml}
        </div>
      `;
    }

    function openDetail(wid){
      $.getJSON(apiUrl, {wid: wid})
        .done((res)=>{
          if(!res.ok || !res.row){
            UIkit.notification({message:'詳細情報の取得に失敗しました', status:'danger'});
            return;
          }
          const row = res.row;
          state.currentWid = Number(row.wid);
          state.currentDetail = row;

          $('#modal-title').html(esc(row.wname ?? ''));
          $('#modal-body').html(buildModalBody(row));

          // 「前/次」は現在のソート順（＋フィルター）に追従
          setModalFooterButtons(state.currentWid);

          UIkit.modal('#modal-overflow').show();
        })
        .fail(()=> UIkit.notification({message:'API通信に失敗しました', status:'danger'}));
    }

    function bindDetailButtons(){
      $('#worksGrid, #worksTbody').off('click', 'button[data-wid]');
      $('#worksGrid, #worksTbody').on('click', 'button[data-wid]', function(){
        const wid = Number($(this).attr('data-wid'));
        openDetail(wid);
      });
    }

    function load(){
      $.getJSON(apiUrl)
        .done((res)=>{
          if(!res.ok){
            UIkit.notification({message: esc(res.error || 'API error'), status:'danger'});
            return;
          }
          state.all = res.rows || [];
          rebuildFilterOptions();
          render();
          bindDetailButtons();
          setView(state.view);
        })
        .fail(()=> UIkit.notification({message:'API通信に失敗しました', status:'danger'}));
    }

    $('#btnLayoutGrid').on('click', ()=> setView('grid'));
    $('#btnLayoutTable').on('click', ()=> setView('table'));

    $('#selSortField').on('change', function(){ state.sortField = $(this).val(); render(); });
    $('#selSortDir').on('change', function(){ state.sortDir = $(this).val(); render(); });

    $('#selLesson').on('change', function(){ state.lesson = $(this).val(); render(); });
    $('#selSensei').on('change', function(){ state.sensei = $(this).val(); render(); });

    state.sortField = $('#selSortField').val();
    state.sortDir   = $('#selSortDir').val();

    window.WorksPage = {
      destroy(){
        $('#btnLayoutGrid,#btnLayoutTable,#selSortField,#selSortDir,#selLesson,#selSensei').off();
        $('#worksGrid,#worksTbody').off();
      }
    };

    load();
  })();
  </script>
</div>