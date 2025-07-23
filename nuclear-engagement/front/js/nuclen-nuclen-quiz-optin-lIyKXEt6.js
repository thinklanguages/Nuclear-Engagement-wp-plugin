import{i as b,s as g,a as f}from"./nuclen-nuclen-quiz-main-RXJmRKM6.js";import"./nuclen-front.js";import"../../logger-DwRZMuf8.js";const w=l=>`
	<div id="nuclen-optin-container" class="nuclen-optin-with-results">
	<p class="nuclen-fg"><strong>${l.promptText}</strong></p>
	<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
	<input  type="text"  id="nuclen-optin-name">
	<label for="nuclen-optin-email" class="nuclen-fg">Email</label>
	<input  type="email" id="nuclen-optin-email" required>
	<button type="button" id="nuclen-optin-submit">${l.submitLabel}</button>
	</div>`;function I(l,n,t,a){var o,c;l.innerHTML=`
	<div id="nuclen-optin-container">
		<p class="nuclen-fg"><strong>${n.promptText}</strong></p>
		<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
		<input  type="text"  id="nuclen-optin-name">
		<label for="nuclen-optin-email" class="nuclen-fg">Email *</label>
		<input  type="email" id="nuclen-optin-email" required>
		<div class="nuclen-optin-btn-row">
		<button type="button" id="nuclen-optin-submit">${n.submitLabel}</button>
		</div>
		${n.mandatory?"":'<div class="nuclen-optin-skip"><a href="#" id="nuclen-optin-skip">Skip &amp; view results</a></div>'}
	</div>`,(o=document.getElementById("nuclen-optin-submit"))==null||o.addEventListener("click",async()=>{var p;const e=document.getElementById("nuclen-optin-submit"),r=document.getElementById("nuclen-optin-name"),s=document.getElementById("nuclen-optin-email"),u=r.value.trim(),i=s.value.trim();if(r.classList.remove("nuclen-error"),s.classList.remove("nuclen-error"),!u){r.classList.add("nuclen-error"),r.focus();return}if(!b(i)){s.classList.add("nuclen-error"),s.focus();return}e.disabled=!0;const m=e.textContent;e.textContent="Submitting...";try{await g(u,i,window.location.href,n),await f(u,i,n),t()}catch(v){console.error("[Optin] Submission error:",v);const d=document.createElement("div");d.className="nuclen-error-message",d.textContent="Unable to submit. Please check your connection and try again.",(p=e.parentElement)==null||p.appendChild(d),setTimeout(()=>d.remove(),5e3),e.disabled=!1,e.textContent=m||n.submitLabel}}),(c=document.getElementById("nuclen-optin-skip"))==null||c.addEventListener("click",e=>{e.preventDefault(),a()})}function h(l){var n;(n=document.getElementById("nuclen-optin-submit"))==null||n.addEventListener("click",async()=>{var s,u;const t=document.getElementById("nuclen-optin-submit"),a=document.getElementById("nuclen-optin-name"),o=document.getElementById("nuclen-optin-email"),c=a.value.trim(),e=o.value.trim();if(a.classList.remove("nuclen-error"),o.classList.remove("nuclen-error"),!c){a.classList.add("nuclen-error"),a.focus();return}if(!b(e)){o.classList.add("nuclen-error"),o.focus();return}t.disabled=!0;const r=t.textContent;t.textContent="Submitting...";try{if(await g(c,e,window.location.href,l),await f(c,e,l),window.NuclenOptinSuccessMessage){const i=document.createElement("div");i.className="nuclen-success-message",i.textContent=window.NuclenOptinSuccessMessage,(s=t.parentElement)==null||s.appendChild(i)}}catch(i){console.error("[Optin] Submission error:",i);const m=document.createElement("div");m.className="nuclen-error-message",m.textContent="Unable to submit. Please check your connection and try again.",(u=t.parentElement)==null||u.appendChild(m),setTimeout(()=>m.remove(),5e3),t.disabled=!1,t.textContent=r||l.submitLabel}})}export{h as attachInlineOptinHandlers,w as buildOptinInlineHTML,I as mountOptinBeforeResults};
//# sourceMappingURL=nuclen-nuclen-quiz-optin-lIyKXEt6.js.map
