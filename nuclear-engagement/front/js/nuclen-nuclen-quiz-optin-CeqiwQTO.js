import{i as b,s as g,a as f}from"./nuclen-nuclen-quiz-main-tW-K_tnt.js";import"./nuclen-front.js";import"../../logger-DwRZMuf8.js";const w=i=>`
	<div id="nuclen-optin-container" class="nuclen-optin-with-results">
	<p class="nuclen-fg"><strong>${i.promptText}</strong></p>
	<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
	<input  type="text"  id="nuclen-optin-name">
	<label for="nuclen-optin-email" class="nuclen-fg">Email</label>
	<input  type="email" id="nuclen-optin-email" required>
	<button type="button" id="nuclen-optin-submit">${i.submitLabel}</button>
	</div>`;function I(i,n,t,a){var s,c;i.innerHTML=`
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
	</div>`,(s=document.getElementById("nuclen-optin-submit"))==null||s.addEventListener("click",async()=>{var p;const e=document.getElementById("nuclen-optin-submit"),u=document.getElementById("nuclen-optin-name"),o=document.getElementById("nuclen-optin-email"),r=u.value.trim(),l=o.value.trim();if(u.classList.remove("nuclen-error"),o.classList.remove("nuclen-error"),!r){u.classList.add("nuclen-error"),u.focus();return}if(!b(l)){o.classList.add("nuclen-error"),o.focus();return}e.disabled=!0;const m=e.textContent;e.textContent="Submitting...";try{await g(r,l,window.location.href,n),await f(r,l,n),t()}catch{const d=document.createElement("div");d.className="nuclen-error-message",d.textContent="Unable to submit. Please check your connection and try again.",(p=e.parentElement)==null||p.appendChild(d),setTimeout(()=>d.remove(),5e3),e.disabled=!1,e.textContent=m||n.submitLabel}}),(c=document.getElementById("nuclen-optin-skip"))==null||c.addEventListener("click",e=>{e.preventDefault(),a()})}function h(i){var n;(n=document.getElementById("nuclen-optin-submit"))==null||n.addEventListener("click",async()=>{var o,r;const t=document.getElementById("nuclen-optin-submit"),a=document.getElementById("nuclen-optin-name"),s=document.getElementById("nuclen-optin-email"),c=a.value.trim(),e=s.value.trim();if(a.classList.remove("nuclen-error"),s.classList.remove("nuclen-error"),!c){a.classList.add("nuclen-error"),a.focus();return}if(!b(e)){s.classList.add("nuclen-error"),s.focus();return}t.disabled=!0;const u=t.textContent;t.textContent="Submitting...";try{if(await g(c,e,window.location.href,i),await f(c,e,i),window.NuclenOptinSuccessMessage){const l=document.createElement("div");l.className="nuclen-success-message",l.textContent=window.NuclenOptinSuccessMessage,(o=t.parentElement)==null||o.appendChild(l)}}catch{const m=document.createElement("div");m.className="nuclen-error-message",m.textContent="Unable to submit. Please check your connection and try again.",(r=t.parentElement)==null||r.appendChild(m),setTimeout(()=>m.remove(),5e3),t.disabled=!1,t.textContent=u||i.submitLabel}})}export{h as attachInlineOptinHandlers,w as buildOptinInlineHTML,I as mountOptinBeforeResults};
//# sourceMappingURL=nuclen-nuclen-quiz-optin-CeqiwQTO.js.map
