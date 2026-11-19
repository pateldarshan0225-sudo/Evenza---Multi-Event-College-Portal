/* Presentation and client feedback only. Server registration handlers are unchanged. */
(function(){
    'use strict';
    document.addEventListener('DOMContentLoaded',()=>{
        const form=document.querySelector('.register-card-bespoke form');if(!form)return;
        form.querySelectorAll('.register-input-group').forEach(group=>{
            const label=group.querySelector('label'),field=group.querySelector('input:not([type=hidden]),select,textarea');
            if(label&&field?.id)label.htmlFor=field.id;
        });
        form.querySelectorAll('.btn-eye-toggle').forEach(button=>button.setAttribute('aria-label','Show or hide password'));
        if(form.id==='collegeRegisterForm')return; // This form already has a complete submit validator.
        const notice=document.createElement('div');notice.className='registration-client-error alert alert-danger d-none';notice.setAttribute('role','alert');notice.textContent='Please complete the highlighted fields to create your account.';form.prepend(notice);
        form.addEventListener('submit',event=>{
            for(const field of form.querySelectorAll('input[required],select[required],textarea[required]')){
                if(field.type==='hidden')continue;
                field.classList.toggle('is-invalid',!field.checkValidity());
                if(field.id&&typeof window.validateField==='function')window.validateField(field.id);
                if(!field.checkValidity())field.classList.add('is-invalid');
            }
            let missingInstitution=false;
            for(const kind of ['university','college']){
                const hidden=document.getElementById(kind+'Select');if(!hidden)continue;
                const missing=!hidden.value,display=document.getElementById(kind+'Input'),error=document.getElementById('err_'+kind+'_id');
                if(missing){missingInstitution=true;display?.classList.add('is-invalid');if(error)error.style.display='flex';}
            }
            const password=document.getElementById('registerPassword');
            const shortPassword=password&&password.value.length<6;
            if(shortPassword){password.classList.add('is-invalid');const error=document.getElementById('err_password');if(error)error.style.display='flex';}
            const invalid=!form.checkValidity()||missingInstitution||shortPassword||!!form.querySelector('.is-invalid');
            notice.classList.toggle('d-none',!invalid);
            if(invalid){event.preventDefault();form.querySelector('input.is-invalid:not([type=hidden]),select.is-invalid,textarea.is-invalid')?.focus();}
        });
    });
})();
