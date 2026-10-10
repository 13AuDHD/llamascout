(() => {
    'use strict';
    const search=document.querySelector('[data-report-target-search]');
    const scope=document.querySelector('[data-report-target-scope]');
    const source=document.querySelector('[data-report-target-source]');
    const id=document.querySelector('[data-report-target-id]');
    const status=document.querySelector('[data-report-target-status]');
    const targetJson=document.getElementById('place-update-report-target-data');
    const fieldsJson=document.getElementById('place-update-scoped-fields-data');
    const placeFields=document.querySelector('[data-place-update-place-fields]');
    const scopedFields=document.querySelector('[data-place-update-scoped-fields]');
    const fieldSelect=document.querySelector('[data-scoped-field-key]');
    const answerSelect=document.querySelector('[data-scoped-field-answer]');
    const answerText=document.querySelector('[data-scoped-field-text]');
    const notice=document.querySelector('[data-scoped-field-notice]');
    const addButton=document.querySelector('[data-scoped-add-answer]');
    const queueList=document.querySelector('[data-scoped-answer-list]');
    const queueInput=document.querySelector('[data-scoped-answers-json]');
    const queue=[];
    if (![search,scope,source,id,status,targetJson,fieldsJson,placeFields,scopedFields,fieldSelect,answerSelect,answerText].every(Boolean)) return;
    let targets,fields;
    try { targets=JSON.parse(targetJson.textContent); fields=JSON.parse(fieldsJson.textContent); } catch (_) { return; }
    const labels=new Map();
    for(const t of targets) labels.set(`${t.label} (#${t.id}, ${t.scope}, ${t.source})`,t);
    const place=targets.find(t=>t.scope==='place');
    if(!place) return;
    const list=document.getElementById('place-update-report-target-options');
    list.replaceChildren();
    for(const label of labels.keys()) {const opt=document.createElement('option');opt.value=label;list.append(opt);}
    search.value=[...labels.keys()].find(label=>labels.get(label)===place)||'';
    const form=search.closest('form');
    const enabledFields=()=>fields.filter(f=>!f.derived&&!f.location&&f.scopes.includes(scope.value)&&
        ['tri','permission','rating','select','text','textarea','number','date','url'].includes(f.type));
    const fillQuestions=()=>{
        fieldSelect.replaceChildren(new Option('Select question...', ''));
        for(const f of enabledFields()) fieldSelect.add(new Option(f.label,f.key));
        fillAnswers();
    };
    const fillAnswers=()=>{
        const f=enabledFields().find(f=>f.key===fieldSelect.value);
        answerSelect.replaceChildren(new Option('Select answer...', ''));
        answerSelect.hidden=true;answerSelect.disabled=true;answerSelect.name='';
        answerText.hidden=true;answerText.disabled=true;answerText.name='';
        if(!f) return;
        const opts=f.type==='tri'?{'0':'No','1':'Yes'}:
            f.type==='permission'?{'0':'No','1':'Yes','2':'Permit'}:
            f.type==='rating'?{'1':'1/5','2':'2/5','3':'3/5','4':'4/5','5':'5/5'}:
            f.type==='select'?f.options:null;
        if(opts){
            for(const [value,label] of Object.entries(opts)) answerSelect.add(new Option(String(label),value));
            if(f.unknown) answerSelect.add(new Option('Unknown','__LLAMA_UNKNOWN__'));
            answerSelect.hidden=false;answerSelect.disabled=false;answerSelect.name='scoped_field_value';
        } else {
            answerText.type=f.type==='number'?'number':f.type==='date'?'date':f.type==='url'?'url':'text';
            answerText.value='';answerText.hidden=false;answerText.disabled=false;answerText.name='scoped_field_value';
        }
        if(notice) notice.textContent='Only this answer will be submitted for review.';
    };
    const renderQueue=()=>{
        if(!queueList || !queueInput)return;
        queueInput.value=JSON.stringify(queue);
        queueList.replaceChildren();
        for(const entry of queue){
            const item=document.createElement('li');
            const f=fields.find(f=>f.key===entry.key);
            const label=document.createElement('span');
            label.textContent=`${f?.label||entry.key}: ${entry.display}`;
            const remove=document.createElement('button');
            remove.type='button';remove.textContent='Remove';
            remove.addEventListener('click',()=>{queue.splice(queue.indexOf(entry),1);renderQueue();});
            item.append(label,' ',remove);queueList.append(item);
        }
    };
    if(addButton)addButton.addEventListener('click',()=>{
        const f=enabledFields().find(f=>f.key===fieldSelect.value);
        if(!f){status.textContent='Select a question first.';return;}
        const input=answerSelect.hidden?answerText:answerSelect;
        const value=input.value.trim();
        if(!value){status.textContent='Enter an answer before adding it.';return;}
        const display=answerSelect.hidden?value:answerSelect.selectedOptions[0]?.textContent||value;
        const previous=queue.findIndex(a=>a.key===f.key);
        if(previous!==-1)queue.splice(previous,1);
        queue.push({key:f.key,value,display});renderQueue();
        status.textContent=`${queue.length} answer(s) ready for moderation.`;
        fieldSelect.value='';fillAnswers();
    });
    let activeTarget='';
    const update=()=>{
        const t=labels.get(search.value.trim());
        if(!t){status.textContent='Select an exact option from the list.';return;}
        const targetKey=`${t.scope}:${t.source}:${t.id}`;
        const switchedTarget=targetKey!==activeTarget;
        activeTarget=targetKey;
        scope.value=t.scope;source.value=t.source;id.value=String(t.id);
        const scoped=t.scope!=='place';
        placeFields.hidden=scoped;scopedFields.hidden=!scoped;
        for(const control of placeFields.querySelectorAll('input,select,textarea,button')){
            control.disabled=scoped;
        }
        if(scoped&&switchedTarget){queue.length=0;renderQueue();fillQuestions();}
        if(!scoped){answerSelect.disabled=true;answerText.disabled=true;fieldSelect.disabled=true;}
        else{fieldSelect.disabled=false;fillAnswers();}
        status.textContent=scoped?'Area/Site answer will be submitted for moderation.':'Entire Place uses the existing update form.';
    };
    search.addEventListener('input',update);
    search.addEventListener('change',update);
    fieldSelect.addEventListener('change',fillAnswers);
    if(form) form.addEventListener('submit',event=>{
        const t=labels.get(search.value.trim());
        if(!t){event.preventDefault();status.textContent='Choose a listed reporting target.';return;}
        if(t.scope!=='place'){
            if(queue.length===0){event.preventDefault();status.textContent='Add at least one answer before submitting.';}
        }
    });
    update();
})();
