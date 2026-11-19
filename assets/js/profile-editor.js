(function(){
    'use strict';
    document.addEventListener('DOMContentLoaded',()=>{
        const form=document.getElementById('profileForm');if(!form)return;
        const file=document.getElementById('profile_image'),preview=document.getElementById('profileImagePreview'),error=document.getElementById('profileImageError');
        const original=preview.src;let objectUrl;
        const release=()=>{if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}};
        file.addEventListener('change',()=>{
            release();const image=file.files[0];file.setCustomValidity('');error.textContent='';preview.src=original;
            if(!image)return;
            if(!['image/jpeg','image/png','image/webp'].includes(image.type)||image.size>2*1024*1024){error.textContent='Choose a JPG, PNG or WEBP image up to 2 MB.';file.setCustomValidity(error.textContent);return;}
            objectUrl=URL.createObjectURL(image);preview.src=objectUrl;
        });
        form.addEventListener('reset',()=>{release();preview.src=original;error.textContent='';file.setCustomValidity('');setTimeout(()=>form.dispatchEvent(new Event('input',{bubbles:true})),0);});
        window.addEventListener('pagehide',release);
    });
})();
