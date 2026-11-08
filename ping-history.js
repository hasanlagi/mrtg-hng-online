(()=>{
 const H=window.HngHistory,el=id=>document.getElementById(id),router=el('historyRouter'),target=el('historyTarget'),step=el('historyStep'),range=el('historyRange');
 const outageDuration=o=>o?'≈ '+H.duration(o.duration_seconds)+(o.ongoing?' · belum pulih':'')+(o.uncertain?' · data tidak lengkap':''):'—';
 let points=[],sequence=0,overviewBusy=false,lastDetail=0,chartRange=86400;
 const chart=typeof Chart==='function'?new Chart(el('pingHistoryCanvas'),{type:'line',data:{labels:[],datasets:[{label:'Ping',data:[],borderColor:'#35b9ff',backgroundColor:'rgba(53,185,255,.14)',borderWidth:1.5,pointRadius:0,pointHitRadius:8,fill:true,tension:0,spanGaps:false},{label:'Timeout',data:[],showLine:false,pointRadius:3,pointBackgroundColor:'#ff6674',pointBorderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{labels:{color:'#c5dced'}},tooltip:{callbacks:{title:items=>items.length?H.date(points[items[0].dataIndex].ts):'',label:ctx=>ctx.datasetIndex===1?'Timeout: '+points[ctx.dataIndex].losses+' sampel':'Ping rata-rata: '+H.ms(ctx.parsed.y)}}},scales:{x:{type:'linear',ticks:{callback:value=>H.label(value,chartRange),color:'#93aec2',maxTicksLimit:8,font:{size:10}},grid:{color:'rgba(135,170,195,.1)'}},y:{beginAtZero:true,ticks:{color:'#93aec2',callback:v=>v+' ms'},grid:{color:'rgba(135,170,195,.1)'}}}}}):null;
 async function detail(){
  if(!router.value)return;H.syncStep(range,step);const current=++sequence;lastDetail=Date.now();el('historyUpdated').textContent='Memuat periode yang dipilih...';
  try{
   const j=await H.read('history-api.php?'+new URLSearchParams({router_id:router.value,target_id:target.value,step:step.value,range:range.value}));if(current!==sequence)return;
   const data=j.data;points=H.chartPoints(data.points);chartRange=data.window.range;el('historyError').textContent='';el('historyHeading').textContent='Riwayat grafik ping · '+data.window.label+' terakhir';
   el('historyAverage').textContent=H.ms(data.stats.average_ms);el('historyMaximum').textContent=H.ms(data.stats.max_ms);el('historyLoss').textContent=data.stats.losses+' sampel · '+(data.stats.loss_pct==null?'—':data.stats.loss_pct+'%');el('historySamples').textContent=data.stats.samples.toLocaleString('id-ID');el('historyIncidentCount').textContent=data.stats.outage_count.toLocaleString('id-ID')+' kali';
   if(chart){chart.data.labels=[];chart.options.scales.x.min=data.since;chart.options.scales.x.max=data.ts;chart.data.datasets[0].data=points.map(p=>({x:p.ts,y:H.pointValue(p,data.window.range)}));chart.data.datasets[0].pointRadius=chart.data.datasets[0].data.filter(p=>Number.isFinite(p.y)).length===1?3:0;chart.data.datasets[1].data=points.map(p=>({x:p.ts,y:p.losses?0:null}));chart.update('none');}else el('historyError').textContent='Pustaka grafik belum termuat. Muat ulang halaman.';
   const last=data.last_outage;el('lastOutageStart').textContent=last?H.date(last.started_at):'Belum ada putus tercatat';el('lastOutageEnd').textContent=last?(last.recovered_at?H.date(last.recovered_at):H.outageNote(last)):'—';el('lastOutageDuration').textContent=outageDuration(last);
   el('historyUpdated').textContent='Diperbarui '+H.date(data.ts)+' · rata-rata '+H.stepLabel(data.window.step)+' · '+data.stats.unknowns+' sampel tanpa data sumber · '+data.stats.gap_buckets+' interval dengan jeda pencatatan.';
   el('historyOutageHeading').textContent='Daftar putus · '+data.window.label+' · '+data.stats.outage_count+' kejadian'+(data.stats.outage_count>100?' · 100 terbaru ditampilkan':'');
   el('historyOutages').innerHTML=data.outages.length?data.outages.map(o=>`<tr><td>${H.escape(H.date(o.started_at))}</td><td>${H.escape(o.recovered_at?H.date(o.recovered_at):'Belum terpantau pulih')}</td><td>${H.escape(outageDuration(o))}</td><td>${H.escape(H.outageNote(o))}</td></tr>`).join(''):'<tr><td colspan="4" class="muted">Belum ada putus yang tercatat pada periode ini.</td></tr>';
  }catch(error){if(current===sequence){el('historyError').textContent=error.message;el('historyUpdated').textContent='Periode baru belum berhasil dimuat.';}}
 }
 async function overview(){
  if(overviewBusy||!router.value)return;overviewBusy=true;const key=router.value+':'+range.value;
  try{const j=await H.read('history-api.php?'+new URLSearchParams({router_id:router.value,mode:'overview',range:range.value}));if(key!==router.value+':'+range.value)return;
   el('historyOverview').innerHTML=j.targets.map(t=>`<tr><td><b>${H.escape(t.name)}</b></td><td>${H.escape(t.host)}</td><td>${H.escape(H.ms(t.ms))}</td><td class="status-${t.status}">${H.escape(t.status==='UNKNOWN'?'DATA TIDAK TERSEDIA':t.status)}</td><td>${t.outage_count} kali</td><td>${H.escape(t.last_outage?H.date(t.last_outage.started_at):'Belum ada putus tercatat')}</td><td>${H.escape(outageDuration(t.last_outage))}</td><td><button class="btn btn-sm btn-outline-info" data-target="${t.id}">Grafik</button></td></tr>`).join('');
  }catch(error){el('historyError').textContent=error.message;}finally{overviewBusy=false;}
 }
 const remembered=localStorage.getItem('hng_mrtg_history_router');if([...router.options].some(o=>o.value===remembered))router.value=remembered;
 const rememberedRange=localStorage.getItem('hng_mrtg_ping_range');if([...range.options].some(o=>o.value===rememberedRange))range.value=rememberedRange;
 function reload(){localStorage.setItem('hng_mrtg_ping_range',range.value);H.syncStep(range,step);detail();overview();}
 router.addEventListener('change',()=>{localStorage.setItem('hng_mrtg_history_router',router.value);reload();});target.addEventListener('change',detail);step.addEventListener('change',detail);range.addEventListener('change',reload);el('refreshHistory').addEventListener('click',reload);
 el('historyOverview').addEventListener('click',event=>{const button=event.target.closest('[data-target]');if(button){target.value=button.dataset.target;detail();}});
 if(!router.value)el('historyError').textContent='Belum ada sumber MikroTik aktif.';reload();setInterval(()=>{overview();const delay=Number(range.value)<=900?5000:Number(range.value)<=3600?10000:30000;if(Date.now()-lastDetail>=delay)detail();},5000);
})();
