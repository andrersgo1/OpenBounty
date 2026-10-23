// DevOrbit 2.0 public exploration and contextual authentication modal.
(()=>{
 const modal=document.getElementById('auth-modal');
 if(modal){
  const loginForm=document.getElementById('auth-login-form');
  const registerForm=document.getElementById('auth-register-form');
  const title=document.getElementById('auth-title');
  const setMode=mode=>{const register=mode==='register';loginForm.hidden=register;registerForm.hidden=!register;title.textContent=register?'Create your DevOrbit account':'Welcome back';modal.querySelectorAll('[data-auth-tab]').forEach(b=>b.classList.toggle('active',b.dataset.authTab===mode));modal.querySelectorAll('.auth-switch [data-auth-tab]').forEach(b=>{b.textContent=register?'Log in':'Create an account';b.dataset.authTab=register?'login':'register'});modal.querySelectorAll('.auth-switch span').forEach(x=>x.textContent=register?'Already have an account?':'New here?');setTimeout(()=>modal.querySelector('form:not([hidden]) input:not([type=hidden])')?.focus(),40)};
  const open=mode=>{modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');setMode(mode||'login')};
  const close=()=>{modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open')};
  document.querySelectorAll('[data-open-auth]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();open(el.dataset.openAuth)}));
  const setNext=v=>modal.querySelectorAll('input[name=next]').forEach(i=>{i.value=v||''});
  document.querySelectorAll('[data-auth-required]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();setNext(el.getAttribute('href'));open(el.dataset.authMode||'register')}));
  modal.querySelectorAll('[data-auth-tab]').forEach(el=>el.addEventListener('click',()=>setMode(el.dataset.authTab)));
  modal.querySelectorAll('[data-close-auth]').forEach(el=>el.addEventListener('click',close));
  modal.addEventListener('click',e=>{if(e.target===modal)close()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('open'))close()});
  const params=new URLSearchParams(window.location.search);const requestedMode=params.get('auth');if(params.get('next'))setNext(params.get('next'));if(requestedMode==='login'||requestedMode==='register')open(requestedMode);
 }
 const menu=document.getElementById('mobile-menu');
 const sidebar=document.getElementById('site-sidebar');
 const backdrop=document.getElementById('sidebar-backdrop');
 const setSidebarOpen=open=>{
  if(!sidebar||!menu||!backdrop)return;
  sidebar.classList.toggle('mobile-open',open);
  document.body.classList.toggle('sidebar-open',open);
  menu.setAttribute('aria-expanded',String(open));
  menu.setAttribute('aria-label',open?'Close navigation':'Open navigation');
  backdrop.hidden=!open;
 };
 menu?.addEventListener('click',()=>setSidebarOpen(!sidebar?.classList.contains('mobile-open')));
 backdrop?.addEventListener('click',()=>setSidebarOpen(false));
 sidebar?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setSidebarOpen(false)));
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&sidebar?.classList.contains('mobile-open')){setSidebarOpen(false);menu?.focus()}});

 // Collapsible sidebar (desktop), remembered per browser.
 const collapse=document.getElementById('sb-collapse');
 const applyCollapsed=c=>{document.body.classList.toggle('ob-collapsed',c);collapse?.setAttribute('aria-pressed',String(c));collapse?.setAttribute('aria-label',c?'Expand sidebar':'Collapse sidebar')};
 try{applyCollapsed(localStorage.getItem('ob-sidebar')==='1')}catch(e){}
 collapse?.addEventListener('click',()=>{const c=!document.body.classList.contains('ob-collapsed');applyCollapsed(c);try{localStorage.setItem('ob-sidebar',c?'1':'0')}catch(e){}});
 // Confirmation prompts: any button/link with data-confirm asks first.
 document.addEventListener('click',e=>{const el=e.target.closest('[data-confirm]');if(el&&!window.confirm(el.dataset.confirm)){e.preventDefault();e.stopImmediatePropagation()}},true);
 // Loading state: disable the submit button once a form is sent (prevents double submits).
 document.addEventListener('submit',e=>{const f=e.target;if(f.hasAttribute('data-no-loading'))return;const b=e.submitter||f.querySelector('button[type=submit]');if(!b||e.defaultPrevented)return;setTimeout(()=>{b.classList.add('is-loading');b.setAttribute('aria-busy','true');f.querySelectorAll('button[type=submit]').forEach(x=>{if(x!==b)x.disabled=true})},0)});
 // Auto-submit filter selects.
 document.querySelectorAll('select[data-autosubmit]').forEach(s=>s.addEventListener('change',()=>s.form&&s.form.submit()));
 // Close open dropdown menu when clicking elsewhere.
 document.addEventListener('click',e=>{document.querySelectorAll('details.ob-menu[open]').forEach(d=>{if(!d.contains(e.target))d.removeAttribute('open')})});
})();
