(()=>{
 const H=window.HngHistory,el=id=>document.getElementById(id),range=el('trafficRange'),step=el('trafficStep'),grid=el('trafficCards'),picker=el('trafficPicker');
 const cards=new Map();let liveBusy=false,chartsBusy=false,generation=0,lastCharts=0,catalog=[];
 const outageDuration=o=>o?'≈ '+H.duration(o.duration_seconds)+(o.ongoing?' · belum pulih':'')+(o.uncertain?' · data tidak lengkap':''):'—';
 function cardElement(id,name){return document.getElementById('traffic_'+id+'_'+name);}
 function makeCard(row){
  const id=row.id,article=document.createElement('article');article.className='history-panel traffic-card';article.dataset.interface=String(id);
  article.innerHTML=`<div class="traffic-card-head"><div><h2>${H.escape(row.label||row.name)}</h2><div class="muted">${H.escape(row.router_name)} · ${H.escape(row.name)}</div></div><strong id="traffic_${id}_status" class="status-UNKNOWN">Menunggu</strong></div><div class="traffic-live"><span class="traffic-rx">↓ RX <b id="traffic_${id}_rx">—</b></span><span class="traffic-tx">↑ TX <b id="traffic_${id}_tx">—</b></span></div><div class="traffic-plot"><canvas id="traffic_${id}_canvas" role="img" aria-label="Grafik trafik ${H.escape(row.name)}"></canvas></div><div class="traffic-stat-grid"><span>Rata-rata RX <b id="traffic_${id}_avgRx">—</b></span><span>Rata-rata TX <b id="traffic_${id}_avgTx">—</b></span><span>Tertinggi RX <b id="traffic_${id}_maxRx">—</b></span><span>Tertinggi TX <b id="traffic_${id}_maxTx">—</b></span></div><div class="traffic-outage-line"><b id="traffic_${id}_count">Putus — kali</b><button class="btn btn-sm btn-outline-info" type="button" data-events="${id}" aria-expanded="false">Detail putus</button></div><div class="history-note" id="traffic_${id}_last">Belum ada putus tercatat.</div><div class="history-note" id="traffic_${id}_note">Menunggu data...</div><div class="history-note text-warning" id="traffic_${id}_error" role="status"></div><div id="traffic_${id}_events" class="traffic-events" hidden></div>`;
  grid.appendChild(article);const state={article,chart:null,points:[],range:Number(range.value)};
  if(typeof Chart==='function')state.chart=new Chart(cardElement(id,'canvas'),{type:'line',data:{labels:[],datasets:[{label:'RX / Download',data:[],borderColor:'#35b9ff',backgroundColor:'rgba(53,185,255,.17)',borderWidth:1.5,pointRadius:0,pointHitRadius:8,fill:true,tension:0,spanGaps:false},{label:'TX / Upload',data:[],borderColor:'#22dc94',backgroundColor:'rgba(34,220,148,.08)',borderWidth:1.5,pointRadius:0,pointHitRadius:8,fill:true,tension:0,spanGaps:false},{label:'Link putus',data:[],showLine:false,pointRadius:3,pointBackgroundColor:'#ff6d78',pointBorderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{color:'#b8d0e1',font:{size:10},boxWidth:20}},tooltip:{callbacks:{title:items=>items.length?H.date(state.points[items[0].dataIndex].ts):'',label:ctx=>ctx.datasetIndex===2?'Link DOWN: '+state.points[ctx.dataIndex].losses+' sampel':ctx.dataset.label+': '+H.bits(ctx.parsed.y)}}},scales:{x:{type:'linear',ticks:{callback:value=>H.label(value,state.range),color:'#8eabbf',maxTicksLimit:6,font:{size:9}},grid:{color:'rgba(135,170,195,.09)'}},y:{beginAtZero:true,ticks:{color:'#8eabbf',font:{size:9},callback:H.bits},grid:{color:'rgba(135,170,195,.1)'}}}}});
  return state;
 }
 function syncCards(rows){
  let changed=false;const wanted=new Set(rows.map(row=>row.id));
  for(const [id,state] of cards)if(!wanted.has(id)){state.chart?.destroy();state.article.remove();cards.delete(id);changed=true;}
  grid.querySelector('.traffic-empty')?.remove();
  for(const row of rows){if(!cards.has(row.id)){cards.set(row.id,makeCard(row));changed=true;}}
  if(!rows.length)grid.innerHTML='<div class="history-panel traffic-empty muted">Belum ada interface dipilih. Klik <b>Pilih interface</b> untuk menentukan grafik yang ingin dipantau.</div>';
  return changed;
 }
 function paintLive(row){
  const id=row.id,status=cardElement(id,'status');status.className='status-'+row.last_status;status.textContent=row.last_status==='ONLINE'?'Link UP':row.last_status==='OFFLINE'?'Link DOWN':'Data belum tersedia';
  cardElement(id,'rx').textContent=H.bits(row.last_rx_bps);cardElement(id,'tx').textContent=H.bits(row.last_tx_bps);cardElement(id,'count').textContent='Putus '+row.outage_count+' kali · periode pilihan';
  const last=row.last_outage;cardElement(id,'last').textContent=last?'Terakhir putus '+H.date(last.started_at)+' · '+outageDuration(last):'Belum ada putus tercatat.';
  cardElement(id,'error').textContent=row.last_error||'';
 }
 async function live(){
  if(liveBusy)return;liveBusy=true;const requested=range.value;
  try{const result=await H.read('traffic-api.php?'+new URLSearchParams({mode:'live',range:requested}));if(requested!==range.value)return;el('trafficError').textContent='';const changed=syncCards(result.interfaces);for(const row of result.interfaces)paintLive(row);if(changed)charts(true);}
  catch(error){el('trafficError').textContent=error.message;}finally{liveBusy=false;}
 }
 async function loadChart(id,current,requestedRange,requestedStep){
  try{
   const result=await H.read('traffic-api.php?'+new URLSearchParams({interface_id:id,range:requestedRange,step:requestedStep}));if(current!==generation||!cards.has(id))return;
   const state=cards.get(id),data=result.data;state.points=H.chartPoints(data.points);state.range=data.window.range;
   if(state.chart){
    state.chart.data.labels=[];state.chart.options.scales.x.min=data.since;state.chart.options.scales.x.max=data.ts;state.chart.data.datasets[0].data=state.points.map(p=>({x:p.ts,y:H.pointValue(p,data.window.range,'a')}));state.chart.data.datasets[1].data=state.points.map(p=>({x:p.ts,y:H.pointValue(p,data.window.range,'b')}));state.chart.data.datasets[2].data=state.points.map(p=>({x:p.ts,y:p.losses?0:null}));
    for(const line of state.chart.data.datasets.slice(0,2))line.pointRadius=line.data.filter(p=>Number.isFinite(p.y)).length===1?3:0;
    state.chart.update('none');
   }else cardElement(id,'error').textContent='Pustaka grafik belum termuat. Muat ulang halaman.';
   cardElement(id,'avgRx').textContent=H.bits(data.stats.average_a);cardElement(id,'avgTx').textContent=H.bits(data.stats.average_b);cardElement(id,'maxRx').textContent=H.bits(data.stats.max_a);cardElement(id,'maxTx').textContent=H.bits(data.stats.max_b);
   cardElement(id,'count').textContent='Putus '+data.stats.outage_count+' kali · '+data.window.label;
   const last=data.last_outage;cardElement(id,'last').textContent=last?'Terakhir putus '+H.date(last.started_at)+' · '+outageDuration(last):'Belum ada putus tercatat.';
   cardElement(id,'note').textContent=data.window.label+' · rata-rata '+H.stepLabel(data.window.step)+' · '+data.stats.samples.toLocaleString('id-ID')+' sampel · data pertama '+H.date(data.stats.first_sample)+' · diperbarui '+H.date(data.ts);
   cardElement(id,'events').innerHTML='<p class="history-note">'+data.stats.outage_count+' kejadian pada periode pilihan'+(data.stats.outage_count>100?' · menampilkan 100 terbaru':'')+'</p>'+(data.outages.length?'<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Mulai putus</th><th>Pulih</th><th>Durasi</th><th>Keterangan</th></tr></thead><tbody>'+data.outages.map(o=>`<tr><td>${H.escape(H.date(o.started_at))}</td><td>${H.escape(o.recovered_at?H.date(o.recovered_at):'Belum pulih')}</td><td>${H.escape(outageDuration(o))}</td><td>${H.escape(H.outageNote(o))}</td></tr>`).join('')+'</tbody></table></div>':'<p class="muted">Belum ada kejadian putus yang tercatat.</p>');
  }catch(error){if(current===generation&&cards.has(id))cardElement(id,'error').textContent=error.message;}
 }
 async function charts(force=false){
  if(chartsBusy&&!force)return;chartsBusy=true;
  H.syncStep(range,step);const current=++generation;lastCharts=Date.now();const ids=[...cards.keys()],requestedRange=range.value,requestedStep=step.value;let cursor=0;
  async function worker(){while(cursor<ids.length && current===generation){const id=ids[cursor++];await loadChart(id,current,requestedRange,requestedStep);}}
  try{await Promise.all(Array.from({length:Math.min(3,ids.length)},worker));}finally{if(current===generation)chartsBusy=false;}
 }
 async function loadCatalog(){
  el('trafficSave').disabled=true;
  try{const result=await H.read('traffic-api.php?mode=catalog');catalog=result.interfaces;el('trafficChoices').innerHTML=catalog.length?catalog.map(row=>`<label><input type="checkbox" value="${row.id}" ${row.selected?'checked':''}> <span><b>${H.escape(row.router_name)} · ${H.escape(row.label||row.name)}</b><small>${H.escape(row.name)} · ${H.escape(row.type)}</small></span></label>`).join(''):'<p class="muted">Daftar interface belum tersedia. Tunggu pembacaan MikroTik berikutnya.</p>';el('trafficSave').disabled=!catalog.length;}
  catch(error){el('trafficError').textContent=error.message;throw error;}
 }
 el('trafficChoose').addEventListener('click',()=>{picker.hidden=!picker.hidden;el('trafficChoose').setAttribute('aria-expanded',String(!picker.hidden));if(!picker.hidden)loadCatalog().catch(()=>{});});
 el('trafficPickAll').addEventListener('click',()=>{for(const input of el('trafficChoices').querySelectorAll('input'))input.checked=true;});el('trafficPickNone').addEventListener('click',()=>{for(const input of el('trafficChoices').querySelectorAll('input'))input.checked=false;});
 picker.addEventListener('submit',async event=>{
  event.preventDefault();el('trafficSave').disabled=true;
  try{const ids=[...el('trafficChoices').querySelectorAll('input:checked')].map(input=>Number(input.value)),body=new FormData(picker);body.set('ids',JSON.stringify(ids));const result=await H.read('traffic-api.php',{method:'POST',body});el('trafficMessage').textContent=result.message;el('trafficError').textContent='';picker.hidden=true;el('trafficChoose').setAttribute('aria-expanded','false');await live();}
  catch(error){el('trafficError').textContent=error.message;}finally{el('trafficSave').disabled=false;}
 });
 grid.addEventListener('click',event=>{const button=event.target.closest('[data-events]');if(!button)return;const panel=cardElement(button.dataset.events,'events');panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));});
 const remembered=localStorage.getItem('hng_mrtg_traffic_range');if([...range.options].some(o=>o.value===remembered))range.value=remembered;
 function refresh(){localStorage.setItem('hng_mrtg_traffic_range',range.value);H.syncStep(range,step);for(const state of cards.values()){if(state.chart){for(const line of state.chart.data.datasets)line.data=[];state.chart.update('none');}}charts(true);live();}
 range.addEventListener('change',refresh);step.addEventListener('change',refresh);el('trafficRefresh').addEventListener('click',refresh);H.syncStep(range,step);live();setInterval(()=>{live();const delay=Number(range.value)<=900?5000:Number(range.value)<=3600?10000:30000;if(Date.now()-lastCharts>=delay)charts();},5000);
})();
