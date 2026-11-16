// Live {n}/500 counter for the rejection reason.
const reason=document.getElementById('reason');
const counter=document.querySelector('.counter');
if(reason&&counter) reason.addEventListener('input',()=>{counter.textContent=`${reason.value.length}/500`;counter.classList.toggle('over',reason.value.length>500);});
