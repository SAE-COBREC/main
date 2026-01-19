(function(){
  if (window.__globalLoaderInstalled) return;
  window.__globalLoaderInstalled = true;
  const loaderEl = document.getElementById('global-loader');
  if(!loaderEl) return;
  let count = 0;
  let fallbackTimer = null;
  const FALLBACK_MS = 12000;

  function setFallback(){
    if (fallbackTimer) clearTimeout(fallbackTimer);
    fallbackTimer = setTimeout(()=>{
      console.warn('Loader fallback timeout');
      hideAll();
      showError('Opération expirée, réessayer');
    }, FALLBACK_MS);
  }

  function show(){
    try{
      count++;
      loaderEl.classList.remove('hidden');
      loaderEl.setAttribute('aria-hidden','false');
      setFallback();
    }catch(e){ console.error(e); }
  }
  function hide(){
    try{
      if(count>0) count--;
      if(count===0){ hideAll(); }
    }catch(e){ console.error(e); }
  }
  function hideAll(){
    if(fallbackTimer) { clearTimeout(fallbackTimer); fallbackTimer=null; }
    loaderEl.classList.add('hidden');
    loaderEl.setAttribute('aria-hidden','true');
    count = 0;
  }
  function showError(msg){
    if (window.showNotification) { window.showNotification(msg, 'error'); }
    else if (window.toastr) { toastr.error(msg); }
    else { console.error(msg); }
  }

  // Wrap fetch globally
  const origFetch = window.fetch;
  if (origFetch) {
    window.fetch = function(...args){
      const options = args[1] || {};
      const skip = options.noLoader === true;
      if (!skip) show();
      
      const p = origFetch.apply(this,args);
      
      if (!skip) {
        return p
          .catch(err=>{ showError('Erreur réseau — vérifiez votre connexion'); throw err; })
          .finally(()=>{ hide(); });
      }
      return p;
    };
  }

  // Attach to form submits (unless data-no-loader present)
  document.addEventListener('submit', function(e){
    const form = e.target;
    if (form && form.matches && form.matches('form') && !form.hasAttribute('data-no-loader')) {
      show();
      // Fallback hide in case form prevented submission
      setTimeout(()=>{ if(count>0){ hide(); } }, FALLBACK_MS);
    }
  }, true);

  // Expose API
  window.Loader = { show, hide, hideAll, getCount: ()=>count };
})();
