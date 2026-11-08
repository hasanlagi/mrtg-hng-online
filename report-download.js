(()=>{
 'use strict';

 function buildUrl(kind,values){
  if(kind!=='traffic'&&kind!=='ping')throw new Error('Jenis laporan tidak dikenali.');
  const query=new URLSearchParams({kind,range:String(values.range),step:String(values.step)});
  if(kind==='ping'){
   query.set('router_id',String(values.router_id));
   query.set('target_id',String(values.target_id));
  }
  return 'report-pdf.php?'+query.toString();
 }

 function safeFilename(disposition,kind){
  const fallback='HNG-MRTG-'+(kind==='traffic'?'trafik':'ping')+'.pdf';
  const encoded=disposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
  const plain=disposition.match(/filename\s*=\s*(?:"([^"]*)"|([^;]*))/i);
  let name=encoded?encoded[1]:plain?(plain[1]??plain[2]):'';
  if(encoded){try{name=decodeURIComponent(name);}catch(_){return fallback;}}
  name=name.split(/[\\/]/).pop().replace(/[\u0000-\u001f\u007f\u202a-\u202e\u2066-\u2069<>:"|?*]/g,'').trim();
  return name.length>4&&name.length<=160&&/\.pdf$/i.test(name)?name:fallback;
 }

 async function readPdf(response,kind){
  if(response.status===401){
   const error=new Error('Sesi berakhir. Silakan login kembali.');
   error.code='LOGIN_REQUIRED';
   throw error;
  }
  const type=(response.headers.get('Content-Type')||'').split(';')[0].trim().toLowerCase();
  if(type==='application/json'||type.endsWith('+json')){
   let data;try{data=await response.json();}catch(_){data=null;}
   const message=typeof data?.error==='string'?data.error:typeof data?.message==='string'?data.message:'';
   throw new Error(message||'Laporan PDF belum dapat dibuat. Coba lagi nanti.');
  }
  if(!response.ok)throw new Error('Laporan PDF belum dapat dibuat. Coba lagi nanti.');
  if(type!=='application/pdf')throw new Error('Server belum mengirim file PDF yang valid. Muat ulang halaman lalu coba lagi.');
  const blob=await response.blob();
  if(!blob.size)throw new Error('File PDF masih kosong. Coba unduh kembali.');
  if(await blob.slice(0,5).text()!=='%PDF-')throw new Error('File yang diterima belum berupa PDF yang valid. Coba lagi nanti.');
  return {blob,filename:safeFilename(response.headers.get('Content-Disposition')||'',kind)};
 }

 if(typeof module==='object'&&module.exports){module.exports={buildUrl,readPdf};return;}

 const value=id=>document.getElementById(id)?.value??'';
 for(const button of document.querySelectorAll('[data-report-kind]')){
  button.addEventListener('click',async()=>{
   if(button.disabled)return;
   const kind=button.dataset.reportKind,status=document.getElementById(button.dataset.reportStatus),label=button.textContent;
   function message(text,error=false){if(status){status.textContent=text;status.classList.toggle('is-error',error);}}
   button.disabled=true;button.setAttribute('aria-busy','true');button.textContent='Menyiapkan PDF…';
   message('Menyiapkan laporan sesuai periode yang dipilih…');
   try{
    const values=kind==='traffic'?{range:value('trafficRange'),step:value('trafficStep')}:{range:value('historyRange'),step:value('historyStep'),router_id:value('historyRouter'),target_id:value('historyTarget')};
    const response=await fetch(buildUrl(kind,values),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/pdf'}});
    const file=await readPdf(response,kind),url=URL.createObjectURL(file.blob),link=document.createElement('a');
    link.href=url;link.download=file.filename;link.hidden=true;document.body.appendChild(link);
    try{link.click();}finally{link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);}
    message('PDF siap. Unduhan dimulai.');
   }catch(error){
    if(error.code==='LOGIN_REQUIRED'){message(error.message,true);window.location.assign('login.php');}
    else message(error instanceof TypeError?'Koneksi terputus. Periksa jaringan lalu coba lagi.':error.message||'PDF belum dapat diunduh. Coba lagi.',true);
   }finally{button.disabled=false;button.removeAttribute('aria-busy');button.textContent=label;}
  });
 }
})();
