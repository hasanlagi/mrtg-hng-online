const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=path.join(__dirname,'..','report-download.js');
const scope={module:{exports:{}},URLSearchParams};
if(fs.existsSync(source))vm.runInNewContext(fs.readFileSync(source,'utf8'),scope);
const report=scope.module.exports;

test('traffic report uses the requested range and automatic interval without sending interface selections',()=>{
  assert.equal(typeof report.buildUrl,'function','PDF request builder is required');
  assert.equal(report.buildUrl('traffic',{range:'300',step:'0',router_id:'9',target_id:'4',ids:[1,2]}),'report-pdf.php?kind=traffic&range=300&step=0');
});

test('ping report includes the selected router and target, and updated control values',()=>{
  assert.equal(typeof report.buildUrl,'function');
  const values={range:'60',step:'0',router_id:'1',target_id:'2'};
  assert.equal(report.buildUrl('ping',values),'report-pdf.php?kind=ping&range=60&step=0&router_id=1&target_id=2');
  values.range='43200';values.step='900';values.target_id='7';
  assert.equal(report.buildUrl('ping',values),'report-pdf.php?kind=ping&range=43200&step=900&router_id=1&target_id=7');
});

test('only supported report kinds can be requested',()=>{
  assert.equal(typeof report.buildUrl,'function');
  assert.throws(()=>report.buildUrl('admin',{range:'300',step:'0'}),/jenis laporan/i);
});

test('a successful PDF response keeps a safe server supplied filename',async()=>{
  assert.equal(typeof report.readPdf,'function');
  const response=new Response('%PDF-1.4\nexample',{status:200,headers:{'Content-Type':'application/pdf','Content-Disposition':'attachment; filename="HNG-trafik-2026.pdf"'}});
  const result=await report.readPdf(response,'traffic');
  assert.equal(result.filename,'HNG-trafik-2026.pdf');
  assert.equal(await result.blob.text(),'%PDF-1.4\nexample');
});

test('PDF download filenames cannot contain paths or executable extensions',async()=>{
  assert.equal(typeof report.readPdf,'function');
  const pathResponse=new Response('%PDF-fixture',{headers:{'Content-Type':'application/pdf','Content-Disposition':'attachment; filename="../../report.pdf"'}});
  assert.equal((await report.readPdf(pathResponse,'ping')).filename,'report.pdf');
  const invalidResponse=new Response('%PDF-fixture',{headers:{'Content-Type':'application/pdf','Content-Disposition':'attachment; filename="report.exe"'}});
  assert.equal((await report.readPdf(invalidResponse,'ping')).filename,'HNG-MRTG-ping.pdf');
});

test('server JSON errors are displayed instead of downloaded as PDFs',async()=>{
  assert.equal(typeof report.readPdf,'function');
  const response=new Response(JSON.stringify({ok:false,error:'Belum ada interface dipilih.'}),{status:400,headers:{'Content-Type':'application/json'}});
  await assert.rejects(report.readPdf(response,'traffic'),{message:'Belum ada interface dipilih.'});
});

test('an HTML login or failure page is rejected instead of saved with a PDF extension',async()=>{
  assert.equal(typeof report.readPdf,'function');
  const response=new Response('<html>Login</html>',{headers:{'Content-Type':'text/html'}});
  await assert.rejects(report.readPdf(response,'ping'),/PDF/i);
});

test('expired authentication is distinguishable so the UI can return to login',async()=>{
  assert.equal(typeof report.readPdf,'function');
  await assert.rejects(report.readPdf(new Response('',{status:401}),'ping'),error=>error.code==='LOGIN_REQUIRED');
});

test('a mislabeled server error body cannot be saved as a PDF',async()=>{
  const response=new Response('<html>Server error</html>',{headers:{'Content-Type':'application/pdf'}});
  await assert.rejects(report.readPdf(response,'traffic'),/PDF/i);
});
