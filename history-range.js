window.HngHistory=Object.freeze({
 escape:v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),
 date:ts=>ts?new Date(ts*1000).toLocaleString('id-ID'):'—',
 ms:v=>v==null?'—':Number(v).toFixed(1)+' ms',
 bits:v=>{if(v==null)return '—';v=Number(v);if(v>=1e9)return (v/1e9).toFixed(2)+' Gbps';if(v>=1e6)return (v/1e6).toFixed(1)+' Mbps';if(v>=1e3)return (v/1e3).toFixed(1)+' Kbps';return v.toFixed(0)+' bps';},
 duration:s=>{s=Math.max(0,Math.floor(s));const d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60);return (d?d+' hari ':'')+(h?h+' jam ':'')+(m?m+' menit ':'')+s%60+' detik';},
 outageNote:o=>o?((o.ongoing?'Belum terpantau pulih':'Sudah pulih')+(o.partial_start?' · awal tidak teramati':'')+(o.uncertain?' · ada jeda data':'')):'Belum ada putus tercatat',
 stepLabel:s=>s>=86400?s/86400+' hari':s>=3600?s/3600+' jam':s>=60?s/60+' menit':s+' detik',
 syncStep:(range,step)=>{for(const option of step.options)option.disabled=Number(option.value)>0 && Math.ceil(Number(range.value)/Number(option.value))+2>1500;if(step.selectedOptions[0]?.disabled)step.value='0';},
 pointValue:(p,range,key='a')=>Number(range)<=86400 && (p.losses||p.unknowns||p.gap)?null:p[key],
 chartPoints:points=>points.filter(p=>p.samples>0||p.gap||p.losses||p.unknowns),
 label:(ts,range)=>{const options=Number(range)<=900?{hour:'2-digit',minute:'2-digit',second:'2-digit'}:Number(range)<=86400?{hour:'2-digit',minute:'2-digit'}:{day:'2-digit',month:'2-digit',...(Number(range)>2592000?{year:'2-digit'}:{hour:'2-digit',minute:'2-digit'})};return new Date(ts*1000).toLocaleString('id-ID',options);},
 read:async(url,options={})=>{const response=await fetch(url,{cache:'no-store',...options});if(response.status===401){location.replace('login.php');throw new Error('Sesi berakhir. Silakan login lagi.');}if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error(response.status===403?'Sesi formulir tidak valid. Muat ulang halaman.':'Respons server belum dapat dibaca.');const result=await response.json();if(!result.ok)throw new Error(result.error||'Data tidak tersedia');return result;}
});
