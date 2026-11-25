// assets/radar.js
'use strict';

const form = document.getElementById('radarForm');
const errorBox = document.getElementById('errorBox');

const jobMeta = document.getElementById('jobMeta');
const cardsWrap = document.getElementById('cards');
const cardTemplate = document.getElementById('cardTemplate');
const emptyState = document.getElementById('emptyState');

const pillPhase = document.getElementById('pillPhase');
const pillProvider = document.getElementById('pillProvider');

const statFound = document.getElementById('statFound');
const statDedup = document.getElementById('statDedup');
const statStatus = document.getElementById('statStatus');

const btnStop = document.getElementById('btnStop');

let pollTimer = null;
let activeJobId = null;

function setError(msg){
  errorBox.hidden = !msg;
  errorBox.textContent = msg || '';
}

function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, (c) => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

function makeCard(item){
  const node = cardTemplate.content.firstElementChild.cloneNode(true);

  node.querySelector('.card-title').textContent = item.name ?? 'Senza nome';
  node.querySelector('.card-category').textContent = item.category ?? '—';

  const badgeWrap = node.querySelector('.card-badges');
  badgeWrap.innerHTML = '';
  const badge = document.createElement('span');
  badge.className = 'pill';
  badge.style.borderRadius = '999px';
  badge.style.padding = '6px 10px';
  badge.style.fontSize = '12px';
  badge.style.background = 'rgba(255,255,255,.06)';
  badge.textContent = item.provider;
  badgeWrap.appendChild(badge);

  const link = node.querySelector('.card-source');
  link.href = item.source_url || '#';
  link.textContent = item.source_label || 'Fonte';

  node.querySelector('.card-provider-id').textContent = item.provider_id ?? '—';

  node.querySelector('.btn-open-sources').addEventListener('click', () => {
    const url = item.source_url;
    if (url && url !== '#') window.open(url, '_blank', 'noopener');
  });

  return node;
}

function setStats({found=0, dedup=0, status=''}){
  statFound.textContent = String(found);
  statDedup.textContent = String(dedup);
  statStatus.textContent = status;
}

async function apiJSON(url, options){
  const res = await fetch(url, options);
  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = { ok:false, error:'Invalid JSON' }; }
  if (!res.ok){
    const msg = data?.error || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

function clearCards(){
  cardsWrap.innerHTML = '';
}

async function fetchStatus(jobId){
  return apiJSON(`./api/status.php?job_id=${encodeURIComponent(jobId)}`);
}

async function fetchResults(jobId){
  return apiJSON(`./api/results.php?job_id=${encodeURIComponent(jobId)}`);
}

function renderResults(items){
  clearCards();
  if (!items || items.length === 0){
    emptyState.hidden = false;
    return;
  }
  emptyState.hidden = true;
  const frag = document.createDocumentFragment();
  for (const it of items){
    frag.appendChild(makeCard(it));
  }
  cardsWrap.appendChild(frag);
}

function updateMeta(meta){
  const bits = [];
  if (meta.job_id) bits.push(`job_id: ${meta.job_id}`);
  if (meta.phase) bits.push(`phase: ${meta.phase}`);
  if (meta.provider) bits.push(`provider: ${meta.provider}`);
  if (meta.message) bits.push(meta.message);
  jobMeta.innerHTML = bits.join('<br/>');
  pillPhase.textContent = `phase: ${meta.phase || '—'}`;
  pillProvider.textContent = `provider: ${meta.provider || '—'}`;
}

function stopPolling(){
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
  btnStop.disabled = true;
}

async function startJob(payload){
  setError('');
  cardsWrap.innerHTML = '';
  emptyState.hidden = false;

  const data = await apiJSON('./api/start.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload),
    cache: 'no-store'
  });

  return data;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  stopPolling();

  setError('');
  activeJobId = null;

  const formData = new FormData(form);
  const payload = {
    area: formData.get('area'),
    sector: formData.get('sector'),
    radiusKm: Number(formData.get('radiusKm')),
    limit: Number(formData.get('limit')),
    provider: formData.get('provider'),
  };

  btnStop.disabled = true;
  btnStop.textContent = 'Interrompi (MVP)';

  try{
    const resp = await startJob(payload);
    if (!resp.ok) throw new Error(resp.error || 'Start job failed');

    activeJobId = resp.job_id;
    updateMeta(resp.meta || { job_id: resp.job_id, phase: 'queued', provider: payload.provider });

    setStats({found:0, dedup:0, status:'queued'});
    btnStop.disabled = false;

    pollTimer = setInterval(async () => {
      if (!activeJobId) return;

      try{
        const st = await fetchStatus(activeJobId);
        updateMeta(st.meta || {});
        setStats({
          found: st.stats?.found ?? 0,
          dedup: st.stats?.dedup ?? 0,
          status: st.status ?? 'running'
        });

        if (st.status === 'completed'){
          stopPolling();
          const results = await fetchResults(activeJobId);
          renderResults(results.items || []);
        }

        if (st.status === 'failed'){
          stopPolling();
          setError(st.error || 'Job fallito');
        }

        if (st.status === 'cancelled'){
          stopPolling();
          setError('Job interrotto (MVP).');
        }
      }catch(err){
        stopPolling();
        setError(err.message || String(err));
      }
    }, 1200);

    // immediata
    pollTimer && (await (async()=>{} )());
  }catch(err){
    setError(err.message || String(err));
  }
});

btnStop.addEventListener('click', async () => {
  if (!activeJobId) return;
  try{
    btnStop.disabled = true;
    await apiJSON('./api/review.php?action=cancel&job_id=' + encodeURIComponent(activeJobId), { method:'POST', cache:'no-store' }).catch(()=>{});
    setError('Interruzione richiesta. Attendi aggiornamento stato…');
  }catch(err){
    setError(err.message || String(err));
  }
});
