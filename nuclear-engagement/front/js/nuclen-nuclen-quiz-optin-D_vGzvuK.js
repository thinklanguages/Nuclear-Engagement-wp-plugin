import{i as b,s as g,a as f}from"./nuclen-nuclen-quiz-main-BZ17qBEo.js";import"./nuclen-front.js";import"../../logger-DwRZMuf8.js";const I=n=>`
	<div id="nuclen-optin-container" class="nuclen-optin-with-results">
	<p class="nuclen-fg"><strong>${n.promptText}</strong></p>
	<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
	<input  type="text"  id="nuclen-optin-name">
	<label for="nuclen-optin-email" class="nuclen-fg">Email</label>
	<input  type="email" id="nuclen-optin-email" required>
	<button type="button" id="nuclen-optin-submit">${n.submitLabel}</button>
	</div>`;function h(n,t,i,a){var s,c;n.innerHTML=`
	<div id="nuclen-optin-container">
		<p class="nuclen-fg"><strong>${t.promptText}</strong></p>
		<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
		<input  type="text"  id="nuclen-optin-name">
		<label for="nuclen-optin-email" class="nuclen-fg">Email *</label>
		<input  type="email" id="nuclen-optin-email" required>
		<div class="nuclen-optin-btn-row">
		<button type="button" id="nuclen-optin-submit">${t.submitLabel}</button>
		</div>
		${t.mandatory?"":'<div class="nuclen-optin-skip"><a href="#" id="nuclen-optin-skip">Skip &amp; view results</a></div>'}
	</div>`,(s=document.getElementById("nuclen-optin-submit"))==null||s.addEventListener("click",async()=>{var p;const e=document.getElementById("nuclen-optin-submit"),r=document.getElementById("nuclen-optin-name"),o=document.getElementById("nuclen-optin-email"),u=r.value.trim(),l=o.value.trim();if(r.classList.remove("nuclen-error"),o.classList.remove("nuclen-error"),!u){r.classList.add("nuclen-error"),r.focus();return}if(!b(l)){o.classList.add("nuclen-error"),o.focus();return}e.disabled=!0;const m=e.textContent;e.textContent="Submitting...";try{await g(u,l,window.location.href,t),await f(u,l,t),i()}catch(v){console.error("[Optin] Submission error:",v);const d=document.createElement("div");d.className="nuclen-error-message",d.textContent="Unable to submit. Please check your connection and try again.",(p=e.parentElement)==null||p.appendChild(d),setTimeout(()=>d.remove(),5e3),e.disabled=!1,e.textContent=m||t.submitLabel}}),(c=document.getElementById("nuclen-optin-skip"))==null||c.addEventListener("click",e=>{e.preventDefault(),a()})}function B(n){var t;(t=document.getElementById("nuclen-optin-submit"))==null||t.addEventListener("click",async()=>{var o,u;const i=document.getElementById("nuclen-optin-submit"),a=document.getElementById("nuclen-optin-name"),s=document.getElementById("nuclen-optin-email"),c=a.value.trim(),e=s.value.trim();if(a.classList.remove("nuclen-error"),s.classList.remove("nuclen-error"),!c){a.classList.add("nuclen-error"),a.focus();return}if(!b(e)){s.classList.add("nuclen-error"),s.focus();return}i.disabled=!0;const r=i.textContent;i.textContent="Submitting...";try{if(await g(c,e,window.location.href,n),await f(c,e,n),n.successMessage){const l=document.createElement("div");l.className="nuclen-success-message",l.textContent=n.successMessage,(o=i.parentElement)==null||o.appendChild(l)}}catch(l){console.error("[Optin] Submission error:",l);const m=document.createElement("div");m.className="nuclen-error-message",m.textContent="Unable to submit. Please check your connection and try again.",(u=i.parentElement)==null||u.appendChild(m),setTimeout(()=>m.remove(),5e3),i.disabled=!1,i.textContent=r||n.submitLabel}})}export{B as attachInlineOptinHandlers,I as buildOptinInlineHTML,h as mountOptinBeforeResults};
//# sourceMappingURL=nuclen-nuclen-quiz-optin-D_vGzvuK.js.map
