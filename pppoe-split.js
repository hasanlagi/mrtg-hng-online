(function(root){
 'use strict';
 function isBlocked(row){return row.disabled===true||row.disabled==='true'||row.disabled===1||['blokir','isolir'].includes(String(row.profile??'').trim().toLowerCase());}
 function split(rows){const offline=[],blocked=[];for(const row of rows)(isBlocked(row)?blocked:offline).push(row);return {offline,blocked};}
 const api=Object.freeze({isBlocked,split});
 if(typeof module==='object'&&module.exports)module.exports=api;else root.HngPppoeSplit=api;
})(typeof window==='object'?window:globalThis);
