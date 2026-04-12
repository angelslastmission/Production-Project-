// PetCare HMS — main.js

// Navbar shadow on scroll
window.addEventListener('scroll', function () {
  var navbar = document.querySelector('.petcare-navbar');
  if (!navbar) return;
  if (window.scrollY > 20) {
    navbar.classList.add('nav-scrolled');
  } else {
    navbar.classList.remove('nav-scrolled');
  }
});

// Auto-hide flash alerts after 3s
setTimeout(function () {
  document.querySelectorAll('.alert').forEach(function (el) {
    el.style.transition = 'opacity 0.5s';
    el.style.opacity = '0';
    setTimeout(function () { el.remove(); }, 500);
  });
}, 3000);
