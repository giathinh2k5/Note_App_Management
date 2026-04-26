window.addEventListener('load', startTheCountdown);

function startTheCountdown(){
      let countdown = 5;
      let counter = document.getElementById('counter');
      let id = setInterval(()=>{
      countdown--;
      counter.innerHTML = countdown.toString();

      if(countdown === 0) {
      clearInterval(id);
      //redirect the page automatically
      window.location.href = 'login.php';
      }
      }, 1000);
}
