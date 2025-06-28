/**
 * @file admin/js/onboarding-pointers.js
 * Uses WordPress' native wp-pointer script to display onboarding tips.
 */
(function($){
$(document).ready(function(){
var data = window.nePointerData;
if(!data || !Array.isArray(data.pointers) || !data.pointers.length){
return;
}
var index = 0;
function dismiss(id){
var form = new URLSearchParams();
form.append('action','nuclen_dismiss_pointer');
form.append('pointer', id);
if(data.nonce){
form.append('nonce', data.nonce);
}
fetch(data.ajaxurl, {
method: 'POST',
credentials: 'same-origin',
headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
body: form.toString()
}).catch(function(){});
}
function showNext(){
if(index >= data.pointers.length){
return;
}
var ptr = data.pointers[index];
var $target = jQuery(ptr.target);
if(!$target.length){
index++;
showNext();
return;
}
$target.pointer({
content: '<h3>' + ptr.title + '</h3><p>' + ptr.content + '</p>',
position: ptr.position,
close: function(){
dismiss(ptr.id);
index++;
showNext();
}
}).pointer('open');
}
showNext();
});
})(jQuery);
