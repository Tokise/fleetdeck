/* =====================================================================
   FleetDeck — frontend logic (PHP/MySQL backend edition)
   ---------------------------------------------------------------------
   Same UI and render functions as the original single-file demo, but
   the data layer now talks to api/records.php and api/archive.php
   instead of localStorage. window.SESSION (username/role/label) is
   set inline by index.php before this file loads.
     1. API helpers (apiGet/apiPost/apiPut/apiDelete)
     2. Local cache of the last-fetched lists (CACHE) + loadAll()
     3. Activity log + toast helpers
     4. Routing (navigate)
     5. Render functions, one per module (read from CACHE, unchanged look)
     6. Bar chart drawing (drawBarChart)
     7. Archive / restore / purge / clear (calls api/archive.php)
     8. Modal / form system (FORMS) — POST to create, PUT to edit
     9. Init
===================================================================== */

const isAdmin = () => window.SESSION && window.SESSION.role === 'admin';

/* ---------------------------------------------------------------------
   1) API HELPERS
--------------------------------------------------------------------- */
async function apiGet(url){
  const r = await fetch(url);
  if(!r.ok) throw new Error((await r.json().catch(()=>({}))).error || 'Request failed');
  return r.json();
}
async function apiSend(url, method, body){
  const r = await fetch(url, {
    method,
    headers:{'Content-Type':'application/json'},
    body: body !== undefined ? JSON.stringify(body) : undefined
  });
  const data = await r.json().catch(()=>({}));
  if(!r.ok) throw new Error(data.error || 'Request failed');
  return data;
}

/* ---------------------------------------------------------------------
   2) LOCAL CACHE — populated from the API, read synchronously by the
   render functions below (same pattern the localStorage version used).
--------------------------------------------------------------------- */
const CACHE = { vehicles:[], reservations:[], drivers:[], fuel:[], routes:[], archive:[], activity:[] };

const RESOURCE_URL = {
  vehicles:'api/records.php?type=vehicles',
  reservations:'api/records.php?type=reservations',
  drivers:'api/records.php?type=drivers',
  fuel:'api/records.php?type=fuel',
  routes:'api/records.php?type=routes',
};

async function refresh(key){
  if(key==='archive'){ CACHE.archive = await apiGet('api/archive.php'); return; }
  if(key==='activity'){ CACHE.activity = await apiGet('api/activity.php'); return; }
  CACHE[key] = await apiGet(RESOURCE_URL[key]);
}
async function loadAll(){
  const keys = ['vehicles','reservations','drivers','fuel','routes','archive','activity'];
  await Promise.all(keys.map(refresh));
}

/* ---------------------------------------------------------------------
   3) TOAST + ACTIVITY
--------------------------------------------------------------------- */
let toastTimer;
function toast(msg, type='ok'){
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'show ' + type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>el.classList.remove('show'), 2400);
}

/* ---------------------------------------------------------------------
   4) ROUTING
--------------------------------------------------------------------- */
const VIEWS = ['dashboard','fvm','vrds','dtpm','fms','tcao','rpo','mfca','archive'];

function navigate(){
  let view = (location.hash || '#dashboard').slice(1);
  if(!VIEWS.includes(view)) view = 'dashboard';
  VIEWS.forEach(v=>{
    document.getElementById('view-'+v).classList.toggle('active', v===view);
  });
  document.querySelectorAll('.nav-item').forEach(n=>{
    n.classList.toggle('active', n.dataset.view===view);
  });
  renderView(view);
}
window.addEventListener('hashchange', navigate);

function renderView(view){
  if(view==='dashboard') renderDashboard();
  if(view==='fvm') renderVehicles();
  if(view==='vrds') renderReservations();
  if(view==='dtpm') renderDrivers();
  if(view==='fms') renderFuel();
  if(view==='tcao') renderTCAO();
  if(view==='rpo') renderRoutes();
  if(view==='archive') renderArchive();
}

/* ---------------------------------------------------------------------
   SMALL SHARED HELPERS
--------------------------------------------------------------------- */
function statusClass(s){ return 'status-' + String(s).replace(/\s+/g,''); }
function pill(s){ return `<span class="status-pill ${statusClass(s)}">${s}</span>`; }
function fmtMoney(n){ return '₱' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
// Only admins get edit/archive buttons in each row — user role gets an empty cell.
function rowActions(formKey, typeKey, id, rerenderFnName){
  if(!isAdmin()) return '';
  return `<div class="row-actions">
    <button class="icon-btn" onclick="startEdit('${formKey}','${typeKey}',${id})" title="Edit">✎</button>
    <button class="icon-btn del" onclick="archiveItem('${typeKey}',${id}, ${rerenderFnName})" title="Move to archive">✕</button>
  </div>`;
}
function emptyRow(colspan, label){
  return `<tr><td colspan="${colspan}"><div class="empty-state"><div class="big">Nothing here yet</div>${label}</div></td></tr>`;
}

/* ---------------------------------------------------------------------
   5) RENDER FUNCTIONS — one per module (unchanged visuals, data from CACHE)
--------------------------------------------------------------------- */
function renderDashboard(){
  const vehicles = CACHE.vehicles, reservations = CACHE.reservations, drivers = CACHE.drivers, fuel = CACHE.fuel, routes = CACHE.routes;

  const activeVehicles = vehicles.filter(v=>v.status==='Active').length;
  const inMaint = vehicles.filter(v=>v.status==='Maintenance').length;
  const openDispatch = reservations.filter(r=>['Pending','Approved','Dispatched'].includes(r.status)).length;
  const fuelSpend = fuel.reduce((s,f)=>s + Number(f.liters)*Number(f.price_per_liter), 0);
  const avgSafety = drivers.length ? Math.round(drivers.reduce((s,d)=>s+Number(d.safety),0)/drivers.length) : 0;
  const optimizedRoutes = routes.filter(r=>r.status==='Optimized').length;

  const kpis = [
    {label:'Active vehicles', value:activeVehicles, sub:`${vehicles.length} total in fleet`, icon:'🚚', link:'#fvm'},
    {label:'In maintenance', value:inMaint, sub:`${vehicles.filter(v=>v.status==='Idle').length} idle`, icon:'🔧', link:'#fvm'},
    {label:'Open dispatches', value:openDispatch, sub:`${reservations.length} total reservations`, icon:'📋', link:'#vrds'},
    {label:'Fuel spend logged', value:fmtMoney(fuelSpend), sub:`${fuel.length} entries`, icon:'⛽', link:'#fms'},
    {label:'Avg driver safety score', value:avgSafety, sub:`${drivers.length} drivers tracked`, icon:'🛡️', link:'#dtpm'},
    {label:'Routes optimized', value:optimizedRoutes, sub:`${routes.length} routes planned`, icon:'🗺️', link:'#rpo'},
    {label:'Fleet size', value:vehicles.length, sub:'Trucks, vans, buses, cars', icon:'🚛', link:'#fvm'},
    {label:'Avg odometer', value:vehicles.length?Math.round(vehicles.reduce((s,v)=>s+Number(v.odometer),0)/vehicles.length).toLocaleString():0, sub:'km across active fleet', icon:'📈', link:'#fvm'},
  ];
  document.getElementById('kpiGrid').innerHTML = kpis.map(k=>`
    <div class="kpi${k.link?' linked':''}" ${k.link?`onclick="location.hash='${k.link}'"`:''}>
      <div class="kpi-icon">${k.icon}</div>
      <div class="kpi-value">${k.value}</div>
      <div class="kpi-sub">${k.label}</div>
    </div>`).join('');

  const last7 = fuel.slice(-7).map(f=>({label:String(f.date).slice(5), value:Number(f.liters)*Number(f.price_per_liter)}));
  drawBarChart('fuelChart', last7, '--amber');

  document.getElementById('activityList').innerHTML = CACHE.activity.length ? CACHE.activity.map(a=>`
    <li><span class="dot"></span><div><div>${a.text}</div></div><span class="t">${new Date(a.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</span></li>
  `).join('') : '<li>No activity logged yet.</li>';
}

function drawBarChart(canvasId, data, accentVar){
  const canvas = document.getElementById(canvasId);
  if(!canvas) return;

  const dpr = window.devicePixelRatio || 1;
  const w = canvas.clientWidth || 400, h = 190;
  canvas.width = w*dpr; canvas.height = h*dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr,dpr);
  ctx.clearRect(0,0,w,h);

  if(!data.length){
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-faint');
    ctx.font = '13px Inter'; ctx.fillText('No data yet — add entries to see this chart.', 10, h/2);
    return;
  }

  const accent = getComputedStyle(document.documentElement).getPropertyValue(accentVar).trim();
  const max = Math.max(...data.map(d=>d.value)) * 1.15 || 1;
  const padBottom = 26, padTop = 10;
  const barW = (w - 20) / data.length * 0.55;
  const gap = (w - 20) / data.length;

  data.forEach((d,i)=>{
    const barH = ((h-padBottom-padTop) * d.value/max);
    const x = 10 + i*gap + (gap-barW)/2;
    const y = h - padBottom - barH;

    const grad = ctx.createLinearGradient(0,y,0,h-padBottom);
    grad.addColorStop(0, accent);
    grad.addColorStop(1, accent + '33');
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(x,y,barW,barH,[4,4,0,0]) : ctx.rect(x,y,barW,barH);
    ctx.fill();

    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-faint');
    ctx.font = '10.5px Inter'; ctx.textAlign='center';
    ctx.fillText(d.label, x+barW/2, h-8);
  });
  ctx.textAlign='left';
}

function renderVehicles(filter=''){
  const items = CACHE.vehicles.filter(v =>
    !filter || (v.plate+v.make+v.model).toLowerCase().includes(filter.toLowerCase())
  );
  const html = `
    <table>
      <thead><tr><th>Plate No.</th><th>Type</th><th>Make / Model</th><th>Year</th><th>Odometer (km)</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(v=>`
          <tr>
            <td class="strong">${v.plate}</td>
            <td>${v.type}</td>
            <td>${v.make} ${v.model}</td>
            <td>${v.year}</td>
            <td>${Number(v.odometer).toLocaleString()}</td>
            <td>${pill(v.status)}</td>
            <td>${rowActions('vehicle', 'vehicles', v.id, 'renderVehicles')}</td>
          </tr>`).join('') : emptyRow(7,'Add your first vehicle to start the registry.')}
      </tbody>
    </table>`;
  document.getElementById('vehiclesTable').innerHTML = html;
}

function renderReservations(){
  const items = CACHE.reservations;
  const html = `
    <table>
      <thead><tr><th>Vehicle</th><th>Requester</th><th>Purpose</th><th>Pickup → Destination</th><th>Date</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(r=>`
          <tr>
            <td class="strong">${r.vehicle||'—'}</td>
            <td>${r.requester}</td>
            <td>${r.purpose}</td>
            <td>${r.pickup} → ${r.destination}</td>
            <td>${r.date}</td>
            <td>${pill(r.status)}</td>
            <td>${rowActions('reservation', 'reservations', r.id, 'renderReservations')}</td>
          </tr>`).join('') : emptyRow(7,'Create a reservation to dispatch a vehicle.')}
      </tbody>
    </table>`;
  document.getElementById('reservationsTable').innerHTML = html;
}

function renderDrivers(){
  const items = CACHE.drivers;
  const html = `
    <table>
      <thead><tr><th>Driver</th><th>License No.</th><th>Phone</th><th>Trips</th><th>On-time %</th><th>Safety score</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(d=>`
          <tr>
            <td class="strong">${d.name}</td>
            <td>${d.license}</td>
            <td>${d.phone}</td>
            <td>${d.trips}</td>
            <td>${d.on_time}%</td>
            <td>${d.safety}</td>
            <td>${pill(d.status)}</td>
            <td>${rowActions('driver', 'drivers', d.id, 'renderDrivers')}</td>
          </tr>`).join('') : emptyRow(8,'Add a driver to begin tracking performance.')}
      </tbody>
    </table>`;
  document.getElementById('driversTable').innerHTML = html;
}

function renderFuel(){
  const items = CACHE.fuel;
  const html = `
    <table>
      <thead><tr><th>Vehicle</th><th>Date</th><th>Liters</th><th>Price / L</th><th>Total cost</th><th>Odometer</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(f=>`
          <tr>
            <td class="strong">${f.vehicle||'—'}</td>
            <td>${f.date}</td>
            <td>${f.liters}</td>
            <td>${fmtMoney(f.price_per_liter)}</td>
            <td>${fmtMoney(Number(f.liters)*Number(f.price_per_liter))}</td>
            <td>${Number(f.odometer).toLocaleString()}</td>
            <td>${rowActions('fuel', 'fuel', f.id, 'renderFuel')}</td>
          </tr>`).join('') : emptyRow(7,'Log a fuel purchase to start tracking spend.')}
      </tbody>
    </table>`;
  document.getElementById('fuelTable').innerHTML = html;
}

function renderTCAO(){
  const fuel = CACHE.fuel;
  const byVehicle = {};
  fuel.forEach(f=>{
    const cost = Number(f.liters)*Number(f.price_per_liter);
    byVehicle[f.vehicle] = (byVehicle[f.vehicle]||0) + cost;
  });
  const rows = Object.entries(byVehicle).map(([label,value])=>({label,value}));
  drawBarChart('costChart', rows, '--teal');

  const avg = rows.length ? rows.reduce((s,r)=>s+r.value,0)/rows.length : 0;
  const flagged = rows.filter(r=>r.value > avg).sort((a,b)=>b.value-a.value);
  document.getElementById('flagList').innerHTML = rows.length ? `
    <div class="flag-row" style="border-bottom:1px solid var(--border);padding-bottom:10px;margin-bottom:4px;">
      <span style="color:var(--text-faint)">Fleet average fuel spend</span><span class="strong">${fmtMoney(avg)}</span>
    </div>
    ${flagged.length ? flagged.map(r=>`
      <div class="flag-row"><span>${r.label}</span><span style="color:var(--red);font-weight:600">${fmtMoney(r.value)} · above average</span></div>
    `).join('') : '<div class="flag-row"><span style="color:var(--text-faint)">No vehicle is currently above the fleet average.</span></div>'}
  ` : '<div class="empty-state"><div class="big">No cost data yet</div>Log fuel entries in FMS to see this breakdown.</div>';
}

function renderRoutes(){
  const items = CACHE.routes;
  const html = `
    <table>
      <thead><tr><th>Route</th><th>Origin → Destination</th><th>Distance (km)</th><th>Est. duration</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(r=>`
          <tr>
            <td class="strong">${r.name}</td>
            <td>${r.origin} → ${r.destination}</td>
            <td>${r.distance}</td>
            <td>${r.duration}</td>
            <td>${pill(r.status)}</td>
            <td>${rowActions('route', 'routes', r.id, 'renderRoutes')}</td>
          </tr>`).join('') : emptyRow(6,'Add a route to plan and track it here.')}
      </tbody>
    </table>`;
  document.getElementById('routesTable').innerHTML = html;
}

const ARCHIVE_META = {
  vehicles:{ label:'Vehicle', cols:(r)=> [r.plate, `${r.make} ${r.model}`, r.status] },
  reservations:{ label:'Reservation', cols:(r)=> [r.vehicle||'—', r.purpose, r.status] },
  drivers:{ label:'Driver', cols:(r)=> [r.name, r.license, r.status] },
  fuel:{ label:'Fuel entry', cols:(r)=> [r.vehicle||'—', r.date, fmtMoney(Number(r.liters||0)*Number(r.price_per_liter||0))] },
  routes:{ label:'Route', cols:(r)=> [r.name, `${r.origin} → ${r.destination}`, r.status] },
};

function renderArchive(){
  const items = CACHE.archive;
  const html = `
    <table>
      <thead><tr><th>Type</th><th>Summary</th><th>Detail</th><th>Status</th><th>Archived</th><th></th></tr></thead>
      <tbody>
        ${items.length ? items.map(a=>{
          const meta = ARCHIVE_META[a.type] || { label:a.type, cols:()=>['—','—','—'] };
          const [c1,c2,c3] = meta.cols(a.data);
          return `
          <tr>
            <td>${meta.label}</td>
            <td class="strong">${c1 ?? '—'}</td>
            <td>${c2 ?? '—'}</td>
            <td>${c3 ? pill(c3) : '—'}</td>
            <td>${new Date(a.archived_at).toLocaleString()}</td>
            <td>${isAdmin() ? `<div class="row-actions">
                <button class="icon-btn" onclick="restoreItem(${a.id})" title="Restore">↺</button>
                <button class="icon-btn del" onclick="purgeItem(${a.id})" title="Delete permanently">✕</button>
              </div>` : ''}</td>
          </tr>`;
        }).join('') : emptyRow(6,'Records removed from other modules will show up here.')}
      </tbody>
    </table>`;
  document.getElementById('archiveTable').innerHTML = html;
}

/* ---------------------------------------------------------------------
   7) ARCHIVE / RESTORE / PURGE / CLEAR — calls api/records.php (DELETE)
   and api/archive.php (restore/purge/clear actions). Destructive-only
   actions (purge, clear) go through the in-page confirm modal, since
   browser confirm()/alert() popups don't reliably fire in every host.
--------------------------------------------------------------------- */
async function archiveItem(typeKey, id, rerender){
  if(!isAdmin()){ toast('Only admin can archive records.', 'warn'); return; }
  try{
    await apiSend(`api/records.php?type=${typeKey}&id=${id}`, 'DELETE');
    await Promise.all([refresh(typeKey), refresh('archive'), refresh('activity')]);
    rerender();
    renderDashboard();
    toast('Moved to archive.', 'warn');
  }catch(e){ toast(e.message, 'warn'); }
}

async function restoreItem(archiveId){
  if(!isAdmin()){ toast('Only admin can restore records.', 'warn'); return; }
  try{
    const entry = CACHE.archive.find(a=>a.id===archiveId);
    await apiSend('api/archive.php', 'POST', {action:'restore', id:archiveId});
    await Promise.all([refresh('archive'), refresh('activity'), entry ? refresh(entry.type) : Promise.resolve()]);
    renderArchive();
    renderDashboard();
    toast('Restored.', 'ok');
  }catch(e){ toast(e.message, 'warn'); }
}

function purgeItem(archiveId){
  if(!isAdmin()){ toast('Only admin can delete records.', 'warn'); return; }
  confirmAction('Permanently delete this archived record? This cannot be undone.', async ()=>{
    try{
      await apiSend('api/archive.php', 'POST', {action:'purge', id:archiveId});
      await refresh('archive');
      renderArchive();
      toast('Deleted permanently.', 'warn');
    }catch(e){ toast(e.message, 'warn'); }
  });
}

function clearArchive(){
  if(!isAdmin()){ toast('Only admin can empty the archive.', 'warn'); return; }
  if(!CACHE.archive.length){ toast('Archive is already empty.', 'ok'); return; }
  confirmAction(`Permanently delete all ${CACHE.archive.length} archived record(s)? This cannot be undone.`, async ()=>{
    try{
      await apiSend('api/archive.php', 'POST', {action:'clear'});
      await Promise.all([refresh('archive'), refresh('activity')]);
      renderArchive();
      toast('Archive emptied.', 'warn');
    }catch(e){ toast(e.message, 'warn'); }
  });
}

// Browser confirm()/alert() dialogs aren't used here — destructive
// actions go through this small in-page confirm modal instead (reuses
// the existing add/edit modal shell).
let pendingConfirmAction = null;
function confirmAction(message, onConfirm){
  pendingConfirmAction = onConfirm;
  document.getElementById('modalTitle').textContent = 'Please confirm';
  document.getElementById('modalBody').innerHTML = `<p style="color:var(--text-dim);font-size:13.5px;margin:0;">${message}</p>`;
  const saveBtn = document.getElementById('modalSaveBtn');
  saveBtn.textContent = 'Delete permanently';
  saveBtn.classList.add('danger-ghost');
  document.getElementById('modalBackdrop').classList.add('open');
}

/* ---------------------------------------------------------------------
   8) MODAL / FORM SYSTEM
--------------------------------------------------------------------- */
const FORMS = {
  vehicle:{
    title:'Add vehicle', typeKey:'vehicles', rerender:()=>{renderVehicles();renderDashboard();},
    fields:[
      {name:'plate', label:'Plate number', type:'text', placeholder:'e.g. NGA-1234'},
      {name:'type', label:'Vehicle type', type:'select', options:['Truck','Van','Bus','Car','Motorcycle']},
      {name:'make', label:'Make', type:'text', placeholder:'e.g. Toyota'},
      {name:'model', label:'Model', type:'text', placeholder:'e.g. HiAce'},
      {name:'year', label:'Year', type:'text', placeholder:'e.g. 2023'},
      {name:'odometer', label:'Odometer (km)', type:'text', placeholder:'e.g. 12000'},
      {name:'status', label:'Status', type:'select', options:['Active','Maintenance','Idle','Out of Service']},
    ],
  },
  reservation:{
    title:'New reservation', typeKey:'reservations', rerender:()=>{renderReservations();renderDashboard();},
    fields:[
      {name:'vehicle', label:'Vehicle plate no.', type:'text', placeholder:'e.g. NGA-1234'},
      {name:'requester', label:'Requested by', type:'text', placeholder:'e.g. Ops — J. Dela Cruz'},
      {name:'purpose', label:'Purpose', type:'text', placeholder:'e.g. Client delivery'},
      {name:'pickup', label:'Pickup location', type:'text', placeholder:'e.g. Main Depot'},
      {name:'destination', label:'Destination', type:'text', placeholder:'e.g. Quezon City Hub'},
      {name:'date', label:'Date', type:'text', placeholder:'YYYY-MM-DD'},
      {name:'status', label:'Status', type:'select', options:['Pending','Approved','Dispatched','Completed','Cancelled']},
    ],
  },
  driver:{
    title:'Add driver', typeKey:'drivers', rerender:()=>{renderDrivers();renderDashboard();},
    fields:[
      {name:'name', label:'Driver name', type:'text', placeholder:'e.g. R. Santos'},
      {name:'license', label:'License number', type:'text', placeholder:'e.g. D01-23-045'},
      {name:'phone', label:'Phone', type:'text', placeholder:'e.g. 0917 000 1122'},
      {name:'trips', label:'Trips completed', type:'text', placeholder:'e.g. 20'},
      {name:'on_time', label:'On-time rate (%)', type:'text', placeholder:'e.g. 90'},
      {name:'safety', label:'Safety score', type:'text', placeholder:'e.g. 88'},
      {name:'status', label:'Status', type:'select', options:['Active','Idle','Suspended']},
    ],
  },
  fuel:{
    title:'Log fuel entry', typeKey:'fuel', rerender:()=>{renderFuel();renderDashboard();},
    fields:[
      {name:'vehicle', label:'Vehicle plate no.', type:'text', placeholder:'e.g. NGA-1234'},
      {name:'date', label:'Date', type:'text', placeholder:'YYYY-MM-DD'},
      {name:'liters', label:'Liters', type:'text', placeholder:'e.g. 40'},
      {name:'price_per_liter', label:'Price per liter (₱)', type:'text', placeholder:'e.g. 64.00'},
      {name:'odometer', label:'Odometer at fill-up (km)', type:'text', placeholder:'e.g. 48210'},
    ],
  },
  route:{
    title:'Add route', typeKey:'routes', rerender:()=>{renderRoutes();renderDashboard();},
    fields:[
      {name:'name', label:'Route name', type:'text', placeholder:'e.g. Depot → QC Hub'},
      {name:'origin', label:'Origin', type:'text', placeholder:'e.g. Main Depot'},
      {name:'destination', label:'Destination', type:'text', placeholder:'e.g. Quezon City Hub'},
      {name:'distance', label:'Distance (km)', type:'text', placeholder:'e.g. 14.2'},
      {name:'duration', label:'Estimated duration', type:'text', placeholder:'e.g. 32 min'},
      {name:'status', label:'Status', type:'select', options:['Draft','Under Review','Optimized']},
    ],
  },
};

let activeForm = null;
let editingId = null;

function startEdit(formKey, typeKey, id){
  if(!isAdmin()){ toast('Only admin can edit records.', 'warn'); return; }
  const item = CACHE[typeKey].find(i=>i.id===id);
  if(!item) return;
  openModal(formKey, item);
}

function nounOf(title){ return title.replace(/^(Add|New|Log)\s+/i, ''); }

function openModal(formKey, existingItem){
  if(!isAdmin()){ toast('Only admin can add or edit records.', 'warn'); return; }
  activeForm = FORMS[formKey];
  editingId = existingItem ? existingItem.id : null;
  pendingConfirmAction = null;
  document.getElementById('modalSaveBtn').classList.remove('danger-ghost');
  document.getElementById('modalTitle').textContent = existingItem ? ('Edit ' + nounOf(activeForm.title)) : activeForm.title;
  document.getElementById('modalBody').innerHTML = activeForm.fields.map(f=>{
    const val = existingItem ? (existingItem[f.name] ?? '') : '';
    return `
    <div class="field">
      <label>${f.label}</label>
      ${f.type==='select'
        ? `<select id="f_${f.name}">${f.options.map(o=>`<option value="${o}" ${String(o)===String(val)?'selected':''}>${o}</option>`).join('')}</select>`
        : `<input id="f_${f.name}" type="text" placeholder="${f.placeholder||''}" value="${String(val).replace(/"/g,'&quot;')}">`
      }
    </div>`;
  }).join('');
  document.getElementById('modalSaveBtn').textContent = existingItem ? 'Save changes' : 'Save';
  document.getElementById('modalBackdrop').classList.add('open');
}
function closeModal(){
  document.getElementById('modalBackdrop').classList.remove('open');
  activeForm=null; editingId=null; pendingConfirmAction=null;
  const saveBtn = document.getElementById('modalSaveBtn');
  saveBtn.textContent = 'Save';
  saveBtn.classList.remove('danger-ghost');
}
document.getElementById('modalBackdrop').addEventListener('click', e=>{ if(e.target.id==='modalBackdrop') closeModal(); });

document.getElementById('modalSaveBtn').addEventListener('click', async ()=>{
  if(pendingConfirmAction){
    const action = pendingConfirmAction;
    pendingConfirmAction = null;
    closeModal();
    action();
    return;
  }
  if(!activeForm) return;
  if(!isAdmin()){ toast('Only admin can save records.', 'warn'); closeModal(); return; }

  const values = {};
  activeForm.fields.forEach(f=>{ values[f.name] = document.getElementById('f_'+f.name).value.trim(); });

  const requiredMissing = activeForm.fields.some(f=>f.type!=='select' && !values[f.name]);
  if(requiredMissing){ toast('Please fill in all fields.', 'warn'); return; }

  try{
    if(editingId){
      values.id = editingId;
      await apiSend(`api/records.php?type=${activeForm.typeKey}`, 'PUT', values);
      toast('Updated.', 'ok');
    } else {
      await apiSend(`api/records.php?type=${activeForm.typeKey}`, 'POST', values);
      toast('Saved.', 'ok');
    }
    await Promise.all([refresh(activeForm.typeKey), refresh('activity')]);
    activeForm.rerender();
    closeModal();
  }catch(e){
    toast(e.message, 'warn');
  }
});

/* ---------------------------------------------------------------------
   9) INIT
--------------------------------------------------------------------- */
(async function init(){
  try{
    await loadAll();
    navigate();
  }catch(e){
    toast('Could not load data: ' + e.message, 'warn');
  }
})();

window.addEventListener('resize', ()=>{
  renderDashboard(); if((location.hash||'#').slice(1)==='tcao') renderTCAO();
});
