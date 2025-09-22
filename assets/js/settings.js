(function(){
  // Toggle visibility for secret inputs (API keys)
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.aichat-toggle-secret').forEach(function(btn){
      btn.addEventListener('click', function(){
        var targetId = btn.getAttribute('data-target');
        var input = document.getElementById(targetId);
        if(!input) return;
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
          input.type = 'text';
          if (icon) { icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); }
        } else {
          input.type = 'password';
          if (icon) { icon.classList.remove('bi-eye-slash'); icon.classList.add('bi-eye'); }
        }
      });
    });
  });
})();
