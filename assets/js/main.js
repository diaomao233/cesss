// 密码显示/隐藏
const togglePassword = document.getElementById('togglePassword');
if (togglePassword) {
  togglePassword.addEventListener('click', function () {
    const pwd = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
      pwd.type = 'text';
      icon.classList.remove('bi-eye');
      icon.classList.add('bi-eye-slash');
    } else {
      pwd.type = 'password';
      icon.classList.remove('bi-eye-slash');
      icon.classList.add('bi-eye');
    }
  });
}

// 刷新验证码
function refreshCaptcha() {
  document.getElementById('captchaImg').src = 'captcha.php?' + Math.random();
}

// 自动关闭 alert
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    const bsAlert = new bootstrap.Alert(alert);
    bsAlert.close();
  }, 5000);
});