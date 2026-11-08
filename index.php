<?php
declare(strict_types=1);
require_once __DIR__.'/src/helpers.php';
$routers=allRouters(false);
$routerOptions=''; foreach($routers as $r){ $routerOptions.='<option value="'.e($r['id']).'">'.e($r['name']).' — '.e($r['host']).'</option>'; }
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#071421"><link rel="manifest" href="manifest.webmanifest?v=20260928-portrait"><link rel="apple-touch-icon" href="icons/icon-192.png">
<title>HNG PPPoE Monitor No.1</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--bg:#071421;--panel:#0c1c2c;--panel2:#0f2437;--line:#21435d;--muted:#8fa8bb;--blue:#16a4ff;--green:#22d783;--yellow:#f5bb3a;--red:#ff5b68}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top right,#0b263b 0,#071421 38%,#06121f 100%);color:#edf6fb;font-size:.86rem;min-height:100vh}
.main{padding:14px 16px 20px}.topbar,.panel,.stat{background:rgba(10,29,45,.96);border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 35px rgba(0,0,0,.16)}
.topbar{padding:11px 12px}.brand{font-weight:900;font-size:1.05rem;line-height:1}.brand small{display:block;color:var(--muted);font-weight:500;font-size:.66rem;margin-top:3px}.search{max-width:560px}.stat{padding:13px 15px}.stat-icon{width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.25rem;margin-bottom:7px}.stat-title{color:#a7bbca;font-size:.73rem;text-transform:uppercase;letter-spacing:.06em}.stat-value{font-size:1.7rem;font-weight:900;line-height:1.1}.muted{color:var(--muted)}
.section-title{font-size:1rem;font-weight:900}.section-head{padding:13px 14px}.table-wrap{max-height:calc(100vh - 300px);min-height:260px;overflow:auto}.table{--bs-table-bg:transparent;--bs-table-color:#dbe7ef;--bs-table-border-color:#234258;margin:0}.table thead th{position:sticky;top:0;background:#0b1d2d;color:#cfe0ea;z-index:2;font-size:.74rem;white-space:nowrap}.table td{vertical-align:middle;white-space:nowrap}.table-hover>tbody>tr:hover>*{--bs-table-bg:#112b40;color:#fff}.user-name{font-weight:800}.sub{font-size:.68rem;color:#7891a5}.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}.online{background:var(--green);box-shadow:0 0 0 4px rgba(34,215,131,.12)}.offline{background:var(--red);box-shadow:0 0 0 4px rgba(255,91,104,.12)}.warning{color:var(--yellow)}.danger{color:var(--red)}.duration{font-weight:900;color:#ffcf66}.duration-old{color:#ff7a7a}
.form-control,.form-select{background:#081722!important;border-color:#2a4b62!important;color:#e9f3f8!important}.form-control:focus,.form-select:focus{border-color:#38a9ff!important;box-shadow:0 0 0 .2rem rgba(56,169,255,.12)}.modal-content{background:#0b1c2a;border:1px solid #28495f;color:#eef5f9}.btn-primary{background:#087be5;border-color:#087be5}.badge-soft{border:1px solid #22506e;background:#0b263a;color:#bcd2e1}.error-box{font-size:.72rem}.footer{color:#678096;font-size:.7rem}.spinner{width:1rem;height:1rem}@media(max-width:900px){.main{padding:8px}.table-wrap{max-height:none}.topbar{gap:8px}.search{max-width:none}.mobile-stack>section{order:initial!important}.mobile-stack>section:nth-child(2){margin-top:0}.mobile-stack{display:flex;flex-direction:column}.mobile-stack>section:first-child{order:1!important}.mobile-stack>section:nth-child(2){order:2!important}}

/* Responsive phone layout; desktop keeps its monitoring tables. */
html{color-scheme:dark}
body{min-height:100dvh}
.main{padding-top:max(14px,env(safe-area-inset-top));padding-right:max(16px,env(safe-area-inset-right));padding-bottom:max(20px,env(safe-area-inset-bottom));padding-left:max(16px,env(safe-area-inset-left))}
.mobile-stack>section,.panel,.brand>div,.stat .d-flex>div{min-width:0}
.stat-icon{flex-shrink:0}
.brand{line-height:1.25}
.topbar-actions,.ping-meta{flex-wrap:wrap}
.ping-heading{gap:12px;flex-wrap:wrap}
.ping-meta{min-width:0;max-width:100%}
#pingSourceLabel{white-space:normal;overflow-wrap:anywhere;text-align:left;line-height:1.4}
.ping-controls{padding:0 14px 12px}
#pingRouter{min-width:0}
#pingNow{flex-shrink:0;min-width:44px}
.error-box,.footer{overflow-wrap:anywhere}
.form-control::placeholder{color:var(--muted);opacity:1}
@media(max-width:767.98px){
  body{font-size:.9rem}
  .main{padding: max(10px,env(safe-area-inset-top)) max(10px,env(safe-area-inset-right)) max(18px,env(safe-area-inset-bottom)) max(10px,env(safe-area-inset-left))}
  .topbar{padding:12px;gap:12px!important;align-items:flex-start!important}
  .brand{font-size:1rem}
  .topbar-actions{width:100%;justify-content:space-between}
  .stat{padding:14px}
  .stat .muted{line-height:1.5}
  .section-head{padding:14px}
  .section-title{font-size:1rem}
  .btn,.form-select,.input-group-sm>.form-control{min-height:44px}
  .form-select,.input-group-sm>.form-control{font-size:16px}
  .table-wrap{max-height:60vh!important;max-height:60dvh!important;min-height:0;overflow:auto;border-radius:0 0 12px 12px}
  .monitor-table{width:100%;font-size:.78rem}
  .monitor-table th,.monitor-table td{padding:8px 10px;white-space:nowrap}
  .monitor-table thead th{font-size:.72rem}
  .monitor-table tbody tr:nth-child(even){background:rgba(15,36,55,.5)}
  .monitor-table .sub{font-size:.68rem;max-width:200px;white-space:normal}
  .ping-table svg{width:100px;height:28px}
  .ping-meta{width:100%;justify-content:space-between}
  .ping-meta #pingSourceLabel{flex:1}
  .ping-meta #pingTs{flex-shrink:0}
  .footer{font-size:.75rem;line-height:1.6;padding:0 8px}
}
</style>
<link href="portal.css?v=20261103c" rel="stylesheet">
<script src="pppoe-split.js?v=20261106b"></script><style>.pppoe-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.pppoe-tabs .btn{min-height:36px;padding:6px 12px}.pppoe-tabs [aria-pressed="true"]{background:#183e55;border-color:#59bde8;color:#fff}.pppoe-tabs b{margin-left:6px}.pppoe-tabs [data-pppoe-tab="blocked"][aria-pressed="true"]{background:#4d3917;border-color:#c99b39}@media(max-width:767px){.pppoe-tabs .btn{min-height:44px;flex:1}}</style><style>.pppoe-blocked-summary,.pppoe-tabs{display:none!important}</style></head>
<body>
<main class="main">
<div class="topbar d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap">
  <div class="brand d-flex align-items-center gap-2"><i class="bi bi-speedometer2 text-info fs-4"></i><div>HNG PPPoE Monitor <small>Offline & Ping · Versi No.1</small></div></div>
  <div class="topbar-actions d-flex align-items-center gap-2"><a class="btn btn-sm btn-outline-info" href="history.php"><i class="bi bi-lock me-1"></i>Riwayat & Detail · Login</a><button id="installApp" class="btn btn-sm btn-primary" hidden><i class="bi bi-download me-1"></i>Install App</button><span class="badge badge-soft"><i class="bi bi-circle-fill text-success me-1"></i> Live 1 detik</span></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12"><div class="stat"><div class="d-flex align-items-center gap-3"><div class="stat-icon mb-0" style="background:linear-gradient(135deg,#c78800,#f5bb3a)"><i class="bi bi-person-x-fill"></i></div><div><div class="stat-title">PPPoE Offline</div><div class="stat-value warning" id="inactiveCount">0</div><div class="muted small">Pelanggan offline di luar akun diblokir</div></div></div></div></div><div class="col-12 col-md-6 pppoe-blocked-summary"><div class="stat"><div class="d-flex align-items-center gap-3"><div class="stat-icon mb-0" style="background:#805c15"><i class="bi bi-person-lock"></i></div><div><div class="stat-title">PPPoE Diblokir</div><div class="stat-value warning" id="blockedCount">0</div><div class="muted small">Profil Blokir / Isolir atau akun dinonaktifkan</div></div></div></div></div>
</div>

<div class="row g-3 mobile-stack">
<section class="col-12 col-xxl-7 order-1"><div class="panel h-100"><div class="section-head"><div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div><div class="section-title"><i class="bi bi-person-x-fill text-danger me-2"></i>PPPoE Offline</div><div class="muted small">Pelanggan yang sedang tidak terhubung</div></div><span class="badge text-bg-danger" id="inactiveBadge">0</span></div><div class="pppoe-tabs" role="group" aria-label="Kategori PPPoE"><button type="button" class="btn btn-sm btn-outline-info" data-pppoe-tab="offline" aria-pressed="true">Offline <b id="offlineTabCount">0</b></button><button type="button" class="btn btn-sm btn-outline-warning" data-pppoe-tab="blocked" aria-pressed="false">Diblokir <b id="blockedTabCount">0</b></button></div><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="inactiveSearch" aria-label="Cari PPPoE nonaktif" class="form-control" placeholder="Cari username, router, profile, reason..."></div></div><div class="table-wrap"><table class="table table-sm table-hover align-middle monitor-table inactive-table"><thead><tr><th>#</th><th>Router</th><th>Nama</th><th>Profile</th><th>Last Logged Out</th><th>Durasi Offline</th><th>Reason</th><th>Status</th></tr></thead><tbody id="inactiveBody"><tr><td colspan="8" class="text-center muted py-5">Memuat...</td></tr></tbody></table></div></div></section>
<section class="col-12 col-xxl-5 order-2"><div class="panel h-100"><div class="section-head ping-heading d-flex justify-content-between align-items-center"><div><div class="section-title"><i class="bi bi-graph-up-arrow text-info me-2"></i>Latency Realtime</div><div class="muted small">9 target · rekam 5 detik · tampilan diperbarui 1 detik</div></div><div class="ping-meta d-flex align-items-center gap-2"><span class="badge badge-soft" id="pingSourceLabel">Pilih MikroTik</span><span class="badge badge-soft" id="pingTs">—</span></div></div><div class="ping-controls d-flex gap-2 mt-2"><select aria-label="Pilih MikroTik untuk latency" id="pingRouter" class="form-select form-select-sm"></select><button class="btn btn-sm btn-outline-info" id="pingNow" aria-label="Perbarui latency"><i class="bi bi-arrow-clockwise"></i></button></div><div class="table-wrap" style="max-height:520px"><table class="table table-sm align-middle monitor-table ping-table"><thead><tr><th>Platform</th><th>Host</th><th>Ping</th><th>Grafik</th><th>Status</th></tr></thead><tbody id="pingBody"><tr><td colspan="5" class="text-center muted py-5">Pilih MikroTik untuk memulai.</td></tr></tbody></table></div></div></section>
</div>
<div id="errors" class="mt-3"></div>
<div class="footer text-center mt-3">HNG Network Monitoring · PPPoE Offline + Ping dari MikroTik · XAMPP · Auto refresh 1 detik</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let pppoeCategory="offline";
const state={inactive:[],loading:false,pingLoading:false,ping:{}};
const inactiveSearchEl=document.getElementById('inactiveSearch');
const pingRouterEl=document.getElementById('pingRouter');
function esc(v){return String(v??'').replace(/[&<>'"]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[s]));}
function norm(x){return String(x??'').toLowerCase();}
function hayInactive(r){return [r.router,r.name,r.address,r.profile,r.service,r.last_logged_out,r.last_disconnect_reason,r.comment].map(norm).join(' ');}
function parseLoggedOut(v){if(!v)return null;const raw=String(v).trim();let t=Date.parse(raw.replace(' ','T'));if(Number.isFinite(t))return t;const m=raw.match(/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/);if(m){const d=new Date(+m[1],+m[2]-1,+m[3],+(m[4]||0),+(m[5]||0),+(m[6]||0));if(!Number.isNaN(d.getTime()))return d.getTime();}return null;}
function durationText(seconds){if(seconds===null||seconds<0||!Number.isFinite(seconds))return '—';seconds=Math.floor(seconds);const d=Math.floor(seconds/86400);seconds%=86400;const h=Math.floor(seconds/3600);seconds%=3600;const m=Math.floor(seconds/60);const s=seconds%60;const p=[];if(d)p.push(d+'h');if(h||d)p.push(String(h).padStart(2,'0')+'j');p.push(String(m).padStart(2,'0')+'m');p.push(String(s).padStart(2,'0')+'d');return p.join(' ');}
function inactiveSeconds(r){const t=parseLoggedOut(r.last_logged_out);return t?Math.max(0,(Date.now()-t)/1000):null;}
function renderInactive(){const q=norm(inactiveSearchEl?.value||'').trim();const groups=HngPppoeSplit.split(state.inactive);const pool=groups[pppoeCategory];const ina=pool.filter(r=>!q||hayInactive(r).includes(q)).slice();document.getElementById('offlineTabCount').textContent=groups.offline.length;document.getElementById('blockedTabCount').textContent=groups.blocked.length;document.getElementById('blockedCount').textContent=groups.blocked.length;ina.sort((a,b)=>{const da=inactiveSeconds(a),db=inactiveSeconds(b);if(da===null&&db===null)return String(a.name||'').localeCompare(String(b.name||''));if(da===null)return 1;if(db===null)return -1;return db-da;});document.getElementById('inactiveCount').textContent=groups.offline.length;document.getElementById('inactiveBadge').textContent=ina.length+(q?' / '+pool.length:'');document.getElementById('inactiveBody').innerHTML=ina.length?ina.map((r,i)=>{const sec=inactiveSeconds(r);const cls=sec!==null&&sec>=86400?'duration-old':'duration';return `<tr><td data-label="No.">${i+1}</td><td data-label="Router">${esc(r.router)}</td><td data-label="Nama"><div class="user-name">${esc(r.name)}</div>${r.comment?`<div class="sub">${esc(r.comment)}</div>`:''}</td><td data-label="Profile">${esc(r.profile||'-')}</td><td data-label="Last Logged Out">${esc(r.last_logged_out||'-')}</td><td data-label="Durasi Offline" class="${cls}">${esc(durationText(sec))}</td><td data-label="Reason">${esc(r.last_disconnect_reason||'-')}</td><td data-label="Status">${HngPppoeSplit.isBlocked(r)?'<span class="badge text-bg-warning">DIBLOKIR</span>':'<span class="badge text-bg-danger">OFFLINE</span>'}</td></tr>`}).join(''):'<tr><td colspan="8" class="text-center muted py-5">Tidak ada akun pada kategori ini yang cocok.</td></tr>';}
function spark(points){const w=120,h=30,pad=2;const valid=points.filter(p=>p.ms!==null);if(!valid.length)return '';const max=Math.max(1,...valid.map(p=>Number(p.ms)))*1.15;let paths=[],run=[];points.forEach((p,i)=>{if(p.ms===null){if(run.length)paths.push(run.join(' '));run=[];return;}const x=pad+(w-pad*2)*(i/Math.max(1,points.length-1));const y=h-pad-(h-pad*2)*(Number(p.ms)/max);run.push(`${x.toFixed(1)},${y.toFixed(1)}`);});if(run.length)paths.push(run.join(' '));return `<svg viewBox="0 0 ${w} ${h}" width="120" height="30" aria-hidden="true">${paths.map(p=>`<polyline fill="none" stroke="#35b9ff" stroke-width="1.5" points="${p}"/>`).join('')}</svg>`;}
function renderPing(){const rows=state.ping.targets||[];document.getElementById('pingBody').innerHTML=rows.length?rows.map(r=>{const val=r.ms==null?'—':Number(r.ms).toFixed(1)+' ms';const online=r.status==='ONLINE',offline=r.status==='OFFLINE';return `<tr><td data-label="Platform"><b>${esc(r.name)}</b></td><td data-label="Host" class="sub">${esc(r.host)}</td><td data-label="Ping" class="fw-bold">${val}</td><td data-label="Grafik">${spark(r.history||[])}</td><td data-label="Status"><span class="badge ${online?'text-bg-success':offline?'text-bg-danger':'text-bg-secondary'}">${online?'ONLINE':offline?'OFFLINE':'DATA BELUM TERSEDIA'}</span></td></tr>`}).join(''):'<tr><td colspan="5" class="text-center muted py-5">Pilih MikroTik untuk memulai.</td></tr>';const ts=Math.max(0,...rows.map(r=>r.ts||0));document.getElementById('pingTs').textContent=ts?new Date(ts*1000).toLocaleTimeString():'Menunggu sampel';}
async function loadInactive(){if(state.loading)return;state.loading=true;try{const r=await fetch('api.php?action=pppoe&_='+Date.now(),{cache:'no-store'});const t=await r.text();let j;try{j=JSON.parse(t)}catch(e){throw new Error('Server mengirim respons bukan JSON: '+t.slice(0,180))}if(!j.ok)throw new Error(j.error||'Gagal mengambil data');state.inactive=j.inactive||[];renderInactive();document.getElementById('errors').innerHTML=(j.errors||[]).length?`<div class="alert alert-warning error-box">${j.errors.map(e=>`Router <b>${esc(e.router)}</b>: ${esc(e.error)}`).join('<br>')}</div>`:'';}catch(e){document.getElementById('errors').innerHTML=`<div class="alert alert-danger error-box">${esc(e.message)}</div>`}finally{state.loading=false}}
async function loadRouters(){try{const r=await fetch('ping.php?action=routers&_='+Date.now(),{cache:'no-store'});const j=await r.json();if(!j.ok)throw new Error(j.error);const old=localStorage.getItem('ionnet_ping_router')||'';pingRouterEl.innerHTML=j.routers.length?j.routers.map(x=>`<option value="${x.id}">${esc(x.name)} — ${esc(x.host)}</option>`).join(''):'<option value="">Belum ada MikroTik</option>';if(old&&j.routers.some(x=>String(x.id)===old))pingRouterEl.value=old;else if(j.routers.length)pingRouterEl.value=String(j.routers[0].id);await loadPing();}catch(e){document.getElementById('pingBody').innerHTML=`<tr><td colspan="5" class="text-center danger py-5">${esc(e.message)}</td></tr>`}}
async function loadPing(){if(state.pingLoading||!pingRouterEl.value)return;state.pingLoading=true;const id=pingRouterEl.value;localStorage.setItem('ionnet_ping_router',id);document.getElementById('pingSourceLabel').textContent=pingRouterEl.options[pingRouterEl.selectedIndex]?.text||'—';try{const fd=new FormData();fd.append('router_id',id);const response=await fetch('ping.php',{method:'POST',body:fd,cache:'no-store'});const j=await response.json();if(id!==pingRouterEl.value)return;if(!j.ok)throw new Error(j.error||'Data ping belum tersedia');state.ping.targets=j.targets||[];renderPing();}catch(error){if(id===pingRouterEl.value)document.getElementById('pingBody').innerHTML=`<tr><td colspan="5" class="text-center danger py-5">${esc(error.message)}</td></tr>`;}finally{state.pingLoading=false}}
let deferredInstall=null;
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();deferredInstall=e;const b=document.getElementById('installApp');b.hidden=false;});
document.getElementById('installApp')?.addEventListener('click',async()=>{if(!deferredInstall)return;deferredInstall.prompt();await deferredInstall.userChoice;deferredInstall=null;document.getElementById('installApp').hidden=true;});
window.addEventListener('appinstalled',()=>{const b=document.getElementById('installApp');if(b)b.hidden=true;});

// The manifest sets portrait at launch; also request it for existing installs.
function preferPortrait(){
  if(window.matchMedia('(display-mode: standalone)').matches && screen.orientation?.lock){
    screen.orientation.lock('portrait-primary').catch(()=>{});
  }
}
preferPortrait();
window.addEventListener('pageshow',preferPortrait);
if('serviceWorker' in navigator){
  let refreshing=false;
  const hadController=Boolean(navigator.serviceWorker.controller);
  navigator.serviceWorker.addEventListener('controllerchange',()=>{
    if(hadController&&!refreshing){refreshing=true;window.location.reload();}
  });
  window.addEventListener('load',()=>navigator.serviceWorker.register('./sw.js?v=20261103-history',{updateViaCache:'none'}).catch(()=>{}));
}
document.querySelectorAll('[data-pppoe-tab]').forEach(button=>button.addEventListener('click',()=>{pppoeCategory=button.dataset.pppoeTab;document.querySelectorAll('[data-pppoe-tab]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));renderInactive();}));
inactiveSearchEl?.addEventListener('input',renderInactive);pingRouterEl.addEventListener('change',loadPing);document.getElementById('pingNow').addEventListener('click',loadPing);setInterval(renderInactive,1000);loadRouters();loadInactive();setInterval(loadInactive,1000);setInterval(loadPing,1000);
</script>
</body></html>
