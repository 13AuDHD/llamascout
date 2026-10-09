"use strict";
document.addEventListener('DOMContentLoaded',()=>{
 const search=document.getElementById('target-search');
 const select=document.getElementById('target-select');
 const go=document.getElementById('target-go');
 if(search&&select){
  search.addEventListener('input',()=>{const q=search.value.trim().toLowerCase();let first=null;
   for(const option of select.options){const visible=option.textContent.toLowerCase().includes(q);option.hidden=!visible;if(visible&&!first)first=option;}
   if(first&&select.selectedOptions[0]?.hidden)select.value=first.value;
  });
  const form=select.closest('form');
  form?.addEventListener('submit',(event)=>{const choice=select.selectedOptions[0];if(!choice||choice.hidden){event.preventDefault();return;}
   event.preventDefault();location.assign('/scoped-report.php?'+new URLSearchParams({place_id:form.querySelector('[name="place_id"]').value})+'&'+choice.value);
  });
 }
 const answerForm=document.querySelector('.scoped-answer-form');
 if(!answerForm)return;
 for(const control of answerForm.querySelectorAll('[data-scoped-field]')){
  control.addEventListener('change',()=>control.dataset.changed='1');
  if(control.tagName==='TEXTAREA'||control.tagName==='INPUT')control.addEventListener('input',()=>control.dataset.changed='1');
 }
 answerForm.addEventListener('submit',()=>{
  for(const old of answerForm.querySelectorAll('[data-scoped-dynamic]'))old.remove();
  for(const control of answerForm.querySelectorAll('[data-scoped-field][data-changed="1"]')){
   const key=control.dataset.scopedField;
   const values=control.multiple?[...control.selectedOptions].map(x=>x.value):[control.value];
   if(control.multiple && !values.length)values.push('');
   for(const val of values){const hidden=document.createElement('input');hidden.type='hidden';hidden.name=control.multiple?`answers[${key}][]`:`answers[${key}]`;hidden.value=val;hidden.dataset.scopedDynamic='1';answerForm.appendChild(hidden);}
  }
 });
});
