(() => {
    'use strict';
    // Changing either selector immediately applies the intended scope.
    for (const form of document.querySelectorAll('[data-publication-preview],[data-inventory-preview]')) {
        for (const select of form.querySelectorAll('select')) {
            select.addEventListener('change', () => form.requestSubmit());
        }
    }
    const root=document.querySelector('[data-resource-bulk-publication]');
    if (!root) return;
    const start=root.querySelector('[data-publication-start]');
    const pause=root.querySelector('[data-publication-pause]');
    const status=root.querySelector('[data-publication-status]');
    let running=false,pending=false,timer=null,published=0,review=0;
    function controls() {
        start.disabled=running||pending;
        pause.disabled=!running;
    }
    function stop(message) {
        running=false;
        clearTimeout(timer);
        timer=null;
        status.textContent=message;
        controls();
    }
    async function step() {
        if (!running||pending) return;
        pending=true;
        controls();
        try {
            const response=await fetch('/resource-publication-step.php',{
                method:'POST',credentials:'same-origin',cache:'no-store',
                body:new URLSearchParams({region:root.dataset.region,category:root.dataset.category,csrf_token:root.dataset.csrf}),
                headers:{Accept:'application/json'}
            });
            const data=await response.json();
            if (!response.ok||!data.ok) throw new Error(data.error||'Publication failed.');
            published+=Number(data.published)||0;
            review+=Number(data.review)||0;
            const summary=published.toLocaleString()+' published; '+review.toLocaleString()+' held for review this session.';
            if (data.done) {
                stop('Complete. '+summary+' Refresh the preview to update the list.');
                return;
            }
            if (!running) { stop('Paused. '+summary); return; }
            status.textContent=summary;
            timer=setTimeout(step,1000);
        } catch (error) {
            stop(error.message+' Earlier completed batches are saved. Start again to resume.');
        } finally { pending=false;controls(); }
    }
    start.addEventListener('click',()=>{
        if (running||pending) return;
        running=true;
        status.textContent='Publishing selected state and service…';
        controls();
        step();
    });
    pause.addEventListener('click',()=>stop(pending?'Pausing after current batch…':'Paused. Completed batches are saved.'));
    controls();
})();
